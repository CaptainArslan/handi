/**
 * The ONE place the server address is defined: EXPO_PUBLIC_API_BASE_URL in frontend/.env
 * (e.g. http://192.168.1.20:8000 — no path). Everything else is derived from it:
 *   API_URL   → {base}/api/v1   (every API call, via src/api/client.ts)
 *   mediaUrl  → photos returned by the API, re-pointed at the same base
 */
const raw = process.env.EXPO_PUBLIC_API_BASE_URL;

if (!raw) {
  throw new Error('EXPO_PUBLIC_API_BASE_URL is not set. Copy frontend/.env.example to frontend/.env, set it, and restart `npx expo start -c`.');
}

/** e.g. http://192.168.1.20:8000 */
export const BASE_URL = raw.trim().replace(/\/+$/, '').replace(/\/api(\/v1)?$/, '');

/** e.g. http://192.168.1.20:8000/api/v1 */
export const API_URL = `${BASE_URL}/api/v1`;

/**
 * Server-made links (photos) carry the backend's APP_URL, which may be `localhost`
 * or a `.test` domain the phone can't reach. Keep the path, swap the origin for BASE_URL.
 */
export function mediaUrl(url: string | null | undefined): string | null {
  if (!url) return null;
  if (url.startsWith('file:') || url.startsWith('content:') || url.startsWith('data:')) return url;
  const path = url.replace(/^https?:\/\/[^/]+/i, '');
  return `${BASE_URL}${path.startsWith('/') ? '' : '/'}${path}`;
}

export const APP_VERSION = '1.0.0';
export const SUPPORT_EMAIL = 'support@handi.app';
