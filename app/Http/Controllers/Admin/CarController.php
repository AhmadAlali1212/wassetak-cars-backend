<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car; // كان هذا الاستيراد ناقصاً وهذا سبب خطأ "Class Car not found"
use App\Models\Ad;
use App\Models\RentalAd;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use App\Support\RejectionReasonHelper;

use Illuminate\Validation\Rule;

class CarController extends Controller
{
    /**
     * عند رفض سيارة (فردياً أو ضمن دفعة)، يجب سحب أي إعلان بيع أو تأجير قائم عليها
     * تلقائياً (draft/pending/active → rejected)، وإلا يبقى الإعلان ظاهراً ومنشوراً
     * للمستخدمين رغم أن السيارة نفسها رُفضت رسمياً من الإدارة — وهذا كان بالضبط
     * الثغرة: لا شيء بالكود سابقاً كان يربط حالة الإعلان بحالة السيارة بعد الرفض.
     */
    private function cascadeRejectListingsForCar(Car $car, string $reason): void
    {
        $ad = Ad::where('car_id', $car->id)
            ->whereIn('status', ['draft', 'pending', 'active'])
            ->first();

        if ($ad) {
            $ad->update([
                'status' => 'rejected',
                'rejection_reason' => 'تم سحب الإعلان تلقائياً لأن السيارة رُفضت من الإدارة: ' . $reason,
            ]);

            NotificationService::send(
                $ad->user_id,
                'تم إلغاء إعلان البيع الخاص بك',
                'تم رفض السيارة المرتبطة بإعلان البيع من قبل الإدارة، وبالتالي تم إلغاء الإعلان تلقائياً.',
                'ad_rejected_due_to_car_rejection',
                ['ad_id' => $ad->id, 'car_id' => $car->id]
            );
        }

        $rentalAd = RentalAd::where('car_id', $car->id)
            ->whereIn('status', ['pending', 'active'])
            ->first();

        if ($rentalAd) {
            $rentalAd->update([
                'status' => 'rejected',
                'rejection_reason' => 'تم سحب إعلان التأجير تلقائياً لأن السيارة رُفضت من الإدارة: ' . $reason,
            ]);

            NotificationService::send(
                $rentalAd->user_id,
                'تم إلغاء إعلان التأجير الخاص بك',
                'تم رفض السيارة المرتبطة بإعلان التأجير من قبل الإدارة، وبالتالي تم إلغاء الإعلان تلقائياً.',
                'rental_ad_rejected_due_to_car_rejection',
                ['rental_ad_id' => $rentalAd->id, 'car_id' => $car->id]
            );
        }
    }

    /**
     * عرض جميع السيارات المنتظرة الموافقة
     */
    public function index()
    {
        $cars = Car::where('approval_status', 'pending')
                   ->with('user')
                   ->paginate(10);

        return response()->json([
            'message' => 'قائمة السيارات المنتظرة الموافقة',
            'cars' => $cars,
        ]);
    }

    /**
     * عرض جميع السيارات المرفوضة
     */
    public function rejected()
    {
        $cars = Car::where('approval_status', 'rejected')
                   ->with('user')
                   ->paginate(10);

        return response()->json([
            'message' => 'قائمة السيارات المرفوضة',
            'cars' => $cars,
        ]);
    }

    /**
     * عرض جميع السيارات المقبولة
     */
    public function approved()
    {
        $cars = Car::where('approval_status', 'approved')
                   ->with('user')
                   ->paginate(10);

        return response()->json([
            'message' => 'قائمة السيارات المقبولة',
            'cars' => $cars,
        ]);
    }

    /**
     * عرض تفاصيل سيارة محددة
     */
    public function show($id)
    {
        $car = Car::with('user', 'ad')->find($id);

        if (!$car) {
            return response()->json(['message' => 'السيارة غير موجودة.'], 404);
        }

        return response()->json([
            'message' => 'تفاصيل السيارة',
            'car' => $car,
        ]);
    }

