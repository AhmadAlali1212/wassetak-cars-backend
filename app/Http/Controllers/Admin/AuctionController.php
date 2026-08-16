<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Auction;
use App\Models\Car; // تأكد من استيراد نموذج السيارة
use App\Models\User; // تأكد من استيراد نموذج المستخدم (البائع)
use Illuminate\Validation\Rule;
use App\Services\NotificationService;
use App\Services\AuctionDepositService;
use Illuminate\Support\Facades\DB;
use App\Support\RejectionReasonHelper;

class AuctionController extends Controller
{
    /**
     * Display a listing of the auctions.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Fetch auctions with their associated car and user (seller)
        $auctions = Auction::with(['car', 'user'])->paginate(10);
        return view('admin.auctions.index', compact('auctions'));
    }

    /**
     * Display the specified auction.
     *
     * @param  \App\Models\Auction  $auction
     * @return \Illuminate\View\View
     */
    public function show(Auction $auction)
    {
        // Load relationships for detailed view, including bids
        $auction->load('car.user', 'user', 'bids.user'); // Load car, seller, and bids with their users
        return view('admin.auctions.show', compact('auction'));
    }

    /**
     * Show the form for editing the specified auction.
     *
     * @param  \App\Models\Auction  $auction
     * @return \Illuminate\View\View
     */
    public function edit(Auction $auction)
    {
        $statuses = ['pending', 'active', 'cancelled'];

        return view('admin.auctions.edit', compact('auction', 'statuses'));
    }

