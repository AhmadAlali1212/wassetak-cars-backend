<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Transaction; // لاستخدام نموذج المعاملة
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    // سعر الاشتراك الشهري (يمكن جعله قابلاً للتكوين في قاعدة البيانات أو ملف الإعدادات)
    const INDIVIDUAL_MONTHLY_SUBSCRIPTION_PRICE = 20000;
    const COMPANY_MONTHLY_SUBSCRIPTION_PRICE = 10000;
    /**
     * عرض حالة الاشتراك الحالية للمستخدم.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function getMonthlySubscriptionPrice(User $user): int
    {
        $accountType = $user->accountType->name ?? null;

        if ($accountType === 'company') {
            return self::COMPANY_MONTHLY_SUBSCRIPTION_PRICE;
        }

        if ($accountType === 'individual') {
            return self::INDIVIDUAL_MONTHLY_SUBSCRIPTION_PRICE;
        }

        return 0;
    }
    public function show(Request $request)
    {
        $user = $request->user();
        $activeSubscription = $user->subscriptions()->where('status', 'active')->latest('end_date')->first();

        return response()->json([
            'message' => 'تم جلب حالة الاشتراك.',
            'is_subscribed' => $user->is_subscribed,
            'subscription_ends_at' => $user->subscription_ends_at,
            'active_subscription_details' => $activeSubscription,
        ]);
    }

    /**
     * اشتراك المستخدم في الخدمة الشهرية.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe(Request $request)
    {
        $authenticatedUser = $request->user();

        try {
            $result = DB::transaction(function () use ($authenticatedUser) {
                $user = User::query()
                    ->with('accountType')
                    ->lockForUpdate()
                    ->findOrFail($authenticatedUser->id);

                $accountType = $user->accountType->name ?? null;

                if (!in_array($accountType, ['individual', 'company'], true)) {
                    throw new \RuntimeException('نوع حسابك لا يسمح بالاشتراك في هذه الخدمة.');
                }

                if ($user->hasActiveSubscription()) {
                    throw new \RuntimeException(
                        'لديك بالفعل اشتراك نشط ينتهي في '
                        . optional($user->subscription_ends_at)->format('Y-m-d H:i:s')
                        . '.'
                    );
                }

                $subscriptionPrice = (int) $this->getMonthlySubscriptionPrice($user);

                if ($subscriptionPrice <= 0) {
                    throw new \RuntimeException('نوع حسابك لا يملك سعر اشتراك محدداً.');
                }

                $user->wallet()->firstOrCreate([], [
                    'balance' => 0,
                    'held_balance' => 0,
                    'is_frozen' => false,
                ]);

                $wallet = \App\Models\Wallet::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($wallet->is_frozen) {
                    throw new \RuntimeException(
                        'المحفظة مجمدة مؤقتاً: ' . ($wallet->freeze_reason ?: 'راجع الإدارة.')
                    );
                }

                if ((float) $wallet->balance < $subscriptionPrice) {
                    throw new \RuntimeException('رصيدك المتاح غير كافٍ للاشتراك. يرجى شحن المحفظة.');
                }

                $wallet->balance = round((float) $wallet->balance - $subscriptionPrice, 2);
                $wallet->save();

                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'type' => 'subscription_payment',
                    'amount' => -$subscriptionPrice,
                    'status' => 'completed',
                    'payment_method' => 'wallet',
                    'description' => 'دفع اشتراك شهري بقيمة ' . $subscriptionPrice . ' لحساب ' . $accountType . '.',
                    'processed_at' => now(),
                ]);

                $startDate = now();
                $endDate = $startDate->copy()->addMonth();

                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'amount' => $subscriptionPrice,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'active',
                    'transaction_id' => $transaction->id,
                ]);

                $user->update([
                    'is_subscribed' => true,
                    'subscription_ends_at' => $endDate,
                ]);

                return compact(
                    'accountType',
                    'subscriptionPrice',
                    'subscription',
                    'wallet',
                    'endDate'
                );
            }, 5);

            return response()->json([
                'message' => 'تم الاشتراك بنجاح. اشتراكك ساري حتى ' . $result['endDate']->format('Y-m-d H:i:s') . '.',
                'account_type' => $result['accountType'],
                'subscription_price' => $result['subscriptionPrice'],
                'new_balance' => (float) $result['wallet']->balance,
                'subscription' => $result['subscription'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'فشلت عملية الاشتراك.'], 500);
        }
    }

    /**
     * إلغاء اشتراك المستخدم.
     * (يمكن أن يكون الإلغاء فورياً أو عند نهاية الفترة الحالية)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(Request $request)
    {
        $user = $request->user();
        $activeSubscriptions = $user->subscriptions()->where('status', 'active')->get();

        if ($activeSubscriptions->isEmpty() && !$user->hasActiveSubscription()) {
            return response()->json(['message' => 'ليس لديك اشتراك نشط لإلغائه.'], 400);
        }

        DB::beginTransaction();
        try {
            foreach ($activeSubscriptions as $activeSubscription) {
                $activeSubscription->status = 'cancelled';
                $activeSubscription->save();
            }


            // إيقاف الاشتراك فوراً على حساب المستخدم

            $user->is_subscribed = false;
            $user->subscription_ends_at = null;
            $user->save();
            DB::commit();

            return response()->json([
                'message' => 'تم إلغاء الاشتراك بنجاح.',
                'is_subscribed' => false,
                'subscription_ends_at' => null,
                'cancelled_subscriptions_count' => $activeSubscriptions->count(),
                'subscription' => $activeSubscriptions->first(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'فشل إلغاء الاشتراك.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * وظيفة مساعدة لإنهاء الاشتراكات المنتهية.
     * يمكن استدعاؤها عبر Cron Job أو Event Listener.
     *
     * @return void
     */
    public function expireSubscriptions()
    {
        $expiredSubscriptions = Subscription::where('status', 'active')
            ->where('end_date', '<=', now())
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            DB::beginTransaction();
            try {
                $subscription->status = 'expired';
                $subscription->save();

                $user = $subscription->user;
                if ($user) {
                    $user->is_subscribed = false;
                    $user->subscription_ends_at = null;
                    $user->save();
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error("Failed to expire subscription ID {$subscription->id}: " . $e->getMessage());
            }
        }
    }
}
