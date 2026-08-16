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
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->onDelete('cascade'); // السيارة في المزاد
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // البائع
            $table->decimal('starting_price', 10, 2);
            $table->decimal('current_price', 10, 2);
            $table->dateTime('start_time');
            $table->dateTime('end_time'); 
            $table->foreignId('winner_id')->nullable()->constrained('users')->onDelete('set null'); // الفائز بالمزاد
            $table->string('status')->default('pending'); // 'pending', 'active', 'closed', 'cancelled'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
