Your password was changed

The password for your Handi account was just changed.{{ $signedOut ? ' You’ve been signed out on your other phones.' : '' }}

When:    {{ $when }}
From:    {{ $device }}
Account: {{ $email }}

Wasn’t you? Reset your password now: {{ $link }}

--
We send this every time your password changes, to keep your account safe.
Questions? Write to {{ config('handi.support_email') }}.
