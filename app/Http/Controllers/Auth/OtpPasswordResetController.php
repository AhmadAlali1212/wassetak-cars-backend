<?php

namespace App\Http\Controllers\Auth;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\WelcomeMail; // الكلاس الخاص بإرسال الإيميل
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class OtpPasswordResetController extends Controller
{
    /**
     * 1. توليد وإرسال رمز الـ OTP
     */


/**
 * 1. توليد وإرسال رمز الـ OTP مع تحديد 3 محاولات فقط
 *//**
 * توليد وإرسال رمز الـ OTP مع شرط: دقيقة بين المحاولات، وبحد أقصى 3 محاولات
 */
public function sendOtp(Request $request)
{
    $request->validate(['email' => 'required|email|exists:users,email']);

    $email = $request->email;
    $delayKey = 'otp-delay:' . $email;  // مفتاح قفل الدقيقة
    $maxKey = 'otp-max:' . $email;      // مفتاح قفل الـ 3 محاولات

    // 1. 🔥 الفحص الأول: هل يحاول الإرسال قبل مرور دقيقة على المحاولة السابقة؟
    if (RateLimiter::tooManyAttempts($delayKey, $maxAttempts = 1)) {
        $seconds = RateLimiter::availableIn($delayKey);
        return response()->json([
            'message' => "يرجى الانتظار {$seconds} ثانية قبل طلب رمز جديد."
        ], 429);
    }

    // 2. 🔥 الفحص الثاني: هل استهلك الـ 3 محاولات بالكامل؟
    if (RateLimiter::tooManyAttempts($maxKey, $perHour = 3)) {
        $seconds = RateLimiter::availableIn($maxKey);
        $minutes = ceil($seconds / 60);
        return response()->json([
            'message' => "لقد استنفدت جميع محاولاتك (3 محاولات). يرجى المحاولة مجدداً بعد {$minutes} دقيقة."
        ], 429);
    }

    // --------------------------------------------------------
    // تنفيذ عملية الإرسال الطبيعية عند تخطي الشروط
    // --------------------------------------------------------
    $user = User::where('email', $email)->first();
    $otp = rand(100000, 999999);
        Log::info('OTP generated', ['email' => $email, 'otp' => $otp]);
      DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => $email],
        [
            'token' => Hash::make($otp),
            'created_at' => now()
        ]
    );

    Mail::to($user->email)->send(new WelcomeMail($user, $otp));

    // 3. 🔥 تسجيل القيود الجديدة بعد نجاح الإرسال

    // قفل الدقيقة: محاولة واحدة وتختفي بعد 60 ثانية
    RateLimiter::hit($delayKey, $decaySeconds = 60);

    // عداد المحاولات: يزداد بمقدار 1 ويختفي بعد ساعة (3600 ثانية)
    RateLimiter::hit($maxKey, $decaySeconds = 3600);

    // حساب المحاولات المتبقية من الـ 3 لإظهارها للمستخدم
    $remaining = RateLimiter::remaining($maxKey, 3);

    return response()->json([
        'message' => 'تم إرسال رمز الـ OTP إلى بريدك الإلكتروني بنجاح.',
        'remaining_attempts' => $remaining
    ], 200);
}
 /*    * 2. التحقق من صحة رمز الـ OTP المرسل
     * يُستدعى عبر المسار: password/otp-verify
     */
    public function verifyOtp(Request $request)
    {
        
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp'   => 'required|numeric|digits:6',
        ]);

        // جلب سجل الرمز المرتبط بهذا البريد الإلكتروني
        $resetRecord = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$resetRecord) {
            return response()->json(['message' => 'لم يتم طلب رمز تحقق لهذا البريد الإلكتروني.'], 400);
        }

        // التحقق من صلاحية الوقت (ألا يكون الرمز قد مر عليه أكثر من 15 دقيقة)
        if (now()->parse($resetRecord->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد.'], 400);
        }

        // بما أن الرمز مشفر بـ Hash::make، نستخدم Hash::check للمقارنة والتحقق
        if (!Hash::check($request->otp, $resetRecord->token)) {
            return response()->json(['message' => 'رمز التحقق غير صحيح.'], 400);
        }

        return response()->json(['message' => 'رمز التحقق صحيح، يمكنك الآن الانتقال لخطوة تعيين كلمة المرور.'], 200);
    }

    /**
     * 3. تعيين كلمة المرور الجديدة بعد نجاح التحقق
     * يُستدعى عبر المسار: password/otp-reset
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:users,email',
            'otp'      => 'required|numeric|digits:6',
            'password' => 'required|min:8|confirmed', // يتطلب إرسال حقل password_confirmation مطابق تماماً
        ]);

        // جلب السجل من قاعدة البيانات للمرة الأخيرة (أمان إضافي)
        $resetRecord = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$resetRecord) {
            return response()->json(['message' => 'طلب غير صالح أو انتهت الجلسة، يرجى المحاولة مجدداً.'], 400);
        }

        // فحص وقت الصلاحية مجدداً
        if (now()->parse($resetRecord->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'انتهت صلاحية الجلسة، يرجى طلب رمز جديد.'], 400);
        }

        // مطابقة الرمز المدخل مع الهاش المخزن
        if (!Hash::check($request->otp, $resetRecord->token)) {
            return response()->json(['message' => 'رمز التحقق غير صحيح.'], 400);
        }

        // تحديث كلمة مرور المستخدم في جدول الـ users الرئيسي
        $user = User::where('email', $request->email)->first();
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // مسح الرمز من جدول التوكنات نهائياً لحماية الحساب ومنع استخدام الرمز نفسه مرة أخرى
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
// تصفير قيود الإرسال بعد تغيير كلمة المرور بنجاح
RateLimiter::clear('otp-delay:' . $request->email);
RateLimiter::clear('otp-max:' . $request->email);
        return response()->json(['message' => 'تم إعادة تعيين كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول بكلمة المرور الجديدة.'], 200);
    }
}
