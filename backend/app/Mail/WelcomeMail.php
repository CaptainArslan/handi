<?php

namespace App\Mail;

use App\Http\Controllers\Api\V1\AuthController;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent once, right after sign-up. */
class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to Handi, '.$this->user->firstName());
    }

    public function content(): Content
    {
        $kitchen = $this->user->kitchen;

        return new Content(
            view: 'mail.handi.welcome',
            text: 'mail.handi.welcome-text',
            with: [
                'name' => $this->user->firstName(),
                'email' => $this->user->email,
                'dishes' => $this->user->kitchenDishes()->count(),
                'city' => $kitchen?->cityName(),
                'time' => AuthController::time12($kitchen?->notify_time ?? '09:00'),
                'link' => config('handi.app_links.today'),
            ],
        );
    }
}
