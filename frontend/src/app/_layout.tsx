import { Poppins_400Regular, Poppins_500Medium, Poppins_600SemiBold, Poppins_700Bold, useFonts } from '@expo-google-fonts/poppins';
import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client';
import { router, Stack, useSegments } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { ConfirmHost } from '@/components/ui/Confirm';
import { FlashHost } from '@/components/ui/Toasts';
import { APP_MAX_WIDTH, isWeb } from '@/lib/layout';
import { linkToHref } from '@/lib/links';
import { lastNotificationLink, onNotificationTap, registerDevice } from '@/lib/notifications';
import { PERSISTED_QUERY_ROOTS, persister, queryClient } from '@/lib/query-client';
import { LOADER_BG } from '@/lib/splash';
import { useAuth } from '@/store/auth';
import { useQueue } from '@/store/queue';
import { C } from '@/theme/tokens';

SplashScreen.preventAutoHideAsync().catch(() => undefined);
// Cross-fade from the white native splash into the loader screen instead of cutting.
try {
  SplashScreen.setOptions({ duration: 350, fade: true });
} catch {
  // older native splash module: falls back to a plain hide
}

/** Routes anyone can open. Everything else needs a session. */
const PUBLIC = new Set(['', 'index', 'welcome', 'setup', 'signup', 'login', 'forgot-password', 'verify-code', 'reset-password', 'no-connection', 'legal', '+not-found']);
/** Signed-in users are sent past these to Today. */
const GUEST_ONLY = new Set(['welcome', 'setup', 'signup', 'login', 'forgot-password', 'verify-code', 'reset-password', 'no-connection']);

export default function RootLayout() {
  const [fontsLoaded, fontError] = useFonts({
    Poppins_400Regular,
    Poppins_500Medium,
    Poppins_600SemiBold,
    Poppins_700Bold,
  });
  const hydrated = useAuth((s) => s.hydrated);
  const token = useAuth((s) => s.token);
  const [queueReady, setQueueReady] = useState(() => useQueue.persist.hasHydrated());
  const ready = (fontsLoaded || !!fontError) && hydrated;
  const segments = useSegments();
  const first: string = segments[0] ?? '';

  useEffect(() => {
    useAuth.getState().hydrate();
    return useQueue.persist.onFinishHydration(() => setQueueReady(true));
  }, []);

  // The loader screen ("/") hides the native splash itself once its first frame is drawn, so there is
  // no blank screen in between. Any other first screen (e.g. opened from a notification) hides it here.
  useEffect(() => {
    if (!ready) return;
    if (first !== '' && first !== 'index') {
      SplashScreen.hideAsync().catch(() => undefined);
      return;
    }
    // Safety net: never leave the native splash up if the loader screen couldn't hide it.
    const t = setTimeout(() => SplashScreen.hideAsync().catch(() => undefined), 4000);
    return () => clearTimeout(t);
  }, [ready, first]);

  // Auth guard: no session on a private screen → Log in; a session on a guest screen → Today.
  // (The splash screen at "/" routes itself, so it is left alone.)
  useEffect(() => {
    if (!ready || first === '' || first === 'index') return;
    if (!token && !PUBLIC.has(first)) router.replace('/login');
    else if (token && GUEST_ONLY.has(first) && first !== 'setup') router.replace('/today');
  }, [ready, token, first]);

  // Signed in: link this phone (push token if allowed) and send anything queued offline.
  useEffect(() => {
    if (ready && token && queueReady) {
      void registerDevice(false);
      void useQueue.getState().flush();
    }
  }, [ready, token, queueReady]);

  // Notifications: the tap that launched the app, and taps while it runs.
  useEffect(() => {
    if (!ready) return;
    const go = (link: string | null) => {
      if (!useAuth.getState().token) return;
      const href = linkToHref(link ?? '/today');
      if (href) router.navigate(href);
    };
    void lastNotificationLink().then((link) => link !== undefined && go(link));
    return onNotificationTap(go);
  }, [ready]);

  if (!ready) return null;

  const app = (
    <>
      <StatusBar style="dark" />
      <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: C.bg }, animation: 'slide_from_right' }}>
        <Stack.Screen name="index" options={{ animation: 'none', contentStyle: { backgroundColor: LOADER_BG } }} />
        <Stack.Screen name="(tabs)" options={{ animation: 'fade' }} />
        <Stack.Screen name="welcome" options={{ animation: 'fade' }} />
        <Stack.Screen name="add-dish" options={{ animation: 'slide_from_bottom' }} />
        <Stack.Screen name="log-meal" options={{ animation: 'slide_from_bottom' }} />
      </Stack>
      <ConfirmHost />
      <FlashHost />
    </>
  );

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <PersistQueryClientProvider
          client={queryClient}
          persistOptions={{
            persister,
            maxAge: 7 * 24 * 60 * 60 * 1000,
            buster: 'v2',
            dehydrateOptions: {
              shouldDehydrateQuery: (query) => query.state.status === 'success' && PERSISTED_QUERY_ROOTS.has(String(query.queryKey[0])),
            },
          }}>
          {isWeb ? (
            // Web: the same phone design, centred as a column — never stretched across the browser.
            <View style={styles.webOuter}>
              <View style={styles.webFrame}>{app}</View>
            </View>
          ) : (
            app
          )}
        </PersistQueryClientProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}

const styles = StyleSheet.create({
  webOuter: { flex: 1, backgroundColor: C.sand2, alignItems: 'center' },
  webFrame: {
    flex: 1,
    width: '100%',
    maxWidth: APP_MAX_WIDTH,
    backgroundColor: C.bg,
    overflow: 'hidden',
  },
});
