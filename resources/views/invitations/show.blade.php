@php
    $local = $invitation->localEventAt();
    $cover = $invitation->cover_image ? asset($invitation->cover_image) : null;
    $mapUrl = safe_url($invitation->map_url);

    // What a chat app shows when this link is shared: a picture with everything about the wedding, and the same in words
    $code = $invitation->kalta?->url;
    $preview = $code && \App\Support\PreviewImage::available()
        ? route('invitations.preview', $code) . '?v=' . \App\Support\PreviewImage::version($invitation)
        : $cover;
    $summary = $local->translatedFormat('l, j F Y') . ' · ' . $local->format('H:i') . ' · ' . $invitation->venue
        . ($invitation->address ? ', ' . $invitation->address : '');

    // Opening seal: the first letters of the two names; skipped when the page is shown again after a reply
    $groomInitial = mb_strtoupper(mb_substr(trim($invitation->groom_name), 0, 1));
    $brideInitial = mb_strtoupper(mb_substr(trim($invitation->bride_name), 0, 1));
    $showSeal = ! session('success') && ! $errors->any();

    // Month grid (weeks start on Monday) with the wedding day marked
    $monthStart = $local->copy()->startOfMonth();
    $weekdays = collect(range(0, 6))->map(fn ($i) => mb_convert_case(mb_substr($monthStart->copy()->startOfWeek()->addDays($i)->translatedFormat('D'), 0, 2), MB_CASE_TITLE));
    $blanks = $monthStart->isoWeekday() - 1;

    $addressee = $guest?->name ?: ($greeting ?? null);
