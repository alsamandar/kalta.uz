<x-app-layout
    :title="__('Online wedding invitation')"
    :description="__('Create an elegant online wedding invitation, send every guest a personal link by e-mail, Telegram or WhatsApp and see who is coming. Free.')"
    :alternates="true"
    :trail="true">

    @push('scripts')<script src="{{ asset_v('js/manage.js') }}" defer></script>@endpush

    <x-page-hero :title="__('Wedding invitation')" :lead="__('Create a beautiful invitation page, send each guest a personal link and collect their replies.')" />

    <div class="container narrow page-body">
        <form class="card" method="POST" action="{{ lroute('invitations.store') }}" enctype="multipart/form-data" data-resilient>
            @csrf
            <x-submission />

            <div class="grid-2">
                @include('invitations._fields', ['invitation' => null])
                <div class="field span-2">
                    <label class="label" for="guests">{{ __('Guests') }} <span class="opt">({{ __('optional') }})</span></label>
                    <textarea class="textarea mono" id="guests" name="guests" rows="5" style="min-height:130px" spellcheck="false"
                        placeholder="{{ __('One guest per line: name, e-mail or phone') }}&#10;Aziz Karimov, aziz@example.com&#10;Malika, +998 90 123 45 67">{{ old('guests') }}</textarea>
                    <span class="help">{{ __('You can add more guests later.') }}</span>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary btn-lg" type="submit" data-loading="{{ __('Creating…') }}"><x-i name="heart" :size="20" /><span>{{ __('Create invitation') }}</span></button>
            </div>
        </form>

        <article class="prose" style="margin-top: 36px">
            <h2>{{ __('How it works') }}</h2>
            <ul class="info-list">
                <li><x-i name="check" :size="18" />{{ __('Fill in the details — it takes two minutes.') }}</li>
                <li><x-i name="check" :size="18" />{{ __('Send each guest a personal link by e-mail, Telegram or WhatsApp.') }}</li>
                <li><x-i name="check" :size="18" />{{ __('Guests reply with one tap; you see everyone on your private page.') }}</li>
            </ul>
        </article>
    </div>
</x-app-layout>
