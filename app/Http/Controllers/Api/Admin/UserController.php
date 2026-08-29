<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * وحدة Users من Admin API — index/show/update/destroy، منطق مطابق
 * لـ Admin\UserController القديم، مع تعديلين:
 * - update() يستخدم $request->boolean('is_verified') بدل
 *   $request->has('is_verified') القديم (يصلح لـ checkbox HTML فقط)،
 *   لأن الطلبات هنا JSON صريح ويصل الحقل دائماً true/false.
 * - destroy() أصبحت محمية: لا يمكن حذف حسابك الحالي، ولا حذف آخر
 *   حساب admin متبقٍّ بالنظام — كانت هذه فجوة أمنية حقيقية بالكود
 *   القديم (حذف بلا أي حماية).
 */
class UserController extends Controller
{
    private const PER_PAGE = 20;

    public function index()
    {
        $paginator = User::with('accountType')->paginate(self::PER_PAGE);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'message' => 'تم جلب المستخدمين بنجاح.',
        ]);
    }

    public function show(User $user)
    {
        $user->load('accountType');

        return response()->json([
            'success' => true,
            'data' => $user,
            'message' => 'تم جلب بيانات المستخدم بنجاح.',
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone_number' => 'nullable|string|unique:users,phone_number,' . $user->id,
            'account_type_id' => 'required|exists:account_types,id',
            'is_verified' => 'boolean',
            'is_subscribed' => 'boolean',
            'subscription_ends_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'account_type_id' => $request->account_type_id,
            'is_verified' => $request->boolean('is_verified'),
            'is_subscribed' => $request->boolean('is_subscribed'),
            'subscription_ends_at' => $request->subscription_ends_at,
        ]);

        \App\Models\AdminAuditLog::record($request->user(), 'user.update', $user, 'تحديث بيانات مستخدم');

        return response()->json([
            'success' => true,
            'data' => $user->fresh('accountType'),
            'message' => 'تم تحديث بيانات المستخدم بنجاح.',
        ]);
    }

    /**
     * ⬅️ إصلاح: منع حذف الحساب الحالي (تسجيل خروج قسري لنفسك بالخطأ)،
     * ومنع حذف آخر حساب admin متبقٍّ بالنظام (كان يمكن سابقاً تفريغ
     * النظام بالكامل من صلاحيات الإدارة بضغطة واحدة بلا أي تحذير).
     */
    public function destroy(Request $request, User $user)
    {
        if ($request->user() && (int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'لا يمكنك حذف حسابك الحالي.',
            ], 422);
        }

        if ($user->isAdmin()) {
            $otherAdminsCount = User::whereHas('accountType', fn ($q) => $q->where('name', 'admin'))
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherAdminsCount === 0) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'لا يمكن حذف آخر حساب أدمن متبقٍّ بالنظام.',
                ], 422);
            }
        }

        $user->delete();

        \App\Models\AdminAuditLog::record($request->user(), 'user.destroy', null, 'حذف مستخدم', ['deleted_user_id' => $user->id, 'deleted_user_email' => $user->email]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'تم حذف المستخدم بنجاح.',
        ]);
    }

    /**
     * قائمة أنواع الحسابات — تُستخدم لتعبئة قائمة الاختيار بنموذج تعديل
     * المستخدم بلوحة React. مصفوفة مباشرة تحت data (بلا pagination،
     * العدد ثابت وصغير جداً).
     */
    public function accountTypes()
    {
        return response()->json([
            'success' => true,
            'data' => AccountType::all(),
            'message' => 'تم جلب أنواع الحسابات بنجاح.',
        ]);
    }
}
