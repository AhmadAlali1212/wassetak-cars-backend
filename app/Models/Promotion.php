<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        // معرف المستخدم
        'user_id',
        // عنوان العرض
        'title',
        // وصف العرض
        'description',
        // نوع العرض
        'type',
        // قيمة العرض
        'value',
        // تاريخ بدء العرض
        'start_date',
        // تاريخ انتهاء العرض
        'end_date',
        // كمية العرض
        'quantity',
        // حالة العرض
        'status',
        // سبب الرفض (إن وُجد)
        'rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    // علاقة مع المستخدم (الشركة/المعرض الذي أنشأ العرض)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
