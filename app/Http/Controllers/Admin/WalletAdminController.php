<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletAdminController extends Controller
{
    public function pendingWithdrawals()
    {
        return response()->json([
            'withdrawals' => Transaction::with('user')
                ->where('type', 'withdrawal_request')
                ->where('status', 'pending')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function approve(int $id)
    {
        try {
            $transaction = DB::transaction(function () use ($id) {
                $transaction = Transaction::query()
                    ->whereKey($id)
                    ->where('type', 'withdrawal_request')
                    ->lockForUpdate()
                    ->first();

                if (!$transaction || $transaction->status !== 'pending') {
                    throw new \RuntimeException('الطلب غير موجود أو تمت معالجته مسبقاً.');
                }

                $transaction->update([
                    'status' => 'completed',
                    'processed_at' => now(),
                    'description' => trim(($transaction->description ?? '') . ' — وافقت الإدارة.'),
                ]);

                return $transaction;
            }, 5);

            NotificationService::send(
                $transaction->user_id,
                'تمت الموافقة على طلب السحب',
                'وافقت الإدارة على سحب ' . number_format(abs((float) $transaction->amount), 2) . '.',
                'withdrawal_approved',
                ['transaction_id' => $transaction->id]
            );

            return response()->json([
                'message' => 'تمت الموافقة على طلب السحب.',
                'transaction' => $transaction,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function reject(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $transaction = DB::transaction(function () use ($id, $validated) {
                $transaction = Transaction::query()
                    ->whereKey($id)
                    ->where('type', 'withdrawal_request')
                    ->lockForUpdate()
                    ->first();

                if (!$transaction || $transaction->status !== 'pending') {
                    throw new \RuntimeException('الطلب غير موجود أو تمت معالجته مسبقاً.');
                }

                Wallet::firstOrCreate(
                    ['user_id' => $transaction->user_id],
                    ['balance' => 0, 'held_balance' => 0, 'is_frozen' => false]
                );

                $wallet = Wallet::query()
                    ->where('user_id', $transaction->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $wallet->balance = round(
                    (float) $wallet->balance + abs((float) $transaction->amount),
                    2
                );
                $wallet->save();

                $metadata = $transaction->metadata ?? [];
                $metadata['rejection_reason'] = $validated['reason'] ?? null;

                $transaction->update([
                    'status' => 'failed',
                    'processed_at' => now(),
                    'metadata' => $metadata,
                    'description' => trim(
                        ($transaction->description ?? '')
                        . ' — رفضته الإدارة وأعادت المبلغ إلى الرصيد.'
                    ),
                ]);

                return $transaction;
            }, 5);

            NotificationService::send(
                $transaction->user_id,
                'تم رفض طلب السحب',
                'أُعيد المبلغ المحجوز إلى رصيد محفظتك.',
                'withdrawal_rejected',
                ['transaction_id' => $transaction->id]
            );

            return response()->json([
                'message' => 'تم رفض الطلب وإعادة المبلغ إلى المحفظة.',
                'transaction' => $transaction,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function frozenWallets()
    {
        return response()->json([
            'wallets' => Wallet::with('user')
                ->where('is_frozen', true)
                ->latest('updated_at')
                ->paginate(15),
        ]);
    }

    public function unfreeze(Request $request, User $user)
    {
        $request->validate([
            'note' => 'required|string|max:500',
        ]);

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'held_balance' => 0, 'is_frozen' => false]
        );

        $wallet->update([
            'is_frozen' => false,
            'freeze_reason' => null,
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'type' => 'wallet_unfrozen_by_admin',
            'amount' => 0,
            'status' => 'completed',
            'payment_method' => 'admin',
            'description' => 'فك تجميد المحفظة بواسطة الإدارة: ' . $request->note,
            'processed_at' => now(),
        ]);

        NotificationService::send(
            $user->id,
            'تم فك تجميد المحفظة',
            'أعادت الإدارة تفعيل محفظتك.',
            'wallet_unfrozen'
        );

        return response()->json([
            'message' => 'تم فك تجميد المحفظة.',
            'wallet' => $wallet->fresh(),
        ]);
    }
}
