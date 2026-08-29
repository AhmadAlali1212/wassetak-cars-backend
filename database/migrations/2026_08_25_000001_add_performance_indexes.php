<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فهرسة أداء بعد مراجعة الاستعلامات الفعلية بالجولات الأخيرة. أهم
     * اكتشاف: عمود `status` بجدولي `ads` و`rental_ads` بلا أي فهرس منذ
     * البداية، رغم إنه شرط WHERE الأساسي بكل طلب لقائمة السيارات المعروضة
     * (GET /ads و GET /rental-ads) — سيصبح مؤثراً جداً بمجرد نمو عدد
     * الإعلانات فعلياً. باقي الفهارس هنا تغطي أنماط استعلامات أضفتها
     * بالجولات الأخيرة (الرسائل، التقييمات، الحظر، الحجوزات، المزادات).
     *
     * ملاحظة: `foreignId()->constrained()` يُنشئ فهرساً تلقائياً على عمود
     * المفتاح الأجنبي نفسه فقط (car_id, user_id, إلخ) — هذا لا يغطي
     * استعلامات تجمع عمود آخر معه (مثل status أو تاريخ)، وهذا بالضبط ما
     * تضيفه هذه الهجرة كفهارس مركّبة (composite).
     */
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->index('status');
            $table->index(['status', 'published_at']);
        });

        Schema::table('rental_ads', function (Blueprint $table) {
            $table->index('status');
            $table->index(['status', 'published_at']);
        });

        Schema::table('auctions', function (Blueprint $table) {
            // يغطي مباشرة استعلام "auctions ending soon" المجدول، وكل
            // استعلامات القوائم المفلترة بالحالة (pending/active/closed)
            $table->index(['status', 'end_time']);
        });

        Schema::table('rental_bookings', function (Blueprint $table) {
            // يغطي فحص التعارض عند الحجز + استعلام التوفر + إخفاء السيارات
            // المؤجَّرة فعلياً من قائمة التصفح — كلها تفلتر rental_ad_id +
            // status + نطاق تاريخ معاً
            $table->index(['rental_ad_id', 'status', 'start_date', 'end_date'], 'rental_bookings_ad_status_dates_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            // يغطي جلب محادثة معينة (sender+receiver بالاتجاهين) وعدّ
            // الرسائل غير المقروءة لكل مستخدم
            $table->index(['sender_id', 'receiver_id']);
            $table->index(['receiver_id', 'is_read']);
        });

        Schema::table('ratings', function (Blueprint $table) {
            // rater_id مغطى ضمن unique indexes سابقة، لكن rated_user_id
            // (الأكثر استعلاماً فعلياً — كل عرض بروفايل) لم يكن مفهرساً إطلاقاً
            $table->index('rated_user_id');
        });

        Schema::table('blocks', function (Blueprint $table) {
            // blocker_id مغطى بالفعل بأول عمود بالـ unique index، لكن
            // البحث بالاتجاه المعاكس (blocked_user_id بمفرده) غير مفهرس
            $table->index('blocked_user_id');
        });

        Schema::table('bids', function (Blueprint $table) {
            // يغطي استعلام "أعلى مزايد سابق" (لإشعار bid_outbid) عند كل مزايدة جديدة
            $table->index(['auction_id', 'is_deposit_held']);
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'published_at']);
        });

        Schema::table('rental_ads', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'published_at']);
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropIndex(['status', 'end_time']);
        });

        Schema::table('rental_bookings', function (Blueprint $table) {
            $table->dropIndex('rental_bookings_ad_status_dates_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['sender_id', 'receiver_id']);
            $table->dropIndex(['receiver_id', 'is_read']);
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->dropIndex(['rated_user_id']);
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->dropIndex(['blocked_user_id']);
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->dropIndex(['auction_id', 'is_deposit_held']);
        });
    }
};
