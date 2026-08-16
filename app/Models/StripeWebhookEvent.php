<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_event_id',
        'type',
        'status',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
