<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function follow(Request $request, User $company)
    {
        $user = $request->user()->load('accountType');
        $company->load('accountType');

        if (!$user->isMobileUser()) {
            return response()->json([
                'message' => 'المتابعة متاحة فقط لمستخدمي التطبيق.'
            ], 403);
        }

        if (!$company->accountType || $company->accountType->name !== 'company') {
            return response()->json([
                'message' => 'يمكن متابعة الشركات أو معارض السيارات فقط.'
            ], 422);
        }

        if ($user->id === $company->id) {
            return response()->json([
                'message' => 'لا يمكنك متابعة حسابك نفسه.'
            ], 422);
        }

        $alreadyFollowing = $user->following()
            ->where('users.id', $company->id)
            ->exists();

        if ($alreadyFollowing) {
            return response()->json([
                'message' => 'أنت تتابع هذه الشركة بالفعل.',
                'is_following' => true,
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                ],
            ]);
        }

        $user->following()->attach($company->id);

        return response()->json([
            'message' => 'تمت متابعة الشركة بنجاح.',
            'is_following' => true,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
        ], 201);
    }

    public function unfollow(Request $request, User $company)
    {
        $user = $request->user()->load('accountType');
        $company->load('accountType');

        if (!$user->isMobileUser()) {
            return response()->json([
                'message' => 'إلغاء المتابعة متاح فقط لمستخدمي التطبيق.'
            ], 403);
        }

        if (!$company->accountType || $company->accountType->name !== 'company') {
            return response()->json([
                'message' => 'يمكن إلغاء متابعة الشركات أو معارض السيارات فقط.'
            ], 422);
        }

        $user->following()->detach($company->id);

        return response()->json([
            'message' => 'تم إلغاء متابعة الشركة بنجاح.',
            'is_following' => false,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
        ]);
    }

    public function status(Request $request, User $company)
    {
        $user = $request->user()->load('accountType');
        $company->load('accountType');

        if (!$user->isMobileUser()) {
            return response()->json([
                'message' => 'هذه الخدمة متاحة فقط لمستخدمي التطبيق.'
            ], 403);
        }

        if (!$company->accountType || $company->accountType->name !== 'company') {
            return response()->json([
                'message' => 'الحساب المطلوب ليس شركة أو معرض سيارات.'
            ], 422);
        }

        $isFollowing = $user->following()
            ->where('users.id', $company->id)
            ->exists();

        return response()->json([
            'company_id' => $company->id,
            'company_name' => $company->name,
            'is_following' => $isFollowing,
        ]);
    }

    public function myFollowingCompanies(Request $request)
    {
        $user = $request->user()->load('accountType');

        if (!$user->isMobileUser()) {
            return response()->json([
                'message' => 'هذه الخدمة متاحة فقط لمستخدمي التطبيق.'
            ], 403);
        }

        $companies = $user->following()
            ->with('accountType')
            ->whereHas('accountType', function ($query) {
                $query->where('name', 'company');
            })
            ->orderBy('follows.created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'message' => 'تم جلب الشركات التي تتابعها بنجاح.',
            'companies' => $companies,
        ]);
    }
}
