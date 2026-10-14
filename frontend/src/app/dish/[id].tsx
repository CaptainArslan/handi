import { useQueryClient } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { errorMessage } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { qk, useDish } from '@/api/queries';
import type { Cost, Effort, KitchenDishDetail, UsualMeal, WeatherTag } from '@/api/types';
import { AiPhotoHelper } from '@/components/AiPhotoHelper';
import { goBack } from '@/components/AuthKit';
import { DishImage } from '@/components/DishImage';
import { WeatherChips } from '@/components/Kitchen';
import { StickyFooter, useBottom, useTop } from '@/components/Layout';
import { LoadError, Loading } from '@/components/LoadState';
import { BackButton, Button, IconButton } from '@/components/ui/Button';
import { Segmented } from '@/components/ui/Controls';
import { Icon, type IconName } from '@/components/ui/Icon';
import { Sheet } from '@/components/ui/Sheet';
import { Spinner } from '@/components/ui/Spinner';
import { flash } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { dayMonth, todayISO } from '@/lib/dates';
import { pickPhoto } from '@/lib/photo';
import { C } from '@/theme/tokens';

const EFFORTS: { value: Effort; label: string }[] = [
  { value: 'quick', label: 'Quick' },
  { value: 'medium', label: 'Medium' },
  { value: 'heavy', label: 'Heavy' },
];
const COSTS: { value: Cost; label: string }[] = [
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'high', label: 'High' },
];

const MEAL_LABEL: Record<UsualMeal, string> = { both: 'Lunch & dinner', lunch: 'Lunch', dinner: 'Dinner', breakfast: 'Breakfast' };

interface Tags {
  weather: WeatherTag[];
  effort: Effort;
  cost: Cost;
}

const sameWeather = (a: WeatherTag[], b: WeatherTag[]) => a.slice().sort().join() === b.slice().sort().join();
const sameTags = (a: Tags, b: Tags) => sameWeather(a.weather, b.weather) && a.effort === b.effort && a.cost === b.cost;

function lastCooked(days: number | null) {
  if (days === null) return 'Never';
  if (days === 0) return 'Today';
  if (days === 1) return 'Yesterday';
  return `${days} days ago`;
}

export default function DishDetailScreen() {
  const params = useLocalSearchParams<{ id: string }>();
  const id = Number(params.id) || 0;
  const dish = useDish(id);
  const top = useTop(56);

  if (!dish.data) {
    return (
      <View style={{ flex: 1, backgroundColor: C.bg, paddingTop: top, paddingHorizontal: 20 }}>
        <BackButton onPress={() => goBack('/dishes')} label="Back to my dishes" />
        {dish.isError ? <LoadError message={errorMessage(dish.error)} onRetry={() => dish.refetch()} /> : <Loading style={{ flex: 1 }} />}
      </View>
    );
  }
  return <Detail key={dish.data.id} dish={dish.data} />;
}

