import { AxiosError, AxiosHeaders, create, isAxiosError, type InternalAxiosRequestConfig } from 'axios';

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

/**
 * POST multipart/form-data with fetch. Axios can't reliably send React Native FormData files on
 * Android, so photo uploads use fetch. Failures are re-thrown as AxiosErrors, so
 * `errorMessage()` and `fieldErrors()` treat them exactly like other requests.
 */
export async function postMultipart<T>(path: string, form: FormData, timeoutMs = 60000): Promise<T> {
  const url = `${API_URL}${path}`;
  const token = getToken();
  const config = { url, method: 'post', headers: new AxiosHeaders() } as InternalAxiosRequestConfig;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  let res: Response;
  try {
    res = await fetch(url, {
      method: 'POST',
      // No Content-Type: fetch adds multipart/form-data with the right boundary.
      headers: { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: form,
      signal: controller.signal,
    });
  } catch (e) {
    throw new AxiosError(e instanceof Error ? e.message : 'Network Error', 'ERR_NETWORK', config);
  } finally {
    clearTimeout(timer);
  }
  const text = await res.text();
  let data: unknown = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = { message: text.slice(0, 200) };
  }
  if (!res.ok) {
    const response = { data, status: res.status, statusText: res.statusText, headers: {}, config } as never;
    if (res.status === 401 && token) {
      const { endSession } = await import('@/lib/session');
      await endSession();
    }
    throw new AxiosError(`Request failed with status code ${res.status}`, 'ERR_BAD_RESPONSE', config, null, response);
  }
  return data as T;
}

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

/** A short, human message for any request error — the server's own message whenever it sent one. */
export function errorMessage(error: unknown): string {
  if (isAxiosError<ValidationErrorBody & { exception?: string; file?: string; line?: number }>(error)) {
    const res = error.response;
    if (!res) {
      const why = __DEV__ ? ` (${error.code ?? 'no response'}: ${error.config?.baseURL ?? ''}${error.config?.url ?? ''})` : '';
      return `Can’t reach Handi. Check your internet connection and try again.${why}`;
    }
    const data = res.data;
    // 422: the first field error is the most useful line.
    const firstField = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined;
    const serverMsg = (typeof data?.message === 'string' && data.message.trim()) || '';
    if (res.status === 413) return serverMsg || 'That photo is too large to upload. Try a smaller one.';
    if (res.status === 422) return firstField || serverMsg || 'Please check the form and try again.';
    if (res.status >= 500) {
      // Production servers only say "Server Error"; with APP_DEBUG=true Laravel sends the real exception.
      const real = serverMsg && serverMsg !== 'Server Error' ? serverMsg : '';
      const where = __DEV__ && data?.exception ? `\n${data.exception.split('\\').pop()}${data.file ? ` · ${data.file.split(/[\\/]/).pop()}:${data.line}` : ''}` : '';
      return real ? `Server error (${res.status}): ${real}${where}` : `Something went wrong on our side (${res.status}). Please try again.`;
    }
    return serverMsg || `Request failed (${res.status}).`;
  }
  // Not a request error (a bug in the app): show the real reason while developing.
  if (__DEV__ && error instanceof Error) return `Something went wrong: ${error.message}`;
  return 'Something went wrong.';
}
