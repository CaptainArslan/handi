<?php

namespace App\Services;

use App\Enums\ContactStatus;
use App\Mail\ContactMessageMail;
use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Models\User;
use App\Support\Activity;
use App\Support\Mailer;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/** Saves a "Contact us" message (app or website) and emails it to the team. */
class ContactService
{
    public const THANKS = 'Thanks! Your message was sent. We usually reply within one working day.';

    /** Messages one person (account, or IP when signed out) can send per hour. */
    public const MAX_PER_HOUR = 5;

    /**
     * @param  array<string, mixed>  $data  validated ContactRequest data
     * @return ContactMessage|null null when the honeypot caught a bot (nothing saved)
     */
    public function submit(array $data, ?UploadedFile $file, ?User $user, Request $request): ?ContactMessage
    {
        if (filled($data['website'] ?? null)) {
            return null;
        }

        // Only messages that were actually sent count, so fixing a typo never uses up the limit.
        $key = 'contact-sent:'.($user?->getKey() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            throw new ThrottleRequestsException('Too many messages. Please try again later.', null, ['Retry-After' => RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 3600);

        $path = $file?->store('contact/'.now()->format('Y/m'), ContactMessage::DISK);

        $contact = ContactMessage::create([
            'user_id' => $user?->getKey(),
            'name' => Str::squish($data['name']),
            'email' => Str::lower(trim($data['email'])),
            'topic' => $data['topic'],
            'message' => trim($data['message']),
            'attachment_path' => $path ?: null,
            'attachment_name' => $file ? Str::limit(basename($file->getClientOriginalName()), 120, '') : null,
            'attachment_size' => $file?->getSize(),
            'device' => $data['device'] ?? Mailer::device($request),
            'app_version' => $data['app_version'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        try {
            Mail::to(config('handi.contact.to'))->send(new ContactMessageMail($contact));
            $contact->forceFill(['mailed_at' => now()])->save();
        } catch (Throwable $e) {
            // Still saved: the team sees it in the admin panel (Contact messages).
            Log::warning('Contact email not sent', ['contact_id' => $contact->id, 'error' => $e->getMessage()]);
        }

        if ($user) {
            Activity::log($user, 'contact.sent', 'Sent a message: '.$contact->topicLabel(), $contact);
        }

        return $contact;
    }

    /**
     * Email the team's reply to the sender, keep it on the message, then (optionally) move the status.
     * A mail failure is recorded on the reply (mail_error) and the status is left alone.
     */
    public function reply(ContactMessage $contact, ?User $admin, string $subject, string $body, ?ContactStatus $then = null): ContactReply
    {
        $reply = $contact->replies()->create([
            'user_id' => $admin?->getKey(),
            'subject' => trim($subject),
            'body' => trim($body),
        ]);

        try {
            Mail::to($contact->email, $contact->name)->send(new ContactReplyMail($reply));
            $reply->forceFill(['mailed_at' => now()])->save();
        } catch (Throwable $e) {
            Log::warning('Contact reply not sent', ['contact_id' => $contact->id, 'reply_id' => $reply->id, 'error' => $e->getMessage()]);
            $reply->forceFill(['mail_error' => Str::limit($e->getMessage(), 250, '')])->save();

            return $reply;
        }

        $from = $contact->status;
        if ($then) {
            $contact->moveTo($then, $admin);
        } else {
            $contact->forceFill(['read_at' => $contact->read_at ?? now()])->save();
        }

        Activity::log($admin, 'admin.contact_replied', "Replied to contact message #{$contact->id}".($then && $then !== $from ? " ({$from->getLabel()} → {$then->getLabel()})" : ''), $contact);

        return $reply;
    }
}
