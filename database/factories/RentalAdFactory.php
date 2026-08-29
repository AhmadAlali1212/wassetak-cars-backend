<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RentalAdFactory extends Factory
{
    protected $model = \App\Models\RentalAd::class;

    public function definition(): array
    {
        return [
            'car_id' => Car::factory(),
            'user_id' => User::factory(),
            'daily_price' => fake()->randomFloat(2, 20, 200),
            'location' => 'Damascus',
            'status' => 'active',
            'published_at' => now(),
        ];
    }
}
