import { useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Animated, { FadeInDown } from 'react-native-reanimated';

import { errorMessage } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { qk, useMe, useNotifications } from '@/api/queries';
import type { AppNotification } from '@/api/types';
import { DishImage } from '@/components/DishImage';
import { AppHeader, useBottom } from '@/components/Layout';
import { LoadError, Loading } from '@/components/LoadState';
import { IconButton } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import { confirmAction } from '@/components/ui/Confirm';
import { flash, Snackbar } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { clock12, dayMonth, daysBetween, time12, todayISO, weekday } from '@/lib/dates';
import { openLink } from '@/lib/links';
import { C, S } from '@/theme/tokens';

type Filter = 'all' | 'unread';
type Page = { data: AppNotification[]; meta: { unread_count: number } };

function groupTitle(date: string, today: string) {
  const diff = daysBetween(date, today);
  if (diff <= 0) return 'Today';
  if (diff === 1) return 'Yesterday';
  if (diff < 7) return weekday(date);
  return dayMonth(date);
}

export default function NotificationsScreen() {
  const queryClient = useQueryClient();
  const bottom = useBottom(28);
  const [filter, setFilter] = useState<Filter>('all');
  const list = useNotifications(filter);
  const me = useMe();
  const [last, setLast] = useState<{ item: AppNotification; idx: number } | null>(null);

  const items = list.data?.data ?? [];
  const unread = list.data?.meta.unread_count ?? 0;
  const reminder = me.data?.kitchen.notifications;
  const reminderTime = time12(reminder?.time ?? '09:00');

  /** Apply the same change to every cached filter (all + unread). */
  const patch = (fn: (page: Page, filter: Filter) => Page) => {
    (['all', 'unread'] as const).forEach((f) => queryClient.setQueryData<Page>(qk.notifications(f), (old) => (old ? fn(old, f) : old)));
  };
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['notifications'] });
    queryClient.invalidateQueries({ queryKey: qk.me });
    queryClient.invalidateQueries({ queryKey: qk.today });
  };

  const open = (n: AppNotification) => {
    if (!n.read_at) {
      const now = new Date().toISOString();
      patch((p, f) => ({
        data: f === 'unread' ? p.data.filter((x) => x.id !== n.id) : p.data.map((x) => (x.id === n.id ? { ...x, read_at: now } : x)),
        meta: { unread_count: Math.max(0, p.meta.unread_count - 1) },
      }));
      endpoints
        .readNotification(n.id)
        .catch(() => undefined)
        .finally(refresh);
    }
    openLink(n.deep_link);
  };

  const markAll = async () => {
    const now = new Date().toISOString();
    patch((p, f) => ({ data: f === 'unread' ? [] : p.data.map((x) => ({ ...x, read_at: x.read_at ?? now })), meta: { unread_count: 0 } }));
    try {
      await endpoints.readAllNotifications();
    } catch (e) {
      flash({ title: 'Couldn’t mark all as read', sub: errorMessage(e), tone: 'error' });
    }
    refresh();
  };

  const clear = async (n: AppNotification) => {
    const idx = items.findIndex((x) => x.id === n.id);
    patch((p) => ({
      data: p.data.filter((x) => x.id !== n.id),
      meta: { unread_count: Math.max(0, p.meta.unread_count - (n.read_at ? 0 : 1)) },
    }));
    setLast({ item: n, idx });
    try {
      await endpoints.clearNotification(n.id);
    } catch (e) {
      setLast(null);
      refresh();
      throw e; // the confirm sheet shows the error toast
    }
  };

  const askClear = (n: AppNotification) =>
    confirmAction({
      title: 'Clear this notification?',
      message: n.title,
      confirmLabel: 'Clear',
      errorTitle: 'Couldn’t clear the notification',
      run: async () => {
        await clear(n);
        return false; // the Undo bar is the success message
      },
    });

  const undo = async () => {
    const l = last;
    if (!l) return;
    setLast(null);
    patch((p, f) => {
      if ((f === 'unread' && l.item.read_at) || p.data.some((x) => x.id === l.item.id)) return p;
      const data = p.data.slice();
      data.splice(Math.min(Math.max(0, l.idx), data.length), 0, l.item);
      return { data, meta: { unread_count: p.meta.unread_count + (l.item.read_at ? 0 : 1) } };
    });
    try {
      await endpoints.restoreNotification(l.item.id);
    } catch (e) {
      flash({ title: 'Couldn’t restore the notification', sub: errorMessage(e), tone: 'error' });
    }
    refresh();
  };

  // Group by local calendar day, newest first (the API already sorts).
  const today = todayISO();
  const groups: { title: string; items: AppNotification[] }[] = [];
  items.forEach((n) => {
    const title = groupTitle(todayISO(new Date(n.created_at)), today);
    let g = groups.find((x) => x.title === title);
    if (!g) {
      g = { title, items: [] };
      groups.push(g);
    }
    g.items.push(n);
  });

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <AppHeader
        title="Notifications"
        sub={list.data ? (unread ? `${unread} unread` : 'All caught up') : undefined}
        onBack={() => router.navigate('/today')}
        backLabel="Back to Today"
        right={<IconButton icon="sliders" accessibilityLabel="Notification settings" onPress={() => router.push('/notification-settings')} />}
      />

      <View style={styles.filterRow}>
        <View style={styles.filterTrack} accessibilityRole="tablist" accessibilityLabel="Filter">
          {(['all', 'unread'] as const).map((f) => {
            const on = filter === f;
            return (
              <Pressable
                key={f}
                onPress={() => setFilter(f)}
                accessibilityRole="tab"
                accessibilityState={{ selected: on }}
                style={[styles.filterOpt, on ? [{ backgroundColor: C.white }, S.segActive] : null]}>
                <Txt v="segment" size={13} color={on ? C.ink : C.muted}>
                  {f === 'all' ? 'All' : unread ? `Unread · ${unread}` : 'Unread'}
                </Txt>
              </Pressable>
            );
          })}
        </View>
        <View style={{ flex: 1 }} />
        <Pressable onPress={markAll} disabled={unread === 0} accessibilityRole="button" accessibilityState={{ disabled: unread === 0 }} style={styles.markAll}>
          <Txt v="segment" size={13} color={unread ? C.primary : C.disabledText}>
            Mark all read
          </Txt>
        </Pressable>
      </View>

      <Pressable
        onPress={() => router.push('/notification-settings')}
        accessibilityRole="button"
        style={({ pressed }) => [styles.reminder, { backgroundColor: pressed ? C.primaryPressed : C.primary }]}>
        <View style={styles.reminderIcon}>
          <Icon name="bell" size={19} color={C.ink} />
        </View>
        <View style={{ flex: 1, gap: 1 }}>
          <Txt v="toastTitle" color={C.white}>
            {reminder?.enabled === false ? 'Daily reminder is off' : 'Daily reminder is on'}
          </Txt>
          <Txt v="caption" color={C.onGreenMuted}>
            {reminder?.enabled === false ? 'Turn it on to get your 3 picks every morning' : `Every morning at ${reminderTime} · skipped if you’ve already logged`}
          </Txt>
        </View>
        <Txt v="segment" size={13} color={C.accent}>
          {reminder?.enabled === false ? 'Turn on' : 'Edit'}
        </Txt>
      </Pressable>

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{ paddingHorizontal: 20, paddingBottom: bottom, gap: 18 }}>
        {!list.data && list.isPending ? (
          <Loading />
        ) : !list.data && list.isError ? (
          <LoadError message={errorMessage(list.error)} onRetry={() => list.refetch()} />
        ) : items.length === 0 ? (
          <Animated.View entering={FadeInDown.duration(300)} style={styles.empty}>
            <View style={styles.emptyIcon}>
              <Icon name="bell" size={40} color={C.primary} strokeWidth={1.8} />
            </View>
            <Txt v="numberS" align="center">
              {filter === 'unread' ? 'No unread notifications' : 'You’re all caught up'}
            </Txt>
            <Txt v="bodyS" lh={21} color={C.muted} align="center" style={{ maxWidth: 260 }}>
              {`Tomorrow’s picks will show up here at ${reminderTime}.`}
            </Txt>
          </Animated.View>
        ) : (
          groups.map((g) => (
            <View key={g.title} style={{ gap: 8 }}>
              <Txt v="overline" color={C.muted} style={{ marginHorizontal: 4 }} accessibilityRole="header">
                {g.title}
              </Txt>
              {g.items.map((n, i) => (
                <Item key={n.id} n={n} index={i} onOpen={() => open(n)} onClear={() => void askClear(n)} />
              ))}
            </View>
          ))
        )}
      </ScrollView>

      <Snackbar message={last ? 'Notification cleared' : null} onUndo={undo} onHide={() => setLast(null)} bottom={bottom} duration={4500} />
    </View>
  );
}

