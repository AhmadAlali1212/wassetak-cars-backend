<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Car;
use Illuminate\Database\Seeder;

class AdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Car::count() === 0) {
            $this->call(CarSeeder::class);
        }

        $statuses = ['active', 'pending', 'sold', 'rejected'];

        foreach (Car::take(8)->get() as $index => $car) {
            Ad::create([
                'car_id' => $car->id,
                'user_id' => $car->user_id,
                'price' => [8500, 18500, 12200, 43000, 68000, 7200, 9100, 35500][$index],
                'status' => $statuses[$index % count($statuses)],
                'published_at' => now()->subDays(20 - $index),
                'expires_at' => $statuses[$index % count($statuses)] === 'sold' ? null : now()->addDays(45 + $index),
            ]);
        }
    }
}
