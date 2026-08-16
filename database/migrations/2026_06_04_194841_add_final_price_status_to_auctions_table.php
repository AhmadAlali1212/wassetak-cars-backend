<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->string('final_price_status')->nullable()->after('winner_id');
            $table->timestamp('final_price_decided_at')->nullable()->after('final_price_status');
            $table->timestamp('closed_at')->nullable()->after('final_price_decided_at');
            $table->string('closed_reason')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn([
                'final_price_status',
                'final_price_decided_at',
                'closed_at',
                'closed_reason',
            ]);
        });
    }
};