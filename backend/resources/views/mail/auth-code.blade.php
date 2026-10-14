<x-mail::message>
# {{ $purpose === 'email_change' ? 'Confirm your new email' : 'Reset your password' }}

{{ $name ? 'Hi '.$name.',' : 'Hi,' }}

Your 6-digit code is:

<x-mail::panel>
<span style="font-size: 28px; letter-spacing: 6px; font-weight: 700;">{{ $code }}</span>
</x-mail::panel>

It's valid for {{ $minutes }} minutes. If you didn't ask for this, you can ignore this email.

{{ config('handi.app_name') }}
</x-mail::message>
