<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Services\AuctionDepositService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * وحدة Auctions من Admin API — خلافاً لـ Ads/RentalAds، لا يوجد أي مسار
 * JSON قديم لـ admin/auctions بـ routes/api.php إطلاقاً، فبُنيت الأربعة
 * endpoints كاملة (index/show/update/destroy) بلا نطاق مُقلَّص.
 *
 * منطق update/destroy منسوخ حرفياً من الأصل (بما فيه
 * AuctionDepositService::releaseAllHeldDeposits عند الإلغاء/الحذف،
 * وNotificationService::send لصاحب المزاد)، مع استثناء واحد واعٍ: منع
 * إعادة فتح مزاد closed (closed→active) كان يرمي RuntimeException غير
 * مُلتقَط بالأصل (كان سينتج 500 غير موحّد) — استُبدل هنا بـ
 * ValidationException تُنتج 422 موحّد errors.status، بنفس النص العربي
 * الأصلي، مطابقاً لنمط ensureTransitionAllowed بوحدتي Ads/RentalAds.
 */
class AuctionController extends Controller
{
    private const PER_PAGE = 20;

    public function index()
    {
        $paginator = Auction::with(['car', 'user'])->paginate(self::PER_PAGE);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'message' => 'تم جلب المزادات بنجاح.',
        ]);
    }

    public function show(Auction $auction)
    {
        $auction->load('car.user', 'user', 'bids.user');

        return response()->json([
            'success' => true,
            'data' => $auction,
            'message' => 'تم جلب تفاصيل المزاد بنجاح.',
        ]);
    }

    public function update(Request $request, Auction $auction)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', Rule::in(['pending', 'active', 'cancelled'])],
            'starting_price' => 'required|numeric|min:0.01',
            'current_price' => 'nullable|numeric|min:0.01|gte:starting_price',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldStatus = $auction->status;

        try {
            DB::transaction(function () use ($request, $auction) {
                $auction = Auction::query()->lockForUpdate()->findOrFail($auction->id);

                if ($request->status === 'cancelled' && $auction->status !== 'cancelled') {
                    AuctionDepositService::releaseAllHeldDeposits(
                        $auction,
                        'إعادة العربون بسبب إلغاء المزاد من الإدارة'
                    );
                }

                // ⬅️ استبدال RuntimeException غير الملتقَطة بـ ValidationException موحّدة
                if ($request->status === 'active' && $auction->status === 'closed') {
                    throw ValidationException::withMessages([
                        'status' => ['لا يمكن إعادة فتح مزاد مغلق من شاشة التعديل.'],
                    ]);
                }

                $startTime = Carbon::parse($request->start_time);
                $endTime = Carbon::parse($request->end_time);

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
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

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

        \App\Models\AdminAuditLog::record($request->user(), 'auction.update', $auction, 'تحديث مزاد', ['new_status' => $request->status]);

        return response()->json([
            'success' => true,
            'data' => $auction->fresh(['car', 'user']),
            'message' => 'تم تحديث المزاد بنجاح.',
        ]);
    }

    public function destroy(Request $request, Auction $auction)
    {
        if ($auction->final_price_status === 'accepted') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'لا يمكن حذف مزاد توجد له صفقة مقبولة وعربون قيد التسوية.',
            ], 422);
        }

        $auctionId = $auction->id;

        DB::transaction(function () use ($auction) {
            AuctionDepositService::releaseAllHeldDeposits(
                $auction,
                'إعادة العربون بسبب حذف المزاد من الإدارة'
            );

            $auction->delete();
        }, 5);

        \App\Models\AdminAuditLog::record($request->user(), 'auction.destroy', null, 'حذف مزاد', ['auction_id' => $auctionId]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'تم حذف المزاد وإعادة أي عربون محجوز.',
        ]);
    }
}
