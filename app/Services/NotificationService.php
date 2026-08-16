<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;

class NotificationService
{
    public static function send(
        int $userId,
        string $title,
        ?string $body = null,
        string $type = 'general',
        array $data = []
    ): UserNotification {
        $notification = UserNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data,
            'read_at' => null,
        ]);

        // إرسال push فعلي عبر FCM بعد حفظ الإشعار بقاعدة البيانات مباشرة.
        // هذا الاستدعاء لا يفشل العملية أبداً حتى لو لم يكن Firebase
        // مهيأً أو لم يكن للمستخدم أي جهاز مسجّل (راجع PushNotificationService).
        $user = User::find($userId);
        if ($user) {
            PushNotificationService::sendToUser($user, $title, $body, $type, $data);
        }

        return $notification;
    }
}
