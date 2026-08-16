<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\Rating;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RatingSeeder extends Seeder
{
    public function run(): void
    {
        $individualType = AccountType::firstOrCreate(
            ['name' => 'individual'],
            ['description' => 'حساب فردي لبيع وشراء وتأجير السيارات']
        );

        $companyType = AccountType::firstOrCreate(
            ['name' => 'company'],
            ['description' => 'حساب شركة أو معرض لإدارة إعلانات السيارات والعروض']
        );

        $ahmad = User::firstOrCreate(
            ['email' => 'ahmad@example.com'],
            [
                'name' => 'أحمد الخطيب',
                'phone_number' => '0991000002',
                'password' => Hash::make('password'),
                'account_type_id' => $individualType->id,
                'is_verified' => true,
                'is_subscribed' => true,
                'subscription_ends_at' => Carbon::now()->addMonths(3),
            ]
        );

        $sara = User::firstOrCreate(
            ['email' => 'sara@example.com'],
            [
                'name' => 'سارة الحسن',
                'phone_number' => '0991000003',
                'password' => Hash::make('password'),
                'account_type_id' => $individualType->id,
                'is_verified' => true,
                'is_subscribed' => true,
                'subscription_ends_at' => Carbon::now()->addMonths(2),
            ]
        );

        $shamCars = User::firstOrCreate(
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

        $baradaAuto = User::firstOrCreate(
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

        /*
         * نحذف فقط تقييمات التجربة حتى لا تتكرر عند تشغيل seeder أكثر من مرة.
         */
        Rating::whereIn('comment', [
            'تعامل ممتاز وسريع، أنصح بالتعامل معه.',
            'الشركة متعاونة والسيارة كانت مطابقة للوصف.',
            'مستخدم جاد بالتعامل والدفع كان سريع.',
            'تجربة جيدة، لكن الرد كان متأخر قليلاً.',
            'المعرض قدم معلومات واضحة عن السيارة.',
        ])->delete();

        Rating::create([
            'rater_id' => $sara->id,
            'rated_user_id' => $shamCars->id,
            'rating' => 5,
            'comment' => 'تعامل ممتاز وسريع، أنصح بالتعامل معه.',
        ]);

        Rating::create([
            'rater_id' => $ahmad->id,
            'rated_user_id' => $shamCars->id,
            'rating' => 4,
            'comment' => 'الشركة متعاونة والسيارة كانت مطابقة للوصف.',
        ]);

        Rating::create([
            'rater_id' => $shamCars->id,
            'rated_user_id' => $sara->id,
            'rating' => 5,
            'comment' => 'مستخدم جاد بالتعامل والدفع كان سريع.',
        ]);

        Rating::create([
            'rater_id' => $sara->id,
            'rated_user_id' => $baradaAuto->id,
            'rating' => 3,
            'comment' => 'تجربة جيدة، لكن الرد كان متأخر قليلاً.',
        ]);

        Rating::create([
            'rater_id' => $baradaAuto->id,
            'rated_user_id' => $ahmad->id,
            'rating' => 4,
            'comment' => 'المعرض قدم معلومات واضحة عن السيارة.',
        ]);
    }
}