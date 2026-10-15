<?php

// Only what Handi changes; every other Sanctum option keeps the package default (merged automatically).
return [
    // App sign-ins expire after 180 days (the app then asks to log in again). null = never.
    'expiration' => env('SANCTUM_TOKEN_EXPIRATION_MINUTES', 60 * 24 * 180),
];
