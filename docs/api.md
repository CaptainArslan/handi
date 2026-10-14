# Handi API (v1)

The app talks to one JSON API under **`/api/v1`**. This guide lists every endpoint with real request and
response examples recorded from a seeded local server. The same information is browsable — with
"Try it" — at **`/docs/api`** (Scramble / OpenAPI; raw spec at `/docs/api.json`), and every request is in
the Postman collection [`handi-api.postman_collection.json`](handi-api.postman_collection.json).

## Basics

| | |
|---|---|
| Base URL | `http://localhost:8000/api` locally (on a phone use your PC's LAN IP, e.g. `http://192.168.1.10:8000/api`) |
| Headers | `Accept: application/json` always; `Content-Type: application/json` for JSON bodies |
| Auth | `Authorization: Bearer {token}` — the token comes from sign up or log in (Sanctum personal access token, one per phone) |
| Dates | The user's local date `YYYY-MM-DD` (timezone on the account, default `Asia/Karachi`). Timestamps are ISO-8601 UTC. |
| Images | `image_url` is ready to show. Library dishes without a photo use the design illustrations; `image_bg` is the tint behind them. |
| Rate limits | 180 req/min signed in, 60/min guest, 20/min on `/auth/*`. Exceeding returns 429. |

### Errors

| Status | When | Body |
|---|---|---|
| 401 | Missing/revoked token, or wrong password on log in | `{"message": "...", "attempts_left": 4}` on log in |
| 403 | Not allowed (e.g. API docs without admin) | `{"message": "..."}` |
| 404 | Not found, or not yours | `{"message": "..."}` |
| 422 | Validation — messages are the exact copy the app shows | `{"message": "...", "errors": {"field": ["..."]}}` |
| 429 | Too many requests, login lockout, no shuffles left, resend too soon | `{"message": "...", "retry_after": 37}` |

### Testing in Postman

1. Import `docs/handi-api.postman_collection.json`.
2. Set the collection variable `base_url` (default `http://localhost:8000/api`).
3. Run **Auth → Log in** (seeded demo user from `DEMO_USER_EMAIL` / `DEMO_USER_PASSWORD`) or **Sign up**. The token is saved automatically into `{{token}}` and sent on every other request.
4. Requests that return ids (Today, My dishes, Custom dishes, Meals, Notifications) save them as variables, so the follow-up requests (`{{dish_id}}`, `{{meal_id}}`, …) just work in order.

## Endpoints

| Group | Method | Path | What |
|---|---|---|---|
| Public catalog | `GET` | `/api/v1/library/dishes` | Dish library |
| Public catalog | `GET` | `/api/v1/categories` | Categories |
| Public catalog | `GET` | `/api/v1/cities` | Cities |
| Public catalog | `GET` | `/api/v1/weather` | Weather |
| Public catalog | `GET` | `/api/v1/legal/{document}` | Terms or privacy policy |
| Public catalog | `POST` | `/api/v1/preview-picks` | Preview today’s picks |
| Auth | `POST` | `/api/v1/auth/register` | Sign up |
| Auth | `POST` | `/api/v1/auth/login` | Log in |
| Auth | `POST` | `/api/v1/auth/logout` | Log out |
| Auth · password reset | `POST` | `/api/v1/auth/password/forgot` | Send code |
| Auth · password reset | `POST` | `/api/v1/auth/password/resend` | Resend code |
| Auth · password reset | `POST` | `/api/v1/auth/password/verify` | Verify code |
| Auth · password reset | `POST` | `/api/v1/auth/password/reset` | Set new password |
| Account | `GET` | `/api/v1/me` | Me |
| Account | `PATCH` | `/api/v1/me` | Update profile |
| Account | `DELETE` | `/api/v1/me` | Delete account |
| Account | `POST` | `/api/v1/me/email/verify` | Confirm new email |
| Account | `PUT` | `/api/v1/me/password` | Change password |
| Kitchen settings | `PATCH` | `/api/v1/me/kitchen` | Update kitchen |
| Kitchen settings | `PUT` | `/api/v1/me/location` | Set location |
| Today | `GET` | `/api/v1/me/today` | Today |
| Today | `POST` | `/api/v1/me/today/shuffle` | Shuffle all 3 |
| Today | `PUT` | `/api/v1/me/today/plan` | Cook this today |
| Today | `DELETE` | `/api/v1/me/today/plan` | Back to picks |
| My dishes | `GET` | `/api/v1/me/dishes` | My dishes |
| My dishes | `POST` | `/api/v1/me/dishes` | Add library dishes |
| My dishes | `GET` | `/api/v1/me/dishes/{id}` | Dish detail |
| My dishes | `PATCH` | `/api/v1/me/dishes/{id}` | Change weather, effort or cost |
| My dishes | `DELETE` | `/api/v1/me/dishes/{id}` | Remove from my dishes |
| My dishes | `DELETE` | `/api/v1/me/dishes/{id}/overrides` | Reset to library tags |
| My dishes | `POST` | `/api/v1/me/dishes/{id}/photo` | Add or change photo |
| My dishes | `POST` | `/api/v1/me/dishes/{id}/restore` | Undo remove |
| My dishes · custom | `GET` | `/api/v1/me/custom-dishes` | My custom dishes |
| My dishes · custom | `POST` | `/api/v1/me/custom-dishes` | Add custom dish |
| My dishes · custom | `PATCH` | `/api/v1/me/custom-dishes/{id}` | Edit custom dish |
| My dishes · custom | `DELETE` | `/api/v1/me/custom-dishes/{id}` | Remove custom dish |
| My dishes · custom | `POST` | `/api/v1/me/custom-dishes/{id}/restore` | Undo remove |
| History | `GET` | `/api/v1/me/meals` | History for a month |
| History | `POST` | `/api/v1/me/meals` | Log a meal / Mark as cooked |
| History | `PATCH` | `/api/v1/me/meals/{id}` | Edit a logged meal |
| History | `DELETE` | `/api/v1/me/meals/{id}` | Delete a logged meal |
| History | `POST` | `/api/v1/me/meals/{id}/restore` | Undo delete |
| History | `GET` | `/api/v1/me/stats` | Stats |
| History | `POST` | `/api/v1/me/sync` | Sync offline changes |
| Notifications | `POST` | `/api/v1/me/devices` | Register this phone for push |
| Notifications | `DELETE` | `/api/v1/me/devices/{deviceId}` | Remove a phone |
| Notifications | `GET` | `/api/v1/me/notifications` | Notifications |
| Notifications | `POST` | `/api/v1/me/notifications/read-all` | Mark all as read |
| Notifications | `PATCH` | `/api/v1/me/notifications/{id}` | Mark as read |
| Notifications | `DELETE` | `/api/v1/me/notifications/{id}` | Clear |
| Notifications | `POST` | `/api/v1/me/notifications/{id}/restore` | Undo clear |
| Notifications | `GET` | `/api/v1/me/notification-settings` | Notification settings |
| Notifications | `PUT` | `/api/v1/me/notification-settings` | Update notification settings |
| Notifications | `POST` | `/api/v1/me/notification-settings/test` | Send a test notification |

## Public catalog

No token needed. Used by onboarding (before sign-up) and to show the library.

### Dish library

`GET /api/v1/library/dishes` · public

The global library users pick their kitchen from during onboarding. Cached; admin edits apply on the next request.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `q` | query | string |  |  |
| `category` | query | string |  |  |

**Example — 200**

```http
GET /api/v1/library/dishes?category=rice
```

```json
{
  "data": [
    {
      "id": 34,
      "name": "Biryani",
      "category": {
        "key": "rice",
        "name": "Rice"
      },
      "note": "Weekend centrepiece",
      "image_url": "http://localhost:8000/images/dishes/biryani.png",
      "image_key": "biryani",
      "image_bg": "#DCCFF0",
      "default_weather": [
        "any"
      ],
      "default_effort": "heavy",
      "default_cost": "medium",
      "usual_meal": "both"
    },
    {
      "id": 37,
      "name": "Khichri",
      "category": {
        "key": "rice",
        "name": "Rice"
      },
      "note": "Easy on the stomach",
      "image_url": "http://localhost:8000/images/dishes/biryani.png",
      "image_key": "biryani",
      "image_bg": "#DCCFF0",
      "default_weather": [
        "rainy",
        "cold"
      ],
      "default_effort": "quick",
      "default_cost": "low",
      "usual_meal": "dinner"
    },
    "… 2 more"
  ],
  "meta": {
    "version": 82,
    "total": 4
  }
}
```

### Categories

`GET /api/v1/categories` · public

**Example — 200**

```http
GET /api/v1/categories
```

```json
{
  "data": [
    {
      "key": "vegetables",
      "name": "Vegetables",
      "image_key": "bhindi"
    },
    {
      "key": "lentils",
      "name": "Lentils",
      "image_key": "lentils"
    },
    "… 7 more"
  ]
}
```

### Cities

`GET /api/v1/cities` · public

Popular cities plus every city grouped by province.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `q` | query | string |  |  |

**Example — 200**

```http
GET /api/v1/cities?q=lah
```

```json
{
  "data": {
    "popular": [
      {
        "id": 1,
        "name": "Lahore",
        "province": "Punjab",
        "province_short": "Punjab",
        "is_popular": true
      },
      {
        "id": 3,
        "name": "Rawalpindi",
        "province": "Punjab",
        "province_short": "Punjab",
        "is_popular": true
      },
      "… 3 more"
    ],
    "provinces": [
      {
        "name": "Punjab",
        "short_name": "Punjab",
        "cities": [
          {
            "id": 1,
            "name": "Lahore",
            "province": "Punjab",
            "province_short": "Punjab",
            "is_popular": true
          }
        ]
      }
    ]
  }
}
```

### Weather

`GET /api/v1/weather` · public

Today's weather for a city or coordinates, bucketed into hot / mild / cold / rainy.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `city_id` | query | integer |  |  |
| `lat` | query | number |  |  |
| `lng` | query | number |  |  |

**Example — 200**

```http
GET /api/v1/weather?city_id=1
```

```json
{
  "data": {
    "temp_now": 31,
    "temp_high": 34,
    "rain_chance": 10,
    "condition": "hot",
    "available": true,
    "fetched_at": "2026-10-13T18:10:16+00:00",
    "city_label": "Lahore",
    "summary": "31° · hot · rain 10%",
    "headline": "It’s hot today. How about something light?",
    "province": "Punjab"
  }
}
```

### Terms or privacy policy

`GET /api/v1/legal/{document}` · public

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `document` | path | string | yes |  |

**Example — 200**

```http
GET /api/v1/legal/terms
```

```json
{
  "data": {
    "title": "Terms of use",
    "summary": "Short version: Handi suggests dishes. You decide what to cook. Be kind to your data and we’ll be kind to yours.",
    "sections": [
      {
        "heading": "1. Using Handi",
        "body": "Handi suggests three home-cooked dishes each day based on your family, budget, history and weather. Suggestions are ideas, not dietary or medical advice."
      },
      {
        "heading": "2. Your account",
        "body": "One account per household. Keep your password private. You can log out of other phones from Settings at any time."
      },
      "… 3 more"
    ],
    "updated_at": "2026-10-01",
    "support_email": "support@handi.app"
  }
}
```

### Preview today’s picks

`POST /api/v1/preview-picks` · public

Runs the real picking rules on a guest's choices before they sign up. Nothing is saved.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `dish_ids` | body | integer[] | yes | min items 1 |
| `location` | body | object |  |  |
| `family_size` | body | integer |  | min 1; max 20 |
| `budget` | body |  |  |  |
| `repeat_gap_days` | body | integer |  | one of `3`, `5`, `7`, `10` |
| `meals` | body | array |  | min items 1 |

**Example — 200**

```http
POST /api/v1/preview-picks

{
  "dish_ids": [
    1,
    2,
    4,
    9,
    11,
    17,
    23,
    24,
    29,
    32,
    34,
    35
  ],
  "location": {
    "mode": "city",
    "city_id": 1
  }
}
```

```json
{
  "data": {
    "date": "2026-10-13",
    "weather": {
      "temp_now": 31,
      "temp_high": 34,
      "rain_chance": 10,
      "condition": "hot",
      "available": true,
      "fetched_at": "2026-10-13T18:10:16+00:00",
      "city_label": "Lahore",
      "summary": "31° · hot · rain 10%",
      "headline": "It’s hot today. How about something light?"
    },
    "picks": [
      {
        "id": 9,
        "name": "Daal Chawal",
        "category": {
          "key": "lentils",
          "name": "Lentils"
        },
        "note": "Comfort classic",
        "image_url": "http://localhost:8000/images/dishes/lentils.png",
        "image_key": "lentils",
        "image_bg": "#F7D98C",
        "default_weather": [
          "hot",
          "mild"
        ],
        "default_effort": "quick",
        "default_cost": "low",
        "usual_meal": "both",
        "reason": "Fits hot weather"
      },
      {
        "id": 4,
        "name": "Karela",
        "category": {
          "key": "vegetables",
          "name": "Vegetables"
        },
        "note": null,
        "image_url": "http://localhost:8000/images/dishes/bhindi.png",
        "image_key": "bhindi",
        "image_bg": "#CFE6CF",
        "default_weather": [
          "hot"
        ],
        "default_effort": "medium",
        "default_cost": "low",
        "usual_meal": "both",
        "reason": "Fits hot weather"
      },
      {
        "id": 34,
        "name": "Biryani",
        "category": {
          "key": "rice",
          "name": "Rice"
        },
        "note": "Weekend centrepiece",
        "image_url": "http://localhost:8000/images/dishes/biryani.png",
        "image_key": "biryani",
        "image_bg": "#DCCFF0",
        "default_weather": [
          "any"
        ],
        "default_effort": "heavy",
        "default_cost": "medium",
        "usual_meal": "both",
        "reason": "Fits hot weather"
      }
    ]
  }
}
```

## Auth

Sign up with the whole onboarding kitchen in one call, log in, log out. Both return a Sanctum token.

### Sign up

`POST /api/v1/auth/register` · public

Creates the account together with everything chosen during onboarding:
family & budget, preferences, location, at least 10 dishes and any custom dishes.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `name` | body | string | yes | min length 2; max length 50 |
| `email` | body | string | yes | max length 255; email |
| `password` | body | string | yes | min length 8; max length 100 |
| `password_confirmation` | body | string | yes | min length 8; max length 100 |
| `terms_accepted` | body |  |  | one of `yes`, `on`, `1`, `1`, `true`, `True` |
| `device_name` | body | string |  | max length 100 |
| `timezone` | body | string |  |  |
| `kitchen` | body | object | yes |  |
| `location` | body | object |  |  |
| `dish_ids` | body | integer[] | yes |  |
| `custom_dishes` | body | object[] |  | max items 50 |

**Example — 201**

```http
POST /api/v1/auth/register

{
  "name": "Sara Ahmed",
  "email": "sara1791915016@example.com",
  "password": "kitchen123",
  "password_confirmation": "kitchen123",
  "terms_accepted": true,
  "device_name": "Pixel 8",
  "timezone": "Asia/Karachi",
  "kitchen": {
    "family_size": 4,
    "budget": "normal",
    "repeat_gap_days": 5,
    "meals": [
      "lunch",
      "dinner"
    ]
  },
  "location": {
    "mode": "city",
    "city_id": 1
  },
  "dish_ids": [
    1,
    2,
    4,
    9,
    11,
    17,
    23,
    24,
    29,
    32,
    34,
    35
  ],
  "custom_dishes": [
    {
      "name": "Mom’s Karhi",
      "category": "lentils"
    }
  ]
}
```

```json
{
  "data": {
    "token": "1|H2lJBIzhMPB3CztVAR4fC04Iim1pSmjac4EXKISV97d421d0",
    "user": {
      "id": 3,
      "name": "Sara Ahmed",
      "first_name": "Sara",
      "initial": "S",
      "email": "sara1791915016@example.com",
      "masked_email": "s***@example.com",
      "timezone": "Asia/Karachi",
      "password_changed_at": "2026-10-13T18:10:16+00:00",
      "created_at": "2026-10-13T18:10:16+00:00",
      "kitchen": {
        "family_size": 4,
        "budget": "normal",
        "repeat_gap_days": 5,
        "meals": [
          "lunch",
          "dinner"
        ],
        "location": {
          "mode": "city",
          "city_id": 1,
          "city": "Lahore",
          "province": "Punjab",
          "province_short": "Punjab",
          "label": "Lahore, Punjab"
        },
        "notifications": {
          "enabled": true,
          "time": "09:00"
        },
        "last_synced_at": null
      },
      "counts": {
        "dishes": 13,
        "custom_dishes": 1,
        "unread_notifications": 1
      }
    }
  }
}
```

**Example — 422**

```http
POST /api/v1/auth/register

{
  "name": "Sara Ahmed",
  "email": "bad",
  "password": "short",
  "password_confirmation": "short",
  "terms_accepted": true,
  "device_name": "Pixel 8",
  "timezone": "Asia/Karachi",
  "kitchen": {
    "family_size": 4,
    "budget": "normal",
    "repeat_gap_days": 5,
    "meals": [
      "lunch",
      "dinner"
    ]
  },
  "location": {
    "mode": "city",
    "city_id": 1
  },
  "dish_ids": [
    1,
    2
  ],
  "custom_dishes": [
    {
      "name": "Mom’s Karhi",
      "category": "lentils"
    }
  ]
}
```

```json
{
  "message": "Enter a valid email, like name@example.com. (and 2 more errors)",
  "errors": {
    "email": [
      "Enter a valid email, like name@example.com."
    ],
    "password": [
      "Use at least 8 characters.",
      "Mix letters and at least one number."
    ]
  }
}
```

### Log in

`POST /api/v1/auth/login` · public

Five wrong passwords lock this email (from this IP) for 60 seconds.
Wrong password → 401 with `attempts_left`; locked → 429 with `retry_after`.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `email` | body | string | yes | email |
| `password` | body | string | yes |  |
| `device_name` | body | string |  | max length 100 |

**Example — 401**

```http
POST /api/v1/auth/login

{
  "email": "sara1791915016@example.com",
  "password": "wrongpass1",
  "device_name": "Pixel 8"
}
```

```json
{
  "message": "Email or password is incorrect. 4 tries left.",
  "attempts_left": 4
}
```

**Example — 200**

```http
POST /api/v1/auth/login

{
  "email": "hina@example.com",
  "password": "kitchen123",
  "device_name": "Postman"
}
```

```json
{
  "data": {
    "token": "2|l5C8kS1xCnImjypaJhj5iGwUn43klwIHTKgCATOh9bc53e59",
    "user": {
      "id": 2,
      "name": "Hina Arslan",
      "first_name": "Hina",
      "initial": "H",
      "email": "hina@example.com",
      "masked_email": "h***@example.com",
      "timezone": "Asia/Karachi",
      "password_changed_at": "2026-07-13T18:10:11+00:00",
      "created_at": "2026-10-13T18:10:11+00:00",
      "kitchen": {
        "family_size": 4,
        "budget": "normal",
        "repeat_gap_days": 5,
        "meals": [
          "lunch",
          "dinner"
        ],
        "location": {
          "mode": "city",
          "city_id": 1,
          "city": "Lahore",
          "province": "Punjab",
          "province_short": "Punjab",
          "label": "Lahore, Punjab"
        },
        "notifications": {
          "enabled": true,
          "time": "09:00"
        },
        "last_synced_at": null
      },
      "counts": {
        "dishes": 14,
        "custom_dishes": 3,
        "unread_notifications": 1
      }
    }
  }
}
```

### Log out

`POST /api/v1/auth/logout` · 🔒 token

Revokes this phone's token and stops reminders on it.

**Example — 204**

```http
POST /api/v1/auth/logout
```

_No content._

## Auth · password reset

Forgot password → 6-digit code by email (15 min, 5 tries, resend after 45 s) → reset token → new password. Never reveals whether an email exists.

### Send code

`POST /api/v1/auth/password/forgot` · public

Always 202. Includes the masked email, how long the code is valid and when it can be resent.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `email` | body | string | yes | The account email; email |

**Example — 202**

```http
POST /api/v1/auth/password/forgot

{
  "email": "sara1791915016@example.com"
}
```

```json
{
  "data": {
    "masked_email": "s***@example.com",
    "expires_in": 900,
    "resend_in": 44
  }
}
```

### Resend code

`POST /api/v1/auth/password/resend` · public

429 with `retry_after` if asked again within 45 seconds.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `email` | body | string | yes | The account email; email |

**Example — 429**

```http
POST /api/v1/auth/password/resend

{
  "email": "sara1791915016@example.com"
}
```

```json
{
  "message": "You can resend the code in 44s.",
  "retry_after": 44
}
```

### Verify code

`POST /api/v1/auth/password/verify` · public

Returns a `reset_token` for “Set new password”. Wrong code → 422 with `attempts_left`;
after 5 wrong codes → 429 (request a new code).

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `code` | body | string | yes |  |
| `email` | body | string | yes | The account email; email |

**Example — 422**

```http
POST /api/v1/auth/password/verify

{
  "email": "sara1791915016@example.com",
  "code": "000000"
}
```

```json
{
  "message": "That code doesn’t match. 4 attempts left.",
  "errors": {
    "code": [
      "That code doesn’t match."
    ]
  },
  "attempts_left": 4
}
```

**Example — 200**

```http
POST /api/v1/auth/password/verify

{
  "email": "sara1791915016@example.com",
  "code": "898318"
}
```

```json
{
  "data": {
    "reset_token": "XpMHGniXYYw7Tn4abhWiPQ3iIpWyVHfxvbkn5CZvHoQBuhl1shIB3WVUVRhEB4b3"
  }
}
```

### Set new password

`POST /api/v1/auth/password/reset` · public

Logs the account out on every phone.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `reset_token` | body | string | yes |  |
| `password` | body | string | yes | min length 8; max length 100 |
| `password_confirmation` | body | string | yes | min length 8; max length 100 |

**Example — 204**

```http
POST /api/v1/auth/password/reset

{
  "reset_token": "XpMHGniXYYw7Tn4abhWiPQ3iIpWyVHfxvbkn5CZvHoQBuhl1shIB3WVUVRhEB4b3",
  "password": "newpass123",
  "password_confirmation": "newpass123"
}
```

_No content._

## Account

The signed-in user. Email changes are confirmed with a 6-digit code sent to the new address.

### Me

`GET /api/v1/me` · 🔒 token

The signed-in user with kitchen settings, location, reminder and counts.

**Example — 200**

```http
GET /api/v1/me
```

```json
{
  "data": {
    "id": 2,
    "name": "Hina Arslan",
    "first_name": "Hina",
    "initial": "H",
    "email": "hina@example.com",
    "masked_email": "h***@example.com",
    "timezone": "Asia/Karachi",
    "password_changed_at": "2026-07-13T18:10:11+00:00",
    "created_at": "2026-10-13T18:10:11+00:00",
    "kitchen": {
      "family_size": 4,
      "budget": "normal",
      "repeat_gap_days": 5,
      "meals": [
        "lunch",
        "dinner"
      ],
      "location": {
        "mode": "city",
        "city_id": 1,
        "city": "Lahore",
        "province": "Punjab",
        "province_short": "Punjab",
        "label": "Lahore, Punjab"
      },
      "notifications": {
        "enabled": true,
        "time": "09:00"
      },
      "last_synced_at": null
    },
    "counts": {
      "dishes": 14,
      "custom_dishes": 3,
      "unread_notifications": 1
    }
  }
}
```

### Update profile

`PATCH /api/v1/me` · 🔒 token

Changing the email needs `current_password`; a 6-digit code is emailed to the new
address and the change applies after `POST /me/email/verify`.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `name` | body | string |  | min length 2; max length 50 |
| `email` | body | string |  | max length 255; email |
| `current_password` | body | string |  |  |

**Example — 200**

```http
PATCH /api/v1/me

{
  "name": "Hina Arslan"
}
```

```json
{
  "data": {
    "id": 2,
    "name": "Hina Arslan",
    "first_name": "Hina",
    "initial": "H",
    "email": "hina@example.com",
    "masked_email": "h***@example.com",
    "timezone": "Asia/Karachi",
    "password_changed_at": "2026-07-13T18:10:11+00:00",
    "created_at": "2026-10-13T18:10:11+00:00",
    "kitchen": {
      "family_size": 4,
      "budget": "normal",
      "repeat_gap_days": 5,
      "meals": [
        "lunch",
        "dinner"
      ],
      "location": {
        "mode": "city",
        "city_id": 1,
        "city": "Lahore",
        "province": "Punjab",
        "province_short": "Punjab",
        "label": "Lahore, Punjab"
      },
      "notifications": {
        "enabled": true,
        "time": "09:00"
      },
      "last_synced_at": null
    },
    "counts": {
      "dishes": 14,
      "custom_dishes": 3,
      "unread_notifications": 1
    }
  },
  "meta": {
    "email_change_pending": false
  }
}
```

**Example — 200**

```http
PATCH /api/v1/me

{
  "email": "new-sara1791915016@example.com",
  "current_password": "newpass123"
}
```

```json
{
  "data": {
    "id": 3,
    "name": "Sara Ahmed",
    "first_name": "Sara",
    "initial": "S",
    "email": "sara1791915016@example.com",
    "masked_email": "s***@example.com",
    "timezone": "Asia/Karachi",
    "password_changed_at": "2026-10-13T18:10:18+00:00",
    "created_at": "2026-10-13T18:10:16+00:00",
    "kitchen": {
      "family_size": 4,
      "budget": "normal",
      "repeat_gap_days": 5,
      "meals": [
        "lunch",
        "dinner"
      ],
      "location": {
        "mode": "city",
        "city_id": 1,
        "city": "Lahore",
        "province": "Punjab",
        "province_short": "Punjab",
        "label": "Lahore, Punjab"
      },
      "notifications": {
        "enabled": true,
        "time": "09:00"
      },
      "last_synced_at": null
    },
    "counts": {
      "dishes": 13,
      "custom_dishes": 1,
      "unread_notifications": 1
    }
  },
  "meta": {
    "email_change_pending": true
  }
}
```

### Delete account

`DELETE /api/v1/me` · 🔒 token

Needs the password and the word DELETE. Removes the user and all their data.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `password` | query | string | yes |  |
| `confirmation` | query | string | yes | One of: `DELETE` |
| `password` | body | string | yes | Current password |
| `confirmation` | body | string | yes | Must be exactly DELETE |

**Example — 204**

```http
DELETE /api/v1/me

{
  "password": "kitchen456",
  "confirmation": "DELETE"
}
```

_No content._

### Confirm new email

`POST /api/v1/me/email/verify` · 🔒 token

The 6-digit code sent to the new address.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `code` | body | string | yes |  |

**Example — 200**

```http
POST /api/v1/me/email/verify

{
  "code": "474048"
}
```

```json
{
  "data": {
    "id": 3,
    "name": "Sara Ahmed",
    "first_name": "Sara",
    "initial": "S",
    "email": "new-sara1791915016@example.com",
    "masked_email": "n***@example.com",
    "timezone": "Asia/Karachi",
    "password_changed_at": "2026-10-13T18:10:18+00:00",
    "created_at": "2026-10-13T18:10:16+00:00",
    "kitchen": {
      "family_size": 4,
      "budget": "normal",
      "repeat_gap_days": 5,
      "meals": [
        "lunch",
        "dinner"
      ],
      "location": {
        "mode": "city",
        "city_id": 1,
        "city": "Lahore",
        "province": "Punjab",
        "province_short": "Punjab",
        "label": "Lahore, Punjab"
      },
      "notifications": {
        "enabled": true,
        "time": "09:00"
      },
      "last_synced_at": null
    },
    "counts": {
      "dishes": 13,
      "custom_dishes": 1,
      "unread_notifications": 1
    }
  }
}
```

### Change password

`PUT /api/v1/me/password` · 🔒 token

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `current_password` | body | string | yes |  |
| `password` | body | string | yes | min length 8; max length 100 |
| `password_confirmation` | body | string | yes | min length 8; max length 100 |
| `sign_out_other_devices` | body | boolean |  |  |

**Example — 422**

```http
PUT /api/v1/me/password

{
  "current_password": "wrong",
  "password": "kitchen456",
  "password_confirmation": "kitchen456"
}
```

```json
{
  "message": "Current password is incorrect.",
  "errors": {
    "current_password": [
      "Current password is incorrect."
    ]
  }
}
```

**Example — 204**

```http
PUT /api/v1/me/password

{
  "current_password": "newpass123",
  "password": "kitchen456",
  "password_confirmation": "kitchen456",
  "sign_out_other_devices": true
}
```

_No content._

## Kitchen settings

Family size, budget, repeat gap, meals, location and phones registered for push.

### Update kitchen

`PATCH /api/v1/me/kitchen` · 🔒 token

Send any of the fields. Changes apply from tomorrow's picks.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `family_size` | body | integer |  | min 1; max 20 |
| `budget` | body |  |  |  |
| `repeat_gap_days` | body | integer |  | one of `3`, `5`, `7`, `10` |
| `meals` | body | array |  | min items 1 |

**Example — 200**

```http
PATCH /api/v1/me/kitchen

{
  "family_size": 5,
  "budget": "normal",
  "repeat_gap_days": 5,
  "meals": [
    "lunch",
    "dinner"
  ]
}
```

```json
{
  "data": {
    "family_size": 5,
    "budget": "normal",
    "repeat_gap_days": 5,
    "meals": [
      "lunch",
      "dinner"
    ],
    "location": {
      "mode": "city",
      "city_id": 1,
      "city": "Lahore",
      "province": "Punjab",
      "province_short": "Punjab",
      "label": "Lahore, Punjab"
    },
    "notifications": {
      "enabled": true,
      "time": "09:00"
    },
    "last_synced_at": null
  }
}
```

### Set location

`PUT /api/v1/me/location` · 🔒 token

`{mode:"city", city_id}` or `{mode:"gps", lat, lng}` (rounded to ~1 km). Returns the kitchen and today's weather there.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `mode` | body | string | yes | one of `gps`, `city` |
| `city_id` | body | integer |  |  |
| `lat` | body | number |  | min -90; max 90 |
| `lng` | body | number |  | min -180; max 180 |

**Example — 200**

```http
PUT /api/v1/me/location

{
  "mode": "city",
  "city_id": 1
}
```

```json
{
  "data": {
    "kitchen": {
      "family_size": 5,
      "budget": "normal",
      "repeat_gap_days": 5,
      "meals": [
        "lunch",
        "dinner"
      ],
      "location": {
        "mode": "city",
        "city_id": 1,
        "city": "Lahore",
        "province": "Punjab",
        "province_short": "Punjab",
        "label": "Lahore, Punjab"
      },
      "notifications": {
        "enabled": true,
        "time": "09:00"
      },
      "last_synced_at": null
    },
    "weather": {
      "temp_now": 31,
      "temp_high": 34,
      "rain_chance": 10,
      "condition": "hot",
      "available": true,
      "fetched_at": "2026-10-13T18:10:16+00:00",
      "city_label": "Lahore",
      "summary": "31° · hot · rain 10%",
      "headline": "It’s hot today. How about something light?"
    }
  }
}
```

## Today

The daily loop: 3 picks (distinct categories, weather-aware) → plan one → mark it cooked (`POST /me/meals` with `source: suggestion`).

### Today

`GET /api/v1/me/today` · 🔒 token

Everything the Today tab needs: weather, the 3 picks (first = top pick), shuffles left,
the planned dish, today's log from the picks, stats and when the next picks arrive.
`stage` is pick | planned | done.

**Example — 200**

```http
GET /api/v1/me/today
```

```json
{
  "data": {
    "date": "2026-10-13",
    "greeting_name": "Hina",
    "initial": "H",
    "unread_notifications": 1,
    "weather": {
      "temp_now": 31,
      "temp_high": 34,
      "rain_chance": 10,
      "condition": "hot",
      "available": true,
      "fetched_at": "2026-10-13T18:10:16+00:00",
      "city_label": "Lahore",
      "summary": "31° · hot · rain 10%",
      "headline": "It’s hot today. How about something light?"
    },
    "generated_at": "2026-10-13T18:10:21+00:00",
    "stage": "pick",
    "shuffles_left": 5,
    "shuffles_per_day": 5,
    "picks": [
      {
        "id": 1,
        "name": "Bhindi",
        "category": {
          "key": "vegetables",
          "name": "Vegetables"
        },
        "is_custom": false,
        "image_url": "http://localhost:8000/images/dishes/bhindi.png",
        "image_key": "bhindi",
        "image_bg": "#CFE6CF",
        "weather": [
          "hot",
          "mild"
        ],
        "effort": "medium",
        "cost": "low",
        "usual_meal": "both",
        "last_cooked_at": "2026-09-29",
        "days_since_cooked": 14,
        "removed": false,
        "reason": "Fits hot weather",
        "is_top": true
      },
      {
        "id": 6,
        "name": "Chicken Karahi",
        "category": {
          "key": "chicken",
          "name": "Chicken"
        },
        "is_custom": false,
        "image_url": "http://localhost:8000/images/dishes/karahi.png",
        "image_key": "karahi",
        "image_bg": "#F6C2AE",
        "weather": [
          "any"
        ],
        "effort": "medium",
        "cost": "medium",
        "usual_meal": "dinner",
        "last_cooked_at": null,
        "days_since_cooked": null,
        "removed": false,
        "reason": "Fits hot weather",
        "is_top": false
      },
      {
        "id": 4,
        "name": "Daal Chawal",
        "category": {
          "key": "lentils",
          "name": "Lentils"
        },
        "is_custom": false,
        "image_url": "http://localhost:8000/images/dishes/lentils.png",
        "image_key": "lentils",
        "image_bg": "#F7D98C",
        "weather": [
          "hot",
          "mild"
        ],
        "effort": "quick",
        "cost": "low",
        "usual_meal": "both",
        "last_cooked_at": "2026-10-04",
        "days_since_cooked": 9,
        "removed": false,
        "reason": "Fits hot weather",
        "is_top": false
      }
    ],
    "plan": null,
    "log": null,
    "stats": {
      "streak_days": 6,
      "meals_this_month": 10
    },
    "repeat_gap_days": 5,
    "reminder": {
      "enabled": true,
      "time": "09:00"
    },
    "next_picks_at": "2026-10-14T09:00:00+05:00"
  }
}
```

### Shuffle all 3

`POST /api/v1/me/today/shuffle` · 🔒 token

Up to 5 times a day. 429 when none are left.

**Example — 200**

```http
POST /api/v1/me/today/shuffle
```

```json
{
  "data": {
    "date": "2026-10-13",
    "greeting_name": "Hina",
    "initial": "H",
    "unread_notifications": 1,
    "weather": {
      "temp_now": 31,
      "temp_high": 34,
      "rain_chance": 10,
      "condition": "hot",
      "available": true,
      "fetched_at": "2026-10-13T18:10:16+00:00",
      "city_label": "Lahore",
      "summary": "31° · hot · rain 10%",
      "headline": "It’s hot today. How about something light?"
    },
    "generated_at": "2026-10-13T18:10:21+00:00",
    "stage": "pick",
    "shuffles_left": 4,
    "shuffles_per_day": 5,
    "picks": [
      {
        "id": 3,
        "name": "Karela",
        "category": {
          "key": "vegetables",
          "name": "Vegetables"
        },
        "is_custom": false,
        "image_url": "http://localhost:8000/images/dishes/bhindi.png",
        "image_key": "bhindi",
        "image_bg": "#CFE6CF",
        "weather": [
          "hot"
        ],
        "effort": "medium",
        "cost": "low",
        "usual_meal": "both",
        "last_cooked_at": "2026-09-25",
        "days_since_cooked": 18,
        "removed": false,
        "reason": "Fits hot weather",
        "is_top": true
      },
      {
        "id": 13,
        "name": "Grandma’s Qeema Matar",
        "category": {
          "key": "beef",
          "name": "Beef"
        },
        "is_custom": true,
        "image_url": "http://localhost:8000/images/dishes/qeema.png",
        "image_key": "qeema",
        "image_bg": "#EBCDB9",
        "weather": [
          "any"
        ],
        "effort": "medium",
        "cost": "low",
        "usual_meal": "both",
        "last_cooked_at": "2026-10-08",
        "days_since_cooked": 5,
        "removed": false,
        "reason": "Fits hot weather",
        "is_top": false
      },
      {
        "id": 12,
        "name": "Mom’s Karhi",
        "category": {
          "key": "lentils",
          "name": "Lentils"
        },
        "is_custom": true,
        "image_url": "http://localhost:8000/images/dishes/lentils.png",
        "image_key": "lentils",
        "image_bg": "#F7D98C",
        "weather": [
          "rainy",
          "mild"
        ],
        "effort": "medium",
        "cost": "low",
        "usual_meal": "both",
        "last_cooked_at": "2026-09-18",
        "days_since_cooked": 25,
        "removed": false,
        "reason": "Not cooked in 25 days",
        "is_top": false
      }
    ],
    "plan": null,
    "log": null,
    "stats": {
      "streak_days": 6,
      "meals_this_month": 10
    },
    "repeat_gap_days": 5,
    "reminder": {
      "enabled": true,
      "time": "09:00"
    },
    "next_picks_at": "2026-10-14T09:00:00+05:00"
  }
}
```

### Cook this today

`PUT /api/v1/me/today/plan` · 🔒 token

Plans one of today's picks for lunch or dinner. Nothing is logged yet.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `kitchen_dish_id` | body | integer | yes |  |
| `meal` | body | string | yes | one of `lunch`, `dinner` |

**Example — 200**

```http
PUT /api/v1/me/today/plan

{
  "kitchen_dish_id": 3,
  "meal": "dinner"
}
```

```json
{
  "data": {
    "date": "2026-10-13",
    "greeting_name": "Hina",
    "initial": "H",
    "unread_notifications": 1,
    "weather": {
      "temp_now": 31,
      "temp_high": 34,
      "rain_chance": 10,
      "condition": "hot",
      "available": true,
      "fetched_at": "2026-10-13T18:10:16+00:00",
      "city_label": "Lahore",
      "summary": "31° · hot · rain 10%",
      "headline": "It’s hot today. How about something light?"
    },
    "generated_at": "2026-10-13T18:10:21+00:00",
    "stage": "planned",
    "shuffles_left": 4,
    "shuffles_per_day": 5,
    "picks": [
      {
        "id": 3,
        "name": "Karela",
        "category": {
          "key": "vegetables",
          "name": "Vegetables"
        },
        "is_custom": false,
        "image_url": "http://localhost:8000/images/dishes/bhindi.png",
        "image_key": "bhindi",
        "image_bg": "#CFE6CF",
        "weather": [
          "hot"
        ],
        "effort": "medium",
        "cost": "low",
        "usual_meal": "both",
        "last_cooked_at": "2026-09-25",
        "days_since_cooked": 18,
        "removed": false,
        "reason": "Fits hot weather",
        "is_top": true
      },
      {
        "id": 13,
        "name": "Grandma’s Qeema Matar",
        "category": {
          "key": "beef",
          "name": "Beef"
        },
        "is_custom": true,
        "image_url": "http://localhost:8000/images/dishes/qeema.png",
        "image_key": "qeema",
        "image_bg": "#EBCDB9",
        "weather": [
          "any"
        ],
        "effort": "medium",
        "cost": "low",
        "usual_meal": "both",
        "last_cooked_at": "2026-10-08",
        "days_since_cooked": 5,
        "removed": false,
        "reason": "Fits hot weather",
        "is_top": false
      },
      {
        "id": 12,
        "name": "Mom’s Karhi",
        "category": {
          "key": "lentils",
          "name": "Lentils"
        },
        "is_custom": true,
        "image_url": "http://localhost:8000/images/dishes/lentils.png",
        "image_key": "lentils",
        "image_bg": "#F7D98C",
        "weather": [
          "rainy",
          "mild"
        ],
        "effort": "medium",
        "cost": "low",
        "usual_meal": "both",
        "last_cooked_at": "2026-09-18",
        "days_since_cooked": 25,
        "removed": false,
        "reason": "Not cooked in 25 days",
        "is_top": false
      }
    ],
    "plan": {
      "dish": {
        "id": 3,
        "name": "Karela",
        "category": {
          "key": "vegetables",
          "name": "Vegetables"
        },
        "is_custom": false,
        "image_url": "http://localhost:8000/images/dishes/bhindi.png",
        "image_key": "bhindi",
        "image_bg": "#CFE6CF",
        "weather": [
          "hot"
        ],
        "effort": "medium",
        "cost": "low",
        "usual_meal": "both",
        "last_cooked_at": "2026-09-25",
        "days_since_cooked": 18,
        "removed": false
      },
      "meal": "dinner",
      "picked_at": "2026-10-13T18:10:21+00:00"
    },
    "log": null,
    "stats": {
      "streak_days": 6,
      "meals_this_month": 10
    },
    "repeat_gap_days": 5,
    "reminder": {
      "enabled": true,
      "time": "09:00"
    },
    "next_picks_at": "2026-10-14T09:00:00+05:00"
  }
}
```

### Back to picks

`DELETE /api/v1/me/today/plan` · 🔒 token

**Example — 200**

```http
DELETE /api/v1/me/today/plan
```

```json
{
  "data": {
    "date": "2026-10-13",
    "greeting_name": "Hina",
    "initial": "H",
    "unread_notifications": 1,
    "weather": {
      "temp_now": 31,
      "temp_high": 34,
      "rain_chance": 10,
      "condition": "hot",
      "available": true,
      "fetched_at": "2026-10-13T18:10:16+00:00",
      "city_label": "Lahore",
      "summary": "31° · hot · rain 10%",
      "headline": "It’s hot today. How about something light?"
    },
    "generated_at": "2026-10-13T18:10:21+00:00",
    "stage": "pick",
    "shuffles_left": 4,
    "shuffles_per_day": 5,
    "picks": [
      {
        "id": 3,
        "name": "Karela",
        "category": {
          "key": "vegetables",
          "name": "Vegetables"
        },
        "is_custom": false,
        "image_url": "http://localhost:8000/images/dishes/bhindi.png",
        "image_key": "bhindi",
        "image_bg": "#CFE6CF",
        "weather": [
          "hot"
        ],
        "effort": "medium",
        "cost": "low",
        "usual_meal": "both",
        "last_cooked_at": "2026-09-25",
        "days_since_cooked": 18,
        "removed": false,
        "reason": "Fits hot weather",
        "is_top": true
      },
      {
        "id": 13,
        "name": "Grandma’s Qeema Matar",
        "category": {
          "key": "beef",
          "name": "Beef"
        },
        "is_custom": true,
        "image_url": "http://localhost:8000/images/dishes/qeema.png",
        "image_key": "qeema",
        "image_bg": "#EBCDB9",
        "weather": [
          "any"
        ],
        "effort": "medium",
        "cost": "low",
        "usual_meal": "both",
        "last_cooked_at": "2026-10-08",
        "days_since_cooked": 5,
        "removed": false,
        "reason": "Fits hot weather",
        "is_top": false
      },
      {
        "id": 12,
        "name": "Mom’s Karhi",
        "category": {
          "key": "lentils",
          "name": "Lentils"
        },
        "is_custom": true,
        "image_url": "http://localhost:8000/images/dishes/lentils.png",
        "image_key": "lentils",
        "image_bg": "#F7D98C",
        "weather": [
          "rainy",
          "mild"
        ],
        "effort": "medium",
        "cost": "low",
        "usual_meal": "both",
        "last_cooked_at": "2026-09-18",
        "days_since_cooked": 25,
        "removed": false,
        "reason": "Not cooked in 25 days",
        "is_top": false
      }
    ],
    "plan": null,
    "log": null,
    "stats": {
      "streak_days": 6,
      "meals_this_month": 10
    },
    "repeat_gap_days": 5,
    "reminder": {
      "enabled": true,
      "time": "09:00"
    },
    "next_picks_at": "2026-10-14T09:00:00+05:00"
  }
}
```

## My dishes

Dishes in the user's kitchen. Library dishes can have their weather/effort/cost overridden per user.

### My dishes

`GET /api/v1/me/dishes` · 🔒 token

Filter by `q` (name) and `category` key; `custom=1` for custom only.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `q` | query | string |  |  |
| `category` | query | string |  |  |
| `custom` | query | boolean |  |  |

**Example — 200**

```http
GET /api/v1/me/dishes?q=karahi
```

```json
{
  "data": [
    {
      "id": 6,
      "name": "Chicken Karahi",
      "category": {
        "key": "chicken",
        "name": "Chicken"
      },
      "is_custom": false,
      "image_url": "http://localhost:8000/images/dishes/karahi.png",
      "image_key": "karahi",
      "image_bg": "#F6C2AE",
      "weather": [
        "any"
      ],
      "effort": "medium",
      "cost": "medium",
      "usual_meal": "dinner",
      "last_cooked_at": null,
      "days_since_cooked": null,
      "removed": false
    }
  ],
  "meta": {
    "total": 14,
    "custom_count": 3,
    "matches": 1
  }
}
```

### Add library dishes

`POST /api/v1/me/dishes` · 🔒 token

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `library_dish_ids` | body | integer[] | yes | min items 1 |

**Example — 201**

```http
POST /api/v1/me/dishes

{
  "library_dish_ids": [
    3,
    5
  ]
}
```

```json
{
  "data": {
    "added": 2
  }
}
```

### Dish detail

`GET /api/v1/me/dishes/{id}` · 🔒 token

Includes library defaults, overrides, last cooked and when it can be suggested again.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | integer | yes |  |

**Example — 200**

```http
GET /api/v1/me/dishes/2
```

```json
{
  "data": {
    "id": 2,
    "name": "Aloo Gobi",
    "category": {
      "key": "vegetables",
      "name": "Vegetables"
    },
    "is_custom": false,
    "image_url": "http://localhost:8000/images/dishes/aloogobi.png",
    "image_key": "aloogobi",
    "image_bg": "#E4EBC4",
    "weather": [
      "cold",
      "mild"
    ],
    "effort": "quick",
    "cost": "low",
    "usual_meal": "both",
    "last_cooked_at": "2026-10-02",
    "days_since_cooked": 11,
    "removed": false,
    "library_dish_id": 2,
    "note": "Winter staple",
    "has_overrides": false,
    "library_defaults": {
      "weather": [
        "cold",
        "mild"
      ],
      "effort": "quick",
      "cost": "low"
    },
    "next_eligible_at": null,
    "photo_status": "none",
    "has_photo": false
  }
}
```

### Change weather, effort or cost

`PATCH /api/v1/me/dishes/{id}` · 🔒 token

For library dishes this stores your own tags; "Reset to library tags" undoes it.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | integer | yes |  |
| `weather` | body | array |  | min items 1 |
| `effort` | body | string |  | one of `quick`, `medium`, `heavy` |
| `cost` | body | string |  | one of `low`, `medium`, `high` |

**Example — 200**

```http
PATCH /api/v1/me/dishes/2

{
  "weather": [
    "cold",
    "rainy"
  ],
  "effort": "quick",
  "cost": "low"
}
```

```json
{
  "data": {
    "id": 2,
    "name": "Aloo Gobi",
    "category": {
      "key": "vegetables",
      "name": "Vegetables"
    },
    "is_custom": false,
    "image_url": "http://localhost:8000/images/dishes/aloogobi.png",
    "image_key": "aloogobi",
    "image_bg": "#E4EBC4",
    "weather": [
      "cold",
      "rainy"
    ],
    "effort": "quick",
    "cost": "low",
    "usual_meal": "both",
    "last_cooked_at": "2026-10-02",
    "days_since_cooked": 11,
    "removed": false,
    "library_dish_id": 2,
    "note": "Winter staple",
    "has_overrides": true,
    "library_defaults": {
      "weather": [
        "cold",
        "mild"
      ],
      "effort": "quick",
      "cost": "low"
    },
    "next_eligible_at": null,
    "photo_status": "none",
    "has_photo": false
  }
}
```

### Remove from my dishes

`DELETE /api/v1/me/dishes/{id}` · 🔒 token

It won't be suggested anymore; past history stays. Undo with `POST /me/dishes/{id}/restore`.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | integer | yes |  |

**Example — 204**

```http
DELETE /api/v1/me/dishes/2
```

_No content._

### Reset to library tags

`DELETE /api/v1/me/dishes/{id}/overrides` · 🔒 token

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | integer | yes |  |

**Example — 200**

```http
DELETE /api/v1/me/dishes/2/overrides
```

```json
{
  "data": {
    "id": 2,
    "name": "Aloo Gobi",
    "category": {
      "key": "vegetables",
      "name": "Vegetables"
    },
    "is_custom": false,
    "image_url": "http://localhost:8000/images/dishes/aloogobi.png",
    "image_key": "aloogobi",
    "image_bg": "#E4EBC4",
    "weather": [
      "cold",
      "mild"
    ],
    "effort": "quick",
    "cost": "low",
    "usual_meal": "both",
    "last_cooked_at": "2026-10-02",
    "days_since_cooked": 11,
    "removed": false,
    "library_dish_id": 2,
    "note": "Winter staple",
    "has_overrides": false,
    "library_defaults": {
      "weather": [
        "cold",
        "mild"
      ],
      "effort": "quick",
      "cost": "low"
    },
    "next_eligible_at": null,
    "photo_status": "none",
    "has_photo": false
  }
}
```

### Add or change photo

`POST /api/v1/me/dishes/{id}/photo` · 🔒 token

Multipart field `photo`, image up to 10 MB. Adds a "Photo added" notification.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | integer | yes |  |
| `photo` | body | string | yes | Maximum file size: 10240 kilobytes. |

**Example — 200**

```http
POST /api/v1/me/dishes/2/photo
Content-Type: multipart/form-data

photo: <file photo.png>
```

```json
{
  "data": {
    "id": 2,
    "name": "Aloo Gobi",
    "category": {
      "key": "vegetables",
      "name": "Vegetables"
    },
    "is_custom": false,
    "image_url": "http://localhost:8000/storage/kitchen-photos/2/40539381-0d0e-476a-b772-c8c4abe20c4b.png",
    "image_key": "aloogobi",
    "image_bg": "#E4EBC4",
    "weather": [
      "cold",
      "mild"
    ],
    "effort": "quick",
    "cost": "low",
    "usual_meal": "both",
    "last_cooked_at": "2026-10-02",
    "days_since_cooked": 11,
    "removed": false,
    "library_dish_id": 2,
    "note": "Winter staple",
    "has_overrides": false,
    "library_defaults": {
      "weather": [
        "cold",
        "mild"
      ],
      "effort": "quick",
      "cost": "low"
    },
    "next_eligible_at": null,
    "photo_status": "ready",
    "has_photo": true
  }
}
```

### Undo remove

`POST /api/v1/me/dishes/{id}/restore` · 🔒 token

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | integer | yes |  |

**Example — 200**

```http
POST /api/v1/me/dishes/2/restore
```

```json
{
  "data": {
    "id": 2,
    "name": "Aloo Gobi",
    "category": {
      "key": "vegetables",
      "name": "Vegetables"
    },
    "is_custom": false,
    "image_url": "http://localhost:8000/storage/kitchen-photos/2/40539381-0d0e-476a-b772-c8c4abe20c4b.png",
    "image_key": "aloogobi",
    "image_bg": "#E4EBC4",
    "weather": [
      "cold",
      "mild"
    ],
    "effort": "quick",
    "cost": "low",
    "usual_meal": "both",
    "last_cooked_at": "2026-10-02",
    "days_since_cooked": 11,
    "removed": false,
    "library_dish_id": 2,
    "note": "Winter staple",
    "has_overrides": false,
    "library_defaults": {
      "weather": [
        "cold",
        "mild"
      ],
      "effort": "quick",
      "cost": "low"
    },
    "next_eligible_at": null,
    "photo_status": "ready",
    "has_photo": true
  }
}
```

## My dishes · custom

Family recipes not in the library. Send `multipart/form-data` when attaching a photo.

### My custom dishes

`GET /api/v1/me/custom-dishes` · 🔒 token

**Example — 200**

```http
GET /api/v1/me/custom-dishes
```

```json
{
  "data": [
    {
      "id": 14,
      "name": "Masala Anda Paratha",
      "category": {
        "key": "breakfast",
        "name": "Breakfast"
      },
      "is_custom": true,
      "image_url": "http://localhost:8000/images/dishes/paratha.png",
      "image_key": "paratha",
      "image_bg": "#FFE3A6",
      "weather": [
        "cold",
        "rainy"
      ],
      "effort": "medium",
      "cost": "low",
      "usual_meal": "breakfast",
      "last_cooked_at": null,
      "days_since_cooked": null,
      "removed": false
    },
    {
      "id": 13,
      "name": "Grandma’s Qeema Matar",
      "category": {
        "key": "beef",
        "name": "Beef"
      },
      "is_custom": true,
      "image_url": "http://localhost:8000/images/dishes/qeema.png",
      "image_key": "qeema",
      "image_bg": "#EBCDB9",
      "weather": [
        "any"
      ],
      "effort": "medium",
      "cost": "low",
      "usual_meal": "both",
      "last_cooked_at": "2026-10-08",
      "days_since_cooked": 5,
      "removed": false
    },
    {
      "id": 12,
      "name": "Mom’s Karhi",
      "category": {
        "key": "lentils",
        "name": "Lentils"
      },
      "is_custom": true,
      "image_url": "http://localhost:8000/images/dishes/lentils.png",
      "image_key": "lentils",
      "image_bg": "#F7D98C",
      "weather": [
        "rainy",
        "mild"
      ],
      "effort": "medium",
      "cost": "low",
      "usual_meal": "both",
      "last_cooked_at": "2026-09-18",
      "days_since_cooked": 25,
      "removed": false
    }
  ]
}
```

### Add custom dish

`POST /api/v1/me/custom-dishes` · 🔒 token

JSON or multipart (with optional `photo` up to 10 MB). `weather` is an array, e.g. ["rainy","mild"] or ["any"].

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `name` | body | string | yes | 2–40 letters, spaces, apostrophes, ( ) and - |
| `category` | body | string | yes | Category key (see GET /categories) |
| `weather` | body | array | yes | One or more of hot, mild, cold, rainy — or just any. Multipart: weather[]=cold&weather[]=rainy |
| `usual_meal` | body | string |  | breakfast | lunch | dinner | both (default both) |
| `effort` | body | string |  | quick | medium | heavy (default medium) |
| `cost` | body | string |  | low | medium | high (default low) |
| `photo` | body | string |  | Optional image up to 10 MB (send as multipart/form-data) |

**Example — 201**

```http
POST /api/v1/me/custom-dishes
Content-Type: multipart/form-data

name: Aunt Sara’s Haleem
category: beef
weather: ['cold', 'rainy']
usual_meal: dinner
effort: heavy
cost: medium
photo: <file haleem.png>
```

```json
{
  "data": {
    "id": 30,
    "name": "Aunt Sara’s Haleem",
    "category": {
      "key": "beef",
      "name": "Beef"
    },
    "is_custom": true,
    "image_url": "http://localhost:8000/storage/kitchen-photos/2/b4a5a369-b15b-4eb2-8a4d-ce4c2bedd16a.png",
    "image_key": "qeema",
    "image_bg": "#EBCDB9",
    "weather": [
      "cold",
      "rainy"
    ],
    "effort": "heavy",
    "cost": "medium",
    "usual_meal": "dinner",
    "last_cooked_at": null,
    "days_since_cooked": null,
    "removed": false,
    "library_dish_id": null,
    "note": null,
    "has_overrides": false,
    "library_defaults": null,
    "next_eligible_at": null,
    "photo_status": "ready",
    "has_photo": true
  }
}
```

**Example — 422**

```http
POST /api/v1/me/custom-dishes

{
  "name": "Biryani",
  "category": "rice",
  "weather": [
    "any"
  ]
}
```

```json
{
  "message": "“Biryani” is already in your dishes.",
  "errors": {
    "name": [
      "“Biryani” is already in your dishes."
    ]
  }
}
```

### Edit custom dish

`PATCH /api/v1/me/custom-dishes/{id}` · 🔒 token

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | integer | yes |  |
| `name` | body | string |  | 2–40 letters, spaces, apostrophes, ( ) and - |
| `category` | body | string |  | Category key (see GET /categories) |
| `weather` | body | array |  | One or more of hot, mild, cold, rainy — or just any. Multipart: weather[]=cold&weather[]=rainy |
| `usual_meal` | body | string |  | breakfast | lunch | dinner | both (default both) |
| `effort` | body | string |  | quick | medium | heavy (default medium) |
| `cost` | body | string |  | low | medium | high (default low) |

**Example — 200**

```http
PATCH /api/v1/me/custom-dishes/30

{
  "name": "Aunt Sara’s Beef Haleem",
  "cost": "high"
}
```

```json
{
  "data": {
    "id": 30,
    "name": "Aunt Sara’s Beef Haleem",
    "category": {
      "key": "beef",
      "name": "Beef"
    },
    "is_custom": true,
    "image_url": "http://localhost:8000/storage/kitchen-photos/2/b4a5a369-b15b-4eb2-8a4d-ce4c2bedd16a.png",
    "image_key": "qeema",
    "image_bg": "#EBCDB9",
    "weather": [
      "cold",
      "rainy"
    ],
    "effort": "heavy",
    "cost": "high",
    "usual_meal": "dinner",
    "last_cooked_at": null,
    "days_since_cooked": null,
    "removed": false,
    "library_dish_id": null,
    "note": null,
    "has_overrides": false,
    "library_defaults": null,
    "next_eligible_at": null,
    "photo_status": "ready",
    "has_photo": true
  }
}
```

### Remove custom dish

`DELETE /api/v1/me/custom-dishes/{id}` · 🔒 token

Past meals with this dish stay in history. Undo with restore.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | integer | yes |  |

**Example — 204**

```http
DELETE /api/v1/me/custom-dishes/30
```

_No content._

### Undo remove

`POST /api/v1/me/custom-dishes/{id}/restore` · 🔒 token

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | integer | yes |  |

**Example — 200**

```http
POST /api/v1/me/custom-dishes/30/restore
```

```json
{
  "data": {
    "id": 30,
    "name": "Aunt Sara’s Beef Haleem",
    "category": {
      "key": "beef",
      "name": "Beef"
    },
    "is_custom": true,
    "image_url": "http://localhost:8000/storage/kitchen-photos/2/b4a5a369-b15b-4eb2-8a4d-ce4c2bedd16a.png",
    "image_key": "qeema",
    "image_bg": "#EBCDB9",
    "weather": [
      "cold",
      "rainy"
    ],
    "effort": "heavy",
    "cost": "high",
    "usual_meal": "dinner",
    "last_cooked_at": null,
    "days_since_cooked": null,
    "removed": false,
    "library_dish_id": null,
    "note": null,
    "has_overrides": false,
    "library_defaults": null,
    "next_eligible_at": null,
    "photo_status": "ready",
    "has_photo": true
  }
}
```

## History

Meal logs (last 60 days editable), monthly history, stats and the offline sync queue.

### History for a month

`GET /api/v1/me/meals` · 🔒 token

`month` = YYYY-MM (default: this month). Meals grouped by day, plus "{n} of {days} days logged".

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `month` | query | string |  |  |

**Example — 200**

```http
GET /api/v1/me/meals?month=2026-10
```

```json
{
  "data": {
    "month": "2026-10",
    "logged_days": 11,
    "days_elapsed": 13,
    "min_date": "2026-08-14",
    "max_date": "2026-10-13",
    "days": {
      "2026-10-13": [
        {
          "id": "0b471451-6dd5-4ef0-9933-13bb5ee12d9d",
          "kitchen_dish_id": 3,
          "dish_name": "Karela",
          "category_key": "vegetables",
          "image_url": "http://localhost:8000/images/dishes/bhindi.png",
          "image_key": "bhindi",
          "image_bg": "#CFE6CF",
          "date": "2026-10-13",
          "meal": "lunch",
          "source": "suggestion",
          "logged_at": "2026-10-13T18:10:21+00:00"
        }
      ],
      "2026-10-12": [
        {
          "id": "01a13675-2e6b-701a-b5cb-db3a0a1c679c",
          "kitchen_dish_id": 11,
          "dish_name": "Anda Paratha",
          "category_key": "breakfast",
          "image_url": "http://localhost:8000/images/dishes/paratha.png",
          "image_key": "paratha",
          "image_bg": "#FFE3A6",
          "date": "2026-10-12",
          "meal": "lunch",
          "source": "suggestion",
          "logged_at": "2026-10-12T20:15:00+00:00"
        }
      ],
      "…": "9 more days"
    }
  }
}
```

### Log a meal / Mark as cooked

`POST /api/v1/me/meals` · 🔒 token

`id` may be a client UUID (offline queue) — sending the same id twice is safe.
If the same dish is already logged for that date and meal, the existing entry is kept:
200 with `meta.duplicate = true`.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | body | string |  | Optional client UUID (offline queue) — resending the same id is safe; uuid |
| `kitchen_dish_id` | body | integer | yes | One of your dishes (GET /me/dishes) |
| `date` | body | string | yes | Y-m-d, today or up to 60 days back; date |
| `meal` | body | string | yes | breakfast | lunch | dinner |
| `source` | body | string |  | suggestion (from Today) or manual (default) |

**Example — 201**

```http
POST /api/v1/me/meals

{
  "id": "0b471451-6dd5-4ef0-9933-13bb5ee12d9d",
  "kitchen_dish_id": 3,
  "date": "2026-10-13",
  "meal": "dinner",
  "source": "suggestion"
}
```

```json
{
  "data": {
    "id": "0b471451-6dd5-4ef0-9933-13bb5ee12d9d",
    "kitchen_dish_id": 3,
    "dish_name": "Karela",
    "category_key": "vegetables",
    "image_url": "http://localhost:8000/images/dishes/bhindi.png",
    "image_key": "bhindi",
    "image_bg": "#CFE6CF",
    "date": "2026-10-13",
    "meal": "dinner",
    "source": "suggestion",
    "logged_at": "2026-10-13T18:10:21+00:00"
  },
  "meta": {
    "duplicate": false
  }
}
```

**Example — 200**

```http
POST /api/v1/me/meals

{
  "kitchen_dish_id": 3,
  "date": "2026-10-13",
  "meal": "dinner"
}
```

```json
{
  "data": {
    "id": "0b471451-6dd5-4ef0-9933-13bb5ee12d9d",
    "kitchen_dish_id": 3,
    "dish_name": "Karela",
    "category_key": "vegetables",
    "image_url": "http://localhost:8000/images/dishes/bhindi.png",
    "image_key": "bhindi",
    "image_bg": "#CFE6CF",
    "date": "2026-10-13",
    "meal": "dinner",
    "source": "suggestion",
    "logged_at": "2026-10-13T18:10:21+00:00"
  },
  "meta": {
    "duplicate": true
  }
}
```

### Edit a logged meal

`PATCH /api/v1/me/meals/{id}` · 🔒 token

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | string | yes |  |
| `kitchen_dish_id` | body | integer |  | One of your dishes (GET /me/dishes) |
| `date` | body | string |  | Y-m-d, today or up to 60 days back; date |
| `meal` | body | string |  | breakfast | lunch | dinner |

**Example — 200**

```http
PATCH /api/v1/me/meals/0b471451-6dd5-4ef0-9933-13bb5ee12d9d

{
  "meal": "lunch"
}
```

```json
{
  "data": {
    "id": "0b471451-6dd5-4ef0-9933-13bb5ee12d9d",
    "kitchen_dish_id": 3,
    "dish_name": "Karela",
    "category_key": "vegetables",
    "image_url": "http://localhost:8000/images/dishes/bhindi.png",
    "image_key": "bhindi",
    "image_bg": "#CFE6CF",
    "date": "2026-10-13",
    "meal": "lunch",
    "source": "suggestion",
    "logged_at": "2026-10-13T18:10:21+00:00"
  },
  "meta": {
    "duplicate": false
  }
}
```

### Delete a logged meal

`DELETE /api/v1/me/meals/{id}` · 🔒 token

Undo with restore within the snackbar window.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | string | yes |  |

**Example — 204**

```http
DELETE /api/v1/me/meals/0b471451-6dd5-4ef0-9933-13bb5ee12d9d
```

_No content._

### Undo delete

`POST /api/v1/me/meals/{id}/restore` · 🔒 token

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | string | yes |  |

**Example — 200**

```http
POST /api/v1/me/meals/0b471451-6dd5-4ef0-9933-13bb5ee12d9d/restore
```

```json
{
  "data": {
    "id": "0b471451-6dd5-4ef0-9933-13bb5ee12d9d",
    "kitchen_dish_id": 3,
    "dish_name": "Karela",
    "category_key": "vegetables",
    "image_url": "http://localhost:8000/images/dishes/bhindi.png",
    "image_key": "bhindi",
    "image_bg": "#CFE6CF",
    "date": "2026-10-13",
    "meal": "lunch",
    "source": "suggestion",
    "logged_at": "2026-10-13T18:10:21+00:00"
  },
  "meta": {
    "duplicate": false
  }
}
```

### Stats

`GET /api/v1/me/stats` · 🔒 token

Days logged in a row and meals this month.

**Example — 200**

```http
GET /api/v1/me/stats
```

```json
{
  "data": {
    "streak_days": 7,
    "meals_this_month": 11
  }
}
```

### Sync offline changes

`POST /api/v1/me/sync` · 🔒 token

Replays meals logged or deleted while offline. Each operation:
`{op: "meal.create", payload: {id, kitchen_dish_id, date, meal, source}}` or `{op: "meal.delete", payload: {id}}`.
Results come back in the same order.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `operations` | body | object[] | yes | min items 1; max items 200 |

**Example — 200**

```http
POST /api/v1/me/sync

{
  "operations": [
    {
      "op": "meal.create",
      "payload": {
        "id": "11e74bb4-ced8-4fa4-895f-7dc4f78ee0b8",
        "kitchen_dish_id": 2,
        "date": "2026-10-12",
        "meal": "lunch",
        "source": "manual"
      }
    },
    {
      "op": "meal.delete",
      "payload": {
        "id": "0b471451-6dd5-4ef0-9933-13bb5ee12d9d"
      }
    }
  ]
}
```

```json
{
  "data": {
    "results": [
      {
        "ok": true,
        "id": "11e74bb4-ced8-4fa4-895f-7dc4f78ee0b8",
        "duplicate": false
      },
      {
        "ok": true,
        "id": "0b471451-6dd5-4ef0-9933-13bb5ee12d9d"
      }
    ],
    "synced_at": "2026-10-13T18:10:22+00:00"
  }
}
```

## Notifications

In-app notification center, the daily reminder settings and a test push.

### Register this phone for push

`POST /api/v1/me/devices` · 🔒 token

Upserts by `device_id` and links it to this login, so logging out stops reminders on this phone.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `device_id` | body | string | yes | max length 100 |
| `expo_push_token` | body | string |  | max length 255 |
| `platform` | body | string | yes | one of `android`, `ios` |
| `name` | body | string |  | max length 100 |

**Example — 201**

```http
POST /api/v1/me/devices

{
  "device_id": "postman-device-1",
  "platform": "android",
  "name": "Pixel 8",
  "expo_push_token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]"
}
```

```json
{
  "data": {
    "registered": true,
    "id": 1
  }
}
```

### Remove a phone

`DELETE /api/v1/me/devices/{deviceId}` · 🔒 token

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `deviceId` | path | string | yes |  |

**Example — 204**

```http
DELETE /api/v1/me/devices/postman-device-1
```

_No content._

### Notifications

`GET /api/v1/me/notifications` · 🔒 token

Newest first. `filter` = all | unread.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `filter` | query | string |  | One of: `all`, `unread`, `None` |

**Example — 200**

```http
GET /api/v1/me/notifications?filter=all
```

```json
{
  "data": [
    {
      "id": 6,
      "type": "sync",
      "title": "1 meal synced",
      "body": "The meals you logged offline are now saved to your account.",
      "cta_label": "See history",
      "deep_link": "/history",
      "image_url": null,
      "image_key": null,
      "image_bg": null,
      "read_at": null,
      "created_at": "2026-10-13T18:10:22+00:00"
    },
    {
      "id": 5,
      "type": "photo_ready",
      "title": "Photo added",
      "body": "Your photo of Aunt Sara’s Haleem is ready and now shows in suggestions.",
      "cta_label": "View dish",
      "deep_link": "/dishes/30",
      "image_url": "http://localhost:8000/images/dishes/qeema.png",
      "image_key": "qeema",
      "image_bg": "#EBCDB9",
      "read_at": null,
      "created_at": "2026-10-13T18:10:21+00:00"
    },
    "… 3 more"
  ],
  "meta": {
    "unread_count": 4
  }
}
```

### Mark all as read

`POST /api/v1/me/notifications/read-all` · 🔒 token

**Example — 204**

```http
POST /api/v1/me/notifications/read-all
```

_No content._

### Mark as read

`PATCH /api/v1/me/notifications/{id}` · 🔒 token

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | integer | yes |  |
| `read` | body | boolean | yes |  |

**Example — 200**

```http
PATCH /api/v1/me/notifications/6

{
  "read": true
}
```

```json
{
  "data": {
    "id": 6,
    "type": "sync",
    "title": "1 meal synced",
    "body": "The meals you logged offline are now saved to your account.",
    "cta_label": "See history",
    "deep_link": "/history",
    "image_url": null,
    "image_key": null,
    "image_bg": null,
    "read_at": "2026-10-13T18:10:22+00:00",
    "created_at": "2026-10-13T18:10:22+00:00"
  }
}
```

### Clear

`DELETE /api/v1/me/notifications/{id}` · 🔒 token

Undo with restore.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | integer | yes |  |

**Example — 204**

```http
DELETE /api/v1/me/notifications/6
```

_No content._

### Undo clear

`POST /api/v1/me/notifications/{id}/restore` · 🔒 token

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `id` | path | integer | yes |  |

**Example — 200**

```http
POST /api/v1/me/notifications/6/restore
```

```json
{
  "data": {
    "id": 6,
    "type": "sync",
    "title": "1 meal synced",
    "body": "The meals you logged offline are now saved to your account.",
    "cta_label": "See history",
    "deep_link": "/history",
    "image_url": null,
    "image_key": null,
    "image_bg": null,
    "read_at": "2026-10-13T18:10:22+00:00",
    "created_at": "2026-10-13T18:10:22+00:00"
  }
}
```

### Notification settings

`GET /api/v1/me/notification-settings` · 🔒 token

**Example — 200**

```http
GET /api/v1/me/notification-settings
```

```json
{
  "data": {
    "enabled": true,
    "time": "09:00"
  }
}
```

### Update notification settings

`PUT /api/v1/me/notification-settings` · 🔒 token

`time` is "HH:mm" between 05:00 and 13:00.

| Field | In | Type | Required | Notes |
|---|---|---|---|---|
| `enabled` | body | boolean | yes |  |
| `time` | body | string | yes |  |

**Example — 200**

```http
PUT /api/v1/me/notification-settings

{
  "enabled": true,
  "time": "09:00"
}
```

```json
{
  "data": {
    "enabled": true,
    "time": "09:00"
  }
}
```

### Send a test notification

`POST /api/v1/me/notification-settings/test` · 🔒 token

**Example — 202**

```http
POST /api/v1/me/notification-settings/test
```

```json
{
  "data": {
    "sent": 1,
    "devices": 1
  }
}
```
