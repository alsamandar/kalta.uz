<x-app-layout
    :title="__('QR code generator')"
    :description="__('Create a QR code for a link, text or phone number and download it as PNG or SVG. Free, no sign-up.')"
    :alternates="true"
    :trail="true">

    <x-page-hero :title="__('QR code generator')" :lead="__('Type a link or any text and get a QR code you can download as PNG or SVG.')" icon="qr" />

    <div class="container page-body">
        <div class="qr-layout">
            <form class="card" method="POST" action="{{ lroute('qr.generate') }}" data-qr-form novalidate>
                @csrf
                <div class="field">
                    <label class="label" for="text">{{ __('Link or text') }}</label>
                    <textarea class="textarea" id="text" name="text" rows="5" maxlength="1000" placeholder="https://kalta.uz" required
                        @if ($errors->has('text')) aria-invalid="true" @endif>{{ old('text', $text) }}</textarea>
                    <span class="help">{{ __('The code updates as you type.') }}</span>
                    <p class="field-error" data-error role="alert" @unless ($errors->has('text')) hidden @endunless>{{ $errors->first('text') }}</p>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg"><x-i name="qr" :size="20" /><span>{{ __('Generate QR code') }}</span></button>
                </div>
            </form>

            <div class="card qr-preview" data-qr-holder data-qr-scope data-qr-name="kalta-qr">
                <div class="qr" @unless ($svg) hidden @endunless>{!! $svg !!}</div>
                <div class="qr-empty" data-qr-empty @if ($svg) hidden @endif>{{ __('Your QR code will appear here') }}</div>
                <div class="qr-actions" data-qr-actions @unless ($svg) hidden @endunless>
                    <button type="button" class="btn btn-secondary btn-sm" data-qr-download="png"><x-i name="download" :size="14" />PNG</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-qr-download="svg"><x-i name="download" :size="14" />SVG</button>
                </div>
            </div>
        </div>

        <article class="prose" style="margin-top: 36px">
            <h2>{{ __('What can you put in a QR code?') }}</h2>
            <p>{{ __('A website address, plain text, a phone number (tel:+998…), an e-mail (mailto:…) or Wi-Fi details. Scan it with the camera app of any modern phone.') }}</p>
            <p>{{ __('Download the code as PNG for print and chats, or as SVG for posters and logos — it stays sharp at any size.') }}</p>
        </article>
    </div>
</x-app-layout>
