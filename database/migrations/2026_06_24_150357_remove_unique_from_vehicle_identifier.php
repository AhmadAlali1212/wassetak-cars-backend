<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('cars', function (Blueprint $table) {
        $indexes = collect(DB::select('SHOW INDEX FROM cars'))->pluck('Key_name')->unique();

        if ($indexes->contains('cars_vehicle_identifier_unique')) {
            $table->dropUnique(['vehicle_identifier']);
        }
    });
}

public function down(): void
{
    Schema::table('cars', function (Blueprint $table) {
        $table->unique('vehicle_identifier');
    });
}
};
