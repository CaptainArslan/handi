import { useEffect, useState, type ReactNode } from 'react';
import { Modal, Pressable, StyleSheet, View, type LayoutChangeEvent } from 'react-native';
import { Gesture, GestureDetector, GestureHandlerRootView } from 'react-native-gesture-handler';
import Animated, { Easing, runOnJS, useAnimatedStyle, useSharedValue, withSpring, withTiming } from 'react-native-reanimated';
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

/** Drag down past this share of the sheet's height (at most DISMISS_MAX_PX), or flick faster than DISMISS_VELOCITY, to close it. */
const DISMISS_SHARE = 0.25;
const DISMISS_MAX_PX = 140;
const DISMISS_VELOCITY = 900;

/** Bottom sheet: scrim fade .2s, slide-up .32s ease-out, radius 28 28 0 0. Drag it down to close. */
export function Sheet({ visible, onClose, children, handle = false, scrim = 'default', gap = 14, center, accessibilityLabel }: SheetProps) {
  const insets = useSafeAreaInsets();
  const [mounted, setMounted] = useState(visible);
  const y = useSharedValue(1); // 0 = open, 1 = fully below the screen (share of the sheet's height)
  const drag = useSharedValue(0); // px the finger has pulled the sheet down
  const height = useSharedValue(0); // measured sheet height
  const fade = useSharedValue(0);

  if (visible && !mounted) setMounted(true);

  useEffect(() => {
    if (visible) {
      drag.value = 0;
      fade.value = withTiming(1, { duration: 200 });
      y.value = withTiming(0, { duration: 320, easing: Easing.bezier(0.2, 0.8, 0.2, 1) });
    } else if (mounted) {
      fade.value = withTiming(0, { duration: 200 });
      y.value = withTiming(1, { duration: 240, easing: Easing.in(Easing.quad) }, (done) => {
        if (done) runOnJS(setMounted)(false);
      });
    }
  }, [visible, mounted, fade, y, drag]);

  // Pull down to close: follows the finger, closes once pulled far enough (or on a quick flick), otherwise springs back.
  const pan = Gesture.Pan()
    .activeOffsetY(8)
    .failOffsetX([-24, 24])
    .onUpdate((e) => {
      drag.set(Math.max(0, e.translationY));
    })
    .onEnd((e) => {
      const h = height.get() || 1;
      if (e.translationY > Math.min(h * DISMISS_SHARE, DISMISS_MAX_PX) || e.velocityY > DISMISS_VELOCITY) {
        // Hand the current position to `y`, so the closing slide continues from where the finger let go.
        y.set(Math.min(1, drag.get() / h));
        drag.set(0);
        runOnJS(onClose)();
      } else {
        drag.set(withSpring(0, { damping: 22, stiffness: 260 }));
      }
    });

  const scrimStyle = useAnimatedStyle(() => {
    const h = height.value || 1;
    return { opacity: fade.value * (1 - Math.min(1, drag.value / h) * 0.6) };
  });
  const sheetStyle = useAnimatedStyle(() => ({
    // Until measured, park it well below the screen so it never flashes in place.
    transform: [{ translateY: y.value * (height.value || 2000) + drag.value }],
  }));
  const measure = (e: LayoutChangeEvent) => {
    height.set(e.nativeEvent.layout.height);
  };

  if (!mounted) return null;

  return (
    <Modal transparent visible statusBarTranslucent animationType="none" onRequestClose={onClose}>
      {/* A Modal is its own native root on Android, so gestures inside it need their own handler root. */}
      <GestureHandlerRootView style={{ flex: 1 }}>
        <Animated.View style={[StyleSheet.absoluteFill, { backgroundColor: scrim === 'light' ? C.scrimLight : C.scrim }, scrimStyle]}>
          <Pressable style={StyleSheet.absoluteFill} onPress={onClose} accessibilityLabel="Close" />
        </Animated.View>
        <View style={styles.bottom} pointerEvents="box-none">
          <GestureDetector gesture={pan}>
            <Animated.View
              accessibilityViewIsModal
              accessibilityLabel={accessibilityLabel}
              onLayout={measure}
              style={[
                styles.sheet,
                { paddingTop: handle ? 12 : 24, paddingBottom: 36 + insets.bottom, gap, alignItems: center ? 'center' : 'stretch' },
                sheetStyle,
              ]}>
              {handle ? <View style={styles.handle} /> : null}
              {children}
            </Animated.View>
          </GestureDetector>
        </View>
      </GestureHandlerRootView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  bottom: { flex: 1, justifyContent: 'flex-end' },
  // maxWidth keeps the sheet inside the phone-width column on web.
  sheet: { backgroundColor: C.white, borderTopLeftRadius: 28, borderTopRightRadius: 28, paddingHorizontal: 20, width: '100%', maxWidth: APP_MAX_WIDTH, alignSelf: 'center' },
  handle: { alignSelf: 'center', width: 40, height: 5, borderRadius: 99, backgroundColor: C.disabledBg, marginBottom: 2 },
});
