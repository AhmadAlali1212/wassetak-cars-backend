<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentalAd extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_id',
        'user_id',
        'daily_price',
        'monthly_price',
        'rental_conditions',
        'location',
        'status', // 'pending', 'active', 'rejected'
        'rejection_reason',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    // علاقة مع كل الحجوزات الفعلية (الفترات الزمنية) لهذا الإعلان
    public function bookings()
    {
        return $this->hasMany(RentalBooking::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
