<?php

namespace App\Filament\Support;

/** Human labels, colours and icons for activity_logs.event codes. */
class ActivityEvents
{
    /** @var array<string, array{0: string, 1: string, 2: string}> event => [label, color, icon] */
    private const MAP = [
        'auth.registered' => ['Signed up', 'success', 'heroicon-m-sparkles'],
        'auth.login' => ['Logged in', 'info', 'heroicon-m-arrow-right-end-on-rectangle'],
        'auth.logout' => ['Logged out', 'gray', 'heroicon-m-arrow-left-start-on-rectangle'],
        'auth.login_failed' => ['Failed login', 'danger', 'heroicon-m-shield-exclamation'],
        'auth.reset_requested' => ['Reset requested', 'warning', 'heroicon-m-key'],
        'auth.password_reset' => ['Password reset', 'warning', 'heroicon-m-key'],
        'account.profile_updated' => ['Profile updated', 'gray', 'heroicon-m-user-circle'],
        'account.email_change_requested' => ['Email change asked', 'warning', 'heroicon-m-envelope'],
        'account.email_changed' => ['Email changed', 'warning', 'heroicon-m-envelope'],
        'account.password_changed' => ['Password changed', 'warning', 'heroicon-m-lock-closed'],
        'account.deleted' => ['Account deleted', 'danger', 'heroicon-m-trash'],
        'contact.sent' => ['Contacted us', 'info', 'heroicon-m-chat-bubble-left-ellipsis'],
        'kitchen.updated' => ['Kitchen settings', 'gray', 'heroicon-m-adjustments-horizontal'],
        'kitchen.location_changed' => ['Location changed', 'gray', 'heroicon-m-map-pin'],
        'notifications.settings_updated' => ['Reminder settings', 'gray', 'heroicon-m-bell'],
        'today.planned' => ['Planned a dish', 'primary', 'heroicon-m-calendar-days'],
        'today.shuffled' => ['Shuffled picks', 'primary', 'heroicon-m-arrow-path'],
        'meal.logged' => ['Meal logged', 'success', 'heroicon-m-check-circle'],
        'meal.updated' => ['Meal edited', 'success', 'heroicon-m-pencil-square'],
        'meal.deleted' => ['Meal deleted', 'gray', 'heroicon-m-minus-circle'],
        'sync.completed' => ['Offline sync', 'info', 'heroicon-m-cloud-arrow-up'],
        'dish.added' => ['Dishes added', 'primary', 'heroicon-m-plus-circle'],
        'dish.custom_created' => ['Custom dish added', 'primary', 'heroicon-m-plus-circle'],
        'dish.custom_updated' => ['Custom dish edited', 'gray', 'heroicon-m-pencil-square'],
        'dish.updated' => ['Dish tags changed', 'gray', 'heroicon-m-tag'],
        'dish.reset' => ['Dish tags reset', 'gray', 'heroicon-m-arrow-uturn-left'],
        'dish.photo_added' => ['Photo added', 'primary', 'heroicon-m-camera'],
        'dish.removed' => ['Dish removed', 'gray', 'heroicon-m-x-circle'],
        'dish.restored' => ['Dish restored', 'gray', 'heroicon-m-arrow-uturn-left'],
        'admin.user_updated' => ['Admin edited user', 'warning', 'heroicon-m-shield-check'],
        'admin.sessions_revoked' => ['Admin logged user out', 'warning', 'heroicon-m-shield-check'],
        'admin.user_deleted' => ['Admin deleted user', 'danger', 'heroicon-m-shield-exclamation'],
        'admin.dishes_imported' => ['Dishes imported', 'warning', 'heroicon-m-arrow-up-tray'],
        'admin.contact_status' => ['Contact message status', 'gray', 'heroicon-m-chat-bubble-left-ellipsis'],
        'admin.contact_replied' => ['Replied to contact message', 'success', 'heroicon-m-paper-airplane'],
    ];

    public static function label(?string $event): string
    {
        return self::MAP[$event][0] ?? ucfirst(str_replace(['.', '_'], ' ', (string) $event));
    }

    public static function color(?string $event): string
    {
        return self::MAP[$event][1] ?? 'gray';
    }

    public static function icon(?string $event): string
    {
        return self::MAP[$event][2] ?? 'heroicon-m-bolt';
    }

    /** @return array<string, string> for select filters */
    public static function options(): array
    {
        return collect(self::MAP)->map(fn ($v) => $v[0])->sort()->all();
    }
}
