import { useMutation, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { errorMessage, fieldErrors } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { qk } from '@/api/queries';
import { visible } from '@/components/AccountKit';
import { EditScaffold } from '@/components/EditScaffold';
import { CheckLine, RuleRow } from '@/components/ui/Controls';
import { Field, Input } from '@/components/ui/Field';
import { Txt } from '@/components/ui/Txt';
import { C } from '@/theme/tokens';

type Key = 'cur' | 'nw' | 'conf';

export default function ChangePassword() {
  const queryClient = useQueryClient();
  const [cur, setCur] = useState('');
  const [nw, setNw] = useState('');
  const [conf, setConf] = useState('');
  const [show, setShow] = useState(false);
  const [others, setOthers] = useState(true);
  const [touched, setTouched] = useState<Partial<Record<Key, boolean>>>({});
  const [submitted, setSubmitted] = useState(false);
  const [server, setServer] = useState<Partial<Record<Key, string>>>({});
  const [general, setGeneral] = useState<string | null>(null);
  const [savedMessage, setSavedMessage] = useState<string | null>(null);

  const dirty = cur !== '' || nw !== '' || conf !== '';
  const rules = [
    { label: 'At least 8 characters', ok: nw.length >= 8 },
    { label: 'Includes a letter', ok: /[A-Za-z]/.test(nw) },
    { label: 'Includes a number', ok: /\d/.test(nw) },
    { label: 'Different from current password', ok: nw.length > 0 && nw !== cur },
  ];
  const errors: Record<Key, string | null> = {
    cur: !cur ? 'Enter your current password.' : (server.cur ?? null),
    nw: rules.every((r) => r.ok) ? (server.nw ?? null) : 'Meet all the rules below.',
    conf: !conf ? 'Confirm your new password.' : conf !== nw ? 'Passwords don’t match.' : (server.conf ?? null),
  };
  const show_ = (k: Key, value: string) => visible(errors[k], submitted, value !== '', !!touched[k]);
  const curErr = show_('cur', cur);
  const nwErr = show_('nw', nw);
  const confErr = show_('conf', conf);

  const save = useMutation({
    mutationFn: () => endpoints.changePassword({ current_password: cur, password: nw, password_confirmation: conf, sign_out_other_devices: others }),
  });

  const reset = () => {
    setCur('');
    setNw('');
    setConf('');
    setTouched({});
    setSubmitted(false);
    setServer({});
    setGeneral(null);
  };

  const onSave = async () => {
    setGeneral(null);
    if (errors.cur || errors.nw || errors.conf) {
      setSubmitted(true);
      return false;
    }
    try {
      await save.mutateAsync();
      setSavedMessage(`New password is active. ${others ? 'Other phones were signed out.' : 'Other phones stay signed in.'}`);
      reset();
      void queryClient.invalidateQueries({ queryKey: qk.me });
      return true;
    } catch (e) {
      const f = fieldErrors(e);
      if (Object.keys(f).length) {
        setServer({ cur: f.current_password, nw: f.password, conf: f.password_confirmation });
        setSubmitted(true);
      } else {
        setGeneral(errorMessage(e));
      }
      return false;
    }
  };

  return (
    <EditScaffold
      title="Change password"
      sub="You stay signed in on this phone"
      dirty={dirty}
      saving={save.isPending}
      onSave={onSave}
      onDiscard={reset}
      savedMessage={savedMessage}>
      <Field label="Current password" error={curErr}>
        <Input
          value={cur}
          onChangeText={(t) => {
            setCur(t);
            setServer((s) => ({ ...s, cur: undefined }));
          }}
          onBlur={() => setTouched((t) => ({ ...t, cur: true }))}
          secureTextEntry={!show}
          autoComplete="current-password"
          textContentType="password"
          autoCapitalize="none"
          placeholder="Current password"
          state={curErr ? 'bad' : 'default'}
          accessibilityLabel="Current password"
        />
        <Pressable onPress={() => router.push('/forgot-password')} accessibilityRole="link" hitSlop={4} style={{ alignSelf: 'flex-start', minHeight: 32, justifyContent: 'center' }}>
          <Txt v="helper" weight={700} color={C.primary} style={{ textDecorationLine: 'underline' }}>
            Forgot your current password?
          </Txt>
        </Pressable>
      </Field>

      <Field label="New password">
        <Input
          value={nw}
          onChangeText={(t) => {
            setNw(t);
            setServer((s) => ({ ...s, nw: undefined }));
          }}
          secureTextEntry={!show}
          autoComplete="new-password"
          textContentType="newPassword"
          autoCapitalize="none"
          placeholder="New password"
          state={nwErr ? 'bad' : 'default'}
          accessibilityLabel="New password"
          accessibilityHint="Must meet the password rules below"
        />
      </Field>

      <View style={styles.rules} accessibilityLabel="Password rules">
        {rules.map((r) => (
          <RuleRow key={r.label} ok={r.ok} failed={submitted && !r.ok}>
            {r.label}
          </RuleRow>
        ))}
      </View>

      <Field label="Confirm new password" error={confErr}>
        <Input
          value={conf}
          onChangeText={(t) => {
            setConf(t);
            setServer((s) => ({ ...s, conf: undefined }));
          }}
          onBlur={() => setTouched((t) => ({ ...t, conf: true }))}
          secureTextEntry={!show}
          autoComplete="new-password"
          textContentType="newPassword"
          autoCapitalize="none"
          placeholder="Type it again"
          state={confErr ? 'bad' : 'default'}
          accessibilityLabel="Confirm new password"
        />
      </Field>

      <View style={{ gap: 12 }}>
        <CheckLine checked={show} onChange={setShow} size={20}>
          <Txt v="bodyS" color={C.ink2}>
            Show passwords
          </Txt>
        </CheckLine>
        <CheckLine checked={others} onChange={setOthers} size={20}>
          <Txt v="bodyS" lh={19.6} color={C.ink2}>
            Sign out on my other phones
          </Txt>
        </CheckLine>
      </View>

      {general ? (
        <Txt v="helper" color={C.danger} accessibilityRole="alert">
          {general}
        </Txt>
      ) : null}
    </EditScaffold>
  );
}

const styles = StyleSheet.create({
  rules: { marginTop: -8, paddingVertical: 14, paddingHorizontal: 16, borderRadius: 16, backgroundColor: C.white, borderWidth: 1, borderColor: C.borderSoft, gap: 9 },
});
