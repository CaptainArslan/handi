import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { FilterChip } from '@/components/ui/Controls';
import { Icon } from '@/components/ui/Icon';
import { Sheet } from '@/components/ui/Sheet';
import { Txt } from '@/components/ui/Txt';
import { C } from '@/theme/tokens';

export interface CategoryOption {
  key: string;
  label: string;
}

/** Approximate chip width (Poppins 600 14px ≈ 8.4px per character + padding + border). */
const chipWidth = (label: string, padH: number) => Math.ceil(label.length * 8.4) + padH * 2 + 3;
const GAP = 8;
const MORE_W = 84;

/**
 * Category chips that never run off the screen: as many as fit are shown, the rest live
 * behind a “More” chip that opens a list. When the chosen category is one of the hidden
 * ones, the More chip shows its name (selected) instead.
 */
export function CategoryFilter({
  options,
  value,
  onChange,
  height = 40,
  paddingHorizontal = 24,
}: {
  options: CategoryOption[];
  value: string;
  onChange: (key: string) => void;
  height?: number;
  paddingHorizontal?: number;
}) {
  const [width, setWidth] = useState(0);
  const [open, setOpen] = useState(false);
  const padH = height === 40 ? 16 : 15;

  // Greedy fit, keeping room for the More chip when something has to be hidden.
  let visible = options.length;
  if (width > 0) {
    const all = options.reduce((sum, o, i) => sum + chipWidth(o.label, padH) + (i ? GAP : 0), 0);
    if (all > width) {
      let used = MORE_W;
      visible = 0;
      for (const o of options) {
        const w = chipWidth(o.label, padH) + GAP;
        if (used + w > width) break;
        used += w;
        visible++;
      }
    }
  }

  const shown = options.slice(0, visible);
  const hidden = options.slice(visible);
  const hiddenSelected = hidden.find((o) => o.key === value);

  return (
    <View style={{ paddingHorizontal }}>
      <View style={styles.row} onLayout={(e) => setWidth(e.nativeEvent.layout.width)} accessibilityRole="tablist">
        {width > 0
          ? shown.map((o) => <FilterChip key={o.key} label={o.label} height={height} selected={value === o.key} onPress={() => onChange(o.key)} />)
          : null}
        {width > 0 && hidden.length > 0 ? (
          <Pressable
            onPress={() => setOpen(true)}
            accessibilityRole="button"
            accessibilityLabel={hiddenSelected ? `Category ${hiddenSelected.label}, more categories` : 'More categories'}
            style={({ pressed }) => [
              styles.more,
              {
                height,
                backgroundColor: hiddenSelected ? C.ink : C.white,
                borderColor: hiddenSelected ? C.ink : C.border,
                transform: [{ scale: pressed ? 0.97 : 1 }],
              },
            ]}>
            <Txt v="chip" color={hiddenSelected ? C.white : C.ink2} numberOfLines={1}>
              {hiddenSelected ? hiddenSelected.label : 'More'}
            </Txt>
            <View style={{ transform: [{ rotate: '90deg' }] }}>
              <Icon name="chevronRight" size={14} color={hiddenSelected ? C.white : C.muted} strokeWidth={2.4} />
            </View>
          </Pressable>
        ) : null}
      </View>

      <Sheet visible={open} onClose={() => setOpen(false)} handle accessibilityLabel="Categories" gap={6}>
        <Txt v="sheetTitle" style={{ marginBottom: 6 }}>
          Categories
        </Txt>
        {options.map((o) => {
          const on = value === o.key;
          return (
            <Pressable
              key={o.key}
              onPress={() => {
                onChange(o.key);
                setOpen(false);
              }}
              accessibilityRole="radio"
              accessibilityState={{ checked: on }}
              style={({ pressed }) => [styles.option, on ? styles.optionOn : null, pressed ? { backgroundColor: C.surfaceAlt2 } : null]}>
              <Txt v="itemTitle" weight={on ? 700 : 500} size={15} color={on ? C.primaryPressed : C.ink}>
                {o.label}
              </Txt>
              {on ? <Icon name="check" size={18} color={C.primary} strokeWidth={2.6} /> : null}
            </Pressable>
          );
        })}
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: GAP, minHeight: 40 },
  more: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingLeft: 15, paddingRight: 11, borderRadius: 999, borderWidth: 1.5, maxWidth: 140 },
  option: { minHeight: 50, borderRadius: 14, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  optionOn: { backgroundColor: C.mint },
});
