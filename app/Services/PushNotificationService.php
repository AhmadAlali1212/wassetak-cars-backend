<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    // يرسل إشعار FCM فعلي (يظهر حتى لو التطبيق مغلق) لكل أجهزة المستخدم
    // المسجّلة. لا يرمي استثناء للخارج أبداً — فشل الإرسال الفوري لا يجب
    // أن يوقف إنشاء الإشعار بقاعدة البيانات (الذي يبقى دائماً يعمل حتى
    // لو Firebase غير مهيأ أو الخدمة متعطلة).
    public static function sendToUser(
        User $user,
        string $title,
        ?string $body,
        string $type,
        array $data = []
    ): void {
        try {
            $tokens = $user->deviceTokens()->pluck('token', 'id');
            if ($tokens->isEmpty()) {
                return;
            }

            $credentialsPath = config('services.firebase.credentials_path');
            if (!$credentialsPath || !file_exists($credentialsPath)) {
                Log::warning('FCM: ملف اعتماد Firebase غير موجود، تم تجاهل إرسال الإشعار.');
                return;
            }

            $messaging = (new Factory())
                ->withServiceAccount($credentialsPath)
                ->createMessaging();

            // بيانات إضافية (data payload) يجب أن تكون كلها نصوصاً حسب
            // متطلبات FCM، لذلك نحوّل كل القيم لنصوص هنا.
            $stringData = array_map(
                fn ($value) => is_scalar($value) ? (string) $value : json_encode($value),
                $data
            );
            $stringData['type'] = $type;

            $message = CloudMessage::new()
                ->withNotification(FirebaseNotification::create($title, $body ?? ''))
                ->withData($stringData);

            /** @var MulticastSendReport $report */
            $report = $messaging->sendMulticast($message, $tokens->values()->all());

            // تنظيف الرموز غير الصالحة (تطبيق أُزيل من الجهاز، رمز منتهي...)
            // حتى لا نستمر بمحاولة الإرسال لها في كل مرة.
            if ($report->hasFailures()) {
                $invalidTokens = [];
                foreach ($report->invalidTokens() as $invalidToken) {
                    $invalidTokens[] = $invalidToken;
                }
                if (!empty($invalidTokens)) {
                    DeviceToken::where('user_id', $user->id)
                        ->whereIn('token', $invalidTokens)
                        ->delete();
                }
            }
        } catch (\Throwable $e) {
            // فشل صامت مسجَّل باللوق — إرسال الـ push هو تحسين إضافي فوق
            // الإشعار المخزَّن أصلاً بقاعدة البيانات، وليس شرطاً لنجاح
            // العملية الأساسية (مثل قبول مزايدة أو اعتماد إعلان).
            Log::error('FCM send failed: ' . $e->getMessage());
        }
    }
}
