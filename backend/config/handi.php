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

    // Admin panel URL path (https://your-domain/{admin_path}). Not linked from the website.
    'admin_path' => env('ADMIN_PATH', 'admin'),

    'support_email' => env('HANDI_SUPPORT_EMAIL', 'support@handi.hegemonicinc.com'),

    // "Contact us" form (app + web). Messages are emailed here (reply goes straight to the sender)
    // and also kept in the admin panel, so nothing is lost if the mail server is down.
    'contact' => [
        'to' => env('HANDI_CONTACT_EMAIL', env('HANDI_SUPPORT_EMAIL', 'support@handi.hegemonicinc.com')),
        // Where people's answers to our replies go (the Reply-To on emails sent from the admin panel).
        'reply_to' => env('HANDI_CONTACT_REPLY_TO', env('HANDI_SUPPORT_EMAIL', 'support@handi.hegemonicinc.com')),
        'attachment_max_kb' => 2048,
        'attachment_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'heic', 'gif', 'pdf', 'txt', 'doc', 'docx'],
        'topics' => [
            'question' => 'Question',
            'problem' => 'Something isn’t working',
            'account' => 'My account',
            'feedback' => 'Feedback or idea',
            'other' => 'Something else',
        ],
    ],

    // Store pages shown on the website. Leave empty until the app is live ("Coming soon" is shown instead).
    'store_links' => [
        'android' => env('HANDI_ANDROID_URL'),
        'ios' => env('HANDI_IOS_URL'),
    ],

    // Where email buttons take people. Defaults to the web app on this domain, which works on any
    // device; set handi://today style links instead to open the phone app.
    'app_links' => [
        'today' => env('HANDI_LINK_TODAY', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/app/today'),
        'reset_password' => env('HANDI_LINK_RESET_PASSWORD', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/app/forgot-password'),
    ],

];
