import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { focusManager, onlineManager, QueryClient } from '@tanstack/react-query';
import { isAxiosError } from 'axios';
import { AppState, Platform } from 'react-native';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      gcTime: 7 * 24 * 60 * 60 * 1000, // keep for the persisted cache (offline use)
      staleTime: 30 * 1000,
      retry: (failureCount, error) => {
        if (isAxiosError(error) && error.response && error.response.status < 500) return false;
        return failureCount < 2;
      },
    },
  },
});

/** Persist what the app needs offline: Today, dishes, history, library, cities, me. */
export const persister = createAsyncStoragePersister({
  storage: AsyncStorage,
  key: 'handi.query-cache',
  throttleTime: 2000,
});

export const PERSISTED_QUERY_ROOTS = new Set(['me', 'today', 'dishes', 'custom', 'dish', 'meals', 'categories', 'library', 'cities', 'notifications', 'legal']);

onlineManager.setEventListener((setOnline) => NetInfo.addEventListener((state) => setOnline(state.isConnected !== false)));

if (Platform.OS !== 'web') {
  AppState.addEventListener('change', (status) => focusManager.setFocused(status === 'active'));
}
