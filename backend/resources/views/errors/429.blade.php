@extends('site.layout')
@section('title', 'Please wait a moment · Handi')

@section('content')
    <section class="wrap lost">
        <div>
            <img src="{{ asset('images/site/handi-logo-120.webp') }}" width="73" height="120" alt="" style="margin-inline:auto;">
            <h1>Slow down a little</h1>
            <p>You’ve sent a few messages already. Please try again later, or email <a href="mailto:{{ config('handi.support_email') }}">{{ config('handi.support_email') }}</a>.</p>
            <a class="btn btn-primary" href="{{ route('site.home') }}">Back to Handi</a>
        </div>
    </section>
@endsection