    /**
     * Update the specified auction in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Auction  $auction
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Auction $auction)
    {
        $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'active', 'cancelled'])],
            'starting_price' => 'required|numeric|min:0.01',
            'current_price' => 'nullable|numeric|min:0.01|gte:starting_price',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $oldStatus = $auction->status;

        DB::transaction(function () use ($request, $auction) {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auction->id);

            if ($request->status === 'cancelled' && $auction->status !== 'cancelled') {
                AuctionDepositService::releaseAllHeldDeposits(
                    $auction,
                    'إعادة العربون بسبب إلغاء المزاد من الإدارة'
                );
            }

            if ($request->status === 'active' && $auction->status === 'closed') {
                throw new \RuntimeException('لا يمكن إعادة فتح مزاد مغلق من شاشة التعديل.');
            }

            $startTime = \Illuminate\Support\Carbon::parse($request->start_time);
            $endTime = \Illuminate\Support\Carbon::parse($request->end_time);

            // عند أول تفعيل للمزاد تبدأ المدة من لحظة موافقة الإدارة،
            // بدلاً من احتساب الوقت الذي قضاه المزاد بحالة pending.
            if ($request->status === 'active' && $auction->status === 'pending') {
                $durationSeconds = max(3600, $startTime->diffInSeconds($endTime));
                $startTime = now();
                $endTime = $startTime->copy()->addSeconds($durationSeconds);
            }

            $auction->update([
                'status' => $request->status,
                'starting_price' => $request->starting_price,
                'current_price' => $request->current_price ?: $request->starting_price,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'winner_id' => $request->status === 'cancelled' ? null : $auction->winner_id,
                'final_price_status' => $request->status === 'cancelled' ? null : $auction->final_price_status,
                'final_price_decided_at' => $request->status === 'cancelled' ? null : $auction->final_price_decided_at,
                'settlement_completed_at' => $request->status === 'cancelled' ? null : $auction->settlement_completed_at,
                'settlement_deadline_at' => $request->status === 'cancelled' ? null : $auction->settlement_deadline_at,
                'winner_withdrew_at' => $request->status === 'cancelled' ? null : $auction->winner_withdrew_at,
                'closed_at' => $request->status === 'cancelled' ? null : $auction->closed_at,
                'closed_reason' => $request->status === 'cancelled' ? null : $auction->closed_reason,
            ]);
        }, 5);

        if ($request->status === 'cancelled' && $oldStatus !== 'cancelled') {
            NotificationService::send(
                $auction->user_id,
                'تم إلغاء المزاد',
                'قامت الإدارة بإلغاء المزاد وإعادة أي عربون محجوز.',
                'auction_cancelled_by_admin',
                ['auction_id' => $auction->id]
            );
        }

        if ($request->status === 'active' && $oldStatus !== 'active') {
            NotificationService::send(
                $auction->user_id,
                'تم قبول المزاد',
                'تمت الموافقة على مزاد السيارة وأصبح فعالاً.',
                'auction_approved',
                ['auction_id' => $auction->id]
            );
        }

        return redirect()
            ->route('admin.auctions.index')
            ->with('success', 'تم تحديث المزاد بنجاح.');
    }

    // public function update(Request $request, Auction $auction)
    // {
    //     $request->validate([
    //         'status' => ['required', 'string', Rule::in(['pending', 'active', 'closed', 'cancelled'])],
    //         'starting_price' => 'required|numeric|min:0',
    //         'current_price' => 'nullable|numeric|min:0|gte:starting_price',
    //         'start_time' => 'required|date',
    //         'end_time' => 'required|date|after:start_time',
    //         'winner_id' => 'nullable|exists:users,id',
    //     ]);

    //     // حفظ حالة المزاد والفائز قبل التعديل
    //     $oldStatus = $auction->status;
    //     $oldWinnerId = $auction->winner_id;

    //     // تحديث المزاد
    //     $auction->update([
    //         'status' => $request->status,
    //         'starting_price' => $request->starting_price,
    //         'current_price' => $request->current_price,
    //         'start_time' => $request->start_time,
    //         'end_time' => $request->end_time,
    //         'winner_id' => $request->winner_id,
    //     ]);

    //     // إرسال إشعارات فقط إذا تغيّرت الحالة أو تغيّر الفائز
    //     if ($oldStatus !== $auction->status || $oldWinnerId != $auction->winner_id) {

    //         if ($auction->status === 'active' && $oldStatus !== 'active') {
    //             NotificationService::send(
    //                 $auction->user_id,
    //                 'تم قبول المزاد',
    //                 'تمت الموافقة على مزاد السيارة الخاص بك وأصبح فعالاً.',
    //                 'auction_approved',
    //                 [
    //                     'auction_id' => $auction->id,
    //                 ]
    //             );
    //         }

    //         if ($auction->status === 'cancelled' && $oldStatus !== 'cancelled') {
    //             NotificationService::send(
    //                 $auction->user_id,
    //                 'تم إلغاء المزاد',
    //                 'قامت الإدارة بإلغاء مزاد السيارة الخاص بك.',
    //                 'auction_cancelled',
    //                 [
    //                     'auction_id' => $auction->id,
    //                 ]
    //             );
    //         }

    //         if ($auction->status === 'closed' && $oldStatus !== 'closed') {
    //             NotificationService::send(
    //                 $auction->user_id,
    //                 'تم إغلاق المزاد',
    //                 'تم إغلاق المزاد الخاص بك.',
    //                 'auction_closed',
    //                 [
    //                     'auction_id' => $auction->id,
    //                     'winner_id' => $auction->winner_id,
    //                     'current_price' => $auction->current_price,
    //                 ]
    //             );
    //         }

    //         if (
    //             $auction->status === 'closed'
    //             && $auction->winner_id
    //             && $oldWinnerId != $auction->winner_id
    //         ) {
    //             NotificationService::send(
    //                 $auction->winner_id,
    //                 'مبروك، لقد فزت بالمزاد',
    //                 'تم تحديدك كفائز في أحد المزادات.',
    //                 'auction_won',
    //                 [
    //                     'auction_id' => $auction->id,
    //                     'current_price' => $auction->current_price,
    //                 ]
    //             );
    //         }
    //     }

    //     return redirect()
    //         ->route('admin.auctions.index')
    //         ->with('success', 'تم تحديث المزاد بنجاح.');
    // }
    /**
     * Remove the specified auction from storage.
     *
     * @param  \App\Models\Auction  $auction
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Auction $auction)
    {
        if ($auction->final_price_status === 'accepted') {
            return redirect()
                ->route('admin.auctions.index')
                ->with('error', 'لا يمكن حذف مزاد توجد له صفقة مقبولة وعربون قيد التسوية.');
        }

        DB::transaction(function () use ($auction) {
            AuctionDepositService::releaseAllHeldDeposits(
                $auction,
                'إعادة العربون بسبب حذف المزاد من الإدارة'
            );

            $auction->delete();
        }, 5);

        return redirect()
            ->route('admin.auctions.index')
            ->with('success', 'تم حذف المزاد وإعادة أي عربون محجوز.');
    }

    /**
     * ============================================================
     *  نقاط نهاية JSON الخاصة بلوحة تحكم الأدمن (تُستخدم عبر الـ API).
     *  (بخلاف الدوال أعلاه المخصصة لواجهة الويب Blade).
     * ============================================================
     */

    /**
     * عرض المزادات المنتظرة موافقة الإدارة (API).
     */
    public function pendingAuctions()
    {
        $auctions = Auction::where('status', 'pending')
            ->with(['car', 'user'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'قائمة المزادات المنتظرة الموافقة',
            'auctions' => $auctions,
        ]);
    }

