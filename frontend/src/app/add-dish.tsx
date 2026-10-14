import { useQueryClient } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Animated, { ZoomIn } from 'react-native-reanimated';

import { fieldErrors, errorMessage } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { qk, useDish, useDishes } from '@/api/queries';
import type { Cost, Effort, KitchenDishDetail, UsualMeal, WeatherTag } from '@/api/types';
import { goBack } from '@/components/AuthKit';
import { CATEGORY_ART, DishImage } from '@/components/DishImage';
import { WeatherChips } from '@/components/Kitchen';
import { AppHeader, StickyFooter } from '@/components/Layout';
import { LoadError, Loading } from '@/components/LoadState';
import { Button } from '@/components/ui/Button';
import { ChoiceChip, ProgressBar, RadioDot, Segmented } from '@/components/ui/Controls';
import { Field, Input } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import { Sheet } from '@/components/ui/Sheet';
import { flash } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { pickPhoto, type PickedPhoto } from '@/lib/photo';
import { cleanDishName, dishNameError } from '@/lib/validate';
import { C, tokens } from '@/theme/tokens';
import { useAppWidth } from '@/lib/layout';

const CATEGORIES = [
  { key: 'vegetables', label: 'Vegetables' },
  { key: 'lentils', label: 'Lentils' },
  { key: 'chicken', label: 'Chicken' },
  { key: 'beef', label: 'Beef' },
  { key: 'mutton', label: 'Mutton' },
  { key: 'fish', label: 'Fish' },
  { key: 'rice', label: 'Rice' },
  { key: 'breakfast', label: 'Breakfast' },
  { key: 'snacks', label: 'Snacks' },
];
const MEALS: { value: UsualMeal; label: string }[] = [
  { value: 'lunch', label: 'Lunch' },
  { value: 'dinner', label: 'Dinner' },
  { value: 'both', label: 'Both' },
  { value: 'breakfast', label: 'Breakfast' },
];
const EFFORTS: { value: Effort; label: string }[] = [
  { value: 'quick', label: 'Quick' },
  { value: 'medium', label: 'Medium' },
  { value: 'heavy', label: 'Heavy' },
];
const COSTS: { value: Cost; label: string }[] = [
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'high', label: 'High' },
];

interface Form {
  name: string;
  category: string | null;
  meal: UsualMeal;
  weather: WeatherTag[];
  effort: Effort;
  cost: Cost;
}

const blank = (name = ''): Form => ({ name: name.slice(0, 40), category: null, meal: 'both', weather: [], effort: 'medium', cost: 'low' });

const fromDish = (d: KitchenDishDetail): Form => ({
  name: d.name,
  category: d.category?.key ?? null,
  meal: d.usual_meal,
  weather: d.weather,
  effort: d.effort,
  cost: d.cost,
});

export default function AddDishScreen() {
  const params = useLocalSearchParams<{ id?: string; name?: string }>();
  const editId = Number(params.id) || 0;
  const dish = useDish(editId);

  if (editId && !dish.data) {
    return (
      <View style={{ flex: 1, backgroundColor: C.bg }}>
        <AppHeader title="Edit custom dish" titleV="editTitle" onBack={() => goBack('/dishes')} backIcon="close" backLabel="Cancel" padding={{ h: 20, bottom: 12 }} />
        {dish.isError ? <LoadError message={errorMessage(dish.error)} onRetry={() => dish.refetch()} /> : <Loading />}
      </View>
    );
  }
  return <DishForm key={editId || 'new'} editing={editId && dish.data ? dish.data : null} initialName={params.name ?? ''} />;
}

