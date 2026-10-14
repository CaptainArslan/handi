import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { errorMessage, fieldErrors } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { useMe } from '@/api/queries';
import type { Me } from '@/api/types';
import { LinkCard, MeState, useApplyMe, visible } from '@/components/AccountKit';
import { EditScaffold } from '@/components/EditScaffold';
import { Field, Input } from '@/components/ui/Field';
import { DISPLAY, Txt } from '@/components/ui/Txt';
import { ago } from '@/lib/dates';
import { emailError, initials, nameError } from '@/lib/validate';
import { C } from '@/theme/tokens';

export default function Profile() {
  const me = useMe();
  if (!me.data) return <MeState error={me.error} onRetry={() => void me.refetch()} />;
  return <ProfileForm me={me.data} />;
}

type Key = 'name' | 'email' | 'pw';

function ProfileForm({ me }: { me: Me }) {
  const { applyMe } = useApplyMe();
  const [nameDraft, setNameDraft] = useState<string | null>(null);
  const [emailDraft, setEmailDraft] = useState<string | null>(null);
  const [pw, setPw] = useState('');
  const [touched, setTouched] = useState<Partial<Record<Key, boolean>>>({});
  const [submitted, setSubmitted] = useState(false);
  const [server, setServer] = useState<Partial<Record<Key, string>>>({});
  const [general, setGeneral] = useState<string | null>(null);
  const [savedMessage, setSavedMessage] = useState<string | null>(null);
  const navTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(
    () => () => {
      if (navTimer.current) clearTimeout(navTimer.current);
    },
    [],
  );

  const name = nameDraft ?? me.name;
  const email = emailDraft ?? me.email;
  const cleanEmail = email.trim().toLowerCase();
  const emailChanged = cleanEmail !== me.email.toLowerCase();
  const dirty = name !== me.name || email !== me.email || pw !== '';

  const errors: Record<Key, string | null> = {
    name: nameError(name) ?? server.name ?? null,
    email: emailError(email) ?? server.email ?? null,
    pw: emailChanged && !emailError(email) ? (!pw ? 'Enter your password to change email.' : (server.pw ?? null)) : null,
  };
  const show = (k: Key, changed: boolean) => visible(errors[k], submitted, changed, !!touched[k]);
  const nameErr = show('name', name !== me.name);
  const emailErr = show('email', email !== me.email);
  const pwErr = show('pw', pw !== '');

  const save = useMutation({
    mutationFn: () =>
      endpoints.updateMe({
        name: name.trim(),
        ...(emailChanged ? { email: cleanEmail, current_password: pw } : {}),
      }),
  });

  const reset = () => {
    setNameDraft(null);
    setEmailDraft(null);
    setPw('');
    setTouched({});
    setSubmitted(false);
    setServer({});
    setGeneral(null);
  };

  const onSave = async () => {
    setGeneral(null);
    if (errors.name || errors.email || errors.pw) {
      setSubmitted(true);
      return false;
    }
    try {
      const res = await save.mutateAsync();
      applyMe(res.data);
      const pending = res.meta.email_change_pending;
      setSavedMessage(pending ? 'Check your new inbox for a confirmation code.' : 'Your profile is up to date.');
      reset();
      if (pending) {
        navTimer.current = setTimeout(() => router.push({ pathname: '/account/verify-email', params: { email: cleanEmail } }), 1400);
      }
      return true;
    } catch (e) {
      const f = fieldErrors(e);
      if (Object.keys(f).length) {
        setServer({ name: f.name, email: f.email, pw: f.current_password });
        setSubmitted(true);
      } else {
        setGeneral(errorMessage(e));
      }
      return false;
    }
  };

  const display = name.trim() || 'Your name';

  return (
    <EditScaffold
      title="Profile"
      sub="Your name and sign-in email"
      dirty={dirty}
      saving={save.isPending}
      onSave={onSave}
      onDiscard={reset}
      savedMessage={savedMessage}>
      <View style={styles.hero}>
        <View style={styles.avatar} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          <Txt style={{ fontFamily: DISPLAY, fontSize: 22, color: C.ink }}>{initials(display)}</Txt>
        </View>
        <View style={{ flex: 1, minWidth: 0, gap: 2 }}>
          <Txt v="cardTitle" color={C.white} numberOfLines={1}>
            {display}
          </Txt>
          <Txt v="helper" color={C.onGreenMuted}>
            Shown on Today’s greeting
          </Txt>
        </View>
      </View>

      <Field label="Your name" error={nameErr}>
        <Input
          value={name}
          onChangeText={(t) => {
            setNameDraft(t);
            setServer((s) => ({ ...s, name: undefined }));
          }}
          onBlur={() => setTouched((t) => ({ ...t, name: true }))}
          autoComplete="name"
          textContentType="name"
          autoCapitalize="words"
          state={nameErr ? 'bad' : 'default'}
          accessibilityLabel="Your name"
        />
      </Field>

      <Field label="Email" error={emailErr}>
        <Input
          value={email}
          onChangeText={(t) => {
            setEmailDraft(t);
            setServer((s) => ({ ...s, email: undefined, pw: undefined }));
          }}
          onBlur={() => setTouched((t) => ({ ...t, email: true }))}
          keyboardType="email-address"
          autoCapitalize="none"
          autoCorrect={false}
          autoComplete="email"
          textContentType="emailAddress"
          state={emailErr ? 'bad' : 'default'}
          accessibilityLabel="Email"
        />
      </Field>

      {emailChanged ? (
        <View style={styles.warm}>
          <Txt v="helper" lh={18.9} color={C.accentInk}>
            Changing your sign-in email needs your password. We’ll email a 6-digit code to the new address to confirm it.
          </Txt>
          <Field label="Current password" error={pwErr}>
            <Input
              value={pw}
              onChangeText={(t) => {
                setPw(t);
                setServer((s) => ({ ...s, pw: undefined }));
              }}
              onBlur={() => setTouched((t) => ({ ...t, pw: true }))}
              secureTextEntry
              autoComplete="current-password"
              textContentType="password"
              placeholder="Your password"
              state={pwErr ? 'bad' : 'default'}
              accessibilityLabel="Current password"
            />
          </Field>
        </View>
      ) : null}

      {general ? (
        <Txt v="helper" color={C.danger} accessibilityRole="alert">
          {general}
        </Txt>
      ) : null}

      <LinkCard
        title="Change password"
        sub={me.password_changed_at ? `Last changed ${lowerFirst(ago(me.password_changed_at))}` : null}
        onPress={() => router.push('/account/password')}
      />
    </EditScaffold>
  );
}

const lowerFirst = (s: string) => (s === 'Just now' ? 'just now' : s);

const styles = StyleSheet.create({
  hero: { flexDirection: 'row', alignItems: 'center', gap: 14, padding: 16, borderRadius: 22, backgroundColor: C.primary },
  avatar: { width: 60, height: 60, borderRadius: 30, backgroundColor: C.accent, alignItems: 'center', justifyContent: 'center' },
  warm: { gap: 10, padding: 14, borderRadius: 18, backgroundColor: C.amberSoft },
});
