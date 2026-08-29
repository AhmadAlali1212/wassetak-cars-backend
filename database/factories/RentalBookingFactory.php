<?php

namespace Database\Factories;

use App\Models\RentalAd;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RentalBookingFactory extends Factory
{
    protected $model = \App\Models\RentalBooking::class;

    public function definition(): array
    {
        return [
            'rental_ad_id' => RentalAd::factory(),
            'user_id' => User::factory(),
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'confirmed',
        ];
    }

    /** حجز مكتمل (تسليم + إرجاع مؤكدان) — الحالة المطلوبة لاختبار التقييمات. */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'delivered_at' => now()->subDays(2),
            'returned_at' => now(),
        ]);
    }
}
