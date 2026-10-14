import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { View } from 'react-native';

import { errorMessage, fieldErrors } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { useMe } from '@/api/queries';
import type { Budget, Me } from '@/api/types';
import { InfoBox, MeState, useApplyMe, visible } from '@/components/AccountKit';
import { EditScaffold } from '@/components/EditScaffold';
import { BudgetCards, FamilyStepper } from '@/components/Kitchen';
import { Txt } from '@/components/ui/Txt';
import { familyError } from '@/lib/validate';
import { C } from '@/theme/tokens';

const IMPACT: Record<Budget, string> = {
  low: 'Low budget: high-cost dishes like mutton and fish show up less often.',
  normal: 'Normal budget: a balanced mix of everyday and treat dishes.',
  high: 'High budget: no cost limits; treat dishes can show any day.',
};

export default function Family() {
  const me = useMe();
  if (!me.data) return <MeState error={me.error} onRetry={() => void me.refetch()} />;
  return <FamilyForm me={me.data} />;
}

function FamilyForm({ me }: { me: Me }) {
  const { applyKitchen } = useApplyMe();
  const origSize = String(me.kitchen.family_size);
  const [sizeDraft, setSizeDraft] = useState<string | null>(null);
  const [budgetDraft, setBudgetDraft] = useState<Budget | null>(null);
  const [submitted, setSubmitted] = useState(false);
  const [server, setServer] = useState<string | null>(null);
  const [general, setGeneral] = useState<string | null>(null);
  const [savedMessage, setSavedMessage] = useState<string | null>(null);

  const size = sizeDraft ?? origSize;
  const budget = budgetDraft ?? me.kitchen.budget;
  const dirty = size !== origSize || budget !== me.kitchen.budget;
  const error = familyError(size, 'Maximum is 20 people.') ?? server;
  const sizeErr = visible(error, submitted, size !== origSize, false);

  const save = useMutation({ mutationFn: () => endpoints.updateKitchen({ family_size: Number(size), budget }) });

  const reset = () => {
    setSizeDraft(null);
    setBudgetDraft(null);
    setSubmitted(false);
    setServer(null);
    setGeneral(null);
  };

  const onSave = async () => {
    setGeneral(null);
    if (error) {
      setSubmitted(true);
      return false;
    }
    try {
      const kitchen = await save.mutateAsync();
      applyKitchen(kitchen);
      setSavedMessage(`Family of ${kitchen.family_size} · ${kitchen.budget} budget. Tomorrow’s picks will use it.`);
      reset();
      return true;
    } catch (e) {
      const f = fieldErrors(e);
      if (f.family_size) {
        setServer(f.family_size);
        setSubmitted(true);
      } else setGeneral(f.budget ?? errorMessage(e));
      return false;
    }
  };

  return (
    <EditScaffold
      title="Family & budget"
      sub="Shapes effort and cost of your picks"
      dirty={dirty}
      saving={save.isPending}
      onSave={onSave}
      onDiscard={reset}
      savedMessage={savedMessage}>
      <View style={{ gap: 10 }}>
        <Txt v="label" color={C.ink2}>
          People eating at home
        </Txt>
        <FamilyStepper
          value={size}
          onChange={(v) => {
            setSizeDraft(v);
            setServer(null);
          }}
          invalid={!!sizeErr}
          fontSize={26}
        />
        <Txt v="helper" color={sizeErr ? C.danger : C.muted} accessibilityRole={sizeErr ? 'alert' : undefined} accessibilityLiveRegion="polite">
          {sizeErr ?? 'Between 1 and 20 people.'}
        </Txt>
      </View>

      <View style={{ gap: 10 }}>
        <Txt v="label" color={C.ink2}>
          Daily food budget
        </Txt>
        <BudgetCards value={budget} onChange={setBudgetDraft} compact />
      </View>

      <InfoBox>{IMPACT[budget]}</InfoBox>

      {general ? (
        <Txt v="helper" color={C.danger} accessibilityRole="alert">
          {general}
        </Txt>
      ) : null}
    </EditScaffold>
  );
}
