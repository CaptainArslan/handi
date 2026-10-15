@extends('site.layout')
@section('title', $doc['title'].' · Handi')
@section('description', $doc['summary'])

@section('content')
    <article class="wrap doc">
        <span class="eyebrow">Updated {{ $updated->format('j F Y') }}</span>
        <h1>{{ $doc['title'] }}</h1>
        <p class="summary">{{ $doc['summary'] }}</p>
        @foreach ($doc['sections'] as $section)
            <section>
                <h2>{{ $section['heading'] }}</h2>
                <p>{{ $section['body'] }}</p>
            </section>
        @endforeach
        <section>
            <h2>Contact</h2>
            <p>Questions about this {{ $document === 'privacy' ? 'policy' : 'page' }}? Write to <a href="mailto:{{ config('handi.support_email') }}">{{ config('handi.support_email') }}</a>.
                @if ($document === 'privacy') To remove your data, see <a href="{{ route('site.delete-account') }}">Delete your account</a>.@endif</p>
        </section>
    </article>
@endsection
