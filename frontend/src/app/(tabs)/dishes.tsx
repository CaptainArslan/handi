import { useQueryClient } from '@tanstack/react-query';
import { Image } from 'expo-image';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { FlatList, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Animated, { FadeInDown } from 'react-native-reanimated';

import { errorMessage } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { qk, useCustomDishes, useDishes } from '@/api/queries';
import type { KitchenDish } from '@/api/types';
import { ART, DishImage } from '@/components/DishImage';
import { weatherLabel } from '@/components/Kitchen';
import { useBottom, useTop } from '@/components/Layout';
import { LoadError, Loading } from '@/components/LoadState';
import { Button, Fab } from '@/components/ui/Button';
import { Input } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import { Sheet } from '@/components/ui/Sheet';
import { flash, NetToast, Snackbar } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { capitalize, tileMeta } from '@/lib/dates';
import { C, S, tokens } from '@/theme/tokens';
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

type Tab = 'all' | 'custom';

const dotColor = (days: number | null) =>
  days === null ? tokens.lastCookedDot.never : days < 5 ? tokens.lastCookedDot.recent : tokens.lastCookedDot.ok;

const lastCookedLine = (days: number | null) => {
  if (days === null) return 'Never cooked yet';
  if (days === 0) return 'Last cooked today';
  if (days === 1) return 'Last cooked yesterday';
  return `Last cooked ${days} days ago`;
};

export default function DishesScreen() {
  const params = useLocalSearchParams<{ tab?: string }>();
  const tab: Tab = params.tab === 'custom' ? 'custom' : 'all';
  const setTab = (t: Tab) => router.setParams({ tab: t });

  const dishes = useDishes();
  const total = dishes.data?.meta.total ?? dishes.data?.data.length ?? 0;
  const customCount = dishes.data?.meta.custom_count ?? 0;

  return tab === 'all' ? (
    <AllDishes total={total} customCount={customCount} onTab={setTab} />
  ) : (
    <CustomDishes total={total} onTab={setTab} />
  );
}

// ---------------------------------------------------------------- Header + tabs

function Header({ total, tab, customCount, onTab }: { total: number; tab: Tab; customCount: number; onTab: (t: Tab) => void }) {
  return (
    <View style={{ gap: 14 }}>
      <View style={{ gap: 2 }}>
        <Txt v="h1Compact" accessibilityRole="header">
          My dishes
        </Txt>
        <Txt v="helper" color={C.muted}>
          {`${total} ${total === 1 ? 'dish' : 'dishes'} in your kitchen`}
        </Txt>
      </View>
      <View style={styles.tabs} accessibilityRole="tablist" accessibilityLabel="Dish lists">
        <TabButton label="All dishes" on={tab === 'all'} onPress={() => onTab('all')} />
        <TabButton label="My custom" on={tab === 'custom'} onPress={() => onTab('custom')} badge={customCount} />
      </View>
    </View>
  );
}

function TabButton({ label, on, onPress, badge }: { label: string; on: boolean; onPress: () => void; badge?: number }) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="tab"
      accessibilityState={{ selected: on }}
      style={[styles.tab, on ? [{ backgroundColor: C.white }, S.tabActive] : null]}>
      <Txt v="segment" color={on ? C.ink : C.muted}>
        {label}
      </Txt>
      {badge !== undefined ? (
        <View style={styles.badge}>
          <Txt v="badge" weight={600} color={C.ink}>
            {String(badge)}
          </Txt>
        </View>
      ) : null}
    </Pressable>
  );
}

// ---------------------------------------------------------------- 4.1 All dishes