function Item({ n, index, onOpen, onClear }: { n: AppNotification; index: number; onOpen: () => void; onClear: () => void }) {
  const unread = !n.read_at;
  const hasImage = !!(n.image_key || n.image_url);
  return (
    <Animated.View
      entering={FadeInDown.duration(300).delay(Math.min(index, 6) * 30)}
      style={[styles.item, { backgroundColor: unread ? C.unreadBg : C.white, borderColor: unread ? C.unreadBorder : C.borderSoft }]}>
      <Pressable
        onPress={onOpen}
        accessibilityRole="button"
        accessibilityLabel={`${unread ? 'Unread. ' : ''}${n.title}. ${n.body}`}
        style={({ pressed }) => [{ flex: 1, minWidth: 0, flexDirection: 'row', gap: 12, opacity: pressed ? 0.75 : 1 }]}>
        <View>
          {hasImage ? (
            <DishImage imageUrl={n.image_url} imageKey={n.image_key} bg={n.image_bg} scale={1} radius={14} style={{ width: 48, height: 48 }} />
          ) : (
            <View style={styles.iconTile}>
              <Icon name={n.type === 'sync' ? 'sync' : n.type === 'account' ? 'shieldCheck' : 'bell'} size={22} color={C.primary} />
            </View>
          )}
          {unread ? <View style={styles.dot} accessibilityLabel="Unread" /> : null}
        </View>
        <View style={{ flex: 1, minWidth: 0, gap: 3 }}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
            <Txt v="toastTitle" weight={unread ? 700 : 600} style={{ flex: 1 }}>
              {n.title}
            </Txt>
            <Txt v="caption" color={C.muted}>
              {clock12(n.created_at)}
            </Txt>
          </View>
          <Txt v="helper" lh={18.2} color={C.ink2}>
            {n.body}
          </Txt>
          {n.cta_label ? (
            <Txt v="badge" color={C.primary}>
              {`${n.cta_label} →`}
            </Txt>
          ) : null}
        </View>
      </Pressable>
      <Pressable onPress={onClear} accessibilityRole="button" accessibilityLabel="Clear notification" hitSlop={4} style={({ pressed }) => [styles.clear, { opacity: pressed ? 0.6 : 1 }]}>
        <Icon name="close" size={16} color={C.faint} strokeWidth={2.4} />
      </Pressable>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  filterRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: 20, paddingBottom: 12 },
  filterTrack: { flexDirection: 'row', gap: 4, padding: 4, borderRadius: 14, backgroundColor: C.sand2 },
  filterOpt: { height: 36, paddingHorizontal: 14, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  markAll: { height: 40, paddingHorizontal: 4, justifyContent: 'center' },
  reminder: { marginHorizontal: 20, marginBottom: 14, flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, paddingHorizontal: 14, borderRadius: 18 },
  reminderIcon: { width: 38, height: 38, borderRadius: 12, backgroundColor: C.accent, alignItems: 'center', justifyContent: 'center' },
  item: { flexDirection: 'row', alignItems: 'flex-start', gap: 6, paddingTop: 12, paddingBottom: 12, paddingLeft: 12, paddingRight: 6, borderRadius: 20, borderWidth: 1 },
  iconTile: { width: 48, height: 48, borderRadius: 14, backgroundColor: C.mint, alignItems: 'center', justifyContent: 'center' },
  dot: { position: 'absolute', right: -3, top: -3, width: 12, height: 12, borderRadius: 6, backgroundColor: C.notify, borderWidth: 2, borderColor: C.white },
  clear: { width: 36, height: 36, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  empty: { alignItems: 'center', gap: 12, paddingVertical: 48, paddingHorizontal: 16 },
  emptyIcon: { width: 88, height: 88, borderRadius: 30, backgroundColor: C.mint, alignItems: 'center', justifyContent: 'center' },
});
