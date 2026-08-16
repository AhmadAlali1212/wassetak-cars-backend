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
    Schema::table('cars', function (Blueprint $table) {
        // 1. شارة الفحص: هل السيارة مفحوصة ومضمونة أم لا
        $table->boolean('is_inspected')->default(false)->after('condition');

        // 2. مسار ملف تقرير الفحص الفني (PDF أو صورة)
        $table->string('inspection_report_path')->nullable()->after('is_inspected');

        // 3. تاريخ ووقت الفحص
        $table->timestamp('inspected_at')->nullable()->after('inspection_report_path');
    });
}

public function down(): void
{
    Schema::table('cars', function (Blueprint $table) {
        $table->dropColumn(['is_inspected', 'inspection_report_path', 'inspected_at']);
    });
}
};
