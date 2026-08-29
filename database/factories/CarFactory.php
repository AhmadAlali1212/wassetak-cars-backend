<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CarFactory extends Factory
{
    protected $model = \App\Models\Car::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'make' => fake()->randomElement(['Toyota', 'Hyundai', 'Kia', 'Honda']),
            'model' => fake()->word(),
            'year' => fake()->numberBetween(2005, 2024),
            'condition' => fake()->randomElement(['new', 'used']),
            'description' => fake()->sentence(),
            'location' => 'Damascus',
            'images' => ['https://example.com/car.jpg'],
            // مُوافَق عليها افتراضياً بالاختبارات لتفادي تكرار نفس السطر
            // بكل اختبار يحتاج سيارة قابلة للنشر فعلياً.
            'approval_status' => 'approved',
        ];
    }

    public function pendingApproval(): static
    {
        return $this->state(fn () => ['approval_status' => 'pending']);
    }
}
