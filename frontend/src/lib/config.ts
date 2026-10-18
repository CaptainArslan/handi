/**
 * The ONE place the server address is defined: EXPO_PUBLIC_API_BASE_URL in frontend/.env
 * (e.g. http://192.168.1.20:8000 — no path). Everything else is derived from it:
 *   API_URL   → {base}/api/v1   (every API call, via src/api/client.ts)
 *   mediaUrl  → photos returned by the API, re-pointed at the same base
 */
import { Platform } from 'react-native';

/**
 * The web build served by Laravel at /app (npm run build:web) talks to the server it was loaded
 * from, so the same build works on any domain: it is built with EXPO_PUBLIC_API_BASE_URL=same-origin.
 */
const sameOrigin = Platform.OS === 'web' && typeof window !== 'undefined' && process.env.EXPO_PUBLIC_API_BASE_URL === 'same-origin';
const raw = sameOrigin ? window.location.origin : process.env.EXPO_PUBLIC_API_BASE_URL;

if (!raw) {
  throw new Error('EXPO_PUBLIC_API_BASE_URL is not set. Copy frontend/.env.example to frontend/.env, set it, and restart `npx expo start -c`.');
}

/** e.g. http://192.168.1.20:8000 */
export const BASE_URL = raw.trim().replace(/\/+$/, '').replace(/\/api(\/v1)?$/, '');

/** e.g. http://192.168.1.20:8000/api/v1 */
export const API_URL = `${BASE_URL}/api/v1`;

/** Hosts a phone can't reach: localhost, private LAN IPs, `.test` / `.local` dev domains. */
const LOCAL_HOST = /^https?:\/\/(?:localhost|127\.[\d.]+|10\.[\d.]+|192\.168\.[\d.]+|172\.(?:1[6-9]|2\d|3[01])\.[\d.]+|[^/:]+\.(?:test|local|localhost))(?::\d+)?(?:\/|$)/i;

/**
 * Server-made links (photos) carry the backend's APP_URL. In development that may be `localhost`
 * or a `.test` domain the phone can't reach, so those get BASE_URL's origin instead (path kept).
 * Real public URLs — the production server, a CDN or Cloudflare R2 — are used as they are.
 */
export function mediaUrl(url: string | null | undefined): string | null {
  if (!url) return null;
  if (url.startsWith('file:') || url.startsWith('content:') || url.startsWith('data:')) return url;
  if (/^https?:\/\//i.test(url) && !LOCAL_HOST.test(url)) return url;
  const path = url.replace(/^https?:\/\/[^/]+/i, '');
  return `${BASE_URL}${path.startsWith('/') ? '' : '/'}${path}`;
}

export const APP_VERSION = '1.0.0';
export const SUPPORT_EMAIL = 'support@handi.hegemonicinc.com';
