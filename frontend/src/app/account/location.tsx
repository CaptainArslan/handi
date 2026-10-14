import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { errorMessage } from '@/api/client';
import { endpoints, type LocationInput } from '@/api/endpoints';
import { qk, useCities, useMe } from '@/api/queries';
import type { City, Me, Today, Weather } from '@/api/types';
import { MeState, useApplyMe } from '@/components/AccountKit';
import { EditScaffold } from '@/components/EditScaffold';
import { Field, Input } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import { Spinner } from '@/components/ui/Spinner';
import { DISPLAY, Txt } from '@/components/ui/Txt';
import { locate } from '@/lib/location';
import { C } from '@/theme/tokens';

type Draft = { mode: 'city'; city: City } | { mode: 'gps'; lat: number; lng: number; weather: Weather | null };

export default function EditLocation() {
  const me = useMe();
  if (!me.data) return <MeState error={me.error} onRetry={() => void me.refetch()} />;
  return <LocationForm me={me.data} />;
}

function useWeather(params: { city_id?: number } | null) {
  return useQuery({
    queryKey: ['weather', params ?? {}],
    queryFn: () => endpoints.weather(params ?? {}),
    enabled: !!params?.city_id,
    staleTime: 30 * 60 * 1000,
  });
}

function LocationForm({ me }: { me: Me }) {
  const queryClient = useQueryClient();
  const { applyKitchen } = useApplyMe();
  const loc = me.kitchen.location;
  const [draft, setDraft] = useState<Draft | null>(null);
  const [q, setQ] = useState('');
  const [locating, setLocating] = useState(false);
  const [locError, setLocError] = useState<string | null>(null);
  const [general, setGeneral] = useState<string | null>(null);
  const [savedMessage, setSavedMessage] = useState<string | null>(null);

  const cities = useCities();
  const all: City[] = (cities.data?.provinces ?? []).flatMap((p) => p.cities);
  const popular = cities.data?.popular ?? [];

  const dirty = !!draft && (draft.mode === 'gps' || loc.mode !== 'city' || draft.city.id !== loc.city_id);

  // Card: what the weather will come from (draft first, then the saved location).
  const cityForWeather = draft ? (draft.mode === 'city' ? draft.city.id : undefined) : (loc.city_id ?? undefined);
  const weatherQ = useWeather(cityForWeather ? { city_id: cityForWeather } : null);
  const todayWeather = queryClient.getQueryData<Today>(qk.today)?.weather ?? null;
  const weather: Weather | null = draft?.mode === 'gps' ? draft.weather : cityForWeather ? (weatherQ.data ?? null) : todayWeather;

  let title: string;
  if (draft?.mode === 'city') title = `${draft.city.name}, ${draft.city.province}`;
  else if (draft?.mode === 'gps') title = draft.weather?.city_label ? join(draft.weather.city_label, draft.weather.province) : 'Your location';
  else title = loc.city ? join(loc.city, loc.province) : (loc.label ?? 'Not set yet');

  const wxLine = weather?.available
    ? `Today ${weather.summary}`
    : cityForWeather && weatherQ.isLoading
      ? 'Today …'
      : draft || loc.mode
        ? 'Today’s weather isn’t available right now'
        : 'Pick a city or use your location';

  const selectedId = draft ? (draft.mode === 'city' ? draft.city.id : null) : loc.mode === 'city' ? loc.city_id : null;
  const query = q.trim().toLowerCase();
  let list = query ? all.filter((c) => c.name.toLowerCase().startsWith(query)) : popular.length ? popular : all;
  if (!query && selectedId && !list.some((c) => c.id === selectedId)) {
    const sel = all.find((c) => c.id === selectedId);
    if (sel) list = [sel, ...list];
  }

  const findMe = async () => {
    setLocError(null);
    setLocating(true);
    const pos = await locate();
    if (pos === 'denied' || pos === 'error') {
      setLocating(false);
      setLocError(pos === 'denied' ? 'Location permission is off. No problem, choose your city instead.' : 'Couldn’t find you right now. Choose your city instead.');
      return;
    }
    let w: Weather | null = null;
    try {
      w = await endpoints.weather({ lat: pos.lat, lng: pos.lng });
    } catch {
      w = null;
    }
    setDraft({ mode: 'gps', lat: pos.lat, lng: pos.lng, weather: w });
    setLocating(false);
  };

  const save = useMutation({
    mutationFn: (body: LocationInput) => endpoints.setLocation(body),
  });

  const reset = () => {
    setDraft(null);
    setLocError(null);
    setGeneral(null);
  };

  const onSave = async () => {
    if (!draft) return false;
    setGeneral(null);
    const body: LocationInput = draft.mode === 'city' ? { mode: 'city', city_id: draft.city.id } : { mode: 'gps', lat: draft.lat, lng: draft.lng };
    try {
      const res = await save.mutateAsync(body);
      applyKitchen(res.kitchen);
      const city = res.kitchen.location.city ?? res.weather?.city_label ?? (draft.mode === 'city' ? draft.city.name : 'your location');
      if (res.kitchen.location.city_id && res.weather) queryClient.setQueryData(['weather', { city_id: res.kitchen.location.city_id }], res.weather);
      setSavedMessage(`Weather now comes from ${city}. Picks update from tomorrow.`);
      setDraft(null);
      setQ('');
      return true;
    } catch (e) {
      setGeneral(errorMessage(e));
      return false;
    }
  };

  return (
    <EditScaffold
      title="Location"
      sub="Used only for the weather"
      dirty={dirty}
      saving={save.isPending}
      onSave={onSave}
      onDiscard={reset}
      savedMessage={savedMessage}>
      <View style={styles.hero} accessibilityRole="summary" accessibilityLiveRegion="polite">
        <View style={styles.sun} />
        <Txt v="overline" color={C.onGreenMuted}>
          Weather for
        </Txt>
        <Txt style={{ fontFamily: DISPLAY, fontSize: 26, color: C.white }} numberOfLines={2}>
          {title}
        </Txt>
        <Txt v="bodyS" color={C.onGreenMuted}>
          {wxLine}
        </Txt>
      </View>

      <View style={{ gap: 8 }}>
        <Pressable
          onPress={findMe}
          disabled={locating}
          accessibilityRole="button"
          accessibilityState={{ busy: locating, disabled: locating }}
          style={({ pressed }) => [styles.locate, { backgroundColor: pressed ? C.surfaceAlt2 : C.white }]}>
          <View style={styles.locTile}>
            {locating ? <Spinner size={16} width={2.5} color={C.primary} track={C.primarySoft} /> : <Icon name="crosshair" size={20} color={C.primary} />}
          </View>
          <View style={{ flex: 1, gap: 1 }}>
            <Txt v="itemTitle" size={15}>
              {locating ? 'Finding you…' : 'Use my current location'}
            </Txt>
            <Txt v="caption" color={C.muted}>
              Rounded to about 1 km, never shared
            </Txt>
          </View>
        </Pressable>
        {locError ? (
          <Txt v="helper" color={C.danger} accessibilityRole="alert">
            {locError}
          </Txt>
        ) : null}
      </View>

      <Field label="Or choose a city" style={{ gap: 8 }}>
        <Input value={q} onChangeText={(t) => setQ(t.slice(0, 30))} placeholder="Search city" maxLength={30} autoCorrect={false} returnKeyType="search" accessibilityLabel="Search city" />
        <View style={styles.list} accessibilityRole="list" accessibilityLabel="Cities">
          {cities.isLoading ? (
            <View style={{ padding: 14, alignItems: 'center' }}>
              <Spinner size={18} color={C.primary} track={C.dashed} />
            </View>
          ) : !cities.data ? (
            <Txt v="bodyS" color={C.muted} style={{ padding: 14 }}>
              {errorMessage(cities.error)}
            </Txt>
          ) : list.length === 0 ? (
            <Txt v="bodyS" color={C.muted} style={{ padding: 14 }}>
              No city matches “{q.trim()}”.
            </Txt>
          ) : (
            list.map((c, i) => {
              const on = c.id === selectedId;
              return (
                <Pressable
                  key={c.id}
                  onPress={() => {
                    setDraft({ mode: 'city', city: c });
                    setLocError(null);
                  }}
                  accessibilityRole="radio"
                  accessibilityState={{ selected: on, checked: on }}
                  accessibilityLabel={`${c.name}, ${c.province}`}
                  style={({ pressed }) => [
                    styles.city,
                    i > 0 ? { borderTopWidth: 1, borderTopColor: C.divider } : null,
                    { backgroundColor: on ? C.mint : pressed ? C.surfaceAlt2 : C.white },
                  ]}>
                  <Txt v="body" weight={on ? 700 : 500} style={{ flex: 1 }} numberOfLines={1}>
                    {c.name}
                  </Txt>
                  <Txt v="caption" color={C.muted}>
                    {c.province_short || c.province}
                  </Txt>
                  {on ? <Icon name="check" size={18} color={C.primary} strokeWidth={3} /> : null}
                </Pressable>
              );
            })
          )}
        </View>
      </Field>

      {general ? (
        <Txt v="helper" color={C.danger} accessibilityRole="alert">
          {general}
        </Txt>
      ) : null}
    </EditScaffold>
  );
}

const join = (city: string, province?: string | null) => (province ? `${city}, ${province}` : city);

const styles = StyleSheet.create({
  hero: { overflow: 'hidden', gap: 6, padding: 16, borderRadius: 22, backgroundColor: C.primary },
  sun: { position: 'absolute', right: -30, top: -30, width: 120, height: 120, borderRadius: 60, backgroundColor: C.accent },
  locate: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: 18, borderWidth: 1.5, borderColor: C.border },
  locTile: { width: 40, height: 40, borderRadius: 12, backgroundColor: C.mint, alignItems: 'center', justifyContent: 'center' },
  list: { borderRadius: 16, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, overflow: 'hidden' },
  city: { flexDirection: 'row', alignItems: 'center', gap: 10, height: 50, paddingHorizontal: 14 },
});
