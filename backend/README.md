# Handi backend

Laravel 12 API (`/api/v1`) and Filament 4 super-admin panel (`/admin`), sharing one MySQL database.

- Written API guide: [`../docs/api.md`](../docs/api.md)
- Interactive API docs (OpenAPI, try-it-out): **http://localhost:8000/docs/api** · raw spec: `/docs/api.json`
- Postman: import [`../docs/handi-api.postman_collection.json`](../docs/handi-api.postman_collection.json)
- Database: [`../docs/database.md`](../docs/database.md)

## Requirements

- **PHP 8.2+** (Laravel 12) with `intl`, `zip`, `pdo_mysql`, `pdo_sqlite`, `mbstring`, `fileinfo`, `gd`
- Composer 2, MySQL 8

On Windows, [Laravel Herd](https://herd.laravel.com) or Laragon gives you all of these. In Laragon, enable `intl` and `zip` under *PHP → Extensions*.

## Setup

```bash
cd backend
composer install                    # or `composer update` after pulling new packages
copy .env.example .env              # first time only, then fill DB_*, ADMIN_*, optional DEMO_USER_*
php artisan key:generate            # first time only
php artisan migrate:fresh --seed    # tables + 9 categories + library dishes + 23 cities + super admin (+ demo user)
php artisan storage:link            # makes uploaded dish photos reachable by URL
```

Every setting and secret lives in `.env` (git-ignored); `.env.example` lists each key with a comment. The super admin comes from `ADMIN_EMAIL` / `ADMIN_PASSWORD`; add more with `php artisan handi:create-admin someone@example.com`.

Set `DEMO_USER_EMAIL` / `DEMO_USER_PASSWORD` locally to seed a demo app user (Hina, Lahore) with a full kitchen, custom dishes, two weeks of meals and notifications — handy for Postman and for the app.

## Running

```bash
php artisan serve --host=0.0.0.0 --port=8000   # reachable from your phone on the same Wi-Fi
php artisan schedule:work                       # sends each user's morning reminder at their chosen time
```

- Admin panel: http://localhost:8000/admin
- API docs: http://localhost:8000/docs/api (open locally; super admins only when `APP_ENV` isn't `local`)
- Quick check: `curl http://localhost:8000/api/v1/categories`

## Dish photos (Spatie Media Library)

Photos of library dishes (admin) and users' dishes (app) are stored with [Spatie Media Library](https://spatie.be/docs/laravel-medialibrary) on `MEDIA_DISK` (`public` locally, `s3`/Cloudflare R2 in production):

- One photo per dish; a new upload replaces the old file and its sizes.
- The original is capped at **1600 px / JPEG 85** before it's stored, and two WebP sizes are made: `thumb` (240×240, lists) and `card` (≤ 900 px, cards and the dish page). The API returns `thumb_url` and `image_url` (card).
- `MEDIA_QUEUE_CONVERSIONS=false` (default) makes the sizes right away; set it to `true` in production with `php artisan queue:work` running to make them in the background.
- Photos uploaded before the media library: run `php artisan handi:photos-to-media` once to move them in (adds the sizes, removes the old files).

## Telescope (debugging)

[Laravel Telescope](https://laravel.com/docs/telescope) shows every request, query, exception, log, mail, job and scheduled task at **http://localhost:8000/telescope** (also linked in the admin sidebar under *Developer*).

```bash
composer require laravel/telescope   # once
php artisan migrate                  # creates the telescope_entries tables
```

- `TELESCOPE_ENABLED=true` in `.env` turns it on (it's off when unset, in tests and in `.env.production.example`).
- Locally it records everything and opens without login. On any other environment it records only problems and only signed-in super admins can open it (sign in at `/admin` first).
- Passwords, tokens and reset codes are masked; entries older than 48 hours are pruned daily by the scheduler.

## Tests

```bash
php artisan test
```

70 tests on in-memory SQLite (needs `pdo_sqlite`). Covers every endpoint, the pick rules, offline sync, reminders, the API docs gate and every admin page (users, user detail tabs, activity log, library, dish import, image preview). Weather and Expo push are faked — tests never hit the network.

## How it fits together

| Piece | Where |
|---|---|
| Routes | `routes/api.php` (public → auth → signed-in `me/*`) |
| Controllers | `app/Http/Controllers/Api/V1` — docblocks feed the OpenAPI docs |
| JSON shapes | `app/Http/Resources` |
| Validation copy | `app/Support/Rules.php` — the exact error messages the app shows |
| Daily picks | `app/Services/PickService.php` (weather fit, repeat gap, 3 distinct categories, 5 shuffles/day) |
| Weather | `app/Services/WeatherService.php` (Open-Meteo, cached per ~1 km) |
| Kitchen | `app/Services/KitchenService.php` (location, adding dishes, custom dishes) |
| Codes by email | `app/Services/AuthCodeService.php` (reset password, change email) |
| Push + inbox | `NotificationService` + `ExpoPushService` |
| Reminders | `reminders:dispatch`, every minute via `routes/console.php` |
| Activity log | `App\Support\Activity::log()` → `activity_logs` (shown in the admin) |
| Admin panel | `app/Filament` — Overview widgets, Users (full detail + dishes, meals, notifications, phones, logins, activity), Activity log, Library dishes (with CSV/Excel import), Categories, Cities |
| Dish import | `app/Services/DishImporter.php` (CSV/XLSX via OpenSpout) · action on `Dishes/Pages/ListDishes.php` |
| Admin look | `public/css/handi-admin.css`, Poppins in `public/fonts/handi` + `public/css/handi-fonts.css`, `app/Filament/Support` (colors, avatars, image preview, event labels) |
| Settings | `config/handi.php`, `config/legal.php` |
| Seed content | `database/seeders/data/library.php`, `CatalogSeeder`, `DemoSeeder` |

**Admin edits reach the app immediately** — catalog models flush a versioned cache on save/delete (`app/Support/CatalogCache.php`).

**Importing dishes.** In the admin, go to *Library dishes → Import*. *Download template* gives a CSV with the expected columns (`name`, `category`, `weather` required; `effort`, `cost`, `usual_meal`, `note`, `description`, `image_key`, `active` optional). *Import dishes* accepts `.csv` or `.xlsx` (up to 2,000 rows). Dishes are matched by name and either updated or skipped. Bad rows are listed with the reason, and each import is logged.

**After pulling changes,** run `php artisan optimize:clear` and hard-refresh the admin.

**Dish art.** Library dishes without an uploaded photo use the design's illustrations in `public/images/dishes/{key}.png`; the API returns `image_url` plus `image_bg` (the tint behind it).
