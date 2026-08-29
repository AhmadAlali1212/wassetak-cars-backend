<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سجل تدقيق لإجراءات الأدمن (Audit Log) — كان غائباً تماماً بلوحة
     * الأدمن الجديدة: أي إجراء (حذف، موافقة، رفض، تعديل) ما كان يُسجَّل
     * بأي مكان، فلو أكتر من أدمن واحد يشتغلوا، ما في طريقة تعرف مين سوى
     * شو ومتى.
     */
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();

            // مثال: 'user.update', 'ad.destroy', 'car.approve', 'wallet.reject'
            $table->string('action');

            // النوع المتأثر بالإجراء (مثال: 'App\Models\Ad') ومعرّفه
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // وصف مختصر مقروء بشرياً (اختياري)، وبيانات إضافية خام (JSON)
            $table->text('description')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index('admin_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
