<x-filament-widgets::widget>
    <div class="handi-hero">
        <div class="handi-hero-eyebrow">{{ $date }}</div>
        <div class="handi-hero-title">{{ $greeting }}, {{ $name }}</div>
        <div class="handi-hero-sub">
            {{ $newToday }} new {{ \Illuminate\Support\Str::plural('user', $newToday) }} and
            {{ $mealsToday }} {{ \Illuminate\Support\Str::plural('meal', $mealsToday) }} logged today. Here’s how What to Cook is doing.
        </div>
        <div class="handi-hero-actions">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="handi-chip-link {{ ($link['primary'] ?? false) ? 'is-primary' : '' }}" @if (str_contains($link['url'], '/docs/')) target="_blank" @endif>
                    <x-filament::icon :icon="$link['icon']" />
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
