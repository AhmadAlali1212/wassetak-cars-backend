<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserSearchController extends Controller
{
    /**
     * البحث عن مستخدم بالاسم/البريد/رقم الهاتف — يفتح الباب لميزات
     * مثل "قيّم المشتري" في البيع العادي، أو أي تواصل داخل التطبيق
     * غير مرتبط بمزاد أو حجز.
     *
     * لا يُرجع بيانات حساسة (لا بريد كامل، لا كلمة مرور، لا مستندات
     * توثيق)، ويستثني حسابات الأدمن من نتائج البحث.
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2|max:100',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $q = trim($request->input('q'));
        $perPage = (int) $request->input('per_page', 15);
        $currentUser = $request->user();

        $users = User::query()
            ->with('accountType')
            ->whereHas('accountType', function ($query) {
                $query->where('name', '!=', 'admin');
            })
            ->where('id', '!=', $currentUser->id)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone_number', 'like', "%{$q}%");
            })
            ->select(['id', 'name', 'email', 'phone_number', 'account_type_id', 'is_verified'])
            ->paginate($perPage);

        return response()->json([
            'message' => 'تم جلب نتائج البحث بنجاح.',
            'users' => $users,
        ]);
    }
}
