import { useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { AppState, Linking, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Animated, { Easing, useAnimatedStyle, useSharedValue, withTiming } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { errorMessage, fieldErrors } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { qk, useNotificationSettings } from '@/api/queries';
import type { NotificationSettings, Today } from '@/api/types';
import { goBack } from '@/components/AuthKit';
import { NotificationPreview, ReminderTimePicker } from '@/components/Kitchen';
import { AppHeader, StickyFooter } from '@/components/Layout';
import { LoadError, Loading } from '@/components/LoadState';
import { Button } from '@/components/ui/Button';
import { Switch } from '@/components/ui/Controls';
import { Icon } from '@/components/ui/Icon';
import { flash } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { time12 } from '@/lib/dates';
import { pushPermission, registerDevice, type PushPermission } from '@/lib/notifications';
import { TIME_ERROR, timeOk } from '@/lib/validate';
import { C } from '@/theme/tokens';

const DEMO_TEXT = 'Today’s picks: Daal Chawal, Bhindi, Chicken Karahi';

export default function NotificationSettingsScreen() {
  const settings = useNotificationSettings();
  const back = () => goBack('/settings');

  if (!settings.data) {
    return (
      <View style={{ flex: 1, backgroundColor: C.bg }}>
        <AppHeader title="Notification settings" onBack={back} backLabel="Back to settings" />
        {settings.isError ? <LoadError message={errorMessage(settings.error)} onRetry={() => settings.refetch()} /> : <Loading />}
      </View>
    );
  }
  return <SettingsForm saved={settings.data} onBack={back} />;
}

function SettingsForm({ saved, onBack }: { saved: NotificationSettings; onBack: () => void }) {
  const queryClient = useQueryClient();
  const insets = useSafeAreaInsets();
  const [draft, setDraft] = useState<NotificationSettings | null>(null);
  const [perm, setPerm] = useState<PushPermission>('undetermined');
  const [saving, setSaving] = useState(false);
  const [justSaved, setJustSaved] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [testing, setTesting] = useState(false);
  const [banner, setBanner] = useState(false);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);

  // Permission can change while the app is in the background (phone settings).
  useEffect(() => {
    let alive = true;
    const check = () =>
      pushPermission().then((p) => {
        if (alive) setPerm(p);
      });
    check();
    const sub = AppState.addEventListener('change', (s) => {
      if (s === 'active') check();
    });
    const pending = timers.current;
    return () => {
      alive = false;
      sub.remove();
      pending.forEach(clearTimeout);
    };
  }, []);

  const y = useSharedValue(-140);
  useEffect(() => {
    y.value = withTiming(banner ? 0 : -140, { duration: 450, easing: Easing.bezier(0.2, 0.9, 0.25, 1.1) });
  }, [banner, y]);
  const bannerStyle = useAnimatedStyle(() => ({ transform: [{ translateY: `${y.value}%` }], opacity: y.value <= -130 ? 0 : 1 }));

  const later = (fn: () => void, ms: number) => {
    timers.current.push(setTimeout(fn, ms));
  };

  const value = draft ?? saved;
  const blocked = perm === 'denied';
  const on = value.enabled && !blocked;
  const timeBad = value.enabled && !timeOk(value.time);
  const dirty = value.enabled !== saved.enabled || value.time !== saved.time;

  const change = (patch: Partial<NotificationSettings>) => {
    setDraft({ ...value, ...patch });
    setJustSaved(false);
    setServerError(null);
  };

  const today = queryClient.getQueryData<Today>(qk.today);
  const pickNames = today?.picks?.map((p) => p.name).filter(Boolean) ?? [];
  const previewText = pickNames.length ? `Today’s picks: ${pickNames.join(', ')}` : DEMO_TEXT;

  const save = async () => {
    if (!dirty || timeBad) return;
    setSaving(true);
    try {
      if (value.enabled && perm === 'undetermined') {
        const p = await registerDevice(true);
        setPerm(p);
      }
      const next = await endpoints.updateNotificationSettings({ enabled: value.enabled, time: value.time });
      queryClient.setQueryData(qk.notificationSettings, next);
      queryClient.invalidateQueries({ queryKey: qk.me });
      queryClient.invalidateQueries({ queryKey: qk.today });
      setDraft(null);
      setJustSaved(true);
      later(() => setJustSaved(false), 2000);
    } catch (e) {
      const errs = fieldErrors(e);
      if (errs.time) setServerError(errs.time);
      else flash({ title: 'Couldn’t save your reminder', sub: errorMessage(e), tone: 'info' });
    } finally {
      setSaving(false);
    }
  };

  const test = async () => {
    setTesting(true);
    try {
      await endpoints.testNotification();
      setBanner(true);
      later(() => setBanner(false), 3200);
    } catch (e) {
      flash({ title: 'Couldn’t send a test', sub: errorMessage(e), tone: 'info' });
    } finally {
      setTesting(false);
    }
  };

  const timeError = timeBad ? TIME_ERROR : serverError;
  const label = timeBad ? 'Fix the time to save' : justSaved ? '✓ Saved' : dirty ? 'Save changes' : 'No changes';

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <AppHeader title="Notification settings" onBack={onBack} backLabel="Back to settings" />

      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: 20, paddingBottom: 24, gap: 18 }}>
        {blocked ? (
          <View style={styles.blocked} accessibilityRole="alert">
            <View style={{ flexDirection: 'row', gap: 10, alignItems: 'flex-start' }}>
              <View style={{ marginTop: 1 }}>
                <Icon name="alert" size={20} color={C.dangerText2} strokeWidth={2.2} />
              </View>
              <Txt v="bodyS" lh={20.3} color={C.dangerText2} style={{ flex: 1 }}>
                <Txt v="bodyS" weight={700} color={C.dangerText2}>
                  Notifications are blocked
                </Txt>
                {' for What to Cook in your phone settings, so the reminder can’t reach you.'}
              </Txt>
            </View>
            <Button label="Open phone settings" variant="danger" height={42} radius={12} fontSize={14} fill={false} onPress={() => Linking.openSettings()} />
          </View>
        ) : null}

        <View style={styles.card}>
          <View style={styles.cardRow}>
            <View style={{ flex: 1, gap: 2 }}>
              <Txt v="itemTitle" size={15}>
                Daily reminder
              </Txt>
              <Txt v="helper" color={C.muted}>
                One message a day with your 3 picks
              </Txt>
            </View>
            <Switch value={on} disabled={blocked} onChange={(enabled) => change({ enabled })} accessibilityLabel="Daily reminder" />
          </View>
          <View style={styles.cardFoot}>
            <View style={{ marginTop: 2 }}>
              <Icon name="check" size={16} color={C.primary} strokeWidth={2.2} />
            </View>
            <Txt v="helper" lh={18.85} color={C.ink2} style={{ flex: 1 }}>
              Skipped automatically on days you’ve already logged a meal.
            </Txt>
          </View>
        </View>

        {on ? (
          <>
            <View style={{ gap: 10 }}>
              <Txt v="section">Send it at</Txt>
              <ReminderTimePicker value={value.time} onChange={(time) => change({ time })} invalid={!!timeError} customLabel="Or a custom time" />
              {timeError ? (
                <Txt v="helper" color={C.danger} accessibilityRole="alert">
                  {timeError}
                </Txt>
              ) : null}
            </View>

            <View style={{ gap: 10 }}>
              <Txt v="section">Preview</Txt>
              <NotificationPreview time={timeOk(value.time) ? time12(value.time) : time12(saved.time)} text={previewText} shadow={false} />
              <Button
                label="Send a test notification"
                variant="outline"
                icon="send"
                iconSize={16}
                height={48}
                radius={14}
                fontSize={14}
                loading={testing}
                disabled={blocked}
                onPress={test}
              />
            </View>
          </>
        ) : null}

        <Pressable
          onPress={() => router.push('/notifications')}
          accessibilityRole="button"
          style={({ pressed }) => [styles.link, pressed ? { backgroundColor: C.surfaceAlt2 } : null]}>
          <View style={{ flex: 1, gap: 2 }}>
            <Txt v="itemTitle" size={15}>
              Notification center
            </Txt>
            <Txt v="helper" color={C.muted}>
              See past reminders and app updates
            </Txt>
          </View>
          <Icon name="chevronRight" size={16} color={C.faint} strokeWidth={2.2} />
        </Pressable>
      </ScrollView>

      <StickyFooter padding={{ top: 12, h: 20, bottom: 32 }}>
        <Button
          label={label}
          height={54}
          variant={justSaved ? 'success' : 'primary'}
          disabled={!justSaved && (timeBad || !dirty)}
          loading={saving}
          onPress={save}
        />
      </StickyFooter>

      <Animated.View pointerEvents="none" style={[styles.bannerWrap, { top: insets.top + 10 }, bannerStyle]} accessibilityLiveRegion="polite">
        <View style={styles.banner}>
          <View style={styles.bannerIcon}>
            <Icon name="bowl" size={22} color={C.accent} />
          </View>
          <View style={{ flex: 1, gap: 2 }}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
              <Txt v="toastTitle">What to Cook</Txt>
              <Txt v="caption" color={C.muted}>
                now
              </Txt>
            </View>
            <Txt v="bodyS" lh={19.6} color={C.ink2}>
              {previewText}
            </Txt>
          </View>
        </View>
      </Animated.View>
    </View>
  );
}

const styles = StyleSheet.create({
  blocked: { gap: 10, padding: 14, borderRadius: 18, backgroundColor: C.dangerSoft },
  card: { borderRadius: 20, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, overflow: 'hidden' },
  cardRow: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14 },
  cardFoot: { flexDirection: 'row', gap: 10, paddingVertical: 12, paddingHorizontal: 14, borderTopWidth: 1, borderTopColor: C.divider, backgroundColor: C.surfaceAlt2 },
  link: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: 18, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft },
  bannerWrap: { position: 'absolute', left: 10, right: 10, zIndex: 30, elevation: 20 },
  banner: {
    flexDirection: 'row',
    gap: 12,
    padding: 12,
    borderRadius: 20,
    backgroundColor: 'rgba(255,255,255,0.97)',
    borderWidth: 1,
    borderColor: C.borderSoft,
    shadowColor: 'rgb(28,26,23)',
    shadowOpacity: 0.22,
    shadowRadius: 18,
    shadowOffset: { width: 0, height: 16 },
  },
  bannerIcon: { width: 40, height: 40, borderRadius: 12, backgroundColor: C.primary, alignItems: 'center', justifyContent: 'center' },
});
