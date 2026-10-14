import DateTimePicker from '@react-native-community/datetimepicker';
import { useState, type ReactNode } from 'react';
import { Platform, Pressable, StyleSheet, TextInput, View } from 'react-native';

import type { Budget, MealType, WeatherTag } from '@/api/types';
import { Button } from '@/components/ui/Button';
import { CheckRow, ChoiceChip } from '@/components/ui/Controls';
import { ErrorLine } from '@/components/ui/Field';
import { Icon, type IconName } from '@/components/ui/Icon';
import { Sheet } from '@/components/ui/Sheet';
import { DISPLAY, Txt } from '@/components/ui/Txt';
import { pad, time12 } from '@/lib/dates';
import { C, S } from '@/theme/tokens';
import { WebPickerInput } from '@/components/WebPickerInput';

// ---------------------------------------------------------------- Family size stepper

/** −  [ 4 ]  +   (56 tall, radius 16). Input strips non-digits, max 3 chars; ± clamps 1..20. */
export function FamilyStepper({ value, onChange, invalid, fontSize = 24 }: { value: string; onChange: (v: string) => void; invalid?: boolean; fontSize?: number }) {
  const [focused, setFocused] = useState(false);
  const n = Number(value) || 0;
  const step = (d: number) => onChange(String(Math.min(20, Math.max(1, (Number.isFinite(n) ? n : 0) + d))));

  return (
    <View style={{ flexDirection: 'row', gap: 12 }}>
      <StepBtn icon="minus" label="Fewer people" onPress={() => step(-1)} />
      <View style={{ flex: 1 }}>
        {focused ? <View pointerEvents="none" style={[styles.ring, { backgroundColor: invalid ? C.dangerRing : C.mintRing }]} /> : null}
        <TextInput
          value={value}
          onChangeText={(t) => onChange(t.replace(/\D/g, '').slice(0, 3))}
          keyboardType="number-pad"
          inputMode="numeric"
          maxLength={3}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          accessibilityLabel="People eating at home"
          style={[
            styles.stepInput,
            { fontFamily: DISPLAY, fontSize, borderColor: invalid ? C.danger : focused ? C.primary : C.border },
          ]}
        />
      </View>
      <StepBtn icon="plus" label="More people" onPress={() => step(1)} />
    </View>
  );
}

function StepBtn({ icon, label, onPress }: { icon: IconName; label: string; onPress: () => void }) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={label}
      style={({ pressed }) => [styles.stepBtn, { backgroundColor: pressed ? C.stepperPressed : C.white }]}>
      <Icon name={icon} size={22} strokeWidth={2.2} />
    </Pressable>
  );
}

// ---------------------------------------------------------------- Budget

export const BUDGETS: { value: Budget; label: string; sub: string }[] = [
  { value: 'low', label: 'Low', sub: 'Save more' },
  { value: 'normal', label: 'Normal', sub: 'Everyday' },
  { value: 'high', label: 'High', sub: 'Treat often' },
];

export function BudgetCards({ value, onChange, invalid, compact }: { value: Budget | null; onChange: (b: Budget) => void; invalid?: boolean; compact?: boolean }) {
  return (
    <View style={{ flexDirection: 'row', gap: 10 }} accessibilityRole="radiogroup">
      {BUDGETS.map((b) => {
        const on = value === b.value;
        return (
          <Pressable
            key={b.value}
            onPress={() => onChange(b.value)}
            accessibilityRole="radio"
            accessibilityState={{ checked: on }}
            style={({ pressed }) => [
              styles.budget,
              compact ? { minHeight: 56, paddingVertical: 8 } : { height: 72 },
              {
                backgroundColor: on ? C.primary : C.white,
                borderColor: on ? C.primary : invalid ? C.danger : C.border,
                transform: [{ scale: pressed ? 0.97 : 1 }],
              },
            ]}>
            <Txt v="itemTitle" size={15} color={on ? C.white : C.ink}>
              {b.label}
            </Txt>
            <Txt v="caption" weight={compact ? 500 : 400} color={on ? C.white : C.ink} style={{ opacity: 0.85 }}>
              {b.sub}
            </Txt>
          </Pressable>
        );
      })}
    </View>
  );
}

