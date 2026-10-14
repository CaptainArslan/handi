import { useEffect, useState, type ReactNode } from 'react';
import { Modal, Pressable, StyleSheet, View } from 'react-native';
import Animated, { Easing, runOnJS, useAnimatedStyle, useSharedValue, withTiming } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { APP_MAX_WIDTH } from '@/lib/layout';
import { C } from '@/theme/tokens';

export interface SheetProps {
  visible: boolean;
  onClose: () => void;
  children: ReactNode;
  /** Grab handle at the top (padding 12 20 36) — otherwise padding 24 20 36. */
  handle?: boolean;
  /** Scrim .5 (default) or .45 (light). */
  scrim?: 'default' | 'light';
  gap?: number;
  center?: boolean;
  accessibilityLabel?: string;
}

/** Bottom sheet: scrim fade .2s, slide-up .32s ease-out, radius 28 28 0 0. */
export function Sheet({ visible, onClose, children, handle = false, scrim = 'default', gap = 14, center, accessibilityLabel }: SheetProps) {
  const insets = useSafeAreaInsets();
  const [mounted, setMounted] = useState(visible);
  const y = useSharedValue(1);
  const fade = useSharedValue(0);

  if (visible && !mounted) setMounted(true);

  useEffect(() => {
    if (visible) {
      fade.value = withTiming(1, { duration: 200 });
      y.value = withTiming(0, { duration: 320, easing: Easing.bezier(0.2, 0.8, 0.2, 1) });
    } else if (mounted) {
      fade.value = withTiming(0, { duration: 200 });
      y.value = withTiming(1, { duration: 240, easing: Easing.in(Easing.quad) }, (done) => {
        if (done) runOnJS(setMounted)(false);
      });
    }
  }, [visible, mounted, fade, y]);

  const scrimStyle = useAnimatedStyle(() => ({ opacity: fade.value }));
  const sheetStyle = useAnimatedStyle(() => ({ transform: [{ translateY: `${y.value * 100}%` }] }));

  if (!mounted) return null;

  return (
    <Modal transparent visible statusBarTranslucent animationType="none" onRequestClose={onClose}>
      <Animated.View style={[StyleSheet.absoluteFill, { backgroundColor: scrim === 'light' ? C.scrimLight : C.scrim }, scrimStyle]}>
        <Pressable style={StyleSheet.absoluteFill} onPress={onClose} accessibilityLabel="Close" />
      </Animated.View>
      <View style={styles.bottom} pointerEvents="box-none">
        <Animated.View
          accessibilityViewIsModal
          accessibilityLabel={accessibilityLabel}
          style={[
            styles.sheet,
            { paddingTop: handle ? 12 : 24, paddingBottom: 36 + insets.bottom, gap, alignItems: center ? 'center' : 'stretch' },
            sheetStyle,
          ]}>
          {handle ? <View style={styles.handle} /> : null}
          {children}
        </Animated.View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  bottom: { flex: 1, justifyContent: 'flex-end' },
  // maxWidth keeps the sheet inside the phone-width column on web.
  sheet: { backgroundColor: C.white, borderTopLeftRadius: 28, borderTopRightRadius: 28, paddingHorizontal: 20, width: '100%', maxWidth: APP_MAX_WIDTH, alignSelf: 'center' },
  handle: { alignSelf: 'center', width: 40, height: 5, borderRadius: 99, backgroundColor: C.disabledBg, marginBottom: 2 },
});