function AllDishes({ total, customCount, onTab }: { total: number; customCount: number; onTab: (t: Tab) => void }) {
  const top = useTop(52);
  const bottom = useBottom(176);
  const width = useAppWidth();
  const tileW = Math.floor((width - 40 - 12) / 2);
  const dishes = useDishes();
  const [q, setQ] = useState('');
  const [cat, setCat] = useState('all');

  const query = q.trim().toLowerCase();
  const shown = (dishes.data?.data ?? []).filter(
    (d) => !d.removed && (cat === 'all' || d.category?.key === cat) && (!query || d.name.toLowerCase().includes(query)),
  );
  const catLabel = CATEGORIES.find((c) => c.key === cat)?.label ?? '';

  let body;
  if (!dishes.data && dishes.isPending) body = <Loading />;
  else if (!dishes.data && dishes.isError) body = <LoadError message={errorMessage(dishes.error)} onRetry={() => dishes.refetch()} />;
  else if (shown.length === 0)
    body = (
      <View style={styles.empty}>
        <Txt v="body" color={C.muted} align="center">
          {query ? `No dishes match “${q.trim()}”.` : cat === 'all' ? 'No dishes in your kitchen yet.' : `No ${catLabel.toLowerCase()} dishes in your kitchen yet.`}
        </Txt>
        <Button
          label={query ? '+ Add it as a custom dish' : '+ Add a custom dish'}
          variant="dashed"
          height={44}
          fill={false}
          onPress={() => router.push(query ? { pathname: '/add-dish', params: { name: q.trim() } } : '/add-dish')}
        />
      </View>
    );

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <View style={{ paddingTop: top, paddingHorizontal: 20, gap: 14 }}>
        <Header total={total} tab="all" customCount={customCount} onTab={onTab} />
        <Input
          icon="search"
          height={48}
          value={q}
          onChangeText={(t) => setQ(t.slice(0, 40))}
          placeholder="Search dishes"
          accessibilityLabel="Search my dishes"
          returnKeyType="search"
          autoCorrect={false}
          clearable
        />
      </View>
      <View style={{ paddingTop: 12, paddingBottom: 4 }}>
        <CategoryFilter options={CATEGORIES} value={cat} onChange={setCat} height={38} paddingHorizontal={20} />
      </View>

      <FlatList
        data={body ? [] : shown}
        keyExtractor={(d) => String(d.id)}
        numColumns={2}
        columnWrapperStyle={{ gap: 12 }}
        contentContainerStyle={{ paddingTop: 10, paddingHorizontal: 20, paddingBottom: bottom, gap: 12 }}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="on-drag"
        showsVerticalScrollIndicator={false}
        ListEmptyComponent={body}
        refreshing={dishes.isRefetching && !dishes.isPending}
        onRefresh={() => dishes.refetch()}
        renderItem={({ item, index }) => <Tile dish={item} width={tileW} index={index} />}
      />

      <Fab label="Add dish" accessibilityLabel="Add custom dish" onPress={() => router.push('/add-dish')} />
      <NetToast bottom={172} />
    </View>
  );
}

function Tile({ dish, width, index }: { dish: KitchenDish; width: number; index: number }) {
  const days = dish.days_since_cooked;
  return (
    <Animated.View entering={FadeInDown.duration(350).delay(Math.min(index, 8) * 30)} style={{ width }}>
      <Pressable
        onPress={() => router.push({ pathname: '/dish/[id]', params: { id: String(dish.id) } })}
        accessibilityRole="button"
        accessibilityLabel={`${dish.name}, ${tileMeta(days)}`}
        style={({ pressed }) => [styles.tile, { transform: [{ scale: pressed ? 0.98 : 1 }] }]}>
        <View style={{ height: 118 }}>
          <DishImage imageUrl={dish.image_url} imageKey={dish.image_key} bg={dish.image_bg} scale={1.12} style={{ height: 118 }} />
          {dish.category ? (
            <View style={[styles.imgBadge, { left: 8, backgroundColor: C.white }]}>
              <Txt v="badgeS">{dish.category.name || capitalize(dish.category.key)}</Txt>
            </View>
          ) : null}
          {dish.is_custom ? (
            <View style={[styles.imgBadge, { right: 8, backgroundColor: C.accent }]}>
              <Txt v="badgeS" color={C.ink}>
                Mine
              </Txt>
            </View>
          ) : null}
        </View>
        <View style={{ paddingTop: 10, paddingHorizontal: 12, paddingBottom: 12, gap: 4 }}>
          <Txt v="itemTitle" size={15} lh={18} numberOfLines={2}>
            {dish.name}
          </Txt>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
            <View style={{ width: 7, height: 7, borderRadius: 4, backgroundColor: dotColor(days) }} />
            <Txt v="caption" color={C.muted}>
              {tileMeta(days)}
            </Txt>
          </View>
        </View>
      </Pressable>
    </Animated.View>
  );
}

// ---------------------------------------------------------------- 4.3 My custom dishes

