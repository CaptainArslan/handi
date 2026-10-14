import { useMutation, useQueryClient } from '@tanstack/react-query';
import { router, type Href } from 'expo-router';
import { useEffect, useState, type ReactNode } from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { endpoints } from '@/api/endpoints';
import { qk, useMe } from '@/api/queries';
import type { Me, NotificationSettings } from '@/api/types';
import { MeState } from '@/components/AccountKit';
import { Avatar } from '@/components/Kitchen';
import { useBottom, useTop } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { Switch } from '@/components/ui/Controls';
import { Icon } from '@/components/ui/Icon';
import { Sheet } from '@/components/ui/Sheet';
import { Spinner } from '@/components/ui/Spinner';
import { flash, NetToast } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { APP_VERSION } from '@/lib/config';
import { ago, capitalize, time12 } from '@/lib/dates';


import { useQueue } from '@/store/queue';
import { useUi } from '@/store/ui';
import { C } from '@/theme/tokens';
import { endSession, resetTo } from '@/lib/session';
import { registerDevice } from '@/lib/notifications';
import { initials } from '@/lib/validate';

export default function Settings() {
  const me = useMe();
  const top = useTop(52);
  const bottom = useBottom(112);

  if (!me.data) {
    return (
      <View style={{ flex: 1, backgroundColor: C.bg }}>
        <MeState error={me.error} onRetry={() => void me.refetch()} />
        <NetToast bottom={100} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{ paddingTop: top, paddingHorizontal: 20, paddingBottom: bottom, gap: 18 }}>
        <Txt v="h1Compact" accessibilityRole="header">
          Settings
        </Txt>
        <SettingsBody me={me.data} dataUpdatedAt={me.dataUpdatedAt} refetch={() => me.refetch()} />
      </ScrollView>
      <NetToast bottom={100} />
    </View>
  );
}

