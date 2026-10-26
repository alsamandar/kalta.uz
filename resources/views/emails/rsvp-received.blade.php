<p><strong>{{ $guest->name }}</strong>
    @if ($guest->status === 'attending')
        {{ __('will attend (:count people).', ['count' => $guest->party_size]) }}
    @else
        {{ __("can't attend.") }}
    @endif
</p>
@if ($guest->note)
    <p>“{{ $guest->note }}”</p>
@endif
<p><a href="{{ route('invitations.manage', $invitation->manage_token) }}">{{ __('View all replies') }}</a></p>
