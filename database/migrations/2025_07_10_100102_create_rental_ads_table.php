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
        Schema::create('rental_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->onDelete('cascade'); // السيارة المعروضة للإيجار
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // المؤجر
            $table->decimal('daily_price', 10, 2)->nullable();
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->text('rental_conditions')->nullable();
            $table->string('location');
            $table->string('status')->default('pending'); // 'pending', 'active', 'rented', 'rejected'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_ads');
    }
};
