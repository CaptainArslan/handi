<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Goodbye email; counts are taken before the account is removed. */
class AccountDeletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $email, public int $dishes, public int $meals, public int $photos) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('handi.app_name').' account is deleted');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.handi.account-deleted',
            text: 'mail.handi.account-deleted-text',
            with: ['email' => $this->email, 'dishes' => $this->dishes, 'meals' => $this->meals, 'photos' => $this->photos],
        );
    }
}
