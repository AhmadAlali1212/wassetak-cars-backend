<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdFactory extends Factory
{
    protected $model = \App\Models\Ad::class;

    public function definition(): array
    {
        return [
            'car_id' => Car::factory(),
            'user_id' => User::factory(),
            'price' => fake()->randomFloat(2, 1000, 30000),
            'status' => 'active',
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }

    /** إعلان "مباع" فعلياً مع مشترٍ محدد — الحالة المطلوبة لاختبار التقييمات. */
    public function sold(?User $buyer = null): static
    {
        return $this->state(fn () => [
            'status' => 'sold',
            'buyer_id' => $buyer?->id ?? User::factory(),
            'sold_at' => now(),
        ]);
    }
}
