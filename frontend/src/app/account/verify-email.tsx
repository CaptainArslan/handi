import { useMutation } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { StyleSheet, TextInput, View } from 'react-native';
import Animated, { useAnimatedStyle, useSharedValue, withRepeat, withSequence, withTiming } from 'react-native-reanimated';

import { body, errorMessage, status } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { useMe } from '@/api/queries';
import { useApplyMe } from '@/components/AccountKit';
import { goBack, IconTile, TextLink } from '@/components/AuthKit';
import { FormScroll, Heading } from '@/components/Layout';
import { BackButton, Button } from '@/components/ui/Button';
import { flash } from '@/components/ui/Toasts';
import { DISPLAY, Txt } from '@/components/ui/Txt';
import { C } from '@/theme/tokens';

type Msg = { text: string; tone: 'muted' | 'bad' | 'ok' };
const DEFAULT_MSG: Msg = { text: 'Code expires in 15 minutes.', tone: 'muted' };

/** Confirm a new sign-in email (after 7.2) — same look as 2.3 Verify code. */
export default function VerifyEmail() {
  const params = useLocalSearchParams<{ email?: string }>();
  const me = useMe();
  const email = params.email || me.data?.email || 'your new email';
  const { applyMe } = useApplyMe();
  const [code, setCode] = useState('');
  const [focused, setFocused] = useState(false);
  const [wrong, setWrong] = useState(false);
  const [locked, setLocked] = useState(false);
  const [msg, setMsg] = useState<Msg>(DEFAULT_MSG);
  const input = useRef<TextInput>(null);

  const verify = useMutation({
    mutationFn: (value: string) => endpoints.verifyEmail(value),
    onSuccess: (user) => {
      applyMe(user);
      flash({ title: 'Email updated', tone: 'success' });
      router.navigate('/settings');
    },
    onError: (error) => {
      const left = body(error)?.attempts_left;
      setCode('');
      if (status(error) === 429 || (status(error) === 422 && left !== undefined && left <= 0)) {
        setLocked(true);
        setWrong(true);
        setMsg({ text: 'Too many attempts. Request a new code to try again.', tone: 'bad' });
        return;
      }
      if (status(error) === 422 && body(error)?.errors?.code) {
        setWrong(true);
        setMsg({
          text: left !== undefined ? `That code doesn’t match. ${left} ${left === 1 ? 'attempt' : 'attempts'} left.` : 'That code doesn’t match.',
          tone: 'bad',
        });
        return;
      }
      setMsg({ text: errorMessage(error), tone: 'bad' });
    },
  });

  const full = code.length === 6;
  const digits = code.split('');
  const activeIndex = Math.min(code.length, 5);

  const onCode = (t: string) => {
    setCode(t.replace(/\D/g, '').slice(0, 6));
    if (wrong && !locked) setWrong(false);
    if (!locked && msg !== DEFAULT_MSG) setMsg(DEFAULT_MSG);
  };

  const submit = () => {
    if (!full || locked || verify.isPending) return;
    verify.mutate(code);
  };

  const msgColor = msg.tone === 'bad' ? C.danger : msg.tone === 'ok' ? C.primary : C.muted;

  return (
    <FormScroll gap={26}>
      <View style={{ alignSelf: 'flex-start' }}>
        <BackButton onPress={() => goBack('/account/profile')} label="Back to profile" />
      </View>
      <IconTile icon="mail" bg={C.amberSoft} color={C.accentDark} />
      <Heading
        title="Check your email"
        gap={8}
        sub={
          <Txt v="bodyL" color={C.muted}>
            We sent a 6-digit code to{' '}
            <Txt v="bodyL" weight={700} color={C.ink}>
              {email}
            </Txt>
          </Txt>
        }
      />

      <View style={{ gap: 10 }}>
        <Txt v="label" color={C.ink2}>
          6-digit code
        </Txt>
        <View>
          <View style={styles.boxes} pointerEvents="none" accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
            {[0, 1, 2, 3, 4, 5].map((i) => {
              const active = focused && i === activeIndex && !locked;
              const borderColor = wrong || locked ? C.danger : active ? C.primary : digits[i] ? C.primaryOk : C.border;
              return (
                <View key={i} style={{ flex: 1 }}>
                  {active ? <View style={styles.ring} /> : null}
                  <View style={[styles.box, { borderColor, backgroundColor: locked ? C.disabledInput : C.white }]}>
                    {digits[i] ? <Txt style={styles.digit}>{digits[i]}</Txt> : active ? <Caret /> : null}
                  </View>
                </View>
              );
            })}
          </View>
          <TextInput
            ref={input}
            value={code}
            onChangeText={onCode}
            onFocus={() => setFocused(true)}
            onBlur={() => setFocused(false)}
            editable={!locked}
            keyboardType="number-pad"
            inputMode="numeric"
            textContentType="oneTimeCode"
            autoComplete="one-time-code"
            maxLength={6}
            autoFocus
            caretHidden
            returnKeyType="done"
            onSubmitEditing={submit}
            accessibilityLabel="6-digit code"
            style={styles.hidden}
          />
        </View>
        <Txt v="helper" color={msgColor} accessibilityRole="alert" accessibilityLiveRegion="polite">
          {msg.text}
        </Txt>
      </View>

      <Button label="Verify" weight={600} disabled={!full || locked} loading={verify.isPending} onPress={submit} />

      <View style={{ minHeight: 44, justifyContent: 'center', alignItems: 'center' }}>
        <Txt v="bodyS" color={C.muted} align="center">
          Didn’t get it?{' '}
          <TextLink onPress={() => goBack('/account/profile')}>Change your email again in Profile.</TextLink>
        </Txt>
      </View>
    </FormScroll>
  );
}

function Caret() {
  const o = useSharedValue(1);
  useEffect(() => {
    o.value = withRepeat(withSequence(withTiming(1, { duration: 500 }), withTiming(0, { duration: 0 }), withTiming(0, { duration: 500 }), withTiming(1, { duration: 0 })), -1, false);
  }, [o]);
  const style = useAnimatedStyle(() => ({ opacity: o.value }));
  return <Animated.View style={[{ width: 2, height: 26, backgroundColor: C.primary }, style]} />;
}

const styles = StyleSheet.create({
  boxes: { flexDirection: 'row', gap: 8 },
  box: { height: 60, borderRadius: 14, borderWidth: 1.5, alignItems: 'center', justifyContent: 'center' },
  ring: { position: 'absolute', left: -4, right: -4, top: -4, bottom: -4, borderRadius: 18, backgroundColor: C.mintRing },
  digit: { fontFamily: DISPLAY, fontSize: 26, color: C.ink },
  hidden: { position: 'absolute', left: 0, top: 0, right: 0, bottom: 0, opacity: 0.011, color: 'transparent', fontSize: 16 },
});
