<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();

            // المستخدم الذي قام بالتقييم
            $table->foreignId('rater_id')
                ->constrained('users')
                ->onDelete('cascade');

            // المستخدم الذي تم تقييمه
            $table->foreignId('rated_user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // عدد النجوم من 1 إلى 5
            $table->unsignedTinyInteger('rating');

            // تعليق اختياري
            $table->text('comment')->nullable();

            $table->timestamps();

            // منع نفس المستخدم من تقييم نفس الشخص أكثر من مرة
            $table->unique(['rater_id', 'rated_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
