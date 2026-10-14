import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Animated, { FadeInDown } from 'react-native-reanimated';

import { errorMessage } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { useCities } from '@/api/queries';
import type { City } from '@/api/types';
import { goBack, LinkButton } from '@/components/AuthKit';
import { Overline, SetupHeader, StickyFooter, useTop } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { ChipRow, ChoiceChip, RadioDot } from '@/components/ui/Controls';
import { Input } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import { Spinner } from '@/components/ui/Spinner';
import { Txt } from '@/components/ui/Txt';
import { useOnboarding } from '@/store/onboarding';
import { C } from '@/theme/tokens';

export default function SetupCity() {
  const top = useTop(56);
  const location = useOnboarding((s) => s.location);
  const set = useOnboarding((s) => s.set);
  // The full list is cached (works offline); search filters it on the phone.
  const cities = useCities();
  const [q, setQ] = useState('');
  const [selectedId, setSelectedId] = useState<number | null>(location?.mode === 'city' ? (location.city_id ?? null) : null);

  const all: City[] = [...(cities.data?.popular ?? []), ...(cities.data?.provinces ?? []).flatMap((p) => p.cities)];
  const selected = all.find((c) => c.id === selectedId) ?? null;

  const weather = useQuery({
    queryKey: ['weather', { city_id: selectedId }],
    queryFn: () => endpoints.weather({ city_id: selectedId ?? undefined }),
    enabled: selectedId !== null,
    staleTime: 10 * 60 * 1000,
  });

  const query = q.trim().toLowerCase();
  const groups = (cities.data?.provinces ?? [])
    .map((p) => ({ name: p.name, cities: p.cities.filter((c) => !query || c.name.toLowerCase().includes(query)) }))
    .filter((g) => g.cities.length > 0);

  const confirm = () => {
    if (!selected) return;
    set({
      location: {
        mode: 'city',
        city_id: selected.id,
        label: `${selected.name}, ${selected.province}`,
        city: selected.name,
        weather: weather.data ?? null,
      },
    });
    router.push('/setup/preview');
  };

  const weatherLine = weather.data ? `Today ${weather.data.summary}` : weather.isError ? 'Weather unavailable right now' : 'Today …';

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: C.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <View style={{ paddingTop: top, paddingHorizontal: 24, paddingBottom: 12, gap: 16 }}>
        <SetupHeader step={4} onBack={() => goBack('/setup/location')} backLabel="Back to location options" />
        <View style={{ gap: 6 }}>
          <Txt v="h1Compact" accessibilityRole="header">
            Choose your city
          </Txt>
          <Txt v="body" color={C.muted}>
            We only use it to check today’s weather.
          </Txt>
        </View>
        <Input
          icon="search"
          height={52}
          clearable
          value={q}
          onChangeText={(t) => setQ(t.slice(0, 30))}
          maxLength={30}
          placeholder="Search for your city"
          accessibilityLabel="Search cities"
          autoComplete="off"
          autoCorrect={false}
          returnKeyType="search"
        />
      </View>

      <ScrollView
        style={{ flex: 1 }}
        contentContainerStyle={{ paddingTop: 4, paddingHorizontal: 24, paddingBottom: 20, gap: 16 }}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="on-drag"
        showsVerticalScrollIndicator={false}>
        {cities.isPending ? (
          <View style={styles.state}>
            <Spinner size={28} color={C.primary} track={C.borderSoft} width={3} />
          </View>
        ) : cities.isError && !cities.data ? (
          <View style={styles.state}>
            <Txt v="body" color={C.muted} align="center">
              {errorMessage(cities.error)}
            </Txt>
            <Button label="Try again" variant="outlineGreen" height={44} fill={false} onPress={() => cities.refetch()} />
          </View>
        ) : (
          <>
            {!query && (cities.data?.popular.length ?? 0) > 0 ? (
              <View style={{ gap: 8 }}>
                <Overline>Popular</Overline>
                <View style={{ marginHorizontal: -24 }}>
                  <ChipRow contentStyle={{ paddingHorizontal: 24 }}>
                    {cities.data!.popular.map((c) => (
                      <ChoiceChip key={c.id} label={c.name} selected={selectedId === c.id} height={42} onPress={() => setSelectedId(c.id)} />
                    ))}
                  </ChipRow>
                </View>
              </View>
            ) : null}

            {groups.map((g) => (
              <Animated.View key={g.name} entering={FadeInDown.duration(300)} style={{ gap: 8 }}>
                <Overline>{g.name}</Overline>
                <View style={styles.list} accessibilityLabel={g.name}>
                  {g.cities.map((c, i) => {
                    const on = selectedId === c.id;
                    return (
                      <Pressable
                        key={c.id}
                        onPress={() => setSelectedId(c.id)}
                        accessibilityRole="radio"
                        accessibilityState={{ checked: on }}
                        style={({ pressed }) => [
                          styles.row,
                          i < g.cities.length - 1 ? styles.divider : null,
                          { backgroundColor: on ? C.mint : pressed ? C.surfaceAlt2 : C.white },
                        ]}>
                        <Txt v="input" weight={on ? 700 : 500} style={{ flex: 1 }}>
                          {c.name}
                        </Txt>
                        <RadioDot on={on} />
                      </Pressable>
                    );
                  })}
                </View>
              </Animated.View>
            ))}

            {groups.length === 0 ? (
              <View style={styles.empty}>
                <View style={styles.emptyTile} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
                  <Icon name="pin" size={28} color={C.primary} />
                </View>
                <Txt v="itemTitle" align="center">
                  No city called “{q.trim()}”
                </Txt>
                <Txt v="bodyS" lh={21} color={C.muted} align="center" style={{ maxWidth: 260 }}>
                  Check the spelling, or pick the nearest larger city. The weather will be close enough.
                </Txt>
              </View>
            ) : null}
          </>
        )}
      </ScrollView>

      <StickyFooter padding={{ top: 14, h: 24, bottom: 34 }} gap={10} shadow>
        {selected ? (
          <>
            <View style={styles.preview} accessibilityLiveRegion="polite">
              <View style={styles.sunTile}>
                <Icon name="sunSimple" size={18} color={C.accent} strokeWidth={2.2} />
              </View>
              <View style={{ flex: 1 }}>
                <Txt v="itemTitle" size={15} color={C.primaryPressed} numberOfLines={1}>
                  {selected.name}, {selected.province}
                </Txt>
                <Txt v="caption" color={C.primaryPressed} numberOfLines={1}>
                  {weatherLine}
                </Txt>
              </View>
            </View>
            <Button label={`Use ${selected.name}`} weight={600} onPress={confirm} />
          </>
        ) : (
          <Button label="Select a city to continue" weight={600} disabled />
        )}
        <LinkButton label="Use my location instead" onPress={() => goBack('/setup/location')} />
      </StickyFooter>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  state: { paddingVertical: 40, alignItems: 'center', gap: 14 },
  list: { borderRadius: 18, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, overflow: 'hidden' },
  row: { minHeight: 52, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', gap: 12 },
  divider: { borderBottomWidth: 1, borderBottomColor: C.divider },
  empty: { alignItems: 'center', gap: 10, paddingVertical: 32, paddingHorizontal: 12 },
  emptyTile: { width: 64, height: 64, borderRadius: 22, backgroundColor: C.mint, alignItems: 'center', justifyContent: 'center' },
  preview: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10, paddingHorizontal: 12, borderRadius: 16, backgroundColor: C.mint },
  sunTile: { width: 36, height: 36, borderRadius: 12, backgroundColor: C.primary, alignItems: 'center', justifyContent: 'center' },
});
