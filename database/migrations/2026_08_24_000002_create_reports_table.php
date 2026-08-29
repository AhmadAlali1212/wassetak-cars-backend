<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بلاغ عن مستخدم (احتيال، إساءة، محتوى غير لائق، إلخ)، مع ربط اختياري
     * بسياق محدد (رسالة معينة، إعلان، مزاد) لتسهيل مراجعة الأدمن لاحقاً.
     * لا endpoint حالياً لعرض/إدارة البلاغات من طرف الأدمن ضمن هذا التسليم؛
     * فقط الإنشاء (store) — الإدارة تبقى عبر استعلام مباشر لقاعدة البيانات
     * أو لوحة أدمن منفصلة إلى أن تُبنى شاشة مخصصة.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();

            // fraud, abuse, inappropriate_content, spam, other
            $table->string('reason');
            $table->text('details')->nullable();

            // سياق اختياري: message, ad, auction, rental_ad, rental_booking, rating
            $table->string('context_type')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();

            // pending, reviewed, dismissed, action_taken
            $table->string('status')->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['reported_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
