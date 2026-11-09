<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\CarController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\RentalAdController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\AdController;
use App\Http\Controllers\Auth\OtpPasswordResetController;
use App\Http\Controllers\Admin\CarController as AdminCarController;
use App\Http\Controllers\Admin\AdController as AdminAdController;
use App\Http\Controllers\Admin\RentalAdController as AdminRentalAdController;
use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Api\BulkCarUploadController;
use App\Http\Controllers\Api\RentalBookingController;
use App\Http\Controllers\Api\StripePaymentController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Admin\WalletAdminController;
use App\Http\Controllers\Admin\AuctionController as AdminAuctionController;
use App\Http\Controllers\Admin\ModerationController as AdminModerationController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\UserSearchController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\BlockController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DisputeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// مسارات المصادقة
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


// Stripe webhook: عام لأن Stripe يرسله مباشرة، والحماية تتم بتوقيع Stripe-Signature.
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->middleware('throttle:120,1');


// مسارات تفعيل الحساب برمز OTP بعد التسجيل
Route::post('/register/otp-resend', [AuthController::class, 'resendVerificationOtp']);
Route::post('/register/otp-verify', [AuthController::class, 'verifyRegistrationOtp']);

// مسارات نسيان كلمة المرور: بنفس آلية تفعيل الحساب عند التسجيل تماماً
// (رمز OTP يُرسل بالبريد الإلكتروني، بدون أي روابط)
// 1. طلب إرسال رمز الـ OTP لإعادة تعيين كلمة المرور
Route::post('forgot-password', [OtpPasswordResetController::class, 'sendOtp']);
// 2. التحقق من رمز الـ OTP المرسل، ثم 3. تعيين كلمة المرور الجديدة
// (نفس مسارات password/otp-verify و password/otp-reset المعرّفة أدناه)

// مسارات محمية تتطلب المصادقة
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // مسارات إدارة البروفايل الشخصي (البيانات، صورة البروفايل، معرض الصور)
    Route::put('/profile', [ProfileController::class, 'update']); // تحديث الاسم/رقم الهاتف
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']); // رفع/استبدال صورة البروفايل
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']); // حذف صورة البروفايل
    Route::post('/profile/gallery', [ProfileController::class, 'addGalleryImages']); // إضافة صور لمعرض المستخدم
    Route::delete('/profile/gallery', [ProfileController::class, 'deleteGalleryImage']); // حذف صورة من المعرض

    // مسارات إدارة السيارات (إضافة وتعديل وحذف السيارات)
Route::post('/cars', [CarController::class, 'store']); // إضافة سيارة جديدة
Route::get('/cars/{id}', [CarController::class, 'showFull']); // عرض تفاصيل سيارة كاملة (السيارة + إعلان البيع + إعلان التأجير وحجوزاته + عروض المالك)
Route::put('/cars/{id}', [CarController::class, 'update']); // تعديل سيارة
Route::delete('/cars/{id}', [CarController::class, 'destroy']); // حذف سيارة
// ⬅️ جديد: رفع فيديو للسيارة لاحقاً (بعد الإنشاء) بدون تعديل بقية بيانات السيارة
Route::post('/cars/{id}/videos', [CarController::class, 'addVideos']);
Route::delete('/cars/{id}/videos', [CarController::class, 'removeVideo']);
Route::get('/my-cars', [CarController::class, 'myCars']);
Route::get('/my-cars/{id}', [CarController::class, 'showMineFull']);
Route::get('/cars', [CarController::class, 'publicCars']);
Route::get('/other-users-cars', [CarController::class, 'otherUsersCars']);
    // مسارات عرض الإعلانات
    Route::get('/ads', [CarController::class, 'index']); // عرض جميع الإعلانات (مع الفلترة والبحث)
    Route::get('/ads/{id}', [CarController::class, 'show']); // عرض إعلان محدد
    Route::get('/other-users-ads', [AdController::class, 'otherUsersAds']); // عرض إعلانات المستخدمين الآخرين

    // مسارات إدارة الإعلانات (إنشاء وتعديل وحذف الإعلانات)
    Route::post('/ads', [AdController::class, 'store']); // إنشاء إعلان جديد لسيارة
    Route::post('/my-ads/{id}/publish', [AdController::class, 'publish']); // نشر إعلان (يمكن نشره أكثر من مرة)
    Route::post('/my-ads/{id}/renew', [AdController::class, 'renew']); // تجديد إعلان منتهٍ بعد تأكيد توفر السيارة
    Route::get('/my-ads', [AdController::class, 'myAds']); // عرض إعلاناتي
    Route::get('/my-ads/{id}', [AdController::class, 'show']); // عرض إعلان محدد لي
    Route::put('/my-ads/{id}', [AdController::class, 'update']); // تحديث إعلان
    Route::delete('/my-ads/{id}', [AdController::class, 'destroy']); // حذف إعلان
    Route::put('/ads/bulk-publish', [AdController::class, 'bulkPublishPrices']); // تحديث أسعار عدة إعلانات دفعة واحدة (نُقل من مجموعة bulk-upload لأنه خاص بالإعلانات)

    // مسارات إدارة إعلانات تأجير السيارات

