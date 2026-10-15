@extends('site.layout')
@section('title', 'Page not found · Handi')

@section('content')
    <section class="wrap lost">
        <div>
            <img src="{{ asset('images/site/handi-logo-120.webp') }}" width="73" height="120" alt="" style="margin-inline:auto;">
            <h1>This pot is empty</h1>
            <p>We couldn’t find that page. It may have moved.</p>
            <a class="btn btn-primary" href="{{ route('site.home') }}">Back to Handi</a>
        </div>
    </section>
@endsection
