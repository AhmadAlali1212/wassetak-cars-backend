<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\CarController as LegacyCarController;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Support\RejectionReasonHelper;
use Illuminate\Http\Request;

/**
 * وحدة CarsApproval من Admin API — آخر وحدة متبقية من خطة Admin API
 * عدا Wallet. بخلاف كل الوحدات السابقة، نقاط تغيير الحالة
 * (approve/reject/approveInspection/bulk-batches approve/reject) لم
 * تُعَد كتابتها من الصفر، بل بُنيت بنمط "delegation": تستدعي دوال
 * Admin\CarController القديمة مباشرة ثم تعيد تغليف استجابتها الخام
 * بالشكل الموحّد الجديد، بلا أي تكرار لمنطق الأعمال (validation، cascade
 * rejection، الإشعارات، إلخ).
 *
 * نقاط القراءة السبع أُعيد كتابتها من الصفر بـ per_page=20 والغلاف
 * الموحَّد المعتاد (خلافاً للكنترولر القديم الذي يستخدم paginate(10)).
 *
 * بادئة المسارات car-approvals (بدل cars) لتفادي أي تعارض مع الكتلة
 * القديمة admin/cars/* (تبقى تعمل بلا أي تعديل).
 *
 * ⚠️ ملاحظات موروثة من الكود القديم (بلا إصلاح، للعلم فقط): لا يوجد
 * DB::transaction بـ reject()/cascadeRejectListingsForCar()/دوال bulk؛
 * approve() JSON يقبل approval_reason اختيارياً بينما نسخة الويب تفرضها
 * null دائماً؛ دوال bulk تُرسل إشعاراً واحداً فقط لأول مستخدم بالمجموعة.
 */
class CarController extends Controller
{
    private const PER_PAGE = 20;

    /** يحوّل استجابة JsonResponse خام من الكنترولر القديم للغلاف الموحّد الجديد. */
    private function wrapLegacyResponse(\Illuminate\Http\JsonResponse $response): \Illuminate\Http\JsonResponse
    {
        $status = $response->getStatusCode();
        $payload = $response->getData(true);
        $message = $payload['message'] ?? '';
        unset($payload['message']);

        // success مشتق من نجاح الاستجابة الأصلية نفسها (كود < 400)، أو من
        // حقل success الصريح إن وُجد أصلاً بالاستجابة القديمة (approveInspection)
        $success = array_key_exists('success', $payload) ? (bool) $payload['success'] : $status < 400;
        unset($payload['success']);

        // إن لم يتبقَّ إلا مفتاح واحد بالبيانات، أعده مباشرة بدل تغليفه بمصفوفة إضافية
        $data = count($payload) === 1 ? array_values($payload)[0] : (count($payload) === 0 ? null : $payload);

        return response()->json([
            'success' => $success,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    public function pending()
    {
        $paginator = Car::where('approval_status', 'pending')->with('user')->paginate(self::PER_PAGE);

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
            'message' => 'قائمة السيارات المنتظرة الموافقة.',
        ]);
    }

    public function approved()
    {
        $paginator = Car::where('approval_status', 'approved')->with('user')->paginate(self::PER_PAGE);

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
            'message' => 'قائمة السيارات المقبولة.',
        ]);
    }

    public function rejected()
    {
        $paginator = Car::where('approval_status', 'rejected')->with('user')->paginate(self::PER_PAGE);

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
            'message' => 'قائمة السيارات المرفوضة.',
        ]);
    }

    public function show($id)
    {
        $car = Car::with('user', 'ad')->find($id);

        if (!$car) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'السيارة غير موجودة.'], 404);
        }

        return response()->json(['success' => true, 'data' => $car, 'message' => 'تفاصيل السيارة.']);
    }

    public function rejectionReasons()
    {
        return response()->json([
            'success' => true,
            'data' => RejectionReasonHelper::list('car'),
            'message' => 'تم جلب أسباب الرفض بنجاح.',
        ]);
    }

    public function pendingBulkBatches()
    {
        return $this->wrapLegacyResponse(app(LegacyCarController::class)->pendingBulkBatches());
    }

    public function showBulkBatch($batchId)
    {
        return $this->wrapLegacyResponse(app(LegacyCarController::class)->showBulkBatch($batchId));
    }

    // ==================== نقاط التغيير — delegation كامل للكنترولر القديم ====================

    public function approve(Request $request, $id)
    {
        $response = $this->wrapLegacyResponse(app(LegacyCarController::class)->approve($request, $id));
        \App\Models\AdminAuditLog::record($request->user(), 'car.approve', null, 'قبول سيارة', ['car_id' => $id]);
        return $response;
    }

    public function reject(Request $request, $id)
    {
        $response = $this->wrapLegacyResponse(app(LegacyCarController::class)->reject($request, $id));
        \App\Models\AdminAuditLog::record($request->user(), 'car.reject', null, 'رفض سيارة', ['car_id' => $id, 'reason' => $request->input('reason')]);
        return $response;
    }

    public function approveInspection(Request $request, $id)
    {
        $response = $this->wrapLegacyResponse(app(LegacyCarController::class)->approveInspection($request, $id));
        \App\Models\AdminAuditLog::record($request->user(), 'car.approve_inspection', null, 'قبول الفحص الفني لسيارة', ['car_id' => $id]);
        return $response;
    }

    public function approveBulkBatch(Request $request, $batchId)
    {
        $response = $this->wrapLegacyResponse(app(LegacyCarController::class)->approveBulkBatch($batchId));
        \App\Models\AdminAuditLog::record($request->user(), 'car.approve_bulk_batch', null, 'قبول دفعة رفع جماعي', ['batch_id' => $batchId]);
        return $response;
    }

    public function rejectBulkBatch(Request $request, $batchId)
    {
        $response = $this->wrapLegacyResponse(app(LegacyCarController::class)->rejectBulkBatch($request, $batchId));
        \App\Models\AdminAuditLog::record($request->user(), 'car.reject_bulk_batch', null, 'رفض دفعة رفع جماعي', ['batch_id' => $batchId]);
        return $response;
    }
}
