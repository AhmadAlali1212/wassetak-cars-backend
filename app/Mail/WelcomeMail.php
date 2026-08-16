<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    // تعريف المتغيرات العامة التي سنمررها للـ View
    public $user;
    public $otp;

    /**
     * نمرر المستخدم والـ OTP عبر الـ Constructor عند استدعاء الإيميل
     */
    public function __construct($user, $otp)
    {
        $this->user = $user;
        $this->otp = $otp;
    }

    /**
     * عنوان الإيميل الذي سيظهر للمستخدم في صندوق الوارد
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رمز تحقق إعادة تعيين كلمة المرور',
        );
    }

    /**
     * ربط الإيميل بملف الواجهة (Blade View)
     */
    public function content(): Content
    {
        return new Content(
            view: 'otp', // سنقوم بإنشاء هذا الملف الآن في مجلد views
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
