# Handi app

The app is built with Expo SDK 57, React Native 0.86, Expo Router (typed routes) and TypeScript. It runs on Android, iOS and web, and talks to the Laravel API in [`../backend`](../backend). The API contract is in [`../docs/api.md`](../docs/api.md), and the screen-by-screen API map is in [`../docs/screens.md`](../docs/screens.md).

## Setup

```bash
cd frontend
npm install
copy .env.example .env      # macOS/Linux: cp .env.example .env
```

### The one setting: `EXPO_PUBLIC_API_BASE_URL`

Every API request and image URL in the app is built from this value (`src/lib/config.ts`), and no other address is embedded anywhere. You can include `/api/v1` or leave it off; the app adds it if it's missing.

| Where the app runs | Value |
|---|---|
| Phone with Expo Go (same Wi-Fi) | `http://<your PC's LAN IP>:8000/api/v1` (allow port 8000 in Windows Firewall) |
| Android emulator | `http://10.0.2.2:8000/api/v1` |
| Web / same PC | `http://localhost:8000/api/v1` or your `.test` domain |

Start the backend with `php artisan serve --host=0.0.0.0 --port=8000`, then run the app:

```bash
npx expo start --go -c      # Expo Go: scan the QR code (--go because expo-dev-client is installed)
npx expo start --web        # browser
```

Restart with `-c` after changing `.env`.

## Checks

```bash
npm run typecheck
npm run lint
```

## Screens

| Area | Routes |
|---|---|
| Start | `index` (splash and routing) · `welcome` · `no-connection` · `legal` |
| Setup (before sign-up) | `setup/location` → `setup/city` → `setup/family` → `setup/preferences` → `setup/dishes` → `setup/preview` → `signup` |
| Auth | `login` · `signup` · `forgot-password` → `verify-code` → `reset-password` |
| Tabs | `(tabs)/today` · `(tabs)/dishes` · `(tabs)/history` · `(tabs)/settings` |
| Dishes and meals | `dish/[id]` · `add-dish` (library or your own, with photo) · `log-meal` |
| Account | `account/profile` · `account/verify-email` · `account/password` · `account/family` · `account/preferences` · `account/location` · `account/delete-account` |
| Notifications | `notifications` (inbox) · `notification-settings` · `allow-reminder` |

## Structure

```
src/
├── app/            # Expo Router screens (above). _layout.tsx: fonts, auth guard, web frame, push taps
├── api/            # axios client (bearer token, 401 → logout), endpoints, React Query hooks, types
├── components/
│   ├── ui/         # Txt, Button, Field, Controls (chips/toggles), Sheet, Icon, Toasts, Spinner
│   ├── CategoryFilter.tsx  # chips that fit the width; the rest are behind a "More" dropdown
│   ├── DishImage, Kitchen, FloatNav, EditScaffold, AuthKit, AccountKit, LoadState, WebPickerInput
├── lib/            # config (base URL), secure storage, session, dates, location, photo,
│                   # notifications, meal queue, query client + persistence, layout, validation
├── store/          # zustand: auth, onboarding, offline queue, network, ui
└── theme/tokens.ts # colors, type scale, spacing, radius, shadows from the design
```

## Behaviour notes

- **Auth and session:**
  - The token is stored in SecureStore on phones and in AsyncStorage (localStorage) on web, so you stay logged in after a restart.
  - A guard in `_layout.tsx` sends signed-out users to Log in and signed-in users past the guest screens.
- **Offline:**
  - Today, dishes, history and the catalog are cached with React Query persistence.
  - Meals logged offline go into a queue and are sent with `POST /me/sync` when the phone is back online.
- **Web:** the same phone design, centred in a 440 px column (`src/lib/layout.ts`). Native pickers are replaced by web inputs.
- **Design:**
  - All text is Poppins (`Txt` with a role from the type scale). Colors and sizes come only from `src/theme/tokens.ts`.
  - Names show as initials avatars (`initials()` in `src/lib/validate.ts`).
- **Push:**
  - The app registers the device with `POST /me/devices`, and tapping a notification opens its link.
  - Remote push does **not** work in Expo Go and needs a development build:
    ```bash
    npx eas-cli@latest build --profile development --platform android
    ```
- **EAS environments:** `eas.json` has `development`, `preview` and `production` profiles. Store the API address per environment instead of in a committed file:
  ```bash
  npx eas-cli@latest env:create --environment preview --name EXPO_PUBLIC_API_BASE_URL --value https://staging-api.example.com/api/v1 --visibility plaintext
  npx eas-cli@latest env:create --environment production --name EXPO_PUBLIC_API_BASE_URL --value https://api.example.com/api/v1 --visibility plaintext
  ```
  `EXPO_PUBLIC_*` values are bundled into the app, so never put secrets in them.
