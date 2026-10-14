# Handi — QA & design review

Reviewed as product owner + senior SQA, October 2026.
Build: Expo web export against the Laravel 12 backend (PHP 8.2), demo user `hina@example.com`.
Backend suite: **70 / 70 Pest tests pass** (SQLite and MariaDB).

## 1. Functional run (30 steps)

| # | Flow | Result |
|---|------|--------|
| 1 | Splash → welcome (video logo, matched background) | ✅ |
| 2 | Sign in with wrong password | ✅ 401, inline error shown |
| 3 | Sign in with correct password | ✅ |
| 4 | Today: greeting, date, bell badge, Pick/Cook/Log tracker | ✅ |
| 5 | Today: suggestions load (weather-aware when available) | ✅ |
| 6 | Pick a dish → tracker moves to "Cook" | ✅ |
| 7 | Mark cooked / log meal → tracker "Log" done | ✅ |
| 8 | Undo log (confirm modal, loader, toast) | ✅ |
| 9 | Dishes list, search, filters | ✅ |
| 10 | Dish detail → add to kitchen | ✅ |
| 11 | Create custom dish with photo (compressed client-side, multipart) | ✅ |
| 12 | Validation error on custom dish shows the server's real message | ✅ |
| 13 | Edit / remove kitchen dish (confirm + toast) | ✅ |
| 14 | Log meal screen | ✅ |
| 15 | History list, thumbnails from media library | ✅ |
| 16 | Delete a history entry (confirm + toast) | ✅ |
| 17 | Notifications list, mark read | ✅ |
| 18 | Clear notifications (confirm + toast) | ✅ |
| 19 | Account → profile edit | ✅ |
| 20 | Account → location (permission states handled) | ✅ |
| 21 | Account → preferences | ✅ |
| 22 | Change password | ✅ |
| 23 | Go offline → toast for ~5 s, then hides | ✅ (shown 1.5 s, gone at 7 s) |
| 24 | Retry while offline → re-hides after 5 s | ✅ |
| 25 | Back online → "Back online" toast 3.5 s | ✅ |
| 26 | Backend unreachable → "Can't reach Handi" screen | ✅ |
| 27 | `GET /api/v1/health` | ✅ |
| 28 | Admin panel: dishes CRUD, image upload → thumb/card conversions | ✅ |
| 29 | Log out → back to welcome | ✅ |
| 30 | Delete account flow | ✅ (see D3) |

The only HTTP error seen in the run was the expected 401 in step 2.

## 2. Design findings

| ID | Screen | Finding | Severity | Status |
|----|--------|---------|----------|--------|
| D1 | Today header | Avatar/greeting/bell looked cramped; the stepper pills were hard to read | Medium | **Fixed**: new header, numeric bell badge, progress card with a connected track |
| D2 | Account → Location | Hero text ran under the decorative sun | Low | **Fixed**: right padding |
| D3 | Account → Delete | The button stays active and validates on press; it should stay disabled until the confirmation text matches | Low | Open |
| D4 | Welcome | Dish labels in the hero collage are clipped at narrow widths | Low | Open |
| D5 | Dishes | The floating "Add dish" button covers the last card's content; the list needs bottom padding | Low | Open |
| D6 | Today | "Today's weather isn't available" appears when Open-Meteo is unreachable. That's correct behaviour; this was seen only in the sandbox | Info | None needed |
| D7 | All screens (dev) | The grey gear bubble is the **Expo dev-client tools button**. It only appears in development builds, not in release APKs | Info | None needed |

## 3. Open technical items

- The Telescope-over-ngrok fix (Sentinel override + admin gate) is written but **not yet tested** on your machine.
- Uncommitted changes: commit and push from your machine.
