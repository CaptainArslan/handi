# Handi roadmap: future features

Ideas and requests for after the first release. Each item notes where it came from and a rough plan.
Move an item to **In progress** when work starts, and to **Done** with the release it shipped in.

Status: **Idea** · **Planned** · **In progress** · **Done (version)**

---

## 1. Family Sharing (view-only family members): Planned

**Requested by:** LinkedIn feedback after launch post (Oct 2026).

**Goal:** Everyone at home can see today's picks and what has been cooked, while one person (the kitchen owner) manages the kitchen.

**How it works for users**
- The owner opens **Settings → Family** and taps **Invite family member**.
- Handi creates an invite link or 6-digit code (expires after 7 days).
- The family member installs Handi (or opens the web app), signs up or logs in, and enters the code.
- They now see the owner's kitchen: Today's picks, History, Dishes. They cannot change anything.
- The owner can see the members list and remove anyone at any time.

**Roles**

| Role | View picks, history, dishes | Get morning reminder | Log a meal | Change dishes / settings | Manage members |
|---|---|---|---|---|---|
| Owner | ✓ | ✓ | ✓ | ✓ | ✓ |
| Viewer | ✓ | optional | ✗ (v1) | ✗ | ✗ |

A later version may add a **Helper** role that can log meals but not change settings.

**Backend (Laravel)**
- Table `kitchen_members`: `id`, `kitchen_id`, `user_id`, `role` (`viewer`), `invited_by`, `created_at`; unique (`kitchen_id`, `user_id`).
- Table `kitchen_invites`: `id`, `kitchen_id`, `code_hash`, `expires_at`, `used_at`, `created_by`.
- Endpoints:
  - `POST /api/v1/me/family/invites`: create an invite (owner only, rate-limited).
  - `POST /api/v1/family/join`: join with a code.
  - `GET /api/v1/me/family`: list members.
  - `DELETE /api/v1/me/family/{member}`: remove a member (owner), or leave (viewer).
- Read endpoints (`/today`, `/history`, `/dishes`) accept the viewer's token and resolve the **shared kitchen**. All write endpoints return 403 for viewers (a policy, `KitchenPolicy::update`).
- A user can own one kitchen and view at most a few others; add a kitchen switcher if they view more than one.
- Account deletion: deleting the owner removes the kitchen and all memberships; deleting a viewer only removes their membership.
- Audit log entries for invite, join and remove.

**App (Expo)**
- New screens: `account/family.tsx` (members + invite), `join-family.tsx` (enter code).
- A read-only mode flag from `/me` (`role: owner | viewer`) hides edit buttons (Cook / Log / Shuffle / Edit) for viewers.
- Deep link `https://handi.hegemonicinc.com/app/join?code=XXXXXX`.
- Viewers can turn the morning reminder on or off for themselves.

**Store / policy updates when shipped**
- Privacy policy: mention that kitchen data is shared with family members the owner invites.
- Play Data safety: data is still not shared with third parties; no change needed beyond the policy text.
- Store listing + release notes: "Share your kitchen with family."

**Tests**
- Viewer can read but every write returns 403.
- Expired or reused invite codes are rejected; codes are rate-limited.
- Removing a member revokes access immediately.

---

## 2. Offline: plan and edit meals without internet: Idea

**Source:** review of offline support.
Today the app queues **logging** and **deleting** meals offline. Changing today's planned dish and editing a logged meal still need internet. Add `plan.set` and `meal.update` to the offline queue (`src/store/queue.ts`) and the `/sync` endpoint.

## 3. Over-the-air updates (expo-updates): Idea

Ship JavaScript-only fixes without a new store release. Add `expo-updates`, a `channel` per build profile in `eas.json`, and use `eas update --channel production`.

## 4. Smaller Android app (R8 / code shrinking): Idea

Enable `enableProguardInReleaseBuilds` / `enableShrinkResourcesInReleaseBuilds` via `expo-build-properties`, test with a preview build, and upload the mapping file to Play for readable crash reports.

## 5. iPhone release: Planned

Blocked on an Apple Developer Program membership under the owner's own team. Then: `eas build -p ios --profile production`, TestFlight, App Store listing (6.9" screenshots), set `HANDI_IOS_URL`.

## 6. Crash reporting: Idea

Add Sentry (or Firebase Crashlytics) to see crashes from real users. Update the Play Data safety form (crash logs, diagnostics) when added.

## 7. Store & launch follow-ups: Planned

- Developer / company name in the privacy policy (required by Play).
- Set `HANDI_ANDROID_URL` once the Play listing is public.
- Landscape (16:9) promo video for YouTube / Play listing.

---

## Done

| Version | What shipped |
|---|---|
| 1.0.x | First release: daily picks, weather matching, meal log, custom dishes, reminders, offline log, web app at /app |
