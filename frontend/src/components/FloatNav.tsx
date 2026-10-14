import type { BottomTabBarProps } from 'expo-router/tabs';
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import Animated, { Easing, useAnimatedStyle, useSharedValue, withDelay, withTiming } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Icon, type IconName } from '@/components/ui/Icon';
import { useUi } from '@/store/ui';
import { C, S } from '@/theme/tokens';

const TABS: Record<string, { icon: IconName; label: string }> = {
  today: { icon: 'home', label: 'Today' },
  dishes: { icon: 'bowl', label: 'Dishes' },
  history: { icon: 'calendar', label: 'History' },
  settings: { icon: 'sliders', label: 'Settings' },
};

const SPRING = Easing.bezier(0.34, 1.35, 0.5, 1);

/** 8.3 Floating nav bar: 4 icon tabs with a sliding green pill and amber dot. */
export function FloatNav({ state, navigation }: BottomTabBarProps) {
  const insets = useSafeAreaInsets();
  const [width, setWidth] = useState(0);
  const lastTab = useUi((s) => s.lastTab);
  const routes = state.routes.filter((r) => r.name in TABS);
  const index = Math.max(0, routes.findIndex((r) => r.key === state.routes[state.index]?.key));

  const x = useSharedValue(lastTab);
  const enter = useSharedValue(0);

  useEffect(() => {
    enter.value = withDelay(120, withTiming(1, { duration: 600, easing: SPRING }));
  }, [enter]);

  useEffect(() => {
    x.value = withTiming(index, { duration: 550, easing: SPRING });
    useUi.getState().set({ lastTab: index });
  }, [index, x]);

  const itemW = width > 0 ? (width - 16) / 4 : 0;
  const indicator = useAnimatedStyle(() => ({ transform: [{ translateX: x.value * itemW }] }));
  const wrap = useAnimatedStyle(() => ({ transform: [{ translateY: (1 - enter.value) * 120 }], opacity: Math.min(1, enter.value * 2) }));

  return (
    <Animated.View style={[styles.wrap, { bottom: 18 + Math.max(0, insets.bottom - 12) }, wrap]} pointerEvents="box-none">
      <View style={[styles.bar, S.nav]} onLayout={(e) => setWidth(e.nativeEvent.layout.width)} accessibilityRole="tablist">
        {itemW > 0 ? (
          <Animated.View style={[styles.indicator, { width: itemW }, indicator]} pointerEvents="none">
            <View style={[styles.pill, S.navIndicator]}>
              <View style={styles.dot} />
            </View>
          </Animated.View>
        ) : null}
        {routes.map((route, i) => {
          const tab = TABS[route.name];
          const active = i === index;
          return (
            <Pressable
              key={route.key}
              accessibilityRole="tab"
              accessibilityLabel={tab.label}
              accessibilityState={{ selected: active }}
              onPress={() => {
                const event = navigation.emit({ type: 'tabPress', target: route.key, canPreventDefault: true });
                if (!active && !event.defaultPrevented) navigation.navigate(route.name, route.params);
              }}
              style={({ pressed }) => [styles.item, { transform: [{ scale: pressed ? 0.86 : active ? 1.06 : 1 }, { translateY: active ? -2 : 0 }] }]}>
              <Icon name={tab.icon} size={23} color={active ? C.white : C.muted} strokeWidth={2.1} />
            </Pressable>
          );
        })}
      </View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  wrap: { position: 'absolute', left: 16, right: 16, zIndex: 45 },
  bar: {
    height: 68,
    paddingHorizontal: 8,
    borderRadius: 26,
    backgroundColor: C.white,
    borderWidth: 1,
    borderColor: C.borderSoft,
    flexDirection: 'row',
    alignItems: 'center',
  },
  indicator: { position: 'absolute', top: 9, bottom: 9, left: 8, alignItems: 'center', justifyContent: 'center' },
  pill: { width: 58, height: 50, borderRadius: 18, backgroundColor: C.primary, alignItems: 'center' },
  dot: { position: 'absolute', bottom: 6, width: 5, height: 5, borderRadius: 3, backgroundColor: C.accent },
  item: { flex: 1, height: 52, alignItems: 'center', justifyContent: 'center' },
});
