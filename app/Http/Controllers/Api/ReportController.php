<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     * إبلاغ عن مستخدم (احتيال، إساءة، محتوى غير لائق، سبام، إلخ)، مع ربط
     * اختياري بسياق محدد (رسالة، إعلان، مزاد...) لتسهيل المراجعة لاحقاً.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reported_user_id' => 'required|integer|exists:users,id',
            'reason' => 'required|in:fraud,abuse,inappropriate_content,spam,other',
            'details' => 'nullable|string|max:2000',
            'context_type' => 'nullable|in:message,ad,auction,rental_ad,rental_booking,rating',
            'context_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reporter = $request->user();

        if ((int) $request->reported_user_id === (int) $reporter->id) {
            return response()->json(['message' => 'لا يمكنك الإبلاغ عن نفسك.'], 422);
        }

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $request->reported_user_id,
            'reason' => $request->reason,
            'details' => $request->input('details'),
            'context_type' => $request->input('context_type'),
            'context_id' => $request->input('context_id'),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => '✅ تم استلام بلاغك، سيراجعه فريقنا قريباً.',
            'report' => $report,
        ], 201);
    }

    /** بلاغاتي التي رفعتها (لمتابعة حالتها). */
    public function myReports(Request $request)
    {
        $reports = Report::where('reporter_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json([
            'message' => 'تم جلب بلاغاتك بنجاح.',
            'reports' => $reports,
        ]);
    }
}
