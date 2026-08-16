<?php

namespace App\Services;

use App\Models\Auction;
use Illuminate\Support\Facades\DB;

class AuctionClosingService
{
    public static function closeExpiredAuction(Auction $auction): Auction
    {
        return DB::transaction(function () use ($auction) {
            $auction = Auction::where('id', $auction->id)
                ->lockForUpdate()
                ->first();

            if (!$auction) {
                throw new \Exception('المزاد غير موجود.');
            }

            if ($auction->status !== 'active') {
                throw new \Exception('لا يمكن إغلاق مزاد غير نشط.');
            }

            if (now()->isBefore($auction->end_time)) {
                throw new \Exception('وقت المزاد لم ينته بعد.');
            }

            // لا نعتمد إلا مزايدة لها عربون محجوز فعلياً.
            $highestBid = $auction->bids()
                ->where('is_deposit_held', true)
                ->orderByDesc('amount')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if ($highestBid) {
                $auction->status = 'closed';
                $auction->winner_id = $highestBid->user_id;
                $auction->current_price = $highestBid->amount;
                $auction->final_price_status = 'pending';
                $auction->final_price_decided_at = null;
                $auction->closed_at = now();
                $auction->closed_reason = 'expired';
                $auction->save();

                NotificationService::send(
                    $auction->user_id,
                    'انتهى المزاد الخاص بك',
                    'تم إغلاق المزاد تلقائيًا وتحديد أعلى مزايد. يرجى قبول أو رفض السعر النهائي.',
                    'auction_closed_waiting_owner_decision',
                    [
                        'auction_id' => $auction->id,
                        'winner_id' => $auction->winner_id,
                        'current_price' => $auction->current_price,
                    ]
                );

                NotificationService::send(
                    $auction->winner_id,
                    'أنت أعلى مزايد',
                    'انتهى المزاد وأنت صاحب أعلى مزايدة. بانتظار موافقة صاحب المزاد على السعر النهائي.',
                    'auction_highest_bidder',
                    [
                        'auction_id' => $auction->id,
                        'current_price' => $auction->current_price,
                    ]
                );
            } else {
                $auction->status = 'closed';
                $auction->winner_id = null;
                $auction->final_price_status = null;
                $auction->final_price_decided_at = null;
                $auction->closed_at = now();
                $auction->closed_reason = $auction->bids()->exists()
                    ? 'no_secured_bids'
                    : 'no_bids';
                $auction->save();

                NotificationService::send(
                    $auction->user_id,
                    $auction->closed_reason === 'no_secured_bids'
                        ? 'انتهى المزاد بدون مزايدة مضمونة'
                        : 'انتهى المزاد بدون مزايدات',
                    $auction->closed_reason === 'no_secured_bids'
                        ? 'تم إغلاق المزاد لأن المزايدات الموجودة لا تملك عربوناً محجوزاً صالحاً.'
                        : 'تم إغلاق المزاد تلقائيًا لأن الوقت انتهى ولم يتم تقديم أي مزايدة.',
                    'auction_closed_no_bids',
                    [
                        'auction_id' => $auction->id,
                    ]
                );
            }

            return $auction->fresh(['car', 'user', 'winner', 'bids']);
        });
    }
}
