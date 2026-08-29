<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\WalletAdminController as LegacyWalletAdminController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * وحدة Wallet من Admin API — آخر وحدة من كل خطة Admin API، لا وحدات
 * متبقية بعدها. بخلاف كل الوحدات السابقة، لم تُعَد كتابتها إطلاقاً:
 * كل الخمس نقاط تفوّض مباشرة لـ Admin\WalletAdminController القديم
 * بلا أي نسخ لمنطق الأعمال (قفل الصفوف، معاملات قاعدة البيانات،
 * الإشعارات، إعادة الأموال عند الرفض، إلخ)، فقط إعادة تغليف الاستجابة
 * الناجحة بالغلاف الموحّد.
 *
 * ⚠️ استثناء مقصود وموثَّق: أخطاء التحقق 422 من reject()/unfreeze()
 * (تستخدمان $request->validate() مباشرة بالكنترولر القديم) تُترَك تمرّ
 * كما هي بصيغة Laravel الافتراضية للتحقق ({message, errors}) بلا تطابق
 * مع الغلاف الموحّد {success,data,message,errors} — قرار واعٍ لتفادي
 * إعادة كتابة الكنترولر القديم بالكامل فقط من أجل هاتين النقطتين.
 * الفرونت (Wallet.jsx) مبني مسبقاً ليتعامل مع كلا الشكلين.
 */
class WalletAdminController extends Controller
{
    private function legacy(): LegacyWalletAdminController
    {
        return app(LegacyWalletAdminController::class);
    }

    /** يحوّل باجيناتور Laravel الخام (data/current_page/...) لشكل items+pagination الموحّد. */
    private function wrapPaginatedLegacyResponse(\Illuminate\Http\JsonResponse $response, string $key, string $message): \Illuminate\Http\JsonResponse
    {
        $payload = $response->getData(true);
        $paginator = $payload[$key] ?? ['data' => [], 'current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0];

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $paginator['data'] ?? [],
                'pagination' => [
                    'current_page' => $paginator['current_page'] ?? 1,
                    'last_page' => $paginator['last_page'] ?? 1,
                    'per_page' => $paginator['per_page'] ?? 15,
                    'total' => $paginator['total'] ?? 0,
                ],
            ],
            'message' => $message,
        ]);
    }

    /** يحوّل استجابة JsonResponse خام (غير مُرقَّمة) للغلاف الموحّد. */
    private function wrapSingleLegacyResponse(\Illuminate\Http\JsonResponse $response): \Illuminate\Http\JsonResponse
    {
        $status = $response->getStatusCode();
        $payload = $response->getData(true);
        $message = $payload['message'] ?? '';
        unset($payload['message']);

        $data = count($payload) === 1 ? array_values($payload)[0] : (count($payload) === 0 ? null : $payload);

        return response()->json([
            'success' => $status < 400,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    public function pendingWithdrawals()
    {
        return $this->wrapPaginatedLegacyResponse(
            $this->legacy()->pendingWithdrawals(),
            'withdrawals',
            'تم جلب طلبات السحب المنتظرة بنجاح.'
        );
    }

    public function approve(Request $request, int $id)
    {
        $response = $this->wrapSingleLegacyResponse($this->legacy()->approve($id));
        \App\Models\AdminAuditLog::record($request->user(), 'wallet.approve_withdrawal', null, 'الموافقة على طلب سحب', ['transaction_id' => $id]);
        return $response;
    }

    public function reject(Request $request, int $id)
    {
        // ⚠️ عمداً بلا try/catch لأخطاء التحقق: انظر تعليق الصنف أعلاه
        $response = $this->wrapSingleLegacyResponse($this->legacy()->reject($request, $id));
        \App\Models\AdminAuditLog::record($request->user(), 'wallet.reject_withdrawal', null, 'رفض طلب سحب', ['transaction_id' => $id, 'reason' => $request->input('reason')]);
        return $response;
    }

    public function frozenWallets()
    {
        return $this->wrapPaginatedLegacyResponse(
            $this->legacy()->frozenWallets(),
            'wallets',
            'تم جلب المحافظ المجمَّدة بنجاح.'
        );
    }

    public function unfreeze(Request $request, User $user)
    {
        // ⚠️ عمداً بلا try/catch لأخطاء التحقق: انظر تعليق الصنف أعلاه
        $response = $this->wrapSingleLegacyResponse($this->legacy()->unfreeze($request, $user));
        \App\Models\AdminAuditLog::record($request->user(), 'wallet.unfreeze', $user, 'فك تجميد محفظة مستخدم');
        return $response;
    }
}
