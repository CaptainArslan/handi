import { useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import Animated, { Easing, useAnimatedStyle, useSharedValue, withDelay, withTiming } from 'react-native-reanimated';

import { errorMessage, isNetworkError, status } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { qk, useToday } from '@/api/queries';
import type { Effort, KitchenDish, MealLog, Today, TodayPick } from '@/api/types';
import { DishImage } from '@/components/DishImage';
import { Avatar } from '@/components/Kitchen';
import { useTop } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { Segmented, Tag } from '@/components/ui/Controls';
import { Icon, WEATHER_ICON } from '@/components/ui/Icon';
import { Spinner } from '@/components/ui/Spinner';
import { flash, NetToast } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { clock12, clock24, greeting, lastCookedTag, lastShort, longDate, shortDate, time12, todayISO } from '@/lib/dates';
import { createMeal, MEAL_LABEL, queuedMealCount, removeMeal } from '@/lib/mealQueue';
import { uuid } from '@/lib/uuid';
import { useNet } from '@/store/net';
import { useQueue } from '@/store/queue';
import { C, S, tokens } from '@/theme/tokens';
import { initials } from '@/lib/validate';
import { useAuth } from '@/store/auth';

const EFFORT_LABEL: Record<Effort, string> = { quick: 'Quick to make', medium: 'Medium effort', heavy: 'Heavy effort' };
const STEP_INDEX = { pick: 0, planned: 1, done: 2 } as const;

type Stage = Today['stage'];

/** "Fits hot weather" (or the server's reason when it is weather-related). */
function fitTag(pick: TodayPick, today: Today): string | null {
  if (pick.reason && pick.reason.startsWith('Fits')) return pick.reason;
  const cond = today.weather?.condition;
  if (cond && (pick.weather.includes(cond) || pick.weather.includes('any'))) return `Fits ${cond} weather`;
  return null;
}

export default function TodayScreen() {
  const top = useTop(52);
  const qc = useQueryClient();
  const query = useToday();
  const online = useNet((s) => s.online);
  const ops = useQueue((s) => s.ops);
  const queued = queuedMealCount(ops);
  const [busy, setBusy] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const fade = useSharedValue(1);

  const today = query.data;

  const setToday = (fn: (t: Today) => Today) => qc.setQueryData<Today>(qk.today, (t) => (t ? fn(t) : t));
  const refresh = () => Promise.all([qc.invalidateQueries({ queryKey: qk.today }), qc.invalidateQueries({ queryKey: ['meals'] })]);

  const fadeStyle = useAnimatedStyle(() => ({
    opacity: fade.value,
    transform: [{ translateY: (1 - fade.value) * 8 }, { scale: 0.98 + 0.02 * fade.value }],
  }));

  if (!today) {
    return (
      <View style={[styles.center, { paddingTop: top }]}>
        {query.isError ? (
          <View style={{ gap: 14, alignItems: 'center', paddingHorizontal: 32 }}>
            <Txt v="body" color={C.muted} align="center">
              {errorMessage(query.error)}
            </Txt>
            <Button label="Try again" variant="outline" height={48} fill={false} onPress={() => query.refetch()} />
          </View>
        ) : (
          <Spinner size={28} color={C.primary} track={C.borderSoft} width={3} />
        )}
      </View>
    );
  }

  const stage: Stage = today.stage;
  const plan = today.plan;
  const meal = plan?.meal ?? 'dinner';

  // ---------------------------------------------------------------- actions

  const choose = async (dish: KitchenDish, m: 'lunch' | 'dinner' = meal) => {
    const prev = today;
    setToday((t) => ({
      ...t,
      stage: 'planned',
      plan: { dish, meal: m, picked_at: t.plan && t.plan.dish.id === dish.id ? t.plan.picked_at : new Date().toISOString() },
    }));
    if (!useNet.getState().online) return;
    try {
      qc.setQueryData(qk.today, await endpoints.plan(dish.id, m));
    } catch (e) {
      if (isNetworkError(e)) return;
      qc.setQueryData(qk.today, prev);
      flash({ title: errorMessage(e), tone: 'info' });
    }
  };

  const backToPick = async () => {
    setToday((t) => ({ ...t, stage: 'pick', plan: null }));
    if (!useNet.getState().online) return;
    try {
      qc.setQueryData(qk.today, await endpoints.unplan());
    } catch (e) {
      if (!isNetworkError(e)) void qc.invalidateQueries({ queryKey: qk.today });
    }
  };

  const canShuffle = online && today.shuffles_left > 0;
  const shuffle = async () => {
    if (!canShuffle || busy) return;
    setBusy(true);
    fade.set(withTiming(0, { duration: tokens.motion.shuffleFade }));
    try {
      const [next] = await Promise.all([endpoints.shuffle(), new Promise((r) => setTimeout(r, tokens.motion.shuffleFade))]);
      qc.setQueryData(qk.today, next);
    } catch (e) {
      if (status(e) === 429) setToday((t) => ({ ...t, shuffles_left: 0 }));
      else flash({ title: errorMessage(e), tone: 'info' });
    } finally {
      fade.set(withTiming(1, { duration: tokens.motion.shuffleFade }));
      setBusy(false);
    }
  };

  const markCooked = async () => {
    if (!plan || busy) return;
    const id = uuid();
    // The account's today (server timezone), not the phone's clock.
    const date = today.date || todayISO();
    const d = plan.dish;
    setBusy(true);
    try {
      const res = await createMeal(
        { id, kitchen_dish_id: d.id, date, meal: plan.meal, source: 'suggestion' },
        { id, kitchen_dish_id: d.id, dish_name: d.name, image_url: d.image_url, image_key: d.image_key, image_bg: d.image_bg, date, meal: plan.meal, source: 'suggestion' },
      );
      setToday((t) => ({
        ...t,
        stage: 'done',
        log: res.log,
        stats: res.queued ? { ...t.stats, meals_this_month: t.stats.meals_this_month + 1 } : t.stats,
      }));
      if (!res.queued) void refresh();
    } catch (e) {
      flash({ title: errorMessage(e), tone: 'info' });
    } finally {
      setBusy(false);
    }
  };

  const undoLog = async () => {
    const log = today.log;
    if (!log || busy) return;
    const pick = today.picks.find((p) => p.id === log.kitchen_dish_id);
    const fallback = pick ? { dish: pick as KitchenDish, meal: log.meal === 'lunch' ? ('lunch' as const) : ('dinner' as const), picked_at: null } : null;
    setToday((t) => {
      const nextPlan = t.plan ?? fallback;
      return {
        ...t,
        stage: nextPlan ? 'planned' : 'pick',
        plan: nextPlan,
        log: null,
        stats: log.pending ? { ...t.stats, meals_this_month: Math.max(0, t.stats.meals_this_month - 1) } : t.stats,
      };
    });
    setBusy(true);
    try {
      const r = await removeMeal(log.id, log.pending);
      if (r === 'deleted') void refresh();
    } catch (e) {
      flash({ title: errorMessage(e), tone: 'info' });
      void refresh();
    } finally {
      setBusy(false);
    }
  };

  // ---------------------------------------------------------------- render

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{ paddingTop: top, paddingHorizontal: 20, paddingBottom: 112, gap: 18 }}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            tintColor={C.primary}
            colors={[C.primary]}
            onRefresh={async () => {
              setRefreshing(true);
              await refresh();
              setRefreshing(false);
            }}
          />
        }>
        <Header today={today} />
        <StepTracker stage={stage} />

        {stage === 'pick' ? (
          <>
            <View style={styles.titleRow}>
              <Txt v="cardHero" accessibilityRole="header" style={{ flexShrink: 1 }}>
                Today’s 3 picks
              </Txt>
              <Pressable
                onPress={shuffle}
                disabled={!canShuffle}
                accessibilityRole="button"
                accessibilityLabel={online ? `Shuffle, ${today.shuffles_left} left today` : 'Shuffle needs internet'}
                accessibilityState={{ disabled: !canShuffle }}
                style={({ pressed }) => [styles.shufflePill, { backgroundColor: canShuffle ? C.ink : C.sand2, transform: [{ scale: pressed ? 0.97 : 1 }] }]}>
                <Icon name="shuffle" size={16} strokeWidth={2.4} color={canShuffle ? C.white : C.muted} />
                <Txt v="toastTitle" color={canShuffle ? C.white : C.muted}>
                  Shuffle
                </Txt>
                <View style={[styles.shuffleBadge, { backgroundColor: canShuffle ? C.accent : C.disabledBg }]}>
                  <Txt v="toastTitle" size={13} color={canShuffle ? C.ink : C.muted}>
                    {today.shuffles_left}
                  </Txt>
                </View>
              </Pressable>
            </View>

            <WeatherStrip today={today} online={online} />

            <Animated.View style={[{ gap: 12 }, fadeStyle]}>
              {today.picks[0] ? <TopPickCard pick={today.picks[0]} today={today} onChoose={() => choose(today.picks[0])} /> : null}
              <View style={{ flexDirection: 'row', gap: 12 }}>
                {today.picks.slice(1, 3).map((p) => (
                  <SmallCard key={p.id} pick={p} onChoose={() => choose(p)} />
                ))}
              </View>
            </Animated.View>

            <Pressable
              onPress={shuffle}
              disabled={!canShuffle}
              accessibilityRole="button"
              accessibilityState={{ disabled: !canShuffle }}
              style={({ pressed }) => [styles.shuffleBar, { borderColor: canShuffle ? C.ink : C.dashed, opacity: pressed ? 0.8 : 1 }]}>
              <View style={[styles.shuffleTile, { backgroundColor: canShuffle ? C.accent : C.sand2 }]}>
                <Icon name="shuffle" size={20} strokeWidth={2.4} color={canShuffle ? C.ink : C.faint} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Txt v="body" weight={700} lh={20}>
                  {!online ? 'Shuffle needs internet' : today.shuffles_left > 0 ? 'Not feeling these? Shuffle all 3' : 'No shuffles left today'}
                </Txt>
                <Txt v="caption" weight={500} color={C.muted}>
                  {!online
                    ? 'You can still pick from the saved 3'
                    : today.shuffles_left > 0
                      ? `${today.shuffles_left} of ${today.shuffles_per_day} shuffles left today · new ideas each time`
                      : 'Pick one of these, or log something else'}
                </Txt>
              </View>
            </Pressable>

            <Pressable onPress={() => router.push('/log-meal')} accessibilityRole="link" style={styles.link}>
              <Txt v="bodyS" weight={600} color={C.ink2} style={{ textDecorationLine: 'underline' }}>
                Already cooked something else? Log it
              </Txt>
            </Pressable>
          </>
        ) : null}

        {stage === 'planned' && plan ? (
          <>
            <View style={styles.titleRow}>
              <Txt v="cardHero" accessibilityRole="header">
                Today’s menu
              </Txt>
              <View style={styles.plannedPill}>
                <View style={{ width: 7, height: 7, borderRadius: 4, backgroundColor: C.success }} />
                <Txt v="toastTitle" size={13} color={C.primaryPressed}>
                  Planned
                </Txt>
              </View>
            </View>

            <View style={[styles.card, { borderRadius: 28 }, S.cardPlanned]}>
              <View style={{ height: 230, borderTopLeftRadius: 28, borderTopRightRadius: 28, overflow: 'hidden' }}>
                <DishImage imageUrl={plan.dish.image_url} imageKey={plan.dish.image_key} bg={plan.dish.image_bg} scale={1.1} alt={plan.dish.name} style={StyleSheet.absoluteFill} />
                {plan.picked_at ? (
                  <View style={styles.pickedBadge}>
                    <Icon name="clock" size={13} color={C.accent} strokeWidth={2.4} />
                    <Txt v="badge" color={C.white}>
                      Picked at {clock12(plan.picked_at)}
                    </Txt>
                  </View>
                ) : null}
              </View>
              <View style={{ padding: 16, gap: 14 }}>
                <View style={{ gap: 8 }}>
                  <Txt v="number" style={{ letterSpacing: -0.26 }}>
                    {plan.dish.name}
                  </Txt>
                  <View style={styles.tags}>
                    {plan.dish.category ? <Tag label={plan.dish.category.name} /> : null}
                    <Tag label={EFFORT_LABEL[plan.dish.effort]} />
                    <Tag label={lastCookedTag(plan.dish.days_since_cooked)} bg={C.amberSoft} color={C.accentText} />
                  </View>
                </View>
                <View style={{ gap: 8 }}>
                  <Txt v="helper" weight={700} color={C.ink2}>
                    Cooking it for
                  </Txt>
                  <Segmented
                    options={[
                      { value: 'lunch', label: 'Lunch' },
                      { value: 'dinner', label: 'Dinner' },
                    ]}
                    value={meal}
                    onChange={(m) => m !== meal && choose(plan.dish, m)}
                  />
                </View>
                <Button label="Mark as cooked" icon="check" iconStroke={2.8} loading={busy} onPress={markCooked} />
              </View>
            </View>

            <View style={styles.note}>
              <View style={{ marginTop: 1 }}>
                <Icon name="info" size={18} color={C.primary} />
              </View>
              <Txt v="helper" color={C.ink2} lh={18.85} style={{ flex: 1 }}>
                Tap <Txt v="helper" weight={700} color={C.ink2}>Mark as cooked</Txt> once it’s on the table. That’s what keeps it out of your picks for the next {today.repeat_gap_days} days.
              </Txt>
            </View>

            <View style={{ gap: 10 }}>
              <Txt v="section">Changed your mind?</Txt>
              {today.picks
                .filter((p) => p.id !== plan.dish.id)
                .slice(0, 2)
                .map((p) => (
                  <Pressable
                    key={p.id}
                    onPress={() => choose(p)}
                    accessibilityRole="button"
                    accessibilityLabel={`Switch to ${p.name}`}
                    style={({ pressed }) => [styles.switchRow, { backgroundColor: pressed ? C.surfaceAlt2 : C.white }]}>
                    <DishImage imageUrl={p.image_url} imageKey={p.image_key} bg={p.image_bg} scale={1} radius={12} style={{ width: 48, height: 48 }} />
                    <View style={{ flex: 1 }}>
                      <Txt v="body" weight={700} lh={20} numberOfLines={1}>
                        {p.name}
                      </Txt>
                      <Txt v="caption" color={C.muted}>
                        {lastShort(p.days_since_cooked)}
                      </Txt>
                    </View>
                    <Txt v="toastTitle" size={13} color={C.primary}>
                      Switch
                    </Txt>
                  </Pressable>
                ))}
              <Button label="See all picks & shuffle" variant="outline" height={48} radius={14} fontSize={14} style={{ backgroundColor: 'transparent' }} onPress={backToPick} />
            </View>
          </>
        ) : null}

        {stage === 'done' && today.log ? <DoneStage today={today} log={today.log} busy={busy} onUndo={undoLog} /> : null}
      </ScrollView>

      <NetToast bottom={100} delay={450} offlineSub={queued > 0 ? `${queued} meal${queued === 1 ? '' : 's'} will sync later` : 'Showing this morning’s saved picks'} />
    </View>
  );
}

