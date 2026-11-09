<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StripeWebhookEvent;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\PaymentIntent;

class StripeWebhookController extends Controller
{
    public function __construct(protected StripeService $stripe)
    {
    }

    public function handle(Request $request)
    {
        try {
            $event = $this->stripe->verifyWebhook(
                $request->getContent(),
                $request->header('Stripe-Signature')
            );
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid Stripe signature.'], 400);
        }

        [$eventRow, $shouldProcess] = $this->acquireEvent($event->id, $event->type);

        if (!$shouldProcess) {
            return response()->json(['message' => 'already processing or processed']);
        }

        try {
            $notification = match ($event->type) {
                'payment_intent.succeeded' => $this->handlePaymentSucceeded($event->data->object),
                'payment_intent.payment_failed', 'payment_intent.canceled' => $this->handlePaymentFailed($event->data->object),
                'refund.created' => $this->handleRefundCreated($event->data->object),
                'charge.dispute.created' => $this->handleDisputeCreated($event->data->object),
                default => null,
            };

            $eventRow->update([
                'status' => $notification === null ? 'ignored' : 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);

            if ($notification) {
                NotificationService::send(...$notification);
            }

            return response()->json([
                'message' => $notification === null ? 'ignored' : 'processed',
            ]);
        } catch (\Throwable $e) {
            $eventRow->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Stripe webhook processing failed', [
                'stripe_event_id' => $event->id,
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);

            // 500 يجعل Stripe يعيد إرسال الحدث لاحقاً.
            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }
    }

    public function creditPaymentIntent(PaymentIntent $intent): void
    {
        $notification = $this->handlePaymentSucceeded($intent);

        if ($notification) {
            NotificationService::send(...$notification);
        }
    }

    private function handlePaymentSucceeded(object $intent): array
    {
        return DB::transaction(function () use ($intent) {
            $topupId = (int) ($intent->metadata->wallet_topup_id ?? 0);
            $topup = WalletTopup::query()->lockForUpdate()->find($topupId);

            if (!$topup) {
                throw new RuntimeException('Wallet top-up record not found.');
            }

            if ($topup->status === 'credited') {
                return [
                    $topup->user_id,
                    'تم شحن المحفظة مسبقاً',
                    'تمت معالجة دفعة Stripe هذه مسبقاً دون تكرار الرصيد.',
                    'wallet_topup_already_processed',
                    ['topup_id' => $topup->id],
                ];
            }

            if (
                $topup->stripe_payment_intent_id
                && $topup->stripe_payment_intent_id !== $intent->id
            ) {
                throw new RuntimeException('PaymentIntent does not match the top-up record.');
            }

            $expectedCurrency = strtolower($topup->currency);
            $receivedCurrency = strtolower((string) $intent->currency);
            $expectedMinor = (int) round(((float) $topup->amount_usd) * 100);
            $receivedMinor = (int) ($intent->amount_received ?? $intent->amount ?? 0);

            if ($receivedCurrency !== $expectedCurrency || $receivedMinor < $expectedMinor) {
                throw new RuntimeException('Stripe amount or currency does not match the top-up record.');
            }

            Wallet::firstOrCreate(
                ['user_id' => $topup->user_id],
                ['balance' => 0, 'held_balance' => 0, 'is_frozen' => false]
            );

            $wallet = Wallet::query()
                ->where('user_id', $topup->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $reference = 'stripe:' . $intent->id . ':credit';

            if (!Transaction::where('reference', $reference)->exists()) {
                $wallet->balance = round((float) $wallet->balance + (float) $topup->wallet_amount, 2);
                $wallet->save();

                Transaction::create([
                    'user_id' => $topup->user_id,
                    'type' => 'wallet_topup',
                    'amount' => (float) $topup->wallet_amount,
                    'status' => 'completed',
                    'payment_method' => 'stripe',
                    'reference' => $reference,
                    'description' => 'شحن محفظة عبر Stripe بقيمة ' . $topup->amount_usd . ' USD.',
                    'metadata' => [
                        'wallet_topup_id' => $topup->id,
                        'payment_intent_id' => $intent->id,
                        'amount_usd' => (float) $topup->amount_usd,
                        'exchange_rate' => (float) $topup->exchange_rate,
                    ],
                    'processed_at' => now(),
                ]);
            }

            $topup->update([
                'stripe_payment_intent_id' => $intent->id,
                'status' => 'credited',
                'credited_at' => now(),
                'failed_at' => null,
                'failure_message' => null,
            ]);

            return [
                $topup->user_id,
                'تم شحن محفظتك',
                'تمت إضافة ' . number_format((float) $topup->wallet_amount, 2) . ' إلى رصيد محفظتك.',
                'wallet_charged',
                [
                    'topup_id' => $topup->id,
                    'amount' => (float) $topup->wallet_amount,
                    'new_balance' => (float) $wallet->balance,
                ],
            ];
        }, 5);
    }

    private function handlePaymentFailed(object $intent): ?array
    {
        $topupId = (int) ($intent->metadata->wallet_topup_id ?? 0);

        if (!$topupId) {
            return null;
        }

        $topup = WalletTopup::find($topupId);

        if (!$topup || $topup->status === 'credited') {
            return null;
        }

        $message = $intent->last_payment_error->message
            ?? ($intent->cancellation_reason ?? 'فشلت أو أُلغيت عملية الدفع.');

        $topup->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_message' => $message,
        ]);

