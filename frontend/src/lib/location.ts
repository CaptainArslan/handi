import * as Location from 'expo-location';

/** Ask for permission and read the position, rounded to ~1 km (2 decimals). */
export async function locate(): Promise<{ lat: number; lng: number } | 'denied' | 'error'> {
  try {
    const { status } = await Location.requestForegroundPermissionsAsync();
    if (status !== 'granted') return 'denied';
    const pos = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
    return { lat: Math.round(pos.coords.latitude * 100) / 100, lng: Math.round(pos.coords.longitude * 100) / 100 };
  } catch {
    return 'error';
  }
}
