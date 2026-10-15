{{-- Public website layout. Same palette and type as the app. No JavaScript (animations are CSS); CSS is inlined so the first paint needs one request. --}}
@php
    $title = trim($__env->yieldContent('title')) ?: 'Handi — Aaj kya pakaen? 3 dishes every morning';
    $description = trim($__env->yieldContent('description')) ?: 'Handi suggests three dishes your family already cooks every morning, matched to today’s weather and what you haven’t had lately. Free on the web, Android and iPhone.';
    $canonical = url()->current();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta name="theme-color" content="#FBF7F1">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="index, follow">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Handi">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ asset('images/site/og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/site/icon-192.png') }}" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <link rel="preload" href="{{ asset('fonts/site/poppins-700.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/site/poppins-400.woff2') }}" as="font" type="font/woff2" crossorigin>
    <style>{!! \App\Support\SiteAssets::css() !!}</style>
    @stack('head')
</head>
<body>
    <a class="skip" href="#main">Skip to content</a>

    <header class="site-header">
        <nav class="wrap nav" aria-label="Main">
            <a class="brand" href="{{ route('site.home') }}" aria-label="Handi home">
                <img src="{{ asset('images/site/handi-mark-64.webp') }}" srcset="{{ asset('images/site/handi-mark-64.webp') }} 1x, {{ asset('images/site/handi-mark-128.webp') }} 2x" width="30" height="45" alt="">
                <b>Handi</b>
            </a>
            <ul class="nav-links">
                <li><a href="{{ route('site.home') }}#how">How it works</a></li>
                <li><a href="{{ route('site.home') }}#setup">Set up</a></li>
                <li><a href="{{ route('site.home') }}#dishes">Dishes</a></li>
                <li><a href="{{ route('site.home') }}#faq">FAQ</a></li>
            </ul>
            <a class="nav-login" href="{{ url('/app/login') }}">Log in</a>
            <a class="btn btn-primary" href="{{ url('/app/welcome') }}">Get started</a>
            <details class="menu">
                <summary aria-label="Open menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </summary>
                <div class="menu-panel">
                    <a href="{{ route('site.home') }}#how">How it works</a>
                    <a href="{{ route('site.home') }}#setup">Set up</a>
                    <a href="{{ route('site.home') }}#dishes">Dishes</a>
                    <a href="{{ route('site.home') }}#faq">FAQ</a>
                    <a href="{{ route('site.home') }}#download">Get the app</a>
                    <a href="{{ url('/app/login') }}">Log in</a>
                    <a class="menu-cta" href="{{ url('/app/welcome') }}">Get started — it’s free</a>
                </div>
            </details>
        </nav>
    </header>

    <main id="main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="wrap">
            <div class="foot">
                <div>
                    <a class="brand" href="{{ route('site.home') }}" aria-label="Handi home">
                        <img src="{{ asset('images/site/handi-mark-64.webp') }}" srcset="{{ asset('images/site/handi-mark-64.webp') }} 1x, {{ asset('images/site/handi-mark-128.webp') }} 2x" width="30" height="45" alt="" loading="lazy">
                        <b>Handi</b>
                    </a>
                    <p class="foot-about">Three dishes every morning, from your own kitchen. Made in Pakistan for home cooks.</p>
                    @include('site.parts.stores', ['tone' => 'light'])
                </div>
                <div>
                    <h3>Product</h3>
                    <ul>
                        <li><a href="{{ route('site.home') }}#how">How it works</a></li>
                        <li><a href="{{ route('site.home') }}#setup">Set up</a></li>
                        <li><a href="{{ route('site.home') }}#faq">FAQ</a></li>
                        <li><a href="{{ url('/app/welcome') }}">Use Handi on the web</a></li>
                        <li><a href="{{ url('/app/login') }}">Log in</a></li>
                    </ul>
                </div>
                <div>
                    <h3>Help</h3>
                    <ul>
                        <li><a href="{{ route('site.support') }}">Support</a></li>
                        <li><a href="{{ route('site.privacy') }}">Privacy policy</a></li>
                        <li><a href="{{ route('site.terms') }}">Terms of use</a></li>
                        <li><a href="{{ route('site.delete-account') }}">Delete your account</a></li>
                        <li><a href="mailto:{{ config('handi.support_email') }}">{{ config('handi.support_email') }}</a></li>
                    </ul>
                </div>
            </div>
            <div class="foot-bottom">
                <span>© {{ date('Y') }} Handi · {{ config('handi.app_name') }}</span>
                <span lang="ur">Aaj kya pakaen?</span>
            </div>
        </div>
    </footer>
</body>
</html>
