# Database (v1)

MySQL 8 in production, SQLite in tests. Every table below is created by `backend/database/migrations`; `php artisan migrate:fresh --seed` rebuilds it with the catalog, the super admin and (optionally) the demo user.

```
users 1─1 kitchens ─┐
  │                 └─ city ─ province
  ├─< kitchen_dishes >─ dishes ─ categories        (library dish, or custom when dish_id is null)
  ├─< daily_picks      (one row per user per local date: 3 picks, shuffles, plan)
  ├─< meal_logs        (uuid; dish name snapshot; soft delete for undo)
  ├─< app_notifications (soft delete for undo)
  ├─< devices          (one per phone; Expo push token; tied to the Sanctum token)
  ├─< personal_access_tokens (Sanctum, one per login)
  ├─< auth_codes       (6-digit codes: password reset, email change)
  └─< activity_logs    (audit trail shown in the admin)
```

## Catalog (edited in the admin)

| Table | Columns | Notes |
|---|---|---|
| `categories` | `key` (unique: vegetables, lentils, chicken, beef, mutton, fish, rice, breakfast, snacks), `name`, `image_key`, `sort_order`, `is_active` | `image_key` = default art for dishes without a photo |
| `dishes` | `name`, `slug`, `category_id`, `note` ("Winter staple"), `description`, `image` (upload), `image_key` (bundled art), `default_weather` json (`["hot","mild"]` or `["any"]`), `default_effort` quick/medium/heavy, `default_cost` low/medium/high, `usual_meal` breakfast/lunch/dinner/both, `is_active` | The library users pick from |
| `provinces` | `name`, `short_name`, `sort_order` | |
| `cities` | `province_id`, `name`, `slug`, `lat`, `lng`, `is_popular`, `sort_order` | 23 seeded; popular ones lead the city picker |

## Per user

| Table | Columns | Notes |
|---|---|---|
| `users` | `name`, `email` (unique), `password`, `is_admin`, `timezone` (default Asia/Karachi), `terms_accepted_at`, `password_changed_at`, `last_login_at`, `last_active_at`, `phone` (unused in v1) | `is_admin` = super admin (panel access); admins never get reminders |
| `kitchens` | `user_id` (unique), `family_size` 1–20, `budget` low/normal/high, `repeat_gap_days` 3/5/7/10, `meals` json, `location_mode` gps/city, `city_id`, `lat`/`lng` (rounded ~1 km), `location_label`, `notifications_enabled`, `notify_time` HH:mm (05:00–13:00), `last_synced_at` | Created at sign-up |
| `kitchen_dishes` | `user_id`, `dish_id` (null = custom), `is_custom`, `name`/`category_id`/`usual_meal` (custom), `weather`/`effort`/`cost` (override for library, values for custom), `photo_path`, `photo_status` none/processing/ready, `removed_at` | unique (`user_id`,`dish_id`). Removing sets `removed_at` so history and undo keep working |
| `daily_picks` | `user_id`, `date` (user's local date), `set_number`, `shuffles_used`, `items` json `[{kitchen_dish_id, reason}]`, `weather` snapshot, `generated_at`, `plan_kitchen_dish_id`, `plan_meal`, `planned_at`, `notified_at` | unique (`user_id`,`date`). Built lazily on first open or by the reminder |
| `meal_logs` | `id` uuid (client may send it), `user_id`, `kitchen_dish_id`, `dish_name` + `category_key` (snapshot), `date`, `meal` breakfast/lunch/dinner, `source` suggestion/manual, `logged_at`, `deleted_at` | Dates up to 60 days back. Same dish + date + meal is not logged twice |
| `app_notifications` | `user_id`, `type` daily_picks/sync/photo_ready/kitchen_ready/account, `title`, `body`, `cta_label`, `deep_link`, `image_key`, `read_at`, `deleted_at` | The in-app inbox; the reminder also goes out as an Expo push |
| `devices` | `user_id`, `personal_access_token_id`, `device_id` (unique), `name`, `expo_push_token`, `platform` android/ios, `last_seen_at` | Logging out deletes the phone's device row with its token |
| `auth_codes` | `purpose` password_reset/email_change, `email`, `user_id`, `code_hash`, `attempts_left` (5), `expires_at` (15 min), `last_sent_at` (resend after 45 s), `reset_token_hash`, `verified_at`, `consumed_at` | Codes and reset tokens are stored hashed |
| `activity_logs` | `user_id`, `event` (e.g. `auth.login`, `meal.logged`, `dish.removed`), `description`, `subject` (morph), `properties` json, `ip_address`, `user_agent`, `created_at` | Kept after account deletion (user_id set null) |

Framework tables: `personal_access_tokens`, `sessions`, `password_reset_tokens` (unused — codes live in `auth_codes`), `cache`, `jobs`.

## Pick rules (PickService)

1. Candidates = kitchen dishes not removed, whose `usual_meal` fits the kitchen's meals.
2. Skip dishes cooked within `repeat_gap_days` (from `meal_logs`).
3. Score by weather fit (today's bucket: hot / mild / cold / rainy, or `any`), days since last cooked, and never-cooked bonus.
4. Take 3 from **different categories**. If the kitchen is too small, relax in order: category rule → weather → repeat gap.
5. Each pick carries a reason ("Fits hot weather", "New for you"). Shuffle = new set, up to `HANDI_SHUFFLES_PER_DAY` (5).
