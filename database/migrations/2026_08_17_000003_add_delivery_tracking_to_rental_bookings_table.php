<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة تتبع "تم التسليم / تم الإرجاع" الحقيقي على الحجز، بدل
     * الحل المؤقت السابق (SharedPreferences محلي غير مُزامَن بالفرونت).
     */
    public function up(): void
    {
        Schema::table('rental_bookings', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('status');
            $table->timestamp('returned_at')->nullable()->after('delivered_at');
            $table->text('rejection_reason')->nullable()->after('returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('rental_bookings', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'returned_at', 'rejection_reason']);
        });
    }
};
