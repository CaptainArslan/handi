<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Dishes\DishResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\MealLog;
use App\Models\User;
use Filament\Widgets\Widget;

/** Green greeting card at the top of the overview with today's headline and shortcuts. */
class WelcomeBanner extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.welcome-banner';

    protected function getViewData(): array
    {
        $hour = now(config('handi.timezone'))->hour;

        return [
            'greeting' => $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening'),
            'name' => str(auth()->user()?->name ?? 'Admin')->before(' '),
            'date' => now(config('handi.timezone'))->format('l, j F Y'),
            'newToday' => User::where('is_admin', false)->where('created_at', '>=', now()->startOfDay())->count(),
            'mealsToday' => MealLog::where('logged_at', '>=', now()->startOfDay())->count(),
            'links' => [
                ['label' => 'Users', 'url' => UserResource::getUrl('index'), 'icon' => 'heroicon-m-users', 'primary' => true],
                ['label' => 'Activity log', 'url' => ActivityLogResource::getUrl('index'), 'icon' => 'heroicon-m-clipboard-document-list'],
                ['label' => 'Add library dish', 'url' => DishResource::getUrl('create'), 'icon' => 'heroicon-m-plus'],
                ['label' => 'API docs', 'url' => url('/docs/api'), 'icon' => 'heroicon-m-code-bracket'],
            ],
        ];
    }
}
