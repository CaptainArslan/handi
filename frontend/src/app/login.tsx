import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useRef, useState } from 'react';
import { View, type TextInput } from 'react-native';
import Animated, { useAnimatedStyle, useSharedValue, withSequence, withTiming } from 'react-native-reanimated';

import { body, errorMessage, fieldErrors, status } from '@/api/client';
import { endpoints } from '@/api/endpoints';

import { goBack, HelpLink, InlineAlert, LinkButton, TextLink, useCountdown } from '@/components/AuthKit';
import { FormScroll, Heading, Spacer } from '@/components/Layout';
import { BackButton, Button } from '@/components/ui/Button';
import { Field, Input } from '@/components/ui/Field';
import { flash } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { deviceName } from '@/lib/notifications';

import { emailError } from '@/lib/validate';

import { C } from '@/theme/tokens';
import { resetTo, startSession } from '@/lib/session';

export default function Login() {
  const [email, setEmail] = useState('');
  const [pw, setPw] = useState('');
  const [touched, setTouched] = useState({ email: false, pw: false });
  const [submitted, setSubmitted] = useState(false);
  const [attemptsLeft, setAttemptsLeft] = useState<number | null>(null);
  const [lockedOnce, setLockedOnce] = useState(false);
  const [server, setServer] = useState<{ email?: string; password?: string; general?: string }>({});
  const lock = useCountdown();
  const pwRef = useRef<TextInput>(null);
  const shake = useSharedValue(0);

  const locked = lock.left > 0;
  // When the lockout ends, the failed-attempt state resets.
  const lockOver = lockedOnce && !locked;

  const emailErr = emailError(email, 'Enter your email.', 'That doesn’t look like an email.');
  const pwErr = pw ? null : 'Enter your password.';
  const showEmail = ((touched.email || submitted) && emailErr) || server.email || null;
  const showPw = ((touched.pw || submitted) && pwErr) || server.password || null;
  const wrong = attemptsLeft !== null && !lockOver && !locked;

  const doShake = () => {
    const t = (x: number) => withTiming(x, { duration: 40 });
    shake.value = withSequence(t(-1), t(2), t(-4), t(4), t(-4), t(4), t(-4), t(2), t(-1), t(0));
  };
  const shakeStyle = useAnimatedStyle(() => ({ transform: [{ translateX: shake.value }] }));

  const login = useMutation({
    mutationFn: () => endpoints.login({ email: email.trim().toLowerCase(), password: pw, device_name: deviceName() }),
    onSuccess: async (session) => {
      await startSession(session.token, session.user);
      flash({ title: `Welcome back, ${session.user.first_name}`, sub: 'Today’s 3 picks are ready.' });
      resetTo('/today');
    },
    onError: (error) => {
      const code = status(error);
      const b = body(error);
      if (code === 401) {
        const n = b?.attempts_left ?? null;
        if (n !== null && n <= 0) {
          setAttemptsLeft(null);
          setLockedOnce(true);
          lock.start(60);
        } else {
          setAttemptsLeft(n ?? 4);
          setLockedOnce(false);
        }
        doShake();
        return;
      }
      if (code === 429) {
        setAttemptsLeft(null);
        setLockedOnce(true);
        lock.start(b?.retry_after ?? 60);
        doShake();
        return;
      }
      if (code === 422) {
        const fe = fieldErrors(error);
        setServer({ email: fe.email, password: fe.password, general: fe.email || fe.password ? undefined : b?.message });
        return;
      }
      setServer({ general: errorMessage(error) });
    },
  });

  const submit = () => {
    if (locked || login.isPending) return;
    setSubmitted(true);
    setServer({});
    if (emailErr || pwErr) return;
    login.mutate();
  };

  const alert = locked ? (
    <InlineAlert tone="dark">{`Too many attempts. For your safety, try again in ${lock.left}s, or reset your password.`}</InlineAlert>
  ) : wrong ? (
    <InlineAlert>{`Email or password is incorrect. ${attemptsLeft} ${attemptsLeft === 1 ? 'try' : 'tries'} left.`}</InlineAlert>
  ) : server.general ? (
    <InlineAlert>{server.general}</InlineAlert>
  ) : null;

  return (
    <FormScroll gap={26}>
      <View style={{ alignSelf: 'flex-start' }}>
        <BackButton onPress={() => goBack('/welcome')} />
      </View>
      <Heading title="Welcome back" v="h1Large" gap={8} sub="Log in to see today’s picks." />

      {alert}

      <Animated.View style={[{ gap: 16 }, shakeStyle]}>
        <Field label="Email" error={showEmail}>
          <Input
            icon="mail"
            value={email}
            onChangeText={(t) => {
              setEmail(t);
              if (server.email) setServer((s) => ({ ...s, email: undefined }));
            }}
            onBlur={() => setTouched((s) => ({ ...s, email: true }))}
            state={showEmail ? 'bad' : 'default'}
            disabled={locked}
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
        </Field>
        <Field
          label="Password"
          labelRight={<LinkButton label="Forgot password?" minHeight={32} onPress={() => router.push('/forgot-password')} />}
          error={showPw}>
          <Input
            ref={pwRef}
            icon="lock"
            value={pw}
            onChangeText={(t) => {
              setPw(t);
              if (server.password) setServer((s) => ({ ...s, password: undefined }));
            }}
            onBlur={() => setTouched((s) => ({ ...s, pw: true }))}
            state={showPw || wrong ? 'bad' : 'default'}
            disabled={locked}
            placeholder="Your password"
            secureToggle
            autoComplete="current-password"
            textContentType="password"
            autoCapitalize="none"
            autoCorrect={false}
            returnKeyType="go"
            onSubmitEditing={submit}
            accessibilityLabel="Password"
          />
        </Field>
        <Button
          label={locked ? `Locked · ${lock.left}s` : 'Log in'}
          weight={600}
          disabled={locked}
          loading={login.isPending}
          onPress={submit}
          style={{ marginTop: 6 }}
        />
      </Animated.View>

      <Spacer />
      <Txt v="body" color={C.muted} align="center">
        New here? <TextLink weight={700} onPress={() => router.dismissTo('/welcome')}>Set up your kitchen</TextLink>
      </Txt>
      <HelpLink style={{ marginTop: -14 }} />
    </FormScroll>
  );
}
