<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Auction;
use App\Models\Car;
use App\Models\Promotion;
use App\Models\RentalAd;
use App\Models\Transaction;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $totalUsers = User::count();
        $verifiedUsers = User::where('is_verified', true)->count();
        $unverifiedUsers = User::where('is_verified', false)->count();
        $activeAds = Ad::where('status', 'active')->count();
        $pendingAds = Ad::where('status', 'pending')->count();
        $activeAuctions = Auction::where('status', 'active')->count();
        $activeRentalAds = RentalAd::where('status', 'active')->count();
        $totalTransactions = Transaction::sum('amount');

        $latestUsers = User::with('accountType')->latest()->take(5)->get();
        $latestCars = Car::with('user')->latest()->take(5)->get();
        $latestAds = Ad::with('car', 'user')->latest()->take(5)->get();
        $latestRentalAds = RentalAd::with('car', 'user')->latest()->take(5)->get();
        $latestAuctions = Auction::with('car', 'user')->latest()->take(5)->get();
        $latestPromotions = Promotion::with('user')->latest()->take(5)->get();
        $latestTransactions = Transaction::with('user')->latest()->take(5)->get();

        return view('admin.dashboard', compact(
            'totalUsers',
            'verifiedUsers',
            'unverifiedUsers',
            'activeAds',
            'pendingAds',
            'activeAuctions',
            'activeRentalAds',
            'totalTransactions',
            'latestUsers',
            'latestCars',
            'latestAds',
            'latestRentalAds',
            'latestAuctions',
            'latestPromotions',
            'latestTransactions'
        ));
    }
}
