<?php

namespace App\Mail;

use App\Models\InvitationGuest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RsvpReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public InvitationGuest $guest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('RSVP: :name — :status', ['name' => $this->guest->name, 'status' => $this->guest->status === 'attending' ? __('coming') : __('declined')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rsvp-received',
            with: ['invitation' => $this->guest->invitation, 'guest' => $this->guest],
        );
    }
}
