Our reply to your message

{!! $reply->body !!}

Thanks,
The Handi team

--
Your message ({!! $contact->topicLabel() !!}, {!! $sentAt !!}):
> {!! str_replace("\n", "\n> ", \Illuminate\Support\Str::limit($contact->message, 1200)) !!}

Just reply to this email to write back.
