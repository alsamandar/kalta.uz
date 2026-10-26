<?php

namespace App\Models;

use App\Support\GuestList;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InvitationGuest extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'attending', 'declined'];

    /** How the hosts passed the invitation on ("sent_via"). */
    public const CHANNELS = ['email', 'telegram', 'whatsapp', 'share', 'copy', 'link', 'manual'];

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (InvitationGuest $guest) {
            $guest->token ??= Str::random(12);
        });
    }

    public function invitation()
    {
        return $this->belongsTo(Invitation::class);
    }

    public function link(): string
    {
        return route('invitations.guest', $this);
    }

    /** The personal link as the hosts share it: with the tag that makes chat apps fetch a fresh preview (see Invitation::shareVersion). */
    public function shareLink(): string
    {
        return $this->link() . '?v=' . $this->invitation->shareVersion();
    }

    /**
     * Where this guest is on the way from "not told yet" to "replied". Each stage implies the earlier ones:
     * somebody who replied has certainly opened the link, and somebody who opened it was certainly sent it.
     *
     * @return 'unsent'|'sent'|'seen'|'coming'|'declined'
     */
    public function stage(): string
    {
        return match (true) {
            $this->status === 'attending' => 'coming',
            $this->status === 'declined' => 'declined',
            $this->opened_at !== null => 'seen',
            $this->sent_at !== null => 'sent',
            default => 'unsent',
        };
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name), 0, 1)) ?: '•';
    }

    /**
     * The phone number the way WhatsApp wants it (country code, digits only), or null if it is too short to
     * be a real number. Numbers without a country code are taken to be Uzbek ones.
     */
    public function whatsappNumber(): ?string
    {
        if (! $this->phone || ! GuestList::isPhone($this->phone)) {
            return null;
        }

        $digits = GuestList::digits($this->phone);

        if (str_starts_with(trim($this->phone), '+')) {
            return $digits;
        }

        return match (true) {
            strlen($digits) === 9 => '998' . $digits,
            strlen($digits) === 10 && $digits[0] === '0' => '998' . substr($digits, 1),
            str_starts_with($digits, '00') => substr($digits, 2),
            default => $digits,
        };
    }

    /**
     * The message the hosts send, without the link: their own wording if they wrote one, otherwise a short
     * greeting. It is in the invitation's language, whatever language the hosts' screen is in.
     */
    public function shareText(): string
    {
        $template = trim((string) $this->invitation->share_message);

        if ($template !== '') {
            return str_replace('{name}', $this->name, $template);
        }

        return __(':name, you are invited to the wedding of :couple!', [
            'name' => $this->name,
            'couple' => $this->invitation->coupleNames(),
        ], $this->invitation->locale);
    }

    /** Text and personal link together, ready to paste into any chat. */
    public function shareMessage(): string
    {
        return $this->shareText() . "\n" . $this->shareLink();
    }

    /** A softer second message for guests who were sent the invitation but have not replied. */
    public function reminderText(): string
    {
        return __(':name, a gentle reminder: please let us know if you can come to the wedding of :couple.', [
            'name' => $this->name,
            'couple' => $this->invitation->coupleNames(),
        ], $this->invitation->locale);
    }

    public function reminderTelegramUrl(): string
    {
        return 'https://t.me/share/url?' . http_build_query(['url' => $this->shareLink(), 'text' => $this->reminderText()], '', '&', PHP_QUERY_RFC3986);
    }

    public function reminderWhatsappUrl(): string
    {
        return 'https://wa.me/' . $this->whatsappNumber() . '?' . http_build_query(['text' => $this->reminderText() . "\n" . $this->shareLink()], '', '&', PHP_QUERY_RFC3986);
    }

    public function telegramShareUrl(): string
    {
        return 'https://t.me/share/url?' . http_build_query(['url' => $this->shareLink(), 'text' => $this->shareText()], '', '&', PHP_QUERY_RFC3986);
    }

    /** Opens the chat with this guest when we know the number, otherwise lets the host pick the chat. */
    public function whatsappShareUrl(): string
    {
        return 'https://wa.me/' . $this->whatsappNumber() . '?' . http_build_query(['text' => $this->shareMessage()], '', '&', PHP_QUERY_RFC3986);
    }
}
