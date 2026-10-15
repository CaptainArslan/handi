<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** An item in the in-app notification center. */
class AppNotification extends Model
{
    use MassPrunable, SoftDeletes;

    protected $fillable = ['user_id', 'type', 'title', 'body', 'cta_label', 'deep_link', 'image_key', 'read_at'];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Notifications older than 90 days are pruned daily (model:prune). */
    public function prunable(): Builder
    {
        return static::withTrashed()->where('created_at', '<', now()->subDays(90));
    }
}
