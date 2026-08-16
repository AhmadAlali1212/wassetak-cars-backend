<?php

namespace App\Services;

use App\Models\Car;
use App\Models\RentalBooking;
use Illuminate\Support\Collection;

class VehicleFraudDetectionService
{
    /**
     * فحص مخصص لاحتيال "التأجير المزدوج": نفس السيارة الفعلية
     * (نفس vehicle_identifier) محجوزة بفترتين متقاطعتين زمنياً من
     * مستخدمين مختلفين.
     *
     * سبب وجود هذه الدالة منفصلة عن أي فحص عام لتكرار السيارة:
     * - الإيجار يختلف جوهرياً عن البيع: تكرار ظهور السيارة بحجوزات
     *   إيجار متعددة عبر الزمن أمر طبيعي تماماً وليس مؤشر احتيال
     *   (نفس السيارة تتأجر وترجع وتتأجر من جديد بشكل شرعي).
     * - المؤشر الحقيقي هنا هو تقاطع الفترات الزمنية الفعلية: نفس
     *   السيارة محجوزة من شخصين مختلفين بحيث تتداخل فترتاهما، وهذا
     *   مستحيل فعلياً ومؤشر على تأجير سيارة غير مملوكة أو ازدواج حجز.
     *
     * يُستخدم هذا الفحص بشكل أساسي عند تأكيد حجز جديد (قبل إنشائه)،
     * لمعرفة هل الفترة المطلوبة تتقاطع مع حجز قائم على نفس السيارة
     * الفعلية، حتى لو كان ذلك الحجز على إعلان (RentalAd) مختلف.
     *
     * @param Car $car السيارة المرتبطة بالحجز الجديد
     * @param string $startDate تاريخ بداية الفترة المطلوب فحصها (Y-m-d)
     * @param string $endDate تاريخ نهاية الفترة المطلوب فحصها (Y-m-d)
     * @param int|null $excludeBookingId استثناء حجز معيّن من الفحص
     *        (مفيد عند تعديل حجز موجود بدل إنشاء حجز جديد)
     * @return array{
     *   has_double_rental_conflict: bool,
     *   overlapping_count: int,
     *   overlapping_bookings: Collection,
     * }
     */
    public function analyzeRentalOverlap(Car $car, string $startDate, string $endDate, ?int $excludeBookingId = null): array
    {
        $identifier = $car->vehicle_identifier;

        if (empty($identifier)) {
            return [
                'has_double_rental_conflict' => false,
                'overlapping_count' => 0,
                'overlapping_bookings' => collect(),
            ];
        }

        // كل الحجوزات الفعّالة (غير الملغاة) التي تتقاطع زمنياً مع
        // الفترة المطلوبة، على أي سيارة تحمل نفس رقم الهيكل - بغض
        // النظر عن سجل Car أو إعلان RentalAd المحدد (لأن المحتال قد
        // يكون أنشأ سجل Car أو RentalAd منفصلاً بنفس رقم الهيكل)
        $overlappingBookings = RentalBooking::query()
            ->active()
            ->overlapping($startDate, $endDate)
            ->whereHas('rentalAd.car', function ($query) use ($identifier) {
                $query->where('vehicle_identifier', $identifier);
            })
            ->when($excludeBookingId, fn ($query) => $query->where('id', '!=', $excludeBookingId))
            ->with(['rentalAd.car', 'user'])
            ->get();

        $overlappingCount = $overlappingBookings->count();

        // تضارب حقيقي فقط لو كانت الحجوزات المتقاطعة من مستخدمين
        // مختلفين (لو كانت كلها لنفس المستخدم، قد تكون مجرد تجديد
        // أو تمديد شرعي من المالك نفسه)
        $distinctUsers = $overlappingBookings->pluck('user_id')->unique()->count();
        $hasConflict = $overlappingCount >= 1 && $distinctUsers >= 1;

        return [
            'has_double_rental_conflict' => $hasConflict,
            'overlapping_count' => $overlappingCount,
            'overlapping_bookings' => $overlappingBookings,
        ];
    }
}
