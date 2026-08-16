<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // $request->user() يستخدم الـ guard المحدد فعلياً بالراوت (sanctum لطلبات الـ API)
        // بدلاً من Auth::check()/Auth::user() اللي بيفحصوا الـ guard الافتراضي (web/session)
        $user = $request->user();

        if ($user && $user->isAdmin()) {
            return $next($request);
        }

        // رجّع JSON بدل redirect، لأن هاد راوت API مو صفحة ويب
        return response()->json([
            'message' => 'ليس لديك صلاحية الوصول لهذا المسار (Admin only).',
        ], 403);
    }
}
