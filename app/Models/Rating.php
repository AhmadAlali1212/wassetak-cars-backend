<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'rater_id',
        'rated_user_id',
        'rating',
        'comment',
        // ⬅️ جديد: مرجع إجباري لمعاملة فعلية — واحد منها فقط يُملأ لكل تقييم
        'ad_id',
        'auction_id',
        'rental_booking_id',
    ];

    public function rater()
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function ratedUser()
    {
        return $this->belongsTo(User::class, 'rated_user_id');
    }

    public function ad()
    {
        return $this->belongsTo(Ad::class);
    }

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function rentalBooking()
    {
        return $this->belongsTo(RentalBooking::class);
    }
}