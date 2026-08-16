<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rental_ads')) {
            return;
        }

        // إعلان التأجير لا تنتهي صلاحيته بانتهاء حجز عميل واحد.
        if (Schema::hasColumn('rental_ads', 'expires_at')) {
            DB::table('rental_ads')->update([
                'expires_at' => null,
            ]);
        }

        // تصحيح السجلات القديمة التي عُلّمت rented وجعل الإعلان قابلاً للاستخدام مجدداً.
        DB::table('rental_ads')
            ->where('status', 'rented')
            ->update([
                'status' => 'active',
                'rejection_reason' => null,
            ]);

        // ضمان بقاء الإعلانات النشطة القديمة منشورة.
        if (Schema::hasColumn('rental_ads', 'published_at')) {
            DB::table('rental_ads')
                ->where('status', 'active')
                ->whereNull('published_at')
                ->update([
                    'published_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // لا نعيد فرض مدة انتهاء على إعلانات التأجير عند التراجع.
    }
};