// ---------------------------------------------------------------- pieces

function Header({ today }: { today: Today }) {
  const unread = today.unread_notifications;
  // Full name from the signed-in account (Today only carries the first name).
  const fullName = useAuth((s) => s.user?.name) ?? today.greeting_name;
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
      <Pressable onPress={() => router.navigate('/settings')} accessibilityRole="button" accessibilityLabel="Profile and settings">
        <Avatar initial={initials(fullName)} />
      </Pressable>
      <View style={{ flex: 1, gap: 1 }}>
        <Txt v="helper" weight={600} color={C.muted}>
          {longDate(today.date)}
        </Txt>
        <Txt v="greeting" numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.8}>
          {greeting()}, {today.greeting_name}
        </Txt>
      </View>
      <Pressable
        onPress={() => router.push('/notifications')}
        accessibilityRole="button"
        accessibilityLabel={`Notifications, ${unread} unread`}
        style={({ pressed }) => [styles.bell, { transform: [{ scale: pressed ? 0.94 : 1 }] }]}>
        <Icon name="bell" size={20} />
        {unread > 0 ? <View style={styles.bellDot} /> : null}
      </Pressable>
    </View>
  );
}

function StepTracker({ stage }: { stage: Stage }) {
  const idx = STEP_INDEX[stage];
  return (
    <View accessibilityLabel={`Today’s progress: step ${idx + 1} of 3`} style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
      {['Pick', 'Cook', 'Log'].map((label, i) => {
        const done = i < idx;
        const cur = i === idx;
        return (
          <View key={label} style={{ flexDirection: 'row', alignItems: 'center', gap: 6, flex: i < 2 ? 1 : undefined }}>
            <View style={[styles.stepPill, { backgroundColor: cur ? C.amberSoft : 'transparent' }]}>
              <View
                style={[
                  styles.stepDot,
                  done ? { backgroundColor: C.primary } : cur ? { backgroundColor: C.accent } : { backgroundColor: C.white, borderWidth: 2, borderColor: C.disabledBg },
                ]}>
                {done ? (
                  <Icon name="check" size={11} color={C.white} strokeWidth={3.6} />
                ) : (
                  <Txt v="badge" color={cur ? C.ink : C.faint}>
                    {i + 1}
                  </Txt>
                )}
              </View>
              <Txt v="toastTitle" size={13} color={cur ? C.ink : done ? C.primaryPressed : C.faint}>
                {label}
              </Txt>
            </View>
            {i < 2 ? <View style={{ flex: 1, minWidth: 8, height: 2, borderRadius: 99, backgroundColor: done ? C.primary : C.disabledBg }} /> : null}
          </View>
        );
      })}
    </View>
  );
}

