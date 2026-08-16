<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * جدول cars يملك عمود approval_reason مسبقاً، لكن جداول
     * ads و rental_ads و promotions لا تخزّن سبب الرفض إطلاقاً،
     * لذلك نضيفه هنا حتى يظهر سبب الرفض المختار من القائمة
     * ويُخزَّن ويُعرض لاحقاً للمستخدم أو الأدمن.
     */
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (!Schema::hasColumn('ads', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
        });

        Schema::table('rental_ads', function (Blueprint $table) {
            if (!Schema::hasColumn('rental_ads', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
        });

        Schema::table('promotions', function (Blueprint $table) {
            if (!Schema::hasColumn('promotions', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (Schema::hasColumn('ads', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });

        Schema::table('rental_ads', function (Blueprint $table) {
            if (Schema::hasColumn('rental_ads', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });

        Schema::table('promotions', function (Blueprint $table) {
            if (Schema::hasColumn('promotions', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });
    }
};
