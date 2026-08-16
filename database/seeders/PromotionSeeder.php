<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\Promotion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $companyType = AccountType::firstOrCreate(
            ['name' => 'company'],
            ['description' => 'حساب شركة أو معرض لإدارة إعلانات السيارات والعروض']
        );

        $companyOne = User::firstOrCreate(
            ['email' => 'sham.cars@example.com'],
            [
                'name' => 'معرض الشام للسيارات',
                'phone_number' => '0991000004',
                'password' => Hash::make('password'),
                'account_type_id' => $companyType->id,
                'is_verified' => true,
                'is_subscribed' => true,
                'subscription_ends_at' => Carbon::now()->addYear(),
            ]
        );

        $companyTwo = User::firstOrCreate(
            ['email' => 'barada.auto@example.com'],
            [
                'name' => 'شركة بردى أوتو',
                'phone_number' => '0991000005',
                'password' => Hash::make('password'),
                'account_type_id' => $companyType->id,
                'is_verified' => true,
                'is_subscribed' => true,
                'subscription_ends_at' => Carbon::now()->addMonths(8),
            ]
        );

        // حذف العروض القديمة حتى تظهر النتيجة الجديدة بشكل واضح
        Promotion::query()->delete();

        Promotion::create([
            'user_id' => $companyOne->id,
            'title' => 'خصم نهاية الشهر',
            'description' => 'خصم خاص على مجموعة من سيارات معرض الشام خلال نهاية الشهر.',
            'type' => 'discount',
            'value' => 10,
            'start_date' => Carbon::now()->subDay(),
            'end_date' => Carbon::now()->addDays(30),
            'quantity' => 20,
            'status' => 'pending',
        ]);

        Promotion::create([
            'user_id' => $companyOne->id,
            'title' => 'عرض استرداد نقدي',
            'description' => 'استرداد نقدي عند إتمام شراء سيارة من معرض الشام.',
            'type' => 'cashback',
            'value' => 5,
            'start_date' => Carbon::now()->subDays(2),
            'end_date' => Carbon::now()->addDays(20),
            'quantity' => 10,
            'status' => 'active',
        ]);

        Promotion::create([
            'user_id' => $companyTwo->id,
            'title' => 'باقة سيارات عائلية',
            'description' => 'عرض خاص على مجموعة سيارات عائلية من شركة بردى أوتو.',
            'type' => 'bundle_offer',
            'value' => null,
            'start_date' => Carbon::now()->addDay(),
            'end_date' => Carbon::now()->addDays(40),
            'quantity' => 5,
            'status' => 'pending',
        ]);

        Promotion::create([
            'user_id' => $companyTwo->id,
            'title' => 'عرض مرفوض تجريبي',
            'description' => 'هذا العرض مضاف فقط لاختبار حالة الرفض في لوحة الإدارة.',
            'type' => 'discount',
            'value' => 15,
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(15),
            'quantity' => 8,
            'status' => 'rejected',
        ]);

        Promotion::create([
            'user_id' => $companyOne->id,
            'title' => 'عرض منتهي',
            'description' => 'عرض قديم منتهي لاختبار حالة expired.',
            'type' => 'discount',
            'value' => 7,
            'start_date' => Carbon::now()->subDays(40),
            'end_date' => Carbon::now()->subDays(5),
            'quantity' => 12,
            'status' => 'expired',
        ]);
    }
}