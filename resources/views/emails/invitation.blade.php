@php $local = $invitation->localEventAt(); @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<body style="margin:0;padding:24px;background:#efe5d2;font-family:Georgia,'Times New Roman',serif;color:#3a2e22">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#fbf6ec;border:1px solid #e4d4b6">
                    @if ($invitation->cover_image)
                        <tr><td><img src="{{ asset($invitation->cover_image) }}" width="560" alt="" style="display:block;width:100%;height:auto"></td></tr>
                    @endif
                    <tr>
                        <td style="padding:40px 36px;text-align:center">
                            <p style="margin:0 0 6px;font-size:18px">{{ __('Dear :name,', ['name' => $guest->name]) }}</p>
                            <p style="margin:0 0 22px;font-style:italic;color:#6b5a46;font-size:18px">{{ __('we would be honoured to have you celebrate the wedding of') }}</p>
                            <p style="margin:0 0 22px;font-size:34px;font-style:italic;color:#8a6428">{{ $invitation->groom_name }} &amp; {{ $invitation->bride_name }}</p>
                            <p style="margin:0;font-size:20px;font-weight:bold">{{ $local->translatedFormat('l, j F Y') }} · {{ $local->format('H:i') }}</p>
                            <p style="margin:8px 0 0;font-size:18px"><strong>{{ $invitation->venue }}</strong></p>
                            @if ($invitation->address)
                                <p style="margin:2px 0 0;color:#6b5a46">{{ $invitation->address }}</p>
                            @endif
                            @if ($invitation->message)
                                <p style="margin:22px 0 0;font-style:italic;color:#6b5a46">{!! nl2br(e($invitation->message)) !!}</p>
                            @endif
                            <p style="margin:30px 0 0">
                                <a href="{{ $guest->link() }}" style="display:inline-block;padding:14px 30px;background:#8a6428;color:#ffffff;text-decoration:none;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;letter-spacing:1px;text-transform:uppercase">{{ __('Open invitation and reply') }}</a>
                            </p>
                            @if ($invitation->rsvp_deadline)
                                <p style="margin:14px 0 0;font-family:Arial,sans-serif;font-size:13px;color:#6b5a46">{{ __('Please reply by :date', ['date' => $invitation->rsvp_deadline->translatedFormat('j F Y')]) }}</p>
                            @endif
                        </td>
                    </tr>
                </table>
                <p style="font-family:Arial,sans-serif;font-size:12px;color:#9a8b7a">{{ __('Sent with Kalta.uz') }}</p>
            </td>
        </tr>
    </table>
</body>

</html>
