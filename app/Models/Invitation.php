<?php

namespace App\Models;

use App\Support\GuestList;
use App\Support\PreviewImage;
use App\Traits\Kaltaable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasFactory, Kaltaable;

    protected $guarded = [];

    protected $hidden = ['manage_token'];

    private ?string $shareVersion = null;

    protected $casts = [
        'event_at' => 'datetime',
        'rsvp_deadline' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation) {
            $invitation->manage_token ??= Str::random(40);
        });
    }

    public function guests()
    {
        return $this->hasMany(InvitationGuest::class);
    }

    /** Render pages and emails in the language the hosts wrote the invitation in. */
    public function applyLocale(): void
    {
        $locale = array_key_exists($this->locale, config('kalta.locales')) ? $this->locale : config('kalta.default_locale');

        app()->setLocale($locale);
        \Carbon\Carbon::setLocale(config("kalta.carbon_locales.$locale", $locale));
    }

    public function coupleNames(): string
    {
        return "{$this->groom_name} & {$this->bride_name}";
    }

    /** Hosts type the time as local wall-clock time; it is stored as UTC. */
    public function setEventAtAttribute($value): void
    {
        $this->attributes['event_at'] = Carbon::parse($value, config('kalta.timezone'))->utc()->format('Y-m-d H:i:s');
    }

    /** The event time as the hosts and guests see it. */
    public function localEventAt(): Carbon
    {
        return $this->event_at->copy()->setTimezone(config('kalta.timezone'));
    }

    /** "morning", "afternoon", "evening" or "night": a wrong AM/PM is easiest to spot when the time is put in words. */
    public function dayPart(): string
    {
        $hour = (int) $this->localEventAt()->format('G');

        return match (true) {
            $hour < 5, $hour >= 22 => 'night',
            $hour < 12 => 'morning',
            $hour < 17 => 'afternoon',
            default => 'evening',
        };
    }

    public function rsvpOpen(): bool
    {
        $deadlineOpen = !$this->rsvp_deadline
            || Carbon::parse($this->rsvp_deadline->toDateString(), config('kalta.timezone'))->endOfDay()->isFuture();

        return $this->event_at->isFuture() && $deadlineOpen;
    }

    /** "Add to Google Calendar" link (4 hours from the start). */
    public function calendarUrl(): string
    {
        $start = $this->event_at->copy()->utc();

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => __('Wedding of :couple', ['couple' => $this->coupleNames()]),
            'dates' => $start->format('Ymd\THis\Z') . '/' . $start->copy()->addHours(4)->format('Ymd\THis\Z'),
            'location' => trim($this->venue . ', ' . $this->address, ', '),
            'details' => (string) $this->message,
        ]);
    }

    /**
     * Add guests from a pasted list, one per line: "Name, e-mail or phone". People already on the list are not
     * added twice. See GuestList for the counts that come back.
     *
     * @return array{added: int, updated: int, skipped: int, full: int}
     */
    public function addGuestsFromText(?string $text): array
    {
        return GuestList::import($this, $text);
    }

    /** Whole days from today (hosts' time zone) to the wedding: 0 is today, negative is past. */
    public function daysUntil(): int
    {
        $today = now(config('kalta.timezone'))->startOfDay();

        return (int) $today->diffInDays($this->localEventAt()->startOfDay(), false);
    }

    /**
     * Numbers for the host dashboard. "Sent" means the host gave the guest their link; anyone who opened it
     * or replied obviously has it, so the stages only ever move forward.
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        $guests = $this->guests;
        $stages = $guests->map->stage();
        $attending = $guests->where('status', 'attending');

        return [
            'total' => $guests->count(),
            'sent' => $stages->reject(fn ($stage) => $stage === 'unsent')->count(),
            'seen' => $stages->filter(fn ($stage) => in_array($stage, ['seen', 'coming', 'declined'], true))->count(),
            'unsent' => $stages->filter(fn ($stage) => $stage === 'unsent')->count(),
            'waiting' => $stages->filter(fn ($stage) => in_array($stage, ['sent', 'seen'], true))->count(),
            'attending' => $attending->count(),
            'people' => (int) $attending->sum('party_size'),
            'declined' => $guests->where('status', 'declined')->count(),
            'pending' => $guests->where('status', 'pending')->count(),
            'replied' => $guests->where('status', '!=', 'pending')->count(),
            'without_phone' => $guests->whereNull('phone')->count(),
            'with_email' => $guests->whereNotNull('email')->count(),
            'emailed' => $guests->whereNotNull('email')->whereNotNull('sent_at')->count(),
        ];
    }

    /**
     * A tag for the addresses the hosts share. Telegram and WhatsApp remember a link's preview card, so after an edit
     * the very same address would keep showing the old card; an address with a new tag is fetched afresh. The tag
     * changes exactly when the preview picture does, and the page ignores it, so links sent earlier keep working.
     */
    public function shareVersion(): string
    {
        return $this->shareVersion ??= PreviewImage::version($this);
    }

    /** The general link in the form to share (see shareVersion). */
    public function shareUrl(): string
    {
        return $this->kalta->short_url . '?v=' . $this->shareVersion();
    }

    /** Delete the uploaded cover photo, if this invitation has one of ours. */
    public function deleteCoverFile(?string $path = null): void
    {
        $path ??= $this->cover_image;

        if ($path && str_starts_with($path, 'media/')) {
            Storage::disk('public')->delete(substr($path, strlen('media/')));
        }
    }
}
