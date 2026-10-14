# Handi app

The app is built with Expo SDK 57, React Native 0.86, Expo Router (typed routes) and TypeScript. It runs on Android, iOS and web, and talks to the Laravel API in [`../backend`](../backend). The API contract is in [`../docs/api.md`](../docs/api.md), and the screen-by-screen API map is in [`../docs/screens.md`](../docs/screens.md).

## Requirements

| Tool | Version | Notes |
|---|---|---|
| Node.js | **22 LTS** | Includes npm. Check with `node -v` |
| Android Studio | latest | Android SDK + an emulator (Pixel, API 34+, **4 GB RAM / 8 GB storage**) |
| JDK | 17 | Bundled with Android Studio — set `JAVA_HOME` to its `jbr` folder if Gradle can't find Java |
| The backend | running | See [`../backend/README.md`](../backend/README.md) — `php artisan serve --host=0.0.0.0 --port=8000` |

iOS needs a Mac with Xcode. The web build needs nothing extra.

## Setup (first time)

**1. Install packages**

```bash
cd frontend
npm install
```

**2. Create `.env` and point it at the backend**

```bash
copy .env.example .env      # Windows
cp .env.example .env        # macOS / Linux / Git Bash
```

Set the one value the app needs, `EXPO_PUBLIC_API_BASE_URL`, for where the app will run:

| Where the app runs | `EXPO_PUBLIC_API_BASE_URL` |
|---|---|
| Android emulator | `http://10.0.2.2:8000/api/v1` |
| Real phone on the same Wi-Fi | `http://<your PC's LAN IP>:8000/api/v1` (find it with `ipconfig`; allow port 8000 in Windows Firewall) |
| Web, same PC | `http://localhost:8000/api/v1` |
| Anywhere, through ngrok | `https://<id>.ngrok-free.app/api/v1` |

`10.0.2.2` is the emulator's name for your PC — `localhost` or a `.test` domain does not work from the emulator or a phone. Every API call and image URL is built from this value (`src/lib/config.ts`); `/api/v1` is added if you leave it off. `.env` is git-ignored, and `EXPO_PUBLIC_*` values are bundled into the app, so never put secrets in it.

Test it from the emulator's browser: `http://10.0.2.2:8000/api/v1/health` should show `"status": "ok"`.

## Running on the Android emulator

1. Start the emulator from Android Studio (*Device Manager → ▶*), or `emulator -avd <name>`. Check it's connected with `adb devices`.
2. Build and install the development app (first time, and again whenever native packages change):

   ```bash
   npx expo run:android        # same as: npm run android
   ```

   The first build takes several minutes. It installs **Handi** on the emulator and starts Metro.
3. Day to day, just start Metro and open the installed app:

   ```bash
   npx expo start              # press "a" to open on Android, "r" to reload
   ```

Restart with `npx expo start -c` after changing `.env` (clears the bundler cache).

> **Why a development build and not Expo Go?** The app uses native modules (video splash, image compression, clipboard, push). A development build includes them; Expo Go may not match the SDK.

## Running on a real phone

- **Android, cable:** enable *Developer options → USB debugging*, plug in, check `adb devices`, then `npx expo run:android`.
- **Android, Wi-Fi only:** build once (`npx expo run:android`) or install an EAS development build (below), then `npx expo start` and open the URL / scan the QR code from the Handi dev app.
- In both cases set `EXPO_PUBLIC_API_BASE_URL` to your PC's LAN IP (or ngrok).

## Running in the browser

```bash
npx expo start --web            # opens http://localhost:8081
```

Uses `http://localhost:8000/api/v1`. Handy for quick UI checks; the camera and push notifications are phone-only.

## Signing in

- Create an account through the app's setup flow, or
- use the demo user from the backend `.env` (`DEMO_USER_EMAIL` / `DEMO_USER_PASSWORD`, seeded by `php artisan migrate --seed`).

## Development build in the cloud (optional)

No Android Studio? Build the dev app with EAS and install the APK on any phone:

```bash
npx eas-cli@latest login
npx eas-cli@latest build --profile development --platform android
```

## Common problems

| Problem | Fix |
|---|---|
| App shows **Can't reach Handi** / offline screen | Backend not running with `--host=0.0.0.0`, or wrong `EXPO_PUBLIC_API_BASE_URL` (use `10.0.2.2` on the emulator). Then `npx expo start -c` |
| Changed `.env` but nothing changed | Restart with `npx expo start -c` |
| `Unable to resolve module expo-…` | `npm install`, then `npx expo run:android` (native package added) |
| `INSTALL_FAILED_INSUFFICIENT_STORAGE` | Uninstall old builds (`adb uninstall com.hegemonic.handi`) or wipe the emulator's data in Device Manager and give it more storage |
| Emulator won't start / black screen | Device Manager → *Cold Boot Now*; delete its snapshots; give it 4 GB RAM |
| Old app behaviour after an update | Shake / `Ctrl+M` → Reload, or `adb shell pm clear com.hegemonic.handi` to clear app data |
| Grey gear bubble on screen | That's Expo's dev tools button; it only appears in development builds |
| Location not found on the emulator | Set a location in the emulator's *Extended controls → Location* and turn on Location in Android settings |

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
