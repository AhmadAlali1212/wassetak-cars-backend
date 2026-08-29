<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RentalAd;
use App\Models\RentalBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RentalBookingController extends Controller
{
    /**
     * إنشاء حجز جديد لإعلان تأجير منشور.
     *
     * الإعلان نفسه يبقى دائماً في rental_ads، بينما كل فترة إيجار
     * تُحفظ كسجل مستقل داخل rental_bookings.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rental_ad_id' => 'required|integer|exists:rental_ads,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = $request->user();

        $booking = DB::transaction(function () use ($request, $user) {
            // قفل الإعلان يمنع طلبين متزامنين من تجاوز فحص تداخل التواريخ.
            $rentalAd = RentalAd::query()
                ->with('car')
                ->whereKey($request->rental_ad_id)
                ->lockForUpdate()
                ->first();

            if (!$rentalAd) {
                abort(404, 'إعلان التأجير غير موجود.');
            }

            if (
                $rentalAd->status !== 'active'
                || !$rentalAd->published_at
                || $rentalAd->published_at->isFuture()
            ) {
                throw ValidationException::withMessages([
                    'rental_ad_id' => ['لا يمكن الحجز على هذا الإعلان حالياً.'],
                ]);
            }

            if (!$rentalAd->car || $rentalAd->car->approval_status !== 'approved') {
                throw ValidationException::withMessages([
                    'rental_ad_id' => ['السيارة المرتبطة بالإعلان غير معتمدة من الإدارة.'],
                ]);
            }

            if ((int) $rentalAd->user_id === (int) $user->id) {
                throw ValidationException::withMessages([
                    'rental_ad_id' => ['لا يمكنك حجز إعلان التأجير الخاص بك.'],
                ]);
            }

            $hasOverlap = $rentalAd->bookings()
                ->where('status', 'confirmed')
                ->where('start_date', '<', $request->end_date)
                ->where('end_date', '>', $request->start_date)
                ->exists();

            if ($hasOverlap) {
                throw ValidationException::withMessages([
                    'start_date' => ['الفترة المطلوبة متعارضة مع حجز مؤكد آخر.'],
                ]);
            }

            // فحص فترات عدم التوفر اليدوية التي حددها المالك (صيانة، إلخ)
            $hasBlockedRange = $rentalAd->unavailabilities()
                ->where('start_date', '<', $request->end_date)
                ->where('end_date', '>', $request->start_date)
                ->exists();

            if ($hasBlockedRange) {
                throw ValidationException::withMessages([
                    'start_date' => ['السيارة غير متوفرة خلال هذه الفترة بحسب مالك الإعلان.'],
                ]);
            }

            return RentalBooking::create([
                'rental_ad_id' => $rentalAd->id,
                'user_id' => $user->id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => 'confirmed',
            ]);
        });

        return response()->json([
            'message' => '✅ تم تأكيد الحجز بنجاح.',
            'booking' => $booking,
        ], 201);
    }

    /**
     * عرض حجوزات المستخدم الحالي.
     */
    public function myBookings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = RentalBooking::with('rentalAd.car')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'bookings' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * إلغاء حجز من قبل صاحب الحجز نفسه.
     */
    public function cancel(Request $request, $id)
    {
        $booking = RentalBooking::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$booking) {
            return response()->json([
                'message' => 'الحجز غير موجود أو لا يخصك.',
            ], 404);
        }

        if ($booking->status === 'cancelled') {
            return response()->json([
                'message' => 'الحجز ملغى مسبقاً.',
            ]);
        }

        if ($booking->status === 'completed' || $booking->end_date->isPast()) {
            return response()->json([
                'message' => 'لا يمكن إلغاء حجز منتهٍ.',
            ], 422);
        }

        $booking->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => '✅ تم إلغاء الحجز بنجاح.',
        ]);
    }

    /**
     * عرض الطلبات الواردة على إعلانات التأجير الخاصة بالمستخدم الحالي
     * (بصفته المالك)، بمختلف الحالات. هذا هو الـ endpoint المفقود الذي
     * كان يمنع صاحب إعلان التأجير من رؤية من حجز سيارته.
     */
    public function incomingBookings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:pending,confirmed,completed,cancelled,rejected',
            'rental_ad_id' => 'nullable|integer|exists:rental_ads,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $perPage = (int) $request->input('per_page', 15);

        $query = RentalBooking::with(['rentalAd.car', 'user'])
            ->whereHas('rentalAd', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            });

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('rental_ad_id')) {
            $query->where('rental_ad_id', $request->input('rental_ad_id'));
        }

        $paginator = $query->latest()->paginate($perPage);

        return response()->json([
            'message' => 'تم جلب الطلبات الواردة بنجاح.',
            'bookings' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * تحضير حجز مملوك للمستخدم الحالي (بصفته مالك إعلان التأجير)،
     * مع فحص الصلاحية. يُستخدم من approve/reject/markDelivered/markReturned.
     */
    private function findOwnedBooking(Request $request, $id): RentalBooking
    {
        $booking = RentalBooking::with('rentalAd')
            ->whereKey($id)
            ->whereHas('rentalAd', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->first();

        if (!$booking) {
            abort(404, 'الحجز غير موجود أو لا يخص إعلاناً تملكه.');
        }

        return $booking;
    }

    /**
     * موافقة مالك الإعلان على طلب حجز بحالة "pending".
     * (الحجوزات الحالية تُنشأ مؤكدة مباشرة عبر store، لكن هذا المسار
     * يبقى متاحاً لأي تدفق مستقبلي يعتمد على تأكيد يدوي من المالك).
     */
    public function approve(Request $request, $id)
    {
        $booking = $this->findOwnedBooking($request, $id);

        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'لا يمكن الموافقة إلا على طلب حجز بحالة "قيد الانتظار".',
            ], 422);
        }

        $booking->update(['status' => 'confirmed']);

        return response()->json([
            'message' => '✅ تمت الموافقة على طلب الحجز.',
            'booking' => $booking,
        ]);
    }

    /**
     * رفض مالك الإعلان لطلب حجز بحالة "pending".
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = $this->findOwnedBooking($request, $id);

        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'لا يمكن رفض إلا طلب حجز بحالة "قيد الانتظار".',
            ], 422);
        }

        $booking->update([
            'status' => 'rejected',
            'rejection_reason' => $request->input('reason'),
        ]);

        return response()->json([
            'message' => 'تم رفض طلب الحجز.',
            'booking' => $booking,
        ]);
    }

    /**
     * تأكيد "تم التسليم" من قبل مالك الإعلان — يستبدل الحل المحلي
     * السابق (SharedPreferences) بحالة حقيقية مُزامَنة على السيرفر.
     */
    public function markDelivered(Request $request, $id)
    {
        $booking = $this->findOwnedBooking($request, $id);

        if ($booking->status !== 'confirmed') {
            return response()->json([
                'message' => 'يمكن تأكيد التسليم فقط لحجز مؤكد.',
            ], 422);
        }

        if ($booking->delivered_at) {
            return response()->json([
                'message' => 'تم تأكيد التسليم مسبقاً.',
                'booking' => $booking,
            ]);
        }

        $booking->update(['delivered_at' => now()]);

        return response()->json([
            'message' => '✅ تم تأكيد تسليم السيارة.',
            'booking' => $booking,
        ]);
    }

    /**
     * تأكيد "تم الإرجاع" من قبل مالك الإعلان — ينهي فترة الحجز فعلياً.
     */
    public function markReturned(Request $request, $id)
    {
        $booking = $this->findOwnedBooking($request, $id);

        if (!$booking->delivered_at) {
            return response()->json([
                'message' => 'لا يمكن تأكيد الإرجاع قبل تأكيد التسليم أولاً.',
            ], 422);
        }

        if ($booking->returned_at) {
            return response()->json([
                'message' => 'تم تأكيد الإرجاع مسبقاً.',
                'booking' => $booking,
            ]);
        }

        $booking->update([
            'returned_at' => now(),
            'status' => 'completed',
        ]);

        // ⬅️ جديد: الآن أصبح هذا الحجز قابلاً للتقييم فعلياً — ندفع
        // المالك والمستأجر نحو شاشة "تقييمات معلّقة" مباشرة.
        $booking->loadMissing('rentalAd.car');
        $carLabel = $booking->rentalAd?->car
            ? "{$booking->rentalAd->car->make} {$booking->rentalAd->car->model}"
            : 'السيارة';
        foreach ([$booking->rentalAd->user_id, $booking->user_id] as $userId) {
            \App\Services\NotificationService::send(
                $userId,
                'يمكنك الآن تقييم هذا الحجز',
                "اكتمل حجز تأجير \"{$carLabel}\". شاركنا رأيك بتقييم سريع.",
                'ratable_transaction',
                ['type' => 'rental_booking', 'transaction_id' => $booking->id]
            );
        }

        return response()->json([
            'message' => '✅ تم تأكيد إرجاع السيارة.',
            'booking' => $booking,
        ]);
    }
}