function DishForm({ editing, initialName }: { editing: KitchenDishDetail | null; initialName: string }) {
  const queryClient = useQueryClient();
  const width = useAppWidth();
  const catW = Math.floor((width - 40 - 16) / 3);
  const allDishes = useDishes();

  const [form, setForm] = useState<Form>(() => (editing ? fromDish(editing) : blank(initialName)));
  const [touched, setTouched] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [serverNameError, setServerNameError] = useState<string | null>(null);
  const [photo, setPhoto] = useState<PickedPhoto | null>(null);
  const [progress, setProgress] = useState(0);
  const [saving, setSaving] = useState(false);
  const [choiceOpen, setChoiceOpen] = useState(false);
  const [select, setSelect] = useState<'effort' | 'cost' | null>(null);
  const [selectOpen, setSelectOpen] = useState(false);
  const [savedName, setSavedName] = useState<string | null>(null);
  const [savedImg, setSavedImg] = useState<{ uri: string | null; key: string | null; bg: string | null }>({ uri: null, key: null, bg: null });
  const [successOpen, setSuccessOpen] = useState(false);
  const ticker = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(
    () => () => {
      if (ticker.current) clearInterval(ticker.current);
    },
    [],
  );

  const set = (patch: Partial<Form>) => setForm((f) => ({ ...f, ...patch }));

  const existing = (allDishes.data?.data ?? []).filter((d) => !d.removed && d.id !== editing?.id).map((d) => d.name);
  // Once saved, the new dish is in the list itself — don't flag it as a duplicate behind the success sheet.
  const nameErr = successOpen ? null : (serverNameError ?? dishNameError(form.name, existing));
  const catErr = !form.category;
  const wxErr = form.weather.length === 0;
  const showName = (touched || submitted) && !!nameErr;

  // ------------------------------------------------------------ photo

  const startCompress = (p: PickedPhoto) => {
    setPhoto(p);
    setProgress(8);
    if (ticker.current) clearInterval(ticker.current);
    ticker.current = setInterval(() => {
      setProgress((v) => {
        const next = Math.min(100, v + 23);
        if (next >= 100 && ticker.current) clearInterval(ticker.current);
        return next;
      });
    }, 280);
  };

  const choose = async (source: 'camera' | 'library') => {
    setChoiceOpen(false);
    const res = await pickPhoto(source);
    if (res.ok) startCompress(res.photo);
    else if (res.reason === 'denied') flash({ title: 'Camera access is off', sub: 'Allow it in your phone settings, or pick from the gallery.', tone: 'info' });
    else if (res.reason === 'too-big') flash({ title: 'That photo is too big', sub: 'Pick one up to 10 MB.', tone: 'info' });
  };

  const removePhoto = () => {
    if (ticker.current) clearInterval(ticker.current);
    setPhoto(null);
    setProgress(0);
  };

  // ------------------------------------------------------------ save

  const invalidate = (id?: number) => {
    queryClient.invalidateQueries({ queryKey: ['dishes'] });
    queryClient.invalidateQueries({ queryKey: qk.custom });
    queryClient.invalidateQueries({ queryKey: qk.today });
    queryClient.invalidateQueries({ queryKey: qk.me });
    if (id) queryClient.invalidateQueries({ queryKey: qk.dish(id) });
  };

  const save = async () => {
    setSubmitted(true);
    if (nameErr || catErr || wxErr || !form.category) return;
    const name = cleanDishName(form.name);
    const input = {
      name,
      category: form.category,
      usual_meal: form.meal,
      weather: form.weather,
      effort: form.effort,
      cost: form.cost,
      photo: photo ? { uri: photo.uri, name: photo.name, type: photo.type } : null,
    };
    setSaving(true);
    try {
      if (editing) {
        const next = await endpoints.updateCustomDish(editing.id, input);
        queryClient.setQueryData(qk.dish(editing.id), next);
        invalidate(editing.id);
        flash({ title: `${name} updated`, sub: 'Changes apply from tomorrow’s picks.' });
        goBack({ pathname: '/dishes', params: { tab: 'custom' } });
        return;
      }
      const created = await endpoints.createCustomDish(input);
      invalidate(created.id);
      setSavedName(name);
      setSavedImg({ uri: photo?.uri ?? null, key: CATEGORY_ART[form.category] ?? 'karhi', bg: null });
      setSuccessOpen(true);
    } catch (e) {
      const errs = fieldErrors(e);
      if (errs.name) {
        setServerNameError(errs.name);
        setTouched(true);
      } else {
        flash({ title: 'Couldn’t save your dish', sub: errs.category ?? errs.weather ?? errs.photo ?? errorMessage(e), tone: 'info' });
      }
    } finally {
      setSaving(false);
    }
  };

  const addAnother = () => {
    removePhoto();
    setForm(blank());
    setTouched(false);
    setSubmitted(false);
    setServerNameError(null);
    setSuccessOpen(false);
  };

  const viewCustom = () => {
    setSuccessOpen(false);
    router.replace({ pathname: '/dishes', params: { tab: 'custom' } });
  };

  const openSelect = (which: 'effort' | 'cost') => {
    setSelect(which);
    setSelectOpen(true);
  };

  const selectOptions = select === 'cost' ? COSTS : EFFORTS;
  const selectValue = select === 'cost' ? form.cost : form.effort;
  const existingPhoto = editing?.has_photo && !photo ? editing : null;

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <AppHeader
        title={editing ? 'Edit custom dish' : 'Add custom dish'}
        sub={editing ? 'Changes apply from tomorrow’s picks' : 'It joins your daily suggestions'}
        titleV="editTitle"
        onBack={() => goBack({ pathname: '/dishes', params: { tab: 'custom' } })}
        backIcon="close"
        backLabel="Cancel"
        padding={{ h: 20, bottom: 12 }}
      />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
          contentContainerStyle={{ paddingTop: 6, paddingHorizontal: 20, paddingBottom: 20, gap: 20 }}>
          {photo ? (
            <View style={styles.photoBox}>
              <DishImage source={photo.uri} bg={tokens.dishBg.karhi} scale={1.1} alt="Selected dish photo" style={StyleSheet.absoluteFill} />
              <View style={styles.photoStatus}>
                <View style={{ flex: 1, gap: 6 }}>
                  <Txt v="toastTitle" size={13} numberOfLines={1}>
                    {progress >= 100 ? 'Photo ready · we’ll optimise it after saving' : `Compressing photo… ${progress}%`}
                  </Txt>
                  <ProgressBar pct={progress} height={5} color={progress >= 100 ? C.success : C.accent} />
                </View>
                <SmallButton label="Remove" onPress={removePhoto} />
              </View>
            </View>
          ) : existingPhoto ? (
            <View style={styles.photoBox}>
              <DishImage imageUrl={existingPhoto.image_url} imageKey={existingPhoto.image_key} bg={existingPhoto.image_bg} scale={1.1} alt={`${existingPhoto.name} photo`} style={StyleSheet.absoluteFill} />
              <View style={styles.photoStatus}>
                <Txt v="toastTitle" size={13} style={{ flex: 1 }}>
                  Current photo
                </Txt>
                <SmallButton label="Change" onPress={() => setChoiceOpen(true)} />
              </View>
            </View>
          ) : (
            <Pressable
              onPress={() => setChoiceOpen(true)}
              accessibilityRole="button"
              accessibilityLabel="Add a photo"
              style={({ pressed }) => [styles.drop, { borderColor: pressed ? C.primary : C.dashed }]}>
              <View style={styles.camTile}>
                <Icon name="camera" size={26} color={C.accentDark} />
              </View>
              <Txt v="itemTitle" size={15}>
                Add a photo
              </Txt>
              <Txt v="caption" color={C.muted}>
                Optional · camera or gallery · up to 10 MB
              </Txt>
            </Pressable>
          )}

          <Field
            label="Dish name"
            labelRight={
              <Txt v="caption" color={C.muted}>
                {`${form.name.length}/40`}
              </Txt>
            }
            error={showName ? nameErr : null}>
            <Input
              value={form.name}
              onChangeText={(t) => {
                set({ name: t.slice(0, 40) });
                setServerNameError(null);
              }}
              onBlur={() => setTouched(true)}
              maxLength={40}
              placeholder="e.g. Aunt Sara’s Haleem"
              accessibilityLabel="Dish name"
              autoCapitalize="words"
              returnKeyType="done"
              state={showName ? 'bad' : 'default'}
            />
          </Field>

          <View style={{ gap: 10 }}>
            <Txt v="label">Category</Txt>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }} accessibilityRole="radiogroup">
              {CATEGORIES.map((c) => (
                <View key={c.key} style={{ width: catW, flexDirection: 'row' }}>
                  <ChoiceChip
                    label={c.label}
                    selected={form.category === c.key}
                    onPress={() => set({ category: c.key })}
                    invalid={submitted && catErr}
                    height={46}
                    radius={12}
                    flex
                    accessibilityRole="radio"
                  />
                </View>
              ))}
            </View>
            {submitted && catErr ? (
              <Txt v="helper" color={C.danger} accessibilityRole="alert">
                Choose a category.
              </Txt>
            ) : null}
          </View>

          <View style={{ gap: 10 }}>
            <Txt v="label">Usually cooked for</Txt>
            <Segmented options={MEALS} value={form.meal} onChange={(meal) => set({ meal })} track={C.sand2} height={42} fontSize={13} />
          </View>

          <View style={{ gap: 10 }}>
            <Txt v="label">Good for weather</Txt>
            <WeatherChips value={form.weather} onChange={(weather) => set({ weather })} invalid={submitted && wxErr} />
            {submitted && wxErr ? (
              <Txt v="helper" color={C.danger} accessibilityRole="alert">
                Pick at least one, or “Any”.
              </Txt>
            ) : null}
          </View>

          <View style={{ flexDirection: 'row', gap: 12 }}>
            <SelectField label="Effort" value={EFFORTS.find((o) => o.value === form.effort)?.label ?? ''} onPress={() => openSelect('effort')} />
            <SelectField label="Cost" value={COSTS.find((o) => o.value === form.cost)?.label ?? ''} onPress={() => openSelect('cost')} />
          </View>
        </ScrollView>

        <StickyFooter padding={{ top: 12, h: 20, bottom: 32 }}>
          <Button label="Save dish" icon="check" iconStroke={2.6} loading={saving} onPress={save} />
        </StickyFooter>
      </KeyboardAvoidingView>

      {/* Camera or gallery */}
      <Sheet visible={choiceOpen} onClose={() => setChoiceOpen(false)} handle accessibilityLabel="Add a photo">
        <Txt v="sheetTitle" accessibilityRole="header">
          Add a photo
        </Txt>
        <Button label="Take a photo" icon="camera" height={54} onPress={() => choose('camera')} />
        <Button label="Choose from gallery" variant="outline" height={54} onPress={() => choose('library')} />
      </Sheet>

      {/* Effort / cost select */}
      <Sheet visible={selectOpen} onClose={() => setSelectOpen(false)} handle gap={8} accessibilityLabel={select === 'cost' ? 'Cost' : 'Effort'}>
        <Txt v="sheetTitle" accessibilityRole="header" style={{ marginBottom: 6 }}>
          {select === 'cost' ? 'Cost' : 'Effort'}
        </Txt>
        <View accessibilityRole="radiogroup">
          {selectOptions.map((o, i) => {
            const on = o.value === selectValue;
            return (
              <Pressable
                key={o.value}
                onPress={() => {
                  if (select === 'cost') set({ cost: o.value as Cost });
                  else set({ effort: o.value as Effort });
                  setSelectOpen(false);
                }}
                accessibilityRole="radio"
                accessibilityState={{ checked: on }}
                style={({ pressed }) => [styles.option, i > 0 ? { borderTopWidth: 1, borderTopColor: C.divider } : null, pressed ? { backgroundColor: C.surfaceAlt2 } : null]}>
                <Txt v="itemTitle" size={16} weight={on ? 700 : 600} style={{ flex: 1 }}>
                  {o.label}
                </Txt>
                <RadioDot on={on} />
              </Pressable>
            );
          })}
        </View>
      </Sheet>

      {/* Success */}
      <Sheet visible={successOpen} onClose={viewCustom} handle center accessibilityLabel={savedName ? `${savedName} added` : undefined}>
        <View style={{ width: 120, height: 120, marginTop: 6 }}>
          <DishImage source={savedImg.uri} imageKey={savedImg.key} bg={savedImg.bg} scale={1.1} radius={60} style={{ width: 120, height: 120 }} />
          <Animated.View entering={ZoomIn.delay(150).duration(450)} style={styles.okBadge}>
            <Icon name="check" size={20} color={C.white} strokeWidth={3} />
          </Animated.View>
        </View>
        <Txt v="sheetTitle" align="center" accessibilityRole="header">
          {`${savedName ?? ''} added`}
        </Txt>
        <Txt v="bodyS" lh={21} color={C.muted} align="center" style={{ maxWidth: 280 }}>
          It can show up in your suggestions from tomorrow.
        </Txt>
        <Button label="View my custom dishes" height={54} onPress={viewCustom} />
        <Button label="Add another" variant="outline" height={54} onPress={addAnother} />
      </Sheet>
    </View>
  );
}

