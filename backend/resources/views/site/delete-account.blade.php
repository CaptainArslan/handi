@extends('site.layout')
@section('title', 'Delete your Handi account')
@section('description', 'How to delete your Handi account and the data it holds, from the app or by email.')

@php($support = config('handi.support_email'))

@section('content')
    <article class="wrap doc">
        <span class="eyebrow">Your data</span>
        <h1>Delete your Handi account</h1>
        <p class="summary">Deleting your account removes your kitchen, dishes, photos, meal history, reminders and sign-ins. It can’t be undone.</p>

        <section>
            <h2>In the app (fastest)</h2>
            <ol>
                <li>Open Handi and go to <b>Settings</b>.</li>
                <li>Tap <b>Delete account</b>.</li>
                <li>Enter your password and type <b>DELETE</b> to confirm.</li>
            </ol>
            <p class="muted" style="margin-top:10px;">Your account is removed right away, and we email you a confirmation.</p>
        </section>

        <section>
            <h2>Without the app</h2>
            <div class="card">
                <p>Email <a href="mailto:{{ $support }}?subject=Delete%20my%20Handi%20account">{{ $support }}</a> from the address you signed up with, with the subject <span class="copy">Delete my Handi account</span>.</p>
                <p class="muted">We reply to confirm it’s you, then delete the account within 7 days.</p>
            </div>
        </section>

        <section>
            <h2>What is deleted</h2>
            <p>Your name, email and password; your kitchen settings and location; your dishes and photos; your meal history; your notifications and the phones you signed in on.</p>
        </section>

        <section>
            <h2>What we keep</h2>
            <p>Nothing that identifies you. Server backups that may still contain your data are overwritten within 30 days.</p>
        </section>
    </article>
@endsection