function CustomDishes({ total, onTab }: { total: number; onTab: (t: Tab) => void }) {
  const top = useTop(52);
  const bottom = useBottom(112);
  const queryClient = useQueryClient();
  const custom = useCustomDishes();
  const [confirm, setConfirm] = useState<KitchenDish | null>(null);
  const [sheetOpen, setSheetOpen] = useState(false);
  const [removing, setRemoving] = useState(false);
  const [removed, setRemoved] = useState<{ dish: KitchenDish; idx: number } | null>(null);

  const items = custom.data ?? [];

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['dishes'] });
    queryClient.invalidateQueries({ queryKey: qk.custom });
    queryClient.invalidateQueries({ queryKey: qk.today });
    queryClient.invalidateQueries({ queryKey: qk.me });
  };

  const doRemove = async () => {
    const dish = confirm;
    if (!dish) return;
    setRemoving(true);
    try {
      await endpoints.removeCustomDish(dish.id);
      const idx = items.findIndex((d) => d.id === dish.id);
      queryClient.setQueryData<KitchenDish[]>(qk.custom, (old) => old?.filter((d) => d.id !== dish.id));
      queryClient.invalidateQueries({ queryKey: qk.dish(dish.id) });
      setSheetOpen(false);
      setRemoved({ dish, idx });
      refresh();
    } catch (e) {
      setSheetOpen(false);
      flash({ title: `Couldn’t remove ${dish.name}`, sub: errorMessage(e), tone: 'info' });
    } finally {
      setRemoving(false);
    }
  };

  const undo = async () => {
    const r = removed;
    if (!r) return;
    setRemoved(null);
    queryClient.setQueryData<KitchenDish[]>(qk.custom, (old) => {
      if (!old || old.some((d) => d.id === r.dish.id)) return old;
      const next = old.slice();
      next.splice(Math.max(0, r.idx), 0, r.dish);
      return next;
    });
    try {
      await endpoints.restoreCustomDish(r.dish.id);
    } catch (e) {
      flash({ title: `Couldn’t bring back ${r.dish.name}`, sub: errorMessage(e), tone: 'info' });
    }
    refresh();
  };

  const count = custom.data ? items.length : undefined;

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{ paddingTop: top, paddingHorizontal: 20, paddingBottom: bottom, gap: 16 }}>
        <Header total={total} tab="custom" customCount={count ?? 0} onTab={onTab} />

        <View style={styles.hero}>
          <View pointerEvents="none" style={[styles.ring, { right: -40, top: -40, width: 170, height: 170, borderRadius: 85 }]} />
          <View pointerEvents="none" style={[styles.ring, { right: -10, top: -10, width: 110, height: 110, borderRadius: 55 }]} />
          <Image
            source={ART.karhi}
            contentFit="cover"
            accessible={false}
            style={{ position: 'absolute', right: -18, bottom: -26, width: 132, height: 132, borderRadius: 66, transform: [{ rotate: '-12deg' }] }}
          />
          <View style={{ gap: 4, maxWidth: 220 }}>
            <Txt v="overline" color={C.accent}>
              Family recipes
            </Txt>
            <Txt v="numberM" lh={25.3} color={C.white}>
              Dishes only your kitchen makes
            </Txt>
          </View>
          <Button
            label="Add custom dish"
            variant="amber"
            height={46}
            icon="plus"
            iconStroke={2.6}
            fill={false}
            onPress={() => router.push('/add-dish')}
          />
        </View>

        {!custom.data && custom.isPending ? (
          <Loading />
        ) : !custom.data && custom.isError ? (
          <LoadError message={errorMessage(custom.error)} onRetry={() => custom.refetch()} />
        ) : items.length === 0 ? (
          <Animated.View entering={FadeInDown.duration(300)} style={styles.customEmpty}>
            <View style={{ width: 120, height: 120 }}>
              <Image source={ART.karhi} contentFit="cover" accessible={false} style={{ width: 120, height: 120, borderRadius: 60, opacity: 0.45 }} />
              <View style={styles.plusBadge}>
                <Icon name="plus" size={20} color={C.white} strokeWidth={2.6} />
              </View>
            </View>
            <Txt v="numberS">No custom dishes yet</Txt>
            <Txt v="bodyS" lh={21} color={C.muted} align="center" style={{ maxWidth: 260 }}>
              Add the dishes your family loves that aren’t in our library, like Mom’s karhi.
            </Txt>
            <Button label="Add your first dish" height={46} radius={14} fontSize={15} fill={false} onPress={() => router.push('/add-dish')} />
          </Animated.View>
        ) : (
          <View style={{ gap: 12 }}>
            {items.map((d, i) => (
              <CustomRow key={d.id} dish={d} index={i} onRemove={() => {
                  setConfirm(d);
                  setSheetOpen(true);
                }} />
            ))}
          </View>
        )}
      </ScrollView>

      <Snackbar message={removed ? `${removed.dish.name} removed` : null} onUndo={undo} onHide={() => setRemoved(null)} bottom={100} />
      <NetToast bottom={removed ? 164 : 100} />

      <Sheet visible={sheetOpen} onClose={() => !removing && setSheetOpen(false)} handle gap={16} accessibilityLabel={confirm ? `Remove ${confirm.name}?` : undefined}>
        {confirm ? (
          <>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 14 }}>
              <DishImage imageUrl={confirm.image_url} imageKey={confirm.image_key} bg={confirm.image_bg} scale={1} radius={16} style={{ width: 64, height: 64 }} />
              <View style={{ flex: 1, gap: 4 }}>
                <Txt v="sheetTitle" size={21} accessibilityRole="header">
                  {`Remove ${confirm.name}?`}
                </Txt>
                <Txt v="bodyS" color={C.muted}>
                  It won’t be suggested anymore.
                </Txt>
              </View>
            </View>
            <View style={styles.note}>
              <Icon name="clock" size={18} color={C.primary} />
              <Txt v="helper" lh={18.85} color={C.ink2} style={{ flex: 1 }}>
                Past meals with this dish stay in your history.
              </Txt>
            </View>
            <Button label="Remove dish" variant="danger" height={54} loading={removing} onPress={doRemove} />
            <Button label="Keep it" variant="outline" height={54} disabled={removing} onPress={() => setSheetOpen(false)} />
          </>
        ) : null}
      </Sheet>
    </View>
  );
}

