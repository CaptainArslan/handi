import type { ReactNode } from 'react';
import { Pressable, StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';

import { C, R, S } from '@/theme/tokens';

import { Icon, type IconName } from './Icon';
import { Spinner } from './Spinner';
import { Txt } from './Txt';

export type ButtonVariant =
  | 'primary' // green CTA
  | 'success' // "✓ Saved"
  | 'danger'
  | 'dark'
  | 'outline'
  | 'outlineGreen'
  | 'outlineDanger'
  | 'text'
  | 'textMuted'
  | 'amber'
  | 'pill'
  | 'dashed';

export interface ButtonProps {
  label: string;
  onPress?: () => void;
  variant?: ButtonVariant;
  /** 56 (CTA) by default; 54 for sheets/save footers, 48/46/42 for smaller buttons. */
  height?: number;
  /** 600 for onboarding/auth CTAs, 700 in-app (default). */
  weight?: 600 | 700;
  fontSize?: number;
  icon?: IconName;
  iconRight?: IconName;
  iconSize?: number;
  iconStroke?: number;
  loading?: boolean;
  disabled?: boolean;
  radius?: number;
  style?: StyleProp<ViewStyle>;
  accessibilityLabel?: string;
  children?: ReactNode;
  fill?: boolean;
}

const LOOK: Record<ButtonVariant, { bg: string; pressed: string; fg: string; border?: string; dashed?: boolean }> = {
  primary: { bg: C.primary, pressed: C.primaryPressed, fg: C.white },
  success: { bg: C.success, pressed: C.success, fg: C.white },
  danger: { bg: C.danger, pressed: C.dangerPressed, fg: C.white },
  dark: { bg: C.ink, pressed: '#000000', fg: C.white },
  outline: { bg: C.white, pressed: C.surfaceAlt2, fg: C.ink, border: C.border },
  outlineGreen: { bg: C.white, pressed: C.mint, fg: C.primary, border: C.primary },
  outlineDanger: { bg: C.white, pressed: C.dangerSoft, fg: C.danger, border: C.border },
  text: { bg: 'transparent', pressed: 'transparent', fg: C.primary },
  textMuted: { bg: 'transparent', pressed: 'transparent', fg: C.ink2 },
  amber: { bg: C.accent, pressed: C.accentHover, fg: C.ink },
  pill: { bg: C.primary, pressed: C.primaryPressed, fg: C.white },
  dashed: { bg: C.white, pressed: C.mint, fg: C.primary, border: C.primary, dashed: true },
};

export function Button({
  label,
  onPress,
  variant = 'primary',
  height,
  weight = 700,
  fontSize,
  icon,
  iconRight,
  iconSize = 18,
  iconStroke = 2.2,
  loading,
  disabled,
  radius,
  style,
  accessibilityLabel,
  fill = true,
}: ButtonProps) {
  const look = LOOK[variant];
  const isDisabled = disabled || loading;
  const solid = !['outline', 'outlineGreen', 'outlineDanger', 'text', 'textMuted', 'dashed'].includes(variant);
  const h = height ?? (variant === 'text' || variant === 'textMuted' ? 48 : variant === 'pill' ? 48 : 56);
  const r = radius ?? (variant === 'pill' ? R.pill : variant === 'amber' || variant === 'outlineGreen' ? 14 : variant === 'dashed' ? 12 : 16);
  const fg = isDisabled && solid ? C.muted : look.fg;
  const size = fontSize ?? (variant === 'text' || variant === 'textMuted' || variant === 'amber' || variant === 'pill' ? 15 : variant === 'outlineGreen' ? 14 : 16);

  return (
    <Pressable
      onPress={onPress}
      disabled={isDisabled}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityState={{ disabled: !!isDisabled, busy: !!loading }}
      style={({ pressed }) => [
        styles.base,
        {
          height: h,
          minHeight: h,
          borderRadius: r,
          backgroundColor: isDisabled && solid ? C.disabledBg : pressed ? look.pressed : look.bg,
          paddingHorizontal: variant === 'pill' ? 28 : variant === 'amber' ? 18 : variant === 'dashed' ? 18 : 16,
          alignSelf: fill ? 'stretch' : 'flex-start',
        },
        look.border ? { borderWidth: 1.5, borderColor: look.border, borderStyle: look.dashed ? 'dashed' : 'solid' } : null,
        variant === 'text' || variant === 'textMuted' ? { paddingHorizontal: 8 } : null,
        pressed && !isDisabled && (variant === 'text' || variant === 'textMuted') ? { opacity: 0.7 } : null,
        style,
      ]}>
      <View style={styles.row}>
        {loading ? <Spinner size={18} track={solid ? C.primaryMuted : C.dashed} color={solid ? C.white : C.primary} /> : null}
        {!loading && icon ? <Icon name={icon} size={iconSize} color={fg} strokeWidth={iconStroke} /> : null}
        <Txt v={weight === 600 ? 'button' : 'buttonBold'} size={size} color={fg} numberOfLines={1}>
          {label}
        </Txt>
        {!loading && iconRight ? <Icon name={iconRight} size={iconSize} color={fg} strokeWidth={iconStroke} /> : null}
      </View>
    </Pressable>
  );
}

/** Square icon button (back button style by default: 44×44, radius 14, white, 1.5 border). */
export function IconButton({
  icon,
  onPress,
  accessibilityLabel,
  size = 44,
  radius = 14,
  bg = C.white,
  border = C.borderSoft,
  color = C.ink,
  iconSize = 20,
  strokeWidth = 2,
  style,
  disabled,
}: {
  icon: IconName;
  onPress?: () => void;
  accessibilityLabel: string;
  size?: number;
  radius?: number;
  bg?: string;
  border?: string | null;
  color?: string;
  iconSize?: number;
  strokeWidth?: number;
  style?: StyleProp<ViewStyle>;
  disabled?: boolean;
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
      hitSlop={4}
      style={({ pressed }) => [
        {
          width: size,
          height: size,
          borderRadius: radius,
          backgroundColor: bg,
          alignItems: 'center',
          justifyContent: 'center',
          transform: [{ scale: pressed ? 0.94 : 1 }],
        },
        border ? { borderWidth: 1.5, borderColor: border } : null,
        style,
      ]}>
      <Icon name={icon} size={iconSize} color={color} strokeWidth={strokeWidth} />
    </Pressable>
  );
}

export function BackButton({ onPress, label = 'Back', icon = 'chevronLeft' }: { onPress: () => void; label?: string; icon?: IconName }) {
  return <IconButton icon={icon} onPress={onPress} accessibilityLabel={label} />;
}

/** Floating action button ("Add dish", "Log a meal"): right 20, bottom 104. */
export function Fab({ label, onPress, accessibilityLabel }: { label: string; onPress: () => void; accessibilityLabel?: string }) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      style={({ pressed }) => [styles.fab, S.fab, { backgroundColor: pressed ? C.primaryPressed : C.primary }]}>
      <Icon name="plus" size={20} color={C.white} strokeWidth={2.4} />
      <Txt v="buttonBold" size={15} color={C.white}>
        {label}
      </Txt>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: { alignItems: 'center', justifyContent: 'center' },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8 },
  fab: {
    position: 'absolute',
    right: 20,
    bottom: 104,
    height: 56,
    borderRadius: 18,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    zIndex: 40,
  },
});
