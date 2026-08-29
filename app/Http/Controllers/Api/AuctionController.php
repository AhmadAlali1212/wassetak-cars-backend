<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CarListingFilters;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Car; // لاستخدام نموذج السيارة
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; // لاستخدام المعاملات (Transactions)
use Illuminate\Support\Facades\Storage; // لاستخدام تخزين الملفات
use App\Services\NotificationService;
use App\Services\AuctionClosingService;
use App\Services\AuctionDepositService;

class AuctionController extends Controller

{
    /**
     * عرض جميع المزادات مع إمكانية البحث والتصفية.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function index(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            array_merge(CarListingFilters::rules(), [
                'status' => ['nullable', 'in:active'],
                'time_status' => ['nullable', 'in:upcoming,active,ended'],
                'min_price' => ['nullable', 'numeric', 'min:0'],
                'max_price' => ['nullable', 'numeric', 'min:0'],
            ])
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $yearErrors = CarListingFilters::yearRangeErrors($request);
        if ($yearErrors !== []) {
            return response()->json(['errors' => $yearErrors], 422);
        }

        if (
            $request->filled('min_price')
            && $request->filled('max_price')
            && (float) $request->max_price < (float) $request->min_price
        ) {
            return response()->json([
                'errors' => [
                    'max_price' => [
                        'يجب أن يكون الحد الأعلى للسعر أكبر من أو يساوي الحد الأدنى.',
                    ],
                ],
            ], 422);
        }

        $query = Auction::query()
            ->with(['car.user', 'winner', 'bids'])
            ->whereHas('car', function ($q) {
                $q->where('approval_status', 'approved');
            });

        // القائمة العامة: افتراضياً تعرض المزادات الجارية الآن فقط (نفس السلوك السابق)،
        // مع إمكانية تصفية صريحة عبر time_status:
        // - upcoming: مزادات مقبولة (active) لكن لم يحن وقت بدئها بعد.
        // - active:   مزادات مقبولة وجارية الآن فعلاً (بين start_time و end_time). هذا هو الافتراضي.
        // - ended:    مزادات انتهت مدتها (سواء أُغلقت فعلياً status=closed، أو ما زالت active
        //             لكن end_time مضى ولم تُغلق بعد عبر close-expired).
        $timeStatus = $request->input('time_status', 'active');

        if ($timeStatus === 'upcoming') {
            $query->where('status', 'active')
                ->where('start_time', '>', now());
        } elseif ($timeStatus === 'ended') {
            $query->where(function ($q) {
                $q->where('status', 'closed')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'active')->where('end_time', '<=', now());
                    });
            });
        } else {
            $query->where('status', 'active')
                ->where('start_time', '<=', now())
                ->where('end_time', '>', now());
        }

        /*
         * سعر المزاد المستخدم بالفلترة هو current_price،
         * وإذا كانت قيمته NULL نرجع إلى starting_price.
         */
        if ($request->filled('min_price')) {
            $query->whereRaw(
                'COALESCE(current_price, starting_price) >= ?',
                [(float) $request->min_price]
            );
        }

        if ($request->filled('max_price')) {
            $query->whereRaw(
                'COALESCE(current_price, starting_price) <= ?',
                [(float) $request->max_price]
            );
        }

        CarListingFilters::applyThroughCarRelation($query, $request);

