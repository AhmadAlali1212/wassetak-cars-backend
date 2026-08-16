<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول مخصص لرموز تحقق تفعيل الحساب عند التسجيل (OTP)،
     * منفصل تماماً عن جدول password_reset_tokens المستخدم لإعادة
     * تعيين كلمة المرور، حتى لا تتداخل قيود المحاولات بين الميزتين.
     */
    public function up(): void
    {
        Schema::create('email_verification_otps', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token'); // الرمز مشفّر بـ Hash::make مثل password_reset_tokens
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_otps');
    }
};
