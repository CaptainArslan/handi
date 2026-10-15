import { router } from 'expo-router';
import { useState } from 'react';
import { FlatList, KeyboardAvoidingView, Platform, Pressable, StyleSheet, View } from 'react-native';

import { errorMessage } from '@/api/client';
import { useLibrary } from '@/api/queries';
import type { LibraryDish } from '@/api/types';
import { goBack } from '@/components/AuthKit';
import { DishImage } from '@/components/DishImage';
import { SetupHeader, StickyFooter, useTop } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { ProgressBar } from '@/components/ui/Controls';
import { Input } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import { Spinner } from '@/components/ui/Spinner';
import { Txt } from '@/components/ui/Txt';
import { uuid } from '@/lib/uuid';
import { cleanDishName, dishNameError } from '@/lib/validate';
import { useOnboarding, type DraftCustomDish } from '@/store/onboarding';
import { C } from '@/theme/tokens';
import { useAppWidth } from '@/lib/layout';
import { CategoryFilter } from '@/components/CategoryFilter';

const CATEGORIES: { key: string; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'vegetables', label: 'Vegetables' },
  { key: 'lentils', label: 'Lentils' },
  { key: 'chicken', label: 'Chicken' },
  { key: 'beef', label: 'Beef' },
  { key: 'mutton', label: 'Mutton' },
  { key: 'fish', label: 'Fish' },
  { key: 'rice', label: 'Rice' },
  { key: 'breakfast', label: 'Breakfast' },
  { key: 'snacks', label: 'Snacks' },
];

type Item = { kind: 'library'; dish: LibraryDish } | { kind: 'custom'; dish: DraftCustomDish };

export default function SetupDishes() {
  const top = useTop(56);
  const width = useAppWidth();
  const itemW = Math.floor((width - 48 - 20) / 3);
  const library = useLibrary();
  const dishIds = useOnboarding((s) => s.dishIds);
  const picked = useOnboarding((s) => s.pickedDishes);
  const custom = useOnboarding((s) => s.customDishes);
  const set = useOnboarding((s) => s.set);
  const [q, setQ] = useState('');
  const [cat, setCat] = useState('all');

  const query = q.trim().toLowerCase();
  const matches = (name: string) => !query || name.toLowerCase().includes(query);
  const items: Item[] = [
    ...(library.data ?? [])
      .filter((d) => (cat === 'all' || d.category?.key === cat) && matches(d.name))
      .map((dish) => ({ kind: 'library' as const, dish })),
    ...custom.filter((d) => matches(d.name)).map((dish) => ({ kind: 'custom' as const, dish })),
  ];

  const count = dishIds.length + custom.length;
  const ready = count >= 10;

  const toggle = (dish: LibraryDish) => {
    if (dishIds.includes(dish.id)) {
      set({ dishIds: dishIds.filter((id) => id !== dish.id), pickedDishes: picked.filter((p) => p.id !== dish.id) });
    } else {
      set({ dishIds: [...dishIds, dish.id], pickedDishes: [...picked, { id: dish.id, name: dish.name }] });
    }
  };

  const addCustom = () => {
    const name = cleanDishName(q);
    // Same rules as the server (letters, spaces, ’ ( ) -), so sign-up can't fail on it later.
    if (dishNameError(name)) return;
    if (!custom.some((c) => c.name.toLowerCase() === name.toLowerCase())) {
      set({ customDishes: [...custom, { key: uuid(), name, category: null }] });
    }
    setQ('');
  };

  const loading = library.isPending;
  const failed = library.isError && !library.data;

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: C.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <View style={{ paddingTop: top, paddingHorizontal: 24, gap: 20 }}>
        <SetupHeader step={2} onBack={() => goBack('/setup/family')} />
        <View style={{ gap: 8 }}>
          <Txt v="h1Compact" accessibilityRole="header">
            What does your family eat?
          </Txt>
          <Txt v="body" color={C.muted}>
            Pick at least 10. You can add your own later.
          </Txt>
        </View>
        <Input
          icon="search"
          height={52}
          value={q}
          onChangeText={(t) => setQ(t.slice(0, 40))}
          maxLength={40}
          placeholder="Search dishes"
          accessibilityLabel="Search dishes"
          returnKeyType="search"
          autoCorrect={false}
        />
      </View>

      <View>
        <View style={{ paddingTop: 16, paddingBottom: 4 }}>
          <CategoryFilter options={CATEGORIES} value={cat} onChange={setCat} paddingHorizontal={24} />
        </View>
      </View>

      {loading ? (
        <View style={styles.state}>
          <Spinner size={28} color={C.primary} track={C.borderSoft} width={3} />
        </View>
      ) : failed ? (
        <View style={styles.state}>
          <Txt v="body" color={C.muted} align="center">
            {errorMessage(library.error)}
          </Txt>
          <Button label="Try again" variant="outlineGreen" height={44} fill={false} onPress={() => library.refetch()} />
        </View>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(it) => (it.kind === 'library' ? `l${it.dish.id}` : `c${it.dish.key}`)}
          numColumns={3}
          style={{ flex: 1 }}
          contentContainerStyle={{ paddingTop: 14, paddingHorizontal: 24, paddingBottom: 20, gap: 10 }}
          columnWrapperStyle={{ gap: 10 }}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="on-drag"
          showsVerticalScrollIndicator={false}
          renderItem={({ item }) =>
            item.kind === 'library' ? (
              <DishPill
                width={itemW}
                name={item.dish.name}
                imageUrl={item.dish.thumb_url ?? item.dish.image_url}
                imageKey={item.dish.image_key}
                bg={item.dish.image_bg}
                selected={dishIds.includes(item.dish.id)}
                onPress={() => toggle(item.dish)}
              />
            ) : (
              <DishPill
                width={itemW}
                name={item.dish.name}
                imageKey="karhi"
                selected
                onPress={() => set({ customDishes: custom.filter((c) => c.key !== item.dish.key) })}
              />
            )
          }
          ListEmptyComponent={
            query.length > 1 ? (
              <View style={styles.empty}>
                <Txt v="body" color={C.muted} align="center">
                  No dish called “{q.trim()}” yet.
                </Txt>
                <Button label={`+ Add “${cleanDishName(q)}” as my own dish`} variant="dashed" height={44} fill={false} weight={600} fontSize={15} onPress={addCustom} />
              </View>
            ) : null
          }
        />
      )}

      <StickyFooter padding={{ top: 16, h: 24, bottom: 36 }} gap={12} shadow>
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
          <Txt v="chip" accessibilityLiveRegion="polite">
            {count} selected
          </Txt>
          <Txt v="helper" weight={600} color={ready ? C.primary : C.muted}>
            {ready ? 'Great, that’s enough' : 'Minimum 10'}
          </Txt>
        </View>
        <ProgressBar pct={Math.min(100, count * 10)} color={ready ? C.primary : C.accent} />
        {ready ? (
          <Button label={`Continue with ${count} dishes`} weight={600} onPress={() => router.push('/setup/preferences')} />
        ) : (
          <Button label={`Pick ${10 - count} more to continue`} weight={600} disabled />
        )}
      </StickyFooter>
    </KeyboardAvoidingView>
  );
}

