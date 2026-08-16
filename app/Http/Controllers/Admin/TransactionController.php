<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with('user')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $transactions = $query->paginate(20)->withQueryString();

        return view('admin.transactions.index', compact('transactions'));
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['user', 'transactionable']);

        return view('admin.transactions.show', compact('transaction'));
    }

    /**
     * السجل المالي غير قابل للتعديل أو الحذف يدوياً.
     * الموافقة والرفض يتمان فقط من endpoints السحب المخصصة مع قفل ومعاملة قاعدة بيانات.
     */
    public function edit(Transaction $transaction)
    {
        abort(405, 'لا يمكن تعديل السجل المالي يدوياً.');
    }

    public function update(Request $request, Transaction $transaction)
    {
        abort(405, 'لا يمكن تعديل السجل المالي يدوياً.');
    }

    public function destroy(Transaction $transaction)
    {
        abort(405, 'لا يمكن حذف السجل المالي.');
    }
}
