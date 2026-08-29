<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * نزاع مرتبط بمعاملة فعلية (نفس فكرة ربط التقييمات بمعاملة حقيقية):
     * "دفعت والسيارة مو متل الوصف"، "المؤجِّر ما رجّع العربون"، إلخ.
     * بعكس التقييمات، لا نشترط اكتمال المعاملة بالكامل — يمكن رفع نزاع
     * أثناء صفقة جارية (مثلاً حجز تأجير لسا confirmed ولم يُرجَع بعد).
     */
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('raised_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('against_user_id')->constrained('users')->cascadeOnDelete();

            // ad, auction, rental_booking
            $table->string('transaction_type');
            $table->unsignedBigInteger('transaction_id');

            // payment_issue, item_not_as_described, no_delivery, no_return,
            // no_payment, damaged_item, other
            $table->string('category');
            $table->text('description');

            // open, in_review, resolved, rejected
            $table->string('status')->default('open');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['transaction_type', 'transaction_id']);
            $table->index(['against_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
