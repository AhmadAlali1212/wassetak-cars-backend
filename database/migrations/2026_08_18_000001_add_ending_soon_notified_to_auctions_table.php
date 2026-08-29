<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * علم يمنع إرسال إشعار "اقترب انتهاء المزاد" أكثر من مرة لنفس المزاد.
     */
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->timestamp('ending_soon_notified_at')->nullable()->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('ending_soon_notified_at');
        });
    }
};
