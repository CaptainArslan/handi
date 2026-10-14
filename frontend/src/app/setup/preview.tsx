import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { StyleSheet, View } from 'react-native';
import Animated, { FadeInDown } from 'react-native-reanimated';

import { errorMessage } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import type { Weather } from '@/api/types';
import { goBack, locationInput } from '@/components/AuthKit';
import { DishImage } from '@/components/DishImage';
import { FormScroll, SetupHeader, Spacer } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { Icon, WEATHER_ICON } from '@/components/ui/Icon';
import { Txt } from '@/components/ui/Txt';
import { capitalize, longDate, todayISO } from '@/lib/dates';
import { useOnboarding } from '@/store/onboarding';
import { C } from '@/theme/tokens';

function weatherLine(w: Weather, fallbackCity?: string): string {
  const parts = [w.city_label ?? fallbackCity ?? null];
  if (w.temp_now !== null) parts.push(`${w.temp_now}° now`);
  if (w.temp_high !== null) parts.push(`high ${w.temp_high}°`);
  if (w.rain_chance !== null) parts.push(`rain ${w.rain_chance}%`);
  return parts.filter(Boolean).join(' · ');
}

export default function SetupPreview() {
  const draft = useOnboarding();
  const body = {
    dish_ids: draft.dishIds,
    location: locationInput(draft.location),
    family_size: draft.familySize,
    budget: draft.budget,
    repeat_gap_days: draft.repeatGap,
    meals: draft.meals,
  };
  const preview = useQuery({
    queryKey: ['preview-picks', body],
    queryFn: () => endpoints.previewPicks(body),
    staleTime: 5 * 60 * 1000,
    retry: 1,
    // Nothing to preview once the draft is cleared after sign-up (or with custom dishes only).
    enabled: draft.dishIds.length > 0,
  });

  const data = preview.data;
  const weather = data?.weather ?? null;

  return (
    <FormScroll gap={22}>
      <SetupHeader step={5} onBack={() => goBack('/setup/location')} />

      <View style={{ gap: 8 }}>
        <Txt v="overline" size={13} color={C.accentDark} style={{ letterSpacing: 1.04 }}>
          {longDate(data?.date ?? todayISO())}
        </Txt>
        <Txt v="h1" accessibilityRole="header">
          Here’s today for you
        </Txt>
      </View>

      {preview.isPending ? (
        <>
          <View style={[styles.weather, { height: 84, opacity: 0.6 }]} />
          <View style={{ gap: 12 }}>
            {[0, 1, 2].map((i) => (
              <View key={i} style={[styles.row, { height: 110 }]}>
                <View style={[styles.thumb, { backgroundColor: C.sand }]} />
                <View style={{ flex: 1, gap: 8 }}>
                  <View style={[styles.bone, { width: '60%', height: 16 }]} />
                  <View style={[styles.bone, { width: '85%', height: 12 }]} />
                </View>
              </View>
            ))}
          </View>
        </>
      ) : preview.isError ? (
        <View style={styles.error}>
          <Txt v="body" color={C.muted} align="center">
            {errorMessage(preview.error)}
          </Txt>
          <Button label="Try again" variant="outlineGreen" height={44} fill={false} onPress={() => preview.refetch()} />
        </View>
      ) : (
        <>
          {weather ? (
            <View style={styles.weather}>
              <Icon name={WEATHER_ICON[weather.condition] ?? 'sun'} size={36} color={C.accent} strokeWidth={1.8} />
              <View style={{ flex: 1, gap: 2 }}>
                <Txt v="itemTitle" color={C.white}>
                  {weather.headline}
                </Txt>
                <Txt v="helper" color={C.onGreenMuted}>
                  {weatherLine(weather, draft.location?.city)}
                </Txt>
              </View>
            </View>
          ) : null}

          <View style={{ gap: 12 }}>
            {data?.picks.map((p, i) => (
              <Animated.View key={p.id} entering={FadeInDown.delay(50 + i * 130).duration(500)} style={styles.row}>
                <DishImage imageUrl={p.image_url} imageKey={p.image_key} bg={p.image_bg} radius={18} scale={1} style={styles.thumb} alt="" />
                <View style={{ flex: 1, gap: 4 }}>
                  <Txt v="cardTitle">{p.name}</Txt>
                  <Txt v="helper" color={C.muted}>
                    {[p.category?.name, capitalize(p.default_effort), p.reason ?? p.note].filter(Boolean).join(' · ')}
                  </Txt>
                </View>
              </Animated.View>
            ))}
          </View>
        </>
      )}

      <Txt v="bodyS" lh={21} color={C.muted} align="center">
        Every morning you’ll get 3 like these: from different categories, never repeating what you just cooked.
      </Txt>

      <Spacer />
      <View style={{ gap: 12 }}>
        <Button label="Save my kitchen" weight={600} onPress={() => router.push('/signup')} />
        <Button label="Change my dishes" variant="text" weight={600} onPress={() => router.dismissTo('/setup/dishes')} />
      </View>
    </FormScroll>
  );
}

const styles = StyleSheet.create({
  weather: { flexDirection: 'row', alignItems: 'center', gap: 14, padding: 16, borderRadius: 22, backgroundColor: C.primary },
  row: { flexDirection: 'row', alignItems: 'center', gap: 14, padding: 12, borderRadius: 20, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft },
  thumb: { width: 84, height: 84, borderRadius: 18 },
  bone: { borderRadius: 6, backgroundColor: C.sand },
  error: { alignItems: 'center', gap: 14, paddingVertical: 24 },
});
