import { router } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import type { MealType } from '@/api/types';
import { goBack } from '@/components/AuthKit';
import { GapHeader, GapOptions, MealRows } from '@/components/Kitchen';
import { FormScroll, Heading, SetupHeader, Spacer } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { Txt } from '@/components/ui/Txt';
import { useOnboarding } from '@/store/onboarding';
import { C } from '@/theme/tokens';

export default function SetupPreferences() {
  const draft = useOnboarding();
  const [gap, setGap] = useState<3 | 5 | 7 | 10>(draft.repeatGap);
  const [meals, setMeals] = useState<MealType[]>(draft.meals);
  const [submitted, setSubmitted] = useState(false);
  const invalid = meals.length === 0;

  const submit = () => {
    if (invalid) {
      setSubmitted(true);
      return;
    }
    draft.set({ repeatGap: gap, meals });
    router.push('/setup/location');
  };

  return (
    <FormScroll gap={26}>
      <SetupHeader step={3} onBack={() => goBack('/setup/dishes')} />
      <Heading title="A few preferences" sub="So the picks feel like your kitchen." subSize={15} gap={8} />

      <View style={{ gap: 12 }}>
        <GapHeader value={gap} />
        <GapOptions value={gap} onChange={setGap} />
      </View>

      <View style={{ gap: 12 }}>
        <Txt v="section" size={15}>
          Which meals need ideas?
        </Txt>
        <MealRows value={meals} onChange={setMeals} invalid={submitted && invalid} />
        {submitted && invalid ? (
          <Txt v="helper" color={C.danger} accessibilityRole="alert">
            Pick at least one meal.
          </Txt>
        ) : null}
      </View>

      <Spacer />
      <Button label="Continue" weight={600} onPress={submit} />
    </FormScroll>
  );
}
