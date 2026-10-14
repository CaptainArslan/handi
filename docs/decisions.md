# Decisions

Record each decision with the date and the reason. Change a decision by adding a new entry, not by deleting the old one.

## Product (V1)

| # | Decision | Reason |
|---|---|---|
| P1 | The app suggests **3 dishes a day** for Pakistani households. | Core value: removes the daily "what to cook" question. |
| P2 | **English only** in V1. No language picker. | Keeps content and UI scope small. Urdu comes later. |
| P3 | **Friday specials deferred** to a later version. | Not needed to validate the core loop. |
| P4 | **Guest mode**: Today, Browse and Dish detail work without login. Favorites and synced preferences need login. | Lets people try the app before signing up. |
| P5 | Suggestions are **global per date**, then filtered per user at request time (veg-only, disliked categories). | One schedule to curate; personalization stays cheap. |

## Technical

| # | Decision | Reason |
|---|---|---|
| T1 | Single repo: `backend/` (Laravel) + `frontend/` (Expo) + `docs/`. | One commit can change the API and the screen that uses it. |
| T2 | Laravel serves both the **API** (`/api/v1`) and the **admin panel** (`/admin`) from the same models and DB. | No syncing; admin edits appear on the app's next fetch. |
| T3 | Admin panel built with **Filament 4** (assumed 2026-10-13; switchable to Inertia + Vue if wanted). | Generates CRUD, uploads and filters; saves weeks on an internal tool. |
| T4 | Auth via **Laravel Sanctum** personal access tokens; token stored in `expo-secure-store`. | Simple and well suited to mobile clients. |
| T5 | Suggestion strategy: **auto-generate + admin override**. A scheduled job fills the next 7 days; rows marked `is_manual` are never overwritten. | Zero daily effort, full editorial control. |
| T6 | Suggestion rules: no repeat within 14 days; no two dishes from the same category on one day; at least one vegetarian dish per day. | Variety and a veg option every day. |
| T7 | API responses are cached per date and invalidated by model observers on save/delete. | Fast Today screen without stale data after admin edits. |
| T8 | Dates are in **Asia/Karachi**. "Today" is computed in that timezone on the server. | All users are in Pakistan; avoids midnight UTC mismatches. |
| T9 | App data layer: React Query (server state) + Zustand (auth, onboarding) + axios. | Caching, retries and infinite scroll built in. |
| T10 | Suggestion rules relax in this order when the catalog is too small: category rule, then veg rule, then (last resort) repeats. | A repeated dish is worse than two daals on one day. |

## Open questions

- [x] Filament or Inertia + Vue for admin? → Filament, assumed for Phase 1 (T3)
- [ ] Does the design split suggestions by meal (lunch/dinner)? Phase 1 assumes 3 dishes for the whole day; `meal_type` is stored so a split can be added later.
- [ ] Login method: email + password only, or phone/OTP as well? (OTP needs an SMS provider.)
- [ ] Should a guest's preferences (veg-only, household size) affect suggestions before login? Plan: yes, sent as query params.
- [ ] Household size: does it scale ingredient quantities in V1, or is it stored for later?

## v2 — rebuilt to the Handi app design (2026-10-13)

These replace P4, P5, T5, T6, T7 and T10 above.

| # | Decision | Reason |
|---|---|---|
| D1 | **Picks are per user**, from the dishes in *their* kitchen (library dishes + custom family recipes), not a global daily list. | The design's core idea: ideas from what your family already cooks. |
| D2 | **Sign-up carries the whole onboarding** (family, budget, repeat gap, meals, location, ≥10 dishes, custom dishes) in one call. Onboarding works offline-first; `POST /preview-picks` shows the first picks before the account exists. | Matches the design flow; no half-created accounts. |
| D3 | **Weather from Open-Meteo** (free, keyless), bucketed hot / mild / cold / rainy, cached per ~1 km for an hour. GPS is rounded to ~1 km before it is stored. | No API key to leak; privacy. |
| D4 | Pick rules: 3 distinct categories, skip dishes cooked within the repeat gap, prefer weather fit and dishes not cooked for longest; relax category → weather → gap when the kitchen is small. 5 shuffles a day. | Variety without ever showing an empty Today. |
| D5 | **Meal logs use client UUIDs** so the app can log offline and sync later (`POST /me/sync`), safely retried. Logging the same dish for the same date and meal twice keeps one entry. | The design's offline mode and "synced" notification. |
| D6 | **Removals are reversible** (dishes `removed_at`, meals/notifications soft-deleted) to back the design's Undo toasts. | Undo everywhere in the design. |
| D7 | **6-digit email codes** for password reset and email change (15 min, 5 tries, resend after 45 s); login locks for 60 s after 5 wrong passwords. | Exact copy and timings from the design. |
| D8 | **Morning reminder** at the user's own time (05:00–13:00, their timezone), sent by `reminders:dispatch` every minute; skipped if they already logged a meal today. | Per-user reminder in Settings. |
| D9 | **Activity log** for every meaningful user action; the admin shows it globally and per user next to the user's kitchen, dishes, meals, notifications, phones and logins. | Super-admin visibility asked for by the owner. |
| D10 | **API docs generated from code** (Scramble at `/docs/api`, OpenAPI export in `docs/openapi.json`) plus a recorded-examples guide and Postman collection. Docs are public only in `local`; super admins elsewhere. | Docs can't drift from the code; not exposed in production. |
