<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AccountType;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('accountType')->paginate(10); // جلب المستخدمين مع نوع الحساب
        return view('admin.users.index', compact('users'));
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return view('admin.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $accountTypes = AccountType::all();
        return view('admin.users.edit', compact('user', 'accountTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone_number' => 'nullable|string|unique:users,phone_number,' . $user->id,
            'account_type_id' => 'required|exists:account_types,id',
            'is_verified' => 'boolean',
            'is_subscribed' => 'boolean',
            'subscription_ends_at' => 'nullable|date',
            // يمكنك إضافة المزيد من التحقق حسب الحاجة
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'account_type_id' => $request->account_type_id,
            'is_verified' => $request->has('is_verified'), // للتأكد من قيمة checkbox
            'is_subscribed' => $request->has('is_subscribed'),
            'subscription_ends_at' => $request->subscription_ends_at,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'تم تحديث بيانات المستخدم بنجاح.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'تم حذف المستخدم بنجاح.');
    }
}
