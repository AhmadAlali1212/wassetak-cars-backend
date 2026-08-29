<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuctionFactory extends Factory
{
    protected $model = \App\Models\Auction::class;

    public function definition(): array
    {
        return [
            'car_id' => Car::factory(),
            'user_id' => User::factory(),
            'starting_price' => 1000,
            'current_price' => 1000,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
            'status' => 'active',
        ];
    }

    /** مزاد انتهى وقته لكن ما مرّ عليه الـ cron بعد (حالة "active" وهمياً). */
    public function expiredButStillActiveFlag(): static
    {
        return $this->state(fn () => [
            'start_time' => now()->subDays(2),
            'end_time' => now()->subMinutes(5),
            'status' => 'active',
        ]);
    }

    /** مزاد أُغلق وحُسمت الصفقة بالكامل (يصلح لاختبار التقييمات). */
    public function completedWithWinner(?User $winner = null): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'winner_id' => $winner?->id ?? User::factory(),
            'final_price_status' => 'completed',
            'settlement_completed_at' => now(),
            'end_time' => now()->subHour(),
        ]);
    }
}
