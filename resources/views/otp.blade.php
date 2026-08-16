<!DOCTYPE html>
<html>
<head>
    <title>رمز التحقق</title>
</head>
<body style="font-family: Arial, sans-serif; direction: rtl; text-align: right;">
    <h2>مرحباً {{ $user->name }}،</h2>
    <p>لقد طلبت إعادة تعيين كلمة المرور الخاصة بك.</p>
    <p>رمز التحقق (OTP) الخاص بك هو:</p>
    <h1 style="color: #4f46e5; letter-spacing: 2px;">{{ $otp }}</h1>
    <p>هذا الرمز صالح لمدة 15 دقيقة فقط.</p>
    <p>إذا لم تكن أنت من طلب هذا، يمكنك تجاهل هذا الإيميل بأمان.</p>
</body>
</html>