// ---------------------------------------------------------------- Repeat gap

export const GAPS = [3, 5, 7, 10] as const;

/** 4 options; onboarding shows "3d" (h 52, 16/700), settings "3 days" (h 50, 14/700). */
export function GapOptions({ value, onChange, long }: { value: number; onChange: (g: 3 | 5 | 7 | 10) => void; long?: boolean }) {
  return (
    <View style={{ flexDirection: 'row', gap: 8 }} accessibilityRole="radiogroup">
      {GAPS.map((g) => (
        <ChoiceChip
          key={g}
          label={long ? `${g} days` : `${g}d`}
          selected={value === g}
          onPress={() => onChange(g)}
          height={long ? 50 : 52}
          radius={14}
          weight={700}
          fontSize={long ? 14 : 16}
          flex
          accessibilityRole="radio"
        />
      ))}
    </View>
  );
}

/** "Don’t repeat a dish within" + "{n} days" (B 22 green). */
export function GapHeader({ value, size = 15 }: { value: number; size?: number }) {
  return (
    <View style={{ flexDirection: 'row', alignItems: 'baseline', justifyContent: 'space-between' }}>
      <Txt v="section" size={size}>
        Don’t repeat a dish within
      </Txt>
      <Txt v="numberM" color={C.primary}>
        {value} days
      </Txt>
    </View>
  );
}

// ---------------------------------------------------------------- Meals

export const MEALS: { value: MealType; label: string; sub: string }[] = [
  { value: 'breakfast', label: 'Breakfast', sub: 'Breakfast ideas' },
  { value: 'lunch', label: 'Lunch', sub: 'Midday meal' },
  { value: 'dinner', label: 'Dinner', sub: 'The big one' },
];

export function MealRows({ value, onChange, invalid, compact }: { value: MealType[]; onChange: (m: MealType[]) => void; invalid?: boolean; compact?: boolean }) {
  const toggle = (m: MealType) => {
    const next = value.includes(m) ? value.filter((x) => x !== m) : [...value, m];
    onChange(MEALS.map((x) => x.value).filter((x) => next.includes(x)));
  };
  return (
    <View style={{ gap: 10 }}>
      {MEALS.map((m) => (
        <CheckRow
          key={m.value}
          label={m.label}
          sub={m.sub}
          checked={value.includes(m.value)}
          onPress={() => toggle(m.value)}
          invalid={invalid}
          minHeight={compact ? 60 : 64}
          padding={compact ? { v: 10, h: 14 } : { v: 12, h: 16 }}
        />
      ))}
    </View>
  );
}

// ---------------------------------------------------------------- Weather chips

export const WEATHER_OPTIONS: { value: WeatherTag; label: string }[] = [
  { value: 'hot', label: 'Hot' },
  { value: 'mild', label: 'Mild' },
  { value: 'cold', label: 'Cold' },
  { value: 'rainy', label: 'Rainy' },
  { value: 'any', label: 'Any' },
];

/** Multi-select; "Any" clears the others and vice versa. */
export function WeatherChips({ value, onChange, invalid }: { value: WeatherTag[]; onChange: (w: WeatherTag[]) => void; invalid?: boolean }) {
  const toggle = (w: WeatherTag) => {
    if (w === 'any') return onChange(value.includes('any') ? [] : ['any']);
    const rest = value.filter((x) => x !== 'any');
    onChange(rest.includes(w) ? rest.filter((x) => x !== w) : [...rest, w]);
  };
  return (
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
      {WEATHER_OPTIONS.map((o) => (
        <ChoiceChip key={o.value} label={o.label} selected={value.includes(o.value)} onPress={() => toggle(o.value)} invalid={invalid} accessibilityRole="checkbox" />
      ))}
    </View>
  );
}

