import { useMutation } from '@tanstack/react-query';

import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { errorMessage, fieldErrors } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { goBack } from '@/components/AuthKit';
import { StickyFooter, useTop } from '@/components/Layout';
import { BackButton, Button } from '@/components/ui/Button';
import { CheckLine } from '@/components/ui/Controls';
import { Field, Input } from '@/components/ui/Field';
import { flash } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';


import { C } from '@/theme/tokens';
import { endSession, resetTo } from '@/lib/session';

const LOSSES = ['Your dish list, custom dishes and photos', 'All cooking history', 'Your profile, location and reminders on every phone'];

export default function DeleteAccount() {
  const top = useTop(52);
  const [pw, setPw] = useState('');
  const [cf, setCf] = useState('');
  const [ack, setAck] = useState(false);
  const [tPw, setTPw] = useState(false);
  const [tCf, setTCf] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [server, setServer] = useState<{ pw?: string; cf?: string }>({});
  const [general, setGeneral] = useState<string | null>(null);

  const pwError = !pw ? 'Enter your password.' : pw.length < 8 ? 'That password is too short to be yours.' : (server.pw ?? null);
  const cfError = !cf ? 'Type DELETE in capital letters.' : cf.trim() !== 'DELETE' ? 'It must say DELETE exactly.' : (server.cf ?? null);
  const pwErr = (tPw || submitted) && pwError ? pwError : null;
  const cfErr = (tCf || submitted) && cfError ? cfError : null;
  const ackErr = submitted && !ack;

  const del = useMutation({
    mutationFn: () => endpoints.deleteAccount({ password: pw, confirmation: 'DELETE' }),
    onSuccess: async () => {
      await endSession();
      flash({ title: 'Your account was deleted', sub: 'All your data has been removed.', tone: 'info' });
      resetTo('/welcome');
    },
    onError: (e) => {
      const f = fieldErrors(e);
      if (f.password || f.confirmation) {
        setServer({ pw: f.password, cf: f.confirmation });
        setSubmitted(true);
      } else setGeneral(errorMessage(e));
    },
  });

  const submit = () => {
    setSubmitted(true);
    setGeneral(null);
    if (pwError || cfError || !ack || del.isPending) return;
    del.mutate();
  };

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <View style={{ paddingTop: top, paddingHorizontal: 20, paddingBottom: 8, flexDirection: 'row' }}>
        <BackButton onPress={() => goBack('/settings')} label="Back to settings" />
      </View>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={{ paddingTop: 8, paddingHorizontal: 20, paddingBottom: 20, gap: 20 }}>
          <View style={{ gap: 8 }}>
            <Txt v="h1Compact" accessibilityRole="header">
              Delete your account
            </Txt>
            <Txt v="body" color={C.muted}>
              This permanently removes everything below. It can’t be undone.
            </Txt>
          </View>

          <View style={styles.losses}>
            {LOSSES.map((l) => (
              <View key={l} style={{ flexDirection: 'row', gap: 10 }}>
                <View style={styles.bullet} />
                <Txt v="bodyS" color={C.dangerText2} style={{ flex: 1 }}>
                  {l}
                </Txt>
              </View>
            ))}
          </View>

          <Field label="Your password" error={pwErr}>
            <Input
              value={pw}
              onChangeText={(t) => {
                setPw(t);
                setServer((s) => ({ ...s, pw: undefined }));
              }}
              onBlur={() => setTPw(true)}
              secureTextEntry
              autoComplete="current-password"
              textContentType="password"
              autoCapitalize="none"
              placeholder="Enter password"
              danger
              state={pwErr ? 'bad' : 'default'}
              accessibilityLabel="Your password"
            />
          </Field>

          <Field label="Type DELETE to confirm" error={cfErr}>
            <Input
              value={cf}
              onChangeText={(t) => {
                setCf(t);
                setServer((s) => ({ ...s, cf: undefined }));
              }}
              onBlur={() => setTCf(true)}
              autoCapitalize="characters"
              autoComplete="off"
              autoCorrect={false}
              placeholder="DELETE"
              danger
              state={cfErr ? 'bad' : 'default'}
              accessibilityLabel="Type DELETE to confirm"
            />
          </Field>

          <View style={{ gap: 8 }}>
            <CheckLine checked={ack} onChange={setAck} danger size={22}>
              <Txt v="bodyS" lh={20.3} color={C.ink2}>
                I understand my data will be deleted and can’t be recovered.
              </Txt>
            </CheckLine>
            {ackErr ? (
              <Txt v="helper" color={C.danger} accessibilityRole="alert">
                Please tick the box to continue.
              </Txt>
            ) : null}
          </View>

          {general ? (
            <Txt v="helper" color={C.danger} accessibilityRole="alert">
              {general}
            </Txt>
          ) : null}
        </ScrollView>
        <StickyFooter padding={{ top: 12, h: 20, bottom: 32 }} gap={8}>
          <Button label="Delete my account" variant="danger" loading={del.isPending} onPress={submit} />
          <Pressable
            onPress={() => goBack('/settings')}
            accessibilityRole="button"
            style={({ pressed }) => ({ height: 48, alignItems: 'center', justifyContent: 'center', opacity: pressed ? 0.7 : 1 })}>
            <Txt v="buttonBold" size={15}>
              Keep my account
            </Txt>
          </Pressable>
        </StickyFooter>
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  losses: { paddingVertical: 14, paddingHorizontal: 16, borderRadius: 18, backgroundColor: C.dangerSoft, gap: 10 },
  bullet: { width: 6, height: 6, borderRadius: 3, backgroundColor: C.danger, marginTop: 7 },
});
