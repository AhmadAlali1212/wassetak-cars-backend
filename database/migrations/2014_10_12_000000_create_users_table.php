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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_type_id'); // فقط العمود بدون علاقة
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone_number')->unique()->nullable(); // رقم الهاتف
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_verified')->default(false); // للتوثيق بالوثائق الرسمية
            $table->json('verification_documents')->nullable(); // مسار الوثائق (سجل تجاري، صور محل)
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
