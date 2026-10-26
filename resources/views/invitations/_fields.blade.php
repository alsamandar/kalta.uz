{{-- The invitation's details, shared by "create" and "edit". $invitation is null when creating. --}}
@php
    $value = fn (string $field, $default = '') => old($field, $default);
    $eventAt = $invitation ? $invitation->localEventAt()->format('Y-m-d\TH:i') : '';
    $deadline = $invitation?->rsvp_deadline?->format('Y-m-d') ?? '';
    $parts = ['morning' => __('morning'), 'afternoon' => __('afternoon'), 'evening' => __('evening'), 'night' => __('night')];
@endphp

<div class="field">
    <label class="label" for="groom_name">{{ __('Groom') }}</label>
    <input class="input" type="text" id="groom_name" name="groom_name" value="{{ $value('groom_name', $invitation->groom_name ?? '') }}" required maxlength="120" autocomplete="off" @if ($errors->has('groom_name')) aria-invalid="true" @endif>
    @error('groom_name')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
</div>
<div class="field">
    <label class="label" for="bride_name">{{ __('Bride') }}</label>
    <input class="input" type="text" id="bride_name" name="bride_name" value="{{ $value('bride_name', $invitation->bride_name ?? '') }}" required maxlength="120" autocomplete="off" @if ($errors->has('bride_name')) aria-invalid="true" @endif>
    @error('bride_name')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
</div>
<div class="field">
    <label class="label" for="event_at">{{ __('Date and time') }}</label>
    <input class="input" type="datetime-local" id="event_at" name="event_at" value="{{ $value('event_at', $eventAt) }}" required
        data-when="#event_at-hint" data-parts="{{ json_encode($parts, JSON_UNESCAPED_UNICODE) }}" aria-describedby="event_at-hint" @if ($errors->has('event_at')) aria-invalid="true" @endif>
    <span class="help when-hint" id="event_at-hint" aria-live="polite"></span>
    @error('event_at')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
</div>
<div class="field">
    <label class="label" for="rsvp_deadline">{{ __('Please reply by') }} <span class="opt">({{ __('optional') }})</span></label>
    <input class="input" type="date" id="rsvp_deadline" name="rsvp_deadline" value="{{ $value('rsvp_deadline', $deadline) }}" @if ($errors->has('rsvp_deadline')) aria-invalid="true" @endif>
    @error('rsvp_deadline')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
</div>
<div class="field span-2">
    <label class="label" for="venue">{{ __('Venue') }}</label>
    <input class="input" type="text" id="venue" name="venue" value="{{ $value('venue', $invitation->venue ?? '') }}" required maxlength="255" placeholder="{{ __('e.g. Navruz restaurant') }}" @if ($errors->has('venue')) aria-invalid="true" @endif>
    @error('venue')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
</div>
<div class="field">
    <label class="label" for="address">{{ __('Address') }} <span class="opt">({{ __('optional') }})</span></label>
    <input class="input" type="text" id="address" name="address" value="{{ $value('address', $invitation->address ?? '') }}" maxlength="255" autocomplete="off">
</div>
<div class="field">
    <label class="label" for="map_url">{{ __('Map link') }} <span class="opt">({{ __('optional') }})</span></label>
    <input class="input" type="text" inputmode="url" id="map_url" name="map_url" value="{{ $value('map_url', $invitation->map_url ?? '') }}" maxlength="255" placeholder="https://yandex.uz/maps/…" autocapitalize="off" @if ($errors->has('map_url')) aria-invalid="true" @endif>
    @error('map_url')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
</div>
<div class="field span-2">
    <label class="label" for="message">{{ __('Message to your guests') }} <span class="opt">({{ __('optional') }})</span></label>
    <textarea class="textarea" id="message" name="message" rows="3" maxlength="2000" placeholder="{{ __('We would be honoured to have you with us on our special day.') }}">{{ $value('message', $invitation->message ?? '') }}</textarea>
</div>
<div class="field">
    <label class="label" for="cover_image">{{ __('Cover photo') }} <span class="opt">({{ __('optional') }})</span></label>
    @if ($invitation?->cover_image)
        <div class="cover-current">
            <img src="{{ asset($invitation->cover_image) }}" alt="" width="96" height="72" loading="lazy" decoding="async">
            <label class="check"><input type="checkbox" name="remove_cover" value="1" @checked(old('remove_cover'))> <span>{{ __('Remove the photo') }}</span></label>
        </div>
    @endif
    <input class="input" type="file" id="cover_image" name="cover_image" accept=".jpg,.jpeg,.png,.webp,image/*" data-shrink="1600" @if ($errors->has('cover_image')) aria-invalid="true" @endif>
    @if ($invitation?->cover_image)<span class="help">{{ __('Choose a new photo to replace the current one.') }}</span>@endif
    @error('cover_image')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
</div>
<div class="field">
    <label class="label" for="locale">{{ __('Invitation language') }}</label>
    <select class="select" id="locale" name="locale">
        @foreach (config('kalta.locales') as $code => $label)
            <option value="{{ $code }}" @selected($value('locale', $invitation->locale ?? app()->getLocale()) === $code)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="field span-2">
    <label class="label" for="host_email">{{ __('Your e-mail') }} <span class="opt">({{ __('optional') }})</span></label>
    <input class="input" type="email" id="host_email" name="host_email" value="{{ $value('host_email', $invitation->host_email ?? '') }}" maxlength="255" autocomplete="email" @if ($errors->has('host_email')) aria-invalid="true" @endif>
    <span class="help">{{ __('We will let you know when a guest replies.') }}</span>
    @error('host_email')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
</div>
