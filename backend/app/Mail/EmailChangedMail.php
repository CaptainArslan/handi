<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the OLD address once a new sign-in email is confirmed. */
class EmailChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $old, public string $new, public string $when) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('handi.app_name').' sign-in email was changed');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.handi.email-changed',
            text: 'mail.handi.email-changed-text',
            with: ['old' => $this->old, 'new' => $this->new, 'when' => $this->when],
        );
    }
}
