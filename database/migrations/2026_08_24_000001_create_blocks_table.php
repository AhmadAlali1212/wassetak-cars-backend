<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * حظر مستخدم: يمنع أي تواصل جديد بين الطرفين (رسائل تحديداً)، ويُستخدم
     * أيضاً لإخفاء الطرف المحظور من نتائج البحث عن مستخدمين إن رغبنا لاحقاً.
     * الحظر باتجاه واحد فقط (blocker -> blocked)، لكن التحقق بالتطبيق
     * (MessageController) يفحص الاتجاهين معاً قبل السماح بإرسال رسالة.
     */
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['blocker_id', 'blocked_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
