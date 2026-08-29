<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

/**
 * سجل تدقيق إجراءات الأدمن — read-only بالكامل (لا update/destroy؛
 * سجل تدقيق يجب ألا يكون قابلاً للتعديل أو الحذف، وإلا فقد غايته).
 */
class AuditLogController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request)
    {
        $query = AdminAuditLog::with('admin:id,name,email')->latest('created_at');

        if ($request->filled('admin_id')) {
            $query->where('admin_id', $request->input('admin_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->input('action') . '%');
        }

        $paginator = $query->paginate(self::PER_PAGE);

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
            'message' => 'تم جلب سجل التدقيق بنجاح.',
        ]);
    }
}
