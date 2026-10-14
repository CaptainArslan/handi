<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/** Writes the activity log that super admins see per user. Never breaks the request. */
class Activity
{
    public static function log(?User $user, string $event, string $description, ?Model $subject = null, array $properties = []): void
    {
        try {
            $request = app()->bound('request') ? request() : null;

            ActivityLog::create([
                'user_id' => $user?->getKey(),
                'event' => $event,
                'description' => $description,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'properties' => $properties ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
