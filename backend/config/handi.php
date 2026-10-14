<?php

return [

    'app_name' => env('HANDI_APP_NAME', 'What to Cook'),

    /*
    | "Today", picks and meal dates are calculated in each user's timezone (default below).
    | The app timezone (config/app.php) stays UTC for stored timestamps.
    */
    'timezone' => env('HANDI_TIMEZONE', 'Asia/Karachi'),

    'picks' => [
        'per_day' => 3,
        'shuffles_per_day' => (int) env('HANDI_SHUFFLES_PER_DAY', 5),
        'repeat_gap_options' => [3, 5, 7, 10],
        'min_onboarding_dishes' => 10,
    ],

    'meals' => [
        'log_days_back' => 60,
    ],

    'reminder' => [
        'default_time' => '09:00',
        'earliest' => '05:00',
        'latest' => '13:00',
    ],

    'auth' => [
        'max_login_attempts' => 5,
        'lockout_seconds' => 60,
        'code_ttl_minutes' => 15,
        'code_attempts' => 5,
        'code_resend_seconds' => 45,
    ],

    // Weather comes from Open-Meteo (free, no API key). Override the URL to use a proxy.
    'weather' => [
        'url' => env('WEATHER_API_URL', 'https://api.open-meteo.com/v1/forecast'),
        'cache_minutes' => (int) env('WEATHER_CACHE_MINUTES', 60),
    ],

    // Disk for dish photos ("public" locally, "s3" for S3/R2 in production).
    'media_disk' => env('MEDIA_DISK', 'public'),

    // Max photo upload size in kilobytes (design: up to 10 MB).
    'photo_max_kb' => 10240,

    // Used by the DatabaseSeeder to create the first super admin.
    // No defaults on purpose: credentials live only in .env.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Handi Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'support_email' => env('HANDI_SUPPORT_EMAIL', 'support@handi.app'),

];
