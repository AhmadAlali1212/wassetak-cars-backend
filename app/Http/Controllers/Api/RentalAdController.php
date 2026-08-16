<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CarListingFilters;
use App\Models\RentalAd;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\FollowerNotificationService;

class RentalAdController extends Controller
{
    /**
     * عرض جميع إعلانات التأجير مع إمكانية البحث والتصفية الجغرافية.
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
                'min_daily_price' => ['nullable', 'numeric', 'min:0'],
                'max_daily_price' => ['nullable', 'numeric', 'min:0'],
                'min_monthly_price' => ['nullable', 'numeric', 'min:0'],
                'max_monthly_price' => ['nullable', 'numeric', 'min:0'],
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
            $request->filled('min_daily_price')
            && $request->filled('max_daily_price')
            && (float) $request->max_daily_price < (float) $request->min_daily_price
        ) {
            return response()->json([
                'errors' => [
                    'max_daily_price' => [
                        'يجب أن يكون الحد الأعلى للسعر اليومي أكبر من أو يساوي الحد الأدنى.',
                    ],
                ],
            ], 422);
        }

        if (
            $request->filled('min_monthly_price')
            && $request->filled('max_monthly_price')
            && (float) $request->max_monthly_price < (float) $request->min_monthly_price
        ) {
            return response()->json([
                'errors' => [
                    'max_monthly_price' => [
                        'يجب أن يكون الحد الأعلى للسعر الشهري أكبر من أو يساوي الحد الأدنى.',
                    ],
                ],
            ], 422);
        }

        $query = RentalAd::query()
            ->with(['car', 'user'])
            ->where('status', 'active')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('car', function ($q) {
                $q->where('approval_status', 'approved');
            });

        /*
         * نبقي location القديم الخاص بإعلان التأجير، ونضيف car.location أيضاً.
         * هكذا Flutter القديم لا ينكسر، وتصبح دلالة location متقاربة مع بقية القوائم.
         */
        if ($request->filled('location')) {
            $location = trim((string) $request->location);

            $query->where(function ($locationQuery) use ($location) {
                $locationQuery
                    ->where('location', 'like', '%' . $location . '%')
                    ->orWhereHas('car', function ($carQuery) use ($location) {
                        $carQuery->where('location', 'like', '%' . $location . '%');
                    });
            });
        }

        if ($request->filled('min_daily_price')) {
            $query->where('daily_price', '>=', (float) $request->min_daily_price);
        }

        if ($request->filled('max_daily_price')) {
            $query->where('daily_price', '<=', (float) $request->max_daily_price);
        }

        if ($request->filled('min_monthly_price')) {
            $query->where('monthly_price', '>=', (float) $request->min_monthly_price);
        }

        if ($request->filled('max_monthly_price')) {
            $query->where('monthly_price', '<=', (float) $request->max_monthly_price);
        }

        // location عالجناه بالأعلى للحفاظ على توافق الحقل القديم داخل rental_ads.
        CarListingFilters::applyThroughCarRelation(
            $query,
            $request,
            false
        );

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $rentalAds = $paginator->getCollection()->map(function ($ad) {
            return [
                'id' => $ad->id,
                'car_id' => $ad->car_id,
                'user_id' => $ad->user_id,
                'daily_price' => $ad->daily_price,
                'monthly_price' => $ad->monthly_price,
                'rental_conditions' => $ad->rental_conditions,
                'location' => $ad->location,
                'status' => $ad->status,
                'published_at' => $ad->published_at,
                'expires_at' => $ad->expires_at,
                'created_at' => $ad->created_at,
                'updated_at' => $ad->updated_at,
                'car' => $ad->car ? [
                    'id' => $ad->car->id,
                    'make' => $ad->car->make,
                    'model' => $ad->car->model,
                    'year' => $ad->car->year,
                    'condition' => $ad->car->condition,
                    'location' => $ad->car->location,
                    'latitude' => $ad->car->latitude,
                    'longitude' => $ad->car->longitude,
                    'images' => $ad->car->images,
                ] : null,
            ];
        })->values();

