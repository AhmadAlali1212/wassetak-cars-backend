<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceTokenController extends Controller
{
    // يُستدعى من التطبيق بعد تسجيل الدخول وفي كل مرة يتغيّر فيها رمز
    // FCM (Firebase قد يجدّده تلقائياً بين الحين والآخر). لأن العمود
    // token فريد (unique)، لو كان نفس الرمز مسجلاً سابقاً لمستخدم آخر
    // (جهاز تمت إعادة استخدامه بحساب مختلف) ننقل ملكيته للمستخدم الحالي
    // بدل رفض الطلب.
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:255',
            'platform' => 'nullable|string|in:android,ios,web',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صالحة.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $request->input('token')],
            [
                'user_id' => $request->user()->id,
                'platform' => $request->input('platform'),
            ]
        );

        return response()->json([
            'message' => 'تم تسجيل جهاز الإشعارات بنجاح.',
            'device_token' => $deviceToken,
        ]);
    }

    // يُستدعى عند تسجيل الخروج حتى لا يستمر الجهاز باستقبال إشعارات
    // لحساب لم يعد مسجلاً دخوله عليه.
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صالحة.',
                'errors' => $validator->errors(),
            ], 422);
        }

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $request->input('token'))
            ->delete();

        return response()->json([
            'message' => 'تم إلغاء تسجيل جهاز الإشعارات.',
        ]);
    }
}
