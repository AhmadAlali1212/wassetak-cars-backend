<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * وحدة Reports من طبقة Admin API الجديدة (نفس اتفاقية الوحدات الموثّقة
 * بـ ADMIN_API_NOTES.md: غلاف موحّد {success,data,message}، pagination
 * بشكل data.items + data.pagination، per_page=20 ثابتة). مخصصة للوحة
 * React (middleware: auth:sanctum + admin.api)، منفصلة تماماً عن
 * app/Http/Controllers/Admin/ModerationController.php القديم (يبقى كما
 * هو دون أي تعديل، ويستخدم middleware 'admin' العادي وغلاف استجابة
 * مختلف — لم يعد الوجهة الصحيحة لأي استهلاك من React).
 */
class ReportController extends Controller
{
    private const PER_PAGE = 20;

    private function transform(Report $report): array
    {
        return [
            'id' => $report->id,
            'reporter' => $report->reporter ? [
                'id' => $report->reporter->id,
                'name' => $report->reporter->name,
                'email' => $report->reporter->email,
            ] : null,
            'reported_user' => $report->reportedUser ? [
                'id' => $report->reportedUser->id,
                'name' => $report->reportedUser->name,
                'email' => $report->reportedUser->email,
            ] : null,
            'reason' => $report->reason,
            'details' => $report->details,
            'context_type' => $report->context_type,
            'context_id' => $report->context_id,
            'status' => $report->status,
            'admin_notes' => $report->admin_notes,
            'resolved_at' => $report->resolved_at,
            'created_at' => $report->created_at,
            'updated_at' => $report->updated_at,
        ];
    }

    public function index(Request $request)
    {
        $query = Report::with(['reporter:id,name,email', 'reportedUser:id,name,email'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $query->paginate(self::PER_PAGE);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => collect($paginator->items())->map(fn ($r) => $this->transform($r))->all(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'message' => 'تم جلب البلاغات بنجاح.',
        ]);
    }

    public function show($id)
    {
        $report = Report::with(['reporter:id,name,email', 'reportedUser:id,name,email'])->find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'البلاغ غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transform($report),
            'message' => 'تم جلب تفاصيل البلاغ بنجاح.',
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,reviewed,dismissed,action_taken',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $report = Report::find($id);
        if (!$report) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'البلاغ غير موجود.',
            ], 404);
        }

        $report->update([
            'status' => $request->status,
            'admin_notes' => $request->input('admin_notes'),
            'resolved_at' => in_array($request->status, ['dismissed', 'action_taken'], true) ? now() : null,
        ]);

        \App\Models\AdminAuditLog::record($request->user(), 'report.update', $report, 'تحديث حالة بلاغ', ['new_status' => $request->status]);

        return response()->json([
            'success' => true,
            'data' => $this->transform($report->fresh(['reporter:id,name,email', 'reportedUser:id,name,email'])),
            'message' => '✅ تم تحديث حالة البلاغ.',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $report = Report::find($id);
        if (!$report) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'البلاغ غير موجود.',
            ], 404);
        }

        $report->delete();

        \App\Models\AdminAuditLog::record($request->user(), 'report.destroy', null, 'حذف بلاغ', ['report_id' => $id]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => '✅ تم حذف البلاغ.',
        ]);
    }
}
