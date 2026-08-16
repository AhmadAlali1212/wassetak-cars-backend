<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\Request;

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
            ->with('rater.accountType')
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'تم جلب التقييمات بنجاح.',
            'ratings' => $ratings,
            'average_rating' => round((float) $user->receivedRatings()->avg('rating'), 2),
            'ratings_count' => $user->receivedRatings()->count(),
        ]);
    }

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

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        $alreadyRated = Rating::where('rater_id', $rater->id)
            ->where('rated_user_id', $ratedUser->id)
            ->exists();

        if ($alreadyRated) {
            return response()->json([
                'message' => 'لقد قمت بتقييم هذا المستخدم مسبقاً.'
            ], 422);
        }

        $rating = Rating::create([
            'rater_id' => $rater->id,
            'rated_user_id' => $ratedUser->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'تم إنشاء التقييم بنجاح.',
            'rating' => $rating->load(['rater.accountType', 'ratedUser.accountType']),
        ], 201);
    }
}