@php
    $locale = app()->getLocale();
    $maxMb = max(1, round(upload_limit_kb() / 1024));

    $tools = [
        ['link', __('Shorten a link'), __('Turn any long URL into a short, tidy link that is easy to share and type.'), __('Shorten now'), lroute('home') . '#shorten'],
        ['upload', __('Share a file'), __('Upload a file up to :mb MB and get a download link. Send it in a messenger or print it as a QR code.', ['mb' => $maxMb]), __('Upload a file'), lroute('files.create')],
        ['qr', __('QR code generator'), __('Create a QR code for a link, text or phone number and download it as PNG or SVG.'), __('Make a QR code'), lroute('qr.create')],
        ['code', __('Text & code snippets'), __('Paste text or code, choose when it expires and share it with a short link. Syntax highlighting included.'), __('Share text'), lroute('codes.create')],
        ['user', __('Link in bio'), __('Put all your links — Instagram, Telegram, X, GitHub — on one beautiful page with a single address.'), __('Create my page'), lroute('bio.create')],
        ['heart', __('Wedding invitation'), __('Design an online invitation, send every guest a personal link and see who is coming.'), __('Create an invitation'), lroute('invitations.create')],
    ];

    $steps = [
        [__('Paste or upload'), __('Paste a link, drop a file or write some text.')],
        [__('Get your short link'), __('We create a short address on kalta.uz and a QR code for it.')],
        [__('Share it anywhere'), __('Send it in messengers, put it on posters or in your profile.')],
    ];

    $features = [
        ['zap', __('Fast and simple'), __('No forms to fill in and no waiting — your link is ready in about a second.')],
        ['lock', __('No sign-up'), __('Everything works without an account. Create one only to manage your links.')],
        ['qr', __('QR code with every link'), __('Every short link comes with a QR code you can download as PNG or SVG.')],
        ['smartphone', __('Made for phones'), __('Designed for small screens first, so it feels natural on your phone.')],
        ['globe', __('Three languages'), __('Use Kalta.uz in Uzbek, Russian or English.')],
        ['shield', __('Files stay safe'), __('Uploaded files are only offered as downloads and are never run on our server.')],
    ];

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

    <section class="hero" id="shorten">
        <div class="container">
            <p class="eyebrow"><x-i name="zap" :size="16" />{{ __('Free · No sign-up · Instant') }}</p>
            <h1>
                <span class="h1-line">{{ __('Shorten links.') }}</span>
                <span class="h1-line">{{ __('Share files.') }}</span>
                <span class="h1-line"><span class="mark">{{ __('Make QR codes.') }}</span></span>
            </h1>
            <p class="lead">{{ __('Kalta.uz is a free toolbox for the web. Paste a long link and get a short one in a second — no account needed.') }}</p>

            <form class="shortener" method="POST" action="{{ lroute('shorts.make') }}" data-shortener novalidate>
                @csrf
                <x-submission />
                <label class="sr-only" for="url">{{ __('Long link') }}</label>
                <div class="shortener-field">
                    <x-i name="link" :size="22" class="shortener-icon" />
                    <input id="url" name="url" type="text" inputmode="url" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                        placeholder="{{ __('Paste your long link here…') }}" value="{{ old('url') }}" required
                        @if ($errors->has('url')) aria-invalid="true" aria-describedby="url-error" @endif>
                    <button type="submit" class="btn btn-primary btn-lg"><span data-label>{{ __('Shorten') }}</span></button>
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

    <section class="section" aria-labelledby="tools-title">
        <div class="container">
            <div class="section-head">
                <h2 id="tools-title">{{ __('Everything you need in one place') }}</h2>
                <p>{{ __('Pick a tool — each one gives you a short link you can share anywhere.') }}</p>
            </div>
            <div class="tools">
                @foreach ($tools as [$icon, $name, $text, $cta, $href])
                    <article class="tool-card">
                        <span class="tool-icon"><x-i :name="$icon" :size="24" /></span>
                        <h3>{{ $name }}</h3>
                        <p>{{ $text }}</p>
                        <a class="more" href="{{ $href }}">{{ $cta }} <x-i name="arrow-right" :size="18" /></a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section alt" aria-labelledby="how-title">
        <div class="container">
            <div class="section-head">
                <h2 id="how-title">{{ __('How it works') }}</h2>
                <p>{{ __('Three steps, about ten seconds.') }}</p>
            </div>
            <ol class="steps" style="list-style: none; padding: 0; margin: 0;">
                @foreach ($steps as [$name, $text])
                    <li class="step">
                        <h3>{{ $name }}</h3>
                        <p>{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="section" aria-labelledby="why-title">
        <div class="container">
            <div class="section-head">
                <h2 id="why-title">{{ __('Why people use Kalta.uz') }}</h2>
            </div>
            <div class="features">
                @foreach ($features as [$icon, $name, $text])
                    <div class="feature">
                        <span class="tool-icon"><x-i :name="$icon" :size="22" /></span>
                        <div>
                            <h3>{{ $name }}</h3>
                            <p>{{ $text }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section alt" id="faq" aria-labelledby="faq-title">
        <div class="container">
            <div class="section-head">
                <h2 id="faq-title">{{ __('Frequently asked questions') }}</h2>
            </div>
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

    <section class="section cta">
        <div class="container">
            <div class="cta-card">
                <h2>{{ __('Ready to shorten your first link?') }}</h2>
                <p>{{ __('It takes a few seconds and costs nothing.') }}</p>
                <a class="btn btn-primary btn-lg" href="#shorten" data-focus="#url">{{ __('Start now') }} <x-i name="arrow-right" :size="20" /></a>
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
