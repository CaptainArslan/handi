@extends('site.layout')
@section('title', 'Contact Handi — send us a message')
@section('description', 'Questions, problems or ideas about Handi? Send the team a message, attach a screenshot if it helps, and a person replies by email, usually within one working day.')

@php
    $support = config('handi.support_email');
    $topics = config('handi.contact.topics');
    $maxMb = round(config('handi.contact.attachment_max_kb', 2048) / 1024);
    $accept = collect(config('handi.contact.attachment_mimes'))->map(fn ($e) => '.'.$e)->join(',');
    $val = fn (string $key) => is_string($old[$key] ?? null) ? $old[$key] : '';
    $err = fn (string $key) => $errors->first($key);
@endphp

@section('content')
    <article class="wrap doc contact-page">
        <span class="eyebrow">Contact us</span>
        <h1>Send us a message</h1>
        <p class="lead">Questions, problems or ideas. A person reads every message and replies by email, usually within one working day.</p>

        @if ($sent)
            <div class="contact-sent" id="form" role="status">
                <span class="contact-sent-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </span>
                <h2>Message sent</h2>
                <p>{{ \App\Services\ContactService::THANKS }} Keep an eye on your inbox (and spam folder) for our reply.</p>
                <div class="contact-sent-actions">
                    <a class="btn btn-primary" href="{{ route('site.home') }}">Back to home</a>
                    <a class="btn btn-ghost" href="{{ route('site.contact') }}">Send another message</a>
                </div>
            </div>
        @else
            <form class="contact-form" id="form" method="post" action="{{ route('site.contact.send') }}" enctype="multipart/form-data" novalidate>
                @if ($errors->any())
                    <p class="form-alert" role="alert">Please fix the {{ $errors->count() === 1 ? 'field' : 'fields' }} marked below.</p>
                @endif

                <div class="field-row">
                    <div class="field {{ $err('name') ? 'has-error' : '' }}">
                        <label for="c-name">Your name</label>
                        <input id="c-name" name="name" type="text" autocomplete="name" maxlength="80" required value="{{ $val('name') }}" placeholder="e.g. Ayesha Khan" @if ($err('name')) aria-invalid="true" aria-describedby="c-name-err" @endif>
                        @if ($err('name'))<p class="field-error" id="c-name-err">{{ $err('name') }}</p>@endif
                    </div>
                    <div class="field {{ $err('email') ? 'has-error' : '' }}">
                        <label for="c-email">Email</label>
                        <input id="c-email" name="email" type="email" autocomplete="email" maxlength="255" required value="{{ $val('email') }}" placeholder="name@example.com" aria-describedby="c-email-help{{ $err('email') ? ' c-email-err' : '' }}" @if ($err('email')) aria-invalid="true" @endif>
                        @if ($err('email'))<p class="field-error" id="c-email-err">{{ $err('email') }}</p>@else<p class="field-help" id="c-email-help">We’ll reply here.</p>@endif
                    </div>
                </div>

                <fieldset class="field {{ $err('topic') ? 'has-error' : '' }}">
                    <legend>What’s it about?</legend>
                    <div class="topic-chips">
                        @foreach ($topics as $key => $label)
                            <label class="chip">
                                <input type="radio" name="topic" value="{{ $key }}" required @checked($val('topic') === $key)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if ($err('topic'))<p class="field-error">{{ $err('topic') }}</p>@endif
                </fieldset>

                <div class="field {{ $err('message') ? 'has-error' : '' }}">
                    <label for="c-message">Message</label>
                    <textarea id="c-message" name="message" rows="7" minlength="10" maxlength="3000" required placeholder="How can we help? If something isn’t working, tell us what happened and your phone model or browser." @if ($err('message')) aria-invalid="true" aria-describedby="c-message-err" @endif>{{ $val('message') }}</textarea>
                    @if ($err('message'))<p class="field-error" id="c-message-err">{{ $err('message') }}</p>@else<p class="field-help">10 to 3,000 characters.</p>@endif
                </div>

                <div class="field {{ $err('attachment') ? 'has-error' : '' }}">
                    <label for="c-file">Attachment <span class="optional">(optional)</span></label>
                    <div class="file-drop">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5l-8.6 8.6a5.5 5.5 0 0 1-7.8-7.8l8.6-8.6a3.7 3.7 0 0 1 5.2 5.2l-8.6 8.6a1.8 1.8 0 0 1-2.6-2.6l7.9-7.9"/></svg>
                        <div class="file-body">
                            <small id="c-file-help">Screenshot, photo, PDF or document · up to {{ $maxMb }} MB</small>
                            <input id="c-file" class="file-input" name="attachment" type="file" accept="{{ $accept }}" aria-describedby="c-file-help{{ $err('attachment') ? ' c-file-err' : '' }}" @if ($err('attachment')) aria-invalid="true" @endif>
                        </div>
                    </div>
                    @if ($err('attachment'))<p class="field-error" id="c-file-err">{{ $err('attachment') }}@if (! empty($old)) Please choose the file again.@endif</p>@endif
                </div>

                {{-- Honeypot: hidden from people, bots fill it in. --}}
                <div class="hp" aria-hidden="true">
                    <label for="c-website">Leave this empty</label>
                    <input id="c-website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <button class="btn btn-primary btn-send" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/></svg>
                    Send message
                </button>
            </form>
        @endif

        <section>
            <h2>Other ways to reach us</h2>
            <p>Email <a href="mailto:{{ $support }}?subject=Handi%20help">{{ $support }}</a>, or check the <a href="{{ route('site.support') }}">help page</a> for quick answers about signing in, reminders and your dishes. In the app, tap <b>Need help? Contact us</b> on any sign-in screen, or Settings → Help &amp; contact us.</p>
        </section>
    </article>
@endsection
