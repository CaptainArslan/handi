import AsyncStorage from '@react-native-async-storage/async-storage';
import { router, type Href } from 'expo-router';
import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { Pressable, StyleSheet, View, type StyleProp, type TextStyle, type ViewStyle } from 'react-native';

import { endpoints, type LocationInput } from '@/api/endpoints';
import { qk } from '@/api/queries';
import { Icon, type IconName } from '@/components/ui/Icon';
import { Spinner } from '@/components/ui/Spinner';
import { Txt } from '@/components/ui/Txt';
import { queryClient } from '@/lib/query-client';
import type { DraftLocation } from '@/store/onboarding';
import { C } from '@/theme/tokens';

/** Set once the dish library has been downloaded (first launch needs internet once). */
export const LIBRARY_OK_KEY = 'handi.library-ok';

export async function hasLibrary(): Promise<boolean> {
  return (await AsyncStorage.getItem(LIBRARY_OK_KEY).catch(() => null)) === '1';
}

/** Download the whole library into the persisted query cache. Resolves false on failure. */
export async function downloadLibrary(): Promise<boolean> {
  try {
    await queryClient.fetchQuery({ queryKey: qk.library(), queryFn: () => endpoints.libraryDishes(), staleTime: 0 });
    await AsyncStorage.setItem(LIBRARY_OK_KEY, '1').catch(() => undefined);
    return true;
  } catch {
    return false;
  }
}

/** Onboarding draft location → API `location` body. */
export function locationInput(location: DraftLocation | null): LocationInput | null {
  if (!location) return null;
  return location.mode === 'city' ? { mode: 'city', city_id: location.city_id } : { mode: 'gps', lat: location.lat, lng: location.lng };
}

/** Back if there is somewhere to go back to, otherwise replace with `fallback`. */
export function goBack(fallback: Href) {
  if (router.canGoBack()) router.back();
  else router.replace(fallback);
}

/** Inline text link (the design's underlined green <a>). Nest inside a <Txt>. */
export function TextLink({
  children,
  onPress,
  weight = 600,
  color = C.primary,
  underline = true,
  style,
}: {
  children: string;
  onPress: () => void;
  weight?: 600 | 700;
  color?: string;
  underline?: boolean;
  style?: StyleProp<TextStyle>;
}) {
  return (
    <Txt
      onPress={onPress}
      accessibilityRole="link"
      weight={weight}
      color={color}
      style={[underline ? { textDecorationLine: 'underline' } : null, style]}
      suppressHighlighting>
      {children}
    </Txt>
  );
}

/** Full-width link button row (min-h 32/36/44) with an underlined green label. */
export function LinkButton({
  label,
  onPress,
  size = 14,
  weight = 600,
  minHeight = 36,
  underline = true,
  color = C.primary,
  align = 'center',
}: {
  label: string;
  onPress: () => void;
  size?: number;
  weight?: 600 | 700;
  minHeight?: number;
  underline?: boolean;
  color?: string;
  align?: 'center' | 'flex-start' | 'flex-end';
}) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="link"
      hitSlop={4}
      style={({ pressed }) => ({ minHeight, justifyContent: 'center', alignSelf: align === 'center' ? 'center' : align, opacity: pressed ? 0.7 : 1 })}>
      <Txt v="chip" size={size} weight={weight} color={color} style={underline ? { textDecorationLine: 'underline' } : null}>
        {label}
      </Txt>
    </Pressable>
  );
}

/** Alert row (gap 10, padding 12 14, radius 14, 14/1.4): danger (pink) or dark (locked). */
export function InlineAlert({ tone = 'danger', children, icon = 'alert' }: { tone?: 'danger' | 'dark'; children: ReactNode; icon?: IconName }) {
  const fg = tone === 'dark' ? C.white : C.dangerText;
  return (
    <View
      accessibilityRole="alert"
      accessibilityLiveRegion="polite"
      style={[styles.alert, { backgroundColor: tone === 'dark' ? C.ink : C.dangerSoft }]}>
      <View style={{ marginTop: 1 }}>
        <Icon name={icon} size={18} color={fg} strokeWidth={2.2} />
      </View>
      <Txt v="bodyS" lh={19.6} color={fg} style={{ flex: 1 }}>
        {children}
      </Txt>
    </View>
  );
}

/** Rounded square icon tile (64/22 on auth screens). */
export function IconTile({ icon, bg, color, size = 64, radius = 22, iconSize = 30, strokeWidth = 1.8 }: { icon: IconName; bg: string; color: string; size?: number; radius?: number; iconSize?: number; strokeWidth?: number }) {
  return (
    <View accessibilityElementsHidden importantForAccessibility="no-hide-descendants" style={{ width: size, height: size, borderRadius: radius, backgroundColor: bg, alignItems: 'center', justifyContent: 'center' }}>
      <Icon name={icon} size={iconSize} color={color} strokeWidth={strokeWidth} />
    </View>
  );
}

/**
 * Green CTA that stays green while busy (spinner + label), as in Location "Finding you…"
 * and No connection "Checking…". Use `Button` for everything else.
 */
export function BusyButton({ label, busy, onPress, style }: { label: string; busy: boolean; onPress: () => void; style?: StyleProp<ViewStyle> }) {
  return (
    <Pressable
      onPress={busy ? undefined : onPress}
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ busy, disabled: busy }}
      style={({ pressed }) => [styles.busy, { backgroundColor: pressed && !busy ? C.primaryPressed : C.primary }, style]}>
      {busy ? <Spinner size={18} /> : null}
      <Txt v="button" color={C.white}>
        {label}
      </Txt>
    </Pressable>
  );
}

/** Disabled grey CTA with a dark spinner ("Waiting for internet…"). */
export function WaitingButton({ label }: { label: string }) {
  return (
    <View accessibilityRole="button" accessibilityState={{ disabled: true, busy: true }} style={[styles.busy, { backgroundColor: C.disabledBg }]}>
      <Spinner size={16} color={C.ink2} track={C.dashed} />
      <Txt v="button" color={C.ink2}>
        {label}
      </Txt>
    </View>
  );
}

/**
 * Seconds-left countdown. `start(s)` (re)starts it from an event handler; pass `initial`
 * to start counting on mount.
 */
export function useCountdown(initial = 0) {
  const [left, setLeft] = useState(initial);
  const timer = useRef<ReturnType<typeof setInterval> | null>(null);

  const run = useCallback((secs: number) => {
    if (timer.current) clearInterval(timer.current);
    const end = Date.now() + secs * 1000;
    timer.current = setInterval(() => {
      const l = Math.max(0, Math.ceil((end - Date.now()) / 1000));
      setLeft(l);
      if (l <= 0 && timer.current) {
        clearInterval(timer.current);
        timer.current = null;
      }
    }, 250);
  }, []);

  const start = useCallback(
    (secs: number) => {
      setLeft(secs);
      run(secs);
    },
    [run],
  );

  useEffect(() => {
    if (initial > 0) run(initial);
    return () => {
      if (timer.current) clearInterval(timer.current);
    };
    // Only on mount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return { left, start };
}

const styles = StyleSheet.create({
  alert: { flexDirection: 'row', gap: 10, alignItems: 'flex-start', paddingVertical: 12, paddingHorizontal: 14, borderRadius: 14 },
  busy: { height: 56, borderRadius: 16, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, alignSelf: 'stretch' },
});
