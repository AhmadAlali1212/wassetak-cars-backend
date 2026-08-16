<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RentalAd;
use App\Models\Ad;
use App\Models\Car;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\NotificationService;
use App\Support\RejectionReasonHelper;

class RentalAdController extends Controller
{
    /**
     * Display a listing of the rental ads. (Blade view)
     */
    public function index()
    {
        $rentalAds = RentalAd::with(['car', 'user'])->paginate(10);
        return view('admin.rental-ads.index', compact('rentalAds'));
    }

    /**
     * Display the specified rental ad. (Blade view)
     */
    public function show(RentalAd $rentalAd)
    {
        $rentalAd->load('car.user', 'user');
        return view('admin.rental-ads.show', compact('rentalAd'));
    }

    /**
     * Show the form for editing the specified rental ad. (Blade view)
     */
    public function edit(RentalAd $rentalAd)
    {
        $statuses = $this->allowedStatusesFor($rentalAd->status);

        return view('admin.rental-ads.edit', compact('rentalAd', 'statuses'));
    }

    /**
     * Update the specified rental ad in storage. (Blade view form)
     */
    public function update(Request $request, RentalAd $rentalAd)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'active', 'rejected'])],
            'daily_price' => 'nullable|numeric|min:0.01',
            'monthly_price' => 'nullable|numeric|min:0.01',
            'rental_conditions' => 'nullable|string',
            'location' => 'required|string|max:255',
            'rejection_reason' => 'nullable|string|max:2000|required_if:status,rejected',
        ]);

        if (
            (float) ($validated['daily_price'] ?? 0) <= 0
            && (float) ($validated['monthly_price'] ?? 0) <= 0
        ) {
            throw ValidationException::withMessages([
                'daily_price' => ['يجب تحديد سعر يومي أو شهري أكبر من صفر على الأقل.'],
            ]);
        }

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
                    $rentalAd->update([
                        'rejection_reason' => $validated['rejection_reason'],
                    ]);
                } elseif ($targetStatus === 'pending') {
                    $rentalAd->update([
                        'rejection_reason' => null,
                    ]);
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

        return redirect()->route('admin.rental-ads.index')
            ->with('success', 'تم تحديث إعلان التأجير بنجاح.');
    }

    /**
     * Remove the specified rental ad from storage. (Blade view)
     */
    public function destroy(RentalAd $rentalAd)
    {
        $rentalAd->delete();
        return redirect()->route('admin.rental-ads.index')->with('success', 'تم حذف إعلان التأجير بنجاح.');
    }

    // ========================================================
    // API endpoints (JSON) — نفس نمط AdController تماماً
    // ========================================================

    /**
     * عرض إعلانات التأجير المنتظرة الموافقة (API)
     */
    public function pendingRentalAds()
    {
        $rentalAds = RentalAd::where('status', 'pending')
                              ->with(['car', 'user'])
                              ->paginate(10);

        return response()->json([
            'message' => 'قائمة إعلانات التأجير المنتظرة الموافقة',
            'rental_ads' => $rentalAds,
        ]);
    }

    /**
     * عرض إعلانات التأجير المرفوضة (API)
     */
    public function rejectedRentalAds()
    {
        $rentalAds = RentalAd::where('status', 'rejected')
                              ->with(['car', 'user'])
                              ->paginate(10);

        return response()->json([
            'message' => 'قائمة إعلانات التأجير المرفوضة',
            'rental_ads' => $rentalAds,
        ]);
    }

    /**
     * عرض إعلانات التأجير المقبولة/الفعالة (API)
     */
    public function approvedRentalAds()
    {
        $rentalAds = RentalAd::where('status', 'active')
                              ->with(['car', 'user'])
                              ->paginate(10);

        return response()->json([
            'message' => 'قائمة إعلانات التأجير المقبولة',
            'rental_ads' => $rentalAds,
        ]);
    }

    /**
     * عرض تفاصيل إعلان تأجير محدد (API)
     */
    public function showRentalAd($id)
    {
        $rentalAd = RentalAd::with(['car.user', 'user'])->find($id);

        if (!$rentalAd) {
            return response()->json(['message' => 'إعلان التأجير غير موجود.'], 404);
        }

        return response()->json([
            'message' => 'تفاصيل إعلان التأجير',
            'rental_ad' => $rentalAd,
        ]);
    }

    /**
     * موافقة الأدمن على إعلان تأجير.
     * الشروط:
     * 1. يجب أن تكون السيارة نفسها approved من قبل الأدمن قبل تفعيل إعلان التأجير عليها.
     * 2. يجب أن يكون هناك سعر فعلي محدد (daily_price أو monthly_price أكبر من صفر) —
     *    التحديث (update) بالـ API يسمح بتصفير السعرين (min:0)، فبدون هذا الفحص كان يمكن
     *    الموافقة على إعلان تأجير "مجاني" فعلياً بالخطأ.
     */
    public function approveRentalAd(Request $request, $id)
    {
        $rentalAd = RentalAd::with(['car', 'user.accountType'])->find($id);

        if (!$rentalAd) {
            return response()->json(['message' => 'إعلان التأجير غير موجود.'], 404);
        }

        DB::transaction(function () use ($rentalAd) {
            $this->approveRentalAdRecord($rentalAd);
        });

        return response()->json([
            'message' => 'تمت الموافقة على إعلان التأجير بنجاح.',
            'rental_ad' => $rentalAd->fresh()->load('car', 'user'),
        ]);
    }

    /**
     * رفض الأدمن لإعلان تأجير.
     */
    /**
     * إرجاع قائمة أسباب رفض إعلان التأجير الجاهزة (لعرضها كقائمة اختيار في لوحة الأدمن).
     */
    public function rejectionReasons()
    {
        return response()->json([
            'reasons' => RejectionReasonHelper::list('rental_ad'),
        ]);
    }

    public function rejectRentalAd(Request $request, $id)
    {
        $reason = RejectionReasonHelper::validateAndResolve($request, 'rental_ad');

        $rentalAd = RentalAd::find($id);

        if (!$rentalAd) {
            return response()->json(['message' => 'إعلان التأجير غير موجود.'], 404);
        }

        DB::transaction(function () use ($rentalAd, $reason) {
            $this->rejectRentalAdRecord($rentalAd, $reason);
        });

        return response()->json([
            'message' => 'تم رفض إعلان التأجير بنجاح.',
            'rental_ad' => $rentalAd->fresh()->load('car', 'user'),
        ]);
    }

    private function allowedStatusesFor(string $currentStatus): array
    {
        $transitions = [
            'pending' => ['pending', 'active', 'rejected'],
            'active' => ['active', 'rejected'],
            'rejected' => ['rejected', 'pending'],
            // دعم السجلات القديمة: إعادتها إلى active بدلاً من إبقائها عالقة.
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