        $perPage = (int) $request->input('per_page', 15);
        $orderColumn = $timeStatus === 'upcoming' ? 'start_time' : 'end_time';
        $orderDirection = $timeStatus === 'ended' ? 'desc' : 'asc';
        $auctions = $query
            ->orderBy($orderColumn, $orderDirection)
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'تم جلب المزادات بنجاح.',
            'auctions' => $auctions,
        ]);
    }


    /**
     * عرض مزادات المستخدم الحالي بجميع حالاتها.
     */
    public function myAuctions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', 'in:pending,active,closed,cancelled'],
            'time_status' => ['nullable', 'in:upcoming,active,ended'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Auction::query()
            ->where('user_id', $request->user()->id)
            ->with(['car', 'winner'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فلترة زمنية اختيارية إضافية (تُطبَّق فوق فلترة status إن وُجدت):
        // - upcoming: لم يحن وقت بدء المزاد بعد.
        // - active:   المزاد جارٍ الآن فعلاً.
        // - ended:    انتهت مدة المزاد (سواء أُغلق أو ما زال بانتظار الإغلاق التلقائي).
        if ($request->filled('time_status')) {
            match ($request->input('time_status')) {
                'upcoming' => $query->where('start_time', '>', now()),
                'active' => $query->where('start_time', '<=', now())->where('end_time', '>', now()),
                'ended' => $query->where('end_time', '<=', now()),
                default => null,
            };
        }

        $perPage = (int) $request->input('per_page', 15);

        return response()->json([
            'message' => 'تم جلب مزاداتك بنجاح.',
            'auctions' => $query->paginate($perPage)->withQueryString(),
        ]);
    }

    /**
     * عرض تفاصيل مزاد محدد.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $auction = Auction::with('car.user', 'winner', 'bids.user')->find($id);

        if (!$auction) {
            return response()->json(['message' => 'المزاد غير موجود.'], 404);
        }

        // إصلاح: إذا انتهى الوقت لكن الحالة ما زالت "active" (لم يمرّ عليها
        // الـ cron بعد)، نغلقه فوراً هنا بدل ترك المستخدم يشوف مزاداً "منتهي
        // لكن ما زال يبدو نشطاً". هذا يجعل الإغلاق موثوقاً حتى لو تأخرت
        // دورة الجدولة.
        if (
            $auction->status === 'active'
            && $auction->end_time
            && now()->greaterThanOrEqualTo($auction->end_time)
        ) {
            try {
                $auction = \App\Services\AuctionClosingService::closeExpiredAuction($auction)
                    ->load('car.user', 'winner', 'bids.user');
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $isWithinActiveWindow = $auction->status === 'active'
            && $auction->car
            && $auction->car->approval_status === 'approved'
            && now()->greaterThanOrEqualTo($auction->start_time)
            && now()->lessThan($auction->end_time);

        if ($isWithinActiveWindow) {
            return response()->json([
                'message' => 'تم جلب تفاصيل المزاد بنجاح.',
                'auction' => $auction,
            ]);
        }

        // المزاد ليس "active" ضمن نافذته الزمنية الحالية (منتهي/مغلق/ملغى/
        // بانتظار الموافقة). كان هذا سابقاً يُرجع 404 دائماً، مما يكسر شاشة
        // التفاصيل لأي مزاد منتهي. الآن: نسمح بالوصول لصاحب المزاد، الفائز،
        // أو أي مستخدم شارك بمزايدة عليه — أما بقية الزوار العامين فتبقى محجوبة.
        $user = $request->user();
        $isParticipant = $user && (
            (int) $auction->user_id === (int) $user->id
            || (int) $auction->winner_id === (int) $user->id
            || $auction->bids->contains('user_id', $user->id)
        );

        if (!$isParticipant) {
            return response()->json(['message' => 'المزاد غير موجود أو غير نشط حالياً.'], 404);
        }

        return response()->json([
            'message' => 'تم جلب تفاصيل المزاد بنجاح.',
            'auction' => $auction,
        ]);
    }

    /**
     * إضافة سيارة للمزاد (إنشاء مزاد جديد).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // التحقق من أن المستخدم لديه نوع حساب يسمح له بإضافة مزادات (فردي، شركة)
        if (!in_array($user->accountType->name, ['individual', 'company'])) {
            return response()->json(['message' => 'ليس لديك الصلاحية لإضافة مزادات.'], 403);
        }

        // التحقق من وجود اشتراك نشط
        if (!$user->hasActiveSubscription()) {
            return response()->json(['message' => 'يجب أن يكون لديك اشتراك نشط لإضافة مزادات. يرجى الاشتراك أولاً.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'car_id' => 'required|exists:cars,id',
            'starting_price' => 'required|numeric|min:0',
            'duration_hours' => 'required|integer|min:1|max:720',
            'start_time' => 'nullable|date|after_or_equal:now',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();

        try {
            // نقفل سجل السيارة حتى لا يتم إنشاء مزادين متزامنين لنفس السيارة.
            $car = Car::where('id', $request->car_id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$car) {
                return response()->json(['message' => 'السيارة غير موجودة أو لا تنتمي لك.'], 404);
            }

            // التحقق من أن السيارة معتمدة (approved) من الإدارة قبل السماح بإضافتها لمزاد
            if ($car->approval_status !== 'approved') {
                DB::rollBack();
                return response()->json([
                    'message' => '❌ لا يمكن إضافة هذه السيارة لمزاد.',
                    'reason' => match ($car->approval_status) {
                        'pending' => 'السيارة بانتظار موافقة الإدارة أولاً.',
                        'rejected' => 'تم رفض هذه السيارة من قبل الإدارة.',
                        default => 'السيارة في حالة غير صحيحة (' . $car->approval_status . ').',
                    },
                ], 422);
            }

            // لا نسمح بأكثر من مزاد pending/active لنفس السيارة.
            $hasOpenAuction = Auction::where('car_id', $car->id)
                ->whereIn('status', ['pending', 'active'])
                ->exists();

            if ($hasOpenAuction) {
                DB::rollBack();
                return response()->json([
                    'message' => 'يوجد مزاد قائم أو قيد المراجعة لهذه السيارة بالفعل.',
                ], 409);
            }

            $auction = Auction::create([
                'car_id' => $car->id,
                'user_id' => $user->id,
                'starting_price' => $request->starting_price,
                'current_price' => $request->starting_price,
                'start_time' => $request->filled('start_time') ? $request->start_time : now(),
                'end_time' => ($request->filled('start_time') ? \Illuminate\Support\Carbon::parse($request->start_time) : now())
                    ->addHours($request->duration_hours),
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'تم إضافة المزاد بنجاح. سيتم مراجعة المزاد قبل النشر.',
                'auction' => $auction->load('car'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ أثناء إنشاء المزاد.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * المزايدة على مزاد.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */

    public function placeBid(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // نحدد "أعلى مزايد سابق" قبل تنفيذ المزايدة الجديدة، لنرسل له إشعار
            // "تم تجاوز مزايدتك" بعدها — كان هذا الإشعار مفقوداً بالكامل سابقاً.
            $previousTopBid = \App\Models\Bid::where('auction_id', $id)
                ->where('is_deposit_held', true)
                ->where('user_id', '!=', $request->user()->id)
                ->orderByDesc('amount')
                ->orderByDesc('id')
                ->first();

            $bid = AuctionDepositService::placeBid(
                (int) $id,
                $request->user(),
                round((float) $request->amount, 2)
            );

            $auction = $bid->auction()->firstOrFail();

            NotificationService::send(
                $auction->user_id,
                'مزايدة جديدة على مزادك',
                'تم تقديم مزايدة جديدة بقيمة ' . number_format((float) $bid->amount, 2) . '.',
                'new_bid',
                [
                    'auction_id' => $auction->id,
                    'bid_id' => $bid->id,
                    'bidder_id' => $bid->user_id,
                    'amount' => (float) $bid->amount,
                    'deposit_amount' => (float) $bid->deposit_amount,
                ]
            );

            // ⬅️ جديد: إشعار المزايد السابق بأن مزايدته تم تجاوزها
            if ($previousTopBid && (int) $previousTopBid->user_id !== (int) $bid->user_id) {
                NotificationService::send(
                    $previousTopBid->user_id,
                    'تم تجاوز مزايدتك',
                    'قام مستخدم آخر بتقديم مزايدة أعلى من مزايدتك بقيمة '
                        . number_format((float) $bid->amount, 2) . '. عربونك السابق تم تحريره تلقائياً.',
                    'bid_outbid',
                    [
                        'auction_id' => $auction->id,
                        'new_amount' => (float) $bid->amount,
                    ]
                );
            }

            return response()->json([
                'message' => 'تمت المزايدة وحجز العربون بنجاح.',
                'bid' => $bid,
                'deposit_amount' => (float) $bid->deposit_amount,
                'auction_current_price' => (float) $auction->current_price,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'حدث خطأ أثناء المزايدة.',
            ], 500);
        }
    }

    /**
     * تحديث مزاد (للمدير أو البائع).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $auction = Auction::find($id);

        if (!$auction) {
            return response()->json(['message' => 'المزاد غير موجود.'], 404);
        }

        // السماح للمدير أو البائع بتعديل المزاد
        if ($user->accountType->name !== 'admin' && $auction->user_id !== $user->id) {
            return response()->json(['message' => 'ليس لديك صلاحية لتعديل هذا المزاد.'], 403);
        }

        // منع التعديل إذا كان المزاد نشطاً أو مغلقاً (باستثناء المدير لتغيير الحالة)
        if ($auction->status === 'active' || $auction->status === 'closed') {
            if ($user->accountType->name !== 'admin' || !$request->has('status')) {
                return response()->json(['message' => 'لا يمكن تعديل المزاد بعد أن أصبح نشطاً أو مغلقاً.'], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'make' => 'sometimes|required|string|max:255',
            'model' => 'sometimes|required|string|max:255',
            'year' => 'sometimes|required|integer|min:1900|max:' . (date('Y') + 1),
            'condition' => 'sometimes|required|string|in:new,used',
            'description' => 'nullable|string',
            'location' => 'sometimes|required|string|max:255',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'starting_price' => 'sometimes|numeric|min:0',
            'duration_hours' => 'sometimes|integer|min:1|max:720',
            'status' => 'sometimes|string|in:pending,active,closed,cancelled', // يمكن للمدير تغيير الحالة
            'winner_id' => 'nullable|exists:users,id', // يمكن للمدير تعيين الفائز يدوياً
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();

        try {
            // تحديث بيانات السيارة
            $car = $auction->car;
            if ($request->has('make')) $car->make = $request->make;
            if ($request->has('model')) $car->model = $request->model;
            if ($request->has('year')) $car->year = $request->year;
            if ($request->has('condition')) $car->condition = $request->condition;
            if ($request->has('description')) $car->description = $request->description;
            if ($request->has('location')) $car->location = $request->location;

            if ($request->hasFile('images')) {
                foreach ($car->images as $oldImage) {
                    Storage::delete(str_replace('/storage/', 'public/', $oldImage));
                }
                $imagePaths = [];
                foreach ($request->file('images') as $image) {
                    $path = $image->store('public/cars_images');
                    $imagePaths[] = Storage::url($path);
                }
                $car->images = $imagePaths;
            }
            $car->save();

            // تحديث بيانات المزاد
            if ($request->has('starting_price')) $auction->starting_price = $request->starting_price;
            if ($request->has('duration_hours')) {
                // أثناء pending نحافظ على مدة المزاد كفترة كاملة، ثم تبدأ فعلياً عند التفعيل.
                $baseStart = now();
                $auction->start_time = $baseStart;
                $auction->end_time = $baseStart->copy()->addHours((int) $request->duration_hours);
            }
            if ($request->has('status')) {
                if ($request->status === 'active' && $auction->status === 'pending') {
                    $durationSeconds = max(
                        3600,
                        $auction->start_time->diffInSeconds($auction->end_time)
                    );

                    $activationTime = now();
                    $auction->start_time = $activationTime;
                    $auction->end_time = $activationTime->copy()->addSeconds($durationSeconds);
                }

                if ($request->status === 'cancelled' && $auction->status !== 'cancelled') {
                    AuctionDepositService::releaseAllHeldDeposits(
                        $auction,
                        'إعادة العربون بسبب إلغاء المزاد'
                    );
                }

                $auction->status = $request->status;
            }
            if ($request->has('winner_id')) $auction->winner_id = $request->winner_id;
            $auction->save();

            DB::commit();

            return response()->json([
                'message' => 'تم تحديث المزاد بنجاح.',
                'auction' => $auction->load('car'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ أثناء تحديث المزاد.', 'error' => $e->getMessage()], 500);
        }
    }

    //
    public function closeExpiredAuctions()
    {
        $expiredAuctions = Auction::where('status', 'active')
            ->where('end_time', '<=', now())
            ->get();

        $results = [];

        foreach ($expiredAuctions as $auction) {
            try {
                $closedAuction = AuctionClosingService::closeExpiredAuction($auction);

                $results[] = [
                    'auction_id' => $closedAuction->id,
                    'status' => $closedAuction->status,
                    'winner_id' => $closedAuction->winner_id,
                    'current_price' => $closedAuction->current_price,
                    'final_price_status' => $closedAuction->final_price_status,
                    'closed_reason' => $closedAuction->closed_reason,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'auction_id' => $auction->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'تم فحص وإغلاق المزادات المنتهية.',
            'results' => $results,
        ]);
    }

    public function acceptFinalPrice(Request $request, $id)
    {
        try {
            $auction = DB::transaction(function () use ($request, $id) {
                $auction = Auction::query()->lockForUpdate()->find($id);

                if (!$auction) {
                    throw new \RuntimeException('المزاد غير موجود.');
                }

                if ($auction->user_id !== $request->user()->id) {
                    throw new \RuntimeException('ليس لديك صلاحية لقبول السعر النهائي لهذا المزاد.');
                }

                if ($auction->status !== 'closed' || !$auction->winner_id) {
                    throw new \RuntimeException('لا يمكن قبول السعر قبل إغلاق المزاد وتحديد الفائز.');
                }

                if ($auction->final_price_status !== 'pending') {
                    throw new \RuntimeException('تم اتخاذ قرار بشأن السعر النهائي مسبقاً.');
                }

                // يبقى العربون محجوزاً إلى أن يؤكد البائع إتمام الصفقة أو يتراجع الفائز.
                $settlementDays = max(1, (int) config('services.auction.settlement_days', 7));

                $auction->update([
                    'final_price_status' => 'accepted',
                    'final_price_decided_at' => now(),
                    'settlement_completed_at' => null,
                    'settlement_deadline_at' => now()->addDays($settlementDays),
                    'winner_withdrew_at' => null,
                ]);

                return $auction;
            }, 5);

            NotificationService::send(
                $auction->winner_id,
                'تم قبول السعر النهائي للمزاد',
                'وافق البائع على السعر. يبقى العربون محجوزاً حتى تأكيد إتمام الصفقة خارج التطبيق.',
                'auction_final_price_accepted',
                [
                    'auction_id' => $auction->id,
                    'current_price' => (float) $auction->current_price,
                ]
            );

            return response()->json([
                'message' => 'تم قبول السعر، والعربون ما زال محجوزاً حتى إتمام الصفقة.',
                'auction' => $auction->fresh(['car', 'user', 'winner']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function rejectFinalPrice(Request $request, $id)
    {
        try {
            $auction = DB::transaction(function () use ($request, $id) {
                $auction = Auction::query()->lockForUpdate()->find($id);

                if (!$auction) {
                    throw new \RuntimeException('المزاد غير موجود.');
                }

                if ($auction->user_id !== $request->user()->id) {
                    throw new \RuntimeException('ليس لديك صلاحية لرفض السعر النهائي لهذا المزاد.');
                }

                if ($auction->status !== 'closed' || !$auction->winner_id) {
                    throw new \RuntimeException('لا يمكن رفض السعر قبل إغلاق المزاد وتحديد الفائز.');
                }

                if ($auction->final_price_status !== 'pending') {
                    throw new \RuntimeException('تم اتخاذ قرار بشأن السعر النهائي مسبقاً.');
                }

                AuctionDepositService::releaseWinnerDeposit(
                    $auction,
                    'تحرير العربون بعد رفض البائع للسعر النهائي'
                );

                $auction->update([
                    'final_price_status' => 'rejected',
                    'final_price_decided_at' => now(),
                    'settlement_deadline_at' => null,
                ]);

                return $auction;
            }, 5);

            NotificationService::send(
                $auction->winner_id,
                'تم رفض السعر النهائي للمزاد',
                'رفض البائع السعر النهائي، وأُعيد العربون إلى رصيدك المتاح.',
                'auction_final_price_rejected',
                [
                    'auction_id' => $auction->id,
                    'current_price' => (float) $auction->current_price,
                ]
            );

            return response()->json([
                'message' => 'تم رفض السعر وإعادة عربون الفائز.',
                'auction' => $auction->fresh(['car', 'user', 'winner']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
    //


    /** يؤكد البائع أن الصفقة تمت خارج التطبيق، فيُحرر عربون الفائز. */
    public function completeSale(Request $request, $id)
    {
        try {
            $auction = DB::transaction(function () use ($request, $id) {
                $auction = Auction::query()->lockForUpdate()->find($id);

                if (!$auction) {
                    throw new \RuntimeException('المزاد غير موجود.');
                }

                if ($auction->user_id !== $request->user()->id) {
                    throw new \RuntimeException('ليس لديك صلاحية لإتمام صفقة هذا المزاد.');
                }

                if ($auction->final_price_status !== 'accepted') {
                    throw new \RuntimeException('يجب قبول السعر النهائي قبل تأكيد إتمام الصفقة.');
                }

                $bid = AuctionDepositService::releaseWinnerDeposit(
                    $auction,
                    'تحرير العربون بعد تأكيد إتمام صفقة البيع'
                );

                if (!$bid) {
                    throw new \RuntimeException('لا يوجد عربون فائز محجوز لهذه الصفقة.');
                }

                $auction->update([
                    'final_price_status' => 'completed',
                    'settlement_completed_at' => now(),
                    'settlement_deadline_at' => null,
                ]);

                return $auction;
            }, 5);

            NotificationService::send(
                $auction->winner_id,
                'تم تأكيد إتمام صفقة المزاد',
                'أكد البائع إتمام الصفقة وأُعيد العربون إلى رصيدك المتاح.',
                'auction_sale_completed',
                ['auction_id' => $auction->id]
            );

            // ⬅️ جديد: الآن أصبحت هذه الصفقة قابلة للتقييم فعلياً — ندفع
            // الطرفين نحو شاشة "تقييمات معلّقة" بدل تركهم يكتشفون ذلك بأنفسهم.
            foreach ([$auction->user_id, $auction->winner_id] as $userId) {
                NotificationService::send(
                    $userId,
                    'يمكنك الآن تقييم هذه الصفقة',
                    "اكتملت صفقة المزاد على \"{$auction->car->make} {$auction->car->model}\". شاركنا رأيك بتقييم سريع.",
                    'ratable_transaction',
                    ['type' => 'auction', 'transaction_id' => $auction->id]
                );
            }

            return response()->json([
                'message' => 'تم تأكيد الصفقة وتحرير عربون الفائز.',
                'auction' => $auction->fresh(['winner']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /** يتراجع الفائز بعد قبول السعر، فيُصادر العربون لصالح البائع. */
    public function winnerWithdraw(Request $request, $id)
    {
        try {
            $auction = DB::transaction(function () use ($request, $id) {
                $auction = Auction::query()->lockForUpdate()->find($id);

                if (!$auction) {
                    throw new \RuntimeException('المزاد غير موجود.');
                }

                if ($auction->winner_id !== $request->user()->id) {
                    throw new \RuntimeException('لست الفائز بهذا المزاد.');
                }

                if ($auction->final_price_status !== 'accepted') {
                    throw new \RuntimeException('لا يمكن التراجع إلا بعد قبول البائع للسعر وقبل إتمام الصفقة.');
                }

                $bid = AuctionDepositService::forfeitWinnerDeposit(
                    $auction,
                    'مصادرة العربون بسبب تراجع الفائز بعد قبول السعر'
                );

                if (!$bid) {
                    throw new \RuntimeException('لا يوجد عربون محجوز قابل للمصادرة.');
                }

                $auction->update([
                    'final_price_status' => 'winner_withdrew',
                    'winner_withdrew_at' => now(),
                    'settlement_deadline_at' => null,
                ]);

                return $auction;
            }, 5);

            NotificationService::send(
                $auction->user_id,
                'تراجع الفائز عن الشراء',
                'تمت مصادرة عربون الفائز وإضافته إلى رصيد محفظتك كتعويض.',
                'auction_winner_withdrew',
                ['auction_id' => $auction->id]
            );

            return response()->json([
                'message' => 'تم تسجيل التراجع ومصادرة العربون لصالح البائع.',
                'auction' => $auction->fresh(['winner']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * حذف مزاد.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $auction = Auction::find($id);

        if (!$auction) {
            return response()->json(['message' => 'المزاد غير موجود.'], 404);
        }

        if ($user->accountType->name !== 'admin' && $auction->user_id !== $user->id) {
            return response()->json(['message' => 'ليس لديك صلاحية لحذف هذا المزاد.'], 403);
        }

        if ($auction->final_price_status === 'accepted') {
            return response()->json([
                'message' => 'لا يمكن حذف مزاد توجد له صفقة مقبولة وعربون قيد التسوية.',
            ], 409);
        }

        DB::transaction(function () use ($auction) {
            AuctionDepositService::releaseAllHeldDeposits(
                $auction,
                'إعادة العربون بسبب حذف أو إلغاء المزاد'
            );

            $auction->delete();
        }, 5);

        return response()->json(['message' => 'تم حذف المزاد وإعادة أي عربون محجوز.']);
    }

    /**
     * وظيفة مساعدة لإنهاء المزادات المنتهية وتحديد الفائز.
     * يمكن استدعاؤها عبر Cron Job أو Event Listener.
     *
     * @return void
     */


}
