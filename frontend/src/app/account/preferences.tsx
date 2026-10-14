import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { View } from 'react-native';

import { errorMessage, fieldErrors } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { useMe } from '@/api/queries';
import type { Kitchen, MealType, Me } from '@/api/types';
import { MeState, useApplyMe, visible } from '@/components/AccountKit';
import { EditScaffold } from '@/components/EditScaffold';
import { GapHeader, GapOptions, MealRows } from '@/components/Kitchen';
import { Txt } from '@/components/ui/Txt';
import { C } from '@/theme/tokens';

type Gap = Kitchen['repeat_gap_days'];

const gapHint = (g: number) =>
  g <= 3 ? 'Favourites come back quickly.' : g >= 10 ? 'Maximum variety; needs 15+ dishes to work well.' : 'A good balance for most families.';

export default function Preferences() {
  const me = useMe();
  if (!me.data) return <MeState error={me.error} onRetry={() => void me.refetch()} />;
  return <PreferencesForm me={me.data} />;
}

function PreferencesForm({ me }: { me: Me }) {
  const { applyKitchen } = useApplyMe();
  const [gapDraft, setGapDraft] = useState<Gap | null>(null);
  const [mealsDraft, setMealsDraft] = useState<MealType[] | null>(null);
  const [submitted, setSubmitted] = useState(false);
  const [general, setGeneral] = useState<string | null>(null);
  const [savedMessage, setSavedMessage] = useState<string | null>(null);

  const gap = gapDraft ?? me.kitchen.repeat_gap_days;
  const meals = mealsDraft ?? me.kitchen.meals;
  const mealsChanged = [...meals].sort().join() !== [...me.kitchen.meals].sort().join();
  const dirty = gap !== me.kitchen.repeat_gap_days || mealsChanged;
  const mealsErr = visible(meals.length === 0 ? 'Pick at least one meal.' : null, submitted, mealsChanged, false);

  const save = useMutation({ mutationFn: () => endpoints.updateKitchen({ repeat_gap_days: gap, meals }) });

  const reset = () => {
    setGapDraft(null);
    setMealsDraft(null);
    setSubmitted(false);
    setGeneral(null);
  };

  const onSave = async () => {
    setGeneral(null);
    if (meals.length === 0) {
      setSubmitted(true);
      return false;
    }
    try {
      const kitchen = await save.mutateAsync();
      applyKitchen(kitchen);
      setSavedMessage(`No repeats within ${kitchen.repeat_gap_days} days. Applies from tomorrow’s picks.`);
      reset();
      return true;
    } catch (e) {
      const f = fieldErrors(e);
      setGeneral(f.meals ?? f.repeat_gap_days ?? errorMessage(e));
      return false;
    }
  };

  return (
    <EditScaffold
      title="Meal preferences"
      sub="How variety and meals are planned"
      dirty={dirty}
      saving={save.isPending}
      onSave={onSave}
      onDiscard={reset}
      savedMessage={savedMessage}>
      <View style={{ gap: 10 }}>
        <GapHeader value={gap} size={14} />
        <GapOptions value={gap} onChange={setGapDraft} long />
        <Txt v="helper" color={C.muted}>
          {gapHint(gap)}
        </Txt>
      </View>

      <View style={{ gap: 10 }}>
        <Txt v="section">Meals that need ideas</Txt>
        <MealRows value={meals} onChange={setMealsDraft} invalid={!!mealsErr} compact />
        {mealsErr ? (
          <Txt v="helper" color={C.danger} accessibilityRole="alert">
            {mealsErr}
          </Txt>
        ) : null}
      </View>

      {general ? (
        <Txt v="helper" color={C.danger} accessibilityRole="alert">
          {general}
        </Txt>
      ) : null}
    </EditScaffold>
  );
}
