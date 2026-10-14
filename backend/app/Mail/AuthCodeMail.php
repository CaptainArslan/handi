<?php

namespace App\Mail;

use App\Models\AuthCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuthCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $purpose, public string $name = '') {}

    public function envelope(): Envelope
    {
        $app = config('handi.app_name');

        return new Envelope(subject: $this->purpose === AuthCode::EMAIL_CHANGE
            ? "Confirm your new email for {$app}"
            : "Your {$app} password reset code");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.auth-code', with: [
            'code' => $this->code,
            'purpose' => $this->purpose,
            'name' => $this->name,
            'minutes' => (int) config('handi.auth.code_ttl_minutes', 15),
        ]);
    }
}
