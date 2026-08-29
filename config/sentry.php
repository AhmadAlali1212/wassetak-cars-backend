<?php

return [
    // فارغ افتراضياً = Sentry معطّل تماماً بأمان (لا استدعاءات شبكة، لا أخطاء)
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    // نسبة العينات لتتبّع الأداء (0.0 إلى 1.0) — 0.2 تعني 20% من الطلبات فقط،
    // تفادياً لإرسال كل شيء (تكلفة/حصة مجانية محدودة على Sentry)
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.2),

    // لا نرسل بيانات المستخدم الحساسة (بريد/هاتف) تلقائياً لخدمة خارجية
    'send_default_pii' => false,

    'environment' => env('APP_ENV', 'production'),
];
