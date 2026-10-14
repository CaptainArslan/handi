import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, { Keyframe } from 'react-native-reanimated';

import { BusyButton, downloadLibrary } from '@/components/AuthKit';
import { useBottom, useTop } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import { Txt } from '@/components/ui/Txt';
import { API_URL } from '@/lib/config';
import { useNet } from '@/store/net';
import { C } from '@/theme/tokens';

const TIPS = ['Check Wi-Fi or mobile data is on', 'Turn off airplane mode', 'After this first load, the app works offline'];

const pop = new Keyframe({
  0: { opacity: 0, transform: [{ scale: 0.6 }] },
  70: { opacity: 1, transform: [{ scale: 1.08 }] },
  100: { opacity: 1, transform: [{ scale: 1 }] },
}).duration(450);

const wait = (ms: number) => new Promise((r) => setTimeout(r, ms));

export default function NoConnection() {
  const top = useTop(72);
  const bottom = useBottom(40);
  const recheck = useNet((s) => s.recheck);
  const [tries, setTries] = useState(0);
  const [checking, setChecking] = useState(false);
  const [online, setOnline] = useState(false);
  // The phone has internet but our server didn't answer: a server/address problem, not Wi-Fi.
  const [serverIssue, setServerIssue] = useState(false);

  useEffect(() => {
    recheck()
      .then(setServerIssue)
      .catch(() => undefined);
  }, [recheck]);

  const retry = async () => {
    if (checking) return;
    setChecking(true);
    const [isOnline] = await Promise.all([recheck().catch(() => false), wait(1400)]);
    const ok = isOnline ? await downloadLibrary() : false;
    setServerIssue(isOnline && !ok);
    setChecking(false);
    if (ok) setOnline(true);
    else setTries((t) => t + 1);
  };

  const statusText = checking ? 'Checking connection…' : tries > 0 && !online ? `Still no connection · tried ${tries} ${tries === 1 ? 'time' : 'times'}` : '';

  return (
    <View style={[styles.screen, { paddingTop: top, paddingBottom: bottom }]}>
      <View style={styles.center}>
        {online ? (
          <Animated.View entering={pop} style={[styles.tile, { backgroundColor: C.primary }]} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
            <Icon name="check" size={60} color={C.white} strokeWidth={2.2} />
          </Animated.View>
        ) : (
          <View style={[styles.tile, { backgroundColor: C.ink, transform: [{ rotate: '-4deg' }] }]} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
            <Icon name="wifiOff" size={60} color={C.accent} strokeWidth={1.6} />
          </View>
        )}

        <View style={{ gap: 10, alignItems: 'center' }}>
          <Txt v="h1" align="center" accessibilityRole="header">
            {online ? 'You’re back online' : serverIssue ? 'Can’t reach Handi' : 'You’re offline'}
          </Txt>
          <Txt v="bodyL" lh={24.8} color={C.muted} align="center" style={{ maxWidth: 310 }}>
            {online
              ? 'Dish library downloaded. From now on, today’s picks and your history work even without internet.'
              : serverIssue
                ? 'You’re connected, but our server isn’t answering right now. Please try again in a moment.'
                : 'We need internet once to download the dish library. It’s small, under 1 MB.'}
          </Txt>
        </View>

        {!online && serverIssue && __DEV__ ? (
          <View style={styles.devHint}>
            <Txt v="bodyS" weight={600} color={C.ink}>
              Developer: the API didn’t answer
            </Txt>
            <Txt v="bodyS" color={C.ink2}>
              {`Tried ${API_URL}\nCheck EXPO_PUBLIC_API_BASE_URL in frontend/.env (emulator: http://10.0.2.2:8000, phone: your PC's LAN IP), that \`php artisan serve --host=0.0.0.0 --port=8000\` is running, then restart with \`npx expo start -c\`.`}
            </Txt>
          </View>
        ) : null}

        {!online && !serverIssue ? (
          <View style={styles.tips}>
            {TIPS.map((t) => (
              <View key={t} style={{ flexDirection: 'row', gap: 10 }}>
                <View style={styles.bullet} />
                <Txt v="bodyS" color={C.ink2} style={{ flex: 1 }}>
                  {t}
                </Txt>
              </View>
            ))}
          </View>
        ) : null}
      </View>

      <Txt v="helper" color={C.muted} align="center" style={{ minHeight: 18 }} accessibilityLiveRegion="polite">
        {statusText}
      </Txt>
      {online ? (
        <Button label="Continue" weight={600} onPress={() => router.replace('/welcome')} />
      ) : (
        <BusyButton label={checking ? 'Checking…' : 'Try again'} busy={checking} onPress={retry} />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg, paddingHorizontal: 24, gap: 24 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 28 },
  tile: { width: 132, height: 132, borderRadius: 44, alignItems: 'center', justifyContent: 'center' },
  tips: { alignSelf: 'stretch', padding: 16, borderRadius: 18, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, gap: 12 },
  devHint: { alignSelf: 'stretch', padding: 14, borderRadius: 14, backgroundColor: '#FDF0D9', gap: 6 },
  bullet: { width: 8, height: 8, borderRadius: 4, backgroundColor: C.accent, marginTop: 6 },
});
