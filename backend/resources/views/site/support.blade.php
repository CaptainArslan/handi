@extends('site.layout')
@section('title', 'Handi support — help with your account and the app')
@section('description', 'Get help with Handi: signing in, passwords, reminders, your dishes and deleting your account. Contact the Handi team by email.')

@php($support = config('handi.support_email'))

@section('content')
    <article class="wrap doc">
        <span class="eyebrow">Support</span>
        <h1>How can we help?</h1>
        <p class="summary"><a href="{{ route('site.contact') }}">Send us a message</a> (you can attach a screenshot) or email <a href="mailto:{{ $support }}?subject=Handi%20help">{{ $support }}</a>, and a person replies, usually within one working day. Please send it from the address you signed up with and tell us your phone model or browser.</p>

        <section>
            <h2>Signing in</h2>
            <div class="help-grid">
                <a class="help" href="{{ url('/app/login') }}"><b>Log in</b><span>With the email and password you signed up with, on any phone or browser.</span></a>
                <a class="help" href="{{ url('/app/forgot-password') }}"><b>Forgot password</b><span>We email a 6-digit code so you can choose a new password.</span></a>
                <a class="help" href="{{ url('/app/welcome') }}"><b>Create an account</b><span>Set up your kitchen in about 2 minutes, then save it.</span></a>
            </div>
        </section>

        <section>
            <h2>Common questions</h2>
            <div class="faq">
                <details><summary>I didn’t get the email code</summary><p>Check spam or promotions, and wait a minute before asking for a new code. Codes expire after {{ config('handi.auth.code_ttl_minutes', 15) }} minutes. Still nothing? Email us.</p></details>
                <details><summary>The morning reminder doesn’t arrive</summary><p>In Settings → Daily reminder, make sure it’s on and the time is right, and allow notifications for Handi in your phone’s settings. On Android, turn off battery saving for Handi.</p></details>
                <details><summary>My picks don’t match the weather</summary><p>Check Settings → Location. If you shared your location, the weather is checked for the nearest city each morning.</p></details>
                <details><summary>How do I change my email or password?</summary><p>Tap your name at the top of Settings to change your name or email (a code is sent to the new address first). Change password is under Settings → Account.</p></details>
                <details><summary>Can I use one account on my phone and computer?</summary><p>Yes. Log in with the same email everywhere and your kitchen, dishes and history stay in sync.</p></details>
            </div>
        </section>

        <section>
            <h2>Your data</h2>
            <p>Read the <a href="{{ route('site.privacy') }}">privacy policy</a> and <a href="{{ route('site.terms') }}">terms of use</a>, or <a href="{{ route('site.delete-account') }}">delete your account</a>.</p>
        </section>

        <section>
            <h2>About the app</h2>
            <p class="muted">Handi · {{ config('handi.app_name') }} · free, no ads, no in-app purchases. Available on the web, with Android and iPhone apps coming to the stores.</p>
        </section>
    </article>
@endsection
