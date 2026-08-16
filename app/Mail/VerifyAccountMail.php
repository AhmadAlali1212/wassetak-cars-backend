<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $otp;

    /**
     * نمرر المستخدم ورمز الـ OTP عبر الـ Constructor عند استدعاء الإيميل
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
            subject: 'رمز تفعيل حسابك في Syria Motor',
        );
    }

    /**
     * ربط الإيميل بملف الواجهة (Blade View) المخصص لتفعيل الحساب
     */
    public function content(): Content
    {
        return new Content(
            view: 'otp_register',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
