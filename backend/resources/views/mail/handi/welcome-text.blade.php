Your kitchen is ready, {{ $name }}

You saved {{ $dishes }} {{ \Illuminate\Support\Str::plural('dish', $dishes) }} your family already cooks. Every morning Handi picks three of them, matched to {{ $city ? $city.'’s weather' : 'today’s weather' }} and what you haven’t had lately.

1. Pick – choose one of today’s three dishes.
2. Cook – make it for lunch or dinner.
3. Log – save it, so it won’t repeat too soon.

See today’s picks: {{ $link }}

Tip: add your own recipes under My dishes → Add custom dish. Your reminder comes at {{ $time }}; change it any time in Settings.

Happy cooking,
The Handi team

--
You’re getting this because you created a Handi account with {{ $email }}.
Questions? Write to {{ config('handi.support_email') }}.
