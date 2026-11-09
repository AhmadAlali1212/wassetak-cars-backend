<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WalletTopup;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StripePaymentController extends Controller
{
    public function __construct(
        protected StripeService $stripe,
        protected StripeWebhookController $webhook,
    )
    {
    }

    public function createIntent(Request $request)
    {
        if (!(bool) config('services.stripe.enabled', false)) {
            return response()->json([
                'message' => 'الدفع عبر Stripe غير مفعّل في إعدادات الخادم.',
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'request_id' => 'required|uuid',
            'amount_usd' => 'required|numeric|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $requestId = (string) $request->request_id;
        $amountUsd = round((float) $request->amount_usd, 2);
        $exchangeRate = (float) config('services.stripe.wallet_credit_per_usd');

        if ($exchangeRate <= 0) {
            return response()->json([
                'message' => 'سعر تحويل رصيد المحفظة غير مضبوط بشكل صحيح.',
            ], 500);
        }

        $idempotencyKey = 'wallet-topup:' . $user->id . ':' . $requestId;
        $walletAmount = round($amountUsd * $exchangeRate, 2);

        $topup = WalletTopup::firstOrCreate(
            [
                'user_id' => $user->id,
                'request_id' => $requestId,
            ],
            [
                'amount_usd' => $amountUsd,
                'exchange_rate' => $exchangeRate,
                'wallet_amount' => $walletAmount,
                'currency' => strtolower((string) config('services.stripe.currency', 'usd')),
                'status' => 'creating',
                'idempotency_key' => $idempotencyKey,
            ]
        );

        if (
            (float) $topup->amount_usd !== $amountUsd
            || (float) $topup->wallet_amount !== $walletAmount
        ) {
            return response()->json([
                'message' => 'request_id مستخدم سابقاً بقيمة مختلفة. أنشئ UUID جديداً.',
            ], 409);
        }

        if ($topup->status === 'credited') {
            return response()->json([
                'message' => 'تم شحن هذه العملية مسبقاً.',
                'topup_id' => $topup->id,
                'status' => $topup->status,
            ], 409);
        }

        try {
            if ($topup->stripe_payment_intent_id) {
                $intent = $this->stripe->retrievePaymentIntent($topup->stripe_payment_intent_id);
            } else {
                $intent = $this->stripe->createPaymentIntent($topup);

                $topup->update([
                    'stripe_payment_intent_id' => $intent->id,
                    'status' => 'pending_payment',
                    'failed_at' => null,
                    'failure_message' => null,
                ]);
            }

            return response()->json([
                'message' => 'أكمل الدفع من خلال Stripe Payment Sheet.',
                'topup_id' => $topup->id,
                'request_id' => $topup->request_id,
                'amount_usd' => (float) $topup->amount_usd,
                'wallet_amount' => (float) $topup->wallet_amount,
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id,
                'publishable_key' => config('services.stripe.key'),
            ]);
        } catch (\Throwable $e) {
            $topup->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_message' => $e->getMessage(),
            ]);

            Log::error('Stripe create PaymentIntent failed', [
                'user_id' => $user->id,
                'topup_id' => $topup->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'تعذر إنشاء عملية الدفع عبر Stripe.',
            ], 502);
        }
    }
    public function status(Request $request, string $requestId)
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $requestId)) {
            return response()->json(['message' => 'request_id غير صالح.'], 422);
        }

        $topup = WalletTopup::query()
            ->where('user_id', $request->user()->id)
            ->where('request_id', $requestId)
            ->first();

        if (!$topup) {
            return response()->json(['message' => 'عملية الشحن غير موجودة.'], 404);
        }

        if ($topup->status === 'pending_payment' && $topup->stripe_payment_intent_id) {
            try {
                $intent = $this->stripe->retrievePaymentIntent($topup->stripe_payment_intent_id);

                if ($intent->status === 'succeeded') {
                    $this->webhook->creditPaymentIntent($intent);
                    $topup->refresh();
                }
            } catch (\Throwable $e) {
                Log::error('Stripe top-up status reconciliation failed', [
                    'user_id' => $request->user()->id,
                    'topup_id' => $topup->id,
                    'payment_intent_id' => $topup->stripe_payment_intent_id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'تعذر التحقق من حالة الدفع عبر Stripe.',
                ], 502);
            }
        }

        return response()->json([
            'topup' => [
                'id' => $topup->id,
                'request_id' => $topup->request_id,
                'status' => $topup->status,
                'amount_usd' => (float) $topup->amount_usd,
                'wallet_amount' => (float) $topup->wallet_amount,
                'credited_at' => $topup->credited_at?->toIso8601String(),
                'failed_at' => $topup->failed_at?->toIso8601String(),
                'failure_message' => $topup->failure_message,
            ],
        ]);
    }

}
