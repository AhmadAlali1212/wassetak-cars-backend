<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Auction;
use App\Models\Rating;
use App\Models\RentalBooking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RatingController extends Controller
{
    public function index(User $user)
    {
        $user->load('accountType');

        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'لا يمكن عرض تقييمات مدير النظام.'
            ], 403);
        }

        $ratings = $user->receivedRatings()
            ->with(['rater.accountType', 'ad.car', 'auction.car', 'rentalBooking.rentalAd.car'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'تم جلب التقييمات بنجاح.',
            'ratings' => $ratings,
            'average_rating' => round((float) $user->receivedRatings()->avg('rating'), 2),
            'ratings_count' => $user->receivedRatings()->count(),
        ]);
    }

    /**
     * إنشاء تقييم — أصبح مربوطاً بشكل صارم بمعاملة فعلية حقيقية ومكتملة
     * (إعلان بيع مباع، أو مزاد مكتمل، أو حجز تأجير مكتمل)، بدل السماح
     * بتقييم حر بين أي مستخدمين بلا أي دليل على تعامل حقيقي بينهما.
     *
     * body: { rating, comment?, type: 'ad'|'auction'|'rental_booking', transaction_id }
     */
    public function store(Request $request, User $user)
    {
        $rater = $request->user()->load('accountType');
        $ratedUser = $user->load('accountType');

        if (!$rater->isMobileUser()) {
            return response()->json([
                'message' => 'التقييم متاح فقط للمستخدم الفردي والشركة.'
            ], 403);
        }

        if (!$ratedUser->isMobileUser()) {
            return response()->json([
                'message' => 'لا يمكن تقييم هذا الحساب.'
            ], 403);
        }

        if ($rater->id === $ratedUser->id) {
            return response()->json([
                'message' => 'لا يمكنك تقييم نفسك.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'in:ad,auction,rental_booking'],
            'transaction_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // ⬅️ جديد: فحص محتوى أساسي على تعليق التقييم
        if (!\App\Services\ProfanityFilterService::isClean($request->input('comment'))) {
            return response()->json([
                'message' => 'تعليقك يحتوي على كلمات غير مسموح بها.',
            ], 422);
        }

        $type = $request->input('type');
        $transactionId = (int) $request->input('transaction_id');

        // كل نوع معاملة له فحص مستقل: يجب أن تكون مكتملة فعلياً، وأن
        // يكون طرفا التقييم (المُقيِّم والمُقيَّم) هما بالضبط طرفي هذه
        // المعاملة (بأي ترتيب)، بدون استثناء.
        [$isValid, $errorMessage, $referenceColumn] = match ($type) {
            'ad' => $this->validateAdTransaction($transactionId, $rater->id, $ratedUser->id),
            'auction' => $this->validateAuctionTransaction($transactionId, $rater->id, $ratedUser->id),
            'rental_booking' => $this->validateRentalBookingTransaction($transactionId, $rater->id, $ratedUser->id),
            default => [false, 'نوع معاملة غير مدعوم.', null],
        };

        if (!$isValid) {
            return response()->json(['message' => $errorMessage], 422);
        }

        $alreadyRated = Rating::where('rater_id', $rater->id)
            ->where($referenceColumn, $transactionId)
            ->exists();

        if ($alreadyRated) {
            return response()->json([
                'message' => 'لقد قيّمت هذه المعاملة مسبقاً.'
            ], 422);
        }

        $rating = Rating::create([
            'rater_id' => $rater->id,
            'rated_user_id' => $ratedUser->id,
            'rating' => $request->input('rating'),
            'comment' => $request->input('comment'),
            $referenceColumn => $transactionId,
        ]);

        return response()->json([
            'message' => 'تم إنشاء التقييم بنجاح.',
            'rating' => $rating->load(['rater.accountType', 'ratedUser.accountType']),
        ], 201);
    }

    /**
     * إعلان بيع: يجب أن يكون status = 'sold' مع buyer_id محدد، وطرفا
     * التقييم هما بالضبط البائع (user_id) والمشتري (buyer_id).
     */
    private function validateAdTransaction(int $adId, int $raterId, int $ratedUserId): array
    {
        $ad = Ad::find($adId);

        if (!$ad) {
            return [false, 'الإعلان غير موجود.', null];
        }

        if ($ad->status !== 'sold' || !$ad->buyer_id) {
            return [false, 'لا يمكن تقييم هذه المعاملة إلا بعد اكتمال البيع وتحديد المشتري.', null];
        }

        $parties = [(int) $ad->user_id, (int) $ad->buyer_id];
        if (!in_array($raterId, $parties, true) || !in_array($ratedUserId, $parties, true) || $raterId === $ratedUserId) {
            return [false, 'لا يمكنك تقييم مستخدم لم يشترك معك بهذه الصفقة.', null];
        }

        return [true, null, 'ad_id'];
    }

    /**
     * مزاد: يجب أن يكون final_price_status = 'completed' (تم تسليم/استلام
     * السيارة فعلياً وتأكيد الصفقة، وليس فقط قبول السعر)، مع winner_id
     * محدد، وطرفا التقييم هما بالضبط صاحب المزاد والفائز.
     */
    private function validateAuctionTransaction(int $auctionId, int $raterId, int $ratedUserId): array
    {
        $auction = Auction::find($auctionId);

        if (!$auction) {
            return [false, 'المزاد غير موجود.', null];
        }

        if ($auction->final_price_status !== 'completed' || !$auction->winner_id) {
            return [false, 'لا يمكن تقييم هذا المزاد إلا بعد اكتمال الصفقة فعلياً.', null];
        }

        $parties = [(int) $auction->user_id, (int) $auction->winner_id];
        if (!in_array($raterId, $parties, true) || !in_array($ratedUserId, $parties, true) || $raterId === $ratedUserId) {
            return [false, 'لا يمكنك تقييم مستخدم لم يشترك معك بهذا المزاد.', null];
        }

        return [true, null, 'auction_id'];
    }

    /**
     * حجز تأجير: يجب أن يكون status = 'completed' (تم تأكيد التسليم
     * والإرجاع فعلياً)، وطرفا التقييم هما بالضبط مالك الإعلان والمستأجر.
     */
    private function validateRentalBookingTransaction(int $bookingId, int $raterId, int $ratedUserId): array
    {
        $booking = RentalBooking::with('rentalAd')->find($bookingId);

        if (!$booking) {
            return [false, 'الحجز غير موجود.', null];
        }

        if ($booking->status !== 'completed' || !$booking->returned_at) {
            return [false, 'لا يمكن تقييم هذا الحجز إلا بعد اكتمال التسليم والإرجاع فعلياً.', null];
        }

        if (!$booking->rentalAd) {
            return [false, 'بيانات إعلان التأجير غير متوفرة لهذا الحجز.', null];
        }

        $parties = [(int) $booking->rentalAd->user_id, (int) $booking->user_id];
        if (!in_array($raterId, $parties, true) || !in_array($ratedUserId, $parties, true) || $raterId === $ratedUserId) {
            return [false, 'لا يمكنك تقييم مستخدم لم يشترك معك بهذا الحجز.', null];
        }

        return [true, null, 'rental_booking_id'];
    }

    /**
     * ⬅️ جديد: المعاملات المكتملة التي يحق للمستخدم الحالي تقييمها الآن
     * ولم يقيّمها بعد — يستخدمها الفرونت لعرض "قيّم هذه الصفقة" فقط
     * حيث يوجد فعلاً ما يستحق التقييم، بدل زر تقييم عام على أي بروفايل.
     */
    public function ratable(Request $request)
    {
        $me = $request->user();

        $ratedAdIds = Rating::where('rater_id', $me->id)->whereNotNull('ad_id')->pluck('ad_id');
        $ratedAuctionIds = Rating::where('rater_id', $me->id)->whereNotNull('auction_id')->pluck('auction_id');
        $ratedBookingIds = Rating::where('rater_id', $me->id)->whereNotNull('rental_booking_id')->pluck('rental_booking_id');

        $ads = Ad::with('car')
            ->where('status', 'sold')
            ->whereNotNull('buyer_id')
            ->where(function ($q) use ($me) {
                $q->where('user_id', $me->id)->orWhere('buyer_id', $me->id);
            })
            ->whereNotIn('id', $ratedAdIds)
            ->get()
            ->map(function ($ad) use ($me) {
                $otherUserId = (int) $ad->user_id === (int) $me->id ? $ad->buyer_id : $ad->user_id;
                return [
                    'type' => 'ad',
                    'transaction_id' => $ad->id,
                    'title' => $ad->car ? "{$ad->car->make} {$ad->car->model} {$ad->car->year}" : "إعلان #{$ad->id}",
                    'other_user_id' => $otherUserId,
                    'other_user_name' => User::find($otherUserId)?->name,
                ];
            });

        $auctions = Auction::with('car')
            ->where('final_price_status', 'completed')
            ->whereNotNull('winner_id')
            ->where(function ($q) use ($me) {
                $q->where('user_id', $me->id)->orWhere('winner_id', $me->id);
            })
            ->whereNotIn('id', $ratedAuctionIds)
            ->get()
            ->map(function ($auction) use ($me) {
                $otherUserId = (int) $auction->user_id === (int) $me->id ? $auction->winner_id : $auction->user_id;
                return [
                    'type' => 'auction',
                    'transaction_id' => $auction->id,
                    'title' => $auction->car ? "{$auction->car->make} {$auction->car->model} {$auction->car->year}" : "مزاد #{$auction->id}",
                    'other_user_id' => $otherUserId,
                    'other_user_name' => User::find($otherUserId)?->name,
                ];
            });

        $bookings = RentalBooking::with('rentalAd.car')
            ->where('status', 'completed')
            ->whereNotNull('returned_at')
            ->whereHas('rentalAd')
            ->where(function ($q) use ($me) {
                $q->where('user_id', $me->id)
                    ->orWhereHas('rentalAd', function ($q2) use ($me) {
                        $q2->where('user_id', $me->id);
                    });
            })
            ->whereNotIn('id', $ratedBookingIds)
            ->get()
            ->filter(fn ($b) => $b->rentalAd !== null)
            ->map(function ($booking) use ($me) {
                $ownerId = (int) $booking->rentalAd->user_id;
                $otherUserId = $ownerId === (int) $me->id ? $booking->user_id : $ownerId;
                $car = $booking->rentalAd->car;
                return [
                    'type' => 'rental_booking',
                    'transaction_id' => $booking->id,
                    'title' => $car ? "{$car->make} {$car->model} {$car->year}" : "حجز #{$booking->id}",
                    'other_user_id' => $otherUserId,
                    'other_user_name' => User::find($otherUserId)?->name,
                ];
            })
            ->values();

        return response()->json([
            'message' => 'تم جلب المعاملات القابلة للتقييم بنجاح.',
            'ratable' => array_values([...$ads->all(), ...$auctions->all(), ...$bookings->all()]),
        ]);
    }
}
