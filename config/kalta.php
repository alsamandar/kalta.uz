<?php

return [

    'name' => 'Kalta.uz',

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    | The default locale is served from the site root (/), the others from a
    | prefix (/ru). English was dropped in 2026-11: /en/... redirects to the Uzbek page. Keep the default locale first.
    */
    'default_locale' => 'uz',

    'locales' => [
        'uz' => 'Oʻzbekcha',
        'ru' => 'Русский',
    ],

    'og_locales' => ['uz' => 'uz_UZ', 'ru' => 'ru_RU'],

    // Carbon has Cyrillic Uzbek as "uz"; the Latin script is "uz_Latn"
    'carbon_locales' => ['uz' => 'uz_Latn', 'ru' => 'ru'],

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    | Set SEO_INDEX=false on staging copies so they are not indexed.
    */
    'seo_index' => env('SEO_INDEX', true),

    // Wedding times are entered and shown as wall-clock time in this zone
    'timezone' => env('KALTA_TIMEZONE', 'Asia/Tashkent'),

    'telegram' => env('KALTA_TELEGRAM', 'https://t.me/kalta_uz'),

    'yandex_verification' => env('YANDEX_VERIFICATION', '347b5470aee188ad'),

    'contact_email' => env('KALTA_CONTACT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    | The effective upload limit is also capped by php.ini (see upload_limit_kb()).
    */
    'upload_max_kb' => 20480,

    'max_guests' => 300,

    'blocked_extensions' => [
        'php', 'phtml', 'phar', 'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'pif',
        'vbs', 'vbe', 'wsf', 'wsh', 'ps1', 'jar', 'dll', 'sh',
    ],

    // Short codes that would collide with real routes
    'reserved_words' => [
        'admin', 'login', 'files', 'codes', 'bio', 'qr', 'ru', 'en', 'uz', 'lang', 'api',
        'created', 'profile', 'register', 'sitemap', 'robots', 'storage', 'images', 'fonts',
        'posts', 'media', 'preview', 'invitations', 'dashboard', 'i',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ads
    |--------------------------------------------------------------------------
    */
    'ads' => [
        'yandex' => env('ADS_YANDEX', true),
        'top_block' => 'R-A-12315204-1',
        'floor_block' => 'R-A-12315204-3',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare
    |--------------------------------------------------------------------------
    | The site sits behind Cloudflare, so the TCP peer is a Cloudflare edge IP.
    | CF-Connecting-IP is only trusted when the request really came from here.
    | https://www.cloudflare.com/ips/
    */
    'cloudflare_ranges' => [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ],
];
