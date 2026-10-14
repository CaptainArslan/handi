import Constants, { ExecutionEnvironment } from 'expo-constants';
import * as Device from 'expo-device';
import { secure } from '@/lib/secure';
import { Platform } from 'react-native';

import { endpoints } from '@/api/endpoints';

type NotificationsModule = typeof import('expo-notifications');

const DEVICE_ID_KEY = 'handi.device-id';

/**
 * Remote push isn't available in Expo Go (removed in SDK 53) or on web, so there
 * expo-notifications is never loaded and the rest of the app works normally.
 * Use a development build (`npx expo run:android` / EAS) to test push.
 */
const isExpoGo = Constants.executionEnvironment === ExecutionEnvironment.StoreClient;
export const pushSupported = Platform.OS !== 'web' && !isExpoGo;

let cached: NotificationsModule | null = null;

function notifications(): NotificationsModule | null {
  if (!pushSupported) return null;
  if (!cached) {
    // Loaded lazily on purpose: importing it at the top would run it inside Expo Go too.
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    cached = require('expo-notifications') as NotificationsModule;
    cached.setNotificationHandler({
      handleNotification: async () => ({ shouldShowBanner: true, shouldShowList: true, shouldPlaySound: false, shouldSetBadge: false }),
    });
  }
  return cached;
}

export async function deviceId(): Promise<string> {
  const existing = await secure.get(DEVICE_ID_KEY);
  if (existing) return existing;
  const id = `${Platform.OS}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
  await secure.set(DEVICE_ID_KEY, id);
  return id;
}

export const deviceName = () => Device.deviceName ?? Device.modelName ?? (Platform.OS === 'ios' ? 'iPhone' : 'Android phone');

export type PushPermission = 'granted' | 'denied' | 'undetermined' | 'unsupported';

export async function pushPermission(): Promise<PushPermission> {
  const N = notifications();
  if (!N) return 'unsupported';
  const { status } = await N.getPermissionsAsync();
  return status as PushPermission;
}

/**
 * Ask for permission (only if `ask`), get the Expo push token and register this phone
 * with the API. Without push (Expo Go, denied) the phone is still registered so it shows
 * in the account's phone list. Safe to call on every start and after login.
 */
export async function registerDevice(ask = false): Promise<PushPermission> {
  // A browser is not a phone: nothing to register (the API accepts android | ios only).
  if (Platform.OS === 'web') return 'unsupported';
  const N = notifications();
  let token: string | null = null;
  let permission: PushPermission = 'unsupported';

  if (N) {
    try {
      if (Platform.OS === 'android') {
        await N.setNotificationChannelAsync('daily', { name: 'Daily picks', importance: N.AndroidImportance.DEFAULT });
      }
      let { status } = await N.getPermissionsAsync();
      if (status !== 'granted' && ask) ({ status } = await N.requestPermissionsAsync());
      permission = status as PushPermission;

      const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
      if (status === 'granted' && projectId) {
        token = (await N.getExpoPushTokenAsync({ projectId })).data;
      } else if (status === 'granted' && __DEV__) {
        console.info('[push] No EAS projectId yet (run `npx eas-cli init`) — registering without a push token.');
      }
    } catch (error) {
      if (__DEV__) console.warn('[push] permission/token failed', error);
    }
  }

  try {
    await endpoints.registerDevice({
      device_id: await deviceId(),
      expo_push_token: token,
      platform: Platform.OS === 'ios' ? 'ios' : 'android',
      name: deviceName(),
    });
  } catch {
    // offline — next start retries
  }
  return permission;
}

/** Run `callback(deepLink)` when the user taps a notification. Returns an unsubscribe. */
export function onNotificationTap(callback: (deepLink: string | null) => void): () => void {
  const N = notifications();
  if (!N) return () => undefined;
  const sub = N.addNotificationResponseReceivedListener((r) => {
    const link = (r.notification.request.content.data as { link?: string } | undefined)?.link ?? null;
    callback(link);
  });
  return () => sub.remove();
}

/**
 * Deep link of the notification that launched the app (cold start), once.
 * `undefined` = the app was not opened from a notification.
 */
export async function lastNotificationLink(): Promise<string | null | undefined> {
  const N = notifications();
  if (!N) return undefined;
  try {
    const r = await N.getLastNotificationResponseAsync();
    if (!r) return undefined;
    await N.clearLastNotificationResponseAsync?.();
    return (r.notification.request.content.data as { link?: string } | undefined)?.link ?? null;
  } catch {
    return undefined;
  }
}
