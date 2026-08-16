<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Auction;
use App\Models\RentalAd;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::count() === 0) {
            $this->call(AdminUserSeeder::class);
        }
        if (Ad::count() === 0) {
            $this->call(AdSeeder::class);
        }
        if (Auction::count() === 0) {
            $this->call(AuctionSeeder::class);
        }
        if (RentalAd::count() === 0) {
            $this->call(RentalAdSeeder::class);
        }

        $users = User::pluck('id')->values();
        $related = [
            Ad::first(),
            Auction::first(),
            RentalAd::first(),
            null,
        ];

        $transactions = [
            ['type' => 'charge', 'amount' => 250.00, 'status' => 'completed', 'description' => 'شحن رصيد المحفظة عبر كود يدوي.'],
            ['type' => 'withdrawal_request', 'amount' => 120.00, 'status' => 'pending', 'description' => 'طلب سحب رصيد قيد المراجعة من الإدارة.'],
            ['type' => 'commission', 'amount' => -35.00, 'status' => 'completed', 'description' => 'خصم عمولة المنصة بعد إتمام عملية بيع.'],
            ['type' => 'charge', 'amount' => 500.00, 'status' => 'completed', 'description' => 'شحن رصيد من أجل تفعيل عرض ترويجي.'],
            ['type' => 'commission', 'amount' => -20.00, 'status' => 'failed', 'description' => 'فشل خصم العمولة بسبب عدم كفاية الرصيد.'],
        ];

        foreach ($transactions as $index => $transaction) {
            $transactionable = $related[$index % count($related)];

            Transaction::create(array_merge($transaction, [
                'user_id' => $users[$index % $users->count()],
                'transactionable_id' => $transactionable?->id,
                'transactionable_type' => $transactionable ? get_class($transactionable) : null,
            ]));
        }
    }
}
