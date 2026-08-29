<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ربط الإعلان بالمشتري الفعلي عند تحويله لحالة "sold" ذاتياً من
     * قبل البائع نفسه (وليس فقط من لوحة الأدمن). هذا الربط ضروري
     * لبناء نظام تقييم موثوق بين بائع ومشتري لاحقاً.
     */
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->foreignId('buyer_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('sold_at')->nullable()->after('buyer_id');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('buyer_id');
            $table->dropColumn('sold_at');
        });
    }
};
