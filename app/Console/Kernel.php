<?php

namespace App\Console;

use App\Models\Ad;
use App\Models\Auction;
use App\Services\NotificationService;
use App\Services\AuctionClosingService;
use App\Services\AuctionDepositService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            $now = now();

            if (Schema::hasTable('promotions')) {
                DB::table('promotions')
                    ->where('status', 'active')
                    ->where('end_date', '<', $now)
                    ->update([
                        'status' => 'expired',
                        'rejection_reason' => null,
                        'updated_at' => $now,
                    ]);
            }

            if (Schema::hasTable('rental_bookings')) {
                DB::table('rental_bookings')
                    ->where('status', 'confirmed')
                    ->whereDate('end_date', '<', today())
                    ->update([
                        'status' => 'completed',
                        'updated_at' => $now,
                    ]);
            }

            if (
                Schema::hasTable('ads')
                && Schema::hasColumn('ads', 'expiration_reminder_sent_at')
            ) {
                $reminderDeadline = $now->copy()->addDays(3);

                // إرسال تذكير واحد فقط لكل دورة نشر قبل الانتهاء بثلاثة أيام.
                Ad::query()
                    ->where('status', 'active')
                    ->whereNotNull('expires_at')
                    ->whereNull('expiration_reminder_sent_at')
                    ->where('expires_at', '>', $now)
                    ->where('expires_at', '<=', $reminderDeadline)
                    ->select('id')
                    ->chunkById(100, function ($ads) use ($now, $reminderDeadline) {
                        foreach ($ads as $candidate) {
                            DB::transaction(function () use ($candidate, $now, $reminderDeadline) {
                                $ad = Ad::query()
                                    ->whereKey($candidate->id)
                                    ->where('status', 'active')
                                    ->whereNotNull('expires_at')
                                    ->whereNull('expiration_reminder_sent_at')
                                    ->where('expires_at', '>', $now)
                                    ->where('expires_at', '<=', $reminderDeadline)
                                    ->lockForUpdate()
                                    ->first();

                                if (!$ad) {
                                    return;
                                }

                                NotificationService::send(
                                    $ad->user_id,
                                    'إعلان البيع سينتهي قريباً',
                                    'بقي أقل من ثلاثة أيام على انتهاء إعلان البيع. يمكنك تجديده بعد انتهاء المدة إذا كانت السيارة ما زالت متاحة.',
                                    'ad_expiration_reminder',
                                    [
                                        'ad_id' => $ad->id,
                                        'expires_at' => $ad->expires_at?->toIso8601String(),
                                    ]
                                );

                                $ad->update([
                                    'expiration_reminder_sent_at' => $now,
                                ]);
                            });
                        }
                    });

                // الإعلان لا يُحذف؛ يتحول فقط إلى expired ويختفي من القوائم العامة.
                DB::table('ads')
                    ->where('status', 'active')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', $now)
                    ->update([
                        'status' => 'expired',
                        'updated_at' => $now,
                    ]);
            }
        })
            ->name('ad-management-hourly-maintenance')
            ->hourly()
            ->withoutOverlapping(10);

        $schedule->call(function () {
            if (!Schema::hasTable('auctions')) {
                return;
            }

            // لا يجوز إبقاء عربون الفائز محجوزاً بلا نهاية إذا لم تُستكمل الصفقة.
            Auction::query()
                ->where('final_price_status', 'accepted')
                ->whereNotNull('settlement_deadline_at')
                ->where('settlement_deadline_at', '<=', now())
                ->select('id')
                ->chunkById(50, function ($auctions) {
                    foreach ($auctions as $candidate) {
                        try {
                            $expired = DB::transaction(function () use ($candidate) {
                                $auction = Auction::query()
                                    ->whereKey($candidate->id)
                                    ->where('final_price_status', 'accepted')
                                    ->whereNotNull('settlement_deadline_at')
                                    ->where('settlement_deadline_at', '<=', now())
                                    ->lockForUpdate()
                                    ->first();

                                if (!$auction) {
                                    return null;
                                }

                                AuctionDepositService::releaseWinnerDeposit(
                                    $auction,
                                    'تحرير العربون لانتهاء مهلة تسوية صفقة المزاد'
                                );

                                $auction->update([
                                    'final_price_status' => 'settlement_expired',
                                    'settlement_completed_at' => now(),
                                    'settlement_deadline_at' => null,
                                ]);

                                return $auction;
                            }, 5);

                            if ($expired) {
                                NotificationService::send(
                                    $expired->winner_id,
                                    'انتهت مهلة تسوية المزاد',
                                    'لم تُستكمل الصفقة ضمن المهلة، لذلك أُعيد العربون إلى رصيدك المتاح.',
                                    'auction_settlement_expired',
                                    ['auction_id' => $expired->id]
                                );

                                NotificationService::send(
                                    $expired->user_id,
                                    'انتهت مهلة تسوية المزاد',
                                    'لم تُستكمل الصفقة ضمن المهلة المحددة، وتم تحرير عربون الفائز.',
                                    'auction_settlement_expired',
                                    ['auction_id' => $expired->id]
                                );
                            }
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                });

            Auction::query()
                ->where('status', 'active')
                ->where('end_time', '<=', now())
                ->select('id')
                ->chunkById(50, function ($auctions) {
                    foreach ($auctions as $candidate) {
                        $auction = Auction::find($candidate->id);

                        if (!$auction) {
                            continue;
                        }

                        try {
                            AuctionClosingService::closeExpiredAuction($auction);
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                });
        })
            ->name('close-expired-auctions')
            ->everyMinute()
            ->withoutOverlapping(5);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