function WeatherStrip({ today, online }: { today: Today; online: boolean }) {
  const w = today.weather;
  const look = w ? tokens.weather[w.condition] : null;
  const city = w?.city_label || 'Your area';
  const temp = w?.temp_now != null ? `${w.temp_now}°` : '';
  return (
    <View accessibilityLabel={look ? `Weather: ${temp} ${look.label} in ${city}` : `Weather in ${city}`} style={styles.weather}>
      {look && w ? (
        <>
          <View style={[styles.wxIcon, { backgroundColor: look.bg }]}>
            <Icon name={WEATHER_ICON[w.condition]} size={16} color={look.fg} strokeWidth={2.3} />
          </View>
          <Txt v="toastTitle" numberOfLines={1}>
            {temp ? `${temp} ` : ''}
            {look.label}
          </Txt>
          <View style={{ width: 1, height: 18, backgroundColor: C.border }} />
        </>
      ) : null}
      <View style={{ flex: 1, minWidth: 0, flexDirection: 'row', alignItems: 'center', gap: 4, paddingLeft: look ? 0 : 6 }}>
        <Icon name="pin" size={13} color={C.muted} strokeWidth={2.2} />
        <Txt v="helper" color={C.muted} numberOfLines={1} style={{ flexShrink: 1 }}>
          {city}
        </Txt>
      </View>
      <View style={[styles.fresh, { backgroundColor: online ? C.mint : C.amberSoft }]}>
        <Txt v="badgeS" color={online ? C.primaryPressed : C.accentText}>
          {online ? 'Live' : `Saved ${clock24(today.generated_at)}`.trim()}
        </Txt>
      </View>
    </View>
  );
}

