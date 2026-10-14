<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $fillable = ['user_id', 'personal_access_token_id', 'device_id', 'name', 'expo_push_token', 'platform', 'last_seen_at'];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
