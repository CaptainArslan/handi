import { Platform } from 'react-native';

import { api, postMultipart } from './client';
import type {
  AppNotification,
  Budget,
  Category,
  CitiesResponse,
  Cost,
  Effort,
  Kitchen,
  KitchenDish,
  KitchenDishDetail,
  ImagePromptSettings,
  LegalDoc,
  LibraryDish,
  MealLog,
  MealType,
  Me,
  MonthHistory,
  NotificationSettings,
  PreviewPick,
  Session,
  Stats,
  Today,
  UsualMeal,
  Weather,
  WeatherTag,
} from './types';

type Data<T> = { data: T };
const d = <T>(p: Promise<{ data: Data<T> }>) => p.then((r) => r.data.data);

export interface LocationInput {
  mode: 'gps' | 'city';
  city_id?: number;
  lat?: number;
  lng?: number;
}

export interface RegisterInput {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  terms_accepted: boolean;
  device_name: string;
  timezone: string;
  kitchen: { family_size: number; budget: Budget; repeat_gap_days: number; meals: MealType[] };
  location: LocationInput | null;
  dish_ids: number[];
  custom_dishes: { name: string; category: string | null }[];
}

export interface CustomDishInput {
  name?: string;
  category?: string;
  weather?: WeatherTag[];
  usual_meal?: UsualMeal;
  effort?: Effort;
  cost?: Cost;
  /** Local file uri from the image picker. */
  photo?: { uri: string; name: string; type: string } | null;
}

export interface MealInput {
  id?: string;
  kitchen_dish_id: number;
  date: string;
  meal: MealType;
  source?: 'suggestion' | 'manual';
}

type PhotoInput = { uri: string; name: string; type: string };

/** A file part for FormData: `{uri,name,type}` on phones, a real File in the browser. */
async function filePart(photo: PhotoInput): Promise<Blob> {
  if (Platform.OS === 'web') {
    const blob = await (await fetch(photo.uri)).blob();
    return new File([blob], photo.name, { type: photo.type || blob.type });
  }
  return photo as unknown as Blob;
}

async function customDishForm(input: CustomDishInput): Promise<FormData> {
  const form = new FormData();
  if (input.name !== undefined) form.append('name', input.name);
  if (input.category !== undefined) form.append('category', input.category);
  input.weather?.forEach((w) => form.append('weather[]', w));
  if (input.usual_meal) form.append('usual_meal', input.usual_meal);
  if (input.effort) form.append('effort', input.effort);
  if (input.cost) form.append('cost', input.cost);
  if (input.photo) form.append('photo', await filePart(input.photo));
  return form;
}

