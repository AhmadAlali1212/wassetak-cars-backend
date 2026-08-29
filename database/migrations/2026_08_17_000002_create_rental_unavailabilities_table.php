<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول فترات عدم التوفر اليدوية (rental_unavailabilities):
     * يسمح لمالك إعلان التأجير بتحديد أيام لا تكون فيها سيارته متاحة
     * للحجز، بشكل مستقل عن الحجوزات الفعلية في rental_bookings
     * (مثلاً: السيارة عند الصيانة، أو محجوزة خارج التطبيق).
     *
     * هذا الجدول لا يستبدل rental_bookings؛ كلاهما يُستخدم معاً عند
     * حساب الأيام المتاحة فعلياً لإعلان معيّن.
     */
    public function up(): void
    {
        Schema::create('rental_unavailabilities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rental_ad_id')->constrained()->cascadeOnDelete();

            // من أضاف الحجب (مالك الإعلان) — للتوثيق فقط
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            // سبب اختياري يظهر لمالك الإعلان فقط (صيانة، حجز خارجي، إلخ)
            $table->string('reason')->nullable();

            $table->timestamps();

            $table->index(['rental_ad_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_unavailabilities');
    }
};
