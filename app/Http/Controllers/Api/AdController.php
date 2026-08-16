<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CarListingFilters;
use App\Models\Ad;
use App\Models\Car;
use App\Models\RentalAd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdController extends Controller
{
    /**
     * إنشاء إعلان بيع جديد لسيارة موجودة
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->accountType->name, ['individual', 'company'], true)) {
            return response()->json(['message' => 'ليس لديك الصلاحية لإضافة إعلانات بيع.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'car_id' => 'required|integer|exists:cars,id',
            'price' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $ad = DB::transaction(function () use ($request, $user) {
            // قفل صف السيارة يجعل إنشاء إعلان البيع والتأجير متسلسلاً لنفس السيارة.
            $car = Car::query()
                ->whereKey($request->car_id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$car) {
                throw ValidationException::withMessages([
                    'car_id' => ['السيارة غير موجودة أو لا تابعة لك.'],
                ]);
            }

            if ($car->approval_status !== 'approved') {
                throw ValidationException::withMessages([
                    'car_id' => [match ($car->approval_status) {
                        'pending' => 'السيارة بانتظار موافقة الإدارة أولاً.',
                        'rejected' => 'تم رفض هذه السيارة من قبل الإدارة.',
                        default => 'السيارة في حالة غير صحيحة (' . $car->approval_status . ').',
                    }],
                ]);
            }

            $existingRentalAd = RentalAd::query()
                ->where('car_id', $car->id)
                ->whereIn('status', ['pending', 'active', 'rented'])
                ->first();

            if ($existingRentalAd) {
                throw ValidationException::withMessages([
                    'car_id' => ['هذه السيارة مرتبطة بإعلان تأجير قائم. يجب اختيار البيع أو التأجير فقط.'],
                ]);
            }

            // لا ننشئ صف إعلان جديد لنفس السيارة؛ expired يُجدد وrejected يُعدل ثم يعاد للمراجعة.
            $existingAd = Ad::query()
                ->where('car_id', $car->id)
                ->first();

            if ($existingAd) {
                throw ValidationException::withMessages([
                    'car_id' => [match ($existingAd->status) {
                        'active' => 'يوجد إعلان بيع نشط بالفعل لهذه السيارة.',
                        'expired' => 'يوجد إعلان منتهي لهذه السيارة. استخدم مسار تجديد الإعلان بدلاً من إنشاء إعلان جديد.',
                        'draft' => 'يوجد إعلان مسودة ناتج عن رفع جماعي. حدّد سعره أولاً.',
                        'pending' => 'يوجد إعلان بانتظار مراجعة الإدارة.',
                        'rejected' => 'يوجد إعلان مرفوض لهذه السيارة. عدّله وأعد إرساله للمراجعة.',
                        'sold' => 'هذه السيارة مسجلة كمباعة ولا يمكن إنشاء إعلان جديد لها.',
                        default => 'يوجد إعلان سابق لهذه السيارة.',
                    }],
                ]);
            }

            return Ad::create([
                'car_id' => $car->id,
                'user_id' => $user->id,
                'price' => $request->price,
                'status' => 'pending',
                'rejection_reason' => null,
                'published_at' => null,
                'expires_at' => null,
                'expiration_reminder_sent_at' => null,
            ]);
        });

        return response()->json([
            'message' => '✅ تم إنشاء الإعلان بنجاح! ⏳ الإعلان بانتظار موافقة الإدارة.',
            'status' => 'pending',
            'ad' => [
                'id' => $ad->id,
                'car_id' => $ad->car_id,
                'user_id' => $ad->user_id,
                'price' => $ad->price,
                'status' => $ad->status,
                'published_at' => $ad->published_at,
                'expires_at' => $ad->expires_at,
                'created_at' => $ad->created_at,
            ],
        ], 201);
    }

    /**
     * عرض إعلانات المستخدمين الآخرين (النشطة) مع إمكانية البحث والفلترة
     */
    public function otherUsersAds(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            array_merge(CarListingFilters::rules(), [
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

        if ($request->filled('min_price') && $request->filled('max_price')
            && (float) $request->max_price < (float) $request->min_price) {
            return response()->json([
                'errors' => ['max_price' => ['يجب أن يكون الحد الأعلى للسعر أكبر من أو يساوي الحد الأدنى.']],
            ], 422);
        }

        $user = $request->user();

        $query = Ad::query()
            ->where('user_id', '!=', $user->id)
            ->where('status', 'active')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->with(['car', 'user'])
            ->whereHas('car', function ($q) {
                $q->where('approval_status', 'approved');
            });

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        CarListingFilters::applyThroughCarRelation($query, $request);

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $formattedAds = $paginator->getCollection()->map(function ($ad) {
            return [
                'id' => $ad->id,
                'car_id' => $ad->car_id,
                'user_id' => $ad->user_id,
                'price' => $ad->price,
                'status' => $ad->status,
                'published_at' => $ad->published_at,
                'expires_at' => $ad->expires_at,
                'can_renew' => $ad->status === 'expired'
                    || ($ad->status === 'active' && $ad->expires_at && $ad->expires_at->lte(now())),
                'created_at' => $ad->created_at,
                'car' => [
                    'id' => $ad->car->id,
                    'make' => $ad->car->make,
                    'model' => $ad->car->model,
                    'year' => $ad->car->year,
                    'condition' => $ad->car->condition,
                    'location' => $ad->car->location,
                    'latitude' => $ad->car->latitude,
                    'longitude' => $ad->car->longitude,
                    'images' => $ad->car->images,
                ],
                'seller' => [
                    'id' => $ad->user->id,
                    'name' => $ad->user->name,
                    'phone' => $ad->user->phone_number,
                ],
            ];
        })->values();

        return response()->json([
            'message' => '✅ تم جلب إعلانات المستخدمين الآخرين بنجاح.',
            'count' => $paginator->total(),
            'ads' => $formattedAds,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * عرض إعلانات المستخدم الحالي
     */
    public function myAds(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:draft,pending,active,expired,sold,rejected',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Ad::query()
            ->where('user_id', $request->user()->id)
            ->with('car');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->latest()->paginate($perPage);

        $formattedAds = $paginator->getCollection()->map(function ($ad) {
            $canRenew = $ad->status === 'expired'
                || ($ad->status === 'active' && $ad->expires_at && $ad->expires_at->lte(now()));

            return [
                'id' => $ad->id,
                'car_id' => $ad->car_id,
                'price' => $ad->price,
                'status' => $ad->status,
                'published_at' => $ad->published_at,
                'expires_at' => $ad->expires_at,
                'can_renew' => $canRenew,
                'created_at' => $ad->created_at,
                'car' => $ad->car ? [
                    'id' => $ad->car->id,
                    'make' => $ad->car->make,
                    'model' => $ad->car->model,
                    'year' => $ad->car->year,
                    'condition' => $ad->car->condition,
                ] : null,
            ];
        })->values();

        return response()->json([
            'message' => 'تم جلب إعلاناتك بنجاح.',
            'count' => $paginator->total(),
            'ads' => $formattedAds,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * عرض إعلان محدد للمستخدم الحالي
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $ad = Ad::where('id', $id)
                ->where('user_id', $user->id)
                ->with('car')
                ->first();

        if (!$ad) {
            return response()->json(['message' => 'الإعلان غير موجود.'], 404);
        }

        return response()->json([
            'message' => 'تم جلب تفاصيل الإعلان بنجاح.',
            'ad' => [
                'id' => $ad->id,
                'car_id' => $ad->car_id,
                'user_id' => $ad->user_id,
                'price' => $ad->price,
                'status' => $ad->status,
                'published_at' => $ad->published_at,
                'expires_at' => $ad->expires_at,
                'created_at' => $ad->created_at,
                'car' => [
                    'id' => $ad->car->id,
                    'make' => $ad->car->make,
                    'model' => $ad->car->model,
                    'year' => $ad->car->year,
                    'condition' => $ad->car->condition,
                    'description' => $ad->car->description,
                    'location' => $ad->car->location,
                    'latitude' => $ad->car->latitude,
                    'longitude' => $ad->car->longitude,
                    'images' => $ad->car->images,
                ],
            ],
        ]);
    }

    /**
     * تحديث إعلان.
     *
     * أي تعديل على السعر يعيد الإعلان تلقائياً لحالة "pending" ليخضع لإعادة مراجعة
     * الإدارة قبل أن يُعتبر نشطاً مجدداً (نفس سلوك إعلان التأجير والعرض الترويجي).
     *
     * إصلاحات:
     * 1. يمنع تعديل إعلان بحالة "sold" — لا يجوز "إعادة فتح" صفقة بيع مكتملة فعلياً
     *    بمجرد إرسال تحديث سعر جديد.
     * 2. يمنع التعديل إذا كانت السيارة المرتبطة مرفوضة نهائياً من الإدارة.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'price' => 'sometimes|required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = $request->user();
        $snapshot = Ad::query()
            ->whereKey($id)
            ->where('user_id', $user->id)
            ->first();

        if (!$snapshot) {
            return response()->json(['message' => 'الإعلان غير موجود.'], 404);
        }

        $ad = DB::transaction(function () use ($snapshot, $request, $user) {
            $car = Car::query()
                ->whereKey($snapshot->car_id)
                ->lockForUpdate()
                ->first();

            $ad = Ad::query()
                ->whereKey($snapshot->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$ad || !$car) {
                abort(404, 'الإعلان أو السيارة غير موجودين.');
            }

            if ($ad->status === 'sold') {
                throw ValidationException::withMessages([
                    'status' => ['السيارة تم بيعها بالفعل، ولا يمكن إعادة فتح الإعلان أو تعديل سعره.'],
                ]);
            }

            if ($car->approval_status === 'rejected') {
                throw ValidationException::withMessages([
                    'car_id' => ['السيارة المرتبطة بهذا الإعلان مرفوضة نهائياً من الإدارة.'],
                ]);
            }

            $hasRentalConflict = RentalAd::query()
                ->where('car_id', $car->id)
                ->whereIn('status', ['pending', 'active', 'rented'])
                ->exists();

            if ($hasRentalConflict) {
                throw ValidationException::withMessages([
                    'car_id' => ['لا يمكن إعادة إرسال إعلان البيع لأن السيارة مرتبطة بإعلان تأجير قائم.'],
                ]);
            }

            if ($request->has('price')) {
                $ad->price = $request->price;
            }

            $ad->status = 'pending';
            $ad->rejection_reason = null;
            $ad->published_at = null;
            $ad->expires_at = null;
            $ad->expiration_reminder_sent_at = null;
            $ad->save();

            return $ad->fresh('car');
        });

        return response()->json([
            'message' => '✅ تم تحديث الإعلان بنجاح. ⏳ في انتظار إعادة مراجعة الإدارة.',
            'ad' => $ad,
        ]);
    }

    /**
     * نشر إعلان بيع (السماح بنشره عدة مرات)
     * الشروط:
     * 1. حالة الإعلان يجب أن تكون active (موافقة من الأدمن)
     * 2. حالة السيارة يجب أن تكون approved (ليست pending)
     * 3. يمكن نشره أكثر من مرة
     */
    public function publish(Request $request, $id)
    {
        $user = $request->user();

        $snapshot = Ad::query()
            ->whereKey($id)
            ->where('user_id', $user->id)
            ->first();

        if (!$snapshot) {
            return response()->json(['message' => 'الإعلان غير موجود.'], 404);
        }

        $ad = DB::transaction(function () use ($snapshot, $user) {
            // نفس ترتيب القفل المستخدم في إنشاء إعلانات البيع والتأجير.
            $car = Car::query()
                ->whereKey($snapshot->car_id)
                ->lockForUpdate()
                ->first();

            $ad = Ad::query()
                ->whereKey($snapshot->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$ad || !$car) {
                abort(404, 'الإعلان أو السيارة غير موجودين.');
            }

            if ($ad->status !== 'active') {
                throw ValidationException::withMessages([
                    'status' => [match ($ad->status) {
                        'pending' => 'الإعلان بانتظار موافقة الإدارة.',
                        'rejected' => 'الإعلان مرفوض ويجب تعديله وإعادة إرساله للمراجعة.',
                        'expired' => 'انتهت مدة الإعلان. استخدم مسار التجديد مع تأكيد توفر السيارة.',
                        'sold' => 'السيارة تم بيعها ولا يمكن نشر الإعلان.',
                        default => 'الإعلان في حالة لا تسمح بالنشر.',
                    }],
                ]);
            }

            if ($ad->expires_at && $ad->expires_at->lte(now())) {
                $ad->update(['status' => 'expired']);

                throw ValidationException::withMessages([
                    'status' => ['انتهت مدة الإعلان. استخدم مسار التجديد مع تأكيد أن السيارة ما زالت متاحة.'],
                ]);
            }

            if ($car->approval_status !== 'approved') {
                throw ValidationException::withMessages([
                    'car_id' => ['السيارة يجب أن تكون معتمدة من الإدارة أولاً.'],
                ]);
            }

            if ((float) $ad->price <= 0) {
                throw ValidationException::withMessages([
                    'price' => ['يجب أن يكون سعر الإعلان أكبر من صفر.'],
                ]);
            }

            $hasRentalConflict = RentalAd::query()
                ->where('car_id', $ad->car_id)
                ->whereIn('status', ['pending', 'active', 'rented'])
                ->exists();

            if ($hasRentalConflict) {
                throw ValidationException::withMessages([
                    'car_id' => ['لا يمكن نشر إعلان البيع لأن السيارة مرتبطة بإعلان تأجير قائم.'],
                ]);
            }

            // إذا كانت الإدارة قد ضبطت تاريخ النشر والانتهاء، لا نمدد المدة كلما أُعيد الطلب.
            if ($ad->published_at && $ad->expires_at && $ad->expires_at->isFuture()) {
                return $ad;
            }

            $ad->update([
                'published_at' => now(),
                'expires_at' => now()->addDays(30),
                'expiration_reminder_sent_at' => null,
                'rejection_reason' => null,
            ]);

            return $ad->fresh('car');
        });

        return response()->json([
            'message' => '✅ الإعلان منشور لمدة 30 يوماً.',
            'ad' => $ad->loadMissing('car'),
        ]);
    }


    /**
     * تجديد إعلان بيع منتهٍ لمدة 30 يوماً جديدة.
     * يجب على صاحب الإعلان تأكيد أن السيارة ما زالت متاحة.
     */
    public function renew(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'still_available' => 'required|accepted',
        ], [
            'still_available.accepted' => 'يجب تأكيد أن السيارة ما زالت متاحة للبيع.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $snapshot = Ad::query()
            ->whereKey($id)
            ->where('user_id', $user->id)
            ->first();

        if (!$snapshot) {
            return response()->json(['message' => 'الإعلان غير موجود أو لا يخصك.'], 404);
        }

        $ad = DB::transaction(function () use ($snapshot, $user) {
            $car = Car::query()
                ->whereKey($snapshot->car_id)
                ->lockForUpdate()
                ->first();

            $ad = Ad::query()
                ->whereKey($snapshot->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$ad || !$car) {
                abort(404, 'الإعلان أو السيارة غير موجودين.');
            }

            // معالجة تأخر Scheduler: الإعلان المنتهي زمنياً يُعامل كـ expired فوراً.
            if ($ad->status === 'active' && $ad->expires_at && $ad->expires_at->lte(now())) {
                $ad->status = 'expired';
                $ad->save();
            }

            if ($ad->status !== 'expired') {
                throw ValidationException::withMessages([
                    'status' => [match ($ad->status) {
                        'active' => 'الإعلان ما زال فعالاً ولا يحتاج إلى تجديد الآن.',
                        'sold' => 'الإعلان مباع ولا يمكن تجديده.',
                        'pending' => 'الإعلان بانتظار مراجعة الإدارة ولا يمكن تجديده.',
                        'rejected' => 'الإعلان مرفوض؛ عدّله وأعد إرساله للمراجعة.',
                        'draft' => 'يجب تسعير المسودة وإرسالها للمراجعة أولاً.',
                        default => 'حالة الإعلان لا تسمح بالتجديد.',
                    }],
                ]);
            }

            if ($car->approval_status !== 'approved') {
                throw ValidationException::withMessages([
                    'car_id' => ['السيارة غير معتمدة من الإدارة ولا يمكن تجديد إعلانها.'],
                ]);
            }

            if ((float) $ad->price <= 0) {
                throw ValidationException::withMessages([
                    'price' => ['يجب أن يكون سعر الإعلان أكبر من صفر.'],
                ]);
            }

            $hasRentalConflict = RentalAd::query()
                ->where('car_id', $ad->car_id)
                ->whereIn('status', ['pending', 'active', 'rented'])
                ->exists();

            if ($hasRentalConflict) {
                throw ValidationException::withMessages([
                    'car_id' => ['لا يمكن تجديد إعلان البيع لأن السيارة مرتبطة بإعلان تأجير قائم.'],
                ]);
            }

            $ad->update([
                'status' => 'active',
                'published_at' => now(),
                'expires_at' => now()->addDays(30),
                'expiration_reminder_sent_at' => null,
                'rejection_reason' => null,
            ]);

            return $ad->fresh('car');
        });

        return response()->json([
            'message' => '✅ تم تأكيد توفر السيارة وتجديد الإعلان لمدة 30 يوماً.',
            'ad' => $ad,
        ]);
    }

    /**
     * حذف إعلان
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $ad = Ad::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

        if (!$ad) {
            return response()->json(['message' => 'الإعلان غير موجود.'], 404);
        }

        $ad->delete();

        return response()->json(['message' => 'تم حذف الإعلان بنجاح.'], 200);
    }

    public function bulkPublishPrices(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ads' => 'required|array|min:1',
            'ads.*.ad_id' => 'required|integer|distinct|exists:ads,id',
            'ads.*.price' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $updatedCount = 0;
        $skipped = [];

        foreach ($request->ads as $adData) {
            $snapshot = Ad::query()
                ->whereKey($adData['ad_id'])
                ->where('user_id', $user->id)
                ->first();

            if (!$snapshot) {
                $skipped[] = [
                    'ad_id' => $adData['ad_id'],
                    'reason' => 'الإعلان غير موجود أو لا يخصك.',
                ];
                continue;
            }

            $result = DB::transaction(function () use ($snapshot, $adData, $user) {
                $car = Car::query()
                    ->whereKey($snapshot->car_id)
                    ->lockForUpdate()
                    ->first();

                $ad = Ad::query()
                    ->whereKey($snapshot->id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if (!$ad || !$car) {
                    return ['updated' => false, 'reason' => 'الإعلان أو السيارة لم يعودا موجودين.'];
                }

                if (!in_array($ad->status, ['draft', 'pending', 'rejected'], true)) {
                    return [
                        'updated' => false,
                        'reason' => "لا يمكن تعديل السعر لأن حالة الإعلان الحالية '{$ad->status}'.",
                    ];
                }

                if ($car->approval_status === 'rejected') {
                    return [
                        'updated' => false,
                        'reason' => 'السيارة المرتبطة بهذا الإعلان مرفوضة نهائياً من الإدارة.',
                    ];
                }

                $hasRentalConflict = RentalAd::query()
                    ->where('car_id', $car->id)
                    ->whereIn('status', ['pending', 'active', 'rented'])
                    ->exists();

                if ($hasRentalConflict) {
                    return [
                        'updated' => false,
                        'reason' => 'السيارة مرتبطة بإعلان تأجير قائم.',
                    ];
                }

                $ad->update([
                    'price' => $adData['price'],
                    'status' => 'pending',
                    'rejection_reason' => null,
                    'published_at' => null,
                    'expires_at' => null,
                    'expiration_reminder_sent_at' => null,
                ]);

                return ['updated' => true];
            });

            if ($result['updated']) {
                $updatedCount++;
            } else {
                $skipped[] = [
                    'ad_id' => $adData['ad_id'],
                    'reason' => $result['reason'],
                ];
            }
        }

        return response()->json([
            'message' => "تم تسعير {$updatedCount} إعلانات وإرسالها للمراجعة بنجاح.",
            'updated_count' => $updatedCount,
            'skipped' => $skipped,
        ]);
    }

}