function CustomRow({ dish, index, onRemove }: { dish: KitchenDish; index: number; onRemove: () => void }) {
  return (
    <Animated.View entering={FadeInDown.duration(300).delay(Math.min(index, 6) * 40)} style={styles.row}>
      <DishImage imageUrl={dish.image_url} imageKey={dish.image_key} bg={dish.image_bg} scale={1} radius={16} alt={dish.name} style={{ width: 92, height: 92 }} />
      <View style={{ flex: 1, minWidth: 0, gap: 6, paddingVertical: 2 }}>
        <Txt v="itemTitle" lh={19.2} numberOfLines={2}>
          {dish.name}
        </Txt>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
          {dish.category ? <Pill label={dish.category.name} bg={C.sand} color={C.ink2} /> : null}
          <Pill label={weatherLabel(dish.weather)} bg={C.mint} color={C.primaryPressed} />
        </View>
        <Txt v="caption" color={C.muted}>
          {lastCookedLine(dish.days_since_cooked)}
        </Txt>
      </View>
      <View style={{ justifyContent: 'space-between' }}>
        <Pressable
          onPress={() => router.push({ pathname: '/add-dish', params: { id: String(dish.id) } })}
          accessibilityRole="button"
          accessibilityLabel={`Edit ${dish.name}`}
          hitSlop={2}
          style={({ pressed }) => [styles.rowBtn, { backgroundColor: C.surfaceAlt, transform: [{ scale: pressed ? 0.94 : 1 }] }]}>
          <Icon name="pencil" size={17} color={C.ink2} />
        </Pressable>
        <Pressable
          onPress={onRemove}
          accessibilityRole="button"
          accessibilityLabel={`Remove ${dish.name}`}
          hitSlop={2}
          style={({ pressed }) => [styles.rowBtn, { backgroundColor: C.dangerSoft, transform: [{ scale: pressed ? 0.94 : 1 }] }]}>
          <Icon name="trash" size={17} color={C.danger} />
        </Pressable>
      </View>
    </Animated.View>
  );
}

function Pill({ label, bg, color }: { label: string; bg: string; color: string }) {
  return (
    <View style={{ paddingVertical: 3, paddingHorizontal: 9, borderRadius: 99, backgroundColor: bg }}>
      <Txt v="tag" color={color}>
        {label}
      </Txt>
    </View>
  );
}

const styles = StyleSheet.create({
  tabs: { flexDirection: 'row', gap: 4, padding: 4, borderRadius: 16, backgroundColor: C.sand2 },
  tab: { flex: 1, height: 42, borderRadius: 12, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6 },
  badge: { minWidth: 20, height: 20, paddingHorizontal: 6, borderRadius: 99, backgroundColor: C.accent, alignItems: 'center', justifyContent: 'center' },
  tile: { borderRadius: 22, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, overflow: 'hidden' },
  imgBadge: { position: 'absolute', top: 8, paddingVertical: 4, paddingHorizontal: 9, borderRadius: 99 },
  empty: { alignItems: 'center', gap: 12, paddingVertical: 40, paddingHorizontal: 16 },
  hero: { overflow: 'hidden', borderRadius: 24, backgroundColor: C.primary, padding: 20, gap: 14 },
  ring: { position: 'absolute', borderWidth: 1.5, borderColor: C.primaryLine },
  customEmpty: { alignItems: 'center', gap: 14, paddingVertical: 28, paddingHorizontal: 16, borderRadius: 24, borderWidth: 1.5, borderStyle: 'dashed', borderColor: C.dashed },
  plusBadge: { position: 'absolute', right: -4, bottom: 4, width: 40, height: 40, borderRadius: 20, backgroundColor: C.primary, alignItems: 'center', justifyContent: 'center' },
  row: { flexDirection: 'row', gap: 14, padding: 10, borderRadius: 22, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft },
  rowBtn: { width: 40, height: 40, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  note: { flexDirection: 'row', gap: 10, paddingVertical: 12, paddingHorizontal: 14, borderRadius: 14, backgroundColor: C.surfaceAlt },
});
