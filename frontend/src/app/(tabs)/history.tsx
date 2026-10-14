import { useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { errorMessage } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { qk, useMeals } from '@/api/queries';
import type { MealLog, MonthHistory } from '@/api/types';
import { DishImage } from '@/components/DishImage';
import { useTop } from '@/components/Layout';
import { Button, Fab } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import { Spinner } from '@/components/ui/Spinner';
import { confirmAction } from '@/components/ui/Confirm';
import { flash, NetToast, Snackbar } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { dayTitle, monthName, monthTitle, pad, parseISO, todayISO } from '@/lib/dates';
import { MEAL_LABEL, pendingLogs, queuedDeletes, removeMeal, unqueue } from '@/lib/mealQueue';
import { useQueue, type QueuedOp } from '@/store/queue';
import { C } from '@/theme/tokens';

const WEEKDAYS = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];

function shiftMonth(month: string, delta: number): string {
  const [y, m] = month.split('-').map(Number);
  const d = new Date(y, m - 1 + delta, 1);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
}

const daysIn = (month: string) => {
  const [y, m] = month.split('-').map(Number);
  return new Date(y, m, 0).getDate();
};

interface Removed {
  log: MealLog;
  index: number;
  month: string;
  result: 'unqueued' | 'deleted' | 'queued';
  op?: QueuedOp;
}

