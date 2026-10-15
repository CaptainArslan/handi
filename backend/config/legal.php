<?php

// Terms & privacy shown in the app (7.8). Edit the copy here.
return [
    'updated_at' => '2026-10-01',

    'terms' => [
        'title' => 'Terms of use',
        'summary' => 'Short version: Handi suggests dishes. You decide what to cook. Be kind to your data and we’ll be kind to yours.',
        'sections' => [
            ['heading' => '1. Using Handi', 'body' => 'Handi suggests three home-cooked dishes each day based on your family, budget, history and weather. Suggestions are ideas, not dietary or medical advice.'],
            ['heading' => '2. Your account', 'body' => 'One account per household. Keep your password private. You can log out of other phones from Settings at any time.'],
            ['heading' => '3. Your dishes and photos', 'body' => 'Custom dishes and photos you add stay yours. You let us store and show them back to you inside the app, nothing more.'],
            ['heading' => '4. Fair use', 'body' => 'Up to 5 shuffles a day keeps suggestions fresh for everyone. Don’t try to break, copy or resell the service.'],
            ['heading' => '5. Closing your account', 'body' => 'You can delete your account in Settings. We remove your data within 30 days, except where the law asks us to keep it.'],
        ],
    ],

    'privacy' => [
        'title' => 'Privacy policy',
        'summary' => 'We collect only what makes your picks better. We never sell your data or show ads.',
        'sections' => [
            ['heading' => '1. What we store', 'body' => 'Your name, email and password (stored encrypted), family size, budget, meal preferences, your dish list and custom dishes, cooking history, reminder time and time zone.'],
            ['heading' => '2. Location', 'body' => 'Used only to check today’s weather. It’s rounded to about 1 km, or you can pick a city instead. The rounded point is sent to our weather provider (Open-Meteo) to get the forecast. It’s never shared for any other purpose.'],
            ['heading' => '3. Photos', 'body' => 'Photos you add to your dishes are stored on our servers and shown only to you inside the app.'],
            ['heading' => '4. Notifications', 'body' => 'One daily reminder at the time you choose, plus account messages. To deliver them we store a push token for your phone and send messages through Expo and Google Firebase Cloud Messaging. Turn them off in Settings at any time.'],
            ['heading' => '5. Emails', 'body' => 'We email you only about your account: a welcome message, sign-in codes and security notices. No newsletters, no marketing.'],
            ['heading' => '6. Offline data', 'body' => 'Today’s picks and your library are saved on your phone so the app works without internet. Changes sync when you’re back online.'],
            ['heading' => '7. Who we share with', 'body' => 'Nobody, except the services that run the app for us: our hosting provider, Open-Meteo (weather), Expo and Google (push notifications) and our email provider. They may only use your data to provide that service. We don’t sell data, show ads or use tracking.'],
            ['heading' => '8. How long we keep it', 'body' => 'As long as your account exists. Security logs are kept for up to 90 days. Deleting your account removes your data right away; backups are overwritten within 30 days.'],
            ['heading' => '9. Children', 'body' => 'Handi is meant for adults who cook for a household. It’s not directed at children under 13.'],
            ['heading' => '10. Your rights', 'body' => 'See, correct or delete your data at any time from the app, or by emailing us. You can delete your account in Settings → Delete account.'],
        ],
    ],
];
