import DateTimePicker from '@react-native-community/datetimepicker';
import { useQueryClient, type QueryClient } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { errorMessage, fieldErrors } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { qk, useDishes, useMeals } from '@/api/queries';
import type { KitchenDish, MealLog, MealType, MonthHistory, Today } from '@/api/types';
import { DishImage } from '@/components/DishImage';
import { Notice } from '@/components/Kitchen';
import { StickyFooter, useTop } from '@/components/Layout';
import { BackButton, Button } from '@/components/ui/Button';
import { Segmented } from '@/components/ui/Controls';
import { Field, Input } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import { Sheet } from '@/components/ui/Sheet';
import { Spinner } from '@/components/ui/Spinner';
import { flash } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { addDays, dayMonth, longDate, parseISO, todayISO } from '@/lib/dates';
import { createMeal, MEAL_LABEL, pendingLogs, removeMeal } from '@/lib/mealQueue';
import { uuid } from '@/lib/uuid';
import { useQueue } from '@/store/queue';
import { C } from '@/theme/tokens';
import { WebPickerInput } from '@/components/WebPickerInput';

type Picked = Pick<KitchenDish, 'id' | 'name' | 'image_url' | 'thumb_url' | 'image_key' | 'image_bg'>;

const MEAL_OPTIONS: { value: MealType; label: string }[] = [
  { value: 'breakfast', label: 'Breakfast' },
  { value: 'lunch', label: 'Lunch' },
  { value: 'dinner', label: 'Dinner' },
];

const isMeal = (v: unknown): v is MealType => v === 'breakfast' || v === 'lunch' || v === 'dinner';
const isDate = (v: unknown): v is string => typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v);

/** Find a logged meal in the month just fetched, or anywhere in the cached History months. */
function findLog(id: string | undefined, month: MonthHistory | undefined, qc: QueryClient): MealLog | null {
  if (!id) return null;
  const scan = (h: MonthHistory | undefined) => Object.values(h?.days ?? {}).flat().find((l) => l.id === id) ?? null;
  const hit = scan(month);
  if (hit) return hit;
  for (const [, h] of qc.getQueriesData<MonthHistory>({ queryKey: ['meals'] })) {
    const found = scan(h);
    if (found) return found;
  }
  return null;
}

function leave() {
  if (router.canGoBack()) router.back();
  else router.replace('/history');
}

