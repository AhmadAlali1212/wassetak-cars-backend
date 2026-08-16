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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // الشركة/المعرض الذي أنشأ العرض
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // discount, cashback, bundle_offer
            $table->decimal('value', 10, 2)->nullable(); // قيمة الخصم أو العرض
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->integer('quantity')->nullable(); // الكمية المحدودة للعرض
            $table->string('status')->default('pending'); // pending, active, rejected, expired        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
