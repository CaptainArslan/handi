@if ($purpose === 'email_change')
Is this your new email?

You asked to sign in to Handi with {{ $email }}. Enter this code in the app to confirm it:

    {{ $code }}

It expires in {{ $minutes }} minutes and works once. Until you confirm, you keep signing in with your current email. Didn’t ask for this? Ignore this email and nothing changes.
@else
{{ $name ? 'Here’s your code, '.$name : 'Here’s your code' }}

Enter this code in the app to choose a new password:

    {{ $code }}

It expires in {{ $minutes }} minutes and works once. Didn’t ask for this? You can ignore this email. Your password stays the same.
@endif

--
Questions? Write to {{ config('handi.support_email') }}.
