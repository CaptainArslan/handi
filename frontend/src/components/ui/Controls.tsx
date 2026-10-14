import { useEffect, type ReactNode } from 'react';
import { Pressable, ScrollView, StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';
import Animated, { Easing, useAnimatedStyle, useSharedValue, withTiming } from 'react-native-reanimated';

import { C, S } from '@/theme/tokens';

import { Icon } from './Icon';
import { Txt } from './Txt';

/** Dark filter chip (category tabs). Selected = ink bg, white text. */
export function FilterChip({ label, selected, onPress, height = 40 }: { label: string; selected: boolean; onPress: () => void; height?: number }) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="tab"
      accessibilityState={{ selected }}
      style={({ pressed }) => [
        styles.chip,
        {
          height,
          paddingHorizontal: height === 40 ? 16 : 15,
          backgroundColor: selected ? C.ink : C.white,
          borderColor: selected ? C.ink : C.border,
          transform: [{ scale: pressed ? 0.97 : 1 }],
        },
      ]}>
      <Txt v="chip" color={selected ? C.white : C.ink2}>
        {label}
      </Txt>
    </Pressable>
  );
}

/** Horizontal, scrollbar-less chip row. */
export function ChipRow({ children, contentStyle }: { children: ReactNode; contentStyle?: StyleProp<ViewStyle> }) {
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={[{ gap: 8 }, contentStyle]}>
      {children}
    </ScrollView>
  );
}

/** Green choice chip/button: weather toggles, popular cities, gap & time options, categories. */
export function ChoiceChip({
  label,
  selected,
  onPress,
  invalid,
  height = 44,
  radius = 99,
  weight = 600,
  fontSize = 14,
  flex,
  icon,
  accessibilityRole = 'button',
}: {
  label: string;
  selected: boolean;
  onPress: () => void;
  invalid?: boolean;
  height?: number;
  radius?: number;
  weight?: 600 | 700;
  fontSize?: number;
  flex?: boolean;
  icon?: ReactNode;
  accessibilityRole?: 'button' | 'radio' | 'checkbox';
}) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole={accessibilityRole}
      accessibilityState={accessibilityRole === 'button' ? { selected } : { checked: selected }}
      style={({ pressed }) => [
        styles.choice,
        {
          height,
          borderRadius: radius,
          backgroundColor: selected ? C.primary : C.white,
          borderColor: selected ? C.primary : invalid ? C.danger : C.border,
          transform: [{ scale: pressed ? 0.97 : 1 }],
        },
        flex ? { flex: 1, paddingHorizontal: 4 } : { paddingHorizontal: 16 },
      ]}>
      {icon}
      <Txt v="chip" weight={weight} size={fontSize} color={selected ? C.white : C.ink} numberOfLines={1}>
        {label}
      </Txt>
    </Pressable>
  );
}

/** Segmented control. track: `sand2` (#EFE8DC) or `sand` (#F1EBE1) or `sand3` (#F1EADF). */
export function Segmented<T extends string>({
  options,
  value,
  onChange,
  track = C.sand2,
  height = 44,
  radius = 14,
  optionRadius = 11,
  fontSize = 14,
  style,
}: {
  options: { value: T; label: string; badge?: ReactNode }[];
  value: T;
  onChange: (v: T) => void;
  track?: string;
  height?: number;
  radius?: number;
  optionRadius?: number;
  fontSize?: number;
  style?: StyleProp<ViewStyle>;
}) {
  return (
    <View style={[styles.segTrack, { backgroundColor: track, borderRadius: radius }, style]} accessibilityRole="tablist">
      {options.map((o) => {
        const on = o.value === value;
        return (
          <Pressable
            key={o.value}
            onPress={() => onChange(o.value)}
            accessibilityRole="tab"
            accessibilityState={{ selected: on }}
            style={[styles.segOpt, { height, borderRadius: optionRadius }, on ? [{ backgroundColor: C.white }, S.segActive] : null]}>
            <Txt v="segment" size={fontSize} color={on ? C.ink : C.muted} numberOfLines={1}>
              {o.label}
            </Txt>
            {o.badge}
          </Pressable>
        );
      })}
    </View>
  );
}

/** 52×32 switch with the springy knob. */
export function Switch({ value, onChange, disabled, accessibilityLabel }: { value: boolean; onChange: (v: boolean) => void; disabled?: boolean; accessibilityLabel: string }) {
  const x = useSharedValue(value ? 24 : 4);
  useEffect(() => {
    x.value = withTiming(value ? 24 : 4, { duration: 250, easing: Easing.bezier(0.3, 1.4, 0.5, 1) });
  }, [value, x]);
  const knob = useAnimatedStyle(() => ({ left: x.value }));

  return (
    <Pressable
      onPress={() => !disabled && onChange(!value)}
      accessibilityRole="switch"
      accessibilityLabel={accessibilityLabel}
      accessibilityState={{ checked: value, disabled }}
      hitSlop={8}
      style={[styles.switch, { backgroundColor: value ? C.primary : C.dashed, opacity: disabled ? 0.5 : 1 }]}>
      <Animated.View style={[styles.knob, S.knob, knob]} />
    </Pressable>
  );
}

/** Custom 24×24 checkbox (radius 8). */
export function CheckBox({ checked, size = 24, danger }: { checked: boolean; size?: number; danger?: boolean }) {
  const on = danger ? C.danger : C.primary;
  return (
    <View
      style={{
        width: size,
        height: size,
        borderRadius: size === 24 ? 8 : 6,
        borderWidth: 2,
        borderColor: checked ? on : C.checkboxBorder,
        backgroundColor: checked ? on : C.white,
        alignItems: 'center',
        justifyContent: 'center',
      }}>
      {checked ? <Icon name="check" size={size - 10} color={C.white} strokeWidth={3.2} /> : null}
    </View>
  );
}