Route::get('/rental-ads', [RentalAdController::class, 'index']); // عرض عام (active بس)
Route::get('/rental-ads/my', [RentalAdController::class, 'myRentalAds']); // ⬅️ جديد: إعلاناتي (أي حالة)
Route::get('/rental-ads/my/{id}', [RentalAdController::class, 'showMine']); // ⬅️ جديد: إعلان محدد إلي (أي حالة)
Route::get('/rental-ads/{id}', [RentalAdController::class, 'show']); // عرض عام لإعلان محدد (active بس)


    Route::post('/rental-ads', [RentalAdController::class, 'store']); // إضافة إعلان تأجير جديد
    Route::post('/rental-ads/{id}/publish', [RentalAdController::class, 'publish']); // نشر إعلان تأجير (يمكن نشره أكثر من مرة)
    Route::put('/rental-ads/{id}', [RentalAdController::class, 'update']); // تحديث إعلان تأجير
    Route::delete('/rental-ads/{id}', [RentalAdController::class, 'destroy']); // حذف إعلان تأجير

    // ⬅️ جديد: أيام عدم التوفر (تحديد الأيام التي تكون فيها السيارة غير متاحة للتأجير)
    Route::get('/rental-ads/{id}/availability', [RentalAdController::class, 'availability']); // الأيام المحجوزة + المحجوبة يدوياً (عام لأي مستخدم مسجل)
    Route::post('/rental-ads/{id}/unavailable-dates', [RentalAdController::class, 'addUnavailableDates']); // المالك يحجب فترة أيام
    Route::delete('/rental-ads/{id}/unavailable-dates/{unavailabilityId}', [RentalAdController::class, 'removeUnavailableDates']); // المالك يلغي حجب فترة

    Route::post('/rental-bookings', [RentalBookingController::class, 'store']); // إنشاء طلب حجز جديد (pending)
    Route::get('/rental-bookings/my', [RentalBookingController::class, 'myBookings']); // حجوزاتي (كمستأجر)
    Route::get('/rental-bookings/incoming', [RentalBookingController::class, 'incomingBookings']); // الطلبات الواردة على إعلاناتي (كمالك)
    Route::post('/rental-bookings/{id}/approve', [RentalBookingController::class, 'approve']); // موافقة على طلب حجز
    Route::post('/rental-bookings/{id}/reject', [RentalBookingController::class, 'reject']); // رفض طلب حجز
    Route::post('/rental-bookings/{id}/mark-delivered', [RentalBookingController::class, 'markDelivered']); // ⬅️ جديد: تأكيد تسليم السيارة
    Route::post('/rental-bookings/{id}/mark-returned', [RentalBookingController::class, 'markReturned']); // ⬅️ جديد: تأكيد إرجاع السيارة
    Route::delete('/rental-bookings/{id}', [RentalBookingController::class, 'cancel']); // إلغاء حجز

    // ⬅️ جديد: نظام المفضلة (Favorites) الحقيقي، بدل أزرار القلب الشكلية المحلية
    Route::get('/favorites', [FavoriteController::class, 'index']); // قائمة مفضلتي
    Route::post('/favorites', [FavoriteController::class, 'store']); // إضافة سيارة للمفضلة
    Route::delete('/favorites/{carId}', [FavoriteController::class, 'destroy']); // إزالة سيارة من المفضلة
    Route::get('/favorites/{carId}/status', [FavoriteController::class, 'status']); // هل هذه السيارة مفضّلة؟

    // ⬅️ جديد: البحث عن مستخدم (لميزات مثل "قيّم المشتري" بالبيع العادي)
    Route::get('/users/search', [UserSearchController::class, 'search']);

    // ⬅️ جديد: تعليم إعلان بيع كـ "مباع" ذاتياً من قبل البائع (دون انتظار الأدمن)
    Route::post('/my-ads/{id}/mark-sold', [AdController::class, 'markSold']);

    // ⬅️ جديد: رسائل حقيقية داخل التطبيق بين مستخدمين، بدل الاعتماد الكامل
    // على واتساب خارجي (جدول messages كان موجوداً بدون أي استخدام فعلي)
    Route::get('/messages/conversations', [MessageController::class, 'conversations']);
    Route::get('/messages/unread-count', [MessageController::class, 'unreadCount']);
    Route::get('/messages/with/{userId}', [MessageController::class, 'thread'])->whereNumber('userId');
    Route::post('/messages', [MessageController::class, 'send'])
        ->middleware('throttle:30,1'); // منع سبام الرسائل

    // ⬅️ جديد: حظر مستخدم (يمنع الرسائل الجديدة بالاتجاهين)
    Route::get('/blocks', [BlockController::class, 'index']);
    Route::post('/blocks', [BlockController::class, 'store']);
    Route::delete('/blocks/{blockedUserId}', [BlockController::class, 'destroy']);

    // ⬅️ جديد: الإبلاغ عن مستخدم (احتيال/إساءة/محتوى غير لائق/سبام)
    Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/reports/my', [ReportController::class, 'myReports']);

    // ⬅️ جديد: نزاع مرتبط بمعاملة فعلية (تصعيد للأدمن)
    Route::post('/disputes', [DisputeController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/disputes/my', [DisputeController::class, 'myDisputes']);


    // مسارات إدارة المزادات
    Route::get('/auctions', [AuctionController::class, 'index']); // المزادات العامة النشطة والجارية
    Route::get('/my-auctions', [AuctionController::class, 'myAuctions']); // مزادات المستخدم بكل الحالات
    Route::post('/auctions/close-expired', [AuctionController::class, 'closeExpiredAuctions']);
    Route::post('/auctions', [AuctionController::class, 'store']);

    Route::get('/auctions/{id}', [AuctionController::class, 'show'])->whereNumber('id');
    // ⬅️ جديد: rate limiting صريح وموثّق لعمليات المزايدة تحديداً (منع سبام المزايدات)
    Route::post('/auctions/{id}/bid', [AuctionController::class, 'placeBid'])
        ->whereNumber('id')
        ->middleware('throttle:10,1'); // 10 مزايدات كحد أقصى لكل مستخدم بالدقيقة
    Route::post('/auctions/{id}/accept-final-price', [AuctionController::class, 'acceptFinalPrice'])->whereNumber('id');
    Route::post('/auctions/{id}/reject-final-price', [AuctionController::class, 'rejectFinalPrice'])->whereNumber('id');
    Route::post('/auctions/{id}/complete-sale', [AuctionController::class, 'completeSale'])->whereNumber('id');
    Route::post('/auctions/{id}/winner-withdraw', [AuctionController::class, 'winnerWithdraw'])->whereNumber('id');
    Route::put('/auctions/{id}', [AuctionController::class, 'update'])->whereNumber('id');
    Route::delete('/auctions/{id}', [AuctionController::class, 'destroy'])->whereNumber('id');

    // مسارات إدارة المحفظة الإلكترونية والمعاملات
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::post('/wallet/stripe/create-intent', [StripePaymentController::class, 'createIntent'])
        ->middleware('throttle:10,1');
    Route::get('/wallet/stripe/topups/{requestId}', [StripePaymentController::class, 'status'])
        ->middleware('throttle:30,1');
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);

    // مسارات إدارة الاشتراكات
    Route::get('/subscription', [SubscriptionController::class, 'show']); // عرض حالة الاشتراك
    Route::post('/subscription/subscribe', [SubscriptionController::class, 'subscribe']); // الاشتراك
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']); // إلغاء الاشتراك

    // مسارات العروض الترويجية
    Route::get('/promotions', [PromotionController::class, 'index']);
    Route::post('/promotions', [PromotionController::class, 'store']);
    Route::get('/promotions/{id}', [PromotionController::class, 'show']);
    Route::put('/promotions/{id}', [PromotionController::class, 'update']);
    Route::delete('/promotions/{id}', [PromotionController::class, 'destroy']);
    // Ratings API
    Route::get('/users/{user}/ratings', [RatingController::class, 'index']);
    Route::post('/users/{user}/ratings', [RatingController::class, 'store']);
    // ⬅️ جديد: المعاملات المكتملة التي يحق لي تقييمها الآن ولم أقيّمها بعد
    Route::get('/ratings/ratable', [RatingController::class, 'ratable']);

    // Notifications API
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->whereNumber('id');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);


    //follow
    Route::post('/companies/{company}/follow', [FollowController::class, 'follow']);
    Route::delete('/companies/{company}/follow', [FollowController::class, 'unfollow']);
    Route::get('/companies/{company}/follow-status', [FollowController::class, 'status']);
    Route::get('/my-following-companies', [FollowController::class, 'myFollowingCompanies']);


    // مثال على مسار محمي
    Route::get('/protected-route', function (Request $request) {
        return response()->json(['message' => 'أهلاً بك في المسار المحمي، ' . $request->user()->name . '!']);
    });
});

