<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BlockController extends Controller
{
    /**
     * حظر مستخدم — يمنع أي رسائل جديدة بينكما بالاتجاهين (يُفحص عند
     * الإرسال بـ MessageController)، بدون حذف المحادثة القديمة.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'blocked_user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if ((int) $request->blocked_user_id === (int) $user->id) {
            return response()->json(['message' => 'لا يمكنك حظر نفسك.'], 422);
        }

        $block = Block::firstOrCreate([
            'blocker_id' => $user->id,
            'blocked_user_id' => $request->blocked_user_id,
        ]);

        return response()->json([
            'message' => '✅ تم حظر هذا المستخدم.',
            'block' => $block,
        ], 201);
    }

    public function destroy(Request $request, $blockedUserId)
    {
        $deleted = Block::where('blocker_id', $request->user()->id)
            ->where('blocked_user_id', $blockedUserId)
            ->delete();

        return response()->json([
            'message' => $deleted ? '✅ تم إلغاء الحظر.' : 'هذا المستخدم غير محظور أصلاً.',
        ]);
    }

    /** قائمة من حظرتهم. */
    public function index(Request $request)
    {
        $blocks = Block::with('blockedUser:id,name,phone_number')
            ->where('blocker_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'message' => 'تم جلب قائمة المحظورين بنجاح.',
            'blocks' => $blocks,
        ]);
    }

    /**
     * هل يوجد حظر بين المستخدم الحالي ومستخدم آخر (بأي اتجاه)؟ يستخدمها
     * الفرونت لإخفاء زر "مراسلة"/"إلغاء الحظر" حسب الحالة الصحيحة.
     */
    public static function existsBetween(int $userA, int $userB): bool
    {
        return Block::where(function ($q) use ($userA, $userB) {
            $q->where('blocker_id', $userA)->where('blocked_user_id', $userB);
        })->orWhere(function ($q) use ($userA, $userB) {
            $q->where('blocker_id', $userB)->where('blocked_user_id', $userA);
        })->exists();
    }
}
