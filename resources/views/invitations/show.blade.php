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
        <noscript><style>.seal-gate { display: none !important; }</style></noscript>
    @endpush

    <div class="invite-theme">
        @if ($showSeal)
            {{-- Opening: the seal breaks, the flap lifts, the card slides out and fills the screen --}}
            <div class="seal-gate" data-seal>
                <div class="gate-top">
                    <p class="gate-lab">{{ __('Wedding invitation') }}</p>
                    @if ($addressee)<p class="gate-name">{{ __('Dear :name,', ['name' => $addressee]) }}</p>@endif
                </div>
                <div class="env-stage">
                    <div class="env">
                        <div class="env-back"></div>
                        <div class="env-card"><div class="env-card-in"><p>{{ __('Invitation') }}</p>@include('invitations._ornament')<span>{{ $groomInitial }} &amp; {{ $brideInitial }}</span></div></div>
                        <div class="env-front"></div>
                        <div class="env-flap"></div>
                        <button class="seal" type="button" aria-label="{{ __('Tap the seal to open') }}">
                            <span class="sh sh-l"><span>{{ $groomInitial }}<i>♥</i>{{ $brideInitial }}</span></span>
                            <span class="sh sh-r"><span>{{ $groomInitial }}<i>♥</i>{{ $brideInitial }}</span></span>
                        </button>
                        <span class="sparks" aria-hidden="true">@for ($i = 0; $i < 12; $i++)<i style="--a:{{ $i * 30 + ($i % 2) * 9 }}deg;--r:{{ 54 + ($i % 3) * 16 }}px"></i>@endfor</span>
                    </div>
                </div>
                <p class="seal-hint">{{ __('Tap the seal to open') }}</p>
            </div>
        @endif

        <div class="petals" aria-hidden="true">@for ($i = 0; $i < 21; $i++)<i class="z{{ $i % 3 }}" style="--x:{{ ($i * 37 + 11) % 100 }}%;--d:{{ 10 + ($i * 7) % 9 }}s;--w:{{ -($i * 1.7) }}s;--sw:{{ (($i * 23) % 70) - 35 }}px"></i>@endfor</div>

        <div class="twinkles" aria-hidden="true">@for ($i = 0; $i < 22; $i++)<i style="left:{{ ($i * 53 + 7) % 100 }}%;top:{{ ($i * 29 + 13) % 100 }}%;--d:{{ 2.4 + ($i % 5) * 0.7 }}s;--w:{{ -($i * 0.6) }}s;--s:{{ 0.5 + ($i % 3) * 0.3 }}"></i>@endfor</div>

        {{-- A gold thread down the side that follows the reader through the card --}}
        <div class="thread" aria-hidden="true"><b></b></div>

        <main class="invite">
            {{-- 1. Greeting --}}
            <section class="sl sl-hero">
                @include('invitations._flora', ['class' => 'flora flora-tl'])
                @include('invitations._flora', ['class' => 'flora flora-br'])
                <div class="glow" aria-hidden="true"></div>
                <div class="hero-in">
                    @if ($addressee)
                        <p class="lab rv">{{ __('Dear :name,', ['name' => $addressee]) }}</p>
                    @else
                        <p class="lab rv">{{ __('Greetings') }}</p>
                    @endif
                    <div class="rv" style="--dl:.15s">@include('invitations._rings')</div>
                    <h1 class="script big gold-text ink rv" style="--dl:.35s">{{ __('Invitation') }}</h1>
                    <div class="rv" style="--dl:.8s">@include('invitations._ornament', ['class' => 'draw'])</div>
                    <p class="txt rv" style="--dl:1s">{{ __('We kindly invite you to our wedding') }}</p>
                </div>
                <a class="scroll-cue" href="#couple"><span>{{ __('Scroll down') }}</span><b aria-hidden="true"></b></a>
            </section>

            {{-- 2. The couple under a floral arch --}}
            <section class="sl" id="couple">
                <div class="arch rv">
                    <svg class="arch-line draw" viewBox="0 0 300 460" preserveAspectRatio="none" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 460V210C12 96 74 14 150 14S288 96 288 210V460"/><path d="M26 460V214C26 110 82 34 150 34S274 110 274 214V460" opacity=".55"/></g></svg>
                    <svg class="arch-roses draw" viewBox="0 0 120 50" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M60 8c-10 0-16 8-14 16 2 9 14 12 21 6 6-6 4-16-4-18-6-1-10 4-8 8 2 3 6 3 7 0"/><path d="M34 20c-7 0-11 6-9 11 2 6 10 7 14 3 4-4 2-10-3-11"/><path d="M86 20c7 0 11 6 9 11-2 6-10 7-14 3-4-4-2-10 3-11"/><path d="M22 30C14 24 6 26 2 32c8 2 14 2 20-2zM98 30c8-6 16-4 20 2-8 2-14 2-20-2zM44 38c-6 4-8 8-6 12 5-2 7-6 6-12zM76 38c6 4 8 8 6 12-5-2-7-6-6-12z"/></g></svg>
                    <div class="arch-in">
                        <p class="lab line">{{ __('Wedding ceremony') }}</p>
                        <h2 class="script name-a gold-text ink" style="--dl:.5s">{{ $invitation->groom_name }}</h2>
                        <p class="amp" aria-hidden="true">&amp;</p>
                        <h2 class="script name-b ink" style="--dl:1.2s">{{ $invitation->bride_name }}</h2>
                        <p class="script small">{{ __('Two hearts united — the greatest happiness') }}</p>
                    </div>
                </div>
            </section>

            <div class="sl-sep"><i class="thr rv"></i>@include('invitations._ornament', ['class' => 'draw'])</div>

            {{-- 3. Date --}}
            <section class="sl">
                <p class="lab rv">{{ __('Wedding date') }}</p>
                <p class="script small rv" style="--dl:.15s">{{ $local->translatedFormat('j F, l') }}</p>
                <div class="cal rv" style="--dl:.25s">
                    @include('invitations._sprig', ['class' => 'sprig sprig-tl'])
                    @include('invitations._sprig', ['class' => 'sprig sprig-br'])
                    <p class="cal-month">{{ mb_convert_case($local->translatedFormat('F Y'), MB_CASE_TITLE) }}</p>
                    <div class="cal-grid" aria-hidden="true">
                        @foreach ($weekdays as $d)<b>{{ $d }}</b>@endforeach
                        @for ($i = 0; $i < $blanks; $i++)<span></span>@endfor
                        @for ($d = 1; $d <= $monthStart->daysInMonth; $d++)
                            @if ($d === $local->day)
                                <span class="on" style="--i:{{ $d + $blanks }}">{{ $d }}<svg class="day-ring draw" viewBox="0 0 44 44"><path d="M24 4C35 5 41 13 40 23S31 40 21 40 4 32 4 22 12 5 26 6"/></svg><svg class="heart" viewBox="0 0 24 24"><path d="M12 21s-7.5-4.6-9.3-9.2C1.4 8.4 3.6 5 7 5c2 0 3.6 1.1 5 3 1.4-1.9 3-3 5-3 3.4 0 5.6 3.4 4.3 6.8C19.5 16.4 12 21 12 21z"/></svg></span>
                            @else
                                <span style="--i:{{ $d + $blanks }}">{{ $d }}</span>
                            @endif
                        @endfor
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
                    <h2 class="script mid gold-text ink rv">{{ __('Until the wedding') }}</h2>
                    <p class="txt rv" style="--dl:.3s">{{ __('We look forward to seeing you') }}</p>
                    <div class="countdown rv" data-countdown="{{ $local->toIso8601String() }}" role="timer" aria-label="{{ __('Time until the wedding') }}">
                        <div style="--i:0"><b data-unit="d">0</b><span>{{ __('days') }}</span></div>
                        <div style="--i:1"><b data-unit="h">0</b><span>{{ __('hours') }}</span></div>
                        <div style="--i:2"><b data-unit="m">0</b><span>{{ __('minutes') }}</span></div>
                        <div style="--i:3"><b data-unit="s">0</b><span>{{ __('seconds') }}</span></div>
                    </div>
                </section>
            @endif

            <div class="sl-sep"><i class="thr rv"></i>@include('invitations._ornament', ['class' => 'draw'])</div>

            {{-- 5. Venue --}}
            <section class="sl">
                <p class="lab rv">{{ __('The venue') }}</p>
                @if ($cover)
                    <div class="photo rv">
                        @include('invitations._sprig', ['class' => 'sprig sprig-tl'])
                        @include('invitations._sprig', ['class' => 'sprig sprig-br'])
                        <div class="photo-in"><img src="{{ $cover }}" alt="{{ $invitation->venue }}" decoding="async" loading="lazy"></div>
                    </div>
                @endif
                <p class="venue rv">{{ $invitation->venue }}</p>
                @if ($invitation->address)
                    <p class="script small addr rv" style="--dl:.15s">“{{ $invitation->address }}”</p>
                @endif
                @if ($mapUrl)
                    <a class="pill-line pin rv" style="--dl:.3s" href="{{ $mapUrl }}" target="_blank" rel="noopener nofollow"><x-i name="map-pin" :size="15" />{{ __('View on map') }}</a>
                @endif
            </section>

            {{-- 6. A word from the hosts --}}
            <section class="sl">
                @include('invitations._flora', ['class' => 'flora flora-tl'])
                @include('invitations._flora', ['class' => 'flora flora-br'])
                <p class="lab rv">{{ __('Sincere wishes') }}</p>
                <p class="txt rv" style="--dl:.15s">{{ __('With respect:') }}</p>
                <h2 class="script mid gold-text ink rv" style="--dl:.3s">{{ $invitation->coupleNames() }}</h2>
                <div class="rv" style="--dl:.6s">@include('invitations._ornament', ['class' => 'draw'])</div>
                <p class="txt wide rv" style="--dl:.75s">{{ $invitation->message ?: __('By coming to our wedding you do us a great honour. Your presence is the most precious gift of this evening.') }}</p>
                <p class="script tiny rv" style="--dl:1s">{{ __('Your presence will bring us great joy') }}</p>
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
                                <label for="st-yes" class="yes">{{ __('Yes') }}</label>
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

        <audio data-music src="{{ asset('audio/wedding-march.mp3') }}" loop preload="none"></audio>
        <button class="music" type="button" data-music-btn aria-pressed="false" aria-label="{{ __('Music') }}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><path class="on" d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/><path class="off" d="m16 9 5 6m0-6-5 6"/></svg></button>
    </div>
</x-app-layout>