export default function HistoryScreen() {
  const top = useTop(52);
  const qc = useQueryClient();
  const today = todayISO();
  const curMonth = today.slice(0, 7);
  const [month, setMonth] = useState(curMonth);
  const [selected, setSelected] = useState(today);
  const [removed, setRemoved] = useState<Removed | null>(null);
  const ops = useQueue((s) => s.ops);

  const query = useMeals(month);
  const data = query.data && query.data.month === month ? query.data : undefined;
  const minMonth = (query.data?.min_date ?? today).slice(0, 7);
  const prevOff = month <= minMonth;
  const nextOff = month >= curMonth;

  const pending = pendingLogs(ops);
  const deleted = queuedDeletes(ops);
  const logsFor = (date: string): MealLog[] => [
    ...(data?.days[date] ?? []).filter((l) => !deleted.has(l.id)),
    ...pending.filter((l) => l.date === date),
  ];

  const goMonth = (delta: number) => {
    const next = shiftMonth(month, delta);
    setMonth(next);
    setSelected(next === curMonth ? today : `${next}-${pad(daysIn(next))}`);
  };

  // ---------------------------------------------------------------- calendar cells
  const [y, m] = month.split('-').map(Number);
  const lead = (new Date(y, m - 1, 1).getDay() + 6) % 7;
  const total = daysIn(month);
  const cells: (string | null)[] = [...Array<null>(lead).fill(null)];
  for (let d = 1; d <= total; d++) cells.push(`${month}-${pad(d)}`);
  while (cells.length % 7) cells.push(null);
  const weeks: (string | null)[][] = [];
  for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7));

  const entries = selected.startsWith(month) ? logsFor(selected) : [];

  // ---------------------------------------------------------------- delete / undo
  const patchMonth = (mon: string, fn: (h: MonthHistory) => MonthHistory) => qc.setQueryData<MonthHistory>(qk.meals(mon), (h) => (h ? fn(h) : h));

  const remove = async (log: MealLog) => {
    const index = (data?.days[log.date] ?? []).findIndex((l) => l.id === log.id);
    const op = log.pending ? useQueue.getState().ops.find((o) => o.op === 'meal.create' && (o.payload as { id?: string }).id === log.id) : undefined;
    if (!log.pending) patchMonth(month, (h) => ({ ...h, days: { ...h.days, [log.date]: (h.days[log.date] ?? []).filter((l) => l.id !== log.id) } }));
    try {
      const result = await removeMeal(log.id, log.pending);
      setRemoved({ log, index, month, result, op });
      if (result === 'deleted') {
        void qc.invalidateQueries({ queryKey: ['meals'] });
        void qc.invalidateQueries({ queryKey: qk.today });
      }
    } catch (e) {
      void qc.invalidateQueries({ queryKey: ['meals'] });
      throw e; // the confirm sheet shows the error toast
    }
  };

  const askRemove = (log: MealLog) =>
    confirmAction({
      title: `Delete ${log.dish_name}?`,
      message: `${MEAL_LABEL[log.meal]} on ${dayTitle(log.date)} will be removed from your history.`,
      confirmLabel: 'Delete meal',
      errorTitle: 'Couldn’t delete the meal',
      run: async () => {
        await remove(log);
        return false; // the Undo bar below is the success message
      },
    });

  const undo = async () => {
    const r = removed;
    setRemoved(null);
    if (!r) return;
    if (r.result === 'unqueued') {
      if (r.op) useQueue.getState().add(r.op);
      return;
    }
    // Put it back where it was while we restore.
    patchMonth(r.month, (h) => {
      const list = [...(h.days[r.log.date] ?? []).filter((l) => l.id !== r.log.id)];
      list.splice(r.index < 0 ? list.length : r.index, 0, r.log);
      return { ...h, days: { ...h.days, [r.log.date]: list } };
    });
    if (r.result === 'queued') {
      unqueue(r.log.id, 'meal.delete');
      return;
    }
    try {
      await endpoints.restoreMeal(r.log.id);
    } catch (e) {
      flash({ title: 'Couldn’t bring the meal back', sub: errorMessage(e), tone: 'error' });
    } finally {
      void qc.invalidateQueries({ queryKey: ['meals'] });
      void qc.invalidateQueries({ queryKey: qk.today });
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingTop: top, paddingHorizontal: 20, paddingBottom: 176, gap: 16 }}>
        <View style={styles.header}>
          <Txt v="h1Compact" accessibilityRole="header">
            History
          </Txt>
          {data ? (
            <View style={styles.chip}>
              <Txt v="toastTitle" size={13} color={C.primaryPressed}>
                {data.logged_days} of {data.days_elapsed} days logged
              </Txt>
            </View>
          ) : null}
        </View>

        <View accessibilityLabel="Calendar" style={styles.calendar}>
          <View style={styles.monthRow}>
            <MonthButton icon="chevronLeft" label="Previous month" disabled={prevOff} onPress={() => goMonth(-1)} />
            <Txt v="month" accessibilityLiveRegion="polite">
              {monthTitle(month)}
            </Txt>
            <MonthButton icon="chevronRight" label="Next month" disabled={nextOff} onPress={() => goMonth(1)} />
          </View>
          <View style={styles.week} importantForAccessibility="no-hide-descendants">
            {WEEKDAYS.map((d, i) => (
              <Txt key={i} v="badge" color={C.muted} align="center" style={{ flex: 1 }}>
                {d}
              </Txt>
            ))}
          </View>
          <View style={{ gap: 4 }}>
            {weeks.map((week, wi) => (
              <View key={wi} style={styles.week}>
                {week.map((date, di) => {
                  if (!date) return <View key={di} style={styles.cell} />;
                  const n = data ? logsFor(date).length : 0;
                  const future = date > today;
                  const on = date === selected;
                  const isToday = date === today;
                  const dayNum = Number(date.slice(8));
                  return (
                    <Pressable
                      key={di}
                      disabled={future}
                      onPress={() => setSelected(date)}
                      accessibilityRole="button"
                      accessibilityState={{ selected: on, disabled: future }}
                      accessibilityLabel={`${dayNum} ${monthName(parseISO(date).getMonth())}${n ? `, ${n} logged` : ''}${future ? ', future' : ''}`}
                      style={[
                        styles.cell,
                        on ? { backgroundColor: C.primary } : isToday ? { backgroundColor: C.white, borderWidth: 1.5, borderColor: C.primary } : null,
                      ]}>
                      <Txt v="bodyS" lh={17} weight={on || isToday ? 700 : 500} color={on ? C.white : isToday ? C.primary : future ? C.futureDay : C.ink}>
                        {dayNum}
                      </Txt>
                      <View style={[styles.dot, { backgroundColor: n > 0 ? C.accent : 'transparent' }]} />
                    </Pressable>
                  );
                })}
              </View>
            ))}
          </View>
        </View>

        <View style={{ gap: 10 }}>
          <Txt v="body" weight={700} color={C.ink2} accessibilityRole="header">
            {dayTitle(selected, today)}
          </Txt>
          {!data && query.isError ? (
            <View style={[styles.empty, { gap: 12 }]}>
              <Txt v="bodyS" color={C.muted} align="center">
                {errorMessage(query.error)}
              </Txt>
              <Button label="Try again" variant="outline" height={44} fill={false} onPress={() => query.refetch()} style={{ alignSelf: 'center' }} />
            </View>
          ) : !data ? (
            <View style={{ paddingVertical: 24, alignItems: 'center' }}>
              <Spinner size={24} color={C.primary} track={C.borderSoft} width={3} />
            </View>
          ) : entries.length === 0 ? (
            <View style={styles.empty}>
              <Txt v="bodyS" color={C.muted} align="center">
                Nothing logged for this day.
              </Txt>
            </View>
          ) : (
            entries.map((log) => <EntryRow key={log.id} log={log} onDelete={() => void askRemove(log)} />)
          )}
        </View>
      </ScrollView>

      <Fab label="Log a meal" onPress={() => router.push({ pathname: '/log-meal', params: { date: selected } })} />
      <Snackbar message={removed ? `${removed.log.dish_name} removed` : null} onUndo={undo} onHide={() => setRemoved(null)} filled bottom={168} side={16} />
      <NetToast bottom={172} />
    </View>
  );
}