function TopPickCard({ pick, today, onChoose }: { pick: TodayPick; today: Today; onChoose: () => void }) {
  const fit = fitTag(pick, today);
  return (
    <View style={[styles.card, { borderRadius: 28 }, S.cardTop]}>
      <View style={{ height: 200, borderTopLeftRadius: 28, borderTopRightRadius: 28, overflow: 'hidden' }}>
        <DishImage imageUrl={pick.image_url} imageKey={pick.image_key} bg={pick.image_bg} scale={1.12} alt={pick.name} style={StyleSheet.absoluteFill} />
        <View style={[styles.badge, { left: 12, backgroundColor: C.accent }]}>
          <Txt v="badge">Top pick</Txt>
        </View>
        <View style={[styles.badge, { right: 12, backgroundColor: C.white }]}>
          <Txt v="badge">{EFFORT_LABEL[pick.effort]}</Txt>
        </View>
      </View>
      <View style={{ padding: 16, gap: 12 }}>
        <View style={{ gap: 8 }}>
          <Txt v="cardHero">{pick.name}</Txt>
          <View style={styles.tags}>
            <Tag label={lastCookedTag(pick.days_since_cooked)} />
            {fit ? <Tag label={fit} bg={C.amberSoft} color={C.accentText} /> : null}
          </View>
        </View>
        <Button label="Cook this today" iconRight="arrowRight" iconStroke={2.4} height={54} onPress={onChoose} />
      </View>
    </View>
  );
}

