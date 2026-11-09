<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminType = AccountType::where('name', 'admin')->first();
        $individualType = AccountType::where('name', 'individual')->first();
        $companyType = AccountType::where('name', 'company')->first();

        $users = [
            [
                'name' => 'مدير النظام',
                'email' => 'admin@example.com',
                'phone_number' => '0991000001',
                'account_type_id' => $adminType?->id,
                'is_verified' => true,
                'is_subscribed' => false,
                'subscription_ends_at' => null,
            ],
            [
                'name' => 'أحمد الخطيب',
                'email' => 'ahmad@example.com',
                'phone_number' => '0991000002',
                'account_type_id' => $individualType?->id,
                'is_verified' => true,
                'is_subscribed' => true,
                'subscription_ends_at' => now()->addMonths(3),
            ],
            [
                'name' => 'سارة الحسن',
                'email' => 'sara@example.com',
                'phone_number' => '0991000003',
                'account_type_id' => $individualType?->id,
                'is_verified' => true,
                'is_subscribed' => true,
                'subscription_ends_at' => now()->addMonths(2),
            ],
            [
                'name' => 'معرض الشام للسيارات',
                'email' => 'sham.cars@example.com',
                'phone_number' => '0991000004',
                'account_type_id' => $companyType?->id,
                'is_verified' => true,
                'is_subscribed' => true,
                'subscription_ends_at' => now()->addYear(),
            ],
            [
                'name' => 'شركة بردى أوتو',
                'email' => 'barada.auto@example.com',
                'phone_number' => '0991000005',
                'account_type_id' => $companyType?->id,
                'is_verified' => true,
                'is_subscribed' => true,
                'subscription_ends_at' => now()->addMonths(8),
            ],
        ];

        foreach ($users as $data) {
              $user = User::updateOrCreate(
              ['email' => $data['email']],
                  array_merge($data, [
                 'password' => Hash::make('password'),
                 'verification_documents' => null,
                 'email_verified_at' => now(),
               ])
           );

    if (!$user->wallet()->exists()) {
        $user->wallet()->create(['balance' => 0]);
              }
           }
    }
}