function Detail({ dish }: { dish: KitchenDishDetail }) {
  const queryClient = useQueryClient();
  const top = useTop(56);
  const bottom = useBottom(24);
  const saved: Tags = { weather: dish.weather, effort: dish.effort, cost: dish.cost };
  const [draft, setDraft] = useState<Tags | null>(null);
  const tags = draft ?? saved;
  const dirty = !!draft && !sameTags(draft, saved);
  const wxErr = tags.weather.length === 0;
  const [saving, setSaving] = useState(false);
  const [resetting, setResetting] = useState(false);
  const [photo, setPhoto] = useState<'idle' | 'uploading' | 'processing'>('idle');
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [removing, setRemoving] = useState(false);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(
    () => () => {
      if (timer.current) clearTimeout(timer.current);
    },
    [],
  );

  const custom = dish.is_custom;
  const canReset = !custom && !!dish.library_defaults && (dish.has_overrides || dirty);

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['dishes'] });
    queryClient.invalidateQueries({ queryKey: qk.custom });
    queryClient.invalidateQueries({ queryKey: qk.today });
    queryClient.invalidateQueries({ queryKey: qk.dish(dish.id) });
  };
  const store = (next: KitchenDishDetail) => queryClient.setQueryData(qk.dish(dish.id), next);

  const set = (patch: Partial<Tags>) => setDraft({ ...tags, ...patch });

  const save = async () => {
    if (wxErr || !dirty) return;
    setSaving(true);
    try {
      const next = custom
        ? await endpoints.updateCustomDish(dish.id, { weather: tags.weather, effort: tags.effort, cost: tags.cost })
        : await endpoints.updateDish(dish.id, { weather: tags.weather, effort: tags.effort, cost: tags.cost });
      store(next);
      setDraft(null);
      invalidate();
    } catch (e) {
      flash({ title: 'Couldn’t save changes', sub: errorMessage(e), tone: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const reset = async () => {
    if (!dish.has_overrides) {
      setDraft(null);
      return;
    }
    setResetting(true);
    try {
      const next = await endpoints.resetDish(dish.id);
      store(next);
      setDraft(null);
      invalidate();
    } catch (e) {
      flash({ title: 'Couldn’t reset the tags', sub: errorMessage(e), tone: 'error' });
    } finally {
      setResetting(false);
    }
  };

  const changePhoto = async () => {
    const picked = await pickPhoto('library');
    if (!picked.ok) {
      if (picked.reason === 'too-big') flash({ title: 'That photo is too big', sub: 'Pick one up to 10 MB.', tone: 'info' });
      return;
    }
    setPhoto('uploading');
    try {
      const next = await endpoints.uploadDishPhoto(dish.id, { uri: picked.photo.uri, name: picked.photo.name, type: picked.photo.type });
      setPhoto('processing');
      store(next);
      invalidate();
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      timer.current = setTimeout(() => setPhoto('idle'), next.photo_status === 'processing' ? 2500 : 900);
    } catch (e) {
      setPhoto('idle');
      flash({ title: 'Couldn’t upload the photo', sub: errorMessage(e), tone: 'error' });
    }
  };

  const remove = async () => {
    setRemoving(true);
    const name = dish.name;
    const id = dish.id;
    try {
      if (custom) await endpoints.removeCustomDish(id);
      else await endpoints.removeDish(id);
      invalidate();
      setConfirmOpen(false);
      flash({
        title: `${name} removed`,
        sub: 'It won’t appear in your picks.',
        action: {
          label: 'Undo',
          onPress: async () => {
            try {
              if (custom) await endpoints.restoreCustomDish(id);
              else await endpoints.restoreDish(id);
            } catch {
              // offline: the dish stays removed; the list refresh shows the real state
            } finally {
              invalidate();
            }
          },
          after: `${name} is back in your picks`,
        },
      });
      goBack('/dishes');
    } catch (e) {
      setRemoving(false);
      setConfirmOpen(false);
      flash({ title: `Couldn’t remove ${name}`, sub: errorMessage(e), tone: 'error' });
    }
  };

  const busy = photo !== 'idle' || dish.photo_status === 'processing';
  const photoLabel =
    photo === 'uploading' ? 'Uploading…' : photo === 'processing' || dish.photo_status === 'processing' ? 'Processing…' : dish.has_photo ? 'Change photo' : 'Add photo';

  const nextISO = dish.next_eligible_at ? dish.next_eligible_at.slice(0, 10) : null;
  const eligibleNow = !nextISO || nextISO <= todayISO();
  const categoryName = dish.category?.name ?? '';

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: dirty ? 24 : bottom }}>
        <View style={styles.hero}>
          <DishImage imageUrl={dish.image_url} imageKey={dish.image_key} bg={dish.image_bg} scale={1.08} alt={dish.name} style={StyleSheet.absoluteFill} />
          <View style={styles.heroTags}>
            {categoryName ? <HeroTag label={categoryName} bg={C.white} /> : null}
            {dish.note ? <HeroTag label={dish.note} bg={C.accent} /> : null}
            {custom ? <HeroTag label="Mine" bg={C.accent} /> : null}
          </View>
          <View style={[styles.heroBar, { top }]}>
            <IconButton icon="chevronLeft" border={null} onPress={() => goBack('/dishes')} accessibilityLabel="Back to my dishes" />
            <Pressable
              onPress={changePhoto}
              disabled={busy}
              accessibilityRole="button"
              accessibilityState={{ disabled: busy, busy }}
              style={({ pressed }) => [styles.photoBtn, { transform: [{ scale: pressed ? 0.96 : 1 }] }]}>
              {busy ? <Spinner size={14} width={2} track={C.dashed} color={C.primary} /> : <Icon name="camera" size={18} color={C.ink} />}
              <Txt v="toastTitle">{photoLabel}</Txt>
            </Pressable>
          </View>
        </View>

        <View style={{ paddingTop: 20, paddingHorizontal: 20, gap: 22 }}>
          <View style={{ gap: 4 }}>
            <Txt v="h1" accessibilityRole="header">
              {dish.name}
            </Txt>
            <Txt v="bodyS" color={C.muted}>
              {[categoryName, MEAL_LABEL[dish.usual_meal]].filter(Boolean).join(' · ')}
            </Txt>
          </View>

          <View style={{ flexDirection: 'row', gap: 10 }}>
            <Stat label="Last cooked" value={lastCooked(dish.days_since_cooked)} />
            <Stat label="Next eligible" value={eligibleNow ? 'Now' : dayMonth(nextISO)} green={eligibleNow} />
          </View>

          {custom && !dish.has_photo && !busy ? <AiPhotoHelper dishName={dish.name} onUpload={changePhoto} /> : null}

          <View style={{ gap: 10 }}>
            <Txt v="section">Good for weather</Txt>
            <WeatherChips value={tags.weather} onChange={(weather) => set({ weather })} invalid={wxErr} />
            {wxErr ? (
              <Txt v="helper" color={C.danger} accessibilityRole="alert">
                Pick at least one weather, or “Any”.
              </Txt>
            ) : null}
          </View>

          <View style={{ gap: 10 }}>
            <Txt v="section">Effort</Txt>
            <Segmented options={EFFORTS} value={tags.effort} onChange={(effort) => set({ effort })} track={C.sand} />
          </View>

          <View style={{ gap: 10 }}>
            <Txt v="section">Cost</Txt>
            <Segmented options={COSTS} value={tags.cost} onChange={(cost) => set({ cost })} track={C.sand} />
          </View>

          <View style={styles.actions}>
            {canReset ? <ActionRow icon="reset" label="Reset to library tags" onPress={reset} busy={resetting} /> : null}
            {custom ? (
              <ActionRow icon="pencil" label="Edit dish details" onPress={() => router.push({ pathname: '/add-dish', params: { id: String(dish.id) } })} />
            ) : null}
            <ActionRow icon="trash" label="Remove from my dishes" color={C.danger} onPress={() => setConfirmOpen(true)} />
          </View>
        </View>
      </ScrollView>

      {dirty ? (
        <StickyFooter padding={{ top: 12, h: 20, bottom: 32 }}>
          <Button label={wxErr ? 'Fix weather to save' : 'Save changes'} height={54} disabled={wxErr} loading={saving} onPress={save} />
        </StickyFooter>
      ) : null}

      <Sheet visible={confirmOpen} onClose={() => !removing && setConfirmOpen(false)} scrim="light" accessibilityLabel={`Remove ${dish.name}?`}>
        <DishImage imageUrl={dish.thumb_url ?? dish.image_url} imageKey={dish.image_key} bg={dish.image_bg} scale={1} radius={18} style={{ width: 64, height: 64 }} />
        <Txt v="sheetTitle" accessibilityRole="header">
          {`Remove ${dish.name}?`}
        </Txt>
        <Txt v="body" color={C.muted}>
          It won’t be suggested anymore. Your past history with it stays.
        </Txt>
        <Button label="Remove" variant="danger" height={54} loading={removing} onPress={remove} />
        <Button label="Keep it" variant="outline" height={54} disabled={removing} onPress={() => setConfirmOpen(false)} />
      </Sheet>
    </View>
  );
}

