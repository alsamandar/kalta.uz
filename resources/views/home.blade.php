@php
    $locale = app()->getLocale();
    $maxMb = max(1, round(upload_limit_kb() / 1024));

    $tools = [
        ['file', __('Share a file'), __('Up to :mb MB. Only people with the link can download it.', ['mb' => $maxMb]), lroute('files.create')],
        ['qr', __('QR code generator'), __('For a poster, a menu or a business card.'), lroute('qr.create')],
        ['bio', __('Link in bio'), __('Instagram, Telegram, your shop: one address for your profile.'), lroute('bio.create')],
        ['post', __('Write a post'), __('Like Telegraph: a title, a story, pictures. No account.'), lroute('posts.create')],
        ['code', __('Text & code snippets'), __('Paste it, pick when it disappears, send the link.'), lroute('codes.create')],
        ['invite', __('Wedding invitation'), __('A personal link for every guest, and you see who is coming.'), lroute('invitations.create')],
    ];

    $promises = array_filter([
        __('No sign-up. An account is only for managing your links.'),
        __('Short links do not expire.'),
        __('Files are private: only people with the link can open them.'),
        __('Uzbek, Russian and English.'),
        ! empty($since) ? __('Working since :year.', ['year' => $since]) : null,
    ]);

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

    <section class="home-hero" id="shorten">
        <div class="container">
            <p class="dict"><b lang="uz">kalta</b> <i>{{ __('Uzbek, adj.') }}</i> {{ __('short, not long') }}</p>
            <h1>
                <span class="h1-big">{{ __('Long story') }} <em>{{ __('short.') }}</em></span>
                <span class="h1-sub">{{ __('Shorten links, share files and text, make QR codes. Free, in a second.') }}</span>
            </h1>

            {{-- a paper tape: the scissors from the logo cut a long address down to a short one; replayed with the visitor's own link --}}
            <div class="snip" data-snip aria-hidden="true">
                <div class="snip-tape">
                    <span class="snip-keep">
                        <span class="snip-long" data-snip-head>https://www.example.com/</span>
                        <span class="snip-short" data-snip-short>kalta.uz/Ns7Kp</span>
                    </span>
                    <span class="snip-cut">
                        <svg class="snip-scissors" viewBox="0 0 32 32" width="36" height="36">
                            <g fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                                <g class="blade blade-a"><circle cx="9.5" cy="10" r="3.3"/><path d="M12.3 11.8L25.5 22.6"/></g>
                                <g class="blade blade-b"><circle cx="9.5" cy="22" r="3.3"/><path d="M12.3 20.2L25.5 9.4"/></g>
                            </g>
                        </svg>
                    </span>
                    <span class="snip-tail" data-snip-tail>katalog/kuz-2026/chegirmalar?utm_source=telegram&amp;utm_medium=kanal&amp;utm_campaign=noyabr&amp;ref=post_1842</span>
                    <span class="snip-saved" data-snip-saved></span>
                </div>
            </div>

            <form class="shortener" method="POST" action="{{ lroute('shorts.make') }}" data-shortener novalidate>
                @csrf
                <x-submission />
                <label class="sr-only" for="url">{{ __('Long link') }}</label>
                <div class="shortener-field">
                    <x-i name="link" :size="22" class="shortener-icon" />
                    <input id="url" name="url" type="text" inputmode="url" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                        placeholder="{{ __('Paste your long link here…') }}" value="{{ old('url') }}" required
                        @if ($errors->has('url')) aria-invalid="true" aria-describedby="url-error" @endif>
                    <button type="submit" class="btn btn-primary btn-lg"><x-i name="scissors" :size="20" /><span data-label>{{ __('Shorten') }}</span></button>
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

    <div class="cutline" aria-hidden="true"><x-i name="scissors" :size="18" /></div>

    <section class="home-block" aria-labelledby="tools-title">
        <div class="container">
            <h2 class="home-title" id="tools-title">{{ __('More than links') }}</h2>
            <div class="shelf">
                @foreach ($tools as [$kind, $name, $text, $href])
                    <a class="shelf-item shelf-{{ $kind }}" href="{{ $href }}">
                        <span class="mini mini-{{ $kind }}" aria-hidden="true">
                            @switch($kind)
                                @case('file')
                                    <span class="mini-file"><span class="mini-ext">PDF</span><span><b>hisobot-oktyabr.pdf</b><small>2.4 MB</small></span></span>
                                    <span class="mini-bar"><span></span></span>
                                    @break
                                @case('qr')
                                    <span class="mini-qr">{!! qr_svg('https://kalta.uz', 'QR') !!}</span>
                                    @break
                                @case('bio')
                                    <span class="mini-phone"><span class="mini-avatar"></span><span class="mini-name"></span><span class="mini-pill"></span><span class="mini-pill"></span><span class="mini-pill"></span></span>
                                    @break
                                @case('post')
                                    <span class="mini-paper"><span class="mini-h"></span><span class="mini-l"></span><span class="mini-l"></span><span class="mini-img"></span><span class="mini-l short"></span></span>
                                    @break
                                @case('code')
                                    <span class="mini-code"><i class="k">const</i> <i class="t">link</i> = <i class="s">'kalta.uz/Ns7Kp'</i>;<br><i class="c">// {{ __('expires in 1 day') }}</i></span>
                                    @break
                                @case('invite')
                                    <span class="mini-card"><span class="mini-amp">A <i>&amp;</i> M</span><span class="mini-date">19 · 11</span></span>
                                    @break
                            @endswitch
                        </span>
                        <span class="shelf-text">
                            <span class="shelf-name">{{ $name }} <x-i name="arrow-right" :size="18" /></span>
                            <span class="shelf-desc">{{ $text }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="home-block promises-block" aria-labelledby="promises-title">
        <div class="container">
            <div class="ticket">
                <h2 class="home-title" id="promises-title">{{ __('A few promises') }}</h2>
                <ul class="promises">
                    @foreach ($promises as $promise)
                        <li>{{ $promise }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="home-block" id="faq" aria-labelledby="faq-title">
        <div class="container faq-wrap">
            <div class="faq-side">
                <h2 class="home-title" id="faq-title">{{ __('People often ask') }}</h2>
                <p>{{ __('Did not find an answer?') }} <a href="{{ config('kalta.telegram') }}" target="_blank" rel="noopener">{{ __('Write to us on Telegram.') }}</a></p>
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

    <section class="home-end">
        <div class="container">
            <a href="#shorten" data-focus="#url">{{ __('That is all. Now paste your link') }} <span aria-hidden="true">↑</span></a>
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
