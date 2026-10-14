import type { ReactNode } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View, type ScrollViewProps, type StyleProp, type ViewStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { BackButton } from '@/components/ui/Button';
import { ProgressBar } from '@/components/ui/Controls';
import { Txt } from '@/components/ui/Txt';
import { C, S } from '@/theme/tokens';

/**
 * Design artboards are 390×844 with the status bar inside the top padding (~44px).
 * `useTop(56)` turns a design top padding into "safe area + the rest".
 */
export function useTop(designTop: number) {
  const insets = useSafeAreaInsets();
  return insets.top + Math.max(8, designTop - 44);
}

export function useBottom(designBottom: number) {
  const insets = useSafeAreaInsets();
  return designBottom + Math.max(0, insets.bottom - 8);
}

/** Full-screen background view. */
export function Screen({ children, bg = C.bg, style }: { children: ReactNode; bg?: string; style?: StyleProp<ViewStyle> }) {
  return <View style={[{ flex: 1, backgroundColor: bg }, style]}>{children}</View>;
}

/**
 * Onboarding / auth layout: padding 56 24 40, vertical `gap`, content grows so a
 * bottom block can sit at the bottom (use <Spacer/>).
 */
export function FormScroll({
  children,
  top = 56,
  gap = 24,
  bottom = 40,
  padH = 24,
  contentStyle,
  ...rest
}: { children: ReactNode; top?: number; gap?: number; bottom?: number; padH?: number; contentStyle?: StyleProp<ViewStyle> } & ScrollViewProps) {
  const padTop = useTop(top);
  const padBottom = useBottom(bottom);
  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: C.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
        contentContainerStyle={[{ flexGrow: 1, paddingTop: padTop, paddingHorizontal: padH, paddingBottom: padBottom, gap }, contentStyle]}
        {...rest}>
        {children}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

export const Spacer = () => <View style={{ flex: 1 }} />;

/** "N of 6" setup progress header (back + bar + label). */
export function SetupHeader({ step, onBack, backLabel = 'Back' }: { step: number; onBack: () => void; backLabel?: string }) {
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 14 }}>
      <BackButton onPress={onBack} label={backLabel} />
      <View style={{ flex: 1 }} accessibilityRole="progressbar" accessibilityValue={{ min: 0, max: 6, now: step }}>
        <ProgressBar pct={(step / 6) * 100} />
      </View>
      <Txt v="caption" size={13} weight={600} color={C.muted}>
        {step} of 6
      </Txt>
    </View>
  );
}

/** Title + subtitle block used by onboarding/auth screens. */
export function Heading({ title, sub, v = 'h1', subSize = 16, gap = 10, center }: { title: string; sub?: ReactNode; v?: 'h1' | 'h1Compact' | 'h1Large' | 'h1Hero'; subSize?: 15 | 16; gap?: number; center?: boolean }) {
  return (
    <View style={{ gap }}>
      <Txt v={v} align={center ? 'center' : undefined} accessibilityRole="header">
        {title}
      </Txt>
      {sub ? (
        typeof sub === 'string' ? (
          <Txt v={subSize === 16 ? 'bodyL' : 'body'} color={C.muted} align={center ? 'center' : undefined}>
            {sub}
          </Txt>
        ) : (
          sub
        )
      ) : null}
    </View>
  );
}

/** White sticky footer with a top border (save bars, PickDishes, City). */
export function StickyFooter({
  children,
  padding = { top: 12, h: 20, bottom: 30 },
  bg = C.white,
  shadow,
  gap = 10,
  border = true,
}: {
  children: ReactNode;
  padding?: { top: number; h: number; bottom: number };
  bg?: string;
  shadow?: boolean;
  gap?: number;
  border?: boolean;
}) {
  const bottom = useBottom(padding.bottom);
  return (
    <View
      style={[
        { backgroundColor: bg, paddingTop: padding.top, paddingHorizontal: padding.h, paddingBottom: bottom, gap },
        border ? { borderTopWidth: 1, borderTopColor: C.borderSoft } : null,
        shadow ? S.footer : null,
      ]}>
      {children}
    </View>
  );
}

/** In-app screen header row: back/close + title (+ subtitle) + optional right slot. */
export function AppHeader({
  title,
  sub,
  onBack,
  backIcon = 'chevronLeft',
  backLabel = 'Back',
  right,
  titleV = 'screenTitle',
  padding = { h: 20, bottom: 14 },
}: {
  title: string;
  sub?: string;
  onBack?: () => void;
  backIcon?: 'chevronLeft' | 'close';
  backLabel?: string;
  right?: ReactNode;
  titleV?: 'screenTitle' | 'editTitle' | 'greeting';
  padding?: { h: number; bottom: number };
}) {
  const top = useTop(52);
  return (
    <View style={[styles.header, { paddingTop: top, paddingHorizontal: padding.h, paddingBottom: padding.bottom }]}>
      {onBack ? <BackButton onPress={onBack} label={backLabel} icon={backIcon} /> : null}
      <View style={{ flex: 1, gap: 2 }}>
        <Txt v={titleV} numberOfLines={1} accessibilityRole="header">
          {title}
        </Txt>
        {sub ? (
          <Txt v="caption" color={C.muted} numberOfLines={1}>
            {sub}
          </Txt>
        ) : null}
      </View>
      {right}
    </View>
  );
}

/** Uppercase section label ("KITCHEN", "POPULAR"). */
export function Overline({ children, color = C.muted, style }: { children: string; color?: string; style?: StyleProp<ViewStyle> }) {
  return (
    <View style={style}>
      <Txt v="overline" color={color}>
        {children}
      </Txt>
    </View>
  );
}

/** White card with the soft border. */
export function Card({ children, style, radius = 18, padding = 0 }: { children: ReactNode; style?: StyleProp<ViewStyle>; radius?: number; padding?: number }) {
  return <View style={[{ backgroundColor: C.white, borderRadius: radius, borderWidth: 1, borderColor: C.borderSoft, padding, overflow: 'hidden' }, style]}>{children}</View>;
}

export function Divider() {
  return <View style={{ height: 1, backgroundColor: C.divider }} />;
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 12 },
});
