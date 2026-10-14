@php
    /** @var \App\Models\User $me */
    $me = filament()->auth()->user();
    $events = \App\Filament\Support\ActivityEvents::class;
    $recent = \App\Models\ActivityLog::where('user_id', $me->id)->latest('created_at')->limit(5)->get();
    $sessions = \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $me->id)->count();
    $facts = [
        ['Member since', $me->created_at?->format('j M Y') ?? '—'],
        ['Last sign-in', $me->last_login_at?->diffForHumans() ?? 'This session'],
        ['Password changed', $me->password_changed_at?->diffForHumans() ?? 'Never'],
        ['Browser sessions', $sessions ?: 1],
    ];
@endphp

<div class="handi-profile">
    <div class="handi-profile-card">
        <div class="handi-profile-cover"></div>
        <img class="handi-profile-avatar" src="{{ \App\Filament\Support\Avatar::for($me->name) }}" alt="">
        <div class="handi-profile-name">{{ $me->name }}</div>
        <div class="handi-profile-email">{{ $me->email }}</div>
        <div class="handi-pills" style="justify-content:center">
            <span class="handi-pill amber">Super admin</span>
            <span class="handi-pill green"><span class="dot"></span>Signed in</span>
        </div>
        <dl class="handi-profile-facts">
            @foreach ($facts as [$label, $value])
                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    </div>

    <div class="handi-profile-card handi-profile-activity">
        <div class="handi-profile-activity-head">
            <span>Your recent activity</span>
            <a href="{{ \App\Filament\Resources\ActivityLogs\ActivityLogResource::getUrl() }}" wire:navigate>View all</a>
        </div>
        @forelse ($recent as $log)
            <div class="handi-profile-activity-row">
                <span class="handi-profile-activity-dot"></span>
                <div class="min-w-0">
                    <div class="handi-profile-activity-title">{{ $events::label($log->event) }}</div>
                    <div class="handi-profile-activity-time">{{ $log->created_at?->diffForHumans() }}</div>
                </div>
            </div>
        @empty
            <div class="handi-profile-activity-empty">Nothing yet — your changes in the admin will show up here.</div>
        @endforelse
    </div>
</div>
