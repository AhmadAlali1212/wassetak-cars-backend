<?php

namespace Tests\Feature\Concerns;

use App\Models\AccountType;
use App\Models\User;

/**
 * users.account_type_id إلزامي (غير nullable) وما في له default بجدول
 * الهجرة، فـ UserFactory وحده غير كافٍ لإنشاء مستخدم صالح. هذا الـ trait
 * يضمن وجود أنواع الحسابات الثلاثة (نفس AccountTypeSeeder) وينشئ مستخدماً
 * بالنوع المطلوب مباشرة.
 */
trait CreatesTestUsers
{
    protected function accountType(string $name): AccountType
    {
        return AccountType::firstOrCreate(
            ['name' => $name],
            ['description' => $name]
        );
    }

    protected function individualUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'account_type_id' => $this->accountType('individual')->id,
        ], $overrides));
    }

    protected function companyUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'account_type_id' => $this->accountType('company')->id,
        ], $overrides));
    }

    protected function adminUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'account_type_id' => $this->accountType('admin')->id,
        ], $overrides));
    }
}
