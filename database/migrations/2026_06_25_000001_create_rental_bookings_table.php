<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * جدول الحجوزات (rental_bookings): كل صف يمثّل فترة تأجير واحدة
     * محددة (تاريخ بداية + نهاية) لإعلان إيجار معيّن (rental_ad).
     *
     * هذا الجدول لا يستبدل rental_ads، بل يكمّله:
     * - rental_ads يبقى "الغلاف" الثابت (السيارة، السعر، الشروط، حالة
     *   موافقة الأدمن) وينشأ مرة واحدة من قبل المالك.
     * - rental_bookings هو السجل المتكرر للحجوزات الفعلية: كل مرة
     *   يحجز شخص السيارة لفترة معينة، يُنشأ صف جديد هنا، بينما
     *   rental_ad الأصلي يبقى كما هو.
     *
     * الفائدة الأساسية لكشف الاحتيال: تسمح هذه البنية بفحص "تقاطع
     * الفترات الزمنية" الحقيقي بين حجوزات مختلفة على نفس السيارة
     * (نفس vehicle_identifier)، بدل الاعتماد فقط على status.
     */
    public function up(): void
    {
        Schema::create('rental_bookings', function (Blueprint $table) {
            $table->id();

            // الإعلان الذي يخص هذا الحجز (الغلاف الثابت: السيارة، السعر، الشروط)
            $table->foreignId('rental_ad_id')->constrained()->onDelete('cascade');

            // المستخدم الذي قام بالحجز (المستأجر)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // فترة الحجز الفعلية
            $table->date('start_date');
            $table->date('end_date');

            // حالة الحجز نفسه، منفصلة عن حالة الإعلان (rental_ads.status)
            // confirmed: حجز مؤكد ونشط
            // completed: انتهت فترة الحجز فعلياً
            // cancelled: أُلغي الحجز قبل بدايته أو خلاله
            $table->string('status')->default('confirmed');

            $table->timestamps();

            // فهرس مركّب يسرّع البحث عن تقاطع الفترات الزمنية
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_bookings');
    }
};
