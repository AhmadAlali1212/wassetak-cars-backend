<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // المشروع يستخدم ENUM في MySQL لإعلانات البيع؛ نضيف الحالة expired.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE ads MODIFY COLUMN status " .
                "ENUM('draft', 'pending', 'active', 'expired', 'rejected', 'sold') " .
                "DEFAULT 'pending'"
            );
        }

        if (!Schema::hasColumn('ads', 'expiration_reminder_sent_at')) {
            Schema::table('ads', function (Blueprint $table) {
                $table->timestamp('expiration_reminder_sent_at')
                    ->nullable()
                    ->after('expires_at');

                $table->index(
                    ['status', 'expires_at'],
                    'ads_status_expires_at_index'
                );
            });
        }

        // إعطاء الإعلانات النشطة القديمة دورة نشر واضحة بدلاً من بقائها بلا نهاية.
        DB::table('ads')
            ->where('status', 'active')
            ->whereNull('published_at')
            ->update([
                'published_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('ads')
            ->where('status', 'active')
            ->whereNull('expires_at')
            ->update([
                'expires_at' => now()->addDays(30),
                'expiration_reminder_sent_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // لا يمكن إزالة expired من ENUM بينما توجد صفوف بهذه الحالة.
        DB::table('ads')
            ->where('status', 'expired')
            ->update([
                'status' => 'pending',
                'published_at' => null,
                'expires_at' => null,
                'updated_at' => now(),
            ]);

        if (Schema::hasColumn('ads', 'expiration_reminder_sent_at')) {
            Schema::table('ads', function (Blueprint $table) {
                $table->dropIndex('ads_status_expires_at_index');
                $table->dropColumn('expiration_reminder_sent_at');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE ads MODIFY COLUMN status " .
                "ENUM('draft', 'pending', 'active', 'rejected', 'sold') " .
                "DEFAULT 'pending'"
            );
        }
    }
};