export const endpoints = {
  // ---------------------------------------------------------------- Public
  libraryDishes: (params?: { q?: string; category?: string }) => d<LibraryDish[]>(api.get('/library/dishes', { params })),
  categories: () => d<Category[]>(api.get('/categories')),
  cities: (q?: string) => d<CitiesResponse>(api.get('/cities', { params: q ? { q } : undefined })),
  weather: (params: { city_id?: number; lat?: number; lng?: number }) => d<Weather>(api.get('/weather', { params })),
  legal: (doc: 'terms' | 'privacy') => d<LegalDoc>(api.get(`/legal/${doc}`)),
  imagePrompt: () => d<ImagePromptSettings>(api.get('/image-prompt')),
  previewPicks: (body: { dish_ids: number[]; location: LocationInput | null; family_size?: number; budget?: Budget | null; repeat_gap_days?: number; meals?: MealType[] }) =>
    d<{ date: string; weather: Weather | null; picks: PreviewPick[] }>(api.post('/preview-picks', body)),

  // ---------------------------------------------------------------- Auth
  register: (body: RegisterInput) => d<Session>(api.post('/auth/register', body)),
  login: (body: { email: string; password: string; device_name: string }) => d<Session>(api.post('/auth/login', body)),
  logout: () => api.post('/auth/logout'),
  forgot: (email: string) => d<{ masked_email: string; expires_in: number; resend_in: number }>(api.post('/auth/password/forgot', { email })),
  resend: (email: string) => d<{ masked_email: string; expires_in: number; resend_in: number }>(api.post('/auth/password/resend', { email })),
  verifyCode: (email: string, code: string) => d<{ reset_token: string }>(api.post('/auth/password/verify', { email, code })),
  resetPassword: (body: { reset_token: string; password: string; password_confirmation: string }) => api.post('/auth/password/reset', body),

  // ---------------------------------------------------------------- Account
  me: () => d<Me>(api.get('/me')),
  updateMe: (body: { name?: string; email?: string; current_password?: string }) =>
    api.patch<{ data: Me; meta: { email_change_pending: boolean } }>('/me', body).then((r) => r.data),
  verifyEmail: (code: string) => d<Me>(api.post('/me/email/verify', { code })),
  changePassword: (body: { current_password: string; password: string; password_confirmation: string; sign_out_other_devices: boolean }) =>
    api.put('/me/password', body),
  deleteAccount: (body: { password: string; confirmation: string }) => api.delete('/me', { data: body }),

  // ---------------------------------------------------------------- Kitchen settings
  updateKitchen: (body: Partial<Pick<Kitchen, 'family_size' | 'budget' | 'repeat_gap_days' | 'meals'>>) => d<Kitchen>(api.patch('/me/kitchen', body)),
  setLocation: (body: LocationInput) => d<{ kitchen: Kitchen; weather: Weather | null }>(api.put('/me/location', body)),
  registerDevice: (body: { device_id: string; expo_push_token?: string | null; platform: 'android' | 'ios'; name?: string }) => api.post('/me/devices', body),
  removeDevice: (deviceId: string) => api.delete(`/me/devices/${encodeURIComponent(deviceId)}`),
  notificationSettings: () => d<NotificationSettings>(api.get('/me/notification-settings')),
  updateNotificationSettings: (body: NotificationSettings) => d<NotificationSettings>(api.put('/me/notification-settings', body)),
  testNotification: () => d<{ sent: number; devices: number }>(api.post('/me/notification-settings/test')),

  // ---------------------------------------------------------------- Today
  today: () => d<Today>(api.get('/me/today')),
  shuffle: () => d<Today>(api.post('/me/today/shuffle')),
  plan: (kitchen_dish_id: number, meal: 'lunch' | 'dinner') => d<Today>(api.put('/me/today/plan', { kitchen_dish_id, meal })),
  unplan: () => d<Today>(api.delete('/me/today/plan')),

  // ---------------------------------------------------------------- My dishes
  dishes: (params?: { q?: string; category?: string; custom?: boolean }) =>
    api
      .get<{ data: KitchenDish[]; meta: { total: number; custom_count: number; matches: number } }>('/me/dishes', {
        params: params ? { ...params, custom: params.custom === undefined ? undefined : params.custom ? 1 : 0 } : undefined,
      })
      .then((r) => r.data),
  addLibraryDishes: (library_dish_ids: number[]) => d<{ added: number }>(api.post('/me/dishes', { library_dish_ids })),
  dish: (id: number) => d<KitchenDishDetail>(api.get(`/me/dishes/${id}`)),
  updateDish: (id: number, body: { weather?: WeatherTag[]; effort?: Effort; cost?: Cost }) => d<KitchenDishDetail>(api.patch(`/me/dishes/${id}`, body)),
  resetDish: (id: number) => d<KitchenDishDetail>(api.delete(`/me/dishes/${id}/overrides`)),
  uploadDishPhoto: async (id: number, photo: PhotoInput) => {
    const form = new FormData();
    form.append('photo', await filePart(photo));
    return postMultipart<Data<KitchenDishDetail>>(`/me/dishes/${id}/photo`, form).then((r) => r.data);
  },
  removeDish: (id: number) => api.delete(`/me/dishes/${id}`),
  restoreDish: (id: number) => d<KitchenDishDetail>(api.post(`/me/dishes/${id}/restore`)),

  customDishes: () => d<KitchenDish[]>(api.get('/me/custom-dishes')),
  createCustomDish: async (input: CustomDishInput) =>
    input.photo
      ? postMultipart<Data<KitchenDishDetail>>('/me/custom-dishes', await customDishForm(input)).then((r) => r.data)
      : d<KitchenDishDetail>(api.post('/me/custom-dishes', { ...input, photo: undefined })),
  updateCustomDish: async (id: number, input: CustomDishInput) => {
    const dish = await d<KitchenDishDetail>(api.patch(`/me/custom-dishes/${id}`, { ...input, photo: undefined }));
    return input.photo ? endpoints.uploadDishPhoto(id, input.photo) : dish;
  },
  removeCustomDish: (id: number) => api.delete(`/me/custom-dishes/${id}`),
  restoreCustomDish: (id: number) => d<KitchenDishDetail>(api.post(`/me/custom-dishes/${id}/restore`)),

  // ---------------------------------------------------------------- History
  meals: (month?: string) => d<MonthHistory>(api.get('/me/meals', { params: month ? { month } : undefined })),
  logMeal: (body: MealInput) => api.post<{ data: MealLog; meta: { duplicate: boolean } }>('/me/meals', body).then((r) => r.data),
  updateMeal: (id: string, body: Partial<MealInput>) => api.patch<{ data: MealLog; meta: { duplicate: boolean } }>(`/me/meals/${id}`, body).then((r) => r.data),
  deleteMeal: (id: string) => api.delete(`/me/meals/${id}`),
  restoreMeal: (id: string) => d<MealLog>(api.post(`/me/meals/${id}/restore`)),
  stats: () => d<Stats>(api.get('/me/stats')),
  sync: (operations: { op: 'meal.create' | 'meal.delete'; payload: Record<string, unknown> }[]) =>
    d<{ results: { ok: boolean; id?: string; error?: string; duplicate?: boolean }[]; synced_at: string }>(api.post('/me/sync', { operations })),

  // ---------------------------------------------------------------- Notifications
  notifications: (filter: 'all' | 'unread' = 'all') =>
    api.get<{ data: AppNotification[]; meta: { unread_count: number } }>('/me/notifications', { params: { filter } }).then((r) => r.data),
  readNotification: (id: number, read = true) => d<AppNotification>(api.patch(`/me/notifications/${id}`, { read })),
  readAllNotifications: () => api.post('/me/notifications/read-all'),
  clearNotification: (id: number) => api.delete(`/me/notifications/${id}`),
  restoreNotification: (id: number) => d<AppNotification>(api.post(`/me/notifications/${id}/restore`)),
};
