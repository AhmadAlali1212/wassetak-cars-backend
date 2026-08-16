<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTopup extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount_usd',
        'exchange_rate',
        'wallet_amount',
        'currency',
        'status',
        'request_id',
        'idempotency_key',
        'stripe_payment_intent_id',
        'credited_at',
        'failed_at',
        'failure_message',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'wallet_amount' => 'decimal:2',
        'credited_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
