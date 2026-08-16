<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Ad;
use App\Services\BulkUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BulkCarUploadController extends Controller
{
    protected BulkUploadService $bulkUploadService;

    public function __construct(BulkUploadService $bulkUploadService)
    {
        $this->bulkUploadService = $bulkUploadService;
    }

    /**
     * تحميل ملف Excel نموذجي فارغ للمعرض
     * GET /api/bulk-upload/template
     */
    public function downloadTemplate()
    {
        $path = $this->bulkUploadService->generateTemplate();

        return response()->download($path, 'cars_bulk_upload_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * معاينة البيانات قبل الرفع الفعلي (dry run)
     * POST /api/bulk-upload/preview
     */
    public function preview(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->accountType->name, ['company'])) {
            return response()->json([
                'message' => 'ميزة الرفع الجماعي متاحة فقط لحسابات المعارض والشركات.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->bulkUploadService->preview($request->file('file'));

        return response()->json([
            'message'       => 'تمت معاينة الملف بنجاح.',
            'total_rows'    => $result['total'],
            'valid_rows'    => $result['valid'],
            'invalid_rows'  => $result['invalid'],
            'preview_data'  => $result['rows'],   // أول 5 صفوف صالحة للمعاينة
            'errors'        => $result['errors'],  // كل الأخطاء بالأرقام
        ]);
    }

    /**
     * رفع ومعالجة ملف Excel دفعة واحدة
     * POST /api/bulk-upload
     */
    public function upload(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->accountType->name, ['company'])) {
            return response()->json([
                'message' => 'ميزة الرفع الجماعي متاحة فقط لحسابات المعارض والشركات.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file'              => 'required|file|mimes:xlsx,xls|max:10240',
            'default_latitude'  => 'required|numeric|between:-90,90',
            'default_longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->bulkUploadService->process(
            file: $request->file('file'),
            user: $user,
            defaultLat: (float) $request->default_latitude,
            defaultLng: (float) $request->default_longitude,
        );

        $statusCode = $result['failed_count'] === 0 ? 201 : 207; // 207 = Multi-Status (بعضها نجح وبعضها فشل)

        return response()->json([
            'message'       => "تم معالجة الملف: {$result['success_count']} سيارة أُضيفت بنجاح، {$result['failed_count']} صف بها أخطاء.",
            'success_count' => $result['success_count'],
            'failed_count'  => $result['failed_count'],
            'added_cars'    => $result['added_cars'],
            'errors'        => $result['errors'],
        ], $statusCode);
    }

    /**
     * عرض سجل رفعات المعرض السابقة
     * GET /api/bulk-upload/history
     */
    public function history(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->accountType->name, ['company'])) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $cars = Car::where('user_id', $user->id)
            ->whereNotNull('bulk_upload_batch')
            ->select('id', 'make', 'model', 'year', 'condition', 'approval_status', 'bulk_upload_batch', 'created_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'message' => 'سجل الرفعات الجماعية.',
            'cars'    => $cars,
        ]);
    }
}
