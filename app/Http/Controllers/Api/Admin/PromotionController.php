<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\FollowerNotificationService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * وحدة Promotions من Admin API — مثل Auctions، لا يوجد أي مسار JSON
 * قديم لـ admin/promotions إطلاقاً، فبُنيت الأربعة endpoints كاملة.
 * منطق update/destroy منسوخ حرفياً 1:1 من الأصل بلا أي استثناء تصميمي
 * مطلوب (الأصل يستخدم ValidationException بشكل صحيح ومتّسق أصلاً).
 */
class PromotionController extends Controller
{
    private const PER_PAGE = 20;

    public function index()
    {
        $paginator = Promotion::with('user')->latest()->paginate(self::PER_PAGE);

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
            'message' => 'تم جلب العروض الترويجية بنجاح.',
        ]);
    }

    public function show(Promotion $promotion)
    {
        $promotion->load('user');

        return response()->json([
            'success' => true,
            'data' => $promotion,
            'message' => 'تم جلب تفاصيل العرض الترويجي بنجاح.',
        ]);
    }

    public function update(Request $request, Promotion $promotion)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['pending', 'active', 'rejected', 'expired'])],
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
            DB::transaction(function () use ($promotion, $validated) {
                $oldStatus = $promotion->status;
                $targetStatus = $validated['status'];

                if ($oldStatus === 'expired') {
                    throw ValidationException::withMessages([
                        'status' => ['العرض منتهي، ولا يمكن إعادة فتحه أو تغيير حالته.'],
                    ]);
                }

                $this->ensureTransitionAllowed($oldStatus, $targetStatus);

                if ($oldStatus === $targetStatus) {
                    if ($targetStatus === 'active') {
                        $this->validatePromotionCanBeActivated($promotion);
                    }

                    if ($targetStatus === 'rejected') {
                        $promotion->update(['rejection_reason' => $validated['rejection_reason']]);
                    } elseif ($targetStatus === 'pending') {
                        $promotion->update(['rejection_reason' => null]);
                    }

                    return;
                }

                if ($targetStatus === 'active') {
                    $this->approvePromotionRecord($promotion);
                    return;
                }

                if ($targetStatus === 'rejected') {
                    $this->rejectPromotionRecord($promotion, $validated['rejection_reason']);
                    return;
                }

                if ($targetStatus === 'expired') {
                    $this->expirePromotionRecord($promotion);
                    return;
                }

                $promotion->update([
                    'status' => 'pending',
                    'rejection_reason' => null,
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

        \App\Models\AdminAuditLog::record($request->user(), 'promotion.update', $promotion, 'تحديث عرض ترويجي', ['new_status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'data' => $promotion->fresh('user'),
            'message' => 'تم تحديث حالة العرض الترويجي بنجاح.',
        ]);
    }

    public function destroy(Request $request, Promotion $promotion)
    {
        $promotionId = $promotion->id;
        $promotion->delete();

        \App\Models\AdminAuditLog::record($request->user(), 'promotion.destroy', null, 'حذف عرض ترويجي', ['promotion_id' => $promotionId]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'تم حذف العرض الترويجي بنجاح.',
        ]);
    }

    // ==================== الدوال الخاصة (منسوخة حرفياً من الأصل) ====================

    private function allowedStatusesFor(string $currentStatus): array
    {
        $transitions = [
            'pending' => ['pending', 'active', 'rejected', 'expired'],
            'active' => ['active', 'rejected', 'expired'],
            'rejected' => ['rejected', 'pending'],
            'expired' => ['expired'],
        ];

        return $transitions[$currentStatus] ?? [$currentStatus];
    }

    private function ensureTransitionAllowed(string $from, string $to): void
    {
        if (!in_array($to, $this->allowedStatusesFor($from), true)) {
            throw ValidationException::withMessages([
                'status' => ["لا يمكن تغيير حالة العرض الترويجي من {$from} إلى {$to}."],
            ]);
        }
    }

    private function validatePromotionCanBeActivated(Promotion $promotion): void
    {
        $promotion->loadMissing('user.accountType');
        $owner = $promotion->user;

        if (!$owner || !$owner->isMobileUser()) {
            throw ValidationException::withMessages([
                'status' => ['صاحب العرض غير موجود أو نوع حسابه لا يسمح بالعروض الترويجية.'],
            ]);
        }

        if (!$owner->hasActiveSubscription()) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تفعيل العرض لأن اشتراك صاحبه غير نشط.'],
            ]);
        }

        if (!$promotion->start_date || !$promotion->end_date || $promotion->end_date->lt($promotion->start_date)) {
            throw ValidationException::withMessages([
                'status' => ['فترة العرض غير صحيحة.'],
            ]);
        }

        if ($promotion->end_date->lte(now())) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تفعيل عرض انتهت مدته.'],
            ]);
        }

        if (!$owner->subscription_ends_at || $promotion->end_date->gt($owner->subscription_ends_at)) {
            throw ValidationException::withMessages([
                'status' => ['تاريخ انتهاء العرض يتجاوز تاريخ انتهاء اشتراك صاحبه.'],
            ]);
        }
    }

    private function approvePromotionRecord(Promotion $promotion): void
    {
        $this->ensureTransitionAllowed($promotion->status, 'active');
        $this->validatePromotionCanBeActivated($promotion);

        $promotion->update([
            'status' => 'active',
            'rejection_reason' => null,
        ]);

        NotificationService::send(
            $promotion->user_id,
            'تم قبول العرض الترويجي',
            'قامت الإدارة بتفعيل عرضك الترويجي: ' . $promotion->title,
            'promotion_active',
            ['promotion_id' => $promotion->id]
        );

        $promotion->loadMissing('user.accountType');
        $owner = $promotion->user;

        if ($owner && $owner->accountType && $owner->accountType->name === 'company') {
            FollowerNotificationService::notifyCompanyFollowers(
                $owner,
                'عرض ترويجي جديد من ' . $owner->name,
                'قامت الشركة التي تتابعها بنشر عرض ترويجي جديد: ' . $promotion->title,
                'followed_company_new_promotion',
                [
                    'promotion_id' => $promotion->id,
                    'target_type' => 'promotion',
                    'target_id' => $promotion->id,
                ]
            );
        }
    }

    private function rejectPromotionRecord(Promotion $promotion, string $reason): void
    {
        $this->ensureTransitionAllowed($promotion->status, 'rejected');

        $promotion->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        NotificationService::send(
            $promotion->user_id,
            'تم رفض العرض الترويجي',
            'قامت الإدارة برفض عرضك الترويجي: ' . $promotion->title . '. السبب: ' . $reason,
            'promotion_rejected',
            ['promotion_id' => $promotion->id, 'reason' => $reason]
        );
    }

    private function expirePromotionRecord(Promotion $promotion): void
    {
        $this->ensureTransitionAllowed($promotion->status, 'expired');

        $promotion->update([
            'status' => 'expired',
            'rejection_reason' => null,
        ]);

        NotificationService::send(
            $promotion->user_id,
            'انتهى العرض الترويجي',
            'تم تعليم عرضك الترويجي كمنتهي: ' . $promotion->title,
            'promotion_expired',
            ['promotion_id' => $promotion->id]
        );
    }
}
