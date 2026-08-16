<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function index()
    {
        $ratings = Rating::with(['rater.accountType', 'ratedUser.accountType'])
            ->latest()
            ->paginate(10);

        return view('admin.ratings.index', compact('ratings'));
    }

    public function show(Rating $rating)
    {
        $rating->load(['rater.accountType', 'ratedUser.accountType']);

        return view('admin.ratings.show', compact('rating'));
    }

    public function edit(Rating $rating)
    {
        $rating->load(['rater.accountType', 'ratedUser.accountType']);

        return view('admin.ratings.edit', compact('rating'));
    }

    public function update(Request $request, Rating $rating)
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        $rating->update($validated);

        return redirect()
            ->route('admin.ratings.index')
            ->with('success', 'تم تحديث التقييم بنجاح.');
    }

    public function destroy(Rating $rating)
    {
        $rating->delete();

        return redirect()
            ->route('admin.ratings.index')
            ->with('success', 'تم حذف التقييم بنجاح.');
    }
}