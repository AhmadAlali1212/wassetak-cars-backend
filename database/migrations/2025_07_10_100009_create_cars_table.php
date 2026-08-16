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
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // مالك السيارة
            $table->string('make'); // اسم الشركة المصنعة
            $table->string('model'); // الموديل
            $table->integer('year'); // سنة الصنع
            $table->string('condition'); // 'new' or 'used' (جديدة أو مستعملة)
            $table->text('description')->nullable();
// جعلناه nullable لأننا سنجلبه بالخلفية تلقائياً من الإحداثيات
            $table->string('location')->nullable();

            // 🔥 إضافة أعمدة الخرائط هنا
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->json('images')->nullable(); // مسارات الصور
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