/** "Rainy · Mild" / "Any weather" */
export function weatherLabel(w: WeatherTag[]): string {
  if (w.length === 0 || w.includes('any')) return 'Any weather';
  return w.map((x) => x[0].toUpperCase() + x.slice(1)).join(' · ');
}

// ---------------------------------------------------------------- Reminder time

export const TIME_PRESETS = [
  { value: '07:00', label: '7 AM' },
  { value: '08:00', label: '8 AM' },
  { value: '09:00', label: '9 AM' },
  { value: '10:00', label: '10 AM' },
];

/** Presets (4 cols, h 48) + "Or pick a time" custom time field. Custom deselects presets. */
export function ReminderTimePicker({
  value,
  onChange,
  invalid,
  customLabel = 'Or pick a time',
}: {
  value: string;
  onChange: (t: string) => void;
  invalid?: boolean;
  customLabel?: string;
}) {
  const isPreset = TIME_PRESETS.some((p) => p.value === value);
  return (
    <View style={{ gap: 12 }}>
      <View style={{ flexDirection: 'row', gap: 8 }} accessibilityRole="radiogroup">
        {TIME_PRESETS.map((p) => (
          <ChoiceChip key={p.value} label={p.label} selected={value === p.value} onPress={() => onChange(p.value)} height={48} radius={14} weight={700} fontSize={15} flex accessibilityRole="radio" />
        ))}
      </View>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
        <Txt v="bodyS" color={C.muted}>
          {customLabel}
        </Txt>
        <TimeField value={isPreset ? '' : value} onChange={onChange} invalid={invalid} />
      </View>
    </View>
  );
}

/** Time input (h 48, radius 14). Opens the native time picker; shows "9:30 AM". */
export function TimeField({ value, onChange, invalid }: { value: string; onChange: (t: string) => void; invalid?: boolean }) {
  const [open, setOpen] = useState(false);
  const base = value || '09:00';
  const [h, m] = base.split(':').map(Number);
  const date = new Date();
  date.setHours(h, m, 0, 0);

  const picker = (
    <DateTimePicker
      value={date}
      mode="time"
      display={Platform.OS === 'ios' ? 'spinner' : 'default'}
      minuteInterval={5}
      onChange={(event, d) => {
        if (Platform.OS !== 'ios') setOpen(false);
        if (event.type === 'set' && d) onChange(`${pad(d.getHours())}:${pad(d.getMinutes())}`);
      }}
    />
  );

  return (
    <>
      <Pressable
        onPress={() => Platform.OS !== 'web' && setOpen(true)}
        accessibilityRole="button"
        accessibilityLabel={value ? `Custom time ${time12(value)}` : 'Pick a custom time'}
        style={[styles.time, { borderColor: invalid ? C.danger : value ? C.primary : C.border }]}>
        <Icon name="clock" size={18} color={C.muted} />
        <Txt v="input" weight={600} color={value ? C.ink : C.faint}>
          {value ? time12(value) : '--:--'}
        </Txt>
        <WebPickerInput type="time" value={value} onChange={onChange} min="05:00" max="13:00" label="Custom reminder time" />
      </Pressable>
      {open && Platform.OS !== 'ios' ? picker : null}
      {Platform.OS === 'ios' ? (
        <Sheet visible={open} onClose={() => setOpen(false)} handle>
          {picker}
          <Button label="Done" height={54} onPress={() => setOpen(false)} />
        </Sheet>
      ) : null}
    </>
  );
}

// ---------------------------------------------------------------- Notification preview

