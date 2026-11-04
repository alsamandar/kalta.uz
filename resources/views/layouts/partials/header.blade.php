@php
    // one menu button holds the tools, the language and the account, so the top of every page stays quiet
    $tools = [
        ['home', 'link', __('Shorten link'), ['home', 'ru.home']],
        ['files.create', 'upload', __('Share a file'), ['*files.create']],
        ['qr.create', 'qr', __('QR code generator'), ['*qr.create']],
        ['codes.create', 'code', __('Text & code'), ['*codes.create']],
        ['bio.create', 'user', __('Link in bio'), ['*bio.create']],
        ['posts.create', 'file-text', __('Write a post'), ['*posts.create']],
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

        <details class="menu site-menu" data-menu>
            <summary class="icon-btn menu-btn" aria-label="{{ __('Menu') }}">
                <x-i name="menu" :size="20" /><span>{{ __('Menu') }}</span>
            </summary>
            <div class="menu-panel">
                <nav aria-label="{{ __('Main menu') }}">
                    <ul class="menu-tools">
                        @foreach ($tools as [$route, $icon, $label, $patterns])
                            <li>
                                <a href="{{ lroute($route) }}" @if (request()->routeIs(...$patterns)) aria-current="page" @endif>
                                    <x-i :name="$icon" :size="20" /><span>{{ $label }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
                <div class="menu-foot">
                    <div class="lang-switch" role="group" aria-label="{{ __('Language') }}">
                        @foreach (config('kalta.locales') as $code => $label)
                            <a href="{{ $alternateUrls[$code] ?? route('lang.switch', $code) }}" lang="{{ $code }}" hreflang="{{ $code }}"
                                @unless (isset($alternateUrls[$code])) rel="nofollow" @endunless
                                @if ($code === $locale) aria-current="true" @endif>{{ strtoupper($code) }}</a>
                        @endforeach
                    </div>
                    @auth
                        <a class="menu-account" href="{{ route('dashboard') }}"><x-i name="user" :size="18" />{{ __('My links') }}</a>
                    @else
                        <a class="menu-account" href="{{ route('login') }}"><x-i name="log-in" :size="18" />{{ __('Log in') }}</a>
                    @endauth
                </div>
            </div>
        </details>
    </div>
</header>