function HeroTag({ label, bg }: { label: string; bg: string }) {
  return (
    <View style={{ paddingVertical: 6, paddingHorizontal: 11, borderRadius: 99, backgroundColor: bg }}>
      <Txt v="badge" color={C.ink}>
        {label}
      </Txt>
    </View>
  );
}

function Stat({ label, value, green }: { label: string; value: string; green?: boolean }) {
  return (
    <View style={styles.stat}>
      <Txt v="caption" weight={600} color={C.muted}>
        {label}
      </Txt>
      <Txt v="numberS" color={green ? C.primary : C.ink}>
        {value}
      </Txt>
    </View>
  );
}

function ActionRow({ icon, label, onPress, color = C.ink, busy }: { icon: IconName; label: string; onPress: () => void; color?: string; busy?: boolean }) {
  return (
    <Pressable
      onPress={onPress}
      disabled={busy}
      accessibilityRole="button"
      style={({ pressed }) => [styles.action, { opacity: pressed ? 0.7 : 1 }]}>
      {busy ? <Spinner size={18} width={2} track={C.dashed} color={C.primary} /> : <Icon name={icon} size={18} color={color} />}
      <Txt v="chip" size={15} color={color}>
        {label}
      </Txt>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  hero: { height: 320, overflow: 'hidden', borderBottomLeftRadius: 32, borderBottomRightRadius: 32 },
  heroTags: { position: 'absolute', left: 20, bottom: 18, flexDirection: 'row', gap: 6 },
  heroBar: { position: 'absolute', left: 20, right: 20, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  photoBtn: {
    height: 44,
    paddingHorizontal: 14,
    borderRadius: 99,
    backgroundColor: C.white,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 6 },
    elevation: 3,
  },
  stat: { flex: 1, paddingVertical: 12, paddingHorizontal: 14, borderRadius: 16, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, gap: 2 },
  actions: { gap: 6, paddingTop: 6, borderTopWidth: 1, borderTopColor: C.borderSoft },
  action: { height: 48, flexDirection: 'row', alignItems: 'center', gap: 10 },
});
