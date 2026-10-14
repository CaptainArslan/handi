import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

/**
 * Small key/value store for secrets (auth token, device id).
 * Phones: Keychain / Keystore via expo-secure-store. Web: expo-secure-store has no web
 * implementation, so the browser's storage (AsyncStorage → localStorage) is used instead.
 */
const web = Platform.OS === 'web';

export const secure = {
  get: (key: string): Promise<string | null> => (web ? AsyncStorage.getItem(key) : SecureStore.getItemAsync(key)),
  set: (key: string, value: string): Promise<void> => (web ? AsyncStorage.setItem(key, value) : SecureStore.setItemAsync(key, value)),
  remove: (key: string): Promise<void> => (web ? AsyncStorage.removeItem(key) : SecureStore.deleteItemAsync(key)),
};
