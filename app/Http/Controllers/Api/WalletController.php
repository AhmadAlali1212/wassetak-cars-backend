<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0,
                'held_balance' => 0,
                'is_frozen' => false,
            ]
        );

        $transactions = $user->transactions()
            ->latest()
            ->paginate(15);

        return response()->json([
            'message' => 'تم جلب بيانات المحفظة وسجل المعاملات بنجاح.',
            'wallet' => [
                'available_balance' => (float) $wallet->balance,
                'held_balance' => (float) $wallet->held_balance,
                'total_balance' => round((float) $wallet->balance + (float) $wallet->held_balance, 2),
                'is_frozen' => (bool) $wallet->is_frozen,
                'freeze_reason' => $wallet->freeze_reason,
            ],
            'transactions' => $transactions,
        ]);
    }

    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1000',
            'withdrawal_method' => 'required|string|in:cash_agent,bank_transfer',
            'destination' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $amount = round((float) $request->amount, 2);

        try {
            $transaction = DB::transaction(function () use ($user, $request, $amount) {
                Wallet::firstOrCreate(
                    ['user_id' => $user->id],
                    ['balance' => 0, 'held_balance' => 0, 'is_frozen' => false]
                );

                $wallet = Wallet::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($wallet->is_frozen) {
                    throw new \RuntimeException(
                        'المحفظة مجمدة مؤقتاً: ' . ($wallet->freeze_reason ?: 'راجع الإدارة.')
                    );
                }

                if ((float) $wallet->balance < $amount) {
                    throw new \RuntimeException('رصيدك المتاح غير كافٍ لإتمام طلب السحب.');
                }

                // حجز فوري: يُخصم من الرصيد المتاح لمنع الإنفاق المزدوج.
                $wallet->balance = round((float) $wallet->balance - $amount, 2);
                $wallet->save();

                return Transaction::create([
                    'user_id' => $user->id,
                    'type' => 'withdrawal_request',
                    'amount' => -$amount,
                    'status' => 'pending',
                    'payment_method' => $request->withdrawal_method,
                    'description' => 'طلب سحب بقيمة ' . $amount . ' عبر ' . $request->withdrawal_method,
                    'metadata' => [
                        'destination' => $request->destination,
                    ],
                ]);
            }, 5);

            return response()->json([
                'message' => 'تم تقديم طلب السحب وحجز المبلغ حتى تراجعه الإدارة.',
                'withdrawal_request' => $transaction,
                'new_available_balance' => (float) $user->wallet()->value('balance'),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'تعذر إنشاء طلب السحب.',
            ], 500);
        }
    }
}
