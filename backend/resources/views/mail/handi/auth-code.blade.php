@extends('mail.handi.layout')
@if ($purpose === 'email_change')
    @section('preheader', 'Your code is '.$code.'. Enter it in the app to finish changing your email.')
    @section('eyebrow', 'Confirm email')
    @section('eyebrowTone', 'warm')
    @section('heading', 'Is this your new email?')
    @section('why', 'You’re getting this because this address was added to a Handi account.')
    @section('content')
        @include('mail.handi.parts.p', ['html' => 'You asked to sign in to Handi with <strong style="color:#1E1B16;">'.e($email).'</strong>. Enter this code in the app to confirm it.'])
        @include('mail.handi.parts.code')
        @include('mail.handi.parts.note', ['html' => 'Until you confirm, you keep signing in with your current email. Didn’t ask for this? Ignore this email and nothing changes.'])
    @endsection
@else
    @section('preheader', 'Enter '.$code.' in the app. It expires in '.$minutes.' minutes.')
    @section('eyebrow', 'Password reset')
    @section('eyebrowTone', 'warm')
    @section('heading', $name ? 'Here’s your code, '.$name : 'Here’s your code')
    @section('why', 'You’re getting this because someone asked to reset the password for '.$email.'.')
    @section('content')
        @include('mail.handi.parts.p', ['html' => 'Enter this code in the app to choose a new password.'])
        @include('mail.handi.parts.code')
        @include('mail.handi.parts.note', ['html' => 'Didn’t ask for this? You can ignore this email. Your password stays the same, and nobody can change it without this code.'])
    @endsection
@endif
