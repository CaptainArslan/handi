<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\AppNotification;
use App\Models\User;

/** Creates notification-center items and (optionally) pushes them to the user's phones. */
class NotificationService
{
    public function __construct(private readonly ExpoPushService $push) {}

    public function notify(
        User $user,
        NotificationType $type,
        string $title,
        string $body,
        ?string $ctaLabel = null,
        ?string $deepLink = null,
        ?string $imageKey = null,
        bool $push = false,
        ?string $pushTitle = null,
    ): AppNotification {
        $item = $user->appNotifications()->create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'cta_label' => $ctaLabel,
            'deep_link' => $deepLink,
            'image_key' => $imageKey,
        ]);

        if ($push) {
            $this->push->send($user->devices()->get(), $pushTitle ?? $title, $body, ['link' => $deepLink, 'notification_id' => $item->id]);
        }

        return $item;
    }
}
