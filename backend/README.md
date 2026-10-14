# Handi backend

Laravel 13 API (`/api/v1`) and Filament 4 super-admin panel (`/admin`), sharing one MySQL database.

- Written API guide: [`../docs/api.md`](../docs/api.md)
- Interactive API docs (OpenAPI, try-it-out): **http://localhost:8000/docs/api** · raw spec: `/docs/api.json`
- Postman: import [`../docs/handi-api.postman_collection.json`](../docs/handi-api.postman_collection.json)
- Database: [`../docs/database.md`](../docs/database.md)

## Requirements

- **PHP 8.3+** (Laravel 13 does not run on 8.2) with `intl`, `zip`, `pdo_mysql`, `pdo_sqlite`, `mbstring`, `fileinfo`, `gd`
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

## Tests

```bash
php artisan test
```

63 tests on in-memory SQLite (needs `pdo_sqlite`). Covers every endpoint, the pick rules, offline sync, reminders, the API docs gate and every admin page (users, user detail tabs, activity log, library, dish import, image preview). Weather and Expo push are faked — tests never hit the network.

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
