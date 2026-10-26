@php
    $local = $invitation->localEventAt();
    $days = $invitation->daysUntil();
    $cover = $invitation->cover_image ? asset($invitation->cover_image) : null;
    $token = $invitation->manage_token;
    $total = max(1, $stats['total']);
    $pct = fn (int $n) => (int) round($n / $total * 100);
    $dayParts = ['morning' => __('morning'), 'afternoon' => __('afternoon'), 'evening' => __('evening'), 'night' => __('night')];
    $channels = ['telegram' => 'Telegram', 'whatsapp' => 'WhatsApp', 'email' => __('e-mail')];

    // What gets sent when the hosts share the general link
    $inviteText = __('You are invited to the wedding of :couple!', ['couple' => $invitation->coupleNames()], $invitation->locale);
    $shareUrl = $invitation->shareUrl(); // the general link with a tag, so chat apps show the current preview card
    $telegramGeneral = 'https://t.me/share/url?' . http_build_query(['url' => $shareUrl, 'text' => $inviteText], '', '&', PHP_QUERY_RFC3986);
    $whatsappGeneral = 'https://wa.me/?' . http_build_query(['text' => $inviteText . "\n" . $shareUrl], '', '&', PHP_QUERY_RFC3986);
    $allLinks = $guests->map(fn ($guest) => $guest->name . ' — ' . $guest->shareLink())->implode("\n");

    $client = [
        'marked' => __('Marked as sent: :name'),
        'undo' => __('Undo'),
        'showing' => __('Showing :shown of :total'),
        'nothing' => __('No guests match.'),
        'recognised' => __('Guests recognised: :count'),
        'sentNow' => __('Sent just now'),
        'newReplies' => __('New replies since your last visit: :count'),
        'copied' => __('Copied!'),
        'error' => __('Something went wrong. Please try again.'),
    ];
