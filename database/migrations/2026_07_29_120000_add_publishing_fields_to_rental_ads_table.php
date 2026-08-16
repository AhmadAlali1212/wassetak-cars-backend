<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_ads', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('status');
            $table->timestamp('expires_at')->nullable()->after('published_at');
            $table->index(
                ['status', 'published_at', 'expires_at'],
                'rental_ads_publication_index'
            );
        });

        // إبقاء الإعلانات النشطة القديمة ظاهرة؛ إعلان التأجير نفسه لا تنتهي صلاحيته.
        DB::table('rental_ads')
            ->where('status', 'active')
            ->whereNull('published_at')
            ->update([
                'published_at' => now(),
                'expires_at' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('rental_ads', function (Blueprint $table) {
            $table->dropIndex('rental_ads_publication_index');
            $table->dropColumn(['published_at', 'expires_at']);
        });
    }
};
