<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use App\Services\FollowerNotificationService;
use App\Support\RejectionReasonHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


class PromotionController extends Controller
{
    public function index()
    {
        $promotions = Promotion::with('user')
            ->latest()
            ->paginate(10);

        return view('admin.promotions.index', compact('promotions'));
    }

    public function show(Promotion $promotion)
    {
        $promotion->load('user');

        return view('admin.promotions.show', compact('promotion'));
    }

    public function edit(Promotion $promotion)
    {
        $promotion->load('user');
        $statuses = $this->allowedStatusesFor($promotion->status);

        return view('admin.promotions.edit', compact('promotion', 'statuses'));
    }

    // public function update(Request $request, Promotion $promotion)
    // {
    //     $validated = $request->validate([
    //         'status' => ['required', Rule::in(['pending', 'active', 'rejected', 'expired'])],
    //     ]);

    //     $promotion->update($validated);

    //     return redirect()
    //         ->route('admin.promotions.index')
    //         ->with('success', 'تم تحديث حالة العرض الترويجي بنجاح.');
    // }
    public function update(Request $request, Promotion $promotion)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'active', 'rejected', 'expired'])],
            'rejection_reason' => 'nullable|string|max:2000|required_if:status,rejected',
        ]);

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
                    $promotion->update([
                        'rejection_reason' => $validated['rejection_reason'],
                    ]);
                } elseif ($targetStatus === 'pending') {
                    $promotion->update([
                        'rejection_reason' => null,
                    ]);
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

        return redirect()
            ->route('admin.promotions.index')
            ->with('success', 'تم تحديث حالة العرض الترويجي بنجاح.');
    }

    public function destroy(Promotion $promotion)
    {
        $promotion->delete();

        return redirect()
            ->route('admin.promotions.index')
            ->with('success', 'تم حذف العرض الترويجي بنجاح.');
    }

    /**
     * عرض العروض الترويجية قيد المراجعة (pending)
     */
    public function pending()
    {
        $promotions = Promotion::where('status', 'pending')
            ->with('user')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'تم جلب العروض الترويجية قيد المراجعة بنجاح.',
            'count' => $promotions->count(),
            'promotions' => $promotions->map(function ($promotion) {
                return [
                    'id' => $promotion->id,
                    'user_id' => $promotion->user_id,
                    'title' => $promotion->title,
                    'description' => $promotion->description,
                    'status' => $promotion->status,
                    'value' => $promotion->value,
                    'start_date' => $promotion->start_date,
                    'end_date' => $promotion->end_date,
                    'created_at' => $promotion->created_at,
                    'user' => [
                        'id' => $promotion->user->id,
                        'name' => $promotion->user->name,
                        'email' => $promotion->user->email,
                    ],
                ];
            }),
        ]);
    }

    /**
     * عرض العروض الترويجية الموافق عليها (approved)
     */
    public function approved()
    {
        $promotions = Promotion::where('status', 'active')
            ->with('user')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'تم جلب العروض الترويجية الموافق عليها بنجاح.',
            'count' => $promotions->count(),
            'promotions' => $promotions->map(function ($promotion) {
                return [
                    'id' => $promotion->id,
                    'user_id' => $promotion->user_id,
                    'title' => $promotion->title,
                    'description' => $promotion->description,
                    'status' => $promotion->status,
                    'value' => $promotion->value,
                    'start_date' => $promotion->start_date,
                    'end_date' => $promotion->end_date,
                    'created_at' => $promotion->created_at,
                    'user' => [
                        'id' => $promotion->user->id,
                        'name' => $promotion->user->name,
                        'email' => $promotion->user->email,
                    ],
                ];
            }),
        ]);
    }

    /**
     * عرض العروض الترويجية المرفوضة (rejected)
     */
    public function rejected()
    {
        $promotions = Promotion::where('status', 'rejected')
            ->with('user')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'تم جلب العروض الترويجية المرفوضة بنجاح.',
            'count' => $promotions->count(),
            'promotions' => $promotions->map(function ($promotion) {
                return [
                    'id' => $promotion->id,
                    'user_id' => $promotion->user_id,
                    'title' => $promotion->title,
                    'description' => $promotion->description,
                    'status' => $promotion->status,
                    'value' => $promotion->value,
                    'start_date' => $promotion->start_date,
                    'end_date' => $promotion->end_date,
                    'created_at' => $promotion->created_at,
                    'user' => [
                        'id' => $promotion->user->id,
                        'name' => $promotion->user->name,
                        'email' => $promotion->user->email,
                    ],
                ];
            }),
        ]);
    }

    /**
     * موافقة الأدمن على العرض الترويجي
     */
    public function approve($id)
    {
        $promotion = Promotion::with('user.accountType')->find($id);

        if (!$promotion) {
            return response()->json(['message' => 'العرض الترويجي غير موجود.'], 404);
        }

        DB::transaction(function () use ($promotion) {
            $this->approvePromotionRecord($promotion);
        });

        return response()->json([
            'message' => 'تم قبول العرض الترويجي بنجاح.',
            'promotion' => $promotion->fresh()->load('user'),
        ]);
    }

    /**
     * رفض الأدمن للعرض الترويجي
     */
    /**
     * إرجاع قائمة أسباب رفض العرض الترويجي الجاهزة (لعرضها كقائمة اختيار في لوحة الأدمن).
     */
    public function rejectionReasons()
    {
        return response()->json([
            'reasons' => RejectionReasonHelper::list('promotion'),
        ]);
    }

    public function reject(Request $request, $id)
    {
        $reason = RejectionReasonHelper::validateAndResolve($request, 'promotion');

        $promotion = Promotion::find($id);

        if (!$promotion) {
            return response()->json(['message' => 'العرض الترويجي غير موجود.'], 404);
        }

        DB::transaction(function () use ($promotion, $reason) {
            $this->rejectPromotionRecord($promotion, $reason);
        });

        return response()->json([
            'message' => 'تم رفض العرض الترويجي بنجاح.',
            'reason' => $reason,
            'promotion' => $promotion->fresh()->load('user'),
        ]);
    }

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
