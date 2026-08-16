<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_id',
        'user_id',
        'starting_price',
        'current_price',
        'start_time',
        'end_time',
        'winner_id',
        'status',
        'final_price_status',
        'final_price_decided_at',
        'settlement_completed_at',
        'settlement_deadline_at',
        'winner_withdrew_at',
        'closed_at',
        'closed_reason',
    ];
    protected $casts = [
        'starting_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'final_price_decided_at' => 'datetime',
        'settlement_completed_at' => 'datetime',
        'settlement_deadline_at' => 'datetime',
        'winner_withdrew_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // علاقة مع السيارة
    public function car()
    {
        // علاقة واحد لواحد مع السيارة
        return $this->belongsTo(Car::class);
    }

    // علاقة مع المستخدم (البائع)
    public function user()
    {
        // علاقة واحد لواحد مع المستخدم
        return $this->belongsTo(User::class);
    }

    // علاقة مع الفائز بالمزاد
    public function winner()
    {
        // علاقة واحد لواحد مع المستخدم
        return $this->belongsTo(User::class, 'winner_id');
    }

    // علاقة مع المزايدات
    public function bids()
    {
        // علاقة متعددة مع المزايدات
        return $this->hasMany(Bid::class);
    }

    // علاقة متعددة الأشكال مع المعاملات (Polymorphic relationship with Transactions)
    public function transactions()
    {
        // علاقة متعددة الأشكال مع المعاملات
        return $this->morphMany(Transaction::class, 'transactionable');
    }
}
