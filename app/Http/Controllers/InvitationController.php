<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvitationRequest;
use App\Http\Requests\UpdateGuestRequest;
use App\Http\Requests\UpdateInvitationRequest;
use App\Mail\RsvpReceivedMail;
use App\Mail\WeddingInvitationMail;
use App\Models\Invitation;
use App\Models\InvitationGuest;
use App\Models\Kalta;
use App\Support\GuestList;
use App\Support\Ics;
use App\Support\PreviewImage;
use App\Support\Submission;
use App\Support\Visitors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function create()
    {
        return view('invitations.create');
    }

    public function store(StoreInvitationRequest $request)
    {
        return Submission::once($request, function () use ($request) {
            $invitation = Invitation::create($request->invitationData());
            $invitation->kalta()->create([
                'url' => randomString(),
                'user_id' => Auth::id() ?? 1,
                'ip' => $request->ip(),
                'name' => $invitation->coupleNames(),
            ]);
            $invitation->addGuestsFromText($request->input('guests'));

            return route('invitations.manage', $invitation->manage_token);
        }, fn (string $url) => Submission::redirect(
            $request,
            $url,
            __('Invitation created! Bookmark this page — it is the only way to manage your invitation.')
        ));
    }

    /**
     * Host dashboard, reachable only through the secret manage link.
     */
    public function manage(Invitation $invitation)
    {
        $invitation->load(['kalta', 'guests' => fn ($query) => $query->orderBy('name')]);
        $invitation->guests->each->setRelation('invitation', $invitation); // their messages need it; no query per guest

        return view('invitations.manage', [
            'invitation' => $invitation,
            'guests' => $invitation->guests,
            'stats' => $invitation->stats(),
            'publicUrl' => $invitation->kalta->short_url,
            'clicks' => (int) $invitation->kalta->clicks,
            'manageUrl' => route('invitations.manage', $invitation->manage_token),
        ]);
    }

    public function edit(Invitation $invitation)
    {
        return view('invitations.edit', ['invitation' => $invitation]);
    }

    public function update(UpdateInvitationRequest $request, Invitation $invitation)
    {
        $oldCover = $invitation->cover_image;

        $invitation->update($request->invitationData());

        if ($oldCover !== $invitation->cover_image) {
            $invitation->deleteCoverFile($oldCover);
        }

        $invitation->kalta()->update(['name' => $invitation->coupleNames()]);

        return Submission::redirect($request, route('invitations.manage', $invitation->manage_token), __('Changes saved.'));
    }

    /** Hosts can remove the invitation with everything that belongs to it: the guests' details are personal data. */
    public function destroy(Invitation $invitation)
    {
        $invitation->deleteCoverFile();
        PreviewImage::forget($invitation);
        $invitation->guests()->delete();
        $invitation->kalta()->delete();
        $invitation->delete();

        return redirect(lroute('home'))->with('success', __('The invitation and its guest list were deleted.'));
    }

    public function addGuests(Request $request, Invitation $invitation)
    {
        $request->validate(['guests' => ['required', 'string', 'max:20000']]);

        $result = $invitation->addGuestsFromText($request->input('guests'));

        $parts = array_filter([
            $result['added'] ? __('Added :count guest(s).', ['count' => $result['added']]) : null,
            $result['updated'] ? __('Added contact details to :count guest(s).', ['count' => $result['updated']]) : null,
            $result['skipped'] ? __(':count already on the list.', ['count' => $result['skipped']]) : null,
            $result['full'] ? __('The list is full: :max guests at most.', ['max' => config('kalta.max_guests')]) : null,
        ]);

        $changed = $result['added'] + $result['updated'] > 0;
        $message = $parts ? implode(' ', $parts) : __('No guests found. Write one guest per line.');

        return redirect()->route('invitations.manage', $invitation->manage_token)->with($changed ? 'success' : 'error', $message);
    }

    public function editGuest(Invitation $invitation, InvitationGuest $guest)
    {
        $guest->setRelation('invitation', $invitation);

        return view('invitations.guest-edit', compact('invitation', 'guest'));
    }

    public function updateGuest(UpdateGuestRequest $request, Invitation $invitation, InvitationGuest $guest)
    {
        $guest->update($request->guestData($guest));

        return Submission::redirect(
            $request,
            route('invitations.manage', $invitation->manage_token) . '#guest-' . $guest->id,
            __('Saved.')
        );
    }

    public function destroyGuest(Invitation $invitation, InvitationGuest $guest)
    {
        $guest->delete();

        return redirect()->route('invitations.manage', $invitation->manage_token)->with('success', __('Guest removed.'));
    }

    /**
     * The host passed this guest's link on (Telegram, WhatsApp, copied it...), or starts that guest over with "undo".
     * Starting over also forgets "opened": hosts try the links themselves, and that must not stay on the list as if
     * the guest had seen it. Somebody who has replied keeps their "opened" mark, they obviously did.
     */
    public function markSent(Request $request, Invitation $invitation, InvitationGuest $guest)
    {
        $data = $request->validate([
            'via' => ['nullable', Rule::in(InvitationGuest::CHANNELS)],
            'undo' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('undo')) {
            $guest->update(array_merge(
                ['sent_at' => null, 'sent_via' => null],
                $guest->status === 'pending' ? ['opened_at' => null, 'views' => 0] : []
            ));
        } elseif ($guest->sent_at === null) {
            $guest->update(['sent_at' => now(), 'sent_via' => $data['via'] ?? 'manual']);
        }

        if ($request->expectsJson()) {
            $stage = $guest->stage();

            return response()->json(['stage' => $stage, 'sent' => in_array($stage, ['sent', 'seen'], true), 'via' => $guest->sent_via]);
        }

        return redirect()->to(route('invitations.manage', $invitation->manage_token) . '#guest-' . $guest->id);
    }

    /**
     * Email the invitation to every guest that has an email address.
     * Already-invited guests are skipped unless "resend" is checked.
     */
    public function send(Request $request, Invitation $invitation)
    {
        $guests = $invitation->guests()
            ->whereNotNull('email')
            ->when(!$request->boolean('resend'), fn ($q) => $q->whereNull('sent_at'))
            ->get();

        $sent = 0;
        $failed = [];
        $skipped = 0;
        foreach ($guests as $guest) {
            // A mail server that is down or not set up fails for everyone, and every try can take long: stop early
            if ($sent === 0 && count($failed) >= 3) {
                $skipped++;
                continue;
            }

            try {
                Mail::to($guest->email, $guest->name)
                    ->locale($invitation->locale)
                    ->send(new WeddingInvitationMail($guest));
                $guest->update(['sent_at' => now(), 'sent_via' => 'email']);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Wedding invitation email failed', ['guest' => $guest->id, 'error' => $e->getMessage()]);
                $failed[] = $guest->name;
            }
        }

        if ($sent === 0 && $skipped > 0) {
            $message = __('E-mail could not be sent: the server has no working mail settings. Send the personal links through Telegram or WhatsApp instead.');
        } else {
            $message = __('Sent :count invitation(s) by email.', ['count' => $sent]);
            if ($failed) {
                $message .= ' ' . __('Failed: :names.', ['names' => implode(', ', $failed)]);
            }
        }

        return redirect()->route('invitations.manage', $invitation->manage_token)->with($failed ? 'error' : 'success', $message);
    }

    /** Everybody's name, contact details and reply as a spreadsheet file. */
    public function export(Invitation $invitation)
    {
        $invitation->load(['guests' => fn ($query) => $query->orderBy('name')]);
        $invitation->guests->each->setRelation('invitation', $invitation);

        $separator = app()->getLocale() === 'en' ? ',' : ';'; // what Excel expects in each language's regional settings
        $timezone = config('kalta.timezone');
        $when = fn ($date) => $date ? $date->copy()->setTimezone($timezone)->format('Y-m-d H:i') : '';
        $status = ['attending' => __('Coming'), 'declined' => __('Declined'), 'pending' => __('No reply')];

        $header = [__('Name'), __('Phone'), __('E-mail'), __('Reply'), __('People'), __('Wishes'), __('Sent at'), __('Opened at'), __('Replied at'), __('Personal link')];

        return response()->streamDownload(function () use ($invitation, $header, $separator, $when, $status) {
            echo "\xEF\xBB\xBF"; // UTF-8 marker, so Excel shows Cyrillic and Uzbek letters correctly
            echo self::csvLine($header, $separator);

            foreach ($invitation->guests as $guest) {
                echo self::csvLine([
                    $guest->name,
                    $guest->phone,
                    $guest->email,
                    $status[$guest->status] ?? '',
                    $guest->status === 'attending' ? $guest->party_size : '',
                    $guest->note,
                    $when($guest->sent_at),
                    $when($guest->opened_at),
                    $when($guest->responded_at),
                    $guest->shareLink(),
                ], $separator);
            }
        }, 'guests-' . \Illuminate\Support\Str::slug($invitation->coupleNames()) . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Personal invitation page for one guest.
     */
    public function guest(Request $request, InvitationGuest $guest)
    {
        $guest->invitation->applyLocale();
        $this->countView($request, $guest);

        return view('invitations.show', ['invitation' => $guest->invitation, 'guest' => $guest, 'kalta' => null]);
    }

    /**
     * The picture chat apps show for an invitation link: names, date, time, venue and address beside the cover photo.
     * Public like the general link it belongs to (it shows nothing that link does not).
     */
    public function preview(Kalta $kalta)
    {
        $invitation = $kalta->kaltaable;
        abort_unless($invitation instanceof Invitation && PreviewImage::available(), 404);

        $headers = ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=86400', 'X-Robots-Tag' => 'noindex, nofollow'];

        try {
            return response()->file(Storage::disk('public')->path(PreviewImage::path($invitation)), $headers);
        } catch (\Throwable $e) {
            // e.g. the storage folder is not writable: still hand out the picture, just without keeping it
            Log::warning('Invitation preview could not be stored', ['invitation' => $invitation->id, 'error' => $e->getMessage()]);

            return response(PreviewImage::render($invitation), 200, array_merge($headers, ['Cache-Control' => 'public, max-age=600']));
        }
    }

    /** "Add to calendar" file from a guest's personal page. */
    public function calendar(InvitationGuest $guest)
    {
        $guest->invitation->applyLocale();

        return Ics::response($guest->invitation, $guest->link());
    }

    public function rsvp(Request $request, InvitationGuest $guest)
    {
        $guest->invitation->applyLocale();

        abort_unless($guest->invitation->rsvpOpen(), 403, __('RSVP is closed for this invitation.'));

        $data = $request->validate([
            'status' => ['required', Rule::in(['attending', 'declined'])],
            'party_size' => ['required_if:status,attending', 'nullable', 'integer', 'min:1', 'max:10'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $guest->update([
            'status' => $data['status'],
            'party_size' => $data['status'] === 'attending' ? $data['party_size'] : 0,
            'note' => $data['note'] ?? null,
            'responded_at' => now(),
        ]);

        if ($hostEmail = $guest->invitation->host_email) {
            try {
                Mail::to($hostEmail)->send(new RsvpReceivedMail($guest));
            } catch (\Throwable $e) {
                Log::warning('RSVP notification failed', ['guest' => $guest->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('success', $data['status'] === 'attending'
            ? __('Thank you! We look forward to celebrating with you.')
            : __('Thank you for letting us know. You will be missed!'));
    }

    /** The host sees who has opened their link (see Visitors for who does not count). */
    private function countView(Request $request, InvitationGuest $guest): void
    {
        if (! Visitors::isHuman($request)) {
            return;
        }

        InvitationGuest::whereKey($guest->getKey())->update([
            'views' => DB::raw('CASE WHEN views < 65535 THEN views + 1 ELSE views END'),
            'opened_at' => DB::raw("COALESCE(opened_at, '" . now()->toDateTimeString() . "')"),
        ]);
    }

    /** One CSV line. Text that a spreadsheet would run as a formula (guests type their own wishes) is defused. */
    private static function csvLine(array $cells, string $separator): string
    {
        $cells = array_map(function ($cell) use ($separator) {
            $cell = (string) $cell;

            if ($cell !== '' && preg_match('/^[=+\-@\t\r]/', $cell) && ! GuestList::isPhone($cell)) {
                $cell = "'" . $cell;
            }

            return preg_match('/["\r\n' . preg_quote($separator, '/') . ']/', $cell) ? '"' . str_replace('"', '""', $cell) . '"' : $cell;
        }, $cells);

        return implode($separator, $cells) . "\r\n";
    }
}
