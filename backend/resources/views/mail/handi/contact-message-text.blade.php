New message: {!! $contact->topicLabel() !!}

@foreach ($facts as $label => $value)
{!! str_pad($label.':', 12) !!}{!! $value !!}
@endforeach

{!! $contact->message !!}

Reply as Handi team: {!! $adminUrl !!}

--
Sent to the Handi team from the “Contact us” form. Reply from the admin panel so the answer goes out from Handi and is saved with the message.
