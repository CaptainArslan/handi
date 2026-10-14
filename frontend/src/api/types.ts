/** Shapes returned by the Handi API (/api/v1). See docs/api.md. */

export type Budget = 'low' | 'normal' | 'high';
export type MealType = 'breakfast' | 'lunch' | 'dinner';
export type UsualMeal = 'breakfast' | 'lunch' | 'dinner' | 'both';
export type WeatherKind = 'hot' | 'mild' | 'cold' | 'rainy';
export type WeatherTag = WeatherKind | 'any';
export type Effort = 'quick' | 'medium' | 'heavy';
export type Cost = 'low' | 'medium' | 'high';
export type LogSource = 'suggestion' | 'manual';

export interface CategoryRef {
  key: string;
  name: string;
}

export interface Category extends CategoryRef {
  image_key: string | null;
}

export interface Weather {
  temp_now: number | null;
  temp_high: number | null;
  rain_chance: number | null;
  condition: WeatherKind;
  available: boolean;
  fetched_at: string | null;
  city_label: string | null;
  /** "31° · hot · rain 10%" */
  summary: string;
  /** "It’s hot today. How about something light?" */
  headline: string;
  province?: string | null;
}

export interface LibraryDish {
  id: number;
  name: string;
  category: CategoryRef | null;
  note: string | null;
  image_url: string | null;
  /** 240×240 WebP for lists and small tiles (falls back to image_url). */
  thumb_url?: string | null;
  image_key: string | null;
  image_bg: string;
  default_weather: WeatherTag[];
  default_effort: Effort;
  default_cost: Cost;
  usual_meal: UsualMeal;
}

export interface PreviewPick extends LibraryDish {
  reason: string | null;
}

export interface City {
  id: number;
  name: string;
  province: string;
  province_short: string;
  is_popular: boolean;
}

export interface CitiesResponse {
  popular: City[];
  provinces: { name: string; short_name: string; cities: City[] }[];
}

export interface KitchenLocation {
  mode: 'gps' | 'city' | null;
  city_id: number | null;
  city: string | null;
  province: string | null;
  province_short: string | null;
  label: string | null;
}

export interface Kitchen {
  family_size: number;
  budget: Budget;
  repeat_gap_days: 3 | 5 | 7 | 10;
  meals: MealType[];
  location: KitchenLocation;
  notifications: { enabled: boolean; time: string };
  last_synced_at: string | null;
}

export interface Me {
  id: number;
  name: string;
  first_name: string;
  initial: string;
  email: string;
  masked_email: string;
  timezone: string;
  password_changed_at: string | null;
  created_at: string;
  kitchen: Kitchen;
  counts: { dishes: number; custom_dishes: number; unread_notifications: number };
}

/** A dish in the user's kitchen (library or custom). */
export interface KitchenDish {
  id: number;
  name: string;
  category: CategoryRef | null;
  is_custom: boolean;
  image_url: string | null;
  /** 240×240 WebP for lists and small tiles (falls back to image_url). */
  thumb_url?: string | null;
  image_key: string | null;
  image_bg: string;
  weather: WeatherTag[];
  effort: Effort;
  cost: Cost;
  usual_meal: UsualMeal;
  last_cooked_at: string | null;
  days_since_cooked: number | null;
  removed: boolean;
}

export interface KitchenDishDetail extends KitchenDish {
  library_dish_id: number | null;
  note: string | null;
  has_overrides: boolean;
  library_defaults: { weather: WeatherTag[]; effort: Effort; cost: Cost } | null;
  next_eligible_at: string | null;
  photo_status: 'none' | 'processing' | 'ready';
  has_photo: boolean;
}

export interface TodayPick extends KitchenDish {
  reason: string | null;
  is_top: boolean;
}

export interface MealLog {
  id: string;
  kitchen_dish_id: number | null;
  dish_name: string;
  category_key: string | null;
  image_url: string | null;
  /** 240×240 WebP for lists and small tiles (falls back to image_url). */
  thumb_url?: string | null;
  image_key: string | null;
  image_bg: string;
  date: string;
  meal: MealType;
  source: LogSource;
  logged_at: string;
  /** Client-only: still in the offline queue. */
  pending?: boolean;
}

export interface Stats {
  streak_days: number;
  meals_this_month: number;
}

export interface Today {
  date: string;
  greeting_name: string;
  initial: string;
  unread_notifications: number;
  weather: Weather | null;
  generated_at: string | null;
  stage: 'pick' | 'planned' | 'done';
  shuffles_left: number;
  shuffles_per_day: number;
  picks: TodayPick[];
  plan: { dish: KitchenDish; meal: 'lunch' | 'dinner'; picked_at: string | null } | null;
  log: MealLog | null;
  stats: Stats;
  repeat_gap_days: number;
  reminder: { enabled: boolean; time: string };
  next_picks_at: string;
}

export interface MonthHistory {
  month: string;
  logged_days: number;
  days_elapsed: number;
  min_date: string;
  max_date: string;
  days: Record<string, MealLog[]>;
}

export interface AppNotification {
  id: number;
  type: 'daily_picks' | 'sync' | 'photo_ready' | 'kitchen_ready' | 'account';
  title: string;
  body: string;
  cta_label: string | null;
  deep_link: string | null;
  image_url: string | null;
  image_key: string | null;
  image_bg: string | null;
  read_at: string | null;
  created_at: string;
}

export interface NotificationSettings {
  enabled: boolean;
  time: string;
}

/** GET /image-prompt — the AI photo prompt users copy into Gemini (edited in the admin). */
export interface ImagePromptSettings {
  enabled: boolean;
  /** Replace every occurrence with the dish name. */
  placeholder: string;
  intro: string;
  template: string;
  ai_name: string;
  ai_url: string;
  updated_at: string | null;
}

export interface LegalDoc {
  title: string;
  summary: string;
  sections: { heading: string; body: string }[];
  updated_at: string;
  support_email: string;
}

export interface Session {
  token: string;
  user: Me;
}

export interface ValidationErrorBody {
  message: string;
  errors?: Record<string, string[]>;
  attempts_left?: number;
  retry_after?: number;
}
