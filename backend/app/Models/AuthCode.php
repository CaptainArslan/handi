<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A 6-digit code sent by email (password reset or email change). */
class AuthCode extends Model
{
    public const PASSWORD_RESET = 'password_reset';

    public const EMAIL_CHANGE = 'email_change';

    protected $fillable = [
        'purpose', 'email', 'user_id', 'code_hash', 'attempts_left', 'expires_at',
        'last_sent_at', 'reset_token_hash', 'verified_at', 'consumed_at',
    ];

    protected $hidden = ['code_hash', 'reset_token_hash'];

    protected function casts(): array
    {
        return [
            'attempts_left' => 'integer',
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