export default function LogMealScreen() {
  const top = useTop(52);
  const qc = useQueryClient();
  const params = useLocalSearchParams<{ id?: string; dish?: string; meal?: string; date?: string }>();
  const editId = params.id || undefined;
  // The account's today (server timezone) when Today is cached; the phone's date otherwise.
  const today = qc.getQueryData<Today>(qk.today)?.date ?? todayISO();
  const minDate = addDays(today, -60);

  const [q, setQ] = useState('');
  const [dish, setDish] = useState<Picked | null>(null);
  const [date, setDate] = useState(isDate(params.date) ? params.date : today);
  const [meal, setMeal] = useState<MealType>(isMeal(params.meal) ? params.meal : 'dinner');
  const [submitted, setSubmitted] = useState(false);
  const [saving, setSaving] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [initFor, setInitFor] = useState<string | null>(null);

  const dishesQ = useDishes();
  const monthQ = useMeals((date || today).slice(0, 7));
  const ops = useQueue((s) => s.ops);
  const all = (dishesQ.data?.data ?? []).filter((d) => !d.removed);

  // Edit: fill the form once the meal is found. Preselect: `dish` param.
  const editLog = findLog(editId, monthQ.data, qc);
  if (editLog && initFor !== editLog.id) {
    setInitFor(editLog.id);
    setDish({ id: editLog.kitchen_dish_id ?? 0, name: editLog.dish_name, image_url: editLog.image_url, image_key: editLog.image_key, image_bg: editLog.image_bg });
    setDate(editLog.date);
    setMeal(editLog.meal);
  }
  const preselect = !editId && params.dish ? all.find((d) => String(d.id) === params.dish) : undefined;
  if (preselect && initFor !== `dish:${preselect.id}`) {
    setInitFor(`dish:${preselect.id}`);
    setDish(preselect);
  }

  // ---------------------------------------------------------------- derived
  const needle = q.trim().toLowerCase();
  const options = all.filter((d) => !needle || d.name.toLowerCase().includes(needle)).slice(0, 5);
  const noMatch = options.length === 0 && needle.length > 1 && !dishesQ.isLoading;

  const dishErr = !dish ? 'Pick the dish you cooked.' : '';
  let dateErr = '';
  if (!date) dateErr = 'Choose a date.';
  else if (date > today) dateErr = 'You can’t log a meal in the future.';
  else if (date < minDate) dateErr = `You can log up to 60 days back (from ${dayMonth(minDate)}).`;
  const showDish = submitted && !!dishErr;
  const showDate = !!dateErr && (submitted || date !== '');

  const monthData = monthQ.data && monthQ.data.month === date.slice(0, 7) ? monthQ.data : undefined;
  const sameDay = [...(monthData?.days[date] ?? []), ...pendingLogs(ops).filter((l) => l.date === date)];
  const dup = dish ? sameDay.find((l) => l.kitchen_dish_id === dish.id && l.meal === meal && l.id !== editId) : undefined;

  const mealName = MEAL_LABEL[meal];
  const when = date === today ? 'Today' : dayMonth(date);

  // ---------------------------------------------------------------- save
  const invalidate = () => Promise.all([qc.invalidateQueries({ queryKey: ['meals'] }), qc.invalidateQueries({ queryKey: qk.today })]);

  const save = async () => {
    setSubmitted(true);
    setServerError(null);
    if (dishErr || dateErr || !dish || saving) return;
    const sub = `${dish.name} · ${mealName} · ${when}`;
    const dupFlash = { title: 'Already in History', sub: `${sub} kept as is`, tone: 'info' as const };

    if (editId) {
      setSaving(true);
      try {
        // Send only what changed (a meal whose dish was removed keeps its dish).
        const changes = {
          ...(dish.id > 0 && dish.id !== editLog?.kitchen_dish_id ? { kitchen_dish_id: dish.id } : {}),
          ...(date !== editLog?.date ? { date } : {}),
          ...(meal !== editLog?.meal ? { meal } : {}),
        };
        const res = await endpoints.updateMeal(editId, changes);
        flash(res.meta?.duplicate ? dupFlash : { title: 'Saved to History', sub });
        void invalidate();
        leave();
      } catch (e) {
        const fe = fieldErrors(e);
        setServerError(fe.date ?? fe.kitchen_dish_id ?? fe.meal ?? errorMessage(e));
      } finally {
        setSaving(false);
      }
      return;
    }

    if (dup) {
      flash(dupFlash);
      leave();
      return;
    }

    const id = uuid();
    setSaving(true);
    try {
      const res = await createMeal(
        { id, kitchen_dish_id: dish.id, date, meal, source: 'manual' },
        { id, kitchen_dish_id: dish.id, dish_name: dish.name, image_url: dish.image_url, image_key: dish.image_key, image_bg: dish.image_bg, date, meal, source: 'manual' },
      );
      if (res.duplicate) flash(dupFlash);
      else
        flash({
          title: 'Saved to History',
          sub,
          action: {
            label: 'Undo',
            after: 'Log removed',
            onPress: async () => {
              await removeMeal(id, res.queued).catch(() => undefined);
              await qc.invalidateQueries({ queryKey: ['meals'] });
              await qc.invalidateQueries({ queryKey: qk.today });
            },
          },
        });
      void invalidate();
      leave();
    } catch (e) {
      const fe = fieldErrors(e);
      setServerError(fe.date ?? fe.kitchen_dish_id ?? fe.meal ?? errorMessage(e));
    } finally {
      setSaving(false);
    }
  };

  // ---------------------------------------------------------------- date picker
  const picker = (
    <DateTimePicker
      value={parseISO(date || today)}
      mode="date"
      display={Platform.OS === 'ios' ? 'inline' : 'default'}
      minimumDate={parseISO(minDate)}
      maximumDate={parseISO(today)}
      accentColor={C.primary}
      onChange={(event, d) => {
        if (Platform.OS !== 'ios') setPickerOpen(false);
        if (event.type === 'set' && d) setDate(todayISO(d));
      }}
    />
  );

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <View style={[styles.header, { paddingTop: top }]}>
        <BackButton icon="close" label="Cancel" onPress={leave} />
        <Txt v="screenTitle" accessibilityRole="header">
          {editId ? 'Edit meal' : 'Log a meal'}
        </Txt>
      </View>

      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingTop: 8, paddingHorizontal: 20, paddingBottom: 20, gap: 20 }}>
          {/* What did you cook? */}
          <View style={{ gap: 8 }}>
            <Txt v="label" color={C.ink2}>
              What did you cook?
            </Txt>
            {dish ? (
              <View style={styles.picked}>
                <DishImage imageUrl={dish.thumb_url ?? dish.image_url} imageKey={dish.image_key} bg={dish.image_bg} scale={1} radius={14} style={{ width: 52, height: 52 }} />
                <Txt v="itemTitle" color={C.primaryPressed} style={{ flex: 1 }} numberOfLines={2}>
                  {dish.name}
                </Txt>
                <Pressable
                  onPress={() => {
                    setDish(null);
                    setQ('');
                  }}
                  accessibilityRole="button"
                  accessibilityLabel={`Change dish, ${dish.name} selected`}
                  style={({ pressed }) => [styles.change, { opacity: pressed ? 0.8 : 1 }]}>
                  <Txt v="toastTitle" color={C.primaryPressed}>
                    Change
                  </Txt>
                </Pressable>
              </View>
            ) : (
              <>
                <Input
                  value={q}
                  onChangeText={(t) => setQ(t.slice(0, 40))}
                  placeholder="Search your dishes"
                  state={showDish ? 'bad' : 'default'}
                  autoCorrect={false}
                  returnKeyType="search"
                  accessibilityLabel="What did you cook?"
                />
                {dishesQ.isLoading || options.length > 0 || noMatch ? (
                <View accessibilityRole="list" accessibilityLabel="Your dishes" style={styles.list}>
                  {dishesQ.isLoading ? (
                    <View style={{ height: 60, alignItems: 'center', justifyContent: 'center' }}>
                      <Spinner size={20} color={C.primary} track={C.borderSoft} />
                    </View>
                  ) : null}
                  {options.map((d, i) => (
                    <Pressable
                      key={d.id}
                      onPress={() => {
                        setDish(d);
                        setQ('');
                      }}
                      accessibilityRole="button"
                      accessibilityLabel={`${d.name}, ${d.category?.name ?? ''}`}
                      style={({ pressed }) => [styles.option, i < options.length - 1 || noMatch ? styles.optionLine : null, pressed ? { backgroundColor: C.surfaceAlt } : null]}>
                      <DishImage imageUrl={d.thumb_url ?? d.image_url} imageKey={d.image_key} bg={d.image_bg} scale={1} radius={12} style={{ width: 42, height: 42 }} />
                      <Txt v="body" weight={600} style={{ flex: 1 }} numberOfLines={1}>
                        {d.name}
                      </Txt>
                      {d.category ? (
                        <Txt v="caption" weight={500} color={C.muted}>
                          {d.category.name}
                        </Txt>
                      ) : null}
                    </Pressable>
                  ))}
                  {noMatch ? (
                    <Pressable
                      onPress={() => router.push({ pathname: '/add-dish', params: { name: q.trim() } })}
                      accessibilityRole="link"
                      style={({ pressed }) => [styles.addRow, pressed ? { backgroundColor: C.surfaceAlt } : null]}>
                      <Txt v="toastTitle" color={C.primary} numberOfLines={1}>
                        + Add “{q.trim()}” as a new dish
                      </Txt>
                    </Pressable>
                  ) : null}
                </View>
                ) : null}
                {showDish ? (
                  <Txt v="helper" color={C.danger} accessibilityRole="alert">
                    {dishErr}
                  </Txt>
                ) : null}
              </>
            )}
          </View>

          {/* Date */}
          <Field label="Date" error={showDate ? dateErr : null} helper="Today or up to 60 days back.">
            <Pressable
              onPress={() => Platform.OS !== 'web' && setPickerOpen(true)}
              accessibilityRole="button"
              accessibilityLabel={`Date, ${date ? longDate(date) : 'not set'}`}
              style={[styles.dateBox, { borderColor: showDate ? C.danger : pickerOpen ? C.primary : C.border }]}>
              <Txt v="input" color={date ? C.ink : C.faint} style={{ flex: 1 }}>
                {date ? (date === today ? `Today, ${dayMonth(date)}` : longDate(date)) : 'dd/mm/yyyy'}
              </Txt>
              <Icon name="calendar" size={20} color={C.muted} />
              <WebPickerInput type="date" value={date} onChange={setDate} min={minDate} max={today} label="Date" />
            </Pressable>
          </Field>
          {pickerOpen && Platform.OS !== 'ios' ? picker : null}

          {/* Meal */}
          <View style={{ gap: 10 }}>
            <Txt v="label" color={C.ink2}>
              Meal
            </Txt>
            <Segmented options={MEAL_OPTIONS} value={meal} onChange={setMeal} track={C.sand} />
          </View>

          {dup && dish ? (
            <View accessibilityLiveRegion="polite" style={styles.dup}>
              <View style={{ marginTop: 1 }}>
                <Icon name="alert" size={18} color={C.accentInk} strokeWidth={2.2} />
              </View>
              <Txt v="bodyS" color={C.accentInk} lh={19.6} style={{ flex: 1 }}>
                {dish.name} is already logged for {meal} on {dayMonth(date)}. Saving will keep the existing entry.
              </Txt>
            </View>
          ) : null}

          {serverError ? (
            <Notice tone="danger" icon="alert">
              {serverError}
            </Notice>
          ) : null}

          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <Icon name="sync" size={16} color={C.muted} />
            <Txt v="helper" color={C.muted} style={{ flex: 1 }}>
              Works offline: saved on your phone, synced later.
            </Txt>
          </View>
        </ScrollView>

        <StickyFooter padding={{ top: 12, h: 20, bottom: 32 }}>
          <Button label={dup && !editId ? 'Keep existing entry' : 'Save meal'} loading={saving} onPress={save} />
        </StickyFooter>
      </KeyboardAvoidingView>

      {Platform.OS === 'ios' ? (
        <Sheet visible={pickerOpen} onClose={() => setPickerOpen(false)} handle>
          {picker}
          <Button label="Done" height={54} onPress={() => setPickerOpen(false)} />
        </Sheet>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 20, paddingBottom: 12 },
  picked: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 10, borderRadius: 18, backgroundColor: C.mint, borderWidth: 1.5, borderColor: C.primary },
  change: { height: 40, paddingHorizontal: 12, borderRadius: 10, backgroundColor: C.white, alignItems: 'center', justifyContent: 'center' },
  list: { borderRadius: 16, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, overflow: 'hidden' },
  option: { flexDirection: 'row', alignItems: 'center', gap: 12, height: 60, paddingHorizontal: 12, backgroundColor: C.white },
  optionLine: { borderBottomWidth: 1, borderBottomColor: C.divider },
  addRow: { height: 48, paddingHorizontal: 14, justifyContent: 'center' },
  dateBox: { height: 54, borderRadius: 14, borderWidth: 1.5, backgroundColor: C.white, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', gap: 10 },
  dup: { flexDirection: 'row', gap: 10, paddingVertical: 12, paddingHorizontal: 14, borderRadius: 14, backgroundColor: C.amberSoft },
});