function SmallCard({ pick, onChoose }: { pick: TodayPick; onChoose: () => void }) {
  return (
    <View style={[styles.card, { flex: 1, borderRadius: 24 }, S.cardSmall]}>
      <View style={{ height: 118, borderTopLeftRadius: 24, borderTopRightRadius: 24, overflow: 'hidden' }}>
        <DishImage imageUrl={pick.image_url} imageKey={pick.image_key} bg={pick.image_bg} scale={1.15} alt={pick.name} style={StyleSheet.absoluteFill} />
        {pick.category ? (
          <View style={[styles.badge, { left: 8, top: 8, paddingVertical: 4, paddingHorizontal: 9, backgroundColor: C.white }]}>
            <Txt v="badgeS">{pick.category.name}</Txt>
          </View>
        ) : null}
      </View>
      <View style={{ padding: 12, gap: 10, flex: 1 }}>
        <View style={{ gap: 3, flex: 1 }}>
          <Txt v="itemTitle" lh={19.2}>
            {pick.name}
          </Txt>
          <Txt v="caption" color={C.muted}>
            {lastShort(pick.days_since_cooked)}
          </Txt>
        </View>
        <Button label="Cook this" variant="outlineGreen" height={42} radius={14} fontSize={14} onPress={onChoose} />
      </View>
    </View>
  );
}