// مسارات الإدارة (Admin Routes) - محمية بـ admin middleware
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

    // مسارات إدارة السيارات - الموافقة والرفض
    Route::get('/cars/pending', [AdminCarController::class, 'index']); // عرض السيارات المنتظرة
    Route::get('/cars/approved', [AdminCarController::class, 'approved']); // عرض السيارات المقبولة
    Route::get('/cars/rejected', [AdminCarController::class, 'rejected']); // عرض السيارات المرفوضة
    Route::get('/cars/{id}', [AdminCarController::class, 'show']); // عرض تفاصيل سيارة
    Route::post('/cars/{id}/approve', [AdminCarController::class, 'approve']); // الموافقة على سيارة
    Route::post('/cars/{id}/reject', [AdminCarController::class, 'reject']); // رفض سيارة
    Route::post('/cars/{id}/approve-inspection', [AdminCarController::class, 'approveInspection']); // اعتماد تقرير الفحص الفني وتفعيل شارة "مفحوصة ومضمونة"

    // مسارات موافقة/رفض دفعة الرفع الجماعي (Excel) كاملة دفعة واحدة، بدل كل سيارة لحالها
    Route::get('/cars/bulk-batches/pending', [AdminCarController::class, 'pendingBulkBatches']); // عرض الدفعات المنتظرة
    Route::get('/cars/bulk-batches/{batchId}', [AdminCarController::class, 'showBulkBatch']); // تفاصيل دفعة محددة
    Route::post('/cars/bulk-batches/{batchId}/approve', [AdminCarController::class, 'approveBulkBatch']); // الموافقة على الدفعة كاملة
    Route::post('/cars/bulk-batches/{batchId}/reject', [AdminCarController::class, 'rejectBulkBatch']); // رفض الدفعة كاملة
