
import { useEffect, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, { Easing, FadeInDown, useAnimatedStyle, useSharedValue, withRepeat, withSequence, withTiming } from 'react-native-reanimated';

import { endpoints } from '@/api/endpoints';
import { qk, useToday } from '@/api/queries';
import { NotificationPreview, ReminderTimePicker } from '@/components/Kitchen';
import { FormScroll, Spacer } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import { flash } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { time12 } from '@/lib/dates';
import { registerDevice } from '@/lib/notifications';
import { queryClient } from '@/lib/query-client';
import { TIME_ERROR, timeOk } from '@/lib/validate';
import { C } from '@/theme/tokens';
import { resetTo } from '@/lib/session';

const FALLBACK = 'Daal Chawal, Bhindi, Chicken Karahi';

export default function AllowReminder() {
  const today = useToday();
  const [time, setTime] = useState('09:00');
  const [busy, setBusy] = useState<'allow' | 'later' | null>(null);
  const ok = timeOk(time);

  const names = today.data?.picks.slice(0, 3).map((p) => p.name).join(', ') || FALLBACK;

  const save = async (enabled: boolean) => {
    try {
      const settings = await endpoints.updateNotificationSettings({ enabled, time });
      queryClient.setQueryData(qk.notificationSettings, settings);
    } catch {
      // Offline or server hiccup: the default reminder stays; it can be changed in Settings.
    }
    void queryClient.invalidateQueries({ queryKey: qk.me });
  };

  const allow = async () => {
    if (!ok || busy) return;
    setBusy('allow');
    await registerDevice(true);
    await save(true);
    flash({ title: 'Your kitchen is ready', sub: 'Reminder set. Here are today’s 3 picks.' });
    resetTo('/today');
  };

  const later = async () => {
    if (busy) return;
    setBusy('later');
    await save(false);
    flash({ title: 'Your kitchen is ready', sub: 'Turn on reminders anytime in Settings.' });
    resetTo('/today');
  };

  return (
    <FormScroll top={72} gap={24}>
      <View style={{ alignItems: 'center', gap: 20 }}>
        <View style={styles.bellTile} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          <RingingBell />
        </View>
        <View style={{ gap: 8 }}>
          <Txt v="h1Compact" align="center" accessibilityRole="header">
            One reminder a day. That’s it.
          </Txt>
          <Txt v="body" color={C.muted} align="center">
            Get today’s 3 dishes before you plan the shopping.
          </Txt>
        </View>
      </View>

      <Animated.View entering={FadeInDown.delay(200).duration(600)} accessibilityLabel="Notification preview">
        <NotificationPreview time={time12(ok ? time : '09:00')} text={`Today’s picks: ${names}`} />
      </Animated.View>

      <View style={{ gap: 10 }}>
        <Txt v="label" color={C.ink2}>
          Send it at
        </Txt>
        <ReminderTimePicker value={time} onChange={setTime} invalid={!ok} />
        {!ok ? (
          <Txt v="helper" color={C.danger} accessibilityRole="alert">
            {TIME_ERROR}
          </Txt>
        ) : null}
      </View>

      <Spacer />
      <View style={{ gap: 10 }}>
        {ok ? (
          <Button label="Allow notifications" weight={600} loading={busy === 'allow'} disabled={busy === 'later'} onPress={allow} />
        ) : (
          <Button label="Fix the time to continue" weight={600} disabled />
        )}
        <Button label="Not now" variant="textMuted" weight={600} fontSize={15} disabled={!!busy} onPress={later} />
      </View>
    </FormScroll>
  );
}

function RingingBell() {
  const r = useSharedValue(0);
  useEffect(() => {
    const t = (deg: number, ms: number) => withTiming(deg, { duration: ms, easing: Easing.inOut(Easing.ease) });
    // 2.4s loop: ring during the first 40%, then rest.
    r.value = withRepeat(withSequence(t(14, 240), t(-12, 240), t(8, 240), t(0, 240), withTiming(0, { duration: 1440 })), -1, false);
  }, [r]);
  const style = useAnimatedStyle(() => ({ transform: [{ rotate: `${r.value}deg` }] }));
  return (
    <Animated.View style={[{ transformOrigin: '50% 10%' }, style]}>
      <Icon name="bell" size={40} color={C.ink} strokeWidth={1.8} />
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  bellTile: { width: 84, height: 84, borderRadius: 28, backgroundColor: C.accent, alignItems: 'center', justifyContent: 'center' },
});