/** Checkbox + label row used for "Show passwords", terms, delete acknowledgement. */
export function CheckLine({ checked, onChange, children, danger, size = 22 }: { checked: boolean; onChange: (v: boolean) => void; children: ReactNode; danger?: boolean; size?: number }) {
  return (
    <Pressable
      onPress={() => onChange(!checked)}
      accessibilityRole="checkbox"
      accessibilityState={{ checked }}
      style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 10, minHeight: 32, paddingVertical: 4 }}>
      <CheckBox checked={checked} size={size} danger={danger} />
      <View style={{ flex: 1, paddingTop: 1 }}>{children}</View>
    </Pressable>
  );
}

/** 22×22 radio dot (city list). */
export function RadioDot({ on }: { on: boolean }) {
  return (
    <View
      style={{
        width: 22,
        height: 22,
        borderRadius: 11,
        borderWidth: on ? 0 : 2,
        borderColor: C.checkboxBorder,
        backgroundColor: on ? C.primary : C.white,
        alignItems: 'center',
        justifyContent: 'center',
      }}>
      {on ? <Icon name="check" size={12} color={C.white} strokeWidth={3.4} /> : null}
    </View>
  );
}

/** Password-rule row: 20px dot + text, live. */
export function RuleRow({ ok, failed, children }: { ok: boolean; failed?: boolean; children: string }) {
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
      <View
        style={{
          width: 20,
          height: 20,
          borderRadius: 10,
          backgroundColor: ok ? C.primary : C.white,
          borderWidth: ok ? 0 : 2,
          borderColor: failed ? C.danger : C.checkboxBorder,
          alignItems: 'center',
          justifyContent: 'center',
        }}>
        {ok ? <Icon name="check" size={11} color={C.white} strokeWidth={3.6} /> : null}
      </View>
      <Txt v="bodyS" weight={ok ? 600 : 500} color={ok ? C.primaryPressed : failed ? C.danger : C.muted}>
        {children}
      </Txt>
    </View>
  );
}

/** Checkbox card row (meals): label 16/700 + sub 13 muted + checkbox right. */
export function CheckRow({
  label,
  sub,
  checked,
  onPress,
  invalid,
  minHeight = 64,
  padding = { v: 12, h: 16 },
}: {
  label: string;
  sub: string;
  checked: boolean;
  onPress: () => void;
  invalid?: boolean;
  minHeight?: number;
  padding?: { v: number; h: number };
}) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="checkbox"
      accessibilityState={{ checked }}
      style={({ pressed }) => [
        {
          minHeight,
          paddingVertical: padding.v,
          paddingHorizontal: padding.h,
          borderRadius: 16,
          borderWidth: 1.5,
          borderColor: checked ? C.primary : invalid ? C.danger : C.border,
          backgroundColor: C.white,
          flexDirection: 'row',
          alignItems: 'center',
          gap: 12,
          transform: [{ scale: pressed ? 0.99 : 1 }],
        },
      ]}>
      <View style={{ flex: 1, gap: 2 }}>
        <Txt v="itemTitle">{label}</Txt>
        <Txt v="helper" color={C.muted}>
          {sub}
        </Txt>
      </View>
      <CheckBox checked={checked} />
    </Pressable>
  );
}

/** Small pill tag (12/600, padding 5 10). */
export function Tag({ label, bg = C.sand, color = C.ink2, padV = 5, padH = 10, size = 12 }: { label: string; bg?: string; color?: string; padV?: number; padH?: number; size?: number }) {
  return (
    <View style={{ backgroundColor: bg, borderRadius: 99, paddingVertical: padV, paddingHorizontal: padH }}>
      <Txt v="tag" size={size} color={color}>
        {label}
      </Txt>
    </View>
  );
}

/** Thin progress bar (h 6, radius 99). */
export function ProgressBar({ pct, color = C.primary, track = C.borderSoft, height = 6, animated = true }: { pct: number; color?: string; track?: string; height?: number; animated?: boolean }) {
  const w = useSharedValue(pct);
  useEffect(() => {
    w.value = animated ? withTiming(pct, { duration: 350, easing: Easing.bezier(0.2, 0.8, 0.2, 1) }) : pct;
  }, [pct, animated, w]);
  const fill = useAnimatedStyle(() => ({ width: `${Math.max(0, Math.min(100, w.value))}%` }));
  return (
    <View style={{ height, borderRadius: 99, backgroundColor: track, overflow: 'hidden' }}>
      <Animated.View style={[{ height, borderRadius: 99, backgroundColor: color }, fill]} />
    </View>
  );
}

const styles = StyleSheet.create({
  chip: { borderRadius: 999, borderWidth: 1.5, alignItems: 'center', justifyContent: 'center' },
  choice: { borderWidth: 1.5, alignItems: 'center', justifyContent: 'center', flexDirection: 'row', gap: 6 },
  segTrack: { flexDirection: 'row', padding: 4, gap: 4 },
  segOpt: { flex: 1, alignItems: 'center', justifyContent: 'center', flexDirection: 'row', gap: 6, paddingHorizontal: 6 },
  switch: { width: 52, height: 32, borderRadius: 99 },
  knob: { position: 'absolute', top: 4, width: 24, height: 24, borderRadius: 12, backgroundColor: '#FFFFFF' },
});
