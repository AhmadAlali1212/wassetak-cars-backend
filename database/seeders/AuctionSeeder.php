<?php

namespace Database\Seeders;

use App\Models\Auction;
use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuctionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Car::count() === 0) {
            $this->call(CarSeeder::class);
        }

        $users = User::pluck('id')->values();
        $cars = Car::skip(4)->take(6)->get();

        foreach ($cars as $index => $car) {
            $status = ['pending', 'active', 'closed', 'cancelled', 'active', 'pending'][$index];
            $sellerId = $car->user_id;
            $winnerId = null;

            if ($status === 'closed' && $users->count() > 1) {
                $winnerId = $users->first(fn ($id) => $id !== $sellerId);
            }

            $startingPrice = [6000, 14000, 9000, 32000, 52000, 7600][$index];

            Auction::create([
                'car_id' => $car->id,
                'user_id' => $sellerId,
                'starting_price' => $startingPrice,
                'current_price' => $startingPrice + [500, 1200, 2500, 0, 4500, 300][$index],
                'start_time' => now()->subDays(5 - min($index, 4)),
                'end_time' => now()->addDays(2 + $index),
                'winner_id' => $winnerId,
                'status' => $status,
            ]);
        }
    }
}