function DishPill({
  width,
  name,
  imageUrl,
  imageKey,
  bg,
  selected,
  onPress,
}: {
  width: number;
  name: string;
  imageUrl?: string | null;
  imageKey?: string | null;
  bg?: string | null;
  selected: boolean;
  onPress: () => void;
}) {
  return (
    <View style={[styles.ring, { width: width + 6, borderColor: selected ? C.mintRing : 'transparent' }]}>
      <Pressable
        onPress={onPress}
        accessibilityRole="checkbox"
        accessibilityState={{ checked: selected }}
        accessibilityLabel={name}
        style={({ pressed }) => [styles.pill, { borderColor: selected ? C.primary : C.borderSoft, transform: [{ scale: pressed ? 0.96 : 1 }] }]}>
        <View>
          <DishImage imageUrl={imageUrl} imageKey={imageKey} bg={bg} scale={1.18} radius={14} style={{ height: 82 }} />
          <View style={[styles.check, selected ? { backgroundColor: C.primary } : { backgroundColor: 'rgba(255,255,255,0.75)', opacity: 0.9 }]}>
            {selected ? <Icon name="check" size={14} color={C.white} strokeWidth={3.4} /> : null}
          </View>
        </View>
        <Txt v="helper" weight={700} lh={15.6} style={{ paddingHorizontal: 2 }} numberOfLines={2}>
          {name}
        </Txt>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  state: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 14, paddingHorizontal: 24 },
  empty: { alignItems: 'center', gap: 12, paddingVertical: 32, paddingHorizontal: 8 },
  ring: { margin: -3, borderWidth: 3, borderRadius: 21 },
  pill: { gap: 7, paddingTop: 5, paddingHorizontal: 5, paddingBottom: 9, borderRadius: 18, borderWidth: 2, backgroundColor: C.white },
  check: { position: 'absolute', top: 6, right: 6, width: 24, height: 24, borderRadius: 12, borderWidth: 2, borderColor: C.white, alignItems: 'center', justifyContent: 'center' },
});
