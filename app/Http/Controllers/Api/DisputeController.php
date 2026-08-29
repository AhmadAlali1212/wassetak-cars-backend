<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Auction;
use App\Models\Dispute;
use App\Models\RentalBooking;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * نظام نزاعات مرتبط بمعاملة فعلية — بديل حقيقي عن "لا آلية تصعيد للأدمن"
 * عند مشاكل مثل "دفعت والسيارة مو متل الوصف" أو "المؤجِّر ما رجّع السيارة".
 *
 * بعكس التقييمات (Ratings)، لا نشترط اكتمال المعاملة بالكامل — يمكن رفع
 * نزاع أثناء صفقة جارية فعلياً (حجز تأجير لسا مؤكد ولم يُرجَع مثلاً)،
 * لأن الغاية هنا حل مشكلة حالية لا تقييم تجربة منتهية.
 */
class DisputeController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'against_user_id' => 'required|integer|exists:users,id',
            'transaction_type' => 'required|in:ad,auction,rental_booking',
            'transaction_id' => 'required|integer',
            'category' => [
                'required',
                'in:payment_issue,item_not_as_described,no_delivery,no_return,no_payment,damaged_item,other',
            ],
            'description' => 'required|string|min:10|max:3000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $raiser = $request->user();
        $againstUserId = (int) $request->against_user_id;

        if ($raiser->id === $againstUserId) {
            return response()->json(['message' => 'لا يمكنك رفع نزاع ضد نفسك.'], 422);
        }

        [$isValid, $errorMessage] = match ($request->transaction_type) {
            'ad' => $this->validateAdParties($request->transaction_id, $raiser->id, $againstUserId),
            'auction' => $this->validateAuctionParties($request->transaction_id, $raiser->id, $againstUserId),
            'rental_booking' => $this->validateRentalBookingParties($request->transaction_id, $raiser->id, $againstUserId),
            default => [false, 'نوع معاملة غير مدعوم.'],
        };

        if (!$isValid) {
            return response()->json(['message' => $errorMessage], 422);
        }

        $dispute = Dispute::create([
            'raised_by' => $raiser->id,
            'against_user_id' => $againstUserId,
            'transaction_type' => $request->transaction_type,
            'transaction_id' => $request->transaction_id,
            'category' => $request->category,
            'description' => $request->description,
            'status' => 'open',
        ]);

        NotificationService::send(
            $againstUserId,
            'تم رفع نزاع بخصوص معاملة معك',
            'رفع طرف آخر نزاعاً بخصوص معاملة تخصكما. سيتواصل فريق الدعم لمراجعتها.',
            'dispute_raised',
            ['dispute_id' => $dispute->id]
        );

        // إشعار كل حسابات الأدمن — لا لوحة أدمن مخصصة ضمن هذا التسليم بعد،
        // فهذا أقرب مسار تصعيد فوري متاح حالياً.
        User::whereHas('accountType', fn ($q) => $q->where('name', 'admin'))
            ->pluck('id')
            ->each(function ($adminId) use ($dispute) {
                NotificationService::send(
                    $adminId,
                    'نزاع جديد يحتاج مراجعة',
                    "نزاع جديد (#{$dispute->id}) من نوع {$dispute->category} بحاجة لمراجعتك.",
                    'dispute_needs_review',
                    ['dispute_id' => $dispute->id]
                );
            });

        return response()->json([
            'message' => '✅ تم تسجيل النزاع، سيتواصل فريق الدعم لمراجعته.',
            'dispute' => $dispute,
        ], 201);
    }

    /** نزاعاتي (رفعتها أو مرفوعة ضدي). */
    public function myDisputes(Request $request)
    {
        $userId = $request->user()->id;

        $disputes = Dispute::with(['raisedByUser:id,name', 'againstUser:id,name'])
            ->where('raised_by', $userId)
            ->orWhere('against_user_id', $userId)
            ->latest()
            ->paginate(15);

        return response()->json([
            'message' => 'تم جلب نزاعاتك بنجاح.',
            'disputes' => $disputes,
        ]);
    }

    private function validateAdParties(int $adId, int $userA, int $userB): array
    {
        $ad = Ad::find($adId);
        if (!$ad) {
            return [false, 'الإعلان غير موجود.'];
        }

        // buyer_id قد لا يكون محدداً بعد (مثلاً نزاع قبل تعليم الإعلان
        // كمباع رسمياً) — بهذه الحالة الطرف الآخر المسموح هو صاحب الإعلان فقط.
        $parties = array_filter([(int) $ad->user_id, $ad->buyer_id ? (int) $ad->buyer_id : null]);

        if (!in_array($userA, $parties, true) || !in_array($userB, $parties, true)) {
            return [false, 'لا يمكنك رفع نزاع على معاملة لست طرفاً فيها.'];
        }

        return [true, null];
    }

    private function validateAuctionParties(int $auctionId, int $userA, int $userB): array
    {
        $auction = Auction::find($auctionId);
        if (!$auction) {
            return [false, 'المزاد غير موجود.'];
        }

        $parties = array_filter([(int) $auction->user_id, $auction->winner_id ? (int) $auction->winner_id : null]);

        if (!in_array($userA, $parties, true) || !in_array($userB, $parties, true)) {
            return [false, 'لا يمكنك رفع نزاع على مزاد لست طرفاً فيه.'];
        }

        return [true, null];
    }

    private function validateRentalBookingParties(int $bookingId, int $userA, int $userB): array
    {
        $booking = RentalBooking::with('rentalAd')->find($bookingId);
        if (!$booking || !$booking->rentalAd) {
            return [false, 'الحجز غير موجود.'];
        }

        $parties = [(int) $booking->rentalAd->user_id, (int) $booking->user_id];

        if (!in_array($userA, $parties, true) || !in_array($userB, $parties, true)) {
            return [false, 'لا يمكنك رفع نزاع على حجز لست طرفاً فيه.'];
        }

        return [true, null];
    }
}