// ⚠️ لازم يكون bulk-approve و bulk-reject قبل /ads/{id}
// وإلا Laravel رح يفسّر كلمة "bulk-approve" على أنها قيمة {id} ويوجّهها غلط لـ showAd()
Route::post('/ads/bulk-approve', [AdminAdController::class, 'bulkApproveAds']); // موافقة جماعية (batch_id و/أو ad_ids)
Route::post('/ads/bulk-reject', [AdminAdController::class, 'bulkRejectAds']);   // رفض جماعي (batch_id و/أو ad_ids)

Route::get('/ads/pending', [AdminAdController::class, 'pendingAds']);
Route::get('/ads/approved', [AdminAdController::class, 'approvedAds']);
Route::get('/ads/rejected', [AdminAdController::class, 'rejectedAds']);
Route::get('/ads/expired', [AdminAdController::class, 'expiredAds']);
Route::get('/ads/{id}', [AdminAdController::class, 'showAd']);
Route::post('/ads/{id}/approve', [AdminAdController::class, 'approveAd']);
Route::post('/ads/{id}/reject', [AdminAdController::class, 'rejectAd']);
    // مسارات إدارة إعلانات التأجير - الموافقة والرفض
    Route::get('/rental-ads/pending', [AdminRentalAdController::class, 'pendingRentalAds']); // عرض إعلانات التأجير المنتظرة
    Route::get('/rental-ads/approved', [AdminRentalAdController::class, 'approvedRentalAds']); // عرض إعلانات التأجير المقبولة
    Route::get('/rental-ads/rejected', [AdminRentalAdController::class, 'rejectedRentalAds']); // عرض إعلانات التأجير المرفوضة
    Route::get('/rental-ads/{id}', [AdminRentalAdController::class, 'showRentalAd']); // عرض تفاصيل إعلان تأجير
    Route::post('/rental-ads/{id}/approve', [AdminRentalAdController::class, 'approveRentalAd']); // الموافقة على إعلان تأجير
    Route::post('/rental-ads/{id}/reject', [AdminRentalAdController::class, 'rejectRentalAd']); // رفض إعلان تأجير

    // مسارات إدارة العروض الترويجية - الموافقة والرفض
    Route::get('/promotions/pending', [AdminPromotionController::class, 'pending']); // عرض العروض المنتظرة
    Route::get('/promotions/approved', [AdminPromotionController::class, 'approved']); // عرض العروض المقبولة
    Route::get('/promotions/rejected', [AdminPromotionController::class, 'rejected']); // عرض العروض المرفوضة
    // ملاحظة: PromotionController@show مخصصة لواجهة الويب (تُرجع Blade view) وليست JSON، لذلك لم تُضَف هنا
    Route::post('/promotions/{id}/approve', [AdminPromotionController::class, 'approve']); // الموافقة على عرض ترويجي
    Route::post('/promotions/{id}/reject', [AdminPromotionController::class, 'reject']); // رفض عرض ترويجي

    // إدارة طلبات السحب والمحافظ المجمدة
    Route::get('/withdrawals', [WalletAdminController::class, 'pendingWithdrawals']);
    Route::post('/withdrawals/{id}/approve', [WalletAdminController::class, 'approve'])->whereNumber('id');
    Route::post('/withdrawals/{id}/reject', [WalletAdminController::class, 'reject'])->whereNumber('id');
    Route::get('/wallets/frozen', [WalletAdminController::class, 'frozenWallets']);
    Route::post('/wallets/{user}/unfreeze', [WalletAdminController::class, 'unfreeze'])->whereNumber('user');

    // مسارات قوائم أسباب الرفض الجاهزة (لعرضها كقائمة اختيار Dropdown في لوحة الأدمن)
    Route::get('/rejection-reasons/car', [AdminCarController::class, 'rejectionReasons']);
    Route::get('/rejection-reasons/ad', [AdminAdController::class, 'rejectionReasons']);
    Route::get('/rejection-reasons/rental-ad', [AdminRentalAdController::class, 'rejectionReasons']);
    Route::get('/rejection-reasons/promotion', [AdminPromotionController::class, 'rejectionReasons']);
    Route::get('/rejection-reasons/auction', [AdminAuctionController::class, 'rejectionReasons']);

    // مسارات إدارة المزادات - الموافقة والرفض (API JSON، بجانب واجهة الويب في routes/admin.php)
    // ⚠️ لازم تكون pending/active/closed/cancelled قبل admin/auctions/{id}
    // وإلا Laravel رح يفسّرها كقيمة {id} (نفس مبدأ bulk-approve/bulk-reject أعلاه)
    Route::get('/auctions/pending', [AdminAuctionController::class, 'pendingAuctions']); // عرض المزادات المنتظرة
    Route::get('/auctions/active', [AdminAuctionController::class, 'activeAuctions']); // عرض المزادات النشطة
    Route::get('/auctions/closed', [AdminAuctionController::class, 'closedAuctions']); // عرض المزادات المغلقة
    Route::get('/auctions/cancelled', [AdminAuctionController::class, 'cancelledAuctions']); // عرض المزادات الملغاة/المرفوضة
    Route::get('/auctions/{id}', [AdminAuctionController::class, 'showAuction'])->whereNumber('id'); // تفاصيل مزاد
    Route::post('/auctions/{id}/approve', [AdminAuctionController::class, 'approveAuction'])->whereNumber('id'); // الموافقة على مزاد
    Route::post('/auctions/{id}/reject', [AdminAuctionController::class, 'rejectAuction'])->whereNumber('id'); // رفض مزاد

    // ⬅️ جديد: مراجعة البلاغات والنزاعات (لا واجهة Flutter مخصصة بعد؛
    // جاهز للاستهلاك من أي لوحة أدمن، بما فيها لوحة الويب المذكورة أعلاه)
    Route::get('/reports', [AdminModerationController::class, 'reports']);
    Route::post('/reports/{id}/status', [AdminModerationController::class, 'updateReport'])->whereNumber('id');
    Route::get('/disputes', [AdminModerationController::class, 'disputes']);
    Route::post('/disputes/{id}/status', [AdminModerationController::class, 'updateDispute'])->whereNumber('id');
});




