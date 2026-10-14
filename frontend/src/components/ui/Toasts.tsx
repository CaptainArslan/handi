import { useEffect, useRef, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import Animated, { Easing, useAnimatedStyle, useSharedValue, withDelay, withTiming } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { create } from 'zustand';

import { useNet } from '@/store/net';
import { C, S } from '@/theme/tokens';

import { Icon } from './Icon';
import { Txt } from './Txt';

// ---------------------------------------------------------------- Flash (8.4)

export interface FlashMessage {
  title: string;
  sub?: string;
  tone?: 'success' | 'info';
  /** e.g. Undo. Runs `onPress`, then shows `{title: after ?? 'Undone', tone: 'info'}` for 2.5s. */
  action?: { label: string; onPress: () => void | Promise<void>; after?: string };
}

interface FlashState {
  message: (FlashMessage & { id: number }) | null;
  show: (m: FlashMessage) => void;
  hide: () => void;
}

export const useFlash = create<FlashState>((set) => ({
  message: null,
  show: (m) => set({ message: { ...m, id: Date.now() } }),
  hide: () => set({ message: null }),
}));

/** Show the top confirmation toast (call right before/after navigating). */
export const flash = (m: FlashMessage) => useFlash.getState().show(m);

/** Global host — render once in the root layout. */
export function FlashHost() {
  const insets = useSafeAreaInsets();
  const message = useFlash((s) => s.message);
  const hide = useFlash((s) => s.hide);
  const [shown, setShown] = useState<FlashMessage | null>(null);
  const y = useSharedValue(-160);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [seen, setSeen] = useState<number | null>(null);

  if (message && message.id !== seen) {
    setSeen(message.id);
    setShown(message);
  }

  const slideOut = () => {
    y.value = withTiming(-160, { duration: 450, easing: Easing.bezier(0.2, 0.8, 0.2, 1) });
  };

  useEffect(() => {
    if (!message) {
      slideOut();
      return;
    }
    y.value = withDelay(250, withTiming(0, { duration: 450, easing: Easing.bezier(0.2, 0.8, 0.2, 1) }));
    if (timer.current) clearTimeout(timer.current);
    timer.current = setTimeout(hide, 5000 + 250);
    return () => {
      if (timer.current) clearTimeout(timer.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [message]);

  const style = useAnimatedStyle(() => ({
    transform: [{ translateY: `${y.value}%` }],
    opacity: y.value <= -150 ? 0 : 1,
  }));

  if (!shown) return null;
  const success = (shown.tone ?? 'success') === 'success';

  return (
    <Animated.View pointerEvents="box-none" style={[styles.flashWrap, { top: insets.top + 8 }, style]}>
      <View style={[styles.toast, S.toast, { paddingVertical: 10, paddingLeft: 12, paddingRight: 8 }]} accessibilityLiveRegion="polite" accessibilityRole="alert">
        <View style={[styles.iconCircle, { backgroundColor: success ? C.success : C.accent }]}>
          {success ? <Icon name="check" size={18} color={C.white} strokeWidth={3} /> : <Icon name="info" size={18} color={C.ink} strokeWidth={2.4} />}
        </View>
        <View style={{ flex: 1, gap: 1 }}>
          <Txt v="toastTitle" color={C.white} numberOfLines={1}>
            {shown.title}
          </Txt>
          {shown.sub ? (
            <Txt v="toastSub" color={C.onDarkMuted} numberOfLines={1}>
              {shown.sub}
            </Txt>
          ) : null}
        </View>
        {shown.action ? (
          <Pressable
            style={styles.action}
            accessibilityRole="button"
            onPress={async () => {
              const a = shown.action!;
              await a.onPress();
              const next = { title: a.after ?? 'Undone', tone: 'info' as const };
              setShown(next);
              if (timer.current) clearTimeout(timer.current);
              timer.current = setTimeout(hide, 2500);
            }}>
            <Txt v="toastTitle" color={C.accent}>
              {shown.action.label}
            </Txt>
          </Pressable>
        ) : null}
        <Pressable style={styles.dismiss} onPress={hide} accessibilityRole="button" accessibilityLabel="Dismiss">
          <Icon name="close" size={16} color={C.onDarkMuted} strokeWidth={2.4} />
        </Pressable>
      </View>
    </Animated.View>
  );
}

// ---------------------------------------------------------------- NetToast (8.2)

/**
 * Offline / back-online bottom toast. Render inside a screen with that screen's bottom offset
 * (Today 100, Dishes 172, History 172, Settings 100, Signup 168).
 */
export function NetToast({ bottom = 96, offlineSub, delay = 350 }: { bottom?: number; offlineSub?: string; delay?: number }) {
  const online = useNet((s) => s.online);
  const recheck = useNet((s) => s.recheck);
  const [mode, setMode] = useState<'hidden' | 'offline' | 'online'>('hidden');
  const [checking, setChecking] = useState(false);
  const wasOffline = useRef(false);
  const y = useSharedValue(140);

  useEffect(() => {
    let t: ReturnType<typeof setTimeout> | undefined;
    if (!online) {
      wasOffline.current = true;
      t = setTimeout(() => setMode('offline'), delay);
    } else if (wasOffline.current) {
      wasOffline.current = false;
      setMode('online');
      t = setTimeout(() => setMode('hidden'), 3500);
    } else {
      setMode('hidden');
    }
    return () => {
      if (t) clearTimeout(t);
    };
  }, [online, delay]);

  useEffect(() => {
    y.value = withTiming(mode === 'hidden' ? 140 : 0, { duration: 400, easing: Easing.bezier(0.2, 0.8, 0.2, 1) });
  }, [mode, y]);

  const style = useAnimatedStyle(() => ({ transform: [{ translateY: `${y.value}%` }], opacity: y.value >= 130 ? 0 : 1 }));

  const isOffline = mode === 'offline';
  return (
    <Animated.View pointerEvents={mode === 'hidden' ? 'none' : 'box-none'} style={[styles.netWrap, { bottom }, style]}>
      <View style={[styles.toast, S.toast, { padding: 10, paddingLeft: 12 }]} accessibilityLiveRegion="polite">
        <View style={[styles.iconCircle, { backgroundColor: isOffline ? C.ink2 : C.success }]}>
          <Icon name={isOffline ? 'wifiOff' : 'wifi'} size={18} color={isOffline ? C.accent : C.white} strokeWidth={2.2} />
        </View>
        <View style={{ flex: 1, gap: 1 }}>
          <Txt v="toastTitle" color={C.white}>
            {isOffline ? 'You’re offline' : 'Back online'}
          </Txt>
          <Txt v="toastSub" color={C.onDarkMuted} numberOfLines={1}>
            {isOffline ? (checking ? 'Checking your connection…' : offlineSub ?? 'Showing saved data. Changes sync later.') : 'Your internet connection was restored.'}
          </Txt>
        </View>
        {isOffline ? (
          <Pressable
            style={styles.action}
            disabled={checking}
            accessibilityRole="button"
            onPress={async () => {
              setChecking(true);
              await new Promise((r) => setTimeout(r, 1200));
              await recheck();
              setChecking(false);
            }}>
            <Txt v="toastTitle" color={C.accent}>
              {checking ? '…' : 'Retry'}
            </Txt>
          </Pressable>
        ) : (
          <Pressable style={styles.dismiss} onPress={() => setMode('hidden')} accessibilityRole="button" accessibilityLabel="Dismiss">
            <Icon name="close" size={16} color={C.onDarkMuted} strokeWidth={2.4} />
          </Pressable>
        )}
      </View>
    </Animated.View>
  );
}

// ---------------------------------------------------------------- Snackbar with Undo (2.9)

export interface SnackbarProps {
  message: string | null;
  onUndo: () => void;
  onHide: () => void;
  bottom?: number;
  duration?: number;
  /** History style: filled amber Undo button and #1C1A17 background. */
  filled?: boolean;
  side?: number;
}

export function Snackbar({ message, onUndo, onHide, bottom = 100, duration = 5000, filled, side = 12 }: SnackbarProps) {
  const y = useSharedValue(160);
  const [text, setText] = useState(message);
  if (message && message !== text) setText(message);

  useEffect(() => {
    if (message) {
      y.value = withTiming(0, { duration: 400, easing: Easing.bezier(0.2, 0.8, 0.2, 1) });
      const t = setTimeout(onHide, duration);
      return () => clearTimeout(t);
    }
    y.value = withTiming(160, { duration: 300 });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [message]);

  const style = useAnimatedStyle(() => ({ transform: [{ translateY: `${y.value}%` }], opacity: y.value >= 150 ? 0 : 1 }));

  return (
    <Animated.View pointerEvents={message ? 'box-none' : 'none'} style={[styles.netWrap, { bottom, left: side, right: side }, style]}>
      <View
        style={[styles.toast, S.toast, { paddingVertical: 10, paddingLeft: 16, paddingRight: 10, backgroundColor: filled ? C.ink : C.inkToast }]}
        accessibilityLiveRegion="polite">
        <Txt v="bodyS" color={C.white} style={{ flex: 1 }} numberOfLines={2}>
          {text}
        </Txt>
        <Pressable
          onPress={onUndo}
          accessibilityRole="button"
          style={filled ? styles.undoFilled : styles.action}>
          <Txt v="toastTitle" color={filled ? C.ink : C.accent}>
            Undo
          </Txt>
        </Pressable>
      </View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  flashWrap: { position: 'absolute', left: 12, right: 12, zIndex: 70, elevation: 30 },
  netWrap: { position: 'absolute', left: 12, right: 12, zIndex: 60, elevation: 25 },
  toast: { flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: 16, backgroundColor: C.inkToast },
  iconCircle: { width: 36, height: 36, borderRadius: 18, alignItems: 'center', justifyContent: 'center' },
  action: { height: 40, paddingHorizontal: 12, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  undoFilled: { height: 44, paddingHorizontal: 14, borderRadius: 12, backgroundColor: C.accent, alignItems: 'center', justifyContent: 'center' },
  dismiss: { width: 40, height: 40, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
});