function MonthButton({ icon, label, disabled, onPress }: { icon: 'chevronLeft' | 'chevronRight'; label: string; disabled: boolean; onPress: () => void }) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ disabled }}
      style={({ pressed }) => [styles.monthBtn, { transform: [{ scale: pressed ? 0.94 : 1 }] }]}>
      <Icon name={icon} size={18} strokeWidth={2.2} color={disabled ? C.disabledText : C.ink} />
    </Pressable>
  );
}

function EntryRow({ log, onDelete }: { log: MealLog; onDelete: () => void }) {
  const meal = MEAL_LABEL[log.meal];
  return (
    <View style={styles.entry}>
      <DishImage imageUrl={log.thumb_url ?? log.image_url} imageKey={log.image_key} bg={log.image_bg} scale={1} radius={14} style={{ width: 52, height: 52 }} />
      <View style={{ flex: 1, minWidth: 0, gap: 2 }}>
        <Txt v="itemTitle" numberOfLines={1}>
          {log.dish_name}
        </Txt>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
          {log.pending ? <Icon name="sync" size={12} color={C.accentDark} strokeWidth={2.2} /> : null}
          <Txt v="helper" color={log.pending ? C.accentDark : C.muted} numberOfLines={1} style={{ flexShrink: 1 }}>
            {log.pending ? `${meal} · Will sync later` : `${meal} · ${log.source === 'suggestion' ? 'From today’s picks' : 'Logged manually'}`}
          </Txt>
        </View>
      </View>
      {!log.pending ? (
        <Pressable
          onPress={() => router.push({ pathname: '/log-meal', params: { id: log.id, date: log.date } })}
          accessibilityRole="button"
          accessibilityLabel={`Edit ${log.dish_name}`}
          style={({ pressed }) => [styles.rowBtn, pressed ? { backgroundColor: C.surfaceAlt } : null]}>
          <Icon name="pencil" size={18} color={C.ink2} />
        </Pressable>
      ) : null}
      <Pressable
        onPress={onDelete}
        accessibilityRole="button"
        accessibilityLabel={`Delete ${log.dish_name}`}
        style={({ pressed }) => [styles.rowBtn, pressed ? { backgroundColor: C.dangerSoft } : null]}>
        <Icon name="trash" size={18} color={C.danger} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  chip: { paddingVertical: 6, paddingHorizontal: 12, borderRadius: 99, backgroundColor: C.mint },
  calendar: { padding: 14, borderRadius: 22, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, gap: 10 },
  monthRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  monthBtn: { width: 44, height: 44, borderRadius: 12, backgroundColor: C.surfaceAlt, alignItems: 'center', justifyContent: 'center' },
  week: { flexDirection: 'row', gap: 4 },
  cell: { flex: 1, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center', gap: 3 },
  dot: { width: 5, height: 5, borderRadius: 3 },
  empty: { padding: 20, borderRadius: 18, borderWidth: 1.5, borderStyle: 'dashed', borderColor: C.dashed },
  entry: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10, paddingLeft: 12, paddingRight: 10, borderRadius: 18, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft },
  rowBtn: { width: 44, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
});