Route::middleware('auth:sanctum')->group(function () {
    Route::post('cars/{id}/request-inspection', [CarController::class, 'requestInspection']);
});









// // الحجوزات
// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/rental-bookings',                    [RentalBookingController::class, 'store']);
//     Route::post('/rental-bookings/{id}/confirm',       [RentalBookingController::class, 'confirm']);
//     Route::post('/rental-bookings/{id}/cancel',        [RentalBookingController::class, 'cancel']);
//     Route::get('/rental-bookings/my',                  [RentalBookingController::class, 'myBookings']);
//     Route::get('/rental-bookings/incoming',            [RentalBookingController::class, 'incomingBookings']);
//     Route::get('/rental-ads/{id}/booked-dates',        [RentalBookingController::class, 'bookedDates']);
// });





Route::prefix('bulk-upload')->middleware(['auth:sanctum'])->group(function () {

    // تحميل نموذج Excel فارغ
    // GET /api/bulk-upload/template
    Route::get('template', [BulkCarUploadController::class, 'downloadTemplate']);

    // معاينة الملف قبل الرفع (dry run - بدون حفظ)
    // POST /api/bulk-upload/preview
    Route::post('preview', [BulkCarUploadController::class, 'preview']);

    // رفع الملف ومعالجته فعلياً
    // POST /api/bulk-upload
    Route::post('/', [BulkCarUploadController::class, 'upload']);

    // عرض سجل الرفعات السابقة للمعرض
    // GET /api/bulk-upload/history
    Route::get('history', [BulkCarUploadController::class, 'history']);
});










