import { forwardRef, useState, type ReactNode } from 'react';
import { Pressable, StyleSheet, TextInput, View, type StyleProp, type TextInputProps, type ViewStyle } from 'react-native';

import { C } from '@/theme/tokens';

import { Icon, type IconName } from './Icon';
import { figtree, Txt } from './Txt';

export interface InputProps extends Omit<TextInputProps, 'style'> {
  /** Leading icon (mail, lock, search…). */
  icon?: IconName;
  /** Visual state: `bad` = red border, `ok` = valid green-grey border. */
  state?: 'default' | 'bad' | 'ok';
  height?: number;
  /** Danger context (Delete account): focus border red + red ring. */
  danger?: boolean;
  /** Show the eye toggle for password fields. */
  secureToggle?: boolean;
  /** Controlled visibility for shared toggles (Signup, Change password). */
  revealed?: boolean;
  onToggleReveal?: () => void;
  /** Clear (×) button when there is text. */
  clearable?: boolean;
  style?: StyleProp<ViewStyle>;
  inputStyle?: TextInputProps['style'];
  disabled?: boolean;
  right?: ReactNode;
}

/** Text input with the design's states: 1.5 border, radius 14, focus ring 4px. */
export const Input = forwardRef<TextInput, InputProps>(function Input(
  {
    icon,
    state = 'default',
    height = 54,
    danger,
    secureToggle,
    revealed,
    onToggleReveal,
    clearable,
    style,
    inputStyle,
    disabled,
    right,
    onFocus,
    onBlur,
    secureTextEntry,
    value,
    onChangeText,
    ...rest
  },
  ref,
) {
  const [focused, setFocused] = useState(false);
  const [ownReveal, setOwnReveal] = useState(false);
  const isRevealed = revealed ?? ownReveal;
  const toggle = onToggleReveal ?? (() => setOwnReveal((r) => !r));

  const borderColor =
    state === 'bad' ? C.danger : focused ? (danger ? C.danger : C.primary) : state === 'ok' ? C.primaryOk : C.border;
  const ring = focused ? (state === 'bad' || danger ? C.dangerRing : C.mintRing) : null;
  const hasRightButton = secureToggle || (clearable && !!value);

  return (
    <View style={[{ height }, style]}>
      {ring ? <View pointerEvents="none" style={[styles.ring, { backgroundColor: ring, borderRadius: 18 }]} /> : null}
      <View
        style={[
          styles.box,
          {
            height,
            borderColor,
            backgroundColor: disabled ? C.disabledInput : C.white,
          },
        ]}>
        {icon ? (
          <View style={[styles.lead, { top: (height - 20) / 2 - 1.5 }]} pointerEvents="none">
            <Icon name={icon} size={20} color={C.muted} />
          </View>
        ) : null}
        <TextInput
          ref={ref}
          value={value}
          onChangeText={onChangeText}
          editable={!disabled}
          placeholderTextColor={C.faint}
          secureTextEntry={secureToggle ? !isRevealed : secureTextEntry}
          onFocus={(e) => {
            setFocused(true);
            onFocus?.(e);
          }}
          onBlur={(e) => {
            setFocused(false);
            onBlur?.(e);
          }}
          style={[
            styles.input,
            {
              paddingLeft: icon ? 46 : 16,
              paddingRight: hasRightButton || right ? 56 : 16,
              color: disabled ? C.faint : C.ink,
            },
            inputStyle,
          ]}
          {...rest}
        />
        {secureToggle ? (
          <Pressable
            onPress={toggle}
            accessibilityRole="button"
            accessibilityLabel={isRevealed ? 'Hide password' : 'Show password'}
            style={[styles.trailing, { top: (height - 3 - 44) / 2 }]}>
            <Icon name={isRevealed ? 'eyeOff' : 'eye'} size={20} color={C.muted} />
          </Pressable>
        ) : clearable && value ? (
          <Pressable
            onPress={() => onChangeText?.('')}
            accessibilityRole="button"
            accessibilityLabel="Clear search"
            style={[styles.trailing, { top: (height - 3 - 44) / 2 }]}>
            <Icon name="close" size={18} color={C.muted} strokeWidth={2.2} />
          </Pressable>
        ) : null}
        {right}
      </View>
    </View>
  );
});

/** Label + input + helper/error stack (gap 6). */
export function Field({
  label,
  labelRight,
  error,
  helper,
  helperColor,
  children,
  style,
}: {
  label?: string;
  labelRight?: ReactNode;
  error?: string | null;
  helper?: string | null;
  helperColor?: string;
  children: ReactNode;
  style?: StyleProp<ViewStyle>;
}) {
  return (
    <View style={[{ gap: 6 }, style]}>
      {label || labelRight ? (
        <View style={styles.labelRow}>
          {label ? <Txt v="label" color={C.ink2}>{label}</Txt> : <View />}
          {labelRight}
        </View>
      ) : null}
      {children}
      {error ? (
        <Txt v="helper" color={C.danger} accessibilityRole="alert" accessibilityLiveRegion="polite">
          {error}
        </Txt>
      ) : helper ? (
        <Txt v="helper" color={helperColor ?? C.muted}>
          {helper}
        </Txt>
      ) : null}
    </View>
  );
}

/** Inline error row with the small alert icon (budget, meals…). */
export function ErrorLine({ children }: { children: string }) {
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }} accessibilityRole="alert">
      <Icon name="alert" size={14} color={C.danger} />
      <Txt v="helper" color={C.danger}>
        {children}
      </Txt>
    </View>
  );
}

const styles = StyleSheet.create({
  ring: { position: 'absolute', left: -4, right: -4, top: -4, bottom: -4 },
  box: { borderWidth: 1.5, borderRadius: 14, justifyContent: 'center' },
  lead: { position: 'absolute', left: 16 },
  input: { flex: 1, fontFamily: figtree(500), fontSize: 16, paddingVertical: 0 },
  trailing: { position: 'absolute', right: 4, width: 44, height: 44, alignItems: 'center', justifyContent: 'center' },
  labelRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', minHeight: 20 },
});
