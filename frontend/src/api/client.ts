import { AxiosError, AxiosHeaders, create, isAxiosError, type InternalAxiosRequestConfig } from 'axios';
import * as LegacyFS from 'expo-file-system/legacy';
import { Platform } from 'react-native';

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
  const config = { url, method: 'post', headers: new AxiosHeaders() } as InternalAxiosRequestConfig;

  // One automatic retry: mobile connections often drop the first large upload after the app was in
  // the background (e.g. while the photo picker was open). A timeout is not retried.
  let res: Response | null = null;
  for (let attempt = 1; attempt <= 2 && !res; attempt++) {
    const token = getToken();
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
      res = await fetch(url, {
        method: 'POST',
        // No Content-Type: fetch adds multipart/form-data with the right boundary.
        headers: { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
        body: form,
        signal: controller.signal,
      });
    } catch (e) {
      const timedOut = controller.signal.aborted;
      const reason = timedOut ? `timeout of ${timeoutMs}ms exceeded` : e instanceof Error ? e.message : 'Network Error';
      console.warn(`[upload] POST ${url} failed (attempt ${attempt}): ${reason}`);
      if (timedOut || attempt === 2) throw new AxiosError(reason, timedOut ? 'ECONNABORTED' : 'ERR_NETWORK', config);
      await new Promise((r) => setTimeout(r, 800));
    } finally {
      clearTimeout(timer);
    }
  }
  if (!res) throw new AxiosError('Network Error', 'ERR_NETWORK', config);

  return finishUpload<T>(res.status, res.statusText, await res.text(), config);
}

/** Turn an upload's raw response into data, or an AxiosError carrying the server's JSON (401 ends the session). */
async function finishUpload<T>(statusCode: number, statusText: string, text: string, config: InternalAxiosRequestConfig): Promise<T> {
  let data: unknown = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = { message: text.slice(0, 200) };
  }
  if (statusCode < 200 || statusCode >= 300) {
    const response = { data, status: statusCode, statusText, headers: {}, config } as never;
    if (statusCode === 401 && getToken()) {
      const { endSession } = await import('@/lib/session');
      await endSession();
    }
    throw new AxiosError(`Request failed with status code ${statusCode}`, 'ERR_BAD_RESPONSE', config, null, response);
  }
  return data as T;
}

export type UploadFields = Record<string, string | string[] | null | undefined>;

/**
 * Upload one file (multipart, field `photo` by default) plus plain form fields.
 *
 * On phones this uses Expo's native uploader (expo-file-system), which streams the file straight from
 * disk. It is more reliable than React Native's FormData networking, which on some Android release
 * builds fails with "Network request failed" before the request ever reaches the server.
 * In the browser it falls back to fetch + FormData. Errors are AxiosErrors, like every other request.
 */
export async function uploadFile<T>(path: string, file: { uri: string; name: string; type: string }, fields: UploadFields = {}, fieldName = 'photo'): Promise<T> {
  if (Platform.OS === 'web') {
    const form = new FormData();
    for (const [k, v] of Object.entries(fields)) {
      if (Array.isArray(v)) v.forEach((x) => form.append(`${k}[]`, x));
      else if (v != null) form.append(k, v);
    }
    const blob = await (await fetch(file.uri)).blob();
    form.append(fieldName, new File([blob], file.name, { type: file.type || blob.type }));
    return postMultipart<T>(path, form);
  }

  const url = `${API_URL}${path}`;
  const config = { url, method: 'post', headers: new AxiosHeaders() } as InternalAxiosRequestConfig;
  const parameters: Record<string, string> = {};
  for (const [k, v] of Object.entries(fields)) {
    if (Array.isArray(v)) v.forEach((x, i) => (parameters[`${k}[${i}]`] = x));
    else if (v != null) parameters[k] = v;
  }

  for (let attempt = 1; ; attempt++) {
    const token = getToken();
    try {
      const res = await LegacyFS.uploadAsync(url, file.uri, {
        httpMethod: 'POST',
        uploadType: LegacyFS.FileSystemUploadType.MULTIPART,
        fieldName,
        mimeType: file.type || 'image/jpeg',
        parameters,
        headers: { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      });
      return finishUpload<T>(res.status, '', res.body, config);
    } catch (e) {
      if (isAxiosError(e)) throw e; // a real server answer (422, 413, 500…)
      const reason = e instanceof Error ? e.message : 'Network Error';
      console.warn(`[upload] POST ${url} failed (attempt ${attempt}): ${reason}`);
      if (attempt >= 2) throw new AxiosError(reason, 'ERR_NETWORK', config);
      await new Promise((r) => setTimeout(r, 800));
    }
  }
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
      // Always say why (e.g. "Network request failed", "timeout of 15000ms exceeded") so problems can be reported;
      // the address is added only while developing.
      const where = __DEV__ ? ` · ${error.config?.baseURL ?? ''}${error.config?.url ?? ''}` : '';
      const why = ` (${error.message || error.code || 'no response'}${where})`;
      if (error.code === 'ECONNABORTED' || /timeout/i.test(error.message)) return `The server took too long to answer. Please try again.${why}`;
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
