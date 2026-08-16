<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // رمز جهاز FCM (Firebase Cloud Messaging). فريد لأن نفس الرمز
            // يمثل تثبيتاً واحداً للتطبيق على جهاز معيّن — لو سجّل مستخدم
            // آخر بنفس الجهاز (تسجيل خروج/دخول)، ننقل ملكية الرمز له بدل
            // ترك تكرار.
            $table->string('token')->unique();

            // android / ios / web — مفيد لاحقاً لو احتجنا صياغة مختلفة للإشعار
            $table->string('platform')->nullable();

            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
