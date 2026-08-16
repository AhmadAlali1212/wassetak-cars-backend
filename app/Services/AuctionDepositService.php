<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AuctionDepositService
{
    public static function depositFor(float $bidAmount): float
    {
        $rate = (float) config('services.auction.deposit_rate', 0.05);

        if ($rate <= 0 || $rate > 1) {
            throw new RuntimeException('نسبة عربون المزاد غير صحيحة في الإعدادات.');
        }

        return round($bidAmount * $rate, 2);
    }

    public static function placeBid(int $auctionId, User $bidder, float $amount): Bid
    {
        return DB::transaction(function () use ($auctionId, $bidder, $amount) {
            $auction = Auction::query()->lockForUpdate()->find($auctionId);

            if (!$auction) {
                throw new RuntimeException('المزاد غير موجود.');
            }

            if (
                $auction->status !== 'active'
                || now()->lessThan($auction->start_time)
                || now()->greaterThanOrEqualTo($auction->end_time)
            ) {
                throw new RuntimeException('المزاد غير نشط حالياً أو لم يبدأ بعد أو انتهى.');
            }

            if ($auction->user_id === $bidder->id) {
                throw new RuntimeException('لا يمكنك المزايدة على مزادك الخاص.');
            }

            if ($amount <= (float) $auction->current_price) {
                throw new RuntimeException('يجب أن تكون المزايدة أعلى من السعر الحالي.');
            }

            $previousBid = $auction->bids()
                ->where('is_deposit_held', true)
                ->orderByDesc('amount')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $userIds = collect([$bidder->id, $previousBid?->user_id])
                ->filter()
                ->unique()
                ->sort()
                ->values();

            self::ensureWalletsExist($userIds);
            $wallets = self::lockWallets($userIds);

            $bidderWallet = $wallets->get($bidder->id);
            self::assertWalletUsable($bidderWallet);

            $newDeposit = self::depositFor($amount);

            if ($previousBid && $previousBid->user_id === $bidder->id) {
                $oldDeposit = (float) $previousBid->deposit_amount;
                $additionalDeposit = round(max(0, $newDeposit - $oldDeposit), 2);

                if ((float) $bidderWallet->balance < $additionalDeposit) {
                    throw new RuntimeException(
                        'رصيدك المتاح غير كافٍ لزيادة العربون. المطلوب إضافياً: '
                        . number_format($additionalDeposit, 2, '.', '')
                    );
                }

                $bidderWallet->balance = round((float) $bidderWallet->balance - $additionalDeposit, 2);
                $bidderWallet->held_balance = round((float) $bidderWallet->held_balance + $additionalDeposit, 2);
                $bidderWallet->save();

                $previousBid->is_deposit_held = false;
                $previousBid->save();

                if ($additionalDeposit > 0) {
                    self::recordTransaction(
                        $bidder->id,
                        'auction_deposit_adjustment',
                        -$additionalDeposit,
                        'زيادة عربون المزايدة على المزاد رقم ' . $auction->id,
                        $auction
                    );
                }
            } else {
                if ((float) $bidderWallet->balance < $newDeposit) {
                    throw new RuntimeException(
                        'رصيدك المتاح غير كافٍ لحجز عربون المزايدة. المطلوب: '
                        . number_format($newDeposit, 2, '.', '')
                    );
                }

                $bidderWallet->balance = round((float) $bidderWallet->balance - $newDeposit, 2);
                $bidderWallet->held_balance = round((float) $bidderWallet->held_balance + $newDeposit, 2);
                $bidderWallet->save();

                self::recordTransaction(
                    $bidder->id,
                    'auction_deposit_hold',
                    -$newDeposit,
                    'حجز عربون مزايدة على المزاد رقم ' . $auction->id,
                    $auction
                );

                if ($previousBid) {
                    $previousWallet = $wallets->get($previousBid->user_id);
                    self::releaseLockedBid(
                        $previousBid,
                        $previousWallet,
                        $auction,
                        'إعادة العربون بعد تجاوز المزايدة'
                    );
                }
            }

            $auction->current_price = $amount;
            $auction->save();

            return Bid::create([
                'auction_id' => $auction->id,
                'user_id' => $bidder->id,
                'amount' => $amount,
                'deposit_amount' => $newDeposit,
                'is_deposit_held' => true,
            ]);
        }, 5);
    }

    public static function releaseWinnerDeposit(Auction $auction, string $reason): ?Bid
    {
        return DB::transaction(function () use ($auction, $reason) {
            $lockedAuction = Auction::query()->lockForUpdate()->find($auction->id);

            if (!$lockedAuction || !$lockedAuction->winner_id) {
                return null;
            }

            $bid = self::winnerHeldBid($lockedAuction);

            if (!$bid) {
                return null;
            }

            self::ensureWalletsExist(collect([$bid->user_id]));
            $wallet = self::lockWallets(collect([$bid->user_id]))->get($bid->user_id);
            self::releaseLockedBid($bid, $wallet, $lockedAuction, $reason);

            return $bid->fresh();
        }, 5);
    }

    public static function forfeitWinnerDeposit(Auction $auction, string $reason): ?Bid
    {
        return DB::transaction(function () use ($auction, $reason) {
            $lockedAuction = Auction::query()->lockForUpdate()->find($auction->id);

            if (!$lockedAuction || !$lockedAuction->winner_id) {
                return null;
            }

            $bid = self::winnerHeldBid($lockedAuction);

            if (!$bid) {
                return null;
            }

            $userIds = collect([$bid->user_id, $lockedAuction->user_id])->unique()->sort()->values();
            self::ensureWalletsExist($userIds);
            $wallets = self::lockWallets($userIds);

            $winnerWallet = $wallets->get($bid->user_id);
            $sellerWallet = $wallets->get($lockedAuction->user_id);
            $deposit = (float) $bid->deposit_amount;

            if ((float) $winnerWallet->held_balance + 0.001 < $deposit) {
                throw new RuntimeException('رصيد العربون المحجوز غير متطابق. أوقف العملية وراجع الإدارة.');
            }

            $winnerWallet->held_balance = round((float) $winnerWallet->held_balance - $deposit, 2);
            $winnerWallet->save();

            $sellerWallet->balance = round((float) $sellerWallet->balance + $deposit, 2);
            $sellerWallet->save();

            $bid->is_deposit_held = false;
            $bid->save();

            self::recordTransaction(
                $bid->user_id,
                'auction_deposit_forfeit',
                -$deposit,
                $reason . ' (المزاد رقم ' . $lockedAuction->id . ')',
                $lockedAuction
            );

            self::recordTransaction(
                $lockedAuction->user_id,
                'auction_deposit_compensation',
                $deposit,
                'تعويض من عربون الفائز المتراجع (المزاد رقم ' . $lockedAuction->id . ')',
                $lockedAuction
            );

            return $bid->fresh();
        }, 5);
    }

    public static function releaseAllHeldDeposits(Auction $auction, string $reason): int
    {
        return DB::transaction(function () use ($auction, $reason) {
            $lockedAuction = Auction::query()->lockForUpdate()->find($auction->id);

            if (!$lockedAuction) {
                return 0;
            }

            $bids = $lockedAuction->bids()
                ->where('is_deposit_held', true)
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get();

            if ($bids->isEmpty()) {
                return 0;
            }

            $userIds = $bids->pluck('user_id')->unique()->sort()->values();
            self::ensureWalletsExist($userIds);
            $wallets = self::lockWallets($userIds);

            foreach ($bids as $bid) {
                self::releaseLockedBid(
                    $bid,
                    $wallets->get($bid->user_id),
                    $lockedAuction,
                    $reason
                );
            }

            return $bids->count();
        }, 5);
    }

    private static function winnerHeldBid(Auction $auction): ?Bid
    {
        return $auction->bids()
            ->where('user_id', $auction->winner_id)
            ->where('is_deposit_held', true)
            ->orderByDesc('amount')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    private static function releaseLockedBid(
        Bid $bid,
        Wallet $wallet,
        Auction $auction,
        string $reason
    ): void {
        if (!$bid->is_deposit_held) {
            return;
        }

        $deposit = (float) $bid->deposit_amount;

        if ((float) $wallet->held_balance + 0.001 < $deposit) {
            throw new RuntimeException('رصيد العربون المحجوز غير متطابق. أوقف العملية وراجع الإدارة.');
        }

        $wallet->held_balance = round((float) $wallet->held_balance - $deposit, 2);
        $wallet->balance = round((float) $wallet->balance + $deposit, 2);
        $wallet->save();

        $bid->is_deposit_held = false;
        $bid->save();

        self::recordTransaction(
            $bid->user_id,
            'auction_deposit_release',
            $deposit,
            $reason . ' (المزاد رقم ' . $auction->id . ')',
            $auction
        );
    }

    private static function ensureWalletsExist(Collection $userIds): void
    {
        foreach ($userIds as $userId) {
            Wallet::firstOrCreate(
                ['user_id' => $userId],
                [
                    'balance' => 0,
                    'held_balance' => 0,
                    'is_frozen' => false,
                ]
            );
        }
    }

    private static function lockWallets(Collection $userIds): Collection
    {
        return Wallet::query()
            ->whereIn('user_id', $userIds->all())
            ->orderBy('user_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('user_id');
    }

    private static function assertWalletUsable(?Wallet $wallet): void
    {
        if (!$wallet) {
            throw new RuntimeException('تعذر العثور على محفظة المستخدم.');
        }

        if ($wallet->is_frozen) {
            throw new RuntimeException('المحفظة مجمدة مؤقتاً: ' . ($wallet->freeze_reason ?: 'راجع الإدارة.'));
        }
    }

    private static function recordTransaction(
        int $userId,
        string $type,
        float $amount,
        string $description,
        Auction $auction
    ): void {
        Transaction::create([
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'status' => 'completed',
            'payment_method' => 'wallet',
            'description' => $description,
            'processed_at' => now(),
            'transactionable_id' => $auction->id,
            'transactionable_type' => Auction::class,
        ]);
    }
}
