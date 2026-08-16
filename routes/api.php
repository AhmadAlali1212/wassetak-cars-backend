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
use App\Http\Controllers\Api\ProfileController;
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

    Route::post('/rental-bookings', [RentalBookingController::class, 'store']); // إنشاء طلب حجز جديد (pending)
    Route::get('/rental-bookings/my', [RentalBookingController::class, 'myBookings']); // حجوزاتي (كمستأجر)
    Route::get('/rental-bookings/incoming', [RentalBookingController::class, 'incomingBookings']); // الطلبات الواردة على إعلاناتي (كمالك)
    Route::post('/rental-bookings/{id}/approve', [RentalBookingController::class, 'approve']); // موافقة على طلب حجز
    Route::post('/rental-bookings/{id}/reject', [RentalBookingController::class, 'reject']); // رفض طلب حجز
    Route::delete('/rental-bookings/{id}', [RentalBookingController::class, 'cancel']); // إلغاء حجز


    // مسارات إدارة المزادات
    Route::get('/auctions', [AuctionController::class, 'index']); // المزادات العامة النشطة والجارية
    Route::get('/my-auctions', [AuctionController::class, 'myAuctions']); // مزادات المستخدم بكل الحالات
    Route::post('/auctions/close-expired', [AuctionController::class, 'closeExpiredAuctions']);
    Route::post('/auctions', [AuctionController::class, 'store']);

    Route::get('/auctions/{id}', [AuctionController::class, 'show'])->whereNumber('id');
    Route::post('/auctions/{id}/bid', [AuctionController::class, 'placeBid'])->whereNumber('id');
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
