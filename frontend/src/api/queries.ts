import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { useAuth } from '@/store/auth';

import { endpoints } from './endpoints';

/** Query keys in one place so mutations can invalidate them. */
export const qk = {
  me: ['me'] as const,
  today: ['today'] as const,
  dishes: (p?: { q?: string; category?: string }) => ['dishes', p ?? {}] as const,
  dish: (id: number) => ['dish', id] as const,
  custom: ['custom'] as const,
  meals: (month: string) => ['meals', month] as const,
  notifications: (filter: 'all' | 'unread') => ['notifications', filter] as const,
  notificationSettings: ['notification-settings'] as const,
  categories: ['categories'] as const,
  library: (p?: { q?: string; category?: string }) => ['library', p ?? {}] as const,
  cities: (q?: string) => ['cities', q ?? ''] as const,
  legal: (doc: 'terms' | 'privacy') => ['legal', doc] as const,
};

/** Re-renders when the user signs in or out, so queries start/stop with the session. */
const useSignedIn = () => useAuth((s) => !!s.token);

export const useMe = () => {
  const token = useAuth((s) => s.token);
  return useQuery({
    queryKey: qk.me,
    queryFn: async () => {
      const me = await endpoints.me();
      useAuth.getState().setUser(me);
      return me;
    },
    enabled: !!token,
  });
};

export const useToday = () => {
  const on = useSignedIn();
  return useQuery({ queryKey: qk.today, queryFn: endpoints.today, enabled: on, refetchOnMount: 'always' });
};

export const useDishes = (p?: { q?: string; category?: string }) => {
  const on = useSignedIn();
  return useQuery({ queryKey: qk.dishes(p), queryFn: () => endpoints.dishes(p), placeholderData: keepPreviousData, enabled: on });
};

export const useDish = (id: number) => {
  const on = useSignedIn();
  return useQuery({ queryKey: qk.dish(id), queryFn: () => endpoints.dish(id), enabled: on && id > 0 });
};

export const useCustomDishes = () => {
  const on = useSignedIn();
  return useQuery({ queryKey: qk.custom, queryFn: endpoints.customDishes, enabled: on });
};

export const useMeals = (month: string) => {
  const on = useSignedIn();
  return useQuery({ queryKey: qk.meals(month), queryFn: () => endpoints.meals(month), placeholderData: keepPreviousData, enabled: on });
};

export const useNotifications = (filter: 'all' | 'unread' = 'all') => {
  const on = useSignedIn();
  return useQuery({ queryKey: qk.notifications(filter), queryFn: () => endpoints.notifications(filter), enabled: on });
};

export const useNotificationSettings = () => {
  const on = useSignedIn();
  return useQuery({ queryKey: qk.notificationSettings, queryFn: endpoints.notificationSettings, enabled: on });
};

export const useCategories = () => useQuery({ queryKey: qk.categories, queryFn: endpoints.categories, staleTime: 60 * 60 * 1000 });

export const useLibrary = (p?: { q?: string; category?: string }) =>
  useQuery({ queryKey: qk.library(p), queryFn: () => endpoints.libraryDishes(p), staleTime: 10 * 60 * 1000, placeholderData: keepPreviousData });

export const useCities = (q?: string) =>
  useQuery({ queryKey: qk.cities(q), queryFn: () => endpoints.cities(q), staleTime: 60 * 60 * 1000, placeholderData: keepPreviousData });

export const useLegal = (doc: 'terms' | 'privacy') => useQuery({ queryKey: qk.legal(doc), queryFn: () => endpoints.legal(doc), staleTime: 24 * 60 * 60 * 1000 });
