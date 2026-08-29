<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول المفضلة (favorites): يسمح لأي مستخدم بحفظ سيارة معينة في
     * قائمة مفضلته. كانت هذه الميزة شكلية محلية فقط بالتطبيق (تُفقد
     * عند إغلاق التطبيق)، هذا الجدول يجعلها حقيقية ومُزامنة.
     */
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('car_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            // نفس المستخدم لا يمكن أن يضيف نفس السيارة مرتين
            $table->unique(['user_id', 'car_id']);
            $table->index('user_id');
            $table->index('car_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