function SettingsBody({ me, dataUpdatedAt, refetch }: { me: Me; dataUpdatedAt: number; refetch: () => Promise<unknown> }) {
  const queryClient = useQueryClient();
  const k = me.kitchen;
  const [logoutOpen, setLogoutOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);

  // ---------------------------------------------------------------- reminder switch (optimistic)
  const reminder = useMutation({
    mutationFn: async (body: NotificationSettings) => {
      // Turning the reminder on asks for push permission on this phone (once).
      if (body.enabled) void registerDevice(true);
      return endpoints.updateNotificationSettings(body);
    },
    onMutate: async (body) => {
      await queryClient.cancelQueries({ queryKey: qk.me });
      const prev = queryClient.getQueryData<Me>(qk.me);
      if (prev) queryClient.setQueryData<Me>(qk.me, { ...prev, kitchen: { ...prev.kitchen, notifications: body } });
      queryClient.setQueryData(qk.notificationSettings, body);
      return { prev };
    },
    onError: (_e, _body, ctx) => {
      if (ctx?.prev) {
        queryClient.setQueryData(qk.me, ctx.prev);
        queryClient.setQueryData(qk.notificationSettings, ctx.prev.kitchen.notifications);
      }
    },
    onSuccess: (res) => queryClient.setQueryData(qk.notificationSettings, res),
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: qk.me });
      void queryClient.invalidateQueries({ queryKey: qk.notificationSettings });
      void queryClient.invalidateQueries({ queryKey: qk.today });
    },
  });

  const pending = useQueue((s) => s.ops.length);

  const logout = async () => {
    setLoggingOut(true);
    // POST /auth/logout revokes this phone's token and removes its push device server-side.
    try {
      await endpoints.logout();
    } catch {
      // token may already be revoked
    }
    await endSession();
    setLogoutOpen(false);
    flash({ title: 'You’re logged out', sub: 'Your kitchen is saved for next time.', tone: 'info' });
    resetTo('/login');
  };

  const meals = k.meals.length;
  const unread = me.counts.unread_notifications;
  const go = (href: Href) => () => router.push(href);

  return (
    <>
      {/* Profile card → 7.2 */}
      <Pressable
        onPress={go('/account/profile')}
        accessibilityRole="link"
        accessibilityLabel="Edit profile"
        style={({ pressed }) => [styles.profile, { backgroundColor: pressed ? C.primaryPressed : C.primary }]}>
        <Avatar initial={initials(me.name)} size={52} fontSize={19} border={0} shadow={false} />
        <View style={{ flex: 1, minWidth: 0, gap: 2 }}>
          <Txt v="cardTitle" color={C.white} numberOfLines={1}>
            {me.name}
          </Txt>
          <Txt v="helper" color={C.onGreenMuted} numberOfLines={1}>
            {me.masked_email} · family of {k.family_size}
          </Txt>
        </View>
        <View style={styles.editPill}>
          <Txt v="badge" size={13} color={C.ink}>
            Edit
          </Txt>
        </View>
      </Pressable>

      <SyncRow lastServer={k.last_synced_at} dataUpdatedAt={dataUpdatedAt} refetch={refetch} />

      <Group title="Kitchen">
        <Row label="Family size" value={`${k.family_size} ${k.family_size === 1 ? 'person' : 'people'}`} onPress={go('/account/family')} />
        <Row label="Budget" value={capitalize(k.budget)} onPress={go('/account/family')} />
        <Row label="Meal preferences" value={`${k.repeat_gap_days} days · ${meals} ${meals === 1 ? 'meal' : 'meals'}`} onPress={go('/account/preferences')} />
        <Row label="Custom dishes" value={`${me.counts.custom_dishes} added`} onPress={() => router.navigate({ pathname: '/dishes', params: { tab: 'custom' } })} />
        <Row label="Location" value={k.location.city ?? k.location.label ?? 'Not set'} onPress={go('/account/location')} />
      </Group>

      <Group title="Daily reminder">
        <View style={[styles.row, { minHeight: 56 }]}>
          <Txt v="body" weight={600} style={{ flex: 1 }}>
            Morning notification
          </Txt>
          <Switch
            value={k.notifications.enabled}
            onChange={(enabled) => reminder.mutate({ enabled, time: k.notifications.time })}
            accessibilityLabel="Morning notification"
          />
        </View>
        {k.notifications.enabled ? <Row label="Time & test message" value={time12(k.notifications.time)} onPress={go('/notification-settings')} /> : null}
        <Row
          label="Notification center"
          onPress={go('/notifications')}
          accessibilityLabel={unread > 0 ? `Notification center, ${unread} unread` : 'Notification center'}
          trailing={
            unread > 0 ? (
              <View style={styles.badge}>
                <Txt v="badge" color={C.white}>
                  {unread > 99 ? '99+' : unread}
                </Txt>
              </View>
            ) : null
          }
        />
      </Group>

      <Group title="Account">
        <Row label="Change password" onPress={go('/account/password')} />
        <Row
          label="Terms & privacy"
          onPress={() => {
            useUi.getState().set({ legalFrom: 'settings' });
            router.push({ pathname: '/legal', params: { tab: 'privacy' } });
          }}
        />
        <Row label="Log out" role="button" chevron={false} onPress={() => setLogoutOpen(true)} />
        <Row label="Delete account" chevron={false} danger onPress={go('/account/delete-account')} />
      </Group>

      <Txt v="caption" color={C.muted} align="center">
        What to Cook · version {APP_VERSION}
      </Txt>

      <Sheet visible={logoutOpen} onClose={() => !loggingOut && setLogoutOpen(false)} scrim="light" accessibilityLabel="Log out of this phone?">
        <Txt v="sheetTitle" accessibilityRole="header">
          Log out of this phone?
        </Txt>
        <Txt v="body" color={C.muted}>
          {pending > 0
            ? `You have ${pending} unsynced change${pending === 1 ? '' : 's'}. Connect to the internet first or they’ll be lost.`
            : 'Your dishes and history are saved to your account. Daily reminders stop on this phone.'}
        </Txt>
        <Button label="Log out" variant="dark" height={54} loading={loggingOut} onPress={logout} />
        <Button label="Stay logged in" variant="outline" height={54} disabled={loggingOut} onPress={() => setLogoutOpen(false)} />
      </Sheet>
    </>
  );
}

// ---------------------------------------------------------------- Sync status row