    /**
     * موافقة الأدمن على سيارة
     */
    public function approve(Request $request, $id)
    {
        $car = Car::find($id);

        if (!$car) {
            return response()->json(['message' => 'السيارة غير موجودة.'], 404);
        }

        if ($car->approval_status === 'approved') {
            return response()->json(['message' => 'السيارة موافق عليها بالفعل.'], 400);
        }

        $car->update([
            'approval_status' => 'approved',
            'approval_reason' => $request->reason ?? null,
            'approved_at' => now(),
        ]);

        // إرسال إشعار للمستخدم
        NotificationService::send(
            $car->user_id,
            'تم قبول السيارة',
            'تمت الموافقة على السيارة ' . $car->make . ' ' . $car->model . ' بنجاح.',
            'car_approved',
            ['car_id' => $car->id]
        );

        return response()->json([
            'message' => 'تم الموافقة على السيارة بنجاح.',
            'car' => $car,
        ]);
    }

    /**
     * رفض الأدمن لسيارة
     */
    /**
     * إرجاع قائمة أسباب رفض السيارة الجاهزة (لعرضها كقائمة اختيار في لوحة الأدمن).
     */
    public function rejectionReasons()
    {
        return response()->json([
            'reasons' => RejectionReasonHelper::list('car'),
        ]);
    }

    public function reject(Request $request, $id)
    {
        $reason = RejectionReasonHelper::validateAndResolve($request, 'car');

        $car = Car::find($id);

        if (!$car) {
            return response()->json(['message' => 'السيارة غير موجودة.'], 404);
        }

        if ($car->approval_status === 'rejected') {
            return response()->json(['message' => 'السيارة مرفوضة بالفعل.'], 400);
        }

        $car->update([
            'approval_status' => 'rejected',
            'approval_reason' => $reason,
            'approved_at' => null,
        ]);

        // سحب أي إعلان بيع/تأجير قائم على هذه السيارة تلقائياً
        $this->cascadeRejectListingsForCar($car, $reason);

        // إرسال إشعار للمستخدم
        NotificationService::send(
            $car->user_id,
            'تم رفض السيارة',
            'تم رفض السيارة ' . $car->make . ' ' . $car->model . ' للسبب: ' . $reason,
            'car_rejected',
            ['car_id' => $car->id]
        );

        return response()->json([
            'message' => 'تم رفض السيارة بنجاح.',
            'car' => $car,
        ]);
    }

    public function approveInspection(Request $request, $id)
    {
        // التحقق من إرفاق ملف التقرير الفني وحجمه وامتداده
        $request->validate([
            'inspection_report' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // كحد أقصى 5 ميغا
        ]);

        $car = Car::findOrFail($id);

        if ($request->hasFile('inspection_report')) {
            // تخزين الملف في مجلد السيرفر (storage/app/public/reports)
            $filePath = $request->file('inspection_report')->store('reports', 'public');

            // تحديث حالة السيارة وتفعيل الشارة وتخزين الوقت
            $car->update([
                'is_inspected' => true,
                'inspection_report_path' => $filePath,
                'inspected_at' => now(),
            ]);

            // إرسال إشعار للمستخدم يعلمه بنجاح الفحص وتوثيق السيارة
            NotificationService::send(
                $car->user_id,
                'تهانينا! سيارتك أصبحت "مفحوصة ومضمونة" ✨',
                "تم اعتماد تقرير الفحص الفني لسيارتك ({$car->make} {$car->model}) وتفعيل الشارة لها في السوق بنجاح.",
                'car_inspection_approved',
                ['car_id' => $car->id, 'report_path' => asset('storage/' . $filePath)]
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تفعيل شارة الفحص ورفع التقرير بنجاح، وإشعار صاحب السيارة إن شاء الله.'
            ]);
        }

