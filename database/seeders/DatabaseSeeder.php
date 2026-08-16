<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

                $this->call([
            AccountTypeSeeder::class,
            AdminUserSeeder::class,

            CarSeeder::class, // يعتمد على المستخدمين
            AdSeeder::class, // يعتمد على السيارات والمستخدمين
            RentalAdSeeder::class, // يعتمد على السيارات والمستخدمين
            AuctionSeeder::class, // يعتمد على السيارات والمستخدمين (للبائع والمزايد)
             PromotionSeeder::class, // لا يعتمد على أي شيء آخر بشكل مباشر
            TransactionSeeder::class, // يعتمد على المستخدمين
            RatingSeeder::class,
        ]);
    }
}
