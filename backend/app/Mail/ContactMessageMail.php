<?php

namespace App\Mail;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A "Contact us" message sent to the Handi team. Replying goes straight to the sender. */
class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Handi] New message: {$this->contact->topicLabel()}",
            replyTo: [new Address($this->contact->email, $this->contact->name)],
        );
    }

    public function content(): Content
    {
        $c = $this->contact;
        $when = $c->created_at->timezone(config('handi.timezone'))->format('D j M Y, g:i A T');
        $facts = array_filter([
            'From' => "{$c->name} <{$c->email}>",
            'Topic' => $c->topicLabel(),
            'Account' => $c->user ? "Signed in (user #{$c->user_id})" : 'Not signed in',
            'Sent from' => trim(($c->device ?? 'Unknown').($c->app_version ? " · v{$c->app_version}" : '')),
            'When' => $when,
            'Attachment' => $c->attachment_name ? "{$c->attachment_name} ({$c->attachmentSizeLabel()})" : null,
        ]);

        return new Content(
            view: 'mail.handi.contact-message',
            text: 'mail.handi.contact-message-text',
            with: [
                'contact' => $c,
                'facts' => $facts,
                'when' => $when,
                'adminUrl' => ContactMessageResource::getUrl('view', ['record' => $c], panel: 'admin'),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $c = $this->contact;

        return $c->attachment_path
            ? [Attachment::fromStorageDisk(ContactMessage::DISK, $c->attachment_path)->as($c->attachment_name ?? basename($c->attachment_path))]
            : [];
    }
}
