<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_usd', 12, 2);
            $table->decimal('exchange_rate', 20, 6);
            $table->decimal('wallet_amount', 20, 2);
            $table->string('currency', 3)->default('usd');
            $table->string('status')->default('creating');
            $table->uuid('request_id');
            $table->string('idempotency_key')->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->timestamp('credited_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_topups');
    }
};
