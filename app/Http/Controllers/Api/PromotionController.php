<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('accountType');

        if (!$user->isMobileUser()) {
            return response()->json([
                'message' => 'هذه الخدمة متاحة فقط للمستخدم الفردي والشركة.'
            ], 403);
        }

        /*
         * إذا أرسل المستخدم mine=1
         * نعرض عروضه هو فقط، بما فيها pending / active / rejected / expired
         */
        if ($request->boolean('mine')) {
            $query = Promotion::with('user')
                ->where('user_id', $user->id)
                ->latest();

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            return response()->json([
                'message' => 'تم جلب عروضك الترويجية بنجاح.',
                'promotions' => $query->paginate(15),
            ]);
        }

        /*
         * بدون mine=1
         * نعرض العروض الفعالة فقط للمستخدمين داخل التطبيق.
         */
        $promotions = Promotion::with('user')
            ->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->latest()
            ->paginate(15);

        return response()->json([
            'message' => 'تم جلب العروض الترويجية الفعالة بنجاح.',
            'promotions' => $promotions,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user()->load('accountType');

        if (!$user->isMobileUser()) {
            return response()->json([
                'message' => 'إنشاء العروض الترويجية متاح فقط للمستخدم الفردي والشركة.'
            ], 403);
        }

        if (!$user->hasActiveSubscription()) {
            return response()->json([
                'message' => 'يجب أن يكون لديك اشتراك نشط لإنشاء عرض ترويجي.'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['required', Rule::in(['discount', 'cashback', 'bundle_offer'])],
            'value' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'quantity' => 'nullable|integer|min:1',
        ]);

        // التحقق من أن تاريخ انتهاء العرض لا يتجاوز تاريخ انتهاء اشتراك المستخدم،
        // وإلا سيبقى العرض "active" (بعد موافقة الأدمن) حتى بعد انتهاء الاشتراك فعلياً.
        if (!empty($user->subscription_ends_at) && \Carbon\Carbon::parse($validated['end_date'])->gt($user->subscription_ends_at)) {
            return response()->json([
                'message' => '❌ لا يمكن إنشاء هذا العرض.',
                'reason' => 'تاريخ انتهاء العرض (' . $validated['end_date'] . ') يتجاوز تاريخ انتهاء اشتراكك الحالي (' . $user->subscription_ends_at->toDateString() . '). اختر تاريخاً أقرب أو جدّد اشتراكك أولاً.',
            ], 422);
        }

        $validated['user_id'] = $user->id;
        $validated['status'] = 'pending';

        $promotion = Promotion::create($validated);

        return response()->json([
            'message' => 'تم إنشاء العرض الترويجي بنجاح. سيتم مراجعته من قبل الإدارة قبل التفعيل.',
            'promotion' => $promotion->load('user'),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user()->load('accountType');

        if (!$user->isMobileUser()) {
            return response()->json([
                'message' => 'هذه الخدمة متاحة فقط للمستخدم الفردي والشركة.'
            ], 403);
        }

        $promotion = Promotion::with('user')->find($id);

        if (!$promotion) {
            return response()->json([
                'message' => 'العرض الترويجي غير موجود.'
            ], 404);
        }

        /*
         * صاحب العرض يرى عرضه مهما كانت حالته.
         */
        if ($promotion->user_id === $user->id) {
            return response()->json([
                'message' => 'تم جلب تفاصيل العرض الترويجي بنجاح.',
                'promotion' => $promotion,
            ]);
        }

        /*
         * غير صاحب العرض يرى فقط العروض active وضمن التاريخ.
         */
        if (
            $promotion->status !== 'active'
            || $promotion->start_date > now()
            || $promotion->end_date < now()
        ) {
            return response()->json([
                'message' => 'العرض الترويجي غير متاح حالياً.'
            ], 404);
        }

        return response()->json([
            'message' => 'تم جلب تفاصيل العرض الترويجي بنجاح.',
            'promotion' => $promotion,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user()->load('accountType');

        if (!$user->isMobileUser()) {
            return response()->json([
                'message' => 'تعديل العروض الترويجية متاح فقط للمستخدم الفردي والشركة.'
            ], 403);
        }

        $promotion = Promotion::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$promotion) {
            return response()->json([
                'message' => 'العرض الترويجي غير موجود أو ليس لديك صلاحية لتعديله.'
            ], 404);
        }

        if ($promotion->status === 'active') {
            return response()->json([
                'message' => 'لا يمكن تعديل العرض بعد تفعيله من قبل الإدارة.'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['sometimes', 'required', Rule::in(['discount', 'cashback', 'bundle_offer'])],
            'value' => 'nullable|numeric|min:0',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'quantity' => 'nullable|integer|min:1',
        ]);

        // نفس فحص store(): تاريخ الانتهاء الجديد (أو القديم إذا لم يُرسَل) يجب ألا يتجاوز اشتراك المستخدم
        $effectiveEndDate = $validated['end_date'] ?? $promotion->end_date;
        if (!empty($user->subscription_ends_at) && \Carbon\Carbon::parse($effectiveEndDate)->gt($user->subscription_ends_at)) {
            return response()->json([
                'message' => '❌ لا يمكن تحديث هذا العرض.',
                'reason' => 'تاريخ انتهاء العرض يتجاوز تاريخ انتهاء اشتراكك الحالي (' . $user->subscription_ends_at->toDateString() . ').',
            ], 422);
        }

        /*
         * أي تعديل يرجع العرض pending حتى يراجعه الآدمن من جديد،
         * مع حذف سبب الرفض القديم كي لا يظهر بعد إعادة الإرسال.
         */
        $promotion->fill($validated);
        $promotion->status = 'pending';
        $promotion->rejection_reason = null;
        $promotion->save();

        return response()->json([
            'message' => 'تم تعديل العرض الترويجي بنجاح. عاد العرض إلى حالة انتظار المراجعة.',
            'promotion' => $promotion->fresh('user'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user()->load('accountType');

        if (!$user->isMobileUser()) {
            return response()->json([
                'message' => 'حذف العروض الترويجية متاح فقط للمستخدم الفردي والشركة.'
            ], 403);
        }

        $promotion = Promotion::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$promotion) {
            return response()->json([
                'message' => 'العرض الترويجي غير موجود أو ليس لديك صلاحية لحذفه.'
            ], 404);
        }

        if ($promotion->status === 'active') {
            return response()->json([
                'message' => 'لا يمكن حذف العرض بعد تفعيله من قبل الإدارة.'
            ], 403);
        }

        $promotion->delete();

        return response()->json([
            'message' => 'تم حذف العرض الترويجي بنجاح.'
        ]);
    }
}
