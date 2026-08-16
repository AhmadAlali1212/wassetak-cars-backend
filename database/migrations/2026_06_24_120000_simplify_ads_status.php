<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // حذف الحقول غير المستخدمة من ads
        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approval_reason', 'approved_at']);
        });

        // إضافة قيود على status field
        DB::statement("ALTER TABLE ads MODIFY COLUMN status ENUM('pending', 'active', 'rejected', 'sold') DEFAULT 'pending'");

        // حذف الحقول غير المستخدمة من rental_ads
        Schema::table('rental_ads', function (Blueprint $table) {
            // تحقق من وجود الأعمدة أولاً
            if (Schema::hasColumn('rental_ads', 'approval_status')) {
                $table->dropColumn('approval_status');
            }
            if (Schema::hasColumn('rental_ads', 'approval_reason')) {
                $table->dropColumn('approval_reason');
            }
            if (Schema::hasColumn('rental_ads', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });

        // إضافة قيود على status field
        DB::statement("ALTER TABLE rental_ads MODIFY COLUMN status ENUM('pending', 'active', 'rejected', 'rented') DEFAULT 'pending'");
    }

    public function down(): void
    {
        // إعادة إضافة الحقول
        Schema::table('ads', function (Blueprint $table) {
            $table->string('approval_status')->default('pending')->after('expires_at');
            $table->text('approval_reason')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approval_reason');
        });

        // إعادة status إلى string عادي
        DB::statement("ALTER TABLE ads MODIFY COLUMN status VARCHAR(255) DEFAULT 'pending'");

        Schema::table('rental_ads', function (Blueprint $table) {
            $table->string('approval_status')->default('pending')->after('location');
            $table->text('approval_reason')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approval_reason');
        });

        DB::statement("ALTER TABLE rental_ads MODIFY COLUMN status VARCHAR(255) DEFAULT 'pending'");
    }
};