// 1. طلب إرسال الرمز للإيميل
Route::post('password/otp-request', [OtpPasswordResetController::class, 'sendOtp']);

// 2. التحقق من الرمز المرسل
Route::post('password/otp-verify', [OtpPasswordResetController::class, 'verifyOtp']);

// 3. تعيين كلمة المرور الجديدة بعد نجاح التحقق
Route::post('password/otp-reset', [OtpPasswordResetController::class, 'resetPassword']);

// اسم بديل مألوف لنفس مسار تعيين كلمة المرور الجديدة (نفس الدالة تماماً)
Route::post('reset-password', [OtpPasswordResetController::class, 'resetPassword'])->name('password.update');

// ============================================================
// ⬅️ جديد: طبقة Api\Admin الجديدة التي تستهلكها لوحة React
// (wasetak-cars-web). تتّبع نفس الاتفاقية الموثّقة بـ ADMIN_API_NOTES.md
// (غلاف موحّد {success,data,message}، pagination بشكل
// data.items + data.pagination، per_page=20، middleware auth:sanctum +
// admin.api). مسارات جديدة بالكامل، لا تعديل على أي سطر أعلاه.
// ملاحظة مهمة: هذه الوحدة تغطي فقط Reports وDisputes (الميزات التي
// أضيفت هذا العام) — بقية الوحدات الموثّقة بنفس الملف (Dashboard, Users,
// Ads, RentalAds, Auctions, Promotions, Transactions, Ratings,
// CarsApproval, Wallet) موصوفة كمكتملة فعلاً في الملف لكن غير موجودة في
// نسخة الباك-إند هذه — يُفترض أنها موجودة بنسخة أخرى من المستودع.
// ============================================================
use App\Http\Controllers\Api\Admin\ReportController as ApiAdminReportController;
use App\Http\Controllers\Api\Admin\DisputeController as ApiAdminDisputeController;
use App\Http\Controllers\Api\Admin\DashboardController as ApiAdminDashboardController;
use App\Http\Controllers\Api\Admin\UserController as ApiAdminUserController;
use App\Http\Controllers\Api\Admin\AdController as ApiAdminAdController;
use App\Http\Controllers\Api\Admin\RentalAdController as ApiAdminRentalAdController;
use App\Http\Controllers\Api\Admin\AuctionController as ApiAdminAuctionController;
use App\Http\Controllers\Api\Admin\PromotionController as ApiAdminPromotionController;
use App\Http\Controllers\Api\Admin\TransactionController as ApiAdminTransactionController;
use App\Http\Controllers\Api\Admin\RatingController as ApiAdminRatingController;
use App\Http\Controllers\Api\Admin\CarController as ApiAdminCarController;
use App\Http\Controllers\Api\Admin\WalletAdminController as ApiAdminWalletAdminController;
use App\Http\Controllers\Api\Admin\AuditLogController as ApiAdminAuditLogController;
use App\Http\Controllers\Api\Admin\BlockController as ApiAdminBlockController;

