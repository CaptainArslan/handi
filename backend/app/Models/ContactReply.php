<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reply sent from the admin panel to the person who wrote a contact message.
 *
 * @property int $id
 * @property int $contact_message_id
 * @property int|null $user_id
 * @property string $subject
 * @property string $body
 * @property Carbon|null $mailed_at
 * @property string|null $mail_error
 * @property Carbon $created_at
 */
class ContactReply extends Model
{
    protected $fillable = ['contact_message_id', 'user_id', 'subject', 'body', 'mailed_at', 'mail_error'];

    protected function casts(): array
    {
        return ['mailed_at' => 'datetime'];
    }

    public function contactMessage(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class);
    }

    /** The admin who wrote it. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
