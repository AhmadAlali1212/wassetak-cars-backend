<?php

namespace App\Services;

/**
 * فلترة محتوى نصي بسيطة (قائمة كلمات محظورة) — تُستخدم على تعليقات
 * التقييمات، الرسائل، ووصف النزاعات. هذا ليس نظام كشف ذكاء اصطناعي؛
 * هو خط دفاع أول بسيط وسريع يرفض الكلام المسيء الصريح والواضح، بينما
 * الحالات الأدق (سياق، سخرية، إلخ) تبقى تحتاج مراجعة بشرية عبر نظام
 * البلاغات (ReportController).
 *
 * ⚠️ القائمة أدناه عامة وقصيرة عمداً (تفادياً لتضمين ألفاظ فعلية بالكود
 * المصدري بشكل يسيء استخدامه). يُنصح بإكمالها لاحقاً من قائمة أشمل
 * (عربي/إنجليزي) حسب حاجتكم الفعلية، أو استبدالها بخدمة خارجية
 * (OpenAI Moderation API، Google Perspective API) للحصول على دقة أعلى.
 */
class ProfanityFilterService
{
    /**
     * كلمات/عبارات يُرفض النص بالكامل إذا احتواها (بلا حساسية لحالة
     * الأحرف أو المسافات الزائدة). أضف كلماتك الفعلية هنا مباشرة.
     */
    private static array $blockedTerms = [
        // أمثلة عامة فقط — استبدلها بقائمتك الفعلية
        'نصاب',
        'حرامي',
        'كلب ابن',
        'scam',
        'fraudster',
    ];

    /**
     * فحص نص وإرجاع أول كلمة محظورة وُجدت (أو null إن كان النص نظيفاً).
     */
    public static function findBlockedTerm(?string $text): ?string
    {
        if (!$text) {
            return null;
        }

        $normalized = mb_strtolower(trim($text));

        foreach (self::$blockedTerms as $term) {
            if (mb_strpos($normalized, mb_strtolower($term)) !== false) {
                return $term;
            }
        }

        return null;
    }

    public static function isClean(?string $text): bool
    {
        return self::findBlockedTerm($text) === null;
    }
}
