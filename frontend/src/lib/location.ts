import * as Location from 'expo-location';
import { Linking, Platform } from 'react-native';

/**
 * Why we couldn't get a position:
 * - `denied`       the user said no (can ask again)
 * - `blocked`      permission is off and the system won't show the prompt again → open Settings
 * - `services-off` permission is fine but the phone's Location (GPS) switch is off
 * - `unavailable`  everything is allowed but no fix came back in time (indoors, emulator with no location set…)
 */
export type LocateFailure = 'denied' | 'blocked' | 'services-off' | 'unavailable';
export type LocateResult = { lat: number; lng: number } | LocateFailure;

const FIX_TIMEOUT_MS = 12000;

const round = (pos: Location.LocationObject) => ({
  lat: Math.round(pos.coords.latitude * 100) / 100,
  lng: Math.round(pos.coords.longitude * 100) / 100,
});

function withTimeout<T>(p: Promise<T>, ms: number): Promise<T | null> {
  return Promise.race([p, new Promise<null>((resolve) => setTimeout(() => resolve(null), ms))]);
}

/** Ask for permission and read the position, rounded to ~1 km (2 decimals). */
export async function locate(): Promise<LocateResult> {
  // 1. Permission
  let perm = await Location.getForegroundPermissionsAsync().catch(() => null);
  if (perm?.status !== 'granted') {
    if (perm && !perm.canAskAgain) return 'blocked';
    perm = await Location.requestForegroundPermissionsAsync().catch(() => null);
    if (perm?.status !== 'granted') return perm && !perm.canAskAgain ? 'blocked' : 'denied';
  }

  // 2. Phone's location switch (Android can show the "turn on location" dialog for us)
  let servicesOn = await Location.hasServicesEnabledAsync().catch(() => true);
  if (!servicesOn && Platform.OS === 'android') {
    await Location.enableNetworkProviderAsync().catch(() => undefined);
    servicesOn = await Location.hasServicesEnabledAsync().catch(() => false);
  }
  if (!servicesOn) return 'services-off';

  // 3. A position: fresh fix first, then the last known one (fine for weather at ~1 km)
  try {
    const fresh = await withTimeout(Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced }), FIX_TIMEOUT_MS);
    if (fresh) return round(fresh);
  } catch {
    // fall through to last known
  }
  const last = await Location.getLastKnownPositionAsync({ maxAge: 6 * 60 * 60 * 1000 }).catch(() => null);
  return last ? round(last) : 'unavailable';
}

/** Copy for each failure (same tone as the design). */
export function locateMessage(reason: LocateFailure): string {
  switch (reason) {
    case 'blocked':
      return 'Location is blocked for Handi. Turn it on in Settings, or choose your city instead. It works just as well.';
    case 'services-off':
      return 'Your phone’s location is turned off. Switch it on and try again, or choose your city instead.';
    case 'unavailable':
      return 'Couldn’t find you right now. Try again in a moment, or choose your city instead.';
    default:
      return 'Location permission is off. No problem, choose your city instead. It works just as well.';
  }
}

/** Open this app's page in system Settings (to allow location after "Don't ask again"). */
export const openAppSettings = () => Linking.openSettings().catch(() => undefined);
