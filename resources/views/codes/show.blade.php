@php
    $language = $code->prog_lang;
    $lines = substr_count($code->content, "\n") + 1;
@endphp
<x-app-layout :title="$kalta->name ?: __('Shared text')" :noindex="true" :ads="false">
    <div class="container page-body" style="padding-top: 28px">
        <h1 class="sr-only">{{ $kalta->name ?: __('Shared text') }}</h1>

        <div class="code-card" data-copy-scope>
            <div class="code-toolbar">
                <div class="code-meta">
                    <span>{{ $language->value === 'txt' ? __('Plain text') : $language->label() }}</span>
                    <span>{{ __(':count lines', ['count' => $lines]) }}</span>
                    @if ($code->expiration)
                        <span><x-i name="clock" :size="14" /> {{ __('Expires :when', ['when' => $code->expiration->diffForHumans()]) }}</span>
                    @endif
                </div>
                <div class="code-actions">
                    <button type="button" class="btn btn-primary btn-sm" data-copy="#code-text"><x-i name="copy" :size="16" /><span>{{ __('Copy') }}</span></button>
                    <a class="btn btn-secondary btn-sm" href="{{ route('kaltas.raw', $kalta) }}" rel="nofollow">{{ __('Raw') }}</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-download-text="#code-text" data-filename="kalta-{{ $kalta->url }}.txt"><x-i name="download" :size="16" />{{ __('Download') }}</button>
                </div>
            </div>
            <pre class="code"><code id="code-text" class="language-{{ $language->highlightName() }}" data-highlight="{{ $language->highlightName() }}">{{ $code->content }}</code></pre>
        </div>

        <p class="center" style="margin-top: 24px">
            <a class="btn btn-secondary" href="{{ lroute('codes.create') }}"><x-i name="plus" :size="18" />{{ __('Share your own text') }}</a>
        </p>
    </div>

    @push('scripts')
        <script>window.K.hljs = @json(asset_v('vendor/hljs/highlight.min.js'))</script>
    @endpush
</x-app-layout>
