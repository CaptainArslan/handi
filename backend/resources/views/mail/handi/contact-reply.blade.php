@extends('mail.handi.layout')
@section('preheader', \Illuminate\Support\Str::limit($reply->body, 110))
@section('eyebrow', 'Reply from Handi')
@section('heading', 'Our reply to your message')
@section('why', 'You’re getting this because you sent us a message on '.$sentAt.'. Just reply to this email to write back.')
@section('content')
    @include('mail.handi.parts.p', ['html' => nl2br(e($reply->body))])
    @include('mail.handi.parts.sig', ['html' => 'Thanks,<br>The Handi team'])
    <tr><td style="padding-top:22px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr><td style="border-left:3px solid #E6DDCF; padding:4px 0 4px 14px; font-size:13px; line-height:20px; color:#857D70;">
                <div style="font-weight:600; color:#6E675C; padding-bottom:4px;">Your message · {{ $contact->topicLabel() }} · {{ $sentAt }}</div>
                {!! nl2br(e(\Illuminate\Support\Str::limit($contact->message, 1200))) !!}
            </td></tr>
        </table>
    </td></tr>
@endsection
