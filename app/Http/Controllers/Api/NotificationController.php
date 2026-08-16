<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = UserNotification::where('user_id', $user->id)
            ->latest();

        if ($request->query('status') === 'unread') {
            $query->whereNull('read_at');
        }

        if ($request->query('status') === 'read') {
            $query->whereNotNull('read_at');
        } 

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        return response()->json([
            'message' => 'تم جلب الإشعارات بنجاح.',
            'unread_count' => UserNotification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),
            'notifications' => $query->paginate(15),
        ]);
    }

    public function show(Request $request, $id)
    {
        $notification = UserNotification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'message' => 'الإشعار غير موجود.'
            ], 404);
        }

        if (!$notification->read_at) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'تم جلب تفاصيل الإشعار بنجاح.',
            'notification' => $notification->fresh(),
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = UserNotification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'message' => 'الإشعار غير موجود.'
            ], 404);
        }

        $notification->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم تعليم الإشعار كمقروء.',
            'notification' => $notification,
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'تم تعليم كل الإشعارات كمقروءة.'
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $notification = UserNotification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'message' => 'الإشعار غير موجود.'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => 'تم حذف الإشعار بنجاح.'
        ]);
    }
}