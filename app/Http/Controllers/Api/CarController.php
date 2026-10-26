<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CarListingFilters;
use App\Models\Car;
use App\Models\Ad;


use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http; // لإرسال طلبات الـ API للخرائط
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CarController extends Controller
{
    /**
     * عرض جميع إعلانات البيع مع إمكانية البحث والتصفية العادية والجغرافية.
     */
/**
 * عرض جميع إعلانات البيع مع إمكانية البحث والتصفية العادية والجغرافية.
 *
 * ⚠️ إصلاح حرج: كانت هذه الميثود تستعلم بـ where('approval_status', 'approved')
 * على جدول ads، وهو عمود تم حذفه فعلياً من الجدول بموجب migration قديمة
 * (تبسيط حالة الإعلان لعمود status وحيد). كان هذا يعني أن كل استدعاء لـ GET /ads
 * (نقطة تصفح الإعلانات الأساسية للعموم) يفشل فوراً بخطأ SQL. تم حذف الشرط المكرر
 * لأن فلترة status = 'active' بالأسفل (سطر ~30) تغطي نفس الغرض أصلاً.
 */
    public function index(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            array_merge(CarListingFilters::rules(), [
                'status' => ['nullable', 'in:active'],
                'min_price' => ['nullable', 'numeric', 'min:0'],
                'max_price' => ['nullable', 'numeric', 'min:0'],
                'user_id' => ['nullable', 'integer', 'exists:users,id'],
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

        $query = Ad::query()
            ->with(['car', 'user'])
            ->where('status', 'active')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->whereHas('car', function ($q) {
                $q->where('approval_status', 'approved');
            });

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        CarListingFilters::applyThroughCarRelation($query, $request);

        $perPage = (int) $request->input('per_page', 15);
        $ads = $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $ads->getCollection()->transform(function ($ad) {
            return [
                'id' => $ad->id,
                'car_id' => $ad->car_id,
                'user_id' => $ad->user_id,
                'price' => $ad->price,
                'status' => $ad->status,
                'published_at' => $ad->published_at,
                'expires_at' => $ad->expires_at,
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
        });

        return response()->json([
            'message' => '✅ تم جلب إعلانات البيع بنجاح وفقاً لمحددات البحث الحالية.',
            'total_count' => $ads->total(),
            'ads' => $ads,
        ]);
    }
    /**
     * عرض تفاصيل إعلان بيع محدد
     */
    /**
     * عرض تفاصيل إعلان بيع محدد.
     *
     * ⚠️ إصلاح حرج: كانت الميثود تتحقق من $ad->approval_status (عمود محذوف من جدول ads)،
     * وبما أن الوصول لعمود غير موجود بـ Eloquent يرجع null دائماً، كان الشرط
     * `$ad->approval_status !== 'approved'` صحيحاً (true) دائماً — أي أن هذا الـ endpoint
     * كان يرجع 404 "غير متاح حالياً" لكل إعلان بلا استثناء، حتى الإعلانات الفعّالة فعلياً.
     */
    public function show($id)
    {
        $ad = Ad::query()
            ->with(['car', 'user'])
            ->whereKey($id)
            ->where('status', 'active')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->whereHas('car', function ($q) {
                $q->where('approval_status', 'approved');
            })
            ->first();

        if (!$ad) {
            return response()->json([
                'message' => 'الإعلان غير موجود أو غير متاح حالياً.',
            ], 404);
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
                'car' => $ad->car->toArray(),
                'seller' => [
                    'id' => $ad->user->id,
                    'name' => $ad->user->name,
                    'email' => $ad->user->email,
                    'phone' => $ad->user->phone_number,
                ],
            ],
        ]);
    }

    /**
     * إضافة إعلان بيع جديد مع تحديد الموقع تلقائياً عبر الإحداثيات الجغرافية
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->accountType->name, ['individual', 'company'])) {
            return response()->json(['message' => 'ليس لديك الصلاحية لإضافة إعلانات بيع.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'make' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'condition' => 'required|string|in:new,used',
            'description' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            // ⬅️ جديد: فيديوهات اختيارية عند إنشاء السيارة — يمكن أيضاً
            // إضافتها لاحقاً بأي وقت عبر POST /cars/{id}/videos
            'videos' => 'nullable|array|max:2',
            'videos.*' => 'file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm,video/x-matroska|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // 🗺️ تحويل الإحداثيات تلقائياً إلى نص باستخدام LocationIQ المستقر
        $detectedLocation = 'موقع غير معروف';
        try {
            // ⚠️ ضع مفتاح الـ Token الخاص بك المجلوب من موقع LocationIQ هنا بدلاً من النص أدناه
            $apiKey = 'pk.2ebc5f372c19bce2742a82d76c2c8fc7';

            $response = Http::get("https://us1.locationiq.com/v1/reverse", [
                'key'             => $apiKey,
                'lat'             => $request->latitude,
                'lon'             => $request->longitude,
                'format'          => 'json',
                'accept-language' => 'ar' // النتيجة باللغة العربية
            ]);

            if ($response->successful()) {
                $address = $response->json()['address'] ?? [];
                // استخلاص اسم المدينة أو المحافظة والدولة
                $city = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['state'] ?? '';
                $country = $address['country'] ?? '';

                if (!empty($city) && !empty($country)) {
                    $detectedLocation = $city . '، ' . $country;
                } else {
                    $detectedLocation = $response->json()['display_name'] ?? 'موقع غير معروف';
                }
            }
        } catch (\Exception $e) {
            $detectedLocation = 'موقع غير معروف';
        }

        // حفظ السيارة مع الموقع المكتشف تلقائياً
        $car = Car::create([
            'user_id' => $user->id,
            'make' => $request->make,
            'model' => $request->model,
            'year' => $request->year,
            'condition' => $request->condition,
            'description' => $request->description,
            'location' => $detectedLocation,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        // معالجة تحميل الصور
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('public/cars_images');
                $imagePaths[] = Storage::url($path);
            }
        }
        $car->images = $imagePaths;

        // ⬅️ جديد: معالجة تحميل الفيديوهات الاختيارية (إن وُجدت عند الإنشاء)
        $videoPaths = [];
        if ($request->hasFile('videos')) {
            foreach ($request->file('videos') as $video) {
                $path = $video->store('public/cars_videos');
                $videoPaths[] = Storage::url($path);
            }
        }
        $car->videos = $videoPaths;

        $car->save();

        return response()->json([
            'message' => 'تم إضافة السيارة بنجاح! ⏳ السيارة بانتظار موافقة الإدارة. استخدم car_id لإنشاء إعلان جديد.',
            'status' => 'pending_approval',
            'approval_info' => [
                'approval_status' => $car->approval_status,
                'message' => 'ستتم مراجعة السيارة من قبل الإدارة',
            ],
            'car' => [
                'id' => $car->id,
                'user_id' => $car->user_id,
                'make' => $car->make,
                'model' => $car->model,
                'year' => $car->year,
                'condition' => $car->condition,
                'description' => $car->description,
                'location' => $car->location,
                'latitude' => $car->latitude,
                'longitude' => $car->longitude,
                'images' => $car->images,
                'approval_status' => $car->approval_status,
                'approval_reason' => $car->approval_reason,
                'approved_at' => $car->approved_at,
                'created_at' => $car->created_at,
                'updated_at' => $car->updated_at,
            ],
        ], 201);
    }

    /**
     * تحديث بيانات السيارة وموقعها الجغرافي تلقائياً عند تغيير الإحداثيات
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $car = Car::where('id', $id)->where('user_id', $user->id)->first();

        if (!$car) {
            return response()->json(['message' => 'السيارة غير موجودة أو ليس لديك صلاحية لتعديلها.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'make' => 'sometimes|required|string|max:255',
            'model' => 'sometimes|required|string|max:255',
            'year' => 'sometimes|required|integer|min:1900|max:' . (date('Y') + 1),
            'condition' => 'sometimes|required|string|in:new,used',
            'description' => 'nullable|string',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->has('make')) $car->make = $request->make;
        if ($request->has('model')) $car->model = $request->model;
        if ($request->has('year')) $car->year = $request->year;
        if ($request->has('condition')) $car->condition = $request->condition;
        if ($request->has('description')) $car->description = $request->description;

        // تحديث الاسم النصي للموقع تلقائياً عند تعديل الإحداثيات الجغرافية
        if ($request->has(['latitude', 'longitude'])) {
            $car->latitude = $request->latitude;
            $car->longitude = $request->longitude;

            try {
                $apiKey = 'pk.2ebc5f372c19bce2742a82d76c2c8fc7'; // ⚠️ ضع الـ Token الخاص بك هنا أيضاً

                $response = Http::get("https://us1.locationiq.com/v1/reverse", [
                    'key'             => $apiKey,
                    'lat'             => $request->latitude,
                    'lon'             => $request->longitude,
                    'format'          => 'json',
                    'accept-language' => 'ar'
                ]);

                if ($response->successful()) {
                    $address = $response->json()['address'] ?? [];
                    $city = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['state'] ?? '';
                    $country = $address['country'] ?? '';
                    if (!empty($city) && !empty($country)) {
                        $car->location = $city . '، ' . $country;
                    }
                }
            } catch (\Exception $e) {
                // تجاوز الخطأ في حال تعطل الشبكة والاحتفاظ بالبيانات السابقة
            }
        }

        // معالجة الصور
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

        // إعادة السيارة لحالة "بانتظار المراجعة" فعلياً إذا تغيّرت بياناتها الجوهرية
        // (ما كانت هذه الخطوة موجودة سابقاً، وكانت الرسالة تقول ذلك دون أن ينفّذه الكود فعلياً)
        $fieldsThatRequireReReview = ['make', 'model', 'year', 'condition', 'location', 'images'];
        if ($car->approval_status !== 'pending' && $car->wasChanged($fieldsThatRequireReReview)) {
            $car->update([
                'approval_status' => 'pending',
                'approval_reason' => null,
                'approved_at' => null,
            ]);

            // إشعار المستخدم بأن سيارته عادت لطابور المراجعة بعد التعديل
            NotificationService::send(
                $car->user_id,
                'سيارتك بانتظار مراجعة الإدارة مجدداً',
                'نظراً لتعديلك على بيانات السيارة ' . $car->make . ' ' . $car->model . '، أصبحت بانتظار موافقة الإدارة من جديد.',
                'car_pending_reapproval',
                ['car_id' => $car->id]
            );
        }

        return response()->json([
            'message' => 'تم تحديث بيانات السيارة والموقع تلقائياً بنجاح. ⏳ في انتظار إعادة مراجعة الإدارة.',
            'approval_info' => [
                'approval_status' => $car->approval_status,
                'previous_approval' => $car->wasChanged('approval_status') ? 'تم إعادة تعيين الحالة إلى pending' : 'بدون تغيير',
            ],
            'car' => [
                'id' => $car->id,
                'user_id' => $car->user_id,
                'make' => $car->make,
                'model' => $car->model,
                'year' => $car->year,
                'condition' => $car->condition,
                'description' => $car->description,
                'location' => $car->location,
                'latitude' => $car->latitude,
                'longitude' => $car->longitude,
                'images' => $car->images,
                'approval_status' => $car->approval_status,
                'approval_reason' => $car->approval_reason,
                'approved_at' => $car->approved_at,
                'updated_at' => $car->updated_at,
            ],
        ]);
    }

    /**
     * حذف السيارة والإعلانات المرتبطة بها (بيع وتأجير).
     *
     * إصلاحات:
     * 1. كان الكود يحذف Ad يدوياً فقط ويعتمد صامتاً على cascade بقاعدة البيانات
     *    لحذف RentalAd — الآن الحذف صريح للاثنين معاً لوضوح الكود وتناسقه.
     * 2. أضيف منع حذف السيارة إذا كانت مرتبطة بإعلان بيع status = 'sold' أو إعلان
     *    تأجير status = 'rented'، لأن هذا يعني وجود صفقة/عملية إيجار قائمة فعلياً
     *    ولا يجوز فقدان سجلها بالحذف الصامت.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $car = Car::where('id', $id)
                  ->where('user_id', $user->id)
                  ->first();

        if (!$car) {
            return response()->json(['message' => 'السيارة غير موجودة أو ليس لديك صلاحية لحذفها.'], 404);
        }

        $soldAd = Ad::where('car_id', $car->id)->where('status', 'sold')->exists();
        if ($soldAd) {
            return response()->json([
                'message' => '❌ لا يمكن حذف هذه السيارة.',
                'reason' => 'هذه السيارة مرتبطة بإعلان بيع مكتمل (sold)، ولا يجوز حذف سجلها.',
            ], 422);
        }

        $rentedAd = \App\Models\RentalAd::where('car_id', $car->id)->where('status', 'rented')->exists();
        if ($rentedAd) {
            return response()->json([
                'message' => '❌ لا يمكن حذف هذه السيارة.',
                'reason' => 'هذه السيارة مؤجَّرة حالياً (rented)، لا يجوز حذفها قبل انتهاء عقد الإيجار.',
            ], 422);
        }

        // حذف الصور المرتبطة بالسيارة
        if ($car->images) {
            foreach ($car->images as $imagePath) {
                Storage::delete(str_replace('/storage/', 'public/', $imagePath));
            }
        }

        // ⬅️ جديد: حذف الفيديوهات المرتبطة بالسيارة أيضاً
        if ($car->videos) {
            foreach ($car->videos as $videoPath) {
                Storage::delete(str_replace('/storage/', 'public/', $videoPath));
            }
        }

        // حذف جميع إعلانات البيع المرتبطة بهذه السيارة
        Ad::where('car_id', $car->id)->delete();

        // حذف جميع إعلانات التأجير المرتبطة بهذه السيارة (صراحةً، بدل الاعتماد الصامت على cascade بقاعدة البيانات)
        \App\Models\RentalAd::where('car_id', $car->id)->delete();

        // حذف السيارة
        $car->delete();

        return response()->json(['message' => 'تم حذف السيارة وجميع الإعلانات المرتبطة بها (بيع وتأجير) بنجاح.'], 200);
    }

    /**
     * عرض جميع سيارات المستخدم الحالي، بأي حالة، مع آخر إعلان بيع وتأجير ومزاد.
     */
    public function myCars(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'approval_status' => 'nullable|in:pending,approved,rejected',
            'make' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'condition' => 'nullable|in:new,used',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Car::query()
            ->where('user_id', $request->user()->id)
            ->with(['latestSaleAd', 'latestRentalAd', 'latestAuction']);

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }
        if ($request->filled('make')) {
            $query->where('make', 'like', '%' . $request->make . '%');
        }
        if ($request->filled('model')) {
            $query->where('model', 'like', '%' . $request->model . '%');
        }
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->latest()->paginate($perPage);
        $cars = $paginator->getCollection()->map(function (Car $car) {
            return $this->formatOwnerCarSummary($car);
        })->values();

        return response()->json([
            'message' => 'تم جلب جميع سياراتك بنجاح.',
            'count' => $paginator->total(),
            'cars' => $cars,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** عرض السيارات العامة القابلة للمشاهدة. */
    public function publicCars(Request $request)
    {
        return $this->publicCarsResponse($request, false);
    }

    /** عرض سيارات المستخدمين الآخرين فقط. */
    public function otherUsersCars(Request $request)
    {
        return $this->publicCarsResponse($request, true);
    }

    /** عرض التفاصيل الكاملة لسيارة يملكها المستخدم الحالي فقط. */
    public function showMineFull(Request $request, $id)
    {
        $car = Car::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$car) {
            return response()->json(['message' => 'السيارة غير موجودة أو لا تابعة لك.'], 404);
        }

        return $this->ownerCarFullResponse($request->user(), $car);
    }

    /**
     * عرض تفاصيل سيارة كاملة بحسب صلاحية الطالب:
     * - المالك يرى البيانات الداخلية والإعلانات بأي حالة والحجوزات الحالية.
     * - غير المالك يرى البيانات العامة فقط عندما تكون السيارة مقبولة ولها عرض عام فعّال.
     */
    public function showFull(Request $request, $id)
    {
        $car = Car::query()
            ->with('user.accountType')
            ->find($id);

        if (!$car) {
            return response()->json(['message' => 'السيارة غير موجودة.'], 404);
        }

        if ((int) $car->user_id === (int) $request->user()->id) {
            return $this->ownerCarFullResponse($request->user(), $car);
        }

        $visibleCar = $this->applyPublicCarVisibility(
            Car::query()->whereKey($id)
        )->with([
            'user.accountType',
            'saleAds' => function ($q) {
                $q->where('status', 'active')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>', now())
                    ->latest();
            },
            'rentalAds' => function ($q) {
                $q->where('status', 'active')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->latest();
            },
            'auctions' => function ($q) {
                $q->where('status', 'active')
                    ->where('start_time', '<=', now())
                    ->where('end_time', '>', now())
                    ->latest();
            },
        ])->first();

        if (!$visibleCar) {
            return response()->json([
                'message' => 'السيارة غير متاحة للعامة حالياً.',
            ], 404);
        }

        return response()->json([
            'message' => 'تم جلب تفاصيل السيارة العامة بنجاح.',
            'access_scope' => 'public',
            'car' => $this->formatPublicCar($visibleCar),
        ]);
    }

    private function publicCarsResponse(Request $request, bool $onlyOthers)
    {
        $validator = Validator::make(
            $request->all(),
            array_merge(CarListingFilters::rules(), [
                'listing_type' => ['nullable', 'in:sale,rental,auction'],
            ])
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $yearErrors = CarListingFilters::yearRangeErrors($request);
        if ($yearErrors !== []) {
            return response()->json(['errors' => $yearErrors], 422);
        }

        $query = $this->applyPublicCarVisibility(Car::query())
            ->with([
                'user.accountType',
                'saleAds' => function ($q) {
                    $q->where('status', 'active')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now())
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '>', now())
                        ->latest();
                },
                'rentalAds' => function ($q) {
                    $q->where('status', 'active')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now())
                        ->latest();
                },
                'auctions' => function ($q) {
                    $q->where('status', 'active')
                        ->where('start_time', '<=', now())
                        ->where('end_time', '>', now())
                        ->latest();
                },
            ]);

        if ($onlyOthers) {
            $query->where('user_id', '!=', $request->user()->id);
        }

        CarListingFilters::applyToCarQuery($query, $request);

        if ($request->listing_type === 'sale') {
            $query->whereHas('saleAds', function ($q) {
                $q->where('status', 'active')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>', now());
            });
        } elseif ($request->listing_type === 'rental') {
            $query->whereHas('rentalAds', function ($q) {
                $q->where('status', 'active')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            });
        } elseif ($request->listing_type === 'auction') {
            $query->whereHas('auctions', function ($q) {
                $q->where('status', 'active')
                    ->where('start_time', '<=', now())
                    ->where('end_time', '>', now());
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $cars = $paginator->getCollection()->map(function (Car $car) {
            return $this->formatPublicCar($car);
        })->values();

        return response()->json([
            'message' => $onlyOthers
                ? 'تم جلب سيارات المستخدمين الآخرين بنجاح.'
                : 'تم جلب السيارات العامة بنجاح.',
            'count' => $paginator->total(),
            'cars' => $cars,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function applyPublicCarVisibility($query)
    {
        return $query
            ->where('approval_status', 'approved')
            ->where(function ($q) {
                $q->whereHas('saleAds', function ($sale) {
                    $sale->where('status', 'active')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now())
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '>', now());
                })->orWhereHas('rentalAds', function ($rental) {
                    $rental->where('status', 'active')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                })->orWhereHas('auctions', function ($auction) {
                    $auction->where('status', 'active')
                        ->where('start_time', '<=', now())
                        ->where('end_time', '>', now());
                });
            });
    }

    private function ownerCarFullResponse(User $user, Car $car)
    {
        $ad = Ad::where('car_id', $car->id)->latest()->first();

        $rentalAd = \App\Models\RentalAd::with(['bookings' => function ($q) {
                $q->where('status', 'confirmed')
                    ->where('end_date', '>=', now()->toDateString())
                    ->orderBy('start_date');
            }])
            ->where('car_id', $car->id)
            ->latest()
            ->first();

        $auction = \App\Models\Auction::where('car_id', $car->id)
            ->with(['winner:id,name'])
            ->latest()
            ->first();

        $ownerActivePromotions = \App\Models\Promotion::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->get(['id', 'title', 'type', 'value', 'start_date', 'end_date']);

        return response()->json([
            'message' => 'تم جلب تفاصيل السيارة الكاملة بنجاح.',
            'access_scope' => 'owner',
            'car' => [
                'id' => $car->id,
                'user_id' => $car->user_id,
                'make' => $car->make,
                'model' => $car->model,
                'year' => $car->year,
                'condition' => $car->condition,
                'description' => $car->description,
                'location' => $car->location,
                'latitude' => $car->latitude,
                'longitude' => $car->longitude,
                'images' => $car->images,
                'approval_status' => $car->approval_status,
                'approval_reason' => $car->approval_reason,
                'approved_at' => $car->approved_at,
                'is_inspected' => (bool) $car->is_inspected,
                'inspection_report_path' => $car->inspection_report_path,
                'inspected_at' => $car->inspected_at,
                'bulk_upload_batch' => $car->bulk_upload_batch,
                'created_at' => $car->created_at,
                'updated_at' => $car->updated_at,
            ],
            'sale_ad' => $ad ? [
                'id' => $ad->id,
                'price' => $ad->price,
                'status' => $ad->status,
                'rejection_reason' => $ad->rejection_reason,
                'published_at' => $ad->published_at,
                'expires_at' => $ad->expires_at,
                'created_at' => $ad->created_at,
            ] : null,
            'rental_ad' => $rentalAd ? [
                'id' => $rentalAd->id,
                'daily_price' => $rentalAd->daily_price,
                'monthly_price' => $rentalAd->monthly_price,
                'rental_conditions' => $rentalAd->rental_conditions,
                'location' => $rentalAd->location,
                'status' => $rentalAd->status,
                'rejection_reason' => $rentalAd->rejection_reason,
                'published_at' => $rentalAd->published_at,
                'expires_at' => $rentalAd->expires_at,
                'created_at' => $rentalAd->created_at,
                'upcoming_or_current_bookings' => $rentalAd->bookings->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'start_date' => $booking->start_date,
                        'end_date' => $booking->end_date,
                        'status' => $booking->status,
                    ];
                })->values(),
            ] : null,
            'auction' => $auction ? [
                'id' => $auction->id,
                'starting_price' => $auction->starting_price,
                'current_price' => $auction->current_price,
                'start_time' => $auction->start_time,
                'end_time' => $auction->end_time,
                'status' => $auction->status,
                'winner' => $auction->winner,
                'final_price_status' => $auction->final_price_status,
                'settlement_deadline_at' => $auction->settlement_deadline_at,
            ] : null,
            'owner_active_promotions' => $ownerActivePromotions,
        ]);
    }

    private function formatOwnerCarSummary(Car $car): array
    {
        return [
            'id' => $car->id,
            'user_id' => $car->user_id,
            'make' => $car->make,
            'model' => $car->model,
            'year' => $car->year,
            'condition' => $car->condition,
            'description' => $car->description,
            'location' => $car->location,
            'latitude' => $car->latitude,
            'longitude' => $car->longitude,
            'images' => $car->images,
            'approval_status' => $car->approval_status,
            'approval_reason' => $car->approval_reason,
            'approved_at' => $car->approved_at,
            'is_inspected' => (bool) $car->is_inspected,
            'inspection_report_path' => $car->inspection_report_path,
            'inspected_at' => $car->inspected_at,
            'bulk_upload_batch' => $car->bulk_upload_batch,
            'created_at' => $car->created_at,
            'updated_at' => $car->updated_at,
            'latest_sale_ad' => $car->latestSaleAd,
            'latest_rental_ad' => $car->latestRentalAd,
            'latest_auction' => $car->latestAuction,
        ];
    }

    private function formatPublicCar(Car $car): array
    {
        $saleAd = $car->saleAds->first();
        $rentalAd = $car->rentalAds->first();
        $auction = $car->auctions->first();

        return [
            'id' => $car->id,
            'make' => $car->make,
            'model' => $car->model,
            'year' => $car->year,
            'condition' => $car->condition,
            'description' => $car->description,
            'location' => $car->location,
            'latitude' => $car->latitude,
            'longitude' => $car->longitude,
            'images' => $car->images,
            'is_inspected' => (bool) $car->is_inspected,
            'inspected_at' => $car->inspected_at,
            'seller' => $car->user ? [
                'id' => $car->user->id,
                'name' => $car->user->name,
                'phone_number' => $car->user->phone_number,
                'account_type' => optional($car->user->accountType)->name,
            ] : null,
            'sale_ad' => $saleAd ? [
                'id' => $saleAd->id,
                'price' => $saleAd->price,
                'published_at' => $saleAd->published_at,
                'expires_at' => $saleAd->expires_at,
            ] : null,
            'rental_ad' => $rentalAd ? [
                'id' => $rentalAd->id,
                'daily_price' => $rentalAd->daily_price,
                'monthly_price' => $rentalAd->monthly_price,
                'rental_conditions' => $rentalAd->rental_conditions,
                'location' => $rentalAd->location,
                'published_at' => $rentalAd->published_at,
            ] : null,
            'auction' => $auction ? [
                'id' => $auction->id,
                'starting_price' => $auction->starting_price,
                'current_price' => $auction->current_price,
                'start_time' => $auction->start_time,
                'end_time' => $auction->end_time,
            ] : null,
            'available_as' => array_values(array_filter([
                $saleAd ? 'sale' : null,
                $rentalAd ? 'rental' : null,
                $auction ? 'auction' : null,
            ])),
        ];
    }


public function requestInspection(Request $request, $id)
{
    $car = Car::findOrFail($id);
    $user = $request->user();
    $fee = round((float) config('inspection.fee', 10000), 2);

    try {
        [$transaction, $newBalance] = DB::transaction(function () use ($user, $car, $fee) {
            $user->wallet()->firstOrCreate([], [
                'balance' => 0,
                'held_balance' => 0,
                'is_frozen' => false,
            ]);

            $wallet = \App\Models\Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($wallet->is_frozen) {
                throw new \RuntimeException(
                    'المحفظة مجمدة مؤقتاً: ' . ($wallet->freeze_reason ?: 'راجع الإدارة.')
                );
            }

            if ((float) $wallet->balance < $fee) {
                throw new \RuntimeException(
                    'رصيدك المتاح لا يكفي لدفع رسم الفحص الفني (' . $fee . ').'
                );
            }

            $wallet->balance = round((float) $wallet->balance - $fee, 2);
            $wallet->save();

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => 'inspection_fee',
                'amount' => -$fee,
                'status' => 'completed',
                'payment_method' => 'wallet',
                'description' => "رسم طلب فحص فني للسيارة ({$car->make} {$car->model}) رقم {$car->id}",
                'processed_at' => now(),
                'transactionable_id' => $car->id,
                'transactionable_type' => Car::class,
            ]);

            return [$transaction, (float) $wallet->balance];
        }, 5);
    } catch (\RuntimeException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'required_fee' => $fee,
        ], 400);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء خصم رسم الفحص، الرجاء المحاولة لاحقاً.',
        ], 500);
    }

    $adminIds = User::whereHas('accountType', function ($q) {
        $q->where('name', 'admin');
    })->pluck('id');

    foreach ($adminIds as $adminId) {
        NotificationService::send(
            $adminId,
            'طلب فحص فني جديد (مدفوع) 🚗',
            "طلب المستخدم {$user->name} فحصاً فنياً مدفوعاً للسيارة ({$car->make} {$car->model}) رقم {$car->id}.",
            'inspection_request',
            [
                'car_id' => $car->id,
                'car_owner_id' => $car->user_id,
                'requested_by_user_id' => $user->id,
                'fee' => $fee,
            ]
        );
    }

    if ($car->user_id !== $user->id) {
        NotificationService::send(
            $car->user_id,
            'أحد المهتمين طلب فحصاً فنياً لسيارتك',
            "طلب أحد المستخدمين فحصاً فنياً لسيارتك ({$car->make} {$car->model}).",
            'inspection_requested_on_your_car',
            ['car_id' => $car->id]
        );
    }

    return response()->json([
        'success' => true,
        'message' => 'تم دفع رسم الفحص وإرسال الطلب بنجاح.',
        'fee_paid' => $fee,
        'new_balance' => $newBalance,
        'transaction' => $transaction,
    ]);
}

    // ==================== فيديوهات السيارة (اختيارية، عند الإنشاء أو لاحقاً) ====================

    /**
     * ⬅️ جديد: إضافة فيديو أو أكثر لسيارة موجودة مسبقاً — يغطي حالة
     * "أريد رفعه لاحقاً" بدون الحاجة لإرسال بقية بيانات السيارة مجدداً.
     * يُضيف للفيديوهات الحالية (لا يستبدلها)، بحد أقصى إجمالي 2 فيديو
     * لكل سيارة.
     */
    public function addVideos(Request $request, $id)
    {
        $user = $request->user();
        $car = Car::where('id', $id)->where('user_id', $user->id)->first();

        if (!$car) {
            return response()->json(['message' => 'السيارة غير موجودة أو ليس لديك صلاحية لتعديلها.'], 404);
        }

        $existingVideos = $car->videos ?? [];

        $validator = Validator::make($request->all(), [
            'videos' => 'required|array|min:1|max:2',
            'videos.*' => 'file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm,video/x-matroska|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (count($existingVideos) + count($request->file('videos')) > 2) {
            return response()->json([
                'message' => 'لا يمكن أن يتجاوز عدد فيديوهات السيارة الواحدة فيديوهين اثنين. احذف فيديو موجود أولاً.',
            ], 422);
        }

        $newPaths = [];
        foreach ($request->file('videos') as $video) {
            $path = $video->store('public/cars_videos');
            $newPaths[] = Storage::url($path);
        }

        $car->videos = array_merge($existingVideos, $newPaths);
        $car->save();

        return response()->json([
            'message' => '✅ تم رفع الفيديو بنجاح.',
            'videos' => $car->videos,
        ], 201);
    }

    /**
     * ⬅️ جديد: حذف فيديو واحد من سيارة، بمعرفة رابطه بالضبط (كما وصل
     * سابقاً ضمن مصفوفة videos)، دون التأثير على بقية الفيديوهات.
     */
    public function removeVideo(Request $request, $id)
    {
        $user = $request->user();
        $car = Car::where('id', $id)->where('user_id', $user->id)->first();

        if (!$car) {
            return response()->json(['message' => 'السيارة غير موجودة أو ليس لديك صلاحية لتعديلها.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'video_url' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $existingVideos = $car->videos ?? [];

        if (!in_array($request->video_url, $existingVideos, true)) {
            return response()->json(['message' => 'هذا الفيديو غير مرتبط بهذه السيارة.'], 404);
        }

        Storage::delete(str_replace('/storage/', 'public/', $request->video_url));

        $car->videos = array_values(array_filter(
            $existingVideos,
            fn ($url) => $url !== $request->video_url
        ));
        $car->save();

        return response()->json([
            'message' => '✅ تم حذف الفيديو بنجاح.',
            'videos' => $car->videos,
        ]);
    }
}
