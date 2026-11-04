@php
    $icons = ['short' => 'link', 'file' => 'upload', 'bio' => 'user', 'code' => 'code', 'invitation' => 'heart', 'post' => 'file-text'];
@endphp
<x-app-layout :title="__('My links')" :noindex="true" :ads="false">
    <x-page-hero :title="__('My links')" :lead="__('Links, files and pages you created while logged in.')" icon="user" />

    <div class="container page-body">
        <div class="toolbar">
            <div class="share-row" style="margin:0">
                <a class="btn btn-primary btn-sm" href="{{ lroute('home') }}#shorten"><x-i name="link" :size="16" />{{ __('Shorten link') }}</a>
                <a class="btn btn-secondary btn-sm" href="{{ lroute('files.create') }}"><x-i name="upload" :size="16" />{{ __('Upload a file') }}</a>
                <a class="btn btn-secondary btn-sm" href="{{ lroute('bio.create') }}"><x-i name="user" :size="16" />{{ __('Link in bio') }}</a>
                <a class="btn btn-secondary btn-sm" href="{{ lroute('posts.create') }}"><x-i name="file-text" :size="16" />{{ __('Write a post') }}</a>
            </div>
            <div class="share-row" style="margin:0">
                <a class="btn btn-ghost btn-sm" href="{{ route('profile.edit') }}">{{ __('Profile') }}</a>
                <form method="POST" action="{{ route('logout') }}">@csrf <button class="btn btn-ghost btn-sm" type="submit"><x-i name="log-out" :size="16" />{{ __('Log Out') }}</button></form>
            </div>
        </div>

        @if ($kaltas->isEmpty())
            <div class="card empty">
                <x-i name="link" :size="36" />
                <p>{{ __('Nothing here yet. Links you create while logged in will appear here.') }}</p>
            </div>
        @else
            <ul class="link-list">
                @foreach ($kaltas as $kalta)
                    @php
                        $target = $kalta->kaltaable;
                        // short links have no useful name (the column defaults to "unnamed kalta"): show where they lead
                        $title = $target instanceof \App\Models\Short
                            ? $target->long_url
                            : ($kalta->name ?: ($target->name ?? $target->title ?? $kalta->url));
                    @endphp
                    <li class="link-item">
                        <span class="tool-icon"><x-i :name="$icons[$kalta->type] ?? 'link'" :size="20" /></span>
                        <div class="link-main">
                            <a href="{{ $kalta->short_url }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://#', '', $kalta->short_url) }}</a>
                            <span class="link-sub">{{ $title }}</span>
                        </div>
                        <span class="link-stat"><x-i name="bar-chart" :size="16" />{{ number_format($kalta->clicks) }}</span>
                        <button type="button" class="icon-btn" data-copy-text="{{ $kalta->short_url }}" title="{{ __('Copy') }}" aria-label="{{ __('Copy') }} {{ $kalta->short_url }}"><x-i name="copy" :size="18" /></button>
                        @if ($target instanceof \App\Models\Post)
                            <a class="icon-btn" href="{{ route('posts.edit', $target) }}" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }} {{ $title }}"><x-i name="edit" :size="18" /></a>
                        @endif
                        <a class="icon-btn" href="{{ lroute('created', $kalta) }}" title="{{ __('Details & QR code') }}" aria-label="{{ __('Details & QR code') }}"><x-i name="qr" :size="18" /></a>
                        <form method="POST" action="{{ route('kaltas.destroy', $kalta) }}" data-confirm="{{ __('Delete this link? This cannot be undone.') }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="icon-btn" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }} {{ $kalta->short_url }}"><x-i name="trash" :size="18" /></button>
                        </form>
                    </li>
                @endforeach
            </ul>
            {{ $kaltas->links() }}
        @endif
    </div>
</x-app-layout>
