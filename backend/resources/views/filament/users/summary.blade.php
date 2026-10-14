@php
    /** @var \App\Models\User $user */
    $user = $getRecord();
    $kitchen = $user->kitchen;
    $stats = app(\App\Services\StatsService::class)->forUser($user);
    $dishes = $user->kitchenDishes()->whereNull('removed_at');
    $active = $user->last_active_at;
    $status = match (true) {
        $active?->gt(now()->subDay()) => ['Active today', 'green'],
        $active?->gt(now()->subDays(7)) => ['Active this week', 'green'],
        $active !== null => ['Last seen '.$active->diffForHumans(), ''],
        default => ['Never opened the app', ''],
    };
    $metrics = [
        ['Dishes', (clone $dishes)->count(), (clone $dishes)->where('is_custom', true)->count().' custom'],
        ['Meals logged', $user->mealLogs()->count(), 'all time'],
        ['This month', $stats['meals_this_month'], 'meals'],
        ['Streak', $stats['streak_days'], $stats['streak_days'] === 1 ? 'day' : 'days'],
        ['From picks', $user->mealLogs()->where('source', 'suggestion')->count(), 'meals'],
        ['Phones', $user->devices()->count(), 'linked'],
    ];
@endphp

<div class="handi-summary">
    <div class="fi-section" style="padding: 1.25rem 1.4rem;">
        <div class="handi-user-card">
            <img src="{{ \App\Filament\Support\Avatar::for($user->name) }}" alt="">
            <div class="min-w-0">
                <div class="handi-user-name">{{ $user->name }}</div>
                <div class="handi-user-meta">{{ $user->email }} · joined {{ $user->created_at?->format('j M Y') }}</div>
                <div class="handi-pills">
                    <span class="handi-pill {{ $status[1] }}"><span class="dot"></span>{{ $status[0] }}</span>
                    @if ($user->is_admin)
                        <span class="handi-pill amber">Super admin</span>
                    @endif
                    @if ($kitchen?->location_label)
                        <span class="handi-pill">📍 {{ $kitchen->location_label }}</span>
                    @endif
                    @if ($kitchen)
                        <span class="handi-pill">Family of {{ $kitchen->family_size }}</span>
                        <span class="handi-pill {{ $kitchen->notifications_enabled ? 'green' : '' }}">
                            Reminder {{ $kitchen->notifications_enabled ? 'at '.date('g:i A', strtotime($kitchen->notify_time)) : 'off' }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="handi-metrics">
        @foreach ($metrics as [$label, $value, $unit])
            <div class="handi-metric">
                <div class="handi-metric-label">{{ $label }}</div>
                <div class="handi-metric-value">{{ $value }} <small>{{ $unit }}</small></div>
            </div>
        @endforeach
    </div>
</div>
