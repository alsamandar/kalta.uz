@php
    $locale = app()->getLocale();
@endphp
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="brand" href="{{ lroute('home') }}" aria-label="Kalta.uz — {{ __('Home') }}">
                <x-logo :size="34" />
                <span class="brand-word">Kalta<span class="brand-uz">UZ</span></span>
            </a>
            <p>{{ __('Free tools to shorten links, share files and make QR codes.') }}</p>
        </div>

        <nav aria-label="{{ __('Tools') }}">
            <h2 class="footer-title">{{ __('Tools') }}</h2>
            <ul>
                <li><a href="{{ lroute('home') }}">{{ __('Shorten link') }}</a></li>
                <li><a href="{{ lroute('files.create') }}">{{ __('Upload a file') }}</a></li>
                <li><a href="{{ lroute('qr.create') }}">{{ __('QR code') }}</a></li>
                <li><a href="{{ lroute('codes.create') }}">{{ __('Text & code') }}</a></li>
                <li><a href="{{ lroute('bio.create') }}">{{ __('Link in bio') }}</a></li>
                <li><a href="{{ lroute('invitations.create') }}">{{ __('Wedding invitation') }}</a></li>
            </ul>
        </nav>

        <nav aria-label="{{ __('Language') }}">
            <h2 class="footer-title">{{ __('Language') }}</h2>
            <ul>
                @foreach (config('kalta.locales') as $code => $label)
                    <li>
                        <a href="{{ $alternateUrls[$code] ?? route('lang.switch', $code) }}" lang="{{ $code }}" hreflang="{{ $code }}"
                            @unless (isset($alternateUrls[$code])) rel="nofollow" @endunless
                            @if ($code === $locale) aria-current="true" @endif>{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div>
            <h2 class="footer-title">{{ __('Contact') }}</h2>
            <ul>
                @if (config('kalta.telegram'))
                    <li><a href="{{ config('kalta.telegram') }}" rel="noopener" target="_blank"><x-i name="telegram" :size="16" /> Telegram</a></li>
                @endif
                @if (config('kalta.contact_email'))
                    <li><a href="mailto:{{ config('kalta.contact_email') }}"><x-i name="mail" :size="16" /> {{ config('kalta.contact_email') }}</a></li>
                @endif
            </ul>
        </div>
    </div>

    <div class="container footer-bottom">
        <p>© {{ date('Y') }} Kalta.uz</p>
        <p>{{ __('Do not use Kalta.uz for spam, phishing or illegal content.') }}</p>
    </div>
</footer>
