<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // إضافة حقول الموافقة إلى جدول السيارات
        Schema::table('cars', function (Blueprint $table) {
            $table->string('approval_status')->default('pending')->after('images'); // pending, approved, rejected
            $table->text('approval_reason')->nullable()->after('approval_status'); // سبب الرفض أو الملاحظات
            $table->timestamp('approved_at')->nullable()->after('approval_reason');
        });

        // إضافة حقول الموافقة إلى جدول الإعلانات
        Schema::table('ads', function (Blueprint $table) {
            $table->string('approval_status')->default('pending')->after('expires_at'); // pending, approved, rejected
            $table->text('approval_reason')->nullable()->after('approval_status'); // سبب الرفض أو الملاحظات
            $table->timestamp('approved_at')->nullable()->after('approval_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approval_reason', 'approved_at']);
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approval_reason', 'approved_at']);
        });
    }
};
