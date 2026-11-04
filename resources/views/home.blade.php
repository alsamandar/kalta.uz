@php
    $locale = app()->getLocale();
    $maxMb = max(1, round(upload_limit_kb() / 1024));


    $faq = [
        [__('Is Kalta.uz free?'), __('Yes. Shortening links, creating QR codes and sharing files or text is free and does not require an account.')],
        [__('Do short links expire?'), __('Short links have no expiry date. Text snippets are removed at the time you choose when you create them.')],
        [__('How big can an uploaded file be?'), __('Up to :mb MB per file. Programs and scripts such as .exe or .php files are not accepted.', ['mb' => $maxMb])],
        [__('Can I get a QR code for my link?'), __('Yes. Every short link comes with a QR code you can download as PNG or SVG. You can also make a QR code for any text on the QR code page.')],
        [__('What is a link-in-bio page?'), __('It is a single page that gathers all your links — Instagram, Telegram, X, GitHub, your website — behind one short address. Perfect for a social media profile.')],
        [__('Which links are not allowed?'), __('Links must start with http:// or https://. Please do not use Kalta.uz for spam, phishing or illegal content — such links may be removed.')],
    ];

    $jsonld = [
        [
            '@type' => 'WebApplication',
            'name' => 'Kalta.uz',
            'url' => lroute('home'),
            'applicationCategory' => 'UtilitiesApplication',
            'operatingSystem' => 'Any',
            'inLanguage' => $locale,
            'description' => __('Shorten long links, create QR codes, share files and text snippets, build a link-in-bio page or a wedding invitation. Free, no sign-up.'),
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'UZS'],
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => collect($faq)->map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item[1]],
            ])->all(),
        ],
    ];
@endphp
<x-app-layout
    :title="__('Kalta.uz — Free URL Shortener, QR Codes & File Sharing')"
    :description="__('Shorten long links, create QR codes, share files and text snippets, build a link-in-bio page or a wedding invitation. Free, no sign-up.')"
    :alternates="true"
    :jsonld="$jsonld">

    @push('head')
        <link rel="stylesheet" href="{{ asset_v('css/home.css') }}">
    @endpush

    {{-- one job on the first screen, like the download sites people already know: paste a link, get a short one; the other tools live in the menu --}}
    <section class="home-hero" id="shorten">
        <div class="container narrow">
            <h1>{{ __('Make a long link short') }}</h1>
            <p class="home-lead">{{ __('Paste it and get a short link with a QR code.') }}</p>

            <form class="shortener" method="POST" action="{{ lroute('shorts.make') }}" data-shortener novalidate>
                @csrf
                <x-submission />
                <label class="sr-only" for="url">{{ __('Long link') }}</label>
                <div class="shortener-field">
                    <input id="url" name="url" type="text" inputmode="url" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                        placeholder="{{ __('Paste your long link here…') }}" value="{{ old('url') }}" required
                        @if ($errors->has('url')) aria-invalid="true" aria-describedby="url-error" @endif>
                    <button type="submit" class="shortener-go" aria-label="{{ __('Shorten') }}"><x-i name="arrow-right" :size="26" /><span class="sr-only" data-label>{{ __('Shorten') }}</span></button>
                </div>
                <p class="field-error" id="url-error" data-error role="alert" @unless ($errors->has('url')) hidden @endunless>
                    @if ($errors->has('url')) <x-i name="alert" :size="18" />{{ $errors->first('url') }} @endif
                </p>
            </form>

            <div data-result aria-live="polite"></div>

            <section class="history" data-history hidden aria-labelledby="history-title">
                <div class="history-head">
                    <h2 class="eyebrow-label" id="history-title">{{ __('Your recent links') }}</h2>
                    <button type="button" class="btn btn-ghost btn-sm" data-history-clear>{{ __('Clear') }}</button>
                </div>
                <ul data-history-list></ul>
            </section>

            <p class="hero-note">{{ __('By using Kalta.uz you agree not to share spam, phishing or illegal content.') }}</p>
        </div>
    </section>

    <section class="home-more" id="faq" aria-labelledby="faq-title">
        <div class="container narrow">
            <h2 id="faq-title">{{ __('Frequently asked questions') }}</h2>
            <div class="faq">
                @foreach ($faq as [$question, $answer])
                    <details>
                        <summary>{{ $question }}<x-i name="chevron-down" :size="20" /></summary>
                        <div class="answer"><p>{{ $answer }}</p></div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <template id="result-template">
        <div class="result">
            <div>
                <p class="result-label">{{ __('Your short link') }}</p>
                <div class="copy-field">
                    <input type="text" readonly data-field="url" aria-label="{{ __('Your short link') }}">
                    <button type="button" class="btn btn-primary" data-copy-field="url"><x-i name="copy" :size="18" /><span>{{ __('Copy') }}</span></button>
                </div>
                <div class="result-actions">
                    <a class="btn btn-secondary btn-sm" href="#" data-field="open" target="_blank" rel="noopener"><x-i name="external" :size="16" />{{ __('Open') }}</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-share hidden><x-i name="share" :size="16" />{{ __('Share') }}</button>
                    <a class="btn btn-ghost btn-sm" href="#" data-field="details"><x-i name="info" :size="16" />{{ __('Details') }}</a>
                </div>
            </div>
            <div class="qr-box">
                <div class="qr" data-field="qr"></div>
                <div class="qr-actions">
                    <button type="button" class="btn btn-secondary btn-sm" data-qr-download="png"><x-i name="download" :size="14" />PNG</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-qr-download="svg"><x-i name="download" :size="14" />SVG</button>
                </div>
            </div>
        </div>
    </template>
</x-app-layout>
