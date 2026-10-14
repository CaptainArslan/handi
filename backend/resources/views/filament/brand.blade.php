{{-- Handi Admin brand: the animated clay-pot logo (looping, muted). Full logo on the sign-in pages. --}}
@php($full = request()->routeIs('filament.admin.auth.*'))
@if ($full)
    <div class="handi-brand-full">
        <video class="handi-logo-video" autoplay muted loop playsinline preload="auto"
               poster="{{ asset('images/brand/handi-logo.png') }}" aria-label="Handi — Aaj kya pakaen?">
            <source src="{{ asset('images/brand/handi-logo.webm') }}" type="video/webm">
            <source src="{{ asset('images/brand/handi-logo.mp4') }}" type="video/mp4">
        </video>
    </div>
@else
    <div class="handi-brand">
        <span class="handi-brand-mark">
            <video class="handi-logo-video" autoplay muted loop playsinline preload="auto"
                   poster="{{ asset('images/brand/handi-mark.png') }}" aria-hidden="true">
                <source src="{{ asset('images/brand/handi-mark.webm') }}" type="video/webm">
                <source src="{{ asset('images/brand/handi-mark.mp4') }}" type="video/mp4">
            </video>
        </span>
        <span class="handi-brand-text">
            <span class="handi-brand-name">Handi</span>
            <span class="handi-brand-sub">Aaj kya pakaen?</span>
        </span>
    </div>
@endif
