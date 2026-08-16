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
}
