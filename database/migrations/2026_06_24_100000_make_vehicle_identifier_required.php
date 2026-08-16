<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ملء البيانات الموجودة بـ vehicle_identifier افتراضي
        DB::table('cars')
            ->whereNull('vehicle_identifier')
            ->update([
                'vehicle_identifier' => DB::raw("CONCAT('VIN_', id)")
            ]);

        // 2. جعل الحقل إجباري (NOT NULL)
        Schema::table('cars', function (Blueprint $table) {
            $table->string('vehicle_identifier')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->string('vehicle_identifier')->nullable()->change();
        });
    }
};
