<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Users\UserResource;
use App\Models\Dish;
use App\Models\KitchenDish;
use App\Models\MealLog;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $users = User::where('is_admin', false);
        $total = (clone $users)->count();
        $newWeek = (clone $users)->where('created_at', '>=', now()->subDays(7))->count();
        $activeToday = (clone $users)->where('last_active_at', '>=', now()->subDay())->count();
        $activeWeek = (clone $users)->where('last_active_at', '>=', now()->subDays(7))->count();

        $mealsToday = MealLog::where('logged_at', '>=', now()->startOfDay())->count();
        $mealsMonth = MealLog::where('logged_at', '>=', now()->startOfMonth())->count();
        $fromPicks = MealLog::where('logged_at', '>=', now()->startOfMonth())->where('source', 'suggestion')->count();

        return [
            Stat::make('App users', $total)
                ->icon('heroicon-o-users')
                ->description("+{$newWeek} this week")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($newWeek ? 'success' : 'gray')
                ->chart($this->daily('users', 'created_at', fn ($q) => $q->where('is_admin', false)))
                ->url(UserResource::getUrl('index')),
            Stat::make('Active today', $activeToday)
                ->icon('heroicon-o-device-phone-mobile')
                ->description("{$activeWeek} in the last 7 days")
                ->descriptionIcon('heroicon-m-device-phone-mobile'),
            Stat::make('Meals logged today', $mealsToday)
                ->icon('heroicon-o-check-badge')
                ->description("{$mealsMonth} this month · ".($mealsMonth ? round($fromPicks / $mealsMonth * 100) : 0).'% from picks')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('primary')
                ->chart($this->daily('meal_logs', 'logged_at')),
            Stat::make('Library dishes', Dish::where('is_active', true)->count())
                ->icon('heroicon-o-book-open')
                ->description(KitchenDish::where('is_custom', true)->whereNull('removed_at')->count().' custom dishes by users')
                ->descriptionIcon('heroicon-m-cake'),
        ];
    }

    /** @return array<int, int> last 7 days, oldest first */
    private function daily(string $table, string $column, ?callable $scope = null): array
    {
        $q = DB::table($table)->where($column, '>=', now()->subDays(6)->startOfDay());
        if ($scope) {
            $scope($q);
        }
        $counts = $q->selectRaw("DATE({$column}) as d, COUNT(*) as n")->groupBy('d')->pluck('n', 'd');

        return collect(range(6, 0))->map(fn ($ago) => (int) ($counts[now()->subDays($ago)->toDateString()] ?? 0))->all();
    }
}
