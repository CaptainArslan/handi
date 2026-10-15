<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Security notice after a password change or a completed reset. */
class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $when, public string $device, public bool $signedOut = true) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('handi.app_name').' password was changed');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.handi.password-changed',
            text: 'mail.handi.password-changed-text',
            with: [
                'name' => $this->user->firstName(),
                'email' => $this->user->email,
                'when' => $this->when,
                'device' => $this->device,
                'signedOut' => $this->signedOut,
                'link' => config('handi.app_links.reset_password'),
            ],
        );
    }
}
