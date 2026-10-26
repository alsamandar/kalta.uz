@php
    $short = $kalta->short_url;
    [$icon, $heading, $lead] = match ($kalta->type) {
        'short' => ['link', __('Your short link is ready'), __('Share it anywhere — it opens the original page.')],
        'file' => ['upload', __('Your file is ready to share'), __('Anyone with this link can download the file.')],
        'bio' => ['user', __('Your link-in-bio page is ready'), __('Put this link in your Instagram or Telegram profile.')],
        'code' => ['code', __('Your text is ready to share'), __('Anyone with this link can read it.')],
        default => ['link', __('Your link is ready'), __('Share it anywhere.')],
    };
    $typeLabel = match ($kalta->type) {
        'short' => __('Short link'),
        'file' => __('File'),
        'bio' => __('Link in bio'),
        'code' => __('Text & code'),
        'invitation' => __('Wedding invitation'),
        default => __('Link'),
    };
@endphp
<x-app-layout :title="$heading" :noindex="true">
    <x-page-hero :title="$heading" :lead="$lead" :icon="$icon" />

    <div class="container narrow page-body">
        <div class="card created-card" data-copy-scope data-qr-scope data-qr-name="kalta-{{ $kalta->url }}">
            <div>
                <p class="result-label">{{ __('Your short link') }}</p>
                <div class="copy-field">
                    <input type="text" readonly value="{{ $short }}" data-field="url" aria-label="{{ __('Your short link') }}" onfocus="this.select()">
                    <button type="button" class="btn btn-primary" data-copy-field="url"><x-i name="copy" :size="18" /><span>{{ __('Copy') }}</span></button>
                </div>

                <div class="share-row">
                    <a class="btn btn-secondary btn-sm" href="{{ $short }}" target="_blank" rel="noopener"><x-i name="external" :size="16" />{{ __('Open') }}</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-share data-share-url="{{ $short }}" hidden><x-i name="share" :size="16" />{{ __('Share') }}</button>
                    <a class="btn btn-secondary btn-sm" href="https://t.me/share/url?url={{ urlencode($short) }}" target="_blank" rel="noopener"><x-i name="telegram" :size="16" />Telegram</a>
                    <a class="btn btn-secondary btn-sm" href="https://wa.me/?text={{ urlencode($short) }}" target="_blank" rel="noopener"><x-i name="whatsapp" :size="16" />WhatsApp</a>
                </div>

                <dl class="facts">
                    <div><dt>{{ __('Type') }}</dt><dd>{{ $typeLabel }}</dd></div>
                    @if ($target instanceof \App\Models\Short)
                        <div><dt>{{ __('Opens') }}</dt><dd>{{ $target->long_url }}</dd></div>
                    @elseif ($target instanceof \App\Models\File)
                        <div><dt>{{ __('File') }}</dt><dd>{{ $target->name }}</dd></div>
                    @elseif ($target instanceof \App\Models\Bio)
                        <div><dt>{{ __('Name') }}</dt><dd>{{ $target->title }}</dd></div>
                    @elseif ($target instanceof \App\Models\Code)
                        <div><dt>{{ __('Language') }}</dt><dd>{{ $target->prog_lang->value === 'txt' ? __('Plain text') : $target->prog_lang->label() }}</dd></div>
                        <div><dt>{{ __('Expires') }}</dt><dd>{{ $target->expiration ? $target->expiration->isoFormat('LLL') : __('Never') }}</dd></div>
                    @endif
                    <div><dt>{{ __('Created') }}</dt><dd>{{ $kalta->created_at->isoFormat('LLL') }}</dd></div>
                    <div><dt>{{ __('Clicks') }}</dt><dd>{{ number_format($kalta->clicks) }}</dd></div>
                </dl>
            </div>

            <div class="qr-box">
                <div class="qr">{!! qr_svg($short, __('QR code for :url', ['url' => $short])) !!}</div>
                <div class="qr-actions">
                    <button type="button" class="btn btn-secondary btn-sm" data-qr-download="png"><x-i name="download" :size="14" />PNG</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-qr-download="svg"><x-i name="download" :size="14" />SVG</button>
                </div>
            </div>
        </div>

        <div class="next-steps">
            <a class="btn btn-primary" href="{{ lroute('home') }}#shorten"><x-i name="plus" :size="18" />{{ __('Shorten another link') }}</a>
            <a class="btn btn-secondary" href="{{ lroute('files.create') }}"><x-i name="upload" :size="18" />{{ __('Upload a file') }}</a>
            <a class="btn btn-secondary" href="{{ lroute('qr.create') }}"><x-i name="qr" :size="18" />{{ __('QR code') }}</a>
        </div>
    </div>
</x-app-layout>
