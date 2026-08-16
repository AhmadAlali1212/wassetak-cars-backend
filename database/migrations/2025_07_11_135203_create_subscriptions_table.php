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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2); // المبلغ الشهري للاشتراك
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->string('status')->default('active'); // 'active', 'inactive', 'cancelled', 'expired'
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null'); // المعاملة المرتبطة بالدفع
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
