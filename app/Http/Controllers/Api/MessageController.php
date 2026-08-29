<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * رسائل حقيقية داخل التطبيق بين مستخدمين، بدل الاعتماد الكامل على
 * واتساب خارجي — خصوصاً مفيدة للتواصل حول مزاد أو حجز أو إعلان معيّن.
 * جدول `messages` كان موجوداً بقاعدة البيانات منذ البداية لكن بلا أي
 * controller أو routes يستخدمه فعلياً.
 */
class MessageController extends Controller
{
    /**
     * قائمة المحادثات: آخر رسالة مع كل طرف تواصلتَ معه، مرتبة الأحدث أولاً،
     * مع عدد الرسائل غير المقروءة من كل طرف.
     */
    public function conversations(Request $request)
    {
        $userId = $request->user()->id;

        // نجلب أحدث رسالة لكل "طرف آخر" تحدثتُ معه (بغض النظر عن اتجاه الرسالة)
        $partnerIds = Message::query()
            ->where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->get(['sender_id', 'receiver_id'])
            ->map(fn ($m) => $m->sender_id === $userId ? $m->receiver_id : $m->sender_id)
            ->unique()
            ->values();

        if ($partnerIds->isEmpty()) {
            return response()->json(['message' => 'لا توجد محادثات بعد.', 'conversations' => []]);
        }

        $conversations = $partnerIds->map(function ($partnerId) use ($userId) {
            $lastMessage = Message::betweenUsers($userId, $partnerId)
                ->latest('id')
                ->first();

            $unreadCount = Message::where('sender_id', $partnerId)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();

            $partner = User::select(['id', 'name', 'phone_number'])->find($partnerId);

            return [
                'partner_id' => $partnerId,
                'partner_name' => $partner?->name,
                'last_message' => $lastMessage?->content,
                'last_message_at' => $lastMessage?->created_at,
                'last_message_is_mine' => $lastMessage && (int) $lastMessage->sender_id === (int) $userId,
                'unread_count' => $unreadCount,
            ];
        })
        ->filter(fn ($c) => $c['last_message_at'] !== null)
        ->sortByDesc('last_message_at')
        ->values();

        return response()->json([
            'message' => 'تم جلب المحادثات بنجاح.',
            'conversations' => $conversations,
        ]);
    }

    /**
     * الرسائل بيني وبين مستخدم معيّن (paginated)، وتُعلَّم رسائله لي كمقروءة
     * تلقائياً فور عرضها.
     */
    public function thread(Request $request, $userId)
    {
        $me = $request->user();

        if ((int) $userId === (int) $me->id) {
            return response()->json(['message' => 'لا يمكنك مراسلة نفسك.'], 422);
        }

        $partner = User::find($userId);
        if (!$partner) {
            return response()->json(['message' => 'المستخدم غير موجود.'], 404);
        }

        $perPage = (int) $request->input('per_page', 30);

        $messages = Message::betweenUsers($me->id, (int) $userId)
            ->latest('id')
            ->paginate($perPage);

        // تعليم كل رسائل الطرف الآخر لي كمقروءة بمجرد فتح المحادثة
        Message::where('sender_id', $userId)
            ->where('receiver_id', $me->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'تم جلب الرسائل بنجاح.',
            'partner' => ['id' => $partner->id, 'name' => $partner->name],
            'messages' => $messages->items(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * إرسال رسالة جديدة لمستخدم آخر، مع إشعار فوري للمستقبِل.
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|integer|exists:users,id',
            'content' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sender = $request->user();

        if ((int) $request->receiver_id === (int) $sender->id) {
            return response()->json(['message' => 'لا يمكنك مراسلة نفسك.'], 422);
        }

        // ⬅️ جديد: منع الرسائل بين طرفين أحدهما حظر الآخر (بأي اتجاه)
        if (\App\Http\Controllers\Api\BlockController::existsBetween($sender->id, (int) $request->receiver_id)) {
            return response()->json([
                'message' => 'لا يمكن إرسال رسالة إلى هذا المستخدم.',
            ], 403);
        }

        // ⬅️ جديد: فحص محتوى أساسي (كلمات محظورة صريحة) قبل الحفظ
        if (!\App\Services\ProfanityFilterService::isClean($request->content)) {
            return response()->json([
                'message' => 'رسالتك تحتوي على كلمات غير مسموح بها.',
            ], 422);
        }

        $message = DB::transaction(function () use ($request, $sender) {
            $message = Message::create([
                'sender_id' => $sender->id,
                'receiver_id' => $request->receiver_id,
                'content' => trim($request->content),
                'is_read' => false,
            ]);

            NotificationService::send(
                (int) $request->receiver_id,
                'رسالة جديدة من ' . $sender->name,
                mb_strimwidth(trim($request->content), 0, 100, '...'),
                'new_message',
                [
                    'message_id' => $message->id,
                    'sender_id' => $sender->id,
                ]
            );

            return $message;
        });

        return response()->json([
            'message' => '✅ تم إرسال الرسالة.',
            'data' => $message,
        ], 201);
    }

    /**
     * عدد كل الرسائل غير المقروءة (لعرضه كشارة برأس التطبيق، بنفس أسلوب
     * شارة الإشعارات).
     */
    public function unreadCount(Request $request)
    {
        $count = Message::where('receiver_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}
