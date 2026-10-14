import { type AxiosError, create, isAxiosError } from 'axios';

import { API_URL } from '@/lib/config';
import { getToken } from '@/store/auth';

import type { ValidationErrorBody } from './types';

export const api = create({
  baseURL: API_URL,
  timeout: 15000,
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = getToken();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    // Token revoked (logged out elsewhere, password reset): end the session — but only if the
    // request was made with the CURRENT token (a late 401 from an old token must not log out a new login).
    const current = getToken();
    const sent = String(error.config?.headers?.Authorization ?? '');
    if (error.response?.status === 401 && current && sent === `Bearer ${current}` && !String(error.config?.url).includes('/auth/')) {
      const { endSession } = await import('@/lib/session');
      await endSession();
    }
    return Promise.reject(error);
  },
);

/** First message per field from a 422 response. */
export function fieldErrors(error: unknown): Record<string, string> {
  if (isAxiosError<ValidationErrorBody>(error) && error.response?.status === 422) {
    const errors = error.response.data?.errors ?? {};
    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? '']));
  }
  return {};
}

export function status(error: unknown): number | null {
  return isAxiosError(error) ? (error.response?.status ?? null) : null;
}

export function body(error: unknown): ValidationErrorBody | null {
  return isAxiosError<ValidationErrorBody>(error) ? (error.response?.data ?? null) : null;
}

/** No response at all (offline, server down, timeout). */
export function isNetworkError(error: unknown): boolean {
  return isAxiosError(error) && !error.response;
}

/** A short, human message for any request error. */
export function errorMessage(error: unknown): string {
  if (isAxiosError<ValidationErrorBody>(error)) {
    if (!error.response) return 'Can’t reach Handi. Check your internet connection and try again.';
    if (error.response.status >= 500) return 'Something went wrong on our side. Please try again.';
    return error.response.data?.message ?? 'Something went wrong.';
  }
  return 'Something went wrong.';
}
