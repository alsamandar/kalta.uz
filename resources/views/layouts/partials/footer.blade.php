{{-- one quiet line: the tools and the languages are in the header menu --}}
<footer class="site-footer">
    <div class="container footer-row">
        <p>© {{ date('Y') }} Kalta.uz</p>
        @if (config('kalta.telegram'))
            <a href="{{ config('kalta.telegram') }}" rel="noopener" target="_blank">Telegram</a>
        @endif
        @if (config('kalta.contact_email'))
            <a href="mailto:{{ config('kalta.contact_email') }}">{{ config('kalta.contact_email') }}</a>
        @endif
        <p class="footer-note">{{ __('Do not use Kalta.uz for spam, phishing or illegal content.') }}</p>
    </div>
</footer>
