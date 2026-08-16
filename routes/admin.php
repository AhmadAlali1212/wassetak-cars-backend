<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\RentalAdController;
use App\Http\Controllers\Admin\AuctionController;
use App\Http\Controllers\Admin\CarController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\RatingController;

// مجموعة مسارات المدير، محمية بواسطة middleware 'auth' و 'admin'
Route::middleware(['auth', 'admin'])->group(function () {
    // لوحة تحكم المدير
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    // إدارة المستخدمين (مسارات فردية لعمليات CRUD)
    Route::get('admin/users', [UserController::class, 'index'])->name('admin.users.index');

    Route::get('admin/users/{user}', [UserController::class, 'show'])->name('admin.users.show');
    Route::get('admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');

    // إدارة إعلانات البيع (مسارات فردية لعمليات CRUD)
    Route::get('admin/ads', [AdController::class, 'index'])->name('admin.ads.index');

    Route::get('admin/ads/{ad}', [AdController::class, 'show'])->name('admin.ads.show');
    Route::get('admin/ads/{ad}/edit', [AdController::class, 'edit'])->name('admin.ads.edit');
    Route::put('admin/ads/{ad}', [AdController::class, 'update'])->name('admin.ads.update');
    Route::delete('admin/ads/{ad}', [AdController::class, 'destroy'])->name('admin.ads.destroy');

    // إدارة إعلانات التأجير (مسارات فردية لعمليات CRUD)
    Route::get('admin/rental-ads', [RentalAdController::class, 'index'])->name('admin.rental-ads.index');
    Route::get('admin/rental-ads/{rental_ad}', [RentalAdController::class, 'show'])->name('admin.rental-ads.show');
    Route::get('admin/rental-ads/{rental_ad}/edit', [RentalAdController::class, 'edit'])->name('admin.rental-ads.edit');
    Route::put('admin/rental-ads/{rental_ad}', [RentalAdController::class, 'update'])->name('admin.rental-ads.update');
    Route::delete('admin/rental-ads/{rental_ad}', [RentalAdController::class, 'destroy'])->name('admin.rental-ads.destroy');

    // إدارة المزادات (مسارات فردية لعمليات CRUD)
    Route::get('admin/auctions', [AuctionController::class, 'index'])->name('admin.auctions.index');
    Route::get('admin/auctions/{auction}', [AuctionController::class, 'show'])->name('admin.auctions.show');
    Route::get('admin/auctions/{auction}/edit', [AuctionController::class, 'edit'])->name('admin.auctions.edit');
    Route::put('admin/auctions/{auction}', [AuctionController::class, 'update'])->name('admin.auctions.update');
    Route::delete('admin/auctions/{auction}', [AuctionController::class, 'destroy'])->name('admin.auctions.destroy');


    // إدارة العروض الترويجية (مسارات فردية لعمليات CRUD)
    // Route::get('admin/promotions', [PromotionController::class, 'index'])->name('admin.promotions.index');
    // Route::get('admin/promotions/create', [PromotionController::class, 'create'])->name('admin.promotions.create');
    // Route::post('admin/promotions', [PromotionController::class, 'store'])->name('admin.promotions.store');
    // Route::get('admin/promotions/{promotion}', [PromotionController::class, 'show'])->name('admin.promotions.show');
    // Route::get('admin/promotions/{promotion}/edit', [PromotionController::class, 'edit'])->name('admin.promotions.edit');
    // Route::put('admin/promotions/{promotion}', [PromotionController::class, 'update'])->name('admin.promotions.update');
    // Route::delete('admin/promotions/{promotion}', [PromotionController::class, 'destroy'])->name('admin.promotions.destroy');

    // إدارة العروض الترويجية (مسارات فردية لعمليات CRUD)
    // ⚠️ لازم تكون pending/approved/rejected قبل admin/promotions/{promotion}
    // وإلا Laravel رح يفسّرها كقيمة {promotion} ويحاول route-model-binding عليها ويفشل (404)
    // (نفس المبدأ المطبّق فعلاً على bulk-approve/bulk-reject في routes/api.php)
    Route::get('admin/promotions', [PromotionController::class, 'index'])->name('admin.promotions.index');

    // إدارة الموافقات والرفض للعروض الترويجية
    Route::get('admin/promotions/pending', [PromotionController::class, 'pending'])->name('admin.promotions.pending');
    Route::get('admin/promotions/approved', [PromotionController::class, 'approved'])->name('admin.promotions.approved');
    Route::get('admin/promotions/rejected', [PromotionController::class, 'rejected'])->name('admin.promotions.rejected');
    Route::post('admin/promotions/{id}/approve', [PromotionController::class, 'approve'])->name('admin.promotions.approve');
    Route::post('admin/promotions/{id}/reject', [PromotionController::class, 'reject'])->name('admin.promotions.reject');

    Route::get('admin/promotions/{promotion}', [PromotionController::class, 'show'])->name('admin.promotions.show');
    Route::get('admin/promotions/{promotion}/edit', [PromotionController::class, 'edit'])->name('admin.promotions.edit');
    Route::put('admin/promotions/{promotion}', [PromotionController::class, 'update'])->name('admin.promotions.update');
    Route::delete('admin/promotions/{promotion}', [PromotionController::class, 'destroy'])->name('admin.promotions.destroy');

    // إدارة المعاملات المالية (مسارات فردية لعمليات CRUD)
    Route::get('admin/transactions', [TransactionController::class, 'index'])->name('admin.transactions.index');
    Route::get('admin/transactions/{transaction}', [TransactionController::class, 'show'])->name('admin.transactions.show');

    // إدارة التقييمات (مسارات فردية لعمليات CRUD)
    Route::get('admin/ratings', [RatingController::class, 'index'])->name('admin.ratings.index');
    Route::get('admin/ratings/{rating}', [RatingController::class, 'show'])->name('admin.ratings.show');
    Route::get('admin/ratings/{rating}/edit', [RatingController::class, 'edit'])->name('admin.ratings.edit');
    Route::put('admin/ratings/{rating}', [RatingController::class, 'update'])->name('admin.ratings.update');
    Route::delete('admin/ratings/{rating}', [RatingController::class, 'destroy'])->name('admin.ratings.destroy');
});




// مسارات الأدمن (admin.php أو داخل مجموعة الأدمن)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::post('cars/{id}/approve-inspection', [CarController::class, 'approveInspection']);
});
