<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تفعيل الحساب</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family: Tahoma, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">

                    <!-- الهيدر -->
                    <tr>
                        <td align="center" style="background-color:#0f172a; padding:28px 24px;">
                            <span style="font-size:22px; font-weight:bold; color:#ffffff; letter-spacing:0.5px;">
                                Syria Motor
                            </span>
                        </td>
                    </tr>

                    <!-- المحتوى -->
                    <tr>
                        <td style="padding:32px 28px 8px 28px; text-align:right;">
                            <p style="margin:0 0 4px 0; font-size:16px; color:#111827;">
                                أهلاً {{ $user->name ?? 'بك' }} 👋
                            </p>
                            <p style="margin:0; font-size:14px; line-height:1.8; color:#4b5563;">
                                شكراً لتسجيلك في <strong>Syria Motor</strong>. لإتمام تفعيل حسابك، استخدم رمز التحقق التالي:
                            </p>
                        </td>
                    </tr>

                    <!-- رمز الـ OTP -->
                    <tr>
                        <td align="center" style="padding:24px 28px;">
                            <div style="display:inline-block; background-color:#eef2ff; border:1px dashed #6366f1; border-radius:10px; padding:16px 36px;">
                                <span style="font-size:32px; font-weight:bold; letter-spacing:8px; color:#312e81; direction:ltr; unicode-bidi:bidi-override;">
                                    {{ $otp }}
                                </span>
                            </div>
                        </td>
                    </tr>

                    <!-- ملاحظات -->
                    <tr>
                        <td style="padding:0 28px 8px 28px; text-align:right;">
                            <p style="margin:0; font-size:13px; line-height:1.8; color:#6b7280;">
                                ⏳ هذا الرمز صالح لمدة <strong>15 دقيقة</strong> فقط من وقت إرساله.<br>
                                🔒 لا تشارك هذا الرمز مع أي شخص، فريق Syria Motor لن يطلبه منك أبداً.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 28px 28px 28px; text-align:right;">
                            <p style="margin:0; font-size:13px; line-height:1.8; color:#9ca3af;">
                                إذا لم تقم بإنشاء هذا الحساب، يمكنك تجاهل هذه الرسالة بأمان.
                            </p>
                        </td>
                    </tr>

                    <!-- الفوتر -->
                    <tr>
                        <td align="center" style="background-color:#f9fafb; padding:16px; border-top:1px solid #eef0f2;">
                            <span style="font-size:12px; color:#9ca3af;">
                                &copy; {{ date('Y') }} Syria Motor — جميع الحقوق محفوظة
                            </span>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