function SyncRow({ lastServer, dataUpdatedAt, refetch }: { lastServer: string | null; dataUpdatedAt: number; refetch: () => Promise<unknown> }) {
  const ops = useQueue((s) => s.ops.length);
  const queueSyncing = useQueue((s) => s.syncing);
  const lastQueue = useQueue((s) => s.lastSyncedAt);
  const [refreshing, setRefreshing] = useState(false);
  const [now, setNow] = useState(() => Date.now());

  // Keep "2 min ago" fresh.
  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 30000);
    return () => clearInterval(t);
  }, []);

  const syncing = queueSyncing || refreshing;
  const times = [lastQueue, lastServer, dataUpdatedAt ? new Date(dataUpdatedAt).toISOString() : null]
    .filter((x): x is string => !!x)
    .map((x) => new Date(x).getTime());
  const last = times.length ? new Date(Math.max(...times)).toISOString() : null;
  const since = ago(last, Math.max(now, ...times));

  const title = syncing ? 'Syncing…' : ops > 0 ? `${ops} ${ops === 1 ? 'change' : 'changes'} waiting to sync` : 'Everything is synced';
  const sub = syncing ? 'Sending your meals and edits' : ops > 0 ? 'Saved safely on this phone' : since === 'Just now' ? 'Just now' : `Last synced ${since}`;
  const dot = syncing ? C.accent : ops > 0 ? C.warning : C.success;

  const syncNow = async () => {
    setRefreshing(true);
    try {
      await useQueue.getState().flush();
      await refetch();
    } finally {
      setRefreshing(false);
      setNow(Date.now());
    }
  };

  return (
    <View style={styles.sync} accessibilityRole="summary" accessibilityLiveRegion="polite">
      <View style={[styles.syncDot, { backgroundColor: dot }]} />
      <View style={{ flex: 1, gap: 1 }}>
        <Txt v="toastTitle">{title}</Txt>
        <Txt v="caption" color={C.muted}>
          {sub}
        </Txt>
      </View>
      <Pressable
        onPress={syncNow}
        disabled={syncing}
        accessibilityRole="button"
        accessibilityState={{ disabled: syncing, busy: syncing }}
        style={({ pressed }) => [styles.syncBtn, { backgroundColor: pressed ? C.surfaceAlt2 : C.white }]}>
        {syncing ? <Spinner size={12} width={2} color={C.primary} track={C.dashed} /> : null}
        <Txt v="badge" size={13}>
          {syncing ? 'Syncing' : 'Sync now'}
        </Txt>
      </Pressable>
    </View>
  );
}

// ---------------------------------------------------------------- Group list

function Group({ title, children }: { title: string; children: ReactNode }) {
  const items = (Array.isArray(children) ? children : [children]).filter(Boolean);
  return (
    <View style={{ gap: 8 }}>
      <View style={{ marginHorizontal: 4 }}>
        <Txt v="overline" color={C.muted} accessibilityRole="header">
          {title}
        </Txt>
      </View>
      <View style={styles.group}>
        {items.map((child, i) => (
          <View key={i} style={i > 0 ? { borderTopWidth: 1, borderTopColor: C.divider } : null}>
            {child}
          </View>
        ))}
      </View>
    </View>
  );
}

function Row({
  label,
  value,
  onPress,
  trailing,
  chevron = true,
  danger,
  role = 'link',
  accessibilityLabel,
}: {
  label: string;
  value?: string;
  onPress: () => void;
  trailing?: ReactNode;
  chevron?: boolean;
  danger?: boolean;
  role?: 'link' | 'button';
  accessibilityLabel?: string;
}) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole={role}
      accessibilityLabel={accessibilityLabel ?? (value ? `${label}, ${value}` : label)}
      style={({ pressed }) => [styles.row, { backgroundColor: pressed ? C.surfaceAlt2 : C.white }]}>
      <Txt v="body" weight={600} color={danger ? C.danger : C.ink} style={{ flex: 1 }} numberOfLines={1}>
        {label}
      </Txt>
      {value ? (
        <Txt v="bodyS" color={C.muted} numberOfLines={1} style={{ maxWidth: '55%' }}>
          {value}
        </Txt>
      ) : null}
      {trailing}
      {chevron ? <Icon name="chevronRight" size={16} color={C.faint} strokeWidth={2.2} /> : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  profile: { flexDirection: 'row', alignItems: 'center', gap: 14, padding: 14, borderRadius: 20 },
  editPill: { paddingVertical: 6, paddingHorizontal: 12, borderRadius: 99, backgroundColor: C.accent },
  sync: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 12,
    paddingRight: 12,
    paddingLeft: 14,
    borderRadius: 16,
    backgroundColor: C.white,
    borderWidth: 1,
    borderColor: C.borderSoft,
  },
  syncDot: { width: 10, height: 10, borderRadius: 5, marginLeft: 2 },
  syncBtn: { flexDirection: 'row', alignItems: 'center', gap: 6, height: 40, paddingHorizontal: 12, borderRadius: 10, borderWidth: 1.5, borderColor: C.border },
  group: { borderRadius: 18, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, overflow: 'hidden' },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 52, paddingHorizontal: 14 },
  badge: { minWidth: 22, height: 22, paddingHorizontal: 7, borderRadius: 99, backgroundColor: C.notify, alignItems: 'center', justifyContent: 'center' },
});
