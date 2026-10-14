import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import Animated, { Easing, useAnimatedStyle, useSharedValue, withRepeat, withTiming } from 'react-native-reanimated';

import { endpoints } from '@/api/endpoints';
import type { Weather } from '@/api/types';
import { BusyButton, goBack, InlineAlert } from '@/components/AuthKit';
import { FormScroll, Heading, SetupHeader, Spacer } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import { Txt } from '@/components/ui/Txt';
import { locate, locateMessage, openAppSettings, type LocateFailure } from '@/lib/location';
import { useOnboarding } from '@/store/onboarding';
import { C } from '@/theme/tokens';

type Mode = 'ask' | 'locating' | 'done' | 'denied';

export default function SetupLocation() {
  const location = useOnboarding((s) => s.location);
  const set = useOnboarding((s) => s.set);
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState<LocateFailure | null>(null);
  const denied = failure !== null;
  const mode: Mode = busy ? 'locating' : location ? 'done' : denied ? 'denied' : 'ask';

  const findMe = async () => {
    setBusy(true);
    const pos = await locate();
    if (typeof pos === 'string') {
      setFailure(pos);
      setBusy(false);
      return;
    }
    let weather: Weather | null = null;
    try {
      weather = await endpoints.weather({ lat: pos.lat, lng: pos.lng });
    } catch {
      weather = null;
    }
    const city = weather?.city_label ?? null;
    set({
      location: { mode: 'gps', lat: pos.lat, lng: pos.lng, label: city ? `${city} · near you` : 'Near you', city: city ?? undefined, weather },
    });
    setFailure(null);
    setBusy(false);
  };

  return (
    <FormScroll gap={22}>
      <SetupHeader step={4} onBack={() => goBack('/setup/preferences')} />
      <PinHero />
      <Heading
        title="Cook for today’s weather"
        v="h1Compact"
        gap={8}
        subSize={15}
        sub="Hot day, light food. Rainy day, pakoras. We round your location to about 1 km and never share it."
      />

      {mode === 'denied' && failure ? <InlineAlert>{locateMessage(failure)}</InlineAlert> : null}

      {mode === 'done' && location ? (
        <View style={styles.located} accessibilityRole="summary" accessibilityLiveRegion="polite">
          <View style={styles.okDot}>
            <Icon name="check" size={16} color={C.white} strokeWidth={3} />
          </View>
          <View style={{ flex: 1 }}>
            <Txt v="itemTitle" color={C.primaryPressed} numberOfLines={1}>
              {location.label}
            </Txt>
            <Txt v="helper" color={C.primaryPressed}>
              Weather updates every hour
            </Txt>
          </View>
          <Pressable onPress={() => set({ location: null })} accessibilityRole="button" style={styles.change}>
            <Txt v="chip" color={C.primaryPressed} style={{ textDecorationLine: 'underline' }}>
              Change
            </Txt>
          </Pressable>
        </View>
      ) : null}

      <Spacer />
      {mode === 'done' ? (
        <Button label="See today’s picks" weight={600} onPress={() => router.push('/setup/preview')} />
      ) : (
        <View style={{ gap: 12 }}>
          <BusyButton
            label={mode === 'locating' ? 'Finding you…' : failure === 'blocked' ? 'Open Settings' : mode === 'denied' ? 'Try location again' : 'Use my location'}
            busy={mode === 'locating'}
            onPress={failure === 'blocked' ? openAppSettings : findMe}
          />
          <Button label="Choose city instead" weight={600} variant={mode === 'denied' ? 'primary' : 'outline'} onPress={() => router.push('/setup/city')} />
        </View>
      )}
    </FormScroll>
  );
}

function PinHero() {
  const p = useSharedValue(0);
  useEffect(() => {
    p.value = withRepeat(withTiming(1, { duration: 1600, easing: Easing.out(Easing.ease) }), -1, false);
  }, [p]);
  const ping = useAnimatedStyle(() => ({ opacity: 0.7 * (1 - p.value), transform: [{ scale: 0.6 + 0.9 * p.value }] }));
  return (
    <View style={styles.hero} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
      <Animated.View style={[styles.circle, { width: 150, height: 150, backgroundColor: C.mintRing }, ping]} />
      <View style={[styles.circle, { width: 110, height: 110, backgroundColor: C.mint }]} />
      <View style={styles.pinTile}>
        <Icon name="pin" size={34} color={C.white} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  hero: { height: 170, alignItems: 'center', justifyContent: 'center' },
  circle: { position: 'absolute', borderRadius: 999 },
  pinTile: {
    width: 72,
    height: 72,
    borderRadius: 24,
    backgroundColor: C.primary,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: 'rgb(15,92,77)',
    shadowOpacity: 0.3,
    shadowRadius: 15,
    shadowOffset: { width: 0, height: 14 },
    elevation: 8,
  },
  located: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, paddingHorizontal: 16, borderRadius: 16, backgroundColor: C.mint },
  okDot: { width: 32, height: 32, borderRadius: 16, backgroundColor: C.primary, alignItems: 'center', justifyContent: 'center' },
  change: { height: 44, paddingHorizontal: 12, justifyContent: 'center' },
});
