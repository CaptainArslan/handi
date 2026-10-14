import { router } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import type { Budget } from '@/api/types';
import { goBack } from '@/components/AuthKit';
import { BudgetCards, ErrorLine, FamilyStepper } from '@/components/Kitchen';
import { FormScroll, Heading, SetupHeader, Spacer } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { Txt } from '@/components/ui/Txt';
import { familyError } from '@/lib/validate';
import { useOnboarding } from '@/store/onboarding';
import { C } from '@/theme/tokens';

export default function SetupFamily() {
  const draft = useOnboarding();
  const [size, setSize] = useState(String(draft.familySize));
  const [budget, setBudget] = useState<Budget | null>(draft.budget);
  const [submitted, setSubmitted] = useState(false);

  const sizeErr = familyError(size);
  const budgetErr = !budget;

  const submit = () => {
    if (sizeErr || !budget) {
      setSubmitted(true);
      return;
    }
    draft.set({ familySize: Number(size), budget });
    router.push('/setup/dishes');
  };

  return (
    <FormScroll gap={28}>
      <SetupHeader step={1} onBack={() => goBack('/welcome')} />
      <Heading title="Tell us about your family" sub="This helps us balance effort and cost." />

      <View style={{ gap: 10 }}>
        <Txt v="label" color={C.ink2}>
          How many people eat at home?
        </Txt>
        <FamilyStepper value={size} onChange={setSize} invalid={!!sizeErr} />
        <Txt v="helper" color={sizeErr ? C.danger : C.muted} accessibilityLiveRegion="polite">
          {sizeErr ?? 'Between 1 and 20 people.'}
        </Txt>
      </View>

      <View style={{ gap: 10 }}>
        <Txt v="label" color={C.ink2}>
          Daily food budget
        </Txt>
        <BudgetCards value={budget} onChange={setBudget} invalid={submitted && budgetErr} />
        {submitted && budgetErr ? <ErrorLine>Choose a budget to continue.</ErrorLine> : null}
      </View>

      <Spacer />
      <Button label="Continue" weight={600} onPress={submit} />
    </FormScroll>
  );
}
