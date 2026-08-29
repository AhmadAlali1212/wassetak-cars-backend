<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * وحدة Disputes من طبقة Admin API الجديدة — نفس اتفاقية باقي الوحدات
 * (راجع تعليق ReportController.php لنفس التوضيح).
 */
class DisputeController extends Controller
{
    private const PER_PAGE = 20;

    private function transform(Dispute $dispute): array
    {
        return [
            'id' => $dispute->id,
            'raised_by_user' => $dispute->raisedByUser ? [
                'id' => $dispute->raisedByUser->id,
                'name' => $dispute->raisedByUser->name,
                'email' => $dispute->raisedByUser->email,
            ] : null,
            'against_user' => $dispute->againstUser ? [
                'id' => $dispute->againstUser->id,
                'name' => $dispute->againstUser->name,
                'email' => $dispute->againstUser->email,
            ] : null,
            'transaction_type' => $dispute->transaction_type,
            'transaction_id' => $dispute->transaction_id,
            'category' => $dispute->category,
            'description' => $dispute->description,
            'status' => $dispute->status,
            'admin_notes' => $dispute->admin_notes,
            'resolved_at' => $dispute->resolved_at,
            'created_at' => $dispute->created_at,
            'updated_at' => $dispute->updated_at,
        ];
    }

    public function index(Request $request)
    {
        $query = Dispute::with(['raisedByUser:id,name,email', 'againstUser:id,name,email'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $query->paginate(self::PER_PAGE);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => collect($paginator->items())->map(fn ($d) => $this->transform($d))->all(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'message' => 'تم جلب النزاعات بنجاح.',
        ]);
    }

    public function show($id)
    {
        $dispute = Dispute::with(['raisedByUser:id,name,email', 'againstUser:id,name,email'])->find($id);

        if (!$dispute) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'النزاع غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transform($dispute),
            'message' => 'تم جلب تفاصيل النزاع بنجاح.',
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,in_review,resolved,rejected',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $dispute = Dispute::find($id);
        if (!$dispute) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'النزاع غير موجود.',
            ], 404);
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

        \App\Models\AdminAuditLog::record($request->user(), 'dispute.update', $dispute, 'تحديث حالة نزاع', ['new_status' => $request->status]);

        return response()->json([
            'success' => true,
            'data' => $this->transform($dispute->fresh(['raisedByUser:id,name,email', 'againstUser:id,name,email'])),
            'message' => '✅ تم تحديث حالة النزاع.',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $dispute = Dispute::find($id);
        if (!$dispute) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'النزاع غير موجود.',
            ], 404);
        }

        $dispute->delete();

        \App\Models\AdminAuditLog::record($request->user(), 'dispute.destroy', null, 'حذف نزاع', ['dispute_id' => $id]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => '✅ تم حذف النزاع.',
        ]);
    }
}
