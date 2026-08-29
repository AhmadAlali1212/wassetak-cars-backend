<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * تسجيل إجراء أدمن — استدعاء واحد بسيط من أي كنترولر:
     * AdminAuditLog::record($request->user(), 'ad.update', $ad, 'تحديث حالة الإعلان إلى active');
     */
    public static function record($admin, string $action, $subject = null, ?string $description = null, array $meta = []): void
    {
        if (!$admin) {
            return;
        }

        static::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject->id ?? null,
            'description' => $description,
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }
}