        return [
            $topup->user_id,
            'فشلت عملية شحن المحفظة',
            'لم تكتمل عملية الدفع عبر Stripe، ولم يُضف أي رصيد.',
            'wallet_topup_failed',
            ['topup_id' => $topup->id],
        ];
    }

    private function handleRefundCreated(object $refund): ?array
    {
        $paymentIntentId = (string) ($refund->payment_intent ?? '');

        if ($paymentIntentId === '') {
            return null;
        }

        return DB::transaction(function () use ($refund, $paymentIntentId) {
            $topup = WalletTopup::query()
                ->where('stripe_payment_intent_id', $paymentIntentId)
                ->lockForUpdate()
                ->first();

            if (!$topup || $topup->status !== 'credited') {
                return null;
            }

            $reference = 'stripe_refund:' . $refund->id;

            if (Transaction::where('reference', $reference)->exists()) {
                return null;
            }

            Wallet::firstOrCreate(
                ['user_id' => $topup->user_id],
                ['balance' => 0, 'held_balance' => 0, 'is_frozen' => false]
            );

            $wallet = Wallet::query()
                ->where('user_id', $topup->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $originalMinor = max(1, (int) round(((float) $topup->amount_usd) * 100));
            $refundMinor = (int) ($refund->amount ?? 0);
            $walletDebit = round((float) $topup->wallet_amount * ($refundMinor / $originalMinor), 2);

            $wallet->balance = round((float) $wallet->balance - $walletDebit, 2);

            if ((float) $wallet->balance < 0) {
                $wallet->is_frozen = true;
                $wallet->freeze_reason = 'استرداد Stripe أدى إلى رصيد سالب؛ راجع الإدارة.';
            }

            $wallet->save();

            Transaction::create([
                'user_id' => $topup->user_id,
                'type' => 'stripe_refund',
                'amount' => -$walletDebit,
                'status' => 'completed',
                'payment_method' => 'stripe',
                'reference' => $reference,
                'description' => 'خصم رصيد مقابل استرداد دفعة Stripe.',
                'metadata' => [
                    'refund_id' => $refund->id,
                    'payment_intent_id' => $paymentIntentId,
                    'wallet_topup_id' => $topup->id,
                ],
                'processed_at' => now(),
            ]);

            return [
                $topup->user_id,
                'تم استرداد دفعة Stripe',
                'تم خصم الرصيد المقابل للاسترداد من المحفظة.',
                'wallet_topup_refunded',
                [
                    'topup_id' => $topup->id,
                    'amount' => $walletDebit,
                ],
            ];
        }, 5);
    }

    private function handleDisputeCreated(object $dispute): ?array
    {
        $paymentIntentId = (string) ($dispute->payment_intent ?? '');

        if ($paymentIntentId === '') {
            return null;
        }

        return DB::transaction(function () use ($dispute, $paymentIntentId) {
            $topup = WalletTopup::where('stripe_payment_intent_id', $paymentIntentId)->first();

            if (!$topup) {
                return null;
            }

            $reference = 'stripe_dispute:' . $dispute->id;

            if (Transaction::where('reference', $reference)->exists()) {
                return null;
            }

            Wallet::firstOrCreate(
                ['user_id' => $topup->user_id],
                ['balance' => 0, 'held_balance' => 0, 'is_frozen' => false]
            );

            $wallet = Wallet::query()
                ->where('user_id', $topup->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->update([
                'is_frozen' => true,
                'freeze_reason' => 'نزاع دفع Stripe مفتوح؛ راجع الإدارة.',
            ]);

            Transaction::create([
                'user_id' => $topup->user_id,
                'type' => 'stripe_dispute_opened',
                'amount' => 0,
                'status' => 'pending',
                'payment_method' => 'stripe',
                'reference' => $reference,
                'description' => 'تم فتح نزاع على دفعة Stripe وتجميد المحفظة مؤقتاً.',
                'metadata' => [
                    'dispute_id' => $dispute->id,
                    'payment_intent_id' => $paymentIntentId,
                    'wallet_topup_id' => $topup->id,
                ],
            ]);

            return [
                $topup->user_id,
                'تم تجميد المحفظة مؤقتاً',
                'فُتح نزاع على إحدى دفعات Stripe. تواصل مع الإدارة.',
                'wallet_frozen_due_to_dispute',
                ['topup_id' => $topup->id, 'dispute_id' => $dispute->id],
            ];
        }, 5);
    }

    /**
     * يحجز الحدث لمعالج واحد فقط. يمكن استعادة حدث عالق في processing بعد 5 دقائق.
     *
     * @return array{0: StripeWebhookEvent, 1: bool}
     */
    private function acquireEvent(string $eventId, string $type): array
    {
        try {
            $event = StripeWebhookEvent::create([
                'stripe_event_id' => $eventId,
                'type' => $type,
                'status' => 'processing',
            ]);

            return [$event, true];
        } catch (QueryException $e) {
            // حدث بنفس المعرّف موجود؛ نقرر تحت قفل هل يجوز إعادة معالجته.
        }

        return DB::transaction(function () use ($eventId, $type) {
            $event = StripeWebhookEvent::query()
                ->where('stripe_event_id', $eventId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($event->status, ['processed', 'ignored'], true)) {
                return [$event, false];
            }

            if (
                $event->status === 'processing'
                && $event->updated_at
                && $event->updated_at->greaterThan(now()->subMinutes(5))
            ) {
                return [$event, false];
            }

            $event->update([
                'type' => $type,
                'status' => 'processing',
                'processed_at' => null,
                'error_message' => null,
            ]);

            return [$event, true];
        }, 5);
    }
}
