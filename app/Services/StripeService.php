<?php

namespace App\Services;

use App\Models\WalletTopup;
use RuntimeException;
use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    protected ?StripeClient $stripe = null;

    public function createPaymentIntent(WalletTopup $topup): PaymentIntent
    {
        $this->assertUsdConfiguration();

        $amountMinor = (int) round(((float) $topup->amount_usd) * 100);

        if ($amountMinor < 100) {
            throw new RuntimeException('قيمة الشحن أقل من الحد الأدنى المسموح به.');
        }

        return $this->client()->paymentIntents->create([
            'amount' => $amountMinor,
            'currency' => 'usd',
            'description' => 'Syria Motor wallet top-up #' . $topup->id,
            'metadata' => [
                'wallet_topup_id' => (string) $topup->id,
                'user_id' => (string) $topup->user_id,
                'wallet_amount' => (string) $topup->wallet_amount,
                'exchange_rate' => (string) $topup->exchange_rate,
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ], [
            'idempotency_key' => $topup->idempotency_key,
        ]);
    }

    public function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->client()->paymentIntents->retrieve($paymentIntentId, []);
    }

    public function verifyWebhook(string $payload, ?string $signature): Event
    {
        if (!$signature) {
            throw new RuntimeException('Stripe-Signature header is missing.');
        }

        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        return Webhook::constructEvent($payload, $signature, $secret);
    }

    private function client(): StripeClient
    {
        if ($this->stripe) {
            return $this->stripe;
        }

        if (!(bool) config('services.stripe.enabled', false)) {
            throw new RuntimeException('الدفع عبر Stripe غير مفعّل في إعدادات الخادم.');
        }

        $secret = (string) config('services.stripe.secret');
        $publishableKey = (string) config('services.stripe.key');

        if ($secret === '' || $publishableKey === '') {
            throw new RuntimeException('مفاتيح Stripe غير مضبوطة في ملف البيئة.');
        }

        if (
            (bool) config('services.stripe.test_mode_only', true)
            && (!str_starts_with($secret, 'sk_test_') || !str_starts_with($publishableKey, 'pk_test_'))
        ) {
            throw new RuntimeException('هذه الحزمة مقيّدة بوضع Stripe التجريبي. استخدم مفاتيح test فقط.');
        }

        return $this->stripe = new StripeClient($secret);
    }

    private function assertUsdConfiguration(): void
    {
        if (strtolower((string) config('services.stripe.currency', 'usd')) !== 'usd') {
            throw new RuntimeException('هذه الحزمة تحتسب الشحن بالدولار فقط. اضبط STRIPE_CURRENCY=usd.');
        }
    }
}
