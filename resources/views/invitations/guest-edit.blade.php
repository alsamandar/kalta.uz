@php
    $token = $invitation->manage_token;
    $back = route('invitations.manage', $token) . '#guest-' . $guest->id;
    $dates = fn ($date) => $date ? $date->copy()->setTimezone(config('kalta.timezone'))->translatedFormat('j F, H:i') : null;
@endphp
<x-app-layout :title="__('Edit guest')" :noindex="true" :ads="false" body-class="page-manage">
    @push('head')
        <link rel="stylesheet" href="{{ asset_v('css/manage.css') }}">
    @endpush

    <div class="container tight page-body" style="padding-top: 24px">
        <a class="back-link" href="{{ $back }}"><x-i name="arrow-left" :size="18" />{{ __('Back to your guest list') }}</a>
        <h1 style="font-size: clamp(1.6rem, 5vw, 2.1rem); font-weight: 800; letter-spacing: -0.035em; overflow-wrap: anywhere">{{ $guest->name }}</h1>

        <form class="card" method="POST" action="{{ route('invitations.guests.update', [$token, $guest]) }}">
            @csrf
            @method('PATCH')

            <div class="field">
                <label class="label" for="name">{{ __('Name') }}</label>
                <input class="input" type="text" id="name" name="name" value="{{ old('name', $guest->name) }}" required maxlength="120" autocomplete="off" @if ($errors->has('name')) aria-invalid="true" @endif>
                @error('name')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="label" for="phone">{{ __('Phone') }} <span class="opt">({{ __('optional') }})</span></label>
                <input class="input" type="tel" id="phone" name="phone" value="{{ old('phone', $guest->phone) }}" maxlength="30" autocomplete="off" placeholder="+998 90 123 45 67" @if ($errors->has('phone')) aria-invalid="true" @endif>
                <span class="help">{{ __('With a phone number, the WhatsApp button opens the chat with this guest.') }}</span>
                @error('phone')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="label" for="email">{{ __('E-mail') }} <span class="opt">({{ __('optional') }})</span></label>
                <input class="input" type="email" id="email" name="email" value="{{ old('email', $guest->email) }}" maxlength="255" autocomplete="off" autocapitalize="off" @if ($errors->has('email')) aria-invalid="true" @endif>
                @error('email')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
            </div>

            <div class="grid-2">
                <div class="field">
                    <label class="label" for="status">{{ __('Reply') }}</label>
                    <select class="select" id="status" name="status">
                        @foreach (['pending' => __('No reply'), 'attending' => __('Coming'), 'declined' => __('Declined')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $guest->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="help">{{ __('Answered by phone? Record the reply here.') }}</span>
                </div>
                <div class="field">
                    <label class="label" for="party_size">{{ __('Number of people (including the guest)') }}</label>
                    <input class="input" type="number" id="party_size" name="party_size" min="1" max="10" value="{{ old('party_size', max(1, $guest->party_size)) }}" @if ($errors->has('party_size')) aria-invalid="true" @endif>
                    @error('party_size')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="field">
                <label class="label" for="note">{{ __('Wishes for the couple') }} <span class="opt">({{ __('optional') }})</span></label>
                <textarea class="textarea" id="note" name="note" rows="3" maxlength="1000">{{ old('note', $guest->note) }}</textarea>
            </div>

            <div class="edit-actions">
                <button class="btn btn-primary btn-lg" type="submit" data-loading="{{ __('Saving…') }}"><x-i name="check" :size="20" /><span>{{ __('Save changes') }}</span></button>
                <a class="btn btn-secondary btn-lg" href="{{ $back }}">{{ __('Cancel') }}</a>
            </div>
        </form>

        <section class="card" data-copy-scope style="margin-top: 16px">
            <h2>{{ __('Personal link') }}</h2>
            <div class="copy-field">
                <input type="text" readonly value="{{ $guest->shareLink() }}" data-field="url" aria-label="{{ __('Personal link') }}" onfocus="this.select()">
                <button type="button" class="btn btn-primary" data-copy-field="url"><x-i name="copy" :size="18" /><span>{{ __('Copy') }}</span></button>
            </div>
            <dl class="facts">
                <div><dt>{{ __('Sent') }}</dt><dd>{{ $dates($guest->sent_at) ?? '—' }}</dd></div>
                <div><dt>{{ __('Opened') }}</dt><dd>{{ $guest->opened_at ? $dates($guest->opened_at) . ' · ' . __('Opened :count times', ['count' => $guest->views]) : '—' }}</dd></div>
                <div><dt>{{ __('Replied') }}</dt><dd>{{ $dates($guest->responded_at) ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="card danger-zone" style="margin-top: 16px">
            <h2>{{ __('Remove guest') }}</h2>
            <p class="muted">{{ __('Their personal link stops working.') }}</p>
            <form action="{{ route('invitations.guests.destroy', [$token, $guest]) }}" method="POST" data-confirm="{{ __('Remove :name from the list?', ['name' => $guest->name]) }}">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger" type="submit"><x-i name="trash" :size="18" />{{ __('Remove') }}</button>
            </form>
        </section>
    </div>
</x-app-layout>
