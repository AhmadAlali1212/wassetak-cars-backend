<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            // حقل نصي يحمل رقم الهيكل (VIN) أو رقم اللوحة - اختياري مؤقتاً للبيانات القديمة
            $table->string('vehicle_identifier')->nullable()->after('description');

            // إضافة فهرس index لتسريع عملية البحث والتتبع عبر الزمن
            $table->index('vehicle_identifier');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropIndex(['vehicle_identifier']);
            $table->dropColumn('vehicle_identifier');
        });
    }
};
