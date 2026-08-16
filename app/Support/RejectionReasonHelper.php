<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RejectionReasonHelper
{
    /**
     * القيمة الخاصة التي تعني أن الأدمن اختار "أخرى" وسيكتب سبباً مخصصاً.
     */
    public const OTHER = 'أخرى';

    /**
     * إرجاع قائمة الأسباب الجاهزة لنوع معيّن (car | ad | rental_ad | promotion).
     */
    public static function list(string $type): array
    {
        return config("rejection_reasons.{$type}", []);
    }

    /**
     * التحقق من صحة سبب الرفض المُرسل من الأدمن وإرجاع النص النهائي الذي سيُخزَّن.
     *
     * - reason: إلزامي، ويجب أن يكون أحد القيم الموجودة في القائمة الجاهزة لهذا النوع.
     * - إذا كانت القيمة المختارة "أخرى"، يصبح حقل custom_reason إلزامياً،
     *   وقيمته هي ما يُخزَّن فعلياً كسبب الرفض.
     *
     * يرمي ValidationException تلقائياً في حال فشل التحقق (نفس سلوك $request->validate()).
     */
    public static function validateAndResolve(Request $request, string $type): string
    {
        $reasons = self::list($type);

        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', Rule::in($reasons)],
            'custom_reason' => ['required_if:reason,' . self::OTHER, 'nullable', 'string', 'max:500'],
        ], [
            'reason.in' => 'الرجاء اختيار سبب رفض من القائمة المتاحة.',
            'custom_reason.required_if' => 'الرجاء كتابة السبب المخصص عند اختيار "أخرى".',
        ]);

        $validated = $validator->validate();

        return $validated['reason'] === self::OTHER
            ? $validated['custom_reason']
            : $validated['reason'];
    }
}
