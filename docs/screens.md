# Screens

> **Draft.** This is the typical screen set for a daily-suggestion app. Replace or rename entries to match the actual design, and add any screen the design has that this list doesn't. Each screen lists the data it needs; that data defines `api.md`.

Legend: 🔓 works as guest · 🔒 needs login

## 1. Splash / app load 🔓
- App logo (static asset).
- On load: read token + onboarding state from storage, prefetch `GET /today`.

## 2. Onboarding (first launch only) 🔓
- Step 1: Veg or non-veg household (`vegetarian_only`).
- Step 2: Household size (1–10+).
- Step 3: Categories you don't like (multi-select chips) ← `GET /categories`.
- Saved locally; synced via `PUT /preferences` after login.

## 3. Today (tab, home) 🔓
- Greeting + today's date (e.g. "Tuesday, 13 October").
- **3 dish cards**, each: image, name, category name, total time (prep + cook), veg/non-veg badge, difficulty, favorite heart (🔒 action).
- Pull-to-refresh.
- Optional: "See tomorrow" link ← `GET /suggestions?date=`.
- ← `GET /today`

## 4. Dish detail 🔓
- Hero image, name, category, veg badge.
- Stats row: prep time, cook time, servings, difficulty, cost level.
- Description.
- Tags (e.g. quick, budget, kids favourite).
- Ingredients list: quantity + unit + name + note.
- Steps: number, instruction, optional image.
- Favorite button (🔒), share button.
- ← `GET /dishes/{slug}`

## 5. Browse / search (tab) 🔓
- Search box (debounced).
- Category chips (horizontal scroll) ← `GET /categories`.
- Veg-only toggle.
- Dish grid/list with infinite scroll; card = image, name, total time, veg badge.
- Empty state ("No dishes found").
- ← `GET /dishes?category=&search=&veg=&page=`

## 6. Favorites (tab) 🔒
- List of saved dish cards; swipe or tap heart to remove.
- Guest sees a "Log in to save dishes" prompt.
- ← `GET /favorites`, `POST /favorites`, `DELETE /favorites/{dish_id}`

## 7. Profile (tab) 🔓/🔒
- Guest: "Log in / Sign up" buttons, local preferences.
- Logged in: name, email/phone, preferences (veg-only, household size, disliked categories), notifications toggle, logout.
- ← `GET /me`, `GET/PUT /preferences`, `POST /auth/logout`

## 8. Login 🔓
- Email, password, submit, link to register.
- ← `POST /auth/login`

## 9. Register 🔓
- Name, email, phone (optional), password, confirm password.
- ← `POST /auth/register`

## 10. Push notification (not a screen)
- Daily at a set time: "Today's 3 dishes are ready 🍲". Tapping opens Today.
- ← `POST /devices` on app start (after permission granted).

## Shared components

| Component | Used on |
|---|---|
| `DishCard` (large) | Today |
| `DishCard` (compact) | Browse, Favorites |
| `CategoryChip` | Onboarding, Browse, Profile |
| `VegBadge` | Cards, Dish detail |
| `StatPill` (time, servings, difficulty) | Cards, Dish detail |
| `IngredientRow` | Dish detail |
| `StepItem` | Dish detail |
| `FavoriteButton` | Cards, Dish detail |
| `Button`, `TextInput`, `Text`, `Screen` | Everywhere |
| `Skeleton`, `EmptyState`, `ErrorState`, `OfflineBanner` | Everywhere |
