<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'held_balance',
        'is_frozen',
        'freeze_reason',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'held_balance' => 'decimal:2',
        'is_frozen' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
