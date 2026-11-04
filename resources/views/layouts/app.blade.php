@php
    $locale = app()->getLocale();
    $brand = config('kalta.name');
    $home = url('/');

    $graph = [
        [
            '@type' => 'Organization',
            '@id' => $home . '#organization',
            'name' => $brand,
            'url' => $home,
            'logo' => asset('img/android-chrome-512x512.png'),
            'sameAs' => array_values(array_filter([config('kalta.telegram')])),
        ],
        [
            '@type' => 'WebSite',
            '@id' => $home . '#website',
            'url' => $home,
            'name' => $brand,
            'inLanguage' => $locale,
            'publisher' => ['@id' => $home . '#organization'],
        ],
    ];

    if ($trail || $breadcrumbs) {
        $crumbs = [['name' => __('Home'), 'url' => lroute('home')], ...$breadcrumbs, ['name' => $title ?? $pageTitle, 'url' => $canonicalUrl]];
        $graph[] = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->map(fn ($crumb, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])->all(),
        ];
    }

    $statusText = match (session('status')) {
        null => null,
        'profile-updated', 'password-updated' => __('Saved.'),
        'verification-link-sent' => __('A new verification link has been sent to your email address.'),
        default => __(session('status')),
    };

    $clientConfig = [
        'locale' => $locale,
        'alternates' => $alternateUrls,
        'i18n' => [
            'copy' => __('Copy'),
            'copied' => __('Copied!'),
            'open' => __('Open'),
            'error' => __('Something went wrong. Please try again.'),
            'offline' => __('No connection. Check your internet and try again.'),
            'clear' => __('Clear'),
            'delete' => __('Remove'),
            'chooseFile' => __('Choose a file'),
            'uploading' => __('Uploading…'),
            'shortening' => __('Shortening…'),
            'generating' => __('Generating…'),
            'tooBig' => __('This file is too big.'),
            'expired' => __('Your session expired. Reload the page and try again.'),
            'retrying' => __('Connection problem. Trying again…'),
            'stillOffline' => __('We could not reach the server. Your details are still on this page: check your connection and tap the button again.'),
            'linkPrompt' => __('Link address (empty to remove the link)'),
            'tools' => [
                'bold' => __('Bold'), 'italic' => __('Italic'), 'heading' => __('Heading'), 'subheading' => __('Subheading'),
                'quote' => __('Quote'), 'list' => __('List'), 'link' => __('Link'), 'image' => __('Add a picture'),
            ],
        ],
    ];

    $ld = ['@context' => 'https://schema.org', '@graph' => array_merge($graph, $jsonld)];
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="{{ $robots }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @if ($alternates && $alternateUrls)
        @foreach ($alternateUrls as $code => $href)
            <link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ $alternateUrls[config('kalta.default_locale')] }}">
    @endif
    @if (config('kalta.yandex_verification'))
        <meta name="yandex-verification" content="{{ config('kalta.yandex_verification') }}">
    @endif

    <meta property="og:site_name" content="{{ $brand }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="{{ $imageSize[0] ?? 1200 }}">
    <meta property="og:image:height" content="{{ $imageSize[1] ?? 630 }}">
    <meta property="og:locale" content="{{ config('kalta.og_locales.' . $locale) }}">
    @foreach (config('kalta.og_locales') as $code => $ogLocale)
        @if ($code !== $locale)
            <meta property="og:locale:alternate" content="{{ $ogLocale }}">
        @endif
    @endforeach
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#fffaf3" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#15110d" media="(prefers-color-scheme: dark)">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ asset('img/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    <script>try{var t=localStorage.getItem('kalta-theme');if(t==='dark'||t==='light')document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
    <link rel="preload" href="{{ asset('fonts/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @if ($locale === 'ru')
        <link rel="preload" href="{{ asset('fonts/inter-cyrillic-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @endif
    <link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
    @stack('head')

    <script type="application/ld+json">{!! json_encode($ld, $jsonFlags) !!}</script>
</head>

<body class="{{ $bodyClass }}">
    <a class="skip-link" href="#main">{{ __('Skip to content') }}</a>

    @unless ($minimal)
        @include('layouts.partials.header')
    @endunless

    <main id="main" tabindex="-1">
        @unless ($minimal)
            @if (session('success') || session('error') || $statusText)
                <div class="container flash">
                    @if (session('success'))
                        <div class="alert alert-success" role="status"><x-i name="check" />{{ session('success') }}</div>
                    @endif
                    @if ($statusText)
                        <div class="alert alert-success" role="status"><x-i name="check" />{{ $statusText }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-error" role="alert"><x-i name="alert" />{{ session('error') }}</div>
                    @endif
                </div>
            @endif
        @endunless

        {{ $slot }}
    </main>

    @unless ($minimal)
        @include('layouts.partials.footer')
    @endunless

    <div class="toast" data-toast role="status" aria-live="polite"></div>

    <script>window.K = {!! json_encode($clientConfig, $jsonFlags) !!}</script>
    <script src="{{ asset_v('js/app.js') }}" defer></script>
    @stack('scripts')

    @if ($ads && !$minimal && config('kalta.ads.yandex'))
        @include('layouts.partials.ads')
    @endif
</body>

</html>
