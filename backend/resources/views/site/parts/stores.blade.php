{{-- Store buttons. Each links to the store page once its URL is set in .env; until then it shows "Coming soon". --}}
@php
    $stores = [
        ['url' => config('handi.store_links.android'), 'top' => 'Get it on', 'name' => 'Google Play', 'path' => 'M7 3.5v17l13-8.5z'],
        ['url' => config('handi.store_links.ios'), 'top' => 'Download on the', 'name' => 'App Store', 'path' => 'M8 3h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM11 18h2'],
    ];
    $tone = $tone ?? 'dark';
@endphp
<div class="stores stores-{{ $tone }}">
    @foreach ($stores as $s)
        @if ($s['url'])
            <a class="store" href="{{ $s['url'] }}" rel="noopener">
        @else
            <span class="store is-soon" aria-label="{{ $s['name'] }}, coming soon">
        @endif
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true"><path d="{{ $s['path'] }}"/></svg>
            <span><small>{{ $s['url'] ? $s['top'] : 'Coming soon to' }}</small><b>{{ $s['name'] }}</b></span>
        @if ($s['url'])
            </a>
        @else
            </span>
        @endif
    @endforeach
</div>
