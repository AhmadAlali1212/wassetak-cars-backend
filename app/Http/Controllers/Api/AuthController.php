<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet; // لاستخدام نموذج المحفظة
use App\Mail\VerifyAccountMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\AccountType; // لاستخدام نموذج أنواع الحسابات

class AuthController extends Controller
{
    /**
     * تسجيل مستخدم جديد.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'nullable|string|unique:users|regex:/^([0-9\s\-\+\(\)]*)$/|min:10', // يمكن أن يكون رقم الهاتف اختيارياً
            'password' => 'required|string|min:8|confirmed',
            'account_type' => 'required|string|in:individual,company', // يجب أن يكون أحد الأنواع المحددة
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // البحث عن نوع الحساب
        $accountType = AccountType::where('name', $request->account_type)->first();

        if (!$accountType) {
            return response()->json(['message' => 'نوع الحساب غير صالح.'], 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'account_type_id' => $accountType->id,
            'is_verified' => false, // افتراضياً غير موثق
            'verification_documents' => null, // لا توجد وثائق عند التسجيل
        ]);

        // إنشاء محفظة جديدة للمستخدم
        $user->wallet()->create(['balance' => 0]);

        // توليد وإرسال رمز تحقق تفعيل الحساب (بنفس شروط ميزة نسيت كلمة المرور)
        $this->generateAndSendVerificationOtp($user);

      //  $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل المستخدم بنجاح. تم إرسال رمز تحقق إلى بريدك الإلكتروني لتفعيل الحساب.',
            'user' => $user,
            //'access_token' => $token,
           // 'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * توليد رمز OTP جديد لتفعيل الحساب وإرساله على بريد المستخدم،
     * وتسجيل قيود معدل الإرسال (دقيقة بين المحاولات، 3 محاولات كحد أقصى بالساعة)
     * — بنفس آلية OtpPasswordResetController::sendOtp() تماماً.
     */
    private function generateAndSendVerificationOtp(User $user): void
    {
        $otp = rand(100000, 999999);

        DB::table('email_verification_otps')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        Mail::to($user->email)->send(new VerifyAccountMail($user, $otp));

        RateLimiter::hit('register-otp-delay:' . $user->email, 60);
        RateLimiter::hit('register-otp-max:' . $user->email, 3600);
    }

    /**
     * إعادة إرسال رمز تفعيل الحساب لمستخدم غير موثّق بريده بعد.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendVerificationOtp(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $email = $request->email;
        $user = User::where('email', $email)->first();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'هذا الحساب مفعّل بالفعل.'], 400);
        }

        $delayKey = 'register-otp-delay:' . $email;
        $maxKey = 'register-otp-max:' . $email;

        if (RateLimiter::tooManyAttempts($delayKey, 1)) {
            $seconds = RateLimiter::availableIn($delayKey);
            return response()->json([
                'message' => "يرجى الانتظار {$seconds} ثانية قبل طلب رمز جديد.",
            ], 429);
        }

        if (RateLimiter::tooManyAttempts($maxKey, 3)) {
            $seconds = RateLimiter::availableIn($maxKey);
            $minutes = ceil($seconds / 60);
            return response()->json([
                'message' => "لقد استنفدت جميع محاولاتك (3 محاولات). يرجى المحاولة مجدداً بعد {$minutes} دقيقة.",
            ], 429);
        }

        $this->generateAndSendVerificationOtp($user);

        $remaining = RateLimiter::remaining('register-otp-max:' . $email, 3);

        return response()->json([
            'message' => 'تم إرسال رمز تحقق جديد إلى بريدك الإلكتروني.',
            'remaining_attempts' => $remaining,
        ], 200);
    }

    /**
     * التحقق من رمز تفعيل الحساب المرسل عند التسجيل.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyRegistrationOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp'   => 'required|numeric|digits:6',
        ]);

        $record = DB::table('email_verification_otps')->where('email', $request->email)->first();

        if (!$record) {
            return response()->json(['message' => 'لم يتم طلب رمز تحقق لهذا البريد الإلكتروني.'], 400);
        }

        if (now()->parse($record->created_at)->addMinutes(15)->isPast()) {
            DB::table('email_verification_otps')->where('email', $request->email)->delete();
            return response()->json(['message' => 'انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد.'], 400);
        }

        if (!Hash::check($request->otp, $record->token)) {
            return response()->json(['message' => 'رمز التحقق غير صحيح.'], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->update(['email_verified_at' => now()]);

        DB::table('email_verification_otps')->where('email', $request->email)->delete();

        RateLimiter::clear('register-otp-delay:' . $request->email);
        RateLimiter::clear('register-otp-max:' . $request->email);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تفعيل الحساب بنجاح.',
            'user' => $user,
            //'access_token' => $token,
            //'token_type' => 'Bearer',
        ], 200);
    }

    /**
     * تسجيل دخول المستخدم.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'بيانات الاعتماد غير صحيحة.',
            ], 401);
        }

        $user = Auth::user();

        // لا يُسمح بتسجيل الدخول قبل تفعيل الحساب عبر رمز التحقق (OTP)
        if (!$user->email_verified_at) {
            Auth::logout();

            $delayKey = 'register-otp-delay:' . $user->email;

            // نرسل رمز تحقق جديد تلقائياً لتسهيل إكمال التفعيل، ما لم يكن هناك
            // رمز أُرسل مؤخراً (نفس قيود resendVerificationOtp لمنع الإزعاج)
            if (!RateLimiter::tooManyAttempts($delayKey, 1) && !RateLimiter::tooManyAttempts('register-otp-max:' . $user->email, 3)) {
                $this->generateAndSendVerificationOtp($user);
            }

            return response()->json([
                'message' => 'يجب تفعيل حسابك أولاً عبر رمز التحقق المُرسل إلى بريدك الإلكتروني.',
                'requires_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح.',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * تسجيل خروج المستخدم.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    /**
     * الحصول على بيانات المستخدم المصادق عليه.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('accountType', 'wallet'), // تحميل نوع الحساب والمحفظة
        ]);
    }
}
