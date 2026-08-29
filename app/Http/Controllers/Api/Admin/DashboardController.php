<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Auction;
use App\Models\Car;
use App\Models\Promotion;
use App\Models\RentalAd;
use App\Models\Transaction;
use App\Models\User;

/**
 * وحدة Dashboard من Admin API — أول وحدتين اكتملتا (مع Users)، نفس
 * الاستعلامات الموجودة بـ Admin\DashboardController القديم بالضبط،
 * لكن كـ JSON بدل view().
 */
class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'verified_users' => User::where('is_verified', true)->count(),
            'unverified_users' => User::where('is_verified', false)->count(),
            'active_ads' => Ad::where('status', 'active')->count(),
            'pending_ads' => Ad::where('status', 'pending')->count(),
            'active_auctions' => Auction::where('status', 'active')->count(),
            'active_rental_ads' => RentalAd::where('status', 'active')->count(),
            'total_transactions' => Transaction::sum('amount'),
        ];

        $latestTransactions = Transaction::with('user')->latest()->take(5)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'latest_transactions' => $latestTransactions,
            ],
            'message' => 'تم جلب بيانات لوحة المعلومات بنجاح.',
        ]);
    }
}
