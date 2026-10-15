# Backend production audit

Audited: Laravel 12 API, Filament 4 admin, public website, scheduler, data layer. October 2026.
Method: two independent reviews of every controller, service, model, migration, Filament resource, command and view, then fixes with tests. The suite runs 98 tests, on SQLite and on MariaDB in `ONLY_FULL_GROUP_BY` strict mode.

## Verdict

**Before: 6/10.** No data leaks between users, but the morning reminder could silently skip users at scale, and several race conditions and brute-force paths existed.
**After fixes: 8/10.** Deployable. What is left is scale work (queued reminders, Redis) and two low-risk privacy details.

## Fixed in this pass

| # | Severity | Problem | Fix |
|---|---|---|---|
| 1 | Critical | Reminders matched the exact minute only. With many users due at 09:00 the run overran the minute and everyone not yet reached got **no reminder that day**. | 60-minute catch-up window: anyone due and not yet reminded is sent on the next run. |
| 2 | Critical | One bad user (invalid timezone, push service error, race) threw and **stopped the whole run** for everyone after them. | Each user is processed in its own `try/catch`; failures are reported and the run continues. |
| 3 | Critical | Scheduler lock lived 24 h: a run killed by a deploy blocked reminders for a day. No single-server guard. | `withoutOverlapping(10)->onOneServer()`. |
| 4 | Critical | Reminder "already sent?" was check-then-act: two runs could both send. | Atomic claim of `notified_at` before sending. |
| 5 | Medium | 5-attempt limit on email codes could be bypassed with parallel requests. | Attempt is consumed atomically **before** the hash check. |
| 6 | Medium | Change-email could be repeated without cooldown (code spam / slow brute force). | 45 s cooldown on new codes, `throttle:sensitive` (10/h per user) on verify and on the test push. |
| 7 | Medium | Login lockout was per email+IP only, so one account could be attacked from many IPs; timing revealed which emails exist. | Extra limit per email across all IPs (20/h); password hash is checked even for unknown emails. |
| 8 | Medium | Deleting an account left its **dish photos** (files and rows) and API tokens behind. | `User::deleting` clears media and tokens for both app and admin deletes. |
| 9 | Medium | Deleted-account log kept the person's email and name. | Only a masked email and the old id are kept. |
| 10 | Medium | Two requests creating today's picks at once returned a 500. Parallel shuffles could exceed 5/day. | Unique-violation fallback; shuffle limit claimed atomically. |
| 11 | Medium | Double tap or offline sync racing an online log created duplicate meals. | Per-user lock around the duplicate check and insert. |
| 12 | Medium | Unbounded ID arrays on public preview, sign-up and add-dishes (one `exists` query per element). Preview accepted any lat/lng. | `max:500` on arrays; lat/lng ranges on preview. |
| 13 | Medium | N+1 on photos: every dish list loaded media per row (≈300 queries for 150 dishes). | `media` and `dish.media` eager-loaded in `withDetails`. |
| 14 | Medium | Weather outage made every request wait the full timeout. | Failures cached for 5 minutes. |
| 15 | Medium | Admin dashboard scanned `meal_logs` every refresh; reminder query had no index. | Indexes on `meal_logs.logged_at`, `meal_logs(date, deleted_at)`, `kitchens(notifications_enabled, notify_time)`. |
| 16 | Medium | Admin "Super admin" toggle silently did nothing (`is_admin` isn't fillable, by design). Admins could delete themselves. | Saved explicitly in `EditUser`; own toggle and own delete disabled. |
| 17 | Medium | Admin timezone field accepted any text, which later crashed the reminder run. | `timezone:all` validation. |
| 18 | Medium | Deleting a city silently cleared every kitchen's location. | Delete disabled while kitchens use the city. |
| 19 | Low | Password reset token could be used twice by parallel requests. | Consumed atomically; password + sign-out in one transaction. |
| 20 | Low | Sign-in tokens never expired. | 180-day expiry (`SANCTUM_TOKEN_EXPIRATION_MINUTES`) and daily prune. |
| 21 | Low | `app_notifications` and `activity_logs` grew forever. | 90-day `MassPrunable`, `model:prune` daily. |
| 22 | Low | Single log file grew without limit. | Daily log files by default. |
| 23 | Low | Catalog cache version bump could collide; import could leave stale cache. | Atomic increment; flush after the import commits. |
| 24 | Low | Duplicate/conflicting security headers between Nginx and Laravel; none on the API. | One `SecurityHeaders` middleware for every response; strict CSP on the website. |
| 25 | — | Telescope could be opened by any admin, or from localhost without login. | Only accounts in `TELESCOPE_ALLOWED_EMAILS` (default `admin@handi.test`), always signed in. |

## Checked and fine

- No IDOR: every `/me/*` route scopes by `user_id`, including restores and trashed rows.
- Mass assignment: every write uses validated, whitelisted data; `is_admin` is never fillable.
- Codes: `random_int`, hashed; reset tokens 64 random chars stored as SHA-256 with a short window.
- Uploads: `image` rule (no SVG), 10 MB cap, 413 handled, originals capped at 1600 px.
- Registration and dish import run in transactions; offline sync capped at 200 operations.
- Foreign keys cascade correctly; deleting a user never fails on a constraint.
- No XSS: every unescaped Blade output is static markup or passes user data through `e()`.

## Still open (recommended next)

| Priority | Item |
|---|---|
| Before 10k users | Move each reminder to a queued job (`SendDailyReminder`) and run a Supervisor worker; switch cache/queue to Redis. The catch-up window covers you until then. |
| Before 10k users | Cache dashboard widget numbers for 60 s. |
| Low | Password-reset endpoints still differ slightly between known and unknown emails (cooldown/attempt counts), which allows slow email enumeration. Key the cooldown by email in cache. |
| Low | Public `/api/v1/health` runs deep checks on every call: cache the result for 30 s or require a token. |
| Low | Creating a custom dish with a photo isn't atomic: if the photo fails, the dish stays without it (the app already handles this). |
| Low | Use `upsert` in `addLibraryDishes` so parallel adds of the same dish don't 500. |
| Low | `DemoSeeder` reads `env()` directly; move to config before using it with cached config. |

## Production settings to confirm

- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `TELESCOPE_ENABLED=false` unless debugging, `TELESCOPE_ALLOWED_EMAILS=admin@handi.test`.
- Run `php artisan migrate --force` (new indexes) and keep the scheduler cron.
- Nginx: use the updated `deploy/nginx.conf` (gzip, long cache for site assets, no duplicate headers).
