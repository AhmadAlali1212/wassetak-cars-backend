<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Auction;
use App\Models\RentalAd;
use App\Models\Transaction;
use Illuminate\Http\Request;

/**
 * وحدة Transactions من Admin API — بنطاق مُقلَّص عمداً لـ index/show
 * فقط، بلا update/destroy إطلاقاً. هذا قرار منتج وليس سهواً: السجل
 * المالي غير قابل للتعديل/الحذف يدوياً بتصميم الكنترولر القديم نفسه
 * (كان يرمي abort(405) صراحة)، والموافقة/الرفض الفعليان على طلبات
 * السحب يمرّان حصراً عبر WalletAdminController (قفل صف + معاملة قاعدة
 * بيانات). لا PUT/DELETE مسجَّلة هنا أصلاً، فتُرفض تلقائياً بـ 405 من
 * الـ router مباشرة دون وصول لأي كود.
 */
class TransactionController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request)
    {
        // ⬅️ إضافة eager loading لـ accountType (الأصل يحمّل user فقط)
        $query = Transaction::with('user.accountType')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
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
            'message' => 'تم جلب المعاملات المالية بنجاح.',
        ]);
    }

    public function show(Transaction $transaction)
    {
        // ⬅️ إصلاح: with('transactionable.car') لا يعمل تلقائياً على علاقات
        // morphTo بـ Laravel، يحتاج morphWith() صريحة لكل نوع محتمل حتى
        // يصل car متداخلاً فعلياً داخل transactionable (لأي من Ad/Auction/RentalAd)
        $transaction->load([
            'user.accountType',
            'transactionable' => function ($morphTo) {
                $morphTo->morphWith([
                    Ad::class => ['car'],
                    Auction::class => ['car'],
                    RentalAd::class => ['car'],
                ]);
            },
        ]);

        return response()->json([
            'success' => true,
            'data' => $transaction,
            'message' => 'تم جلب تفاصيل المعاملة بنجاح.',
        ]);
    }
}
