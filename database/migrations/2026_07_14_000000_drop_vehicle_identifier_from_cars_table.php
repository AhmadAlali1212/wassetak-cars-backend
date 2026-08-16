<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * إزالة نهائية لحقل vehicle_identifier (رقم الهيكل/VIN) وأي فهرس
     * متعلق به من جدول cars. تتحقق من وجود العمود/الفهرس قبل الحذف
     * حتى تعمل بأمان بغض النظر عن الحالة الفعلية لقاعدة البيانات
     * (سواء مرّت الميغريشنز السابقة بنجاح أو فشلت جزئياً).
     */
    public function up(): void
    {
        if (Schema::hasColumn('cars', 'vehicle_identifier')) {
            // حذف أي فهرس (عادي أو unique) مرتبط بالعمود أولاً، إن وُجد
            $indexes = collect(DB::select("SHOW INDEX FROM cars WHERE Column_name = 'vehicle_identifier'"))
                ->pluck('Key_name')
                ->unique();

            foreach ($indexes as $indexName) {
                DB::statement("ALTER TABLE cars DROP INDEX `{$indexName}`");
            }

            Schema::table('cars', function (Blueprint $table) {
                $table->dropColumn('vehicle_identifier');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('cars', 'vehicle_identifier')) {
            Schema::table('cars', function (Blueprint $table) {
                $table->string('vehicle_identifier')->nullable()->after('description');
            });
        }
    }
};
