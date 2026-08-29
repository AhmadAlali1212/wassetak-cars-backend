<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Report;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * أبسط مسار تصعيد ممكن للأدمن حالياً على البلاغات والنزاعات: عرض
 * القوائم + تحديث الحالة مع ملاحظة. لا توجد شاشة Flutter لهذا (الأدمن
 * غالباً يستخدم لوحة ويب منفصلة خارج هذا التطبيق)، لكن الـ API جاهز
 * ليُستهلك من أي لوحة أدمن.
 */
class ModerationController extends Controller
{
    public function reports(Request $request)
    {
        $query = Report::with(['reporter:id,name', 'reportedUser:id,name'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'message' => 'تم جلب البلاغات بنجاح.',
            'reports' => $query->paginate((int) $request->input('per_page', 20)),
        ]);
    }

    public function updateReport(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,reviewed,dismissed,action_taken',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $report = Report::find($id);
        if (!$report) {
            return response()->json(['message' => 'البلاغ غير موجود.'], 404);
        }

        $report->update([
            'status' => $request->status,
            'admin_notes' => $request->input('admin_notes'),
            'resolved_at' => in_array($request->status, ['dismissed', 'action_taken'], true) ? now() : null,
        ]);

        return response()->json(['message' => '✅ تم تحديث حالة البلاغ.', 'report' => $report]);
    }

    public function disputes(Request $request)
    {
        $query = Dispute::with(['raisedByUser:id,name', 'againstUser:id,name'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'message' => 'تم جلب النزاعات بنجاح.',
            'disputes' => $query->paginate((int) $request->input('per_page', 20)),
        ]);
    }

    public function updateDispute(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,in_review,resolved,rejected',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dispute = Dispute::find($id);
        if (!$dispute) {
            return response()->json(['message' => 'النزاع غير موجود.'], 404);
        }

        $dispute->update([
            'status' => $request->status,
            'admin_notes' => $request->input('admin_notes'),
            'resolved_at' => in_array($request->status, ['resolved', 'rejected'], true) ? now() : null,
        ]);

        if (in_array($request->status, ['resolved', 'rejected'], true)) {
            foreach ([$dispute->raised_by, $dispute->against_user_id] as $userId) {
                NotificationService::send(
                    $userId,
                    'تم تحديث حالة النزاع',
                    $request->status === 'resolved'
                        ? 'تم حل النزاع رقم #' . $dispute->id . '.'
                        : 'تم رفض النزاع رقم #' . $dispute->id . '.',
                    'dispute_resolved',
                    ['dispute_id' => $dispute->id]
                );
            }
        }

        return response()->json(['message' => '✅ تم تحديث حالة النزاع.', 'dispute' => $dispute]);
    }
}
