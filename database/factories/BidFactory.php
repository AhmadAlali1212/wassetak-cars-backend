<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BidFactory extends Factory
{
    protected $model = \App\Models\Bid::class;

    public function definition(): array
    {
        return [
            'auction_id' => Auction::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 1000, 5000),
            'deposit_amount' => 0,
            'is_deposit_held' => false,
        ];
    }
}
