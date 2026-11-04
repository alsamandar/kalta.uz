@php
    $tools = [
        ['home', 'link', __('Shorten link'), ['home', 'ru.home', 'en.home']],
        ['files.create', 'upload', __('Files'), ['*files.create']],
        ['qr.create', 'qr', __('QR code'), ['*qr.create']],
        ['codes.create', 'code', __('Text & code'), ['*codes.create']],
        ['bio.create', 'user', __('Link in bio'), ['*bio.create']],
        ['posts.create', 'file-text', __('Post'), ['*posts.create']],
        ['invitations.create', 'heart', __('Wedding invitation'), ['*invitations.create']],
    ];
    $locale = app()->getLocale();
@endphp
<header class="site-header">
    <div class="container header-row">
        <a class="brand" href="{{ lroute('home') }}" aria-label="Kalta.uz — {{ __('Home') }}">
            <x-logo :size="34" />
            <span class="brand-word">Kalta<span class="brand-uz">UZ</span></span>
        </a>

        <nav class="tools-nav" aria-label="{{ __('Main menu') }}">
            <ul>
                @foreach ($tools as [$route, $icon, $label, $patterns])
                    <li>
                        <a href="{{ lroute($route) }}" @if (request()->routeIs(...$patterns)) aria-current="page" @endif>
                            <x-i :name="$icon" :size="18" />{{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="header-actions">
            <details class="menu" data-menu>
                <summary class="icon-btn menu-btn" aria-label="{{ __('Language') }}: {{ strtoupper($locale) }}">
                    <x-i name="globe" :size="18" /><span>{{ strtoupper($locale) }}</span>
                </summary>
                <ul class="menu-list">
                    @foreach (config('kalta.locales') as $code => $label)
                        <li>
                            <a href="{{ $alternateUrls[$code] ?? route('lang.switch', $code) }}" lang="{{ $code }}" hreflang="{{ $code }}"
                                @unless (isset($alternateUrls[$code])) rel="nofollow" @endunless
                                @if ($code === $locale) aria-current="true" @endif>
                                {{ $label }}
                                @if ($code === $locale)<x-i name="check" :size="16" />@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>

            <button type="button" class="icon-btn" data-theme-toggle aria-label="{{ __('Toggle dark mode') }}">
                <x-i name="moon" :size="18" class="theme-moon" />
                <x-i name="sun" :size="18" class="theme-sun" />
            </button>

            @auth
                <a class="btn btn-secondary btn-sm header-login" href="{{ route('dashboard') }}" aria-label="{{ __('My links') }}">
                    <x-i name="user" :size="16" /><span>{{ __('My links') }}</span>
                </a>
            @else
                <a class="btn btn-secondary btn-sm header-login" href="{{ route('login') }}" aria-label="{{ __('Log in') }}">
                    <x-i name="log-in" :size="16" /><span>{{ __('Log in') }}</span>
                </a>
            @endauth
        </div>
    </div>
</header>
