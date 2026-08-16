<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ad;
use App\Models\RentalAd;
use App\Models\Car;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\NotificationService;
use App\Services\FollowerNotificationService;
use App\Support\RejectionReasonHelper;

class AdController extends Controller
{
    /**
     * عرض قائمة بجميع إعلانات البيع.
     */
    public function index()
    {
        $ads = Ad::with(['car', 'user'])->paginate(10);
        return view('admin.ads.index', compact('ads'));
    }

    /**
     * عرض تفاصيل إعلان بيع محدد.
     */
    public function show(Ad $ad)
    {
        $ad->load('car.user', 'user');
        return view('admin.ads.show', compact('ad'));
    }

    /**
     * عرض نموذج تعديل إعلان بيع محدد.
     */
    public function edit(Ad $ad)
    {
        $statuses = $this->allowedStatusesFor($ad->status);

        return view('admin.ads.edit', compact('ad', 'statuses'));
    }

    /**
     * تحديث إعلان بيع محدد في قاعدة البيانات.
     */
    public function update(Request $request, Ad $ad)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['draft', 'pending', 'active', 'expired', 'sold', 'rejected'])],
            'price' => 'required|numeric|min:0.01',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:published_at',
            'rejection_reason' => 'nullable|string|max:2000|required_if:status,rejected',
        ]);

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
                    $ad->update([
                        'rejection_reason' => $validated['rejection_reason'],
                    ]);
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

        return redirect()
            ->route('admin.ads.index')
            ->with('success', 'تم تحديث الإعلان بنجاح.');
    }

    /**
     * حذف إعلان بيع محدد من قاعدة البيانات.
     */
    public function destroy(Ad $ad)
    {
        $ad->delete();
        return redirect()->route('admin.ads.index')->with('success', 'تم حذف الإعلان بنجاح.');
    }

    /**
     * عرض الإعلانات المنتظرة الموافقة (API)
     * ملاحظة: عمود approval_status حُذف من جدول ads، والحالة الوحيدة المعتمدة الآن هي status.
     */
    public function pendingAds()
    {
        $ads = Ad::where('status', 'pending')
                 ->with(['car', 'user'])
                 ->paginate(10);

        return response()->json([
            'message' => 'قائمة الإعلانات المنتظرة الموافقة',
            'ads' => $ads,
        ]);
    }

    /**
     * عرض الإعلانات المرفوضة (API)
     */
    public function rejectedAds()
    {
        $ads = Ad::where('status', 'rejected')
                 ->with(['car', 'user'])
                 ->paginate(10);

        return response()->json([
            'message' => 'قائمة الإعلانات المرفوضة',
            'ads' => $ads,
        ]);
    }

    /**
     * عرض الإعلانات المقبولة/الفعالة (API)
     */
    public function approvedAds()
    {
        $ads = Ad::where('status', 'active')
                 ->with(['car', 'user'])
                 ->paginate(10);

        return response()->json([
            'message' => 'قائمة الإعلانات المقبولة',
            'ads' => $ads,
        ]);
    }

    /**
     * عرض إعلانات البيع التي انتهت مدة نشرها (API).
     */
    public function expiredAds()
    {
        $ads = Ad::where('status', 'expired')
            ->with(['car', 'user'])
            ->paginate(10);

        return response()->json([
            'message' => 'قائمة إعلانات البيع المنتهية',
            'ads' => $ads,
        ]);
    }

    /**
     * عرض تفاصيل إعلان محدد (API)
     */
    public function showAd($id)
    {
        $ad = Ad::with(['car.user', 'user'])->find($id);

        if (!$ad) {
            return response()->json(['message' => 'الإعلان غير موجود.'], 404);
        }

        return response()->json([
            'message' => 'تفاصيل الإعلان',
            'ad' => $ad,
        ]);
    }

    /**
     * موافقة الأدمن على إعلان بيع.
     * شروط أساسية:
     * 1. يجب أن تكون السيارة نفسها approved من قبل الأدمن قبل تفعيل إعلان البيع عليها.
     * 2. يجب أن يكون سعر الإعلان أكبر من صفر (السعر > 0)، وذلك لأن الإعلانات
     *    الناتجة عن الرفع الجماعي (bulk-upload) تُنشأ تلقائياً بسعر 0 كمسودة،
     *    فلا يجوز اعتمادها ونشرها للمستخدمين قبل أن يحدّد صاحبها السعر الفعلي
     *    عبر PUT /ads/bulk-publish أو PUT /my-ads/{id}.
     */
    public function approveAd(Request $request, $id)
    {
        $snapshot = Ad::query()->whereKey($id)->first();

        if (!$snapshot) {
            return response()->json(['message' => 'الإعلان غير موجود.'], 404);
        }

        $ad = DB::transaction(function () use ($snapshot) {
            $car = Car::query()
                ->whereKey($snapshot->car_id)
                ->lockForUpdate()
                ->first();

            $ad = Ad::query()
                ->whereKey($snapshot->id)
                ->lockForUpdate()
                ->first();

            if (!$ad || !$car) {
                abort(404, 'الإعلان أو السيارة غير موجودين.');
            }

            $ad->setRelation('car', $car);
            $ad->load('user.accountType');
            $this->approveAdRecord($ad);

            return $ad->fresh()->load('car', 'user');
        });

        return response()->json([
            'message' => 'تمت الموافقة على الإعلان بنجاح.',
            'ad' => $ad,
        ]);
    }

    /**
     * الموافقة الجماعية على عدة إعلانات بيع دفعة واحدة.
     *
     * يقبل الطلب:
     * - batch_id (اختياري): يوافق على كل إعلانات السيارات المنتمية لدفعة رفع جماعي معينة
     *   (car.bulk_upload_batch = batch_id)، تماماً بنفس فكرة موافقة دفعة السيارات.
     * - ad_ids (اختياري): مصفوفة معرفات إعلانات محددة يدوياً من الأدمن.
     * - يمكن إرسال الحقلين معاً؛ في هذه الحالة تُجمَّع كل الإعلانات المطابقة لأي من الشرطين (OR).
     * - يجب إرسال واحد منهما على الأقل.
     *
     * لكل إعلان مطابق وبحالة pending، يُطبَّق نفس شرطي approveAd():
     * السيارة approved + السعر أكبر من صفر. أي إعلان لا يحقق الشروط يُستثنى ويُذكر
     * ضمن "skipped" مع السبب، بدل أن يفشل الطلب بالكامل.
     */
    public function bulkApproveAds(Request $request)
    {
        $request->validate([
            'batch_id' => 'nullable|string',
            'ad_ids' => 'nullable|array|min:1',
            'ad_ids.*' => 'integer|distinct|exists:ads,id',
        ]);

        if (!$request->filled('batch_id') && !$request->filled('ad_ids')) {
            return response()->json([
                'message' => 'يجب إرسال batch_id أو ad_ids (أو كلاهما) لتحديد الإعلانات المطلوب الموافقة عليها.',
            ], 422);
        }

        $ads = Ad::with(['car', 'user.accountType'])
            ->where('status', 'pending')
            ->where(function ($query) use ($request) {
                if ($request->filled('batch_id')) {
                    $query->orWhereHas('car', function ($carQuery) use ($request) {
                        $carQuery->where('bulk_upload_batch', $request->batch_id);
                    });
                }

                if ($request->filled('ad_ids')) {
                    $query->orWhereIn('id', $request->ad_ids);
                }
            })
            ->get();

        if ($ads->isEmpty()) {
            return response()->json([
                'message' => 'لم يتم العثور على إعلانات pending مطابقة للطلب.',
            ], 404);
        }

        $approvedIds = [];
        $skipped = [];

        foreach ($ads as $candidate) {
            try {
                DB::transaction(function () use ($candidate) {
                    $car = Car::query()
                        ->whereKey($candidate->car_id)
                        ->lockForUpdate()
                        ->first();

                    $ad = Ad::query()
                        ->whereKey($candidate->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$ad || !$car || $ad->status !== 'pending') {
                        throw ValidationException::withMessages([
                            'status' => ['تغيّرت حالة الإعلان ولم يعد قابلاً للموافقة الجماعية.'],
                        ]);
                    }

                    $ad->setRelation('car', $car);
                    $ad->load('user.accountType');
                    $this->approveAdRecord($ad);
                });

                $approvedIds[] = $candidate->id;
            } catch (ValidationException $exception) {
                $skipped[] = [
                    'ad_id' => $candidate->id,
                    'reason' => collect($exception->errors())->flatten()->first(),
                ];
            }
        }

        return response()->json([
            'message' => 'تمت معالجة ' . $ads->count() . ' إعلان.',
            'approved_ad_ids' => $approvedIds,
            'skipped' => $skipped,
        ]);
    }

    /**
     * الرفض الجماعي لعدة إعلانات بيع دفعة واحدة.
     * يقبل نفس معايير bulkApproveAds (batch_id و/أو ad_ids)، بالإضافة إلى reason إلزامي
     * (من القائمة الجاهزة GET /admin/rejection-reasons/ad، أو "أخرى" + custom_reason).
     */
    public function bulkRejectAds(Request $request)
    {
        $reason = RejectionReasonHelper::validateAndResolve($request, 'ad');

        $request->validate([
            'batch_id' => 'nullable|string',
            'ad_ids' => 'nullable|array|min:1',
            'ad_ids.*' => 'integer|distinct|exists:ads,id',
        ]);

        if (!$request->filled('batch_id') && !$request->filled('ad_ids')) {
            return response()->json([
                'message' => 'يجب إرسال batch_id أو ad_ids (أو كلاهما) لتحديد الإعلانات المطلوب رفضها.',
            ], 422);
        }

        $ads = Ad::whereIn('status', ['draft', 'pending', 'active'])
            ->where(function ($query) use ($request) {
                if ($request->filled('batch_id')) {
                    $query->orWhereHas('car', function ($carQuery) use ($request) {
                        $carQuery->where('bulk_upload_batch', $request->batch_id);
                    });
                }

                if ($request->filled('ad_ids')) {
                    $query->orWhereIn('id', $request->ad_ids);
                }
            })
            ->get();

        if ($ads->isEmpty()) {
            return response()->json([
                'message' => 'لم يتم العثور على إعلانات قابلة للرفض مطابقة للطلب.',
            ], 404);
        }

        $rejectedIds = [];
        $skipped = [];

        foreach ($ads as $candidate) {
            try {
                DB::transaction(function () use ($candidate, $reason) {
                    $ad = Ad::query()
                        ->whereKey($candidate->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$ad || !in_array($ad->status, ['draft', 'pending', 'active'], true)) {
                        throw ValidationException::withMessages([
                            'status' => ['تغيّرت حالة الإعلان ولم يعد قابلاً للرفض الجماعي.'],
                        ]);
                    }

                    $this->rejectAdRecord($ad, $reason);
                });

                $rejectedIds[] = $candidate->id;
            } catch (ValidationException $exception) {
                $skipped[] = [
                    'ad_id' => $candidate->id,
                    'reason' => collect($exception->errors())->flatten()->first(),
                ];
            }
        }

        return response()->json([
            'message' => 'تم رفض ' . count($rejectedIds) . ' إعلان.',
            'rejected_ad_ids' => $rejectedIds,
            'skipped' => $skipped,
        ]);
    }

    /**
     * رفض الأدمن لإعلان بيع.
     */
    /**
     * إرجاع قائمة أسباب رفض إعلان البيع الجاهزة (لعرضها كقائمة اختيار في لوحة الأدمن).
     */
    public function rejectionReasons()
    {
        return response()->json([
            'reasons' => RejectionReasonHelper::list('ad'),
        ]);
    }

    public function rejectAd(Request $request, $id)
    {
        $reason = RejectionReasonHelper::validateAndResolve($request, 'ad');

        $ad = DB::transaction(function () use ($id, $reason) {
            $ad = Ad::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (!$ad) {
                abort(404, 'الإعلان غير موجود.');
            }

            $this->rejectAdRecord($ad, $reason);

            return $ad->fresh()->load('car', 'user');
        });

        return response()->json([
            'message' => 'تم رفض الإعلان بنجاح.',
            'ad' => $ad,
        ]);
    }

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
        // مسارات التفعيل تقفل صف السيارة أولاً ثم صف الإعلان لمنع التعارض
        // مع إنشاء أو تعديل إعلان تأجير في اللحظة نفسها.
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

        // إشعار المتابعين يكون عند أول اعتماد فقط، وليس في كل تجديد.
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
