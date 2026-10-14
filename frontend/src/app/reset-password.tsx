import { useMutation } from '@tanstack/react-query';
import { Redirect } from 'expo-router';
import { useRef, useState } from 'react';
import { StyleSheet, View, type TextInput } from 'react-native';

import { errorMessage, fieldErrors, status } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { goBack, InlineAlert } from '@/components/AuthKit';
import { FormScroll, Heading } from '@/components/Layout';
import { BackButton, Button } from '@/components/ui/Button';
import { RuleRow } from '@/components/ui/Controls';
import { Field, Input } from '@/components/ui/Field';
import { flash } from '@/components/ui/Toasts';
import { confirmError, pwRules } from '@/lib/validate';
import { useUi } from '@/store/ui';
import { C } from '@/theme/tokens';
import { resetTo } from '@/lib/session';

/** Opened without a code request (e.g. a page reload on web) → start again at Forgot password. */
export default function ResetPasswordGuard() {
  const ready = useUi((s) => !!s.resetToken);
  if (!ready) return <Redirect href="/forgot-password" />;
  return <ResetPassword />;
}

function ResetPassword() {
  const resetToken = useUi((s) => s.resetToken);
  const [pw, setPw] = useState('');
  const [pw2, setPw2] = useState('');
  const [touched2, setTouched2] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [revealed, setRevealed] = useState(false);
  const [serverErr, setServerErr] = useState<string | null>(null);
  const pw2Ref = useRef<TextInput>(null);

  const rules = pwRules(pw);
  const RULES: { ok: boolean; label: string }[] = [
    { ok: rules.length, label: 'At least 8 characters' },
    { ok: rules.letter, label: 'Includes a letter' },
    { ok: rules.number, label: 'Includes a number' },
    { ok: rules.trimmed, label: 'No spaces at the start or end' },
  ];
  const rulesOk = RULES.every((r) => r.ok);
  const err2 = confirmError(pw, pw2, 'Confirm your new password.');
  const show2 = (touched2 || submitted) && err2;

  const reset = useMutation({
    mutationFn: () => endpoints.resetPassword({ reset_token: resetToken ?? '', password: pw, password_confirmation: pw2 }),
    onSuccess: () => {
      flash({ title: 'Password updated', sub: 'Log in with your new password.' });
      resetTo('/login');
      // Forget the reset state after leaving (clearing it first would bounce to Forgot password).
      setTimeout(() => useUi.getState().set({ resetToken: null, resetEmail: null, resetMasked: null }), 600);
    },
    onError: (error) => {
      if (status(error) === 422) {
        const fe = fieldErrors(error);
        setServerErr(fe.reset_token ?? fe.password ?? fe.password_confirmation ?? errorMessage(error));
      } else {
        setServerErr(errorMessage(error));
      }
    },
  });

  const submit = () => {
    setSubmitted(true);
    setServerErr(null);
    if (!rulesOk || err2 || reset.isPending) return;
    reset.mutate();
  };

  return (
    <FormScroll gap={24}>
      <View style={{ alignSelf: 'flex-start' }}>
        <BackButton onPress={() => goBack('/verify-code')} />
      </View>
      <Heading title="Set a new password" gap={8} sub="You’ll be logged out on other phones." />

      <View style={{ gap: 16 }}>
        {serverErr ? <InlineAlert>{serverErr}</InlineAlert> : null}
        <Field label="New password">
          <Input
            value={pw}
            onChangeText={(t) => {
              setPw(t);
              setServerErr(null);
            }}
            placeholder="New password"
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
            accessibilityLabel="New password"
            accessibilityHint="Password rules are listed below"
          />
        </Field>

        <View style={styles.rules} accessibilityLabel="Password rules">
          {RULES.map((r) => (
            <RuleRow key={r.label} ok={r.ok} failed={submitted && !r.ok}>
              {r.label}
            </RuleRow>
          ))}
        </View>

        <Field label="Confirm new password" error={show2 || null}>
          <Input
            ref={pw2Ref}
            value={pw2}
            onChangeText={setPw2}
            onBlur={() => setTouched2(true)}
            state={show2 ? 'bad' : 'default'}
            placeholder="Type it again"
            secureTextEntry={!revealed}
            autoComplete="new-password"
            textContentType="newPassword"
            autoCapitalize="none"
            autoCorrect={false}
            returnKeyType="done"
            onSubmitEditing={submit}
            accessibilityLabel="Confirm new password"
          />
        </Field>

        <Button label="Update password" weight={600} loading={reset.isPending} onPress={submit} />
      </View>
    </FormScroll>
  );
}

const styles = StyleSheet.create({
  rules: { paddingVertical: 14, paddingHorizontal: 16, borderRadius: 16, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, gap: 10 },
});
