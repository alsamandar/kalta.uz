@php
    $token = $invitation->manage_token;
    $sample = $invitation->guests()->orderBy('name')->value('name') ?: 'Aziz';
    $default = __(':name, you are invited to the wedding of :couple!', ['name' => $sample, 'couple' => $invitation->coupleNames()], $invitation->locale);
@endphp
<x-app-layout :title="__('Edit invitation')" :noindex="true" :ads="false" body-class="page-manage">
    @push('head')
        <link rel="stylesheet" href="{{ asset_v('css/manage.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset_v('js/manage.js') }}" defer></script>
    @endpush

    <div class="container narrow page-body" style="padding-top: 24px">
        <a class="back-link" href="{{ route('invitations.manage', $token) }}"><x-i name="arrow-left" :size="18" />{{ __('Back to your guest list') }}</a>
        <h1 style="font-size: clamp(1.6rem, 5vw, 2.3rem); font-weight: 800; letter-spacing: -0.035em">{{ __('Edit invitation') }}</h1>
        <p class="muted">{{ __('Changes show up at once for everyone who has a link.') }}</p>

        <form class="card" method="POST" action="{{ route('invitations.update', $token) }}" enctype="multipart/form-data" data-resilient>
            @csrf
            @method('PATCH')

            <div class="grid-2">
                @include('invitations._fields', ['invitation' => $invitation])

                <div class="field span-2" id="share_message">
                    <label class="label" for="share_message_text">{{ __('Your message in Telegram and WhatsApp') }} <span class="opt">({{ __('optional') }})</span></label>
                    <textarea class="textarea" id="share_message_text" name="share_message" rows="3" maxlength="600" placeholder="{{ $default }}"
                        data-message-field data-fallback="{{ $default }}" data-sample="{{ $sample }}" data-link="{{ url('/i') }}/aBcDeFgHiJkL">{{ old('share_message', $invitation->share_message) }}</textarea>
                    <span class="help">{{ __('The text that goes with each personal link. Write {name} where the name of the guest should go. The link is added under your message.') }}</span>
                    @error('share_message')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
                    <p class="msg-preview" data-message-preview aria-live="polite"></p>
                </div>
            </div>

            <div class="edit-actions">
                <button class="btn btn-primary btn-lg" type="submit" data-loading="{{ __('Saving…') }}"><x-i name="check" :size="20" /><span>{{ __('Save changes') }}</span></button>
                <a class="btn btn-secondary btn-lg" href="{{ route('invitations.manage', $token) }}">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