@endphp
<x-app-layout
    :title="__('Wedding of :couple', ['couple' => $invitation->coupleNames()])"
    :description="$summary"
    :image="$preview"
    :canonical="url()->current() . '?v=' . $invitation->shareVersion()"
    :noindex="true"
    :minimal="true">

    @push('head')
        <link rel="stylesheet" href="{{ asset_v('css/invitation.css') }}">
        <noscript><style>.seal-gate { display: none !important; } .rv { opacity: 1 !important; transform: none !important; }</style></noscript>
    @endpush

    <div class="invite-theme">
        @if ($showSeal)
            <div class="seal-gate" data-seal>
                <div class="gate-top">
                    <p class="gate-lab">{{ __('Wedding invitation') }}</p>
                    @if ($addressee)<p class="gate-name">{{ __('Dear :name,', ['name' => $addressee]) }}</p>@endif
                </div>
                <div class="env">
                    <div class="env-back"></div>
                    <div class="env-flap"></div>
                    <button class="seal" type="button" aria-label="{{ __('Tap the seal to open') }}"><span>{{ $groomInitial }}<i>♥</i>{{ $brideInitial }}</span></button>
                </div>
                <p class="seal-hint">{{ __('Tap the seal to open') }}</p>
            </div>
        @endif

        <div class="petals" aria-hidden="true">@for ($i = 0; $i < 18; $i++)<i style="--x:{{ ($i * 37 + 11) % 100 }}%;--d:{{ 9 + ($i * 7) % 9 }}s;--s:{{ 0.55 + ($i % 4) * 0.2 }};--w:{{ -($i * 1.9) }}s"></i>@endfor</div>

        <div class="twinkles" aria-hidden="true">@for ($i = 0; $i < 22; $i++)<i style="left:{{ ($i * 53 + 7) % 100 }}%;top:{{ ($i * 29 + 13) % 100 }}%;--d:{{ 2.4 + ($i % 5) * 0.7 }}s;--w:{{ -($i * 0.6) }}s;--s:{{ 0.5 + ($i % 3) * 0.3 }}"></i>@endfor</div>

        <main class="invite">
            {{-- 1. Greeting --}}
            <section class="sl sl-hero">
                @include('invitations._flora', ['class' => 'flora flora-tl'])
                @include('invitations._flora', ['class' => 'flora flora-br'])
                <div class="glow" aria-hidden="true"></div>
                @if ($addressee)
                    <p class="lab rv">{{ __('Dear :name,', ['name' => $addressee]) }}</p>
                @else
                    <p class="lab rv">{{ __('Greetings') }}</p>
                @endif
                <div class="rv">@include('invitations._rings')</div>
                <h1 class="script big gold-text rv">{{ __('Invitation') }}</h1>
                <div class="rv">@include('invitations._ornament')</div>
                <p class="txt rv">{{ __('We kindly invite you to our wedding') }}</p>
                <a class="scroll-cue" href="#couple"><span>{{ __('Scroll down') }}</span><b aria-hidden="true"></b></a>
            </section>

            {{-- 2. The couple under a floral arch --}}
            <section class="sl" id="couple">
                <div class="arch rv">
                    <svg class="arch-line" viewBox="0 0 300 460" preserveAspectRatio="none" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 460V210C12 96 74 14 150 14S288 96 288 210V460"/><path d="M26 460V214C26 110 82 34 150 34S274 110 274 214V460" opacity=".55"/></g><circle cx="12" cy="300" r="2.5" fill="currentColor"/><circle cx="288" cy="300" r="2.5" fill="currentColor"/></svg>
                    <svg class="arch-roses" viewBox="0 0 120 50" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M60 8c-10 0-16 8-14 16 2 9 14 12 21 6 6-6 4-16-4-18-6-1-10 4-8 8 2 3 6 3 7 0"/><path d="M34 20c-7 0-11 6-9 11 2 6 10 7 14 3 4-4 2-10-3-11"/><path d="M86 20c7 0 11 6 9 11-2 6-10 7-14 3-4-4-2-10 3-11"/><path d="M22 30C14 24 6 26 2 32c8 2 14 2 20-2zM98 30c8-6 16-4 20 2-8 2-14 2-20-2zM44 38c-6 4-8 8-6 12 5-2 7-6 6-12zM76 38c6 4 8 8 6 12-5-2-7-6-6-12z"/></g></svg>
                    <div class="arch-in">
                        <p class="lab line">{{ __('Wedding ceremony') }}</p>
                        <h2 class="script name-a gold-text">{{ $invitation->groom_name }}</h2>
                        <p class="amp" aria-hidden="true">&amp;</p>
                        <h2 class="script name-b">{{ $invitation->bride_name }}</h2>
                        <p class="script small">{{ __('Two hearts united — the greatest happiness') }}</p>
                    </div>
                </div>
            </section>

            <div class="sl-sep">@include('invitations._ornament')</div>

            {{-- 3. Date --}}
            <section class="sl">
                <p class="lab rv">{{ __('Wedding date') }}</p>
                <p class="script small rv">{{ $local->translatedFormat('j F, l') }}</p>
                <div class="cal rv">
                    @include('invitations._sprig', ['class' => 'sprig sprig-tl'])
                    @include('invitations._sprig', ['class' => 'sprig sprig-br'])
                    <p class="cal-month">{{ mb_convert_case($local->translatedFormat('F Y'), MB_CASE_TITLE) }}</p>
                    <div class="cal-grid" aria-hidden="true">
                        @foreach ($weekdays as $d)<b>{{ $d }}</b>@endforeach
                        @for ($i = 0; $i < $blanks; $i++)<span></span>@endfor
                        @for ($d = 1; $d <= $monthStart->daysInMonth; $d++)<span @class(['on' => $d === $local->day])>{{ $d }}</span>@endfor
                    </div>
                </div>
                <p class="time rv"><x-i name="clock" :size="15" /><b>{{ $local->format('H:i') }}</b> — {{ __('Start time') }}</p>
                <p class="sub rv">
                    <a href="{{ $guest ? route('invitations.guest.calendar', $guest) : route('kaltas.calendar', $kalta->url) }}">{{ __('Add to calendar') }}</a>
                    · <a href="{{ $invitation->calendarUrl() }}" target="_blank" rel="noopener">{{ __('Google Calendar') }}</a>
                </p>
            </section>

            {{-- 4. Countdown --}}
            @if ($invitation->event_at->isFuture())
                <section class="sl">
                    @include('invitations._flora', ['class' => 'flora flora-tr thin'])
                    @include('invitations._flora', ['class' => 'flora flora-bl thin'])
                    <h2 class="script mid gold-text rv">{{ __('Until the wedding') }}</h2>
                    <p class="txt rv">{{ __('We look forward to seeing you') }}</p>
                    <div class="countdown rv" data-countdown="{{ $local->toIso8601String() }}" role="timer" aria-label="{{ __('Time until the wedding') }}">
                        <div><b data-unit="d">0</b><span>{{ __('days') }}</span></div>
                        <div><b data-unit="h">0</b><span>{{ __('hours') }}</span></div>
                        <div><b data-unit="m">0</b><span>{{ __('minutes') }}</span></div>
                        <div><b data-unit="s">0</b><span>{{ __('seconds') }}</span></div>
                    </div>
                </section>
            @endif

            <div class="sl-sep">@include('invitations._ornament')</div>

            {{-- 5. Venue --}}
            <section class="sl">
                <p class="lab rv">{{ __('The venue') }}</p>
                @if ($cover)
                    <div class="photo rv">
                        @include('invitations._sprig', ['class' => 'sprig sprig-tl'])
                        @include('invitations._sprig', ['class' => 'sprig sprig-br'])
                        <img src="{{ $cover }}" alt="{{ $invitation->venue }}" decoding="async" loading="lazy">
                    </div>
                @endif
                <p class="venue rv">{{ $invitation->venue }}</p>
                @if ($invitation->address)
                    <p class="script small addr rv">“{{ $invitation->address }}”</p>
                @endif
                @if ($mapUrl)
                    <a class="pill-line rv" href="{{ $mapUrl }}" target="_blank" rel="noopener nofollow"><x-i name="map-pin" :size="15" />{{ __('View on map') }}</a>
                @endif
            </section>

            {{-- 6. A word from the hosts --}}
            <section class="sl">
                @include('invitations._flora', ['class' => 'flora flora-tl'])
                @include('invitations._flora', ['class' => 'flora flora-br'])
                <p class="lab rv">{{ __('Sincere wishes') }}</p>
                <p class="txt rv">{{ __('With respect:') }}</p>
                <h2 class="script mid gold-text rv">{{ $invitation->coupleNames() }}</h2>
                <div class="rv">@include('invitations._ornament')</div>
                <p class="txt wide rv">{{ $invitation->message ?: __('By coming to our wedding you do us a great honour. Your presence is the most precious gift of this evening.') }}</p>
                <p class="script tiny rv">{{ __('Your presence will bring us great joy') }}</p>
            </section>

            {{-- 7. Reply --}}
            @if ($guest)
                <section class="sl" id="rsvp">
                    @if (session('success'))
                        <p class="rsvp-note" role="status">{{ session('success') }}</p>
                    @endif

                    @if ($invitation->rsvpOpen())
                        <p class="lab rv">{{ __('Please reply') }}</p>
                        @if ($invitation->rsvp_deadline)
                            <p class="txt rv">{{ __('Please reply by :date', ['date' => $invitation->rsvp_deadline->translatedFormat('j F Y')]) }}</p>
                        @endif
                        <form class="rsvp rv" method="POST" action="{{ route('invitations.rsvp', $guest) }}">
                            @csrf
                            <h2>{{ __('Will you attend?') }}</h2>
                            @if ($errors->any())
                                <p class="rsvp-error" role="alert">{{ $errors->first() }}</p>
                            @endif

                            <div class="choices">
                                <input type="radio" name="status" value="attending" id="st-yes" required @checked(old('status', $guest->status) === 'attending')>
                                <label for="st-yes">{{ __('Yes') }}</label>
                                <input type="radio" name="status" value="declined" id="st-no" @checked(old('status', $guest->status) === 'declined')>
                                <label for="st-no">{{ __('No') }}</label>
                            </div>

                            <div class="row" data-party>
                                <label class="field-label" for="party_size">{{ __('Number of people (including you)') }}</label>
                                <input type="number" id="party_size" name="party_size" min="1" max="10" value="{{ old('party_size', max(1, $guest->party_size)) }}">
                            </div>
                            <div class="row">
                                <label class="field-label" for="note">{{ __('Wishes for the couple') }}</label>
                                <textarea id="note" name="note" rows="3" maxlength="1000">{{ old('note', $guest->note) }}</textarea>
                            </div>
                            <button class="pill" type="submit">{{ $guest->responded_at ? __('Update my reply') : __('Send reply') }}</button>
                        </form>
                    @else
                        <p class="rsvp-note">{{ __('Replies are closed.') }}</p>
                    @endif
                </section>
            @endif

            <footer class="invite-foot">
                <p>{{ __('Made with') }} Kalta.uz</p>
                <a class="pill-line" href="{{ lroute('invitations.create') }}">{{ __('Create an invitation') }} <span aria-hidden="true">›</span></a>
            </footer>
        </main>

        <audio data-music src="{{ asset('audio/melody.mp3') }}" loop preload="none"></audio>
        <button class="music" type="button" data-music-btn aria-pressed="false" aria-label="{{ __('Music') }}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><path class="on" d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/><path class="off" d="m16 9 5 6m0-6-5 6"/></svg></button>
    </div>
</x-app-layout>
