# Handi backend

Laravel 12 API (`/api/v1`) and Filament 4 super-admin panel (`/admin`), sharing one MySQL database.

- Written API guide: [`../docs/api.md`](../docs/api.md)
- Interactive API docs (OpenAPI, try-it-out): **http://localhost:8000/docs/api** · raw spec: `/docs/api.json`
- Postman: import [`../docs/handi-api.postman_collection.json`](../docs/handi-api.postman_collection.json)
- Database: [`../docs/database.md`](../docs/database.md)

## Requirements

| Tool | Version | Notes |
|---|---|---|
| PHP | **8.2 or newer** | Extensions: `intl`, `zip`, `gd`, `exif`, `mbstring`, `fileinfo`, `pdo_mysql` (+ `pdo_sqlite` for tests) |
| Composer | 2.x | |
| MySQL / MariaDB | MySQL 8 or MariaDB 10.6+ | Any local server (Herd Pro, Laragon, XAMPP, DBngin, Docker) |
| Node | not needed | The admin uses plain CSS — no `npm install` / Vite build for the backend |

**Windows:** [Laravel Herd](https://herd.laravel.com) or Laragon gives you PHP, Composer and MySQL. In Laragon enable `intl`, `zip`, `gd` and `exif` under *PHP → Extensions*. Check with `php -v` and `php -m`.

> If your system PHP is older than 8.2 but Herd has a newer one, prefix the commands with Herd's PHP, e.g. `herd php artisan migrate` and `herd composer install`.

## Setup (first time)

**1. Install packages**

```bash
cd backend
composer install
```

> Use `composer install` (it follows `composer.lock`). Don't run `composer require filament/filament` or `php artisan filament:install --panels` — Filament is already installed and those commands break the lock file.

**2. Create `.env`**

```bash
copy .env.example .env      # Windows (cmd / PowerShell)
cp .env.example .env        # macOS / Linux / Git Bash
php artisan key:generate
```

Then open `.env` and fill in at least:

```dotenv
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=handi
DB_USERNAME=root
DB_PASSWORD=

ADMIN_EMAIL=admin@example.com       # super admin for /admin
ADMIN_PASSWORD=change-me-please

# Optional: a demo app user with a full kitchen and two weeks of meals
DEMO_USER_EMAIL=hina@example.com
DEMO_USER_PASSWORD=kitchen123
```

`.env` is git-ignored; never commit it. Every other key is explained in `.env.example`.

**3. Create the database**

Create an empty database called `handi` (utf8mb4) in your MySQL tool, or:

```bash
mysql -u root -e "CREATE DATABASE handi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**4. Tables, seed data and storage**

```bash
php artisan migrate --seed     # tables + 9 categories, dish library, 23 cities, super admin (+ demo user)
php artisan storage:link       # makes uploaded photos reachable by URL
```

To start over later: `php artisan migrate:fresh --seed` (deletes all data).

## Running

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

`--host=0.0.0.0` lets the Android emulator and phones on your Wi-Fi reach it. Keep this terminal open.

Optional, in extra terminals:

```bash
php artisan schedule:work      # morning reminders + Telescope pruning
php artisan queue:work         # only if MEDIA_QUEUE_CONVERSIONS=true
```

**Check it's up:**

| What | URL |
|---|---|
| Health check (should say `"status": "ok"`) | http://localhost:8000/api/v1/health |
| Admin panel (log in with `ADMIN_EMAIL` / `ADMIN_PASSWORD`) | http://localhost:8000/admin |
| Interactive API docs | http://localhost:8000/docs/api |
| Telescope (when `TELESCOPE_ENABLED=true`) | http://localhost:8000/telescope |

Using Herd? Link the folder (`herd link handi-backend`) and use `http://handi-backend.test` instead of `php artisan serve`. Phones and the emulator can't open `.test` addresses, so for the app keep `php artisan serve --host=0.0.0.0` running, or share it with ngrok / `herd share`.

**Sharing over ngrok:** `ngrok http 8000`, then set `APP_URL` to the ngrok URL and `TRUSTED_PROXIES=*` in `.env`, and run `php artisan config:clear`.

## After pulling changes

```bash
composer install
php artisan migrate
php artisan optimize:clear
```

Then hard-refresh the admin (Ctrl+F5).

## Common problems

| Problem | Fix |
|---|---|
| `Your PHP version … does not satisfy …` | Use PHP 8.2+ (`php -v`); with Herd run `herd composer install` |
| `could not find driver` | Enable `pdo_mysql` in `php.ini` |
| `SQLSTATE[HY000] [1049] Unknown database 'handi'` | Create the database (step 3) |
| `SQLSTATE[HY000] [2002] Connection refused` | Start MySQL; check `DB_HOST` / `DB_PORT` |
| Photos don't load in the app | `php artisan storage:link`, and `APP_URL` must be an address the phone can reach |
| `global_helpers.php` / Filament class not found | `composer install` again; don't `composer require filament/filament` |
| Admin shows old styles or pages | `php artisan optimize:clear`, then Ctrl+F5 |
| Telescope says *Unable to access … using "local" environment* | Set `TRUSTED_PROXIES=*` and sign in at `/admin` first |

## Dish photos (Spatie Media Library)

Photos of library dishes (admin) and users' dishes (app) are stored with [Spatie Media Library](https://spatie.be/docs/laravel-medialibrary) on `MEDIA_DISK` (`public` locally, `s3`/Cloudflare R2 in production):

- One photo per dish; a new upload replaces the old file and its sizes.
- The original is capped at **1600 px / JPEG 85** before it's stored, and two WebP sizes are made: `thumb` (240×240, lists) and `card` (≤ 900 px, cards and the dish page). The API returns `thumb_url` and `image_url` (card).
- `MEDIA_QUEUE_CONVERSIONS=false` (default) makes the sizes right away; set it to `true` in production with `php artisan queue:work` running to make them in the background.
- Photos uploaded before the media library: run `php artisan handi:photos-to-media` once to move them in (adds the sizes, removes the old files).

## Telescope (debugging)

[Laravel Telescope](https://laravel.com/docs/telescope) shows every request, query, exception, log, mail, job and scheduled task at **http://localhost:8000/telescope** (also linked in the admin sidebar under *Developer*).

Telescope is already installed; `php artisan migrate` creates its tables.

- `TELESCOPE_ENABLED=true` in `.env` turns it on (it's off when unset, in tests and in `.env.production.example`).
- Locally it records everything and opens without login. On any other environment it records only problems and only signed-in super admins can open it (sign in at `/admin` first).
- Passwords, tokens and reset codes are masked; entries older than 48 hours are pruned daily by the scheduler.

## Tests

```bash
php artisan test
```

77 tests on in-memory SQLite (needs `pdo_sqlite`). Covers every endpoint, the pick rules, offline sync, reminders, the API docs gate and every admin page (users, user detail tabs, activity log, library, dish import, image preview). Weather and Expo push are faked — tests never hit the network.

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
