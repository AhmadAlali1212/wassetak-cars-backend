<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        /*
         * ⚠️ إصلاح حرج: بدون هذا، أي ValidationException تُرمى عبر
         * throw ValidationException::withMessages([...]) على مسار API كان يُعامَل حسب
         * $request->expectsJson() فقط — وهذا يعتمد على Accept header الذي لا يرسله
         * Postman افتراضياً. النتيجة: بدل استجابة JSON 422 بالأخطاء، كان Laravel
         * يعمل redirect()->back()، وبما أنه لا يوجد Referer من Postman، كان
         * ينتهي به الأمر لعرض صفحة الترحيب الافتراضية (welcome.blade.php) بدل خطأ واضح.
         *
         * الحل: أي طلب يبدأ مساره بـ api/ يحصل دائماً على استجابة JSON صحيحة،
         * بصرف النظر عن الـ Accept header المُرسَل من العميل.
         */
        $this->renderable(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'البيانات المُرسَلة غير صالحة.',
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });

        /*
         * نفس المبدأ لأي استثناء آخر غير متوقع على مسارات API (مثل ModelNotFoundException
         * أو أي Throwable عام) — بدل ما يرجع صفحة HTML (Whoops/الترحيب)، يرجع JSON دائماً.
         */
        $this->renderable(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null; // اترك السلوك الافتراضي لطلبات الويب (لوحة الأدمن Blade)
            }

            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json(['message' => 'غير مصرح لك، يرجى تسجيل الدخول.'], 401);
            }

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $e instanceof \Symfony\Component\Routing\Exception\ResourceNotFoundException
                || $e instanceof \Illuminate\Routing\Exceptions\UrlGenerationException) {
                return response()->json(['message' => 'العنصر المطلوب غير موجود.'], 404);
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                return response()->json(['message' => 'المسار غير موجود.'], 404);
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                return response()->json(['message' => 'طريقة الطلب (Method) غير مسموحة لهذا المسار.'], 405);
            }

            return null; // أي استثناء آخر يكمل للمعالجة الافتراضية (تُسجَّل بالـ log على الأقل)
        });
    }
}
