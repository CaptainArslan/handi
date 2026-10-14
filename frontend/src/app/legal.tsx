import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';

import { useLegal } from '@/api/queries';
import type { LegalDoc } from '@/api/types';
import { InfoBox } from '@/components/AccountKit';
import { useBottom, useTop } from '@/components/Layout';
import { BackButton } from '@/components/ui/Button';
import { Segmented } from '@/components/ui/Controls';
import { Txt } from '@/components/ui/Txt';
import { SUPPORT_EMAIL } from '@/lib/config';
import { monthName, parseISO } from '@/lib/dates';
import { useUi } from '@/store/ui';
import { C } from '@/theme/tokens';

type Doc = 'terms' | 'privacy';

/** Bundled copy (7.8) for offline / first load. */
const FALLBACK: Record<Doc, LegalDoc> = {
  terms: {
    title: 'Terms of use',
    summary: 'Short version: Handi suggests dishes. You decide what to cook. Be kind to your data and we’ll be kind to yours.',
    sections: [
      { heading: '1. Using Handi', body: 'Handi suggests three home-cooked dishes each day based on your family, budget, history and weather. Suggestions are ideas, not dietary or medical advice.' },
      { heading: '2. Your account', body: 'One account per household. Keep your password private. You can log out of other phones from Settings at any time.' },
      { heading: '3. Your dishes and photos', body: 'Custom dishes and photos you add stay yours. You let us store and show them back to you inside the app, nothing more.' },
      { heading: '4. Fair use', body: 'Up to 5 shuffles a day keeps suggestions fresh for everyone. Don’t try to break, copy or resell the service.' },
      { heading: '5. Closing your account', body: 'You can delete your account in Settings. We remove your data within 30 days, except where the law asks us to keep it.' },
    ],
    updated_at: '2026-10-01',
    support_email: SUPPORT_EMAIL,
  },
  privacy: {
    title: 'Privacy policy',
    summary: 'We collect only what makes your picks better. We never sell your data or show ads.',
    sections: [
      { heading: '1. What we store', body: 'Your name, email, family size, budget, meal preferences, dish list, cooking history and reminder time.' },
      { heading: '2. Location', body: 'Used only to check today’s weather. It’s rounded to about 1 km, or you can pick a city instead. It’s never shared.' },
      { heading: '3. Notifications', body: 'One daily reminder at the time you choose, plus account messages. Turn them off in Settings at any time.' },
      { heading: '4. Offline data', body: 'Today’s picks and your library are saved on this phone so the app works without internet. Changes sync when you’re back online.' },
      { heading: '5. Your rights', body: 'See, download or delete your data at any time. Deleting your account removes it from our servers within 30 days.' },
    ],
    updated_at: '2026-10-01',
    support_email: SUPPORT_EMAIL,
  },
};

const updatedLabel = (iso: string) => {
  const d = parseISO(iso.slice(0, 10));
  return `Last updated ${d.getDate()} ${monthName(d.getMonth())} ${d.getFullYear()}`;
};

export default function Legal() {
  const params = useLocalSearchParams<{ tab?: string }>();
  const [tab, setTab] = useState<Doc>(params.tab === 'privacy' ? 'privacy' : 'terms');
  const from = useUi((s) => s.legalFrom);
  const top = useTop(56);
  const bottom = useBottom(32);
  const legal = useLegal(tab);
  const doc = legal.data ?? FALLBACK[tab];

  const back = () => {
    if (from === 'signup') {
      if (router.canGoBack()) router.back();
      else router.replace('/signup');
    } else if (router.canGoBack()) router.back();
    else router.replace('/settings');
  };

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <View style={[styles.header, { paddingTop: top }]}>
        <BackButton onPress={back} label={from === 'signup' ? 'Back to sign up' : 'Back to settings'} />
        <View style={{ flex: 1 }}>
          <Txt v="greeting" accessibilityRole="header">
            Terms & privacy
          </Txt>
          <Txt v="helper" color={C.muted}>
            {updatedLabel(doc.updated_at)}
          </Txt>
        </View>
      </View>

      <Segmented<Doc>
        options={[
          { value: 'terms', label: 'Terms of use' },
          { value: 'privacy', label: 'Privacy policy' },
        ]}
        value={tab}
        onChange={setTab}
        track={C.sand3}
        optionRadius={10}
        fontSize={15}
        style={{ marginHorizontal: 20 }}
      />

      <ScrollView contentContainerStyle={{ paddingTop: 18, paddingHorizontal: 20, paddingBottom: bottom, gap: 12 }}>
        <InfoBox icon="shieldCheck">
          <Txt v="bodyS" lh={21} color={C.primaryPressed}>
            {doc.summary}
          </Txt>
        </InfoBox>
        {doc.sections.map((s) => (
          <View key={s.heading} style={styles.section}>
            <Txt v="itemTitle" accessibilityRole="header">
              {s.heading}
            </Txt>
            <Txt v="bodyS" lh={21.7} color={C.ink2}>
              {s.body}
            </Txt>
          </View>
        ))}
        <Txt v="helper" color={C.muted} align="center" style={{ marginTop: 4 }}>
          Questions? Write to{' '}
          <Txt v="helper" weight={600} color={C.primary}>
            {doc.support_email || SUPPORT_EMAIL}
          </Txt>
        </Txt>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 14, paddingHorizontal: 20, paddingBottom: 14 },
  section: { padding: 16, borderRadius: 18, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, gap: 6 },
});
