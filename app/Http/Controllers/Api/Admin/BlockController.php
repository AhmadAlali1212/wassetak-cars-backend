<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Block;
use Illuminate\Http\Request;

/**
 * وحدة Blocks من Admin API — كانت فجوة حقيقية بلوحة الأدمن: جدول
 * الحظر (Block) والـ API الخاص بالمستخدمين (POST/GET/DELETE /blocks)
 * موجودين بتطبيق الموبايل منذ جولة "البلاغ/الحظر"، لكن ما كان في أي
 * واجهة أدمن لمراجعة من حظر مين — مثلاً لفك حظر حصل بالخطأ أو بسبب
 * سوء فهم بين طرفين.
 */
class BlockController extends Controller
{
    private const PER_PAGE = 20;

    public function index()
    {
        $paginator = Block::with(['blocker:id,name,email', 'blockedUser:id,name,email'])
            ->latest()
            ->paginate(self::PER_PAGE);

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
            'message' => 'تم جلب سجلات الحظر بنجاح.',
        ]);
    }

    /** فك حظر إداري — يُلغي سجل الحظر بغض النظر عمن أنشأه. */
    public function destroy(Request $request, Block $block)
    {
        $meta = ['blocker_id' => $block->blocker_id, 'blocked_user_id' => $block->blocked_user_id];
        $block->delete();

        AdminAuditLog::record($request->user(), 'block.destroy', null, 'فك حظر إداري بين مستخدمين', $meta);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => '✅ تم فك الحظر بنجاح.',
        ]);
    }
}