        return response()->json([
            'message' => 'تم جلب إعلانات التأجير المنشورة والنشطة بنجاح.',
            'count' => $paginator->total(),
            'rental_ads' => $rentalAds,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * عرض جميع إعلانات التأجير الخاصة بالمستخدم الحالي، بأي حالة كانت
     * (pending/active/rejected) — بخلاف index() العام الذي يعرض
     * فقط الإعلانات النشطة (active) لأي زائر. هذه الميثود كانت مفقودة
     * بالكامل رغم وجودها لكل من الإعلانات (Ad::myAds) والعروض الترويجية
     * (Promotion مع mine=1)، فلم يكن لصاحب إعلان تأجير pending أو rejected
     * أي طريقة لرؤية إعلاناته الخاصة عبر الـ API.
     */
    public function myRentalAds(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,active,rejected',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = RentalAd::query()
            ->where('user_id', $request->user()->id)
            ->with('car');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->latest()->paginate($perPage);
        $rentalAds = $paginator->getCollection()->map(function ($ad) {
            return [
                'id' => $ad->id,
                'car_id' => $ad->car_id,
                'daily_price' => $ad->daily_price,
                'monthly_price' => $ad->monthly_price,
                'rental_conditions' => $ad->rental_conditions,
                'location' => $ad->location,
                'status' => $ad->status,
                'rejection_reason' => $ad->rejection_reason,
                'published_at' => $ad->published_at,
                'expires_at' => $ad->expires_at,
                'created_at' => $ad->created_at,
                'updated_at' => $ad->updated_at,
                'car' => $ad->car ? [
                    'id' => $ad->car->id,
                    'make' => $ad->car->make,
                    'model' => $ad->car->model,
                    'year' => $ad->car->year,
                ] : null,
            ];
        })->values();

        return response()->json([
            'message' => 'تم جلب إعلانات التأجير الخاصة بك بنجاح.',
            'count' => $paginator->total(),
            'rental_ads' => $rentalAds,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * عرض تفاصيل إعلان تأجير محدد يخص المستخدم الحالي، بغض النظر عن حالته
     * (بخلاف show() العام الذي يرفض أي إعلان غير active لأي طالب كان،
     * حتى لو كان هو صاحب الإعلان نفسه).
     */
    public function showMine(Request $request, $id)
    {
        $user = $request->user();

        $rentalAd = RentalAd::where('id', $id)
            ->where('user_id', $user->id)
            ->with('car')
            ->first();

        if (!$rentalAd) {
            return response()->json(['message' => 'إعلان التأجير غير موجود أو لا يخصك.'], 404);
        }

        return response()->json([
            'message' => 'تم جلب تفاصيل إعلان التأجير بنجاح.',
            'rental_ad' => [
                'id' => $rentalAd->id,
                'car_id' => $rentalAd->car_id,
                'daily_price' => $rentalAd->daily_price,
                'monthly_price' => $rentalAd->monthly_price,
                'rental_conditions' => $rentalAd->rental_conditions,
                'location' => $rentalAd->location,
                'status' => $rentalAd->status,
                'rejection_reason' => $rentalAd->rejection_reason,
                'published_at' => $rentalAd->published_at,
                'expires_at' => $rentalAd->expires_at,
                'created_at' => $rentalAd->created_at,
                'updated_at' => $rentalAd->updated_at,
                'car' => $rentalAd->car,
            ],
        ]);
    }

    /**
     * عرض تفاصيل إعلان تأجير محدد.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $rentalAd = RentalAd::query()
            ->with(['car', 'user'])
            ->whereKey($id)
            ->where('status', 'active')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('car', function ($q) {
                $q->where('approval_status', 'approved');
            })
            ->first();

        if (!$rentalAd) {
            return response()->json([
                'message' => 'إعلان التأجير غير موجود أو غير متاح حالياً.',
            ], 404);
        }

        return response()->json([
            'message' => 'تم جلب تفاصيل إعلان التأجير بنجاح.',
            'rental_ad' => [
                'id' => $rentalAd->id,
                'car_id' => $rentalAd->car_id,
                'user_id' => $rentalAd->user_id,
                'daily_price' => $rentalAd->daily_price,
                'monthly_price' => $rentalAd->monthly_price,
                'rental_conditions' => $rentalAd->rental_conditions,
                'location' => $rentalAd->location,
                'status' => $rentalAd->status,
                'published_at' => $rentalAd->published_at,
                'expires_at' => $rentalAd->expires_at,
                'created_at' => $rentalAd->created_at,
                'updated_at' => $rentalAd->updated_at,
            ],
        ]);
    }

    /**
     * إضافة إعلان تأجير جديد مع جلب الموقع الجغرافي من LocationIQ يدوياً.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->accountType->name, ['individual', 'company'], true)) {
            return response()->json(['message' => 'ليس لديك الصلاحية لإضافة إعلانات تأجير.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'car_id' => 'required|exists:cars,id|integer',
            'daily_price' => 'nullable|numeric|min:0.01',
            'monthly_price' => 'nullable|numeric|min:0.01',
            'rental_conditions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ((float) ($request->daily_price ?? 0) <= 0 && (float) ($request->monthly_price ?? 0) <= 0) {
            return response()->json([
                'message' => 'يجب تحديد سعر يومي أو شهري أكبر من صفر على الأقل.',
            ], 422);
        }

        $rentalAd = DB::transaction(function () use ($request, $user) {
            $car = Car::query()
                ->whereKey($request->car_id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$car) {
                throw ValidationException::withMessages([
                    'car_id' => ['هذه السيارة غير موجودة أو لا تنتمي إليك.'],
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

            $existingSaleAd = \App\Models\Ad::query()
                ->where('car_id', $car->id)
                ->whereIn('status', ['draft', 'pending', 'active'])
                ->first();

            if ($existingSaleAd) {
                throw ValidationException::withMessages([
                    'car_id' => ['هذه السيارة مرتبطة بإعلان بيع قائم. يجب اختيار البيع أو التأجير فقط.'],
                ]);
            }

            $existingRentalAd = RentalAd::query()
                ->where('car_id', $car->id)
                ->first();

            if ($existingRentalAd) {
                throw ValidationException::withMessages([
                    'car_id' => [match ($existingRentalAd->status) {
                        'pending' => 'يوجد إعلان تأجير بانتظار مراجعة الإدارة.',
                        'active' => 'يوجد إعلان تأجير نشط لهذه السيارة.',
                        'rejected' => 'يوجد إعلان تأجير مرفوض؛ عدّله وأعد إرساله بدلاً من إنشاء إعلان جديد.',
                        default => 'يوجد إعلان تأجير سابق لهذه السيارة.',
                    }],
                ]);
            }

            return RentalAd::create([
                'car_id' => $car->id,
                'user_id' => $user->id,
                'daily_price' => $request->daily_price,
                'monthly_price' => $request->monthly_price,
                'rental_conditions' => $request->rental_conditions,
                'location' => $car->location,
                'status' => 'pending',
                'rejection_reason' => null,
                'published_at' => null,
                'expires_at' => null,
            ]);
        });

        return response()->json([
            'message' => '✅ تم إضافة إعلان التأجير بنجاح! ⏳ بانتظار موافقة الإدارة',
            'rental_ad' => $rentalAd,
        ], 201);
    }

    /**
     * تحديث إعلان تأجير موجود وإعادته لحالة "pending" لإعادة مراجعة الإدارة.
     *
     * إذا عُدّلت الأسعار أو الشروط، يعود الإعلان إلى pending لإعادة المراجعة.
     * الحجوزات الحالية تبقى محفوظة في rental_bookings ولا تغيّر حالة الإعلان.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'daily_price' => 'sometimes|nullable|numeric|min:0.01',
            'monthly_price' => 'sometimes|nullable|numeric|min:0.01',
            'rental_conditions' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = $request->user();
        $snapshot = RentalAd::query()
            ->whereKey($id)
            ->where('user_id', $user->id)
            ->first();

        if (!$snapshot) {
            return response()->json(['message' => 'إعلان التأجير غير موجود أو ليس لديك صلاحية لتعديله.'], 404);
        }

        $rentalAd = DB::transaction(function () use ($snapshot, $request, $user) {
            $car = Car::query()
                ->whereKey($snapshot->car_id)
                ->lockForUpdate()
                ->first();

            $rentalAd = RentalAd::query()
                ->whereKey($snapshot->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$rentalAd || !$car) {
                abort(404, 'إعلان التأجير أو السيارة غير موجودين.');
            }

            if ($car->approval_status === 'rejected') {
                throw ValidationException::withMessages([
                    'car_id' => ['السيارة المرتبطة بهذا الإعلان مرفوضة نهائياً من الإدارة.'],
                ]);
            }

            $effectiveDailyPrice = $request->exists('daily_price')
                ? $request->input('daily_price')
                : $rentalAd->daily_price;

            $effectiveMonthlyPrice = $request->exists('monthly_price')
                ? $request->input('monthly_price')
                : $rentalAd->monthly_price;

            if ((float) ($effectiveDailyPrice ?? 0) <= 0 && (float) ($effectiveMonthlyPrice ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'daily_price' => ['يجب أن يبقى للإعلان سعر يومي أو شهري أكبر من صفر على الأقل.'],
                ]);
            }

            $hasSaleConflict = \App\Models\Ad::query()
                ->where('car_id', $car->id)
                ->whereIn('status', ['draft', 'pending', 'active'])
                ->exists();

            if ($hasSaleConflict) {
                throw ValidationException::withMessages([
                    'car_id' => ['لا يمكن إعادة إرسال إعلان التأجير لأن السيارة مرتبطة بإعلان بيع قائم.'],
                ]);
            }

            if ($request->exists('daily_price')) {
                $rentalAd->daily_price = $request->input('daily_price');
            }
            if ($request->exists('monthly_price')) {
                $rentalAd->monthly_price = $request->input('monthly_price');
            }
            if ($request->exists('rental_conditions')) {
                $rentalAd->rental_conditions = $request->input('rental_conditions');
            }

            $rentalAd->status = 'pending';
            $rentalAd->rejection_reason = null;
            $rentalAd->published_at = null;
            $rentalAd->expires_at = null;
            $rentalAd->save();

            return $rentalAd->fresh('car');
        });

        return response()->json([
            'message' => '✅ تم تحديث الإعلان بنجاح! ⏳ تم إعادته للمراجعة من قبل الإدارة',
            'rental_ad' => $rentalAd,
        ]);
    }

    /**
     * نشر إعلان تأجير (السماح بنشره عدة مرات)
     * الشروط:
     * 1. حالة الإعلان يجب أن تكون active (موافقة من الأدمن)
     * 2. حالة السيارة يجب أن تكون approved (ليست pending)
     */
    public function publish(Request $request, $id)
    {
        $user = $request->user();

        $rentalAd = RentalAd::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['car', 'user.accountType'])
            ->first();

        if (!$rentalAd) {
            return response()->json(['message' => 'إعلان التأجير غير موجود.'], 404);
        }

        if ($rentalAd->status !== 'active') {
            return response()->json([
                'message' => '❌ لا يمكن نشر هذا الإعلان!',
                'reason' => match ($rentalAd->status) {
                    'pending' => 'الإعلان بانتظار موافقة الإدارة',
                    'rejected' => 'الإعلان تم رفضه من قبل الإدارة',
                    default => 'الإعلان في حالة غير صحيحة',
                },
            ], 422);
        }

        if (!$rentalAd->car || $rentalAd->car->approval_status !== 'approved') {
            return response()->json([
                'message' => '❌ لا يمكن نشر إعلان التأجير.',
                'reason' => 'السيارة يجب أن تكون موافقاً عليها من الإدارة أولاً.',
            ], 422);
        }

        if ((float) $rentalAd->daily_price <= 0 && (float) $rentalAd->monthly_price <= 0) {
            return response()->json([
                'message' => '❌ لا يمكن نشر إعلان التأجير.',
                'reason' => 'يجب تحديد سعر يومي أو شهري أكبر من صفر.',
            ], 422);
        }

        $hasSaleConflict = \App\Models\Ad::where('car_id', $rentalAd->car_id)
            ->whereIn('status', ['draft', 'pending', 'active'])
            ->exists();

        if ($hasSaleConflict) {
            return response()->json([
                'message' => '❌ لا يمكن نشر إعلان التأجير.',
                'reason' => 'السيارة مرتبطة حالياً بإعلان بيع قائم.',
            ], 422);
        }

        $isFirstPublication = is_null($rentalAd->published_at);

        $rentalAd->rejection_reason = null;
        $rentalAd->published_at = now();
        // إعلان التأجير دائم؛ انتهاء مدة الحجز لا ينهي الإعلان نفسه.
        $rentalAd->expires_at = null;
        $rentalAd->save();

        if ($isFirstPublication) {
            $owner = $rentalAd->user;

            if ($owner && $owner->accountType && $owner->accountType->name === 'company') {
                FollowerNotificationService::notifyCompanyFollowers(
                    $owner,
                    'إعلان تأجير جديد من ' . $owner->name,
                    'قامت الشركة التي تتابعها بنشر إعلان تأجير جديد.',
                    'followed_company_new_rental_ad',
                    [
                        'rental_ad_id' => $rentalAd->id,
                        'target_type' => 'rental_ad',
                        'target_id' => $rentalAd->id,
                    ]
                );
            }
        }

        return response()->json([
            'message' => '✅ تم نشر إعلان التأجير بنجاح!',
            'rental_ad' => [
                'id' => $rentalAd->id,
                'car_id' => $rentalAd->car_id,
                'user_id' => $rentalAd->user_id,
                'daily_price' => $rentalAd->daily_price,
                'monthly_price' => $rentalAd->monthly_price,
                'rental_conditions' => $rentalAd->rental_conditions,
                'location' => $rentalAd->location,
                'status' => $rentalAd->status,
                'published_at' => $rentalAd->published_at,
                'expires_at' => $rentalAd->expires_at,
                'created_at' => $rentalAd->created_at,
                'updated_at' => $rentalAd->updated_at,
            ],
        ]);
    }

    /**
     * حذف إعلان تأجير فقط، دون حذف السيارة المرتبطة به.
     * (تم إزالة حذف السيارة تلقائياً بناءً على طلب: يجب أن يبقى بإمكان المستخدم
     *  الاحتفاظ بالسيارة وإعادة عرضها للبيع أو إنشاء إعلان تأجير جديد عليها لاحقاً.)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $rentalAd = RentalAd::where('id', $id)->where('user_id', $user->id)->first();

        if (!$rentalAd) {
            return response()->json(['message' => 'إعلان التأجير غير موجود أو ليس لديك صلاحية لحذفه.'], 404);
        }

        $rentalAd->delete();

        return response()->json(['message' => 'تم حذف إعلان التأجير بنجاح.'], 200);
    }
}
