<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'user_id',
        'amount',
        'deposit_amount',
        'is_deposit_held',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'is_deposit_held' => 'boolean',
    ];

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
