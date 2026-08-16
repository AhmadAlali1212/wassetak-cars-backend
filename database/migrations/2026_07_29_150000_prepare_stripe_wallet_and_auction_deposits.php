<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('status');
            }

            if (!Schema::hasColumn('transactions', 'reference')) {
                $table->string('reference')->nullable()->unique()->after('payment_method');
            }

            if (!Schema::hasColumn('transactions', 'metadata')) {
                $table->json('metadata')->nullable()->after('description');
            }

            if (!Schema::hasColumn('transactions', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('metadata');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('amount', 20, 2)->change();
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('balance', 20, 2)->default(0)->change();

            if (!Schema::hasColumn('wallets', 'held_balance')) {
                $table->decimal('held_balance', 20, 2)->default(0)->after('balance');
            }

            if (!Schema::hasColumn('wallets', 'is_frozen')) {
                $table->boolean('is_frozen')->default(false)->after('held_balance');
            }

            if (!Schema::hasColumn('wallets', 'freeze_reason')) {
                $table->string('freeze_reason')->nullable()->after('is_frozen');
            }
        });

        // قبل إضافة القيد الفريد، دمج أي محافظ مكررة للمستخدم نفسه بأمان.
        $duplicateWallets = DB::table('wallets')
            ->select(
                'user_id',
                DB::raw('MIN(id) AS keep_id'),
                DB::raw('SUM(balance) AS total_balance'),
                DB::raw('SUM(held_balance) AS total_held_balance')
            )
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateWallets as $duplicate) {
            DB::table('wallets')
                ->where('id', $duplicate->keep_id)
                ->update([
                    'balance' => $duplicate->total_balance,
                    'held_balance' => $duplicate->total_held_balance,
                    'updated_at' => now(),
                ]);

            DB::table('wallets')
                ->where('user_id', $duplicate->user_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('wallets', function (Blueprint $table) {
            $table->unique('user_id', 'wallets_user_id_unique');
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->decimal('amount', 20, 2)->change();

            if (!Schema::hasColumn('bids', 'deposit_amount')) {
                $table->decimal('deposit_amount', 20, 2)->default(0)->after('amount');
            }

            if (!Schema::hasColumn('bids', 'is_deposit_held')) {
                $table->boolean('is_deposit_held')->default(false)->after('deposit_amount');
            }
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->decimal('starting_price', 20, 2)->change();
            $table->decimal('current_price', 20, 2)->change();

            if (!Schema::hasColumn('auctions', 'settlement_completed_at')) {
                $table->timestamp('settlement_completed_at')->nullable()->after('final_price_decided_at');
            }

            if (!Schema::hasColumn('auctions', 'settlement_deadline_at')) {
                $table->timestamp('settlement_deadline_at')->nullable()->after('settlement_completed_at');
            }

            if (!Schema::hasColumn('auctions', 'winner_withdrew_at')) {
                $table->timestamp('winner_withdrew_at')->nullable()->after('settlement_deadline_at');
            }
        });

        /*
         * المزايدات الموجودة قبل هذا الترحيل لم يكن لها عربون حقيقي.
         * لا يجوز اعتبارها مضمونة بعد تشغيل النظام الجديد.
         */
        $legacyAuctionIds = DB::table('bids')->distinct()->pluck('auction_id');

        if ($legacyAuctionIds->isNotEmpty()) {
            DB::table('auctions')
                ->whereIn('id', $legacyAuctionIds)
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'winner_id' => null,
                    'final_price_status' => null,
                    'closed_at' => now(),
                    'closed_reason' => 'legacy_unsecured_bids',
                    'updated_at' => now(),
                ]);

            DB::table('auctions')
                ->whereIn('id', $legacyAuctionIds)
                ->where('status', 'closed')
                ->whereIn('final_price_status', ['pending', 'accepted'])
                ->update([
                    'final_price_status' => 'legacy_unsecured',
                    'settlement_deadline_at' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique('wallets_user_id_unique');
        });

        Schema::table('auctions', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['settlement_completed_at', 'settlement_deadline_at', 'winner_withdrew_at'],
                fn (string $column) => Schema::hasColumn('auctions', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('bids', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['deposit_amount', 'is_deposit_held'],
                fn (string $column) => Schema::hasColumn('bids', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('wallets', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['held_balance', 'is_frozen', 'freeze_reason'],
                fn (string $column) => Schema::hasColumn('wallets', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['payment_method', 'reference', 'metadata', 'processed_at'],
                fn (string $column) => Schema::hasColumn('transactions', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
