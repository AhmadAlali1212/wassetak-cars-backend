<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة حقل batch_id لتتبع رفعات Excel الجماعية
     */
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            // معرّف الدفعة - يُملأ فقط عند الرفع الجماعي، يبقى null للسيارات الفردية
            $table->string('bulk_upload_batch')->nullable()->after('approval_status');

            // فهرس لتسريع البحث بالدفعة (مفيد لصفحة السجل)
            $table->index('bulk_upload_batch');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropIndex(['bulk_upload_batch']);
            $table->dropColumn('bulk_upload_batch');
        });
    }
};
