<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentalBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        // الإعلان الذي يخص هذا الحجز (السيارة، السعر، الشروط)
        'rental_ad_id',
        // المستخدم الذي قام بالحجز (المستأجر)
        'user_id',
        // فترة الحجز الفعلية
        'start_date',
        'end_date',
        // حالة الحجز: confirmed, completed, cancelled
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // علاقة مع إعلان الإيجار (الغلاف الثابت)
    public function rentalAd()
    {
        return $this->belongsTo(RentalAd::class);
    }

    // علاقة مع المستخدم المستأجر
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: الحجوزات "الفعّالة" فقط (غير الملغاة)، والتي لم تنتهِ
     * منطقياً بعد بحسب تاريخها (بغض النظر عن status لتفادي حالة
     * نسيان تحديث status بعد انتهاء الفترة).
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /**
     * Scope: الحجوزات التي تتقاطع زمنياً مع فترة (start, end) معينة.
     * شرط التقاطع الرياضي بين فترتين [start1,end1] و [start2,end2]:
     * start1 <= end2 AND start2 <= end1
     */
    public function scopeOverlapping($query, $start, $end)
    {
        return $query->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);
    }
}
