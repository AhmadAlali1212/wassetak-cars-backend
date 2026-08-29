<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * وحدة Ratings من Admin API — نسخ حرفي 1:1 من Admin\RatingController
 * القديم (لا status، لا انتقالات حالة، لا خدمات إشعارات — أبسط وحدة).
 * لا يوجد أي مسار JSON قديم لـ admin/ratings بـ routes/api.php، فبُنيت
 * الأربعة endpoints كاملة بلا نطاق مُقلَّص.
 */
class RatingController extends Controller
{
    private const PER_PAGE = 20;

    private function transform(Rating $rating): array
    {
        return [
            'id' => $rating->id,
            'rater' => $rating->rater ? [
                'id' => $rating->rater->id,
                'name' => $rating->rater->name,
                'email' => $rating->rater->email,
            ] : null,
            'rated_user' => $rating->ratedUser ? [
                'id' => $rating->ratedUser->id,
                'name' => $rating->ratedUser->name,
                'email' => $rating->ratedUser->email,
            ] : null,
            'rating' => $rating->rating,
            'comment' => $rating->comment,
            'ad_id' => $rating->ad_id,
            'auction_id' => $rating->auction_id,
            'rental_booking_id' => $rating->rental_booking_id,
            'created_at' => $rating->created_at,
            'updated_at' => $rating->updated_at,
        ];
    }

    public function index()
    {
        $paginator = Rating::with(['rater.accountType', 'ratedUser.accountType'])
            ->latest()
            ->paginate(self::PER_PAGE);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => collect($paginator->items())->map(fn ($r) => $this->transform($r))->all(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'message' => 'تم جلب التقييمات بنجاح.',
        ]);
    }

    public function show(Rating $rating)
    {
        $rating->load(['rater.accountType', 'ratedUser.accountType']);

        return response()->json([
            'success' => true,
            'data' => $this->transform($rating),
            'message' => 'تم جلب تفاصيل التقييم بنجاح.',
        ]);
    }

    public function update(Request $request, Rating $rating)
    {
        $validator = Validator::make($request->all(), [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $rating->update($validator->validated());

        \App\Models\AdminAuditLog::record($request->user(), 'rating.update', $rating, 'تعديل تقييم');

        return response()->json([
            'success' => true,
            'data' => $this->transform($rating->fresh(['rater.accountType', 'ratedUser.accountType'])),
            'message' => 'تم تحديث التقييم بنجاح.',
        ]);
    }

    public function destroy(Request $request, Rating $rating)
    {
        $ratingId = $rating->id;
        $rating->delete();

        \App\Models\AdminAuditLog::record($request->user(), 'rating.destroy', null, 'حذف تقييم', ['rating_id' => $ratingId]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'تم حذف التقييم بنجاح.',
        ]);
    }
}
