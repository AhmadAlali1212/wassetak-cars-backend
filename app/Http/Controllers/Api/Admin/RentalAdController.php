<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\RentalAd;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * وحدة Rental Ads من Admin API — بنفس نطاق ونمط وحدة Ads بالضبط
 * (index/update/destroy فقط، بلا show: يوجد مسبقاً مسار JSON قديم
 * GET /admin/rental-ads/{id} عبر Admin\RentalAdController@showRentalAd).
 * منطق update/destroy منسوخ حرفياً 1:1 من الأصل.
 *
 * ⚠️ ملاحظة سابقة كانت هنا عن حذف صامت للحجوزات المرتبطة — تم إصلاحها،
 * انظر تعليق destroy() أدناه.
 */
class RentalAdController extends Controller
{
    private const PER_PAGE = 20;

    public function index()
    {
        $paginator = RentalAd::with(['car', 'user'])->paginate(self::PER_PAGE);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'message' => 'تم جلب إعلانات التأجير بنجاح.',
        ]);
    }

    public function update(Request $request, RentalAd $rentalAd)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', Rule::in(['pending', 'active', 'rejected'])],
            'daily_price' => 'nullable|numeric|min:0.01',
            'monthly_price' => 'nullable|numeric|min:0.01',
            'rental_conditions' => 'nullable|string',
            'location' => 'required|string|max:255',
            'rejection_reason' => 'nullable|string|max:2000|required_if:status,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (
            (float) ($validated['daily_price'] ?? 0) <= 0
            && (float) ($validated['monthly_price'] ?? 0) <= 0
        ) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'errors' => ['daily_price' => ['يجب تحديد سعر يومي أو شهري أكبر من صفر على الأقل.']],
            ], 422);
        }

        try {
            DB::transaction(function () use ($rentalAd, $validated) {
                $oldStatus = $rentalAd->status;
                $targetStatus = $validated['status'];

                $this->ensureTransitionAllowed($oldStatus, $targetStatus);

                $rentalAd->fill([
                    'daily_price' => $validated['daily_price'] ?? null,
                    'monthly_price' => $validated['monthly_price'] ?? null,
                    'rental_conditions' => $validated['rental_conditions'] ?? null,
                    'location' => $validated['location'],
                ]);
                $rentalAd->save();

                if ($oldStatus === $targetStatus) {
                    if ($targetStatus === 'active') {
                        $this->validateRentalAdCanBeActivated($rentalAd);
                    }

                    if ($targetStatus === 'rejected') {
                        $rentalAd->update(['rejection_reason' => $validated['rejection_reason']]);
                    } elseif ($targetStatus === 'pending') {
                        $rentalAd->update(['rejection_reason' => null]);
                    }

                    return;
                }

                if ($targetStatus === 'active') {
                    $this->approveRentalAdRecord($rentalAd);
                    return;
                }

                if ($targetStatus === 'rejected') {
                    $this->rejectRentalAdRecord($rentalAd, $validated['rejection_reason']);
                    return;
                }

                $rentalAd->update([
                    'status' => 'pending',
                    'rejection_reason' => null,
                    'published_at' => null,
                    'expires_at' => null,
                ]);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        \App\Models\AdminAuditLog::record($request->user(), 'rental_ad.update', $rentalAd, 'تحديث إعلان تأجير', ['new_status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'data' => $rentalAd->fresh(['car', 'user']),
            'message' => 'تم تحديث إعلان التأجير بنجاح.',
        ]);
    }

    /**
     * ⬅️ إصلاح: كان الحذف يعتمد بالكامل على CASCADE بقاعدة البيانات،
     * فيحذف بصمت أي حجوزات تأجير (rental_bookings) مرتبطة بهذا الإعلان
     * — حتى لو كانت حجوزات نشطة (pending/confirmed) لمستأجرين حقيقيين،
     * بلا أي تنبيه لهم ولا تحذير للأدمن. الآن: يُرفض الحذف افتراضياً إن
     * وُجدت حجوزات نشطة، إلا بتمرير ?force=1 صراحة — وعندها تُلغى تلك
     * الحجوزات فعلياً (status=cancelled) ويُشعَر أصحابها، بدل تركها تُحذف
     * صامتة عبر الـ FK.
     */
    public function destroy(Request $request, RentalAd $rentalAd)
    {
        $activeBookings = $rentalAd->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        if ($activeBookings->isNotEmpty() && !$request->boolean('force')) {
            return response()->json([
                'success' => false,
                'data' => ['active_bookings_count' => $activeBookings->count()],
                'message' => 'يوجد ' . $activeBookings->count() . ' حجز نشط مرتبط بهذا الإعلان. '
                    . 'أضف ?force=1 لتأكيد الحذف رغم ذلك (سيتم إلغاء هذه الحجوزات وإشعار المستأجرين).',
            ], 422);
        }

        foreach ($activeBookings as $booking) {
            $booking->update(['status' => 'cancelled']);

            NotificationService::send(
                $booking->user_id,
                'تم إلغاء حجزك',
                'قامت الإدارة بحذف إعلان التأجير المرتبط بحجزك، وتم إلغاء الحجز تلقائياً.',
                'rental_booking_cancelled_by_admin',
                ['rental_booking_id' => $booking->id]
            );
        }

        $rentalAdId = $rentalAd->id;
        $rentalAd->delete();

        \App\Models\AdminAuditLog::record(
            $request->user(),
            'rental_ad.destroy',
            null,
            'حذف إعلان تأجير',
            ['rental_ad_id' => $rentalAdId, 'cancelled_bookings' => $activeBookings->count()]
        );

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'تم حذف إعلان التأجير بنجاح.',
        ]);
    }

    // ==================== الدوال الخاصة (منسوخة حرفياً من الأصل) ====================

    private function allowedStatusesFor(string $currentStatus): array
    {
        $transitions = [
            'pending' => ['pending', 'active', 'rejected'],
            'active' => ['active', 'rejected'],
            'rejected' => ['rejected', 'pending'],
            'rented' => ['active', 'rejected'],
        ];

        return $transitions[$currentStatus] ?? [$currentStatus];
    }

    private function ensureTransitionAllowed(string $from, string $to): void
    {
        if (!in_array($to, $this->allowedStatusesFor($from), true)) {
            throw ValidationException::withMessages([
                'status' => ["لا يمكن تغيير حالة إعلان التأجير من {$from} إلى {$to}."],
            ]);
        }
    }

    private function validateRentalAdCanBeActivated(RentalAd $rentalAd): void
    {
        $rentalAd->loadMissing(['car', 'user.accountType']);

        if (!$rentalAd->car || $rentalAd->car->approval_status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => ['يجب أن تكون السيارة معتمدة من الإدارة قبل تفعيل إعلان التأجير.'],
            ]);
        }

        if ((float) $rentalAd->daily_price <= 0 && (float) $rentalAd->monthly_price <= 0) {
            throw ValidationException::withMessages([
                'daily_price' => ['يجب تحديد سعر يومي أو شهري أكبر من صفر قبل التفعيل.'],
            ]);
        }

        $hasSaleConflict = Ad::where('car_id', $rentalAd->car_id)
            ->whereIn('status', ['draft', 'pending', 'active'])
            ->exists();

        if ($hasSaleConflict) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تفعيل إعلان التأجير لأن السيارة مرتبطة بإعلان بيع قائم.'],
            ]);
        }
    }

    private function approveRentalAdRecord(RentalAd $rentalAd): void
    {
        $this->ensureTransitionAllowed($rentalAd->status, 'active');
        $this->validateRentalAdCanBeActivated($rentalAd);

        $rentalAd->update([
            'status' => 'active',
            'rejection_reason' => null,
            'published_at' => null,
            'expires_at' => null,
        ]);

        NotificationService::send(
            $rentalAd->user_id,
            'تم قبول إعلان التأجير',
            'تمت الموافقة على إعلان التأجير الخاص بك. يمكنك الآن نشره من التطبيق.',
            'rental_ad_approved',
            ['rental_ad_id' => $rentalAd->id]
        );
    }

    private function rejectRentalAdRecord(RentalAd $rentalAd, string $reason): void
    {
        $this->ensureTransitionAllowed($rentalAd->status, 'rejected');

        $rentalAd->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'published_at' => null,
            'expires_at' => null,
        ]);

        NotificationService::send(
            $rentalAd->user_id,
            'تم رفض إعلان التأجير',
            'تم رفض إعلان التأجير الخاص بك للسبب: ' . $reason,
            'rental_ad_rejected',
            ['rental_ad_id' => $rentalAd->id, 'reason' => $reason]
        );
    }
}