@endphp
<x-app-layout :title="$invitation->coupleNames()" :noindex="true" :ads="false" body-class="page-manage">
    @push('head')
        <link rel="stylesheet" href="{{ asset_v('css/manage.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset_v('js/manage.js') }}" defer></script>
    @endpush

    <div class="container mid manage page-body" data-manage="{{ $invitation->id }}" data-i18n="{{ json_encode($client, JSON_UNESCAPED_UNICODE) }}">
        @include('invitations._sprite', ['icons' => ['circle', 'send', 'eye', 'check-circle', 'x', 'phone', 'mail', 'telegram', 'whatsapp', 'dots', 'share', 'copy', 'link', 'check', 'undo', 'edit', 'trash']])

        <section class="card mg-hero @if ($cover) has-cover @endif" aria-labelledby="mg-title">
            @if ($cover)
                <img class="mg-cover" src="{{ $cover }}" alt="" width="96" height="96" decoding="async">
            @endif
            <div class="mg-hero-main">
                <p class="eyebrow-label">{{ __('Your invitation') }}</p>
                <h1 id="mg-title">{{ $invitation->coupleNames() }}</h1>
                <p class="mg-line"><x-i name="calendar" :size="16" /><span>{{ $local->translatedFormat('l, j F Y') }} · {{ $local->format('H:i') }} <span class="muted">({{ $dayParts[$invitation->dayPart()] }})</span></span></p>
                <p class="mg-line"><x-i name="map-pin" :size="16" /><span>{{ $invitation->venue }}@if ($invitation->address), {{ $invitation->address }}@endif</span></p>
                <p class="mg-pills">
                    <span class="pill @if ($days >= 0) pill-brand @endif"><x-i name="clock" :size="14" />
                        @if ($days > 1){{ __('Days to go: :count', ['count' => $days]) }}
                        @elseif ($days === 1){{ __('The wedding is tomorrow') }}
                        @elseif ($days === 0){{ __('The wedding is today!') }}
                        @else{{ __('The wedding has taken place') }}@endif
                    </span>
                    <span class="pill"><x-i name="{{ $invitation->rsvpOpen() ? 'check-circle' : 'lock' }}" :size="14" />
                        @if (! $invitation->rsvpOpen()){{ __('Replies are closed') }}
                        @elseif ($invitation->rsvp_deadline){{ __('Replies open until :date', ['date' => $invitation->rsvp_deadline->translatedFormat('j F')]) }}
                        @else{{ __('Replies are open') }}@endif
                    </span>
                </p>
            </div>
            <div class="mg-hero-actions no-print">
                <a class="btn btn-secondary" href="{{ route('invitations.edit', $token) }}"><x-i name="edit" :size="18" />{{ __('Edit details') }}</a>
                <a class="btn btn-secondary" href="{{ $publicUrl }}?preview=1" target="_blank" rel="noopener"><x-i name="eye" :size="18" />{{ __('Preview') }}</a>
            </div>
        </section>

        <div class="mg-banner no-print" data-new-banner role="status" hidden>
            <x-i name="bell" :size="18" /><span data-new-text></span>
            <button type="button" class="btn btn-secondary btn-sm" data-show-new>{{ __('Show') }}</button>
        </div>

        <section class="card mg-stats" aria-labelledby="mg-stats-title">
            <h2 id="mg-stats-title" class="sr-only">{{ __('Summary') }}</h2>

            @if ($stats['total'] === 0)
                <div class="empty">
                    <x-i name="users" :size="32" />
                    <p>{{ __('No guests yet. Add your guest list below and each guest gets a personal link.') }}</p>
                    <a class="btn btn-primary" href="#add-guests" data-focus="#guests-text"><x-i name="user-plus" :size="18" />{{ __('Add guests') }}</a>
                </div>
            @else
                <div class="mg-headline">
                    <div class="mg-big"><b>{{ $stats['people'] }}</b><span>{{ __('People coming') }}</span></div>
                    <p class="muted mg-sub">{{ __(':replied of :total guests have replied.', ['replied' => $stats['replied'], 'total' => $stats['total']]) }}</p>
                </div>

                <div class="seg-bar" role="img" aria-label="{{ __('Coming') }}: {{ $stats['attending'] }}, {{ __('Declined') }}: {{ $stats['declined'] }}, {{ __('No reply') }}: {{ $stats['pending'] }}">
                    <span class="seg seg-ok" style="width: {{ $pct($stats['attending']) }}%"></span>
                    <span class="seg seg-no" style="width: {{ $pct($stats['declined']) }}%"></span>
                </div>
                <ul class="seg-legend">
                    <li><i class="dot dot-ok"></i>{{ __('Coming') }} <b>{{ $stats['attending'] }}</b></li>
                    <li><i class="dot dot-no"></i>{{ __('Declined') }} <b>{{ $stats['declined'] }}</b></li>
                    <li><i class="dot"></i>{{ __('No reply') }} <b>{{ $stats['pending'] }}</b></li>
                </ul>

                <ul class="funnel">
                    @foreach ([[__('Sent'), $stats['sent']], [__('Opened'), $stats['seen']], [__('Replied'), $stats['replied']]] as [$label, $n])
                        <li>
                            <span class="funnel-label">{{ $label }}</span>
                            <span class="funnel-n"><b>{{ $n }}</b> / {{ $stats['total'] }}</span>
                            <span class="meter" aria-hidden="true"><i style="width: {{ $pct($n) }}%"></i></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card no-print" aria-labelledby="mg-share-title" data-copy-scope data-qr-scope data-qr-name="kalta-{{ $invitation->kalta->url }}">
            <h2 id="mg-share-title">{{ __('One link for everyone') }}</h2>
            <p class="muted">{{ __('Post it in a group chat or print the QR code. Guests can read the invitation, but only the personal links below have the reply form.') }}</p>
            <div class="copy-field">
                <input type="text" readonly value="{{ $shareUrl }}" data-field="url" aria-label="{{ __('General link') }}" onfocus="this.select()">
                <button type="button" class="btn btn-primary" data-copy-field="url"><x-i name="copy" :size="18" /><span>{{ __('Copy') }}</span></button>
            </div>
            <div class="share-row">
                <a class="btn btn-secondary btn-sm" href="{{ $telegramGeneral }}" target="_blank" rel="noopener"><x-i name="telegram" :size="16" />Telegram</a>
                <a class="btn btn-secondary btn-sm" href="{{ $whatsappGeneral }}" target="_blank" rel="noopener"><x-i name="whatsapp" :size="16" />WhatsApp</a>
                <button type="button" class="btn btn-secondary btn-sm" data-share data-share-url="{{ $shareUrl }}" data-share-title="{{ $invitation->coupleNames() }}" hidden><x-i name="share" :size="16" />{{ __('Share…') }}</button>
            </div>
            <details class="qr-toggle">
                <summary><x-i name="qr" :size="16" />{{ __('QR code') }}<x-i name="chevron-down" :size="16" class="chev" /></summary>
                <div class="qr-box">
                    <div class="qr">{!! qr_svg($publicUrl, __('QR code for :url', ['url' => $publicUrl])) !!}</div>
                    <div class="qr-actions">
                        <button type="button" class="btn btn-secondary btn-sm" data-qr-download="png"><x-i name="download" :size="14" />PNG</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-qr-download="svg"><x-i name="download" :size="14" />SVG</button>
                    </div>
                </div>
            </details>
            <p class="help">{{ __('The general link was opened :count time(s).', ['count' => $clicks]) }}</p>
        </section>

        <section class="card" id="guests" aria-labelledby="mg-guests-title" data-guests>
            <div class="mg-guests-head">
                <h2 id="mg-guests-title">{{ __('Guests') }} <span class="count">{{ $stats['total'] }}</span></h2>
                <div class="mg-guests-actions no-print">
                    <a class="btn btn-primary btn-sm" href="#add-guests" data-focus="#guests-text"><x-i name="user-plus" :size="16" />{{ __('Add guests') }}</a>
                    @if ($stats['total'] > 0)
                        <details class="menu" data-menu>
                            <summary class="icon-btn" aria-label="{{ __('More') }}"><x-i name="dots" :size="18" /></summary>
                            <ul class="menu-list">
                                <li><a href="{{ route('invitations.export', $token) }}"><span><x-i name="download" :size="16" />{{ __('Download list (CSV)') }}</span></a></li>
                                <li><button type="button" data-print><x-i name="printer" :size="16" />{{ __('Print list') }}</button></li>
                                <li><button type="button" data-copy="#all-links"><x-i name="copy" :size="16" />{{ __('Copy all personal links') }}</button></li>
                                <li><a href="{{ route('invitations.edit', $token) }}#share_message"><span><x-i name="edit" :size="16" />{{ __('Change the message you send') }}</span></a></li>
                            </ul>
                        </details>
                    @endif
                </div>
            </div>

            @if ($stats['total'] > 0)
                <textarea id="all-links" hidden readonly>{{ $allLinks }}</textarea>

                <div class="guest-tools no-print" data-guest-tools hidden>
                    <div class="input-icon">
                        <x-i name="search" :size="18" />
                        <input class="input" type="search" data-search placeholder="{{ __('Search guests…') }}" aria-label="{{ __('Search guests…') }}" autocomplete="off" autocapitalize="off" spellcheck="false">
                    </div>
                    <div class="chips" role="group" aria-label="{{ __('Show') }}">
                        <button type="button" class="chip" data-filter="all" aria-pressed="true">{{ __('All') }} <b>{{ $stats['total'] }}</b></button>
                        <button type="button" class="chip" data-filter="unsent" aria-pressed="false">{{ __('Not sent') }} <b data-count-for="unsent">{{ $stats['unsent'] }}</b></button>
                        <button type="button" class="chip" data-filter="waiting" aria-pressed="false">{{ __('Waiting') }} <b data-count-for="waiting">{{ $stats['waiting'] }}</b></button>
                        <button type="button" class="chip" data-filter="coming" aria-pressed="false">{{ __('Coming') }} <b data-count-for="coming">{{ $stats['attending'] }}</b></button>
                        <button type="button" class="chip" data-filter="declined" aria-pressed="false">{{ __('Declined') }} <b data-count-for="declined">{{ $stats['declined'] }}</b></button>
                    </div>
                    <p class="help" data-showing aria-live="polite" hidden></p>
                </div>

                @if ($stats['without_phone'] * 2 > $stats['total'])
                    <p class="tip no-print" data-tip>
                        <x-i name="phone" :size="16" /><span>{{ __('Tip: with a phone number, WhatsApp opens the chat with the guest directly. Add numbers in the box below (one line per guest: name, phone) or with Edit in the guest menu.') }}</span>
                        <button type="button" class="tip-close" data-tip-close aria-label="{{ __('Close') }}" hidden><x-i name="x" :size="16" /></button>
                    </p>
                @endif

                <ul class="guests" data-guest-list>
                    @foreach ($guests as $guest)
                        @php
                            $stage = $guest->stage();
                            $search = \App\Support\GuestList::key($guest->name . ' ' . $guest->email . ' ' . $guest->phone . ' ' . \App\Support\GuestList::digits($guest->phone));
                            $search = str_replace(["'"], '', $search);
                        @endphp
                        <li class="guest-card" id="guest-{{ $guest->id }}" data-guest data-stage="{{ $stage }}" data-sent="{{ in_array($stage, ['sent', 'seen'], true) ? 1 : 0 }}" data-search="{{ $search }}"
                            data-name="{{ $guest->name }}" data-text="{{ $guest->shareText() }}" data-link="{{ $guest->shareLink() }}"
                            data-responded="{{ $guest->responded_at?->timestamp ?? 0 }}">
                            <span class="avatar av-{{ $stage }}" aria-hidden="true">{{ $guest->initial() }}</span>

                            <div class="g-main">
                                <div class="g-title">
                                    <strong class="g-name">{{ $guest->name }}</strong>
                                    <span class="chip-status st-{{ $stage }}" data-status-chip>
                                        @switch($stage)
                                            @case('coming')<x-iu name="check-circle" :size="14" />{{ __('Coming') }} · {{ $guest->party_size }}@break
                                            @case('declined')<x-iu name="x" :size="14" />{{ __('Declined') }}@break
                                            @case('seen')<x-iu name="eye" :size="14" />{{ __('Opened') }}@break
                                            @case('sent')<x-iu name="send" :size="14" />{{ __('Sent') }}@break
                                            @default<x-iu name="circle" :size="14" />{{ __('Not sent') }}
                                        @endswitch
                                    </span>
                                    <span class="new-tag" data-new-tag hidden>{{ __('New') }}</span>
                                </div>

                                <p class="g-meta" @if (! $guest->phone && ! $guest->email) hidden @endif>
                                    @if ($guest->phone)<span><x-iu name="phone" :size="14" />{{ $guest->phone }}</span>@endif
                                    @if ($guest->email)<span><x-iu name="mail" :size="14" />{{ $guest->email }}</span>@endif
                                </p>

                                <p class="g-meta g-activity" data-activity @if (! $guest->sent_at && $guest->views < 2) hidden @endif>
                                    @if ($guest->sent_at)
                                        <span>{{ isset($channels[$guest->sent_via])
                                            ? __('Sent via :channel :when', ['channel' => $channels[$guest->sent_via], 'when' => $guest->sent_at->diffForHumans()])
                                            : __('Sent :when', ['when' => $guest->sent_at->diffForHumans()]) }}</span>
                                    @endif
                                    @if ($guest->views > 1)<span>{{ __('Opened :count times', ['count' => $guest->views]) }}</span>@endif
                                </p>

                                @if ($guest->note)
                                    <p class="g-note">“{{ $guest->note }}”</p>
                                @endif
                            </div>

                            <div class="g-actions no-print">
                                <a class="btn btn-secondary" href="{{ $guest->telegramShareUrl() }}" target="_blank" rel="noopener" data-track="telegram"><x-iu name="telegram" :size="18" />Telegram</a>
                                <a class="btn btn-secondary" href="{{ $guest->whatsappShareUrl() }}" target="_blank" rel="noopener" data-track="whatsapp"><x-iu name="whatsapp" :size="18" />WhatsApp</a>
                                <details class="menu" data-menu>
                                    <summary class="icon-btn" aria-label="{{ __('More actions for :name', ['name' => $guest->name]) }}"><x-iu name="dots" :size="18" /></summary>
                                    <ul class="menu-list">
                                        <li><button type="button" data-guest-share hidden><x-iu name="share" :size="16" />{{ __('Share…') }}</button></li>
                                        <li><button type="button" data-copy-text="{{ $guest->shareMessage() }}" data-track="copy"><x-iu name="copy" :size="16" />{{ __('Copy message') }}</button></li>
                                        <li><button type="button" data-copy-text="{{ $guest->shareLink() }}" data-track="copy"><x-iu name="link" :size="16" />{{ __('Copy personal link') }}</button></li>
                                        @if (in_array($stage, ['sent', 'seen'], true))
                                            <li><a href="{{ $guest->reminderTelegramUrl() }}" target="_blank" rel="noopener"><span><x-iu name="telegram" :size="16" />{{ __('Remind via Telegram') }}</span></a></li>
                                            <li><a href="{{ $guest->reminderWhatsappUrl() }}" target="_blank" rel="noopener"><span><x-iu name="whatsapp" :size="16" />{{ __('Remind via WhatsApp') }}</span></a></li>
                                            <li><button type="button" data-copy-text="{{ $guest->reminderText() }}&#10;{{ $guest->shareLink() }}"><x-iu name="copy" :size="16" />{{ __('Copy reminder') }}</button></li>
                                        @endif
                                        <li><a href="{{ $guest->link() }}?preview=1" target="_blank" rel="noopener"><span><x-iu name="eye" :size="16" />{{ __('Open guest page') }}</span></a></li>
                                        @if ($guest->phone)
                                            <li><a href="tel:{{ preg_replace('/[^\d+]/', '', $guest->phone) }}"><span><x-iu name="phone" :size="16" />{{ __('Call') }}</span></a></li>
                                        @endif
                                        <li class="sep" role="separator"></li>
                                        @if (in_array($stage, ['unsent', 'sent', 'seen'], true))
                                        <li>
                                            <button type="submit" form="guest-action" formaction="{{ route('invitations.guests.sent', [$token, $guest]) }}" data-sent-toggle
                                                @if (in_array($stage, ['sent', 'seen'], true)) name="undo" value="1" @endif>
                                                <span class="when-unsent"><x-iu name="check" :size="16" />{{ __('Mark as sent') }}</span>
                                                <span class="when-sent"><x-iu name="undo" :size="16" />{{ __('Mark as not sent') }}</span>
                                            </button>
                                        </li>
                                        @endif
                                        <li><a href="{{ route('invitations.guests.edit', [$token, $guest]) }}"><span><x-iu name="edit" :size="16" />{{ __('Edit guest') }}</span></a></li>
                                        <li>
                                            <button type="submit" class="danger" form="guest-action" formaction="{{ route('invitations.guests.destroy', [$token, $guest]) }}" name="_method" value="DELETE"
                                                data-confirm-click="{{ __('Remove :name from the list?', ['name' => $guest->name]) }}"><x-iu name="trash" :size="16" />{{ __('Remove') }}</button>
                                        </li>
                                    </ul>
                                </details>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="empty" data-no-match hidden>{{ __('No guests match.') }}</p>

                {{-- One form for every guest's "mark as sent" / "remove" button: far less markup than a form per guest --}}
                <form id="guest-action" method="POST" hidden>@csrf</form>
            @endif
        </section>

        <form class="card no-print" id="add-guests" action="{{ route('invitations.guests.store', $token) }}" method="POST">
            @csrf
            <h2>{{ __('Add guests') }}</h2>
            <div class="field">
                <label class="label" for="guests-text">{{ __('Guest list') }}</label>
                <textarea class="textarea mono" id="guests-text" name="guests" rows="5" required spellcheck="false" style="min-height:130px"
                    placeholder="{{ __('One guest per line: name, e-mail or phone') }}&#10;Aziz Karimov, aziz@example.com&#10;Malika, +998 90 123 45 67" data-guest-input></textarea>
                <span class="help" data-guest-count aria-live="polite">{{ __('Tip: copy a column of names from Excel or Notes. People already on the list are skipped, and a phone number for someone listed without one is added to them.') }}</span>
            </div>
            <button class="btn btn-primary" type="submit"><x-i name="user-plus" :size="18" />{{ __('Add guests') }}</button>
        </form>

        @if ($stats['with_email'] > 0)
            <section class="card no-print" aria-labelledby="mg-mail-title">
                <h2 id="mg-mail-title">{{ __('E-mail invitations') }}</h2>
                <p class="muted">{{ __(':count guest(s) have an e-mail address; :sent of them already e-mailed.', ['count' => $stats['with_email'], 'sent' => $stats['emailed']]) }}</p>
                <form action="{{ route('invitations.send', $token) }}" method="POST" class="row-between" style="gap:16px">
                    @csrf
                    <label class="check"><input type="checkbox" name="resend" value="1"> <span>{{ __('Also resend to guests already invited') }}</span></label>
                    <button class="btn btn-secondary" type="submit" data-loading="{{ __('Sending…') }}"><x-i name="mail" :size="18" /><span>{{ __('Send e-mail invitations') }}</span></button>
                </form>
            </section>
        @endif

        <section class="card no-print" aria-labelledby="mg-private-title" data-copy-scope>
            <h2 id="mg-private-title"><x-i name="lock" :size="20" /> {{ __('Your private page') }}</h2>
            <p class="muted">{{ __('The address of this page is the only key to your guest list. Keep it safe and do not post it anywhere public.') }}</p>
            <div class="copy-field">
                <input type="text" readonly value="{{ $manageUrl }}" data-field="url" aria-label="{{ __('Your private page') }}" onfocus="this.select()">
                <button type="button" class="btn btn-secondary" data-copy-field="url"><x-i name="copy" :size="18" /><span>{{ __('Copy') }}</span></button>
            </div>
            <div class="share-row">
                <a class="btn btn-secondary btn-sm" href="https://t.me/share/url?{{ http_build_query(['url' => $manageUrl], '', '&', PHP_QUERY_RFC3986) }}" target="_blank" rel="noopener"><x-i name="telegram" :size="16" />{{ __('Send to myself in Telegram') }}</a>
            </div>
        </section>

        <section class="card danger-zone no-print" aria-labelledby="mg-delete-title">
            <h2 id="mg-delete-title">{{ __('Delete invitation') }}</h2>
            <p class="muted">{{ __('This removes the invitation, the guest list and all replies for good. Every link you sent stops working.') }}</p>
            <form action="{{ route('invitations.destroy', $token) }}" method="POST" data-confirm="{{ __('Delete this invitation and its guest list? This cannot be undone.') }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger" type="submit"><x-i name="trash" :size="18" />{{ __('Delete invitation') }}</button>
            </form>
        </section>
    </div>

    <template data-stage-chips>
        <span data-stage="unsent" class="chip-status st-unsent"><x-i name="circle" :size="14" />{{ __('Not sent') }}</span>
        <span data-stage="sent" class="chip-status st-sent"><x-i name="send" :size="14" />{{ __('Sent') }}</span>
        <span data-stage="seen" class="chip-status st-seen"><x-i name="eye" :size="14" />{{ __('Opened') }}</span>
    </template>

    <div class="snack no-print" data-snack role="status" aria-live="polite" hidden>
        <span data-snack-text></span>
        <button type="button" data-snack-undo>{{ __('Undo') }}</button>
    </div>
</x-app-layout>