function SmallButton({ label, onPress }: { label: string; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} accessibilityRole="button" style={({ pressed }) => [styles.small, { opacity: pressed ? 0.75 : 1 }]}>
      <Txt v="toastTitle" size={13} color={C.ink2}>
        {label}
      </Txt>
    </Pressable>
  );
}

function SelectField({ label, value, onPress }: { label: string; value: string; onPress: () => void }) {
  return (
    <View style={{ flex: 1, gap: 6 }}>
      <Txt v="label">{label}</Txt>
      <Pressable
        onPress={onPress}
        accessibilityRole="button"
        accessibilityLabel={`${label}: ${value}`}
        style={({ pressed }) => [styles.select, { borderColor: pressed ? C.primary : C.border }]}>
        <Txt v="input" style={{ flex: 1 }}>
          {value}
        </Txt>
        <View style={{ transform: [{ rotate: '90deg' }] }}>
          <Icon name="chevronRight" size={18} color={C.muted} strokeWidth={2.2} />
        </View>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  drop: { height: 168, borderRadius: 24, borderWidth: 2, borderStyle: 'dashed', backgroundColor: C.white, alignItems: 'center', justifyContent: 'center', gap: 10 },
  camTile: { width: 56, height: 56, borderRadius: 18, backgroundColor: C.amberSoft, alignItems: 'center', justifyContent: 'center' },
  photoBox: { height: 188, borderRadius: 24, overflow: 'hidden', backgroundColor: tokens.dishBg.karhi },
  photoStatus: {
    position: 'absolute',
    left: 12,
    right: 12,
    bottom: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 14,
    backgroundColor: C.white,
    shadowColor: '#000',
    shadowOpacity: 0.12,
    shadowRadius: 9,
    shadowOffset: { width: 0, height: 6 },
    elevation: 4,
  },
  small: { height: 36, paddingHorizontal: 10, borderRadius: 10, backgroundColor: C.surfaceAlt, alignItems: 'center', justifyContent: 'center' },
  select: { height: 54, borderRadius: 14, borderWidth: 1.5, backgroundColor: C.white, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', gap: 8 },
  option: { height: 56, flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 4 },
  okBadge: { position: 'absolute', right: -2, bottom: 2, width: 40, height: 40, borderRadius: 20, backgroundColor: C.primary, borderWidth: 3, borderColor: C.white, alignItems: 'center', justifyContent: 'center' },
});
