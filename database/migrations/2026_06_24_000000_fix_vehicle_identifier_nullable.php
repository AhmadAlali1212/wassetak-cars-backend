<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            // تعديل الحقل ليكون nullable بدلاً من إلزامي
            $table->string('vehicle_identifier')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->string('vehicle_identifier')->nullable(false)->change();
        });
    }
};