Route::middleware(['auth:sanctum', 'admin.api'])->prefix('admin')->group(function () {
    Route::get('/reports', [ApiAdminReportController::class, 'index']);
    Route::get('/reports/{id}', [ApiAdminReportController::class, 'show'])->whereNumber('id');
    Route::put('/reports/{id}', [ApiAdminReportController::class, 'update'])->whereNumber('id');
    Route::delete('/reports/{id}', [ApiAdminReportController::class, 'destroy'])->whereNumber('id');

    Route::get('/disputes', [ApiAdminDisputeController::class, 'index']);
    Route::get('/disputes/{id}', [ApiAdminDisputeController::class, 'show'])->whereNumber('id');
    Route::put('/disputes/{id}', [ApiAdminDisputeController::class, 'update'])->whereNumber('id');
    Route::delete('/disputes/{id}', [ApiAdminDisputeController::class, 'destroy'])->whereNumber('id');

    // ⬅️ جديد: بقية وحدات Admin API الموثّقة بـ ADMIN_API_NOTES.md، أُعيد
    // بناؤها اعتماداً على الكود الفعلي بـ app/Http/Controllers/Admin/*
    // القديم (Blade/JSON قديم) بعد أن تبيّن عدم وجودها بهذه النسخة أصلاً.

    Route::get('/dashboard', [ApiAdminDashboardController::class, 'index']);

    Route::get('/account-types', [ApiAdminUserController::class, 'accountTypes']);
    Route::get('/users', [ApiAdminUserController::class, 'index']);
    Route::get('/users/{user}', [ApiAdminUserController::class, 'show']);
    Route::put('/users/{user}', [ApiAdminUserController::class, 'update']);
    Route::delete('/users/{user}', [ApiAdminUserController::class, 'destroy']);

    Route::get('/ads', [ApiAdminAdController::class, 'index']);
    Route::put('/ads/{ad}', [ApiAdminAdController::class, 'update']);
    Route::delete('/ads/{ad}', [ApiAdminAdController::class, 'destroy']);

    Route::get('/rental-ads', [ApiAdminRentalAdController::class, 'index']);
    Route::put('/rental-ads/{rentalAd}', [ApiAdminRentalAdController::class, 'update']);
    Route::delete('/rental-ads/{rentalAd}', [ApiAdminRentalAdController::class, 'destroy']);

    Route::get('/auctions', [ApiAdminAuctionController::class, 'index']);
    Route::get('/auctions/{auction}', [ApiAdminAuctionController::class, 'show']);
    Route::put('/auctions/{auction}', [ApiAdminAuctionController::class, 'update']);
    Route::delete('/auctions/{auction}', [ApiAdminAuctionController::class, 'destroy']);

    Route::get('/promotions', [ApiAdminPromotionController::class, 'index']);
    Route::get('/promotions/{promotion}', [ApiAdminPromotionController::class, 'show']);
    Route::put('/promotions/{promotion}', [ApiAdminPromotionController::class, 'update']);
    Route::delete('/promotions/{promotion}', [ApiAdminPromotionController::class, 'destroy']);

    Route::get('/transactions', [ApiAdminTransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [ApiAdminTransactionController::class, 'show']);

    Route::get('/ratings', [ApiAdminRatingController::class, 'index']);
    Route::get('/ratings/{rating}', [ApiAdminRatingController::class, 'show']);
    Route::put('/ratings/{rating}', [ApiAdminRatingController::class, 'update']);
    Route::delete('/ratings/{rating}', [ApiAdminRatingController::class, 'destroy']);

    // ⚠️ بادئة car-approvals (بدل cars) لتفادي أي تعارض مع الكتلة القديمة admin/cars/*
    Route::get('/car-approvals/rejection-reasons', [ApiAdminCarController::class, 'rejectionReasons']);
    Route::get('/car-approvals/bulk-batches/pending', [ApiAdminCarController::class, 'pendingBulkBatches']);
    Route::get('/car-approvals/bulk-batches/{batchId}', [ApiAdminCarController::class, 'showBulkBatch']);
    Route::post('/car-approvals/bulk-batches/{batchId}/approve', [ApiAdminCarController::class, 'approveBulkBatch']);
    Route::post('/car-approvals/bulk-batches/{batchId}/reject', [ApiAdminCarController::class, 'rejectBulkBatch']);
    Route::get('/car-approvals/pending', [ApiAdminCarController::class, 'pending']);
    Route::get('/car-approvals/approved', [ApiAdminCarController::class, 'approved']);
    Route::get('/car-approvals/rejected', [ApiAdminCarController::class, 'rejected']);
    Route::get('/car-approvals/{id}', [ApiAdminCarController::class, 'show'])->whereNumber('id');
    Route::post('/car-approvals/{id}/approve', [ApiAdminCarController::class, 'approve'])->whereNumber('id');
    Route::post('/car-approvals/{id}/reject', [ApiAdminCarController::class, 'reject'])->whereNumber('id');
    Route::post('/car-approvals/{id}/approve-inspection', [ApiAdminCarController::class, 'approveInspection'])->whereNumber('id');

    Route::get('/wallet/withdrawals', [ApiAdminWalletAdminController::class, 'pendingWithdrawals']);
    Route::post('/wallet/withdrawals/{id}/approve', [ApiAdminWalletAdminController::class, 'approve'])->whereNumber('id');
    Route::post('/wallet/withdrawals/{id}/reject', [ApiAdminWalletAdminController::class, 'reject'])->whereNumber('id');
    Route::get('/wallet/frozen', [ApiAdminWalletAdminController::class, 'frozenWallets']);
    Route::post('/wallet/{user}/unfreeze', [ApiAdminWalletAdminController::class, 'unfreeze']);

    // ⬅️ جديد: سجل تدقيق إجراءات الأدمن (read-only)
    Route::get('/audit-logs', [ApiAdminAuditLogController::class, 'index']);

    // ⬅️ جديد: إدارة الحظر بين المستخدمين (كانت فجوة — لا واجهة أدمن لها)
    Route::get('/blocks', [ApiAdminBlockController::class, 'index']);
    Route::delete('/blocks/{block}', [ApiAdminBlockController::class, 'destroy']);
});
