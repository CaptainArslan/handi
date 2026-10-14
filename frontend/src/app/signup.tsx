import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View, type TextInput } from 'react-native';

import { errorMessage, fieldErrors, status } from '@/api/client';
import { endpoints } from '@/api/endpoints';

import { goBack, locationInput, TextLink, WaitingButton } from '@/components/AuthKit';
import { SetupHeader, useBottom, useTop } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { CheckLine } from '@/components/ui/Controls';
import { Field, Input } from '@/components/ui/Field';
import { NetToast } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { deviceName } from '@/lib/notifications';

import { confirmError, emailError, nameError, newPasswordError, strength, STRENGTH_LABEL } from '@/lib/validate';

import { useNet } from '@/store/net';
import { useOnboarding } from '@/store/onboarding';
import { useUi } from '@/store/ui';
import { C, tokens } from '@/theme/tokens';
import { resetTo, startSession } from '@/lib/session';

type Key = 'name' | 'email' | 'pw' | 'pw2';
const SERVER_FIELD: Record<string, Key | 'terms'> = {
  name: 'name',
  email: 'email',
  password: 'pw',
  password_confirmation: 'pw2',
  terms_accepted: 'terms',
};
const TAKEN = 'This email already has an account.';

export default function Signup() {
  const top = useTop(56);
  const footerBottom = useBottom(36);
  const online = useNet((s) => s.online);
  const draft = useOnboarding();

  const [v, setV] = useState({ name: '', email: '', pw: '', pw2: '' });
  const [terms, setTerms] = useState(false);
  const [touched, setTouched] = useState<Partial<Record<Key, boolean>>>({});
  const [submitted, setSubmitted] = useState(false);
  const [revealed, setRevealed] = useState(false);
  const [server, setServer] = useState<Partial<Record<Key | 'terms', string>>>({});
  const [general, setGeneral] = useState<string | null>(null);

  const emailRef = useRef<TextInput>(null);
  const pwRef = useRef<TextInput>(null);
  const pw2Ref = useRef<TextInput>(null);

  const errs: Record<Key, string | null> = {
    name: nameError(v.name),
    email: emailError(v.email),
    pw: newPasswordError(v.pw),
    pw2: confirmError(v.pw, v.pw2),
  };
  const shownErr = (k: Key): string | null => ((touched[k] || submitted) && errs[k]) || server[k] || null;
  const state = (k: Key) => (shownErr(k) ? 'bad' : touched[k] && !errs[k] ? 'ok' : 'default');

  const change = (k: Key) => (t: string) => {
    setV((s) => ({ ...s, [k]: t }));
    if (server[k]) setServer((s) => ({ ...s, [k]: undefined }));
  };
  const blur = (k: Key) => () => setTouched((s) => ({ ...s, [k]: true }));

  const register = useMutation({
    mutationFn: () =>
      endpoints.register({
        name: v.name.trim(),
        email: v.email.trim().toLowerCase(),
        password: v.pw,
        password_confirmation: v.pw2,
        terms_accepted: true,
        device_name: deviceName(),
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'Asia/Karachi',
        kitchen: { family_size: draft.familySize, budget: draft.budget ?? 'normal', repeat_gap_days: draft.repeatGap, meals: draft.meals },
        location: locationInput(draft.location),
        dish_ids: draft.dishIds,
        custom_dishes: draft.customDishes.map((d) => ({ name: d.name, category: d.category })),
      }),
    onSuccess: async (session) => {
      await startSession(session.token, session.user);
      useOnboarding.getState().reset();
      resetTo('/allow-reminder');
    },
    onError: (error) => {
      if (status(error) === 422) {
        const fe = fieldErrors(error);
        const mapped: Partial<Record<Key | 'terms', string>> = {};
        const other: string[] = [];
        for (const [field, msg] of Object.entries(fe)) {
          const k = SERVER_FIELD[field];
          if (k) mapped[k] = msg;
          else other.push(msg);
        }
        setServer(mapped);
        setGeneral(other[0] ?? null);
        return;
      }
      setGeneral(errorMessage(error));
    },
  });

  const submit = () => {
    setSubmitted(true);
    setGeneral(null);
    if (Object.values(errs).some(Boolean) || !terms || !online || register.isPending) return;
    register.mutate();
  };

  const openLegal = (tab: 'terms' | 'privacy') => {
    useUi.getState().set({ legalFrom: 'signup' });
    router.push({ pathname: '/legal', params: { tab } });
  };

  const score = strength(v.pw);
  const pwShown = shownErr('pw');
  const emailShown = shownErr('email');
  const taken = emailShown === TAKEN;
  const termsErr = (submitted && !terms) || !!server.terms;

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: C.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <View style={{ paddingTop: top, paddingHorizontal: 24, gap: 18 }}>
        <SetupHeader step={6} onBack={() => goBack('/setup/preview')} />
        <View style={{ gap: 6 }}>
          <Txt v="h1Compact" accessibilityRole="header">
            Save your kitchen
          </Txt>
          <Txt v="body" color={C.muted}>
            Your dishes and history stay safe on any phone.
          </Txt>
        </View>
      </View>

      <ScrollView
        style={{ flex: 1 }}
        contentContainerStyle={{ paddingTop: 18, paddingHorizontal: 24, paddingBottom: 8, gap: 14 }}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}>
        <Field label="Your name" error={shownErr('name')}>
          <Input
            height={52}
            value={v.name}
            onChangeText={change('name')}
            onBlur={blur('name')}
            state={state('name')}
            placeholder="e.g. Hina Arslan"
            autoComplete="name"
            textContentType="name"
            autoCapitalize="words"
            returnKeyType="next"
            submitBehavior="submit"
            onSubmitEditing={() => emailRef.current?.focus()}
            accessibilityLabel="Your name"
          />
        </Field>

        <Field label="Email">
          <Input
            ref={emailRef}
            height={52}
            value={v.email}
            onChangeText={change('email')}
            onBlur={blur('email')}
            state={state('email')}
            placeholder="name@example.com"
            keyboardType="email-address"
            autoComplete="email"
            textContentType="emailAddress"
            autoCapitalize="none"
            autoCorrect={false}
            returnKeyType="next"
            submitBehavior="submit"
            onSubmitEditing={() => pwRef.current?.focus()}
            accessibilityLabel="Email"
          />
          {emailShown ? (
            <Txt v="helper" color={C.danger} accessibilityRole="alert">
              {emailShown}
              {taken ? (
                <>
                  {' '}
                  <TextLink weight={700} onPress={() => router.push('/login')}>
                    Log in instead
                  </TextLink>
                </>
              ) : null}
            </Txt>
          ) : null}
        </Field>

        <Field label="Password" error={pwShown} helper={v.pw ? `Strength: ${STRENGTH_LABEL[score]}` : '8+ characters with a letter and a number.'}>
          <Input
            ref={pwRef}
            height={52}
            value={v.pw}
            onChangeText={change('pw')}
            onBlur={blur('pw')}
            state={state('pw')}
            placeholder="At least 8 characters"
            secureToggle
            revealed={revealed}
            onToggleReveal={() => setRevealed((r) => !r)}
            autoComplete="new-password"
            textContentType="newPassword"
            autoCapitalize="none"
            autoCorrect={false}
            returnKeyType="next"
            submitBehavior="submit"
            onSubmitEditing={() => pw2Ref.current?.focus()}
            accessibilityLabel="Password"
          />
          <View style={styles.bars} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
            {[0, 1, 2, 3].map((i) => (
              <View
                key={i}
                style={[styles.bar, { backgroundColor: v.pw && i < Math.max(1, score) ? tokens.strength[Math.max(0, score - 1)] : C.borderSoft }]}
              />
            ))}
          </View>
        </Field>

        <Field label="Confirm password" error={shownErr('pw2')}>
          <Input
            ref={pw2Ref}
            height={52}
            value={v.pw2}
            onChangeText={change('pw2')}
            onBlur={blur('pw2')}
            state={state('pw2')}
            placeholder="Type it again"
            secureTextEntry={!revealed}
            autoComplete="new-password"
            textContentType="newPassword"
            autoCapitalize="none"
            autoCorrect={false}
            returnKeyType="done"
            onSubmitEditing={submit}
            accessibilityLabel="Confirm password"
          />
        </Field>

        <View>
          <CheckLine
            checked={terms}
            onChange={(c) => {
              setTerms(c);
              if (server.terms) setServer((s) => ({ ...s, terms: undefined }));
            }}>
            <Txt v="bodyS" lh={20.3} color={C.ink2}>
              I agree to the <TextLink onPress={() => openLegal('terms')} weight={600}>Terms</TextLink> and{' '}
              <TextLink onPress={() => openLegal('privacy')} weight={600}>
                Privacy Policy
              </TextLink>
            </Txt>
          </CheckLine>
          {termsErr ? (
            <Txt v="helper" color={C.danger} accessibilityRole="alert" style={{ marginTop: 6 }}>
              {server.terms ?? 'Please accept the terms to continue.'}
            </Txt>
          ) : null}
        </View>

        {general ? (
          <Txt v="helper" color={C.danger} accessibilityRole="alert">
            {general}
          </Txt>
        ) : null}
      </ScrollView>

      <View style={[styles.footer, { paddingBottom: footerBottom }]}>
        {online ? (
          <Button label="Create account" weight={600} loading={register.isPending} onPress={submit} />
        ) : (
          <WaitingButton label="Waiting for internet…" />
        )}
        <Txt v="bodyS" color={C.muted} align="center">
          Have an account? <TextLink onPress={() => router.push('/login')}>Log in</TextLink>
        </Txt>
      </View>

      <NetToast bottom={168} />
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  bars: { flexDirection: 'row', gap: 6, marginTop: 2 },
  bar: { flex: 1, height: 5, borderRadius: 99 },
  footer: { paddingTop: 12, paddingHorizontal: 24, gap: 12, borderTopWidth: 1, borderTopColor: C.borderSoft, backgroundColor: C.bg },
});
