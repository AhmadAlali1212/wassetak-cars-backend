<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class BulkUploadService
{
    // الحقول المطلوبة بالترتيب الصحيح (بدون فجوات)
    private const COLUMNS = [
        'A' => 'make',
        'B' => 'model',
        'C' => 'year',
        'D' => 'condition',
        'E' => 'description',
        'F' => 'latitude',
        'G' => 'longitude',
    ];

    private const REQUIRED_FIELDS = ['make', 'model', 'year', 'condition'];

    // -------------------------------------------------------------------
    // توليد ملف Excel نموذجي للتحميل
    // -------------------------------------------------------------------
    public function generateTemplate(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('رفع السيارات');

        // الرؤوس متسلسلة من A إلى G
        $headers = [
            'A1' => 'الشركة المصنعة (make) *',
            'B1' => 'الموديل (model) *',
            'C1' => 'سنة الصنع (year) *',
            'D1' => 'الحالة: new أو used *',
            'E1' => 'الوصف (اختياري)',
            'F1' => 'خط العرض (latitude) اختياري',
            'G1' => 'خط الطول (longitude) اختياري',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // تنسيق رأس الجدول
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a56db']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

        // صفوف أمثلة
        $examples = [
            ['Toyota', 'Camry', 2022, 'used',  'سيارة نظيفة بدون حوادث', 24.7136, 46.6753],
            ['Kia',    'Sportage', 2023, 'new', 'وكالة بالكامل', 21.3891, 39.8579],
            ['Hyundai','Tucson', 2021, 'used',  '', 30.0444, 31.2357],
        ];

        foreach ($examples as $i => $row) {
            $rowNum = $i + 2;
            $sheet->fromArray($row, null, "A{$rowNum}");
            $bgColor = ($i % 2 === 0) ? 'f3f4f6' : 'ffffff';
            $sheet->getStyle("A{$rowNum}:G{$rowNum}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($bgColor);
        }

        // ضبط عرض الأعمدة تلقائياً
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // حفظ الملف مؤقتاً
        $path = storage_path('app/tmp/bulk_template_' . Str::random(8) . '.xlsx');
        @mkdir(dirname($path), 0777, true);

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }

    // -------------------------------------------------------------------
    // معاينة الملف (dry run) - دون حفظ أي بيانات
    // -------------------------------------------------------------------
    public function preview(UploadedFile $file): array
    {
        $rows   = $this->readExcel($file);
        $valid  = 0;
        $invalid = 0;
        $errors  = [];
        $previewRows = [];

        foreach ($rows as $index => $row) {
            $rowNum   = $index + 2; // الصف في Excel يبدأ من 2 (الأول رؤوس)
            $validated = $this->validateRow($row, $rowNum);

            if (empty($validated['errors'])) {
                $valid++;
                if (count($previewRows) < 5) {
                    $previewRows[] = array_merge($row, ['row' => $rowNum, 'status' => 'valid']);
                }
            } else {
                $invalid++;
                $errors[] = ['row' => $rowNum, 'errors' => $validated['errors']];
            }
        }

        return [
            'total'   => count($rows),
            'valid'   => $valid,
            'invalid' => $invalid,
            'rows'    => $previewRows,
            'errors'  => $errors,
        ];
    }

    // -------------------------------------------------------------------
    // المعالجة الفعلية وحفظ السيارات في قاعدة البيانات
    // -------------------------------------------------------------------
    public function process(UploadedFile $file, User $user, float $defaultLat, float $defaultLng): array
    {
        $rows        = $this->readExcel($file);
        $batchId     = 'batch_' . now()->format('Ymd_His') . '_' . Str::random(6);
        $addedCars   = [];
        $errors      = [];
        $successCount = 0;
        $failedCount  = 0;

        // تحديد الموقع الافتراضي من الإحداثيات مرة واحدة فقط (توفيراً لاستدعاءات API)
        $defaultLocation = $this->reverseGeocode($defaultLat, $defaultLng);

        foreach ($rows as $index => $row) {
            $rowNum    = $index + 2;
            $validated = $this->validateRow($row, $rowNum);

            if (!empty($validated['errors'])) {
                $failedCount++;
                $errors[] = ['row' => $rowNum, 'errors' => $validated['errors']];
                continue;
            }

            // الإحداثيات: استخدم ما في الصف إن وُجد، وإلا استخدم الافتراضي
            $lat      = !empty($row['latitude'])  ? (float) $row['latitude']  : $defaultLat;
            $lng      = !empty($row['longitude']) ? (float) $row['longitude'] : $defaultLng;
            $location = ($lat === $defaultLat && $lng === $defaultLng)
                ? $defaultLocation
                : $this->reverseGeocode($lat, $lng);

            try {
                // إنشاء سجل السيارة
                $car = Car::create([
                    'user_id'            => $user->id,
                    'make'               => trim($row['make']),
                    'model'              => trim($row['model']),
                    'year'               => (int) $row['year'],
                    'condition'          => strtolower(trim($row['condition'])),
                    'description'        => $row['description'] ?? null,
                    'location'           => $location,
                    'latitude'           => $lat,
                    'longitude'          => $lng,
                    'images'             => [],
                    'bulk_upload_batch'  => $batchId,
                ]);

                // إنشاء الإعلان كمسودة (Draft) وبسعر صفر
                // ملاحظة: الحالة draft (وليست pending) لأن الإعلان لسا غير جاهز
                // لمراجعة الإدارة أصلاً — السعر لسا 0 والسيارة نفسها لسا pending.
                // بيتحول تلقائياً لـ pending فقط بعد ما يحدد المعرض السعر الفعلي
                // عبر PUT /ads/bulk-publish (شوف AdController::bulkPublishPrices).
                $ad = Ad::create([
                    'car_id'  => $car->id,
                    'user_id' => $user->id,
                    'price'   => 0,
                    'status'  => 'draft',
                ]);

                $addedCars[] = [
                    'row'                => $rowNum,
                    'car_id'             => $car->id,
                    'ad_id'              => $ad->id, // إرجاع رقم الإعلان للتسعير لاحقاً
                    'make'               => $car->make,
                    'model'              => $car->model,
                    'year'               => $car->year,
                    'location'           => $location,
                ];

                $successCount++;

            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = [
                    'row'    => $rowNum,
                    'errors' => ['server' => 'خطأ أثناء الحفظ: ' . $e->getMessage()],
                ];
            }
        }

        return [
            'batch_id'      => $batchId,
            'success_count' => $successCount,
            'failed_count'  => $failedCount,
            'added_cars'    => $addedCars,
            'errors'        => $errors,
        ];
    }

    // -------------------------------------------------------------------
    // قراءة Excel وتحويل الصفوف إلى مصفوفة مترابطة
    // -------------------------------------------------------------------
    private function readExcel(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = [];

        $highestRow = $sheet->getHighestDataRow();

        for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
            $row = [];
            foreach (self::COLUMNS as $col => $field) {
                $row[$field] = $sheet->getCell("{$col}{$rowNum}")->getValue();
            }

            // تجاهل الصفوف الفارغة تماماً
            if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    // -------------------------------------------------------------------
    // التحقق من صحة صف واحد
    // -------------------------------------------------------------------
    private function validateRow(array $row, int $rowNum): array
    {
        $errors = [];

        $rules = [
            'make'               => 'required|string|max:255',
            'model'              => 'required|string|max:255',
            'year'               => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'condition'          => 'required|in:new,used',
            'description'        => 'nullable|string|max:2000',
            'latitude'           => 'nullable|numeric|between:-90,90',
            'longitude'          => 'nullable|numeric|between:-180,180',
        ];

        $messages = [
            'make.required'               => 'حقل الشركة المصنعة مطلوب',
            'model.required'              => 'حقل الموديل مطلوب',
            'year.required'               => 'حقل سنة الصنع مطلوب',
            'year.integer'                => 'سنة الصنع يجب أن تكون رقماً صحيحاً',
            'year.min'                    => 'سنة الصنع لا يمكن أن تكون قبل 1900',
            'year.max'                    => 'سنة الصنع تتجاوز العام القادم',
            'condition.required'          => 'حقل الحالة مطلوب',
            'condition.in'                => 'الحالة يجب أن تكون "new" أو "used"',
        ];

        $validator = Validator::make($row, $rules, $messages);

        if ($validator->fails()) {
            foreach ($validator->errors()->toArray() as $field => $msgs) {
                $errors[$field] = $msgs[0];
            }
        }

        return ['errors' => $errors];
    }

    // -------------------------------------------------------------------
    // تحويل إحداثيات جغرافية إلى اسم موقع نصي
    // -------------------------------------------------------------------
    private function reverseGeocode(float $lat, float $lng): string
    {
        try {
            $apiKey   = config('services.locationiq.key', 'pk.2ebc5f372c19bce2742a82d76c2c8fc7');
            $response = Http::timeout(5)->get('https://us1.locationiq.com/v1/reverse', [
                'key'             => $apiKey,
                'lat'             => $lat,
                'lon'             => $lng,
                'format'          => 'json',
                'accept-language' => 'ar',
            ]);

            if ($response->successful()) {
                $address = $response->json()['address'] ?? [];
                $city    = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['state'] ?? '';
                $country = $address['country'] ?? '';

                if ($city && $country) {
                    return "{$city}، {$country}";
                }
                return $response->json()['display_name'] ?? 'موقع غير معروف';
            }
        } catch (\Exception $e) {
            // fallback صامت
        }

        return 'موقع غير معروف';
    }
}

