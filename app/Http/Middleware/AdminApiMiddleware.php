<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware مخصص لطبقة Api\Admin\* (تستهلكها لوحة React الجديدة)،
 * منفصل عمداً عن AdminMiddleware القديم (تستخدمه المسارات الأخرى تحت
 * routes/api.php وroutes/admin.php). الفرق الوحيد: شكل استجابة الرفض
 * الموحّد {success,data,message} الذي تتوقعه React تحديداً، بدل
 * {message} المستخدم بالمسارات القديمة.
 */
class AdminApiMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isAdmin()) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'data' => null,
            'message' => 'Unauthorized',
        ], 403);
    }
}
