@php
    $expirationLabels = [
        'never' => __('Never'),
        '10m' => __('10 minutes'),
        '1h' => __('1 hour'),
        '1d' => __('1 day'),
        '1w' => __('1 week'),
        '1mo' => __('1 month'),
    ];
@endphp
<x-app-layout
    :title="__('Share text or code with a short link')"
    :description="__('Paste text or a code snippet, choose when it expires and share it with a short link. Syntax highlighting included. Free, no sign-up.')"
    :alternates="true"
    :trail="true">

    <x-page-hero :title="__('Share text or code')" :lead="__('Paste anything — notes, a config file or a code snippet — and get a short link to it.')" />

    <div class="container narrow page-body">
        <form class="card" method="POST" action="{{ lroute('codes.store') }}" data-resilient>
            @csrf
            <x-submission />
            <div class="field">
                <label class="label" for="content">{{ __('Your text or code') }}</label>
                <textarea class="textarea mono" id="content" name="content" required maxlength="100000" spellcheck="false" autocapitalize="off"
                    placeholder="{{ __('Paste your text or code here…') }}"
                    @if ($errors->has('content')) aria-invalid="true" @endif>{{ old('content') }}</textarea>
                @error('content')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
            </div>

            <div class="grid-2">
                <div class="field">
                    <label class="label" for="language">{{ __('Language') }}</label>
                    <select class="select" id="language" name="language">
                        @foreach ($languages as $language)
                            <option value="{{ $language->value }}" @selected(old('language', 'txt') === $language->value)>
                                {{ $language->value === 'txt' ? __('Plain text') : $language->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="expiration">{{ __('Delete after') }}</label>
                    <select class="select" id="expiration" name="expiration">
                        @foreach ($expirations as $key)
                            <option value="{{ $key }}" @selected(old('expiration', 'never') === $key)>{{ $expirationLabels[$key] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg" data-loading="{{ __('Creating…') }}"><x-i name="link" :size="20" /><span>{{ __('Create link') }}</span></button>
            </div>
        </form>

        <article class="prose" style="margin-top: 36px">
            <h2>{{ __('Share text or code with a short link') }}</h2>
            <p>{{ __('Choose the language for syntax highlighting and when the text should disappear. You get a short link and a QR code.') }}</p>
            <p>{{ __('Only people with the link can open it. The text is not listed anywhere and is hidden from search engines.') }}</p>
        </article>
    </div>
</x-app-layout>