        return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء رفع الملف.'], 400);
    }

    /**
     * عرض دفعات الرفع الجماعي (Excel) التي لا تزال تحتوي على سيارات منتظرة الموافقة،
     * مجمّعة حسب رقم الدفعة، لتسهيل موافقة/رفض الدفعة كاملة بدل كل سيارة لحالها.
     */
    public function pendingBulkBatches()
    {
        $batches = Car::whereNotNull('bulk_upload_batch')
            ->where('approval_status', 'pending')
            ->selectRaw('bulk_upload_batch, user_id, COUNT(*) as cars_count, MIN(created_at) as uploaded_at')
            ->groupBy('bulk_upload_batch', 'user_id')
            ->orderByDesc('uploaded_at')
            ->get()
            ->map(function ($row) {
                $user = \App\Models\User::find($row->user_id);
                return [
                    'batch_id' => $row->bulk_upload_batch,
                    'user_id' => $row->user_id,
                    'user_name' => $user->name ?? null,
                    'pending_cars_count' => $row->cars_count,
                    'uploaded_at' => $row->uploaded_at,
                ];
            });

        return response()->json([
            'message' => 'دفعات الرفع الجماعي المنتظرة الموافقة.',
            'batches' => $batches,
        ]);
    }

    /**
     * عرض كل سيارات دفعة رفع جماعي محددة (بغض النظر عن حالتها).
     */
    public function showBulkBatch($batchId)
    {
        $cars = Car::where('bulk_upload_batch', $batchId)->with('user')->get();

        if ($cars->isEmpty()) {
            return response()->json(['message' => 'لا توجد سيارات بهذه الدفعة، أو رقم الدفعة غير صحيح.'], 404);
        }

        return response()->json([
            'message' => 'تفاصيل دفعة الرفع الجماعي.',
            'batch_id' => $batchId,
            'cars' => $cars,
        ]);
    }

    /**
     * الموافقة على كل السيارات المنتظرة (pending) ضمن دفعة رفع جماعي واحدة دفعة واحدة.
     */
    public function approveBulkBatch($batchId)
    {
        $cars = Car::where('bulk_upload_batch', $batchId)
            ->where('approval_status', 'pending')
            ->get();

        if ($cars->isEmpty()) {
            return response()->json(['message' => 'لا توجد سيارات منتظرة الموافقة بهذه الدفعة، أو رقم الدفعة غير صحيح.'], 404);
        }

        $ownerId = $cars->first()->user_id;

        foreach ($cars as $car) {
            $car->update([
                'approval_status' => 'approved',
                'approval_reason' => null,
                'approved_at' => now(),
            ]);
        }

        // إشعار واحد مجمّع لصاحب المعرض بدل إشعار منفصل لكل سيارة
        NotificationService::send(
            $ownerId,
            'تمت الموافقة على دفعة سياراتك بالكامل ✅',
            "تمت موافقة الإدارة على {$cars->count()} سيارة من دفعة الرفع الجماعي رقم {$batchId}.",
            'bulk_batch_approved',
            ['batch_id' => $batchId, 'approved_count' => $cars->count()]
        );

        return response()->json([
            'message' => "تمت الموافقة على {$cars->count()} سيارة ضمن الدفعة بنجاح.",
            'approved_count' => $cars->count(),
            'batch_id' => $batchId,
        ]);
    }

    /**
     * رفض كل السيارات المنتظرة (pending) ضمن دفعة رفع جماعي واحدة دفعة واحدة، بسبب واحد موحّد من القائمة الجاهزة.
     */
    public function rejectBulkBatch(Request $request, $batchId)
    {
        $reason = RejectionReasonHelper::validateAndResolve($request, 'car');

        $cars = Car::where('bulk_upload_batch', $batchId)
            ->where('approval_status', 'pending')
            ->get();

        if ($cars->isEmpty()) {
            return response()->json(['message' => 'لا توجد سيارات منتظرة الموافقة بهذه الدفعة، أو رقم الدفعة غير صحيح.'], 404);
        }

        $ownerId = $cars->first()->user_id;

        foreach ($cars as $car) {
            $car->update([
                'approval_status' => 'rejected',
                'approval_reason' => $reason,
                'approved_at' => null,
            ]);

            // سحب أي إعلان بيع/تأجير قائم على كل سيارة بالدفعة تلقائياً
            $this->cascadeRejectListingsForCar($car, $reason);
        }

        NotificationService::send(
            $ownerId,
            'تم رفض دفعة سياراتك بالكامل ❌',
            "تم رفض {$cars->count()} سيارة من دفعة الرفع الجماعي رقم {$batchId}. السبب: {$reason}",
            'bulk_batch_rejected',
            ['batch_id' => $batchId, 'rejected_count' => $cars->count(), 'reason' => $reason]
        );

        return response()->json([
            'message' => "تم رفض {$cars->count()} سيارة ضمن الدفعة.",
            'rejected_count' => $cars->count(),
            'batch_id' => $batchId,
            'reason' => $reason,
        ]);
    }
}
