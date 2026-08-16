<?php

namespace Database\Seeders;

use App\Models\AccountType;
use Illuminate\Database\Seeder;

class AccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'individual', 'description' => 'حساب فردي لبيع وشراء وتأجير السيارات'],
            ['name' => 'company', 'description' => 'حساب شركة أو معرض لإدارة إعلانات السيارات والعروض'],
            ['name' => 'admin', 'description' => 'حساب مدير النظام لإدارة المنصة'],
        ];

        foreach ($types as $type) {
            AccountType::updateOrCreate(
                ['name' => $type['name']],
                ['description' => $type['description']]
            );
        }
    }
}
