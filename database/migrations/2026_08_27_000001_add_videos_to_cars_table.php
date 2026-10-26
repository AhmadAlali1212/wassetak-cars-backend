<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فيديوهات اختيارية للسيارة — يمكن رفعها عند إنشاء السيارة أو
     * لاحقاً بأي وقت (endpoints منفصلة لإضافة/حذف فيديو واحد بدون
     * الحاجة لتعديل بقية بيانات السيارة).
     */
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->json('videos')->nullable()->after('images');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('videos');
        });
    }
};
