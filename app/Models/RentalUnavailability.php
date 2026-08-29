<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentalUnavailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_ad_id',
        'created_by',
        'start_date',
        'end_date',
        'reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function rentalAd()
    {
        return $this->belongsTo(RentalAd::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: الفترات التي تتقاطع زمنياً مع فترة (start, end) معينة.
     */
    public function scopeOverlapping($query, $start, $end)
    {
        return $query->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);
    }
}
