<?php

namespace App\Mail;

use App\Models\InvitationGuest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeddingInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public InvitationGuest $guest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You are invited: the wedding of :couple', ['couple' => $this->guest->invitation->coupleNames()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
            with: ['invitation' => $this->guest->invitation, 'guest' => $this->guest],
        );
    }
}
