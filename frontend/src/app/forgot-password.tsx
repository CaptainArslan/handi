import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { errorMessage, fieldErrors, status } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { goBack, IconTile, InlineAlert } from '@/components/AuthKit';
import { FormScroll, Heading, Spacer } from '@/components/Layout';
import { BackButton, Button } from '@/components/ui/Button';
import { Field, Input } from '@/components/ui/Field';
import { Txt } from '@/components/ui/Txt';
import { emailError } from '@/lib/validate';
import { useUi } from '@/store/ui';
import { C } from '@/theme/tokens';

export default function ForgotPassword() {
  const [email, setEmail] = useState(useUi.getState().resetEmail ?? '');
  const [touched, setTouched] = useState(false);
  const [serverErr, setServerErr] = useState<string | null>(null);
  const [general, setGeneral] = useState<string | null>(null);

  const err = emailError(email, 'Enter the email you signed up with.');
  const shown = (touched && err) || serverErr;

  const send = useMutation({
    mutationFn: (value: string) => endpoints.forgot(value),
    onSuccess: (res, value) => {
      useUi.getState().set({ resetEmail: value, resetMasked: res.masked_email, resetToken: null });
      router.push('/verify-code');
    },
    onError: (error) => {
      if (status(error) === 422) setServerErr(fieldErrors(error).email ?? errorMessage(error));
      else setGeneral(errorMessage(error));
    },
  });

  const submit = () => {
    setTouched(true);
    setGeneral(null);
    if (err || send.isPending) return;
    send.mutate(email.trim().toLowerCase());
  };

  return (
    <FormScroll gap={26}>
      <View style={{ alignSelf: 'flex-start' }}>
        <BackButton label="Back to log in" onPress={() => goBack('/login')} />
      </View>
      <IconTile icon="lockKey" bg={C.mint} color={C.primary} />
      <Heading title="Forgot your password?" gap={8} sub="Enter your email and we’ll send a 6-digit code. It’s valid for 15 minutes." />

      <View style={{ gap: 16 }}>
        {general ? <InlineAlert>{general}</InlineAlert> : null}
        <Field label="Email" error={shown || null}>
          <Input
            icon="mail"
            value={email}
            onChangeText={(t) => {
              setEmail(t);
              setServerErr(null);
            }}
            onBlur={() => setTouched(true)}
            state={shown ? 'bad' : 'default'}
            placeholder="name@example.com"
            keyboardType="email-address"
            autoComplete="email"
            textContentType="emailAddress"
            autoCapitalize="none"
            autoCorrect={false}
            returnKeyType="send"
            onSubmitEditing={submit}
            accessibilityLabel="Email"
          />
        </Field>
        <Button label="Send code" weight={600} loading={send.isPending} onPress={submit} />
      </View>

      <Spacer />
      <Txt v="bodyS" lh={21} color={C.muted} align="center">
        For your privacy we don’t confirm whether an email is registered. Check spam if nothing arrives.
      </Txt>
    </FormScroll>
  );
}