/** "What to Cook" phone-notification preview card (1.9 / 6.2). */
export function NotificationPreview({ time, text, shadow = true }: { time: string; text: string; shadow?: boolean }) {
  return (
    <View style={[styles.preview, shadow ? { shadowColor: 'rgb(60,40,10)', shadowOpacity: 0.1, shadowRadius: 15, shadowOffset: { width: 0, height: 12 }, elevation: 6 } : null]}>
      <View style={styles.previewIcon}>
        <Icon name="bowl" size={22} color={C.accent} strokeWidth={2} />
      </View>
      <View style={{ flex: 1, gap: 3 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <Txt v="toastTitle">What to Cook</Txt>
          <Txt v="caption" color={C.muted}>
            {time}
          </Txt>
        </View>
        <Txt v="bodyS" color={C.ink2}>
          {text}
        </Txt>
      </View>
    </View>
  );
}

// ---------------------------------------------------------------- Notices & bits

export type NoticeTone = 'info' | 'mint' | 'warm' | 'danger';

const NOTICE: Record<NoticeTone, { bg: string; fg: string; icon: string }> = {
  info: { bg: C.surfaceAlt, fg: C.ink2, icon: C.primary },
  mint: { bg: C.mint, fg: C.primaryPressed, icon: C.primary },
  warm: { bg: C.amberSoft, fg: C.accentInk, icon: C.accentDark },
  danger: { bg: C.dangerSoft, fg: C.dangerText, icon: C.danger },
};

export function Notice({ tone = 'info', icon = 'info', children, size = 13 }: { tone?: NoticeTone; icon?: IconName | null; children: ReactNode; size?: number }) {
  const t = NOTICE[tone];
  return (
    <View style={[styles.notice, { backgroundColor: t.bg }]}>
      {icon ? <Icon name={icon} size={18} color={t.icon} /> : null}
      <View style={{ flex: 1 }}>{typeof children === 'string' ? <Txt v="helper" size={size} lh={size * 1.5} color={t.fg}>{children}</Txt> : children}</View>
    </View>
  );
}

export { ErrorLine };

/** Round amber avatar with the name's initials (e.g. “MA”). */
export function Avatar({ initial, size = 46, fontSize = 17, border = 3, shadow = true }: { initial: string; size?: number; fontSize?: number; border?: number; shadow?: boolean }) {
  return (
    <View
      style={[
        { width: size, height: size, borderRadius: size / 2, backgroundColor: C.accent, alignItems: 'center', justifyContent: 'center', borderWidth: border, borderColor: C.white },
        shadow ? S.avatar : null,
      ]}>
      <Txt v="buttonBold" size={fontSize} color={C.ink}>
        {initial}
      </Txt>
    </View>
  );
}

const styles = StyleSheet.create({
  ring: { position: 'absolute', left: -4, right: -4, top: -4, bottom: -4, borderRadius: 20 },
  stepInput: { height: 56, borderRadius: 16, borderWidth: 1.5, textAlign: 'center', color: C.ink, backgroundColor: C.white, paddingVertical: 0 },
  stepBtn: { width: 56, height: 56, borderRadius: 16, borderWidth: 1.5, borderColor: C.border, alignItems: 'center', justifyContent: 'center' },
  budget: { flex: 1, borderRadius: 16, borderWidth: 1.5, alignItems: 'center', justifyContent: 'center', gap: 2 },
  time: { height: 48, borderRadius: 14, borderWidth: 1.5, paddingHorizontal: 12, flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: C.white, minWidth: 128 },
  preview: { backgroundColor: C.white, borderRadius: 20, padding: 14, borderWidth: 1, borderColor: C.borderSoft, flexDirection: 'row', gap: 12 },
  previewIcon: { width: 40, height: 40, borderRadius: 12, backgroundColor: C.primary, alignItems: 'center', justifyContent: 'center' },
  notice: { flexDirection: 'row', gap: 10, paddingVertical: 12, paddingHorizontal: 14, borderRadius: 14, alignItems: 'flex-start' },
});