function DoneStage({ today, log, busy, onUndo }: { today: Today; log: MealLog; busy: boolean; onUndo: () => void }) {
  const pop = useSharedValue(0);
  useEffect(() => {
    pop.value = withDelay(120, withTiming(1, { duration: 450, easing: Easing.bezier(0.3, 1.6, 0.5, 1) }));
  }, [pop]);
  const popStyle = useAnimatedStyle(() => ({ transform: [{ scale: pop.value }] }));

  const gap = today.repeat_gap_days;
  const mealName = MEAL_LABEL[log.meal];
  const streak = today.stats.streak_days;
  const other = log.meal === 'dinner' ? 'lunch' : 'dinner';

  return (
    <>
      <View style={{ alignItems: 'center', gap: 14, paddingTop: 8 }}>
        <View style={{ width: 168, height: 168 }}>
          <View pointerEvents="none" style={styles.ring} />
          <View style={[{ width: 168, height: 168, borderRadius: 84, backgroundColor: C.white }, S.floatCard]}>
            <DishImage imageUrl={log.image_url} imageKey={log.image_key} bg={log.image_bg} scale={1} radius={84} alt={log.dish_name} style={{ width: 168, height: 168 }} />
          </View>
          <Animated.View style={[styles.doneBadge, popStyle]}>
            <Icon name="check" size={24} color={C.white} strokeWidth={3} />
          </Animated.View>
        </View>
        <Txt v="overline" color={C.accentDark} style={{ letterSpacing: 1.2, marginTop: 6 }}>
          {`Logged · ${mealName} · ${shortDate(log.date)}`}
        </Txt>
        <Txt v="h1Compact" align="center" accessibilityRole="header" style={{ lineHeight: 30.8 }}>
          {log.dish_name} it is!
        </Txt>
        <Txt v="body" color={C.muted} align="center" style={{ maxWidth: 300 }}>
          {log.pending
            ? `Saved on this phone. It will sync when you’re back online, and tomorrow’s picks will skip it for ${gap} days.`
            : `Tomorrow’s picks will skip ${log.dish_name} for ${gap} days, so your week stays varied.`}
        </Txt>
      </View>

      <View style={{ flexDirection: 'row', gap: 10 }}>
        <View style={styles.stat}>
          <Txt v="number" color={C.primary}>
            {streak} {streak === 1 ? 'day' : 'days'}
          </Txt>
          <Txt v="caption" weight={600} color={C.muted}>
            logged in a row
          </Txt>
        </View>
        <View style={styles.stat}>
          <Txt v="number" color={C.accentDark}>
            {today.stats.meals_this_month}
          </Txt>
          <Txt v="caption" weight={600} color={C.muted}>
            meals this month
          </Txt>
        </View>
      </View>

      <View style={styles.nextCard}>
        <View pointerEvents="none" style={styles.nextCircle} />
        <View style={styles.nextTile}>
          <Icon name="bell" size={22} color={C.ink} />
        </View>
        <View style={{ flex: 1, gap: 2 }}>
          <Txt v="caption" weight={600} color={C.onGreenMuted}>
            Next picks arrive
          </Txt>
          <Txt v="itemTitle" color={C.white}>
            {today.reminder.enabled ? `Tomorrow at ${time12(today.reminder.time)}` : 'Tomorrow, in the app'}
          </Txt>
        </View>
        <Pressable onPress={() => router.push('/notification-settings')} accessibilityRole="link" style={{ minHeight: 44, justifyContent: 'center' }}>
          <Txt v="toastTitle" size={13} color={C.accent}>
            Change
          </Txt>
        </Pressable>
      </View>

      <View style={{ gap: 10 }}>
        <Button
          label={`Also log today’s ${other}`}
          variant="outline"
          icon="plus"
          iconStroke={2.4}
          height={52}
          fontSize={15}
          onPress={() => router.push({ pathname: '/log-meal', params: { meal: other } })}
        />
        <View style={{ flexDirection: 'row', justifyContent: 'center', gap: 4 }}>
          <Pressable onPress={() => router.navigate('/history')} accessibilityRole="link" style={styles.textLink}>
            <Txt v="toastTitle" color={C.primary}>
              See history
            </Txt>
          </Pressable>
          <Pressable onPress={onUndo} disabled={busy} accessibilityRole="button" style={styles.textLink}>
            <Txt v="toastTitle" color={C.danger}>
              Undo log
            </Txt>
          </Pressable>
        </View>
      </View>
    </>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: C.bg },
  titleRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 },
  bell: { width: 46, height: 46, borderRadius: 16, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, alignItems: 'center', justifyContent: 'center' },
  bellDot: { position: 'absolute', top: 11, right: 12, width: 8, height: 8, borderRadius: 4, backgroundColor: C.notify, borderWidth: 2, borderColor: C.white },
  stepPill: { flexDirection: 'row', alignItems: 'center', gap: 6, height: 30, paddingLeft: 4, paddingRight: 10, borderRadius: 99 },
  stepDot: { width: 22, height: 22, borderRadius: 11, alignItems: 'center', justifyContent: 'center' },
  shufflePill: { flexDirection: 'row', alignItems: 'center', gap: 8, height: 46, paddingLeft: 14, paddingRight: 8, borderRadius: 99, flexShrink: 0 },
  shuffleBadge: { minWidth: 28, height: 28, borderRadius: 99, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 6 },
  weather: { marginTop: -6, height: 44, paddingHorizontal: 6, borderRadius: 14, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, flexDirection: 'row', alignItems: 'center', gap: 10 },
  wxIcon: { width: 32, height: 32, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  fresh: { paddingVertical: 4, paddingHorizontal: 9, borderRadius: 99 },
  card: { backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft },
  badge: { position: 'absolute', top: 12, paddingVertical: 6, paddingHorizontal: 11, borderRadius: 99 },
  tags: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  shuffleBar: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, paddingHorizontal: 14, borderRadius: 20, borderWidth: 1.5, borderStyle: 'dashed' },
  shuffleTile: { width: 40, height: 40, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  link: { minHeight: 44, alignItems: 'center', justifyContent: 'center' },
  plannedPill: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingVertical: 6, paddingHorizontal: 12, borderRadius: 99, backgroundColor: C.mint },
  pickedBadge: { position: 'absolute', left: 12, bottom: 12, flexDirection: 'row', alignItems: 'center', gap: 6, paddingVertical: 6, paddingHorizontal: 11, borderRadius: 99, backgroundColor: C.inkToast },
  note: { flexDirection: 'row', gap: 10, paddingVertical: 12, paddingHorizontal: 14, borderRadius: 16, backgroundColor: C.surfaceAlt },
  switchRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 8, paddingLeft: 8, paddingRight: 12, borderRadius: 18, borderWidth: 1, borderColor: C.borderSoft },
  ring: { position: 'absolute', left: -14, right: -14, top: -14, bottom: -14, borderRadius: 98, borderWidth: 2, borderStyle: 'dashed', borderColor: C.accent },
  doneBadge: { position: 'absolute', right: 4, bottom: 8, width: 48, height: 48, borderRadius: 24, backgroundColor: C.primary, borderWidth: 4, borderColor: C.bg, alignItems: 'center', justifyContent: 'center' },
  stat: { flex: 1, padding: 14, borderRadius: 20, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, gap: 2 },
  nextCard: { overflow: 'hidden', flexDirection: 'row', alignItems: 'center', gap: 14, padding: 16, borderRadius: 22, backgroundColor: C.primary },
  nextCircle: { position: 'absolute', right: -30, bottom: -40, width: 120, height: 120, borderRadius: 60, borderWidth: 2, borderStyle: 'dashed', borderColor: C.primaryLine },
  nextTile: { width: 46, height: 46, borderRadius: 14, backgroundColor: C.accent, alignItems: 'center', justifyContent: 'center' },
  textLink: { minHeight: 44, paddingHorizontal: 10, justifyContent: 'center' },
});
