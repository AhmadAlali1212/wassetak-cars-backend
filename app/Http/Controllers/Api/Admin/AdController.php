<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Car;
use App\Models\RentalAd;
use App\Services\FollowerNotificationService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * وحدة Ads من Admin API — index/update/destroy فقط (بلا show): يوجد
 * مسبقاً مسار JSON قديم GET /admin/ads/{id} (Admin\AdController@showAd)
 * لم يُلمَس. منطق update/destroy منسوخ حرفياً 1:1 من Admin\AdController
 * القديم (بما فيه كل دوال التحقق/الانتقال الخاصة، private فلا يمكن
 * توريثها)، مع تغيير واحد فقط: النتيجة النهائية JSON بالغلاف الموحّد
 * بدل redirect()، وValidationException المرمية داخلياً تُلتقَط وتُعاد
 * بشكل {success:false,data:null,message:"Validation failed",errors}
 * بدل شكل Laravel الافتراضي.
 */
class AdController extends Controller
{
    private const PER_PAGE = 20;

    public function index()
    {
        $paginator = Ad::with(['car', 'user'])->paginate(self::PER_PAGE);

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
            'message' => 'تم جلب إعلانات البيع بنجاح.',
        ]);
    }

    public function update(Request $request, Ad $ad)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'status' => ['required', 'string', Rule::in(['draft', 'pending', 'active', 'expired', 'sold', 'rejected'])],
            'price' => 'required|numeric|min:0.01',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:published_at',
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

        try {
            DB::transaction(function () use ($ad, $validated) {
                $car = Car::query()->whereKey($ad->car_id)->lockForUpdate()->first();
                $ad = Ad::query()->whereKey($ad->id)->lockForUpdate()->firstOrFail();
                $ad->setRelation('car', $car);
                $oldStatus = $ad->status;
                $targetStatus = $validated['status'];

                if ($oldStatus === 'sold') {
                    throw ValidationException::withMessages([
                        'status' => ['الإعلان مباع، ولا يمكن تعديله أو إعادة فتحه.'],
                    ]);
                }

                $this->ensureTransitionAllowed($oldStatus, $targetStatus);

                $ad->price = $validated['price'];

                if (in_array($targetStatus, ['active', 'expired', 'sold'], true)) {
                    $ad->published_at = $validated['published_at'] ?? $ad->published_at;
                    $ad->expires_at = $validated['expires_at'] ?? $ad->expires_at;
                } else {
                    $ad->published_at = null;
                    $ad->expires_at = null;
                }

                $ad->save();

                if ($oldStatus === $targetStatus) {
                    if ($targetStatus === 'active') {
                        $this->validateAdCanBeActivated($ad);
                        $this->normalizeActiveDates($ad);
                    }

                    if ($targetStatus === 'rejected') {
                        $ad->update(['rejection_reason' => $validated['rejection_reason']]);
                    } elseif (in_array($targetStatus, ['draft', 'pending'], true)) {
                        $ad->update([
                            'rejection_reason' => null,
                            'published_at' => null,
                            'expires_at' => null,
                            'expiration_reminder_sent_at' => null,
                        ]);
                    }

                    return;
                }

                if ($targetStatus === 'active') {
                    $this->approveAdRecord($ad);
                    return;
                }

                if ($targetStatus === 'rejected') {
                    $this->rejectAdRecord($ad, $validated['rejection_reason']);
                    return;
                }

                if ($targetStatus === 'sold') {
                    $this->markAdAsSold($ad);
                    return;
                }

                if ($targetStatus === 'expired') {
                    $this->markAdAsExpired($ad);
                    return;
                }

                $ad->update([
                    'status' => $targetStatus,
                    'rejection_reason' => null,
                    'published_at' => null,
                    'expires_at' => null,
                    'expiration_reminder_sent_at' => null,
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

        \App\Models\AdminAuditLog::record($request->user(), 'ad.update', $ad, 'تحديث إعلان بيع', ['new_status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'data' => $ad->fresh(['car', 'user']),
            'message' => 'تم تحديث الإعلان بنجاح.',
        ]);
    }

    public function destroy(Request $request, Ad $ad)
    {
        $adId = $ad->id;
        $ad->delete();

        \App\Models\AdminAuditLog::record($request->user(), 'ad.destroy', null, 'حذف إعلان بيع', ['ad_id' => $adId]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'تم حذف الإعلان بنجاح.',
        ]);
    }

    // ==================== الدوال الخاصة (منسوخة حرفياً من الأصل) ====================

    private function allowedStatusesFor(string $currentStatus): array
    {
        $transitions = [
            'draft' => ['draft', 'pending', 'rejected'],
            'pending' => ['pending', 'draft', 'active', 'rejected'],
            'active' => ['active', 'expired', 'sold', 'rejected'],
            'expired' => ['expired', 'active', 'sold'],
            'rejected' => ['rejected', 'pending'],
            'sold' => ['sold'],
        ];

        return $transitions[$currentStatus] ?? [$currentStatus];
    }

    private function ensureTransitionAllowed(string $from, string $to): void
    {
        if (!in_array($to, $this->allowedStatusesFor($from), true)) {
            throw ValidationException::withMessages([
                'status' => ["لا يمكن تغيير حالة إعلان البيع من {$from} إلى {$to}."],
            ]);
        }
    }

    private function validateAdCanBeActivated(Ad $ad): void
    {
        $ad->loadMissing(['car', 'user.accountType']);

        if (!$ad->car || $ad->car->approval_status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => ['يجب أن تكون السيارة معتمدة من الإدارة قبل تفعيل الإعلان.'],
            ]);
        }

        if ((float) $ad->price <= 0) {
            throw ValidationException::withMessages([
                'price' => ['يجب أن يكون سعر الإعلان أكبر من صفر قبل التفعيل.'],
            ]);
        }

        $hasRentalConflict = RentalAd::where('car_id', $ad->car_id)
            ->whereIn('status', ['pending', 'active', 'rented'])
            ->exists();

        if ($hasRentalConflict) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تفعيل إعلان البيع لأن السيارة مرتبطة بإعلان تأجير قائم.'],
            ]);
        }

        if ($ad->published_at && $ad->published_at->gt(now())) {
            throw ValidationException::withMessages([
                'published_at' => ['تاريخ النشر لا يمكن أن يكون في المستقبل.'],
            ]);
        }

        if ($ad->expires_at && $ad->expires_at->lte(now())) {
            throw ValidationException::withMessages([
                'expires_at' => ['تاريخ انتهاء الإعلان يجب أن يكون في المستقبل.'],
            ]);
        }
    }

    private function normalizeActiveDates(Ad $ad): void
    {
        $ad->update([
            'published_at' => $ad->published_at ?: now(),
            'expires_at' => $ad->expires_at && $ad->expires_at->gt(now())
                ? $ad->expires_at
                : now()->addDays(30),
            'rejection_reason' => null,
            'expiration_reminder_sent_at' => null,
        ]);
    }

    private function approveAdRecord(Ad $ad): void
    {
        $isRenewal = $ad->status === 'expired';

        $this->ensureTransitionAllowed($ad->status, 'active');

        if ($isRenewal) {
            $ad->published_at = (!$ad->published_at || $ad->published_at->isFuture())
                ? now()
                : $ad->published_at;
            $ad->expires_at = (!$ad->expires_at || $ad->expires_at->lte(now()))
                ? now()->addDays(30)
                : $ad->expires_at;
            $ad->save();
        }

        $this->validateAdCanBeActivated($ad);

        $ad->status = 'active';
        $ad->save();
        $this->normalizeActiveDates($ad);

        NotificationService::send(
            $ad->user_id,
            $isRenewal ? 'تم تجديد إعلان البيع' : 'تم قبول إعلان البيع',
            $isRenewal
                ? 'تم تجديد إعلان البيع الخاص بك لمدة 30 يوماً.'
                : 'تمت الموافقة على إعلان البيع الخاص بك وأصبح فعالاً.',
            $isRenewal ? 'ad_renewed_by_admin' : 'ad_approved',
            ['ad_id' => $ad->id]
        );

        if ($isRenewal) {
            return;
        }

        $ad->loadMissing('user.accountType');
        $owner = $ad->user;

        if ($owner && $owner->accountType && $owner->accountType->name === 'company') {
            FollowerNotificationService::notifyCompanyFollowers(
                $owner,
                'إعلان جديد من ' . $owner->name,
                'قامت الشركة التي تتابعها بنشر إعلان بيع جديد.',
                'followed_company_new_sale_ad',
                [
                    'ad_id' => $ad->id,
                    'target_type' => 'sale_ad',
                    'target_id' => $ad->id,
                ]
            );
        }
    }

    private function rejectAdRecord(Ad $ad, string $reason): void
    {
        $this->ensureTransitionAllowed($ad->status, 'rejected');

        $ad->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'published_at' => null,
            'expires_at' => null,
            'expiration_reminder_sent_at' => null,
        ]);

        NotificationService::send(
            $ad->user_id,
            'تم رفض إعلان البيع',
            'تم رفض إعلان البيع الخاص بك للسبب: ' . $reason,
            'ad_rejected',
            ['ad_id' => $ad->id, 'reason' => $reason]
        );
    }

    private function markAdAsSold(Ad $ad): void
    {
        $this->ensureTransitionAllowed($ad->status, 'sold');

        $ad->update([
            'status' => 'sold',
            'rejection_reason' => null,
            'expiration_reminder_sent_at' => null,
        ]);

        NotificationService::send(
            $ad->user_id,
            'تم تعليم إعلان البيع كمباع',
            'تم تغيير حالة إعلان البيع الخاص بك إلى مباع.',
            'ad_sold',
            ['ad_id' => $ad->id]
        );
    }

    private function markAdAsExpired(Ad $ad): void
    {
        $this->ensureTransitionAllowed($ad->status, 'expired');

        $ad->update([
            'status' => 'expired',
            'expires_at' => (!$ad->expires_at || $ad->expires_at->isFuture()) ? now() : $ad->expires_at,
            'expiration_reminder_sent_at' => null,
            'rejection_reason' => null,
        ]);
    }
}
