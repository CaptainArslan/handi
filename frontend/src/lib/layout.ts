import { Platform, useWindowDimensions } from 'react-native';

/** On web the app renders as a phone-width column so the mobile design never stretches. */
export const APP_MAX_WIDTH = 440;
export const isWeb = Platform.OS === 'web';

/** Width of the app column (window width on phones, capped on web/tablets). */
export function useAppWidth() {
  const { width } = useWindowDimensions();
  return Math.min(width, APP_MAX_WIDTH);
}
