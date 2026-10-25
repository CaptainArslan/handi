<?php

namespace App\Models;

use App\Enums\ContactStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A "Contact us" message. Attachments live on the private `local` disk (never public).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property string $topic
 * @property string $message
 * @property ContactStatus $status
 * @property string|null $admin_note
 * @property Carbon|null $status_changed_at
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property int|null $attachment_size
 * @property string|null $device
 * @property string|null $app_version
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $mailed_at
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
class ContactMessage extends Model
{
    public const DISK = 'local';

    protected $fillable = [
        'user_id', 'name', 'email', 'topic', 'message', 'attachment_path', 'attachment_name', 'attachment_size',
        'device', 'app_version', 'ip_address', 'user_agent', 'mailed_at', 'read_at',
        'status', 'admin_note', 'status_changed_at', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'mailed_at' => 'datetime',
            'read_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ContactMessage $m) {
            if ($m->attachment_path) {
                Storage::disk(self::DISK)->delete($m->attachment_path);
            }
        });
    }

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Replies the team sent from the admin panel, oldest first. */
    public function replies(): HasMany
    {
        return $this->hasMany(ContactReply::class)->oldest('id');
    }

    /** The admin who marked it resolved. */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** Move to a new status (a non-null $note replaces the team note) and keep resolved_at / resolved_by in step. */
    public function moveTo(ContactStatus $status, ?User $by = null, ?string $note = null): void
    {
        $changed = $this->status !== $status;
        $resolved = $status === ContactStatus::Resolved;

        $this->forceFill([
            'status' => $status,
            'status_changed_at' => $changed ? now() : $this->status_changed_at,
            'resolved_at' => $resolved ? ($this->resolved_at ?? now()) : null,
            'resolved_by' => $resolved ? ($this->resolved_by ?? $by?->getKey()) : null,
            'read_at' => $this->read_at ?? now(),
            'admin_note' => $note !== null ? (trim($note) ?: null) : $this->admin_note,
        ])->save();
    }

    public function topicLabel(): string
    {
        return config('handi.contact.topics')[$this->topic] ?? ucfirst($this->topic);
    }

    /** "1.4 MB" / "320 KB". */
    public function attachmentSizeLabel(): ?string
    {
        if (! $this->attachment_size) {
            return null;
        }

        return $this->attachment_size >= 1048576
            ? round($this->attachment_size / 1048576, 1).' MB'
            : max(1, (int) round($this->attachment_size / 1024)).' KB';
    }
}
