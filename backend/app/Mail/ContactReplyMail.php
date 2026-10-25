<?php

namespace App\Mail;

use App\Models\ContactReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The team's answer to a "Contact us" message, with the original message quoted below. */
class ContactReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactReply $reply) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reply->subject,
            replyTo: [new Address((string) config('handi.contact.reply_to'), 'Handi team')],
        );
    }

    public function content(): Content
    {
        $contact = $this->reply->contactMessage;

        return new Content(
            view: 'mail.handi.contact-reply',
            text: 'mail.handi.contact-reply-text',
            with: [
                'reply' => $this->reply,
                'contact' => $contact,
                'firstName' => strtok($contact->name, ' ') ?: $contact->name,
                'sentAt' => $contact->created_at->timezone(config('handi.timezone'))->format('j M Y, g:i A'),
            ],
        );
    }
}