    /**
     * عرض المزادات النشطة/المقبولة حالياً (API).
     */
    public function activeAuctions()
    {
        $auctions = Auction::where('status', 'active')
            ->with(['car', 'user', 'winner'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'قائمة المزادات النشطة',
            'auctions' => $auctions,
        ]);
    }

    /**
     * عرض المزادات المغلقة (انتهت مدتها) (API).
     */
    public function closedAuctions()
    {
        $auctions = Auction::where('status', 'closed')
            ->with(['car', 'user', 'winner'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'قائمة المزادات المغلقة',
            'auctions' => $auctions,
        ]);
    }

    /**
     * عرض المزادات الملغاة/المرفوضة من الإدارة (API).
     */
    public function cancelledAuctions()
    {
        $auctions = Auction::where('status', 'cancelled')
            ->with(['car', 'user'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'قائمة المزادات الملغاة/المرفوضة',
            'auctions' => $auctions,
        ]);
    }

    /**
     * عرض تفاصيل مزاد محدد للأدمن (أي حالة)، مع السيارة والبائع والمزايدات (API).
     */
    public function showAuction($id)
    {
        $auction = Auction::with(['car.user', 'user', 'winner', 'bids.user'])->find($id);

        if (!$auction) {
            return response()->json(['message' => 'المزاد غير موجود.'], 404);
        }

        return response()->json([
            'message' => 'تفاصيل المزاد',
            'auction' => $auction,
        ]);
    }

    /**
     * موافقة الأدمن على مزاد منتظر (pending -> active) (API).
     * تُعاد جدولة بداية/نهاية المزاد من لحظة الموافقة، بنفس المدة الأصلية
     * التي طلبها البائع، حتى لا تُهدَر مدة المزاد أثناء انتظار المراجعة.
     */
    public function approveAuction(Request $request, $id)
    {
        try {
            $auction = DB::transaction(function () use ($id) {
                $auction = Auction::query()->with('car')->lockForUpdate()->find($id);

                if (!$auction) {
                    throw new \RuntimeException('المزاد غير موجود.');
                }

                if ($auction->status !== 'pending') {
                    throw new \RuntimeException('لا يمكن الموافقة إلا على مزاد بانتظار المراجعة.');
                }

                if (!$auction->car || $auction->car->approval_status !== 'approved') {
                    throw new \RuntimeException('لا يمكن الموافقة على المزاد قبل اعتماد السيارة المرتبطة به.');
                }

                $durationSeconds = max(3600, $auction->start_time->diffInSeconds($auction->end_time));
                $startTime = now();
                $endTime = $startTime->copy()->addSeconds($durationSeconds);

                $auction->update([
                    'status' => 'active',
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]);

                return $auction;
            }, 5);

            NotificationService::send(
                $auction->user_id,
                'تم قبول المزاد',
                'تمت الموافقة على مزاد السيارة الخاص بك وأصبح فعالاً.',
                'auction_approved',
                ['auction_id' => $auction->id]
            );

            return response()->json([
                'message' => 'تمت الموافقة على المزاد بنجاح.',
                'auction' => $auction->fresh(['car', 'user']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * رفض الأدمن لمزاد منتظر (pending -> cancelled) مع سبب رفض (API).
     */
    public function rejectAuction(Request $request, $id)
    {
        $reason = RejectionReasonHelper::validateAndResolve($request, 'auction');

        try {
            $auction = DB::transaction(function () use ($id, $reason) {
                $auction = Auction::query()->lockForUpdate()->find($id);

                if (!$auction) {
                    throw new \RuntimeException('المزاد غير موجود.');
                }

                if ($auction->status !== 'pending') {
                    throw new \RuntimeException('لا يمكن رفض إلا مزاد بانتظار المراجعة.');
                }

                AuctionDepositService::releaseAllHeldDeposits(
                    $auction,
                    'إعادة العربون بسبب رفض المزاد من الإدارة'
                );

                $auction->update([
                    'status' => 'cancelled',
                    'closed_reason' => $reason,
                    'closed_at' => now(),
                ]);

                return $auction;
            }, 5);

            NotificationService::send(
                $auction->user_id,
                'تم رفض المزاد',
                'رفضت الإدارة مزاد السيارة الخاص بك للسبب: ' . $reason,
                'auction_rejected',
                ['auction_id' => $auction->id, 'reason' => $reason]
            );

            return response()->json([
                'message' => 'تم رفض المزاد بنجاح.',
                'auction' => $auction->fresh(['car', 'user']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * إرجاع قائمة أسباب رفض المزاد الجاهزة (لعرضها كقائمة اختيار في لوحة الأدمن).
     */
    public function rejectionReasons()
    {
        return response()->json([
            'reasons' => RejectionReasonHelper::list('auction'),
        ]);
    }
}
