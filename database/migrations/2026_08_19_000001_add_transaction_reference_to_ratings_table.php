<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ربط التقييمات (Ratings) بشكل صارم بمعاملة فعلية حقيقية، بدل السماح
     * بتقييم حر بين أي مستخدمين. كل تقييم جديد يجب أن يشير إلى بالضبط
     * واحدة من: إعلان بيع مكتمل (ad_id)، مزاد مكتمل (auction_id)، أو
     * حجز تأجير مكتمل (rental_booking_id). القيود الفعلية للتحقق موجودة
     * بـ RatingController::store، هذا الجدول فقط يخزّن المرجع + يمنع
     * تكرار التقييم لنفس المعاملة من نفس المُقيِّم.
     */
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->foreignId('ad_id')->nullable()->after('rated_user_id')
                ->constrained('ads')->nullOnDelete();
            $table->foreignId('auction_id')->nullable()->after('ad_id')
                ->constrained('auctions')->nullOnDelete();
            $table->foreignId('rental_booking_id')->nullable()->after('auction_id')
                ->constrained('rental_bookings')->nullOnDelete();

            // MySQL يسمح بأكثر من قيمة NULL بفهرس unique، فهذا لا يمنع
            // تكرار قيم null (تقييمات قديمة قبل هذا التعديل)، لكنه يمنع
            // تكرار تقييم نفس المُقيِّم لنفس المعاملة تحديداً.
            $table->unique(['rater_id', 'ad_id']);
            $table->unique(['rater_id', 'auction_id']);
            $table->unique(['rater_id', 'rental_booking_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropUnique(['rater_id', 'ad_id']);
            $table->dropUnique(['rater_id', 'auction_id']);
            $table->dropUnique(['rater_id', 'rental_booking_id']);
            $table->dropConstrainedForeignId('ad_id');
            $table->dropConstrainedForeignId('auction_id');
            $table->dropConstrainedForeignId('rental_booking_id');
        });
    }
};
