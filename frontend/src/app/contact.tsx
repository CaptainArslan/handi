import { useMutation } from '@tanstack/react-query';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as DocumentPicker from 'expo-document-picker';
import { useState } from 'react';
import { Platform, Pressable, StyleSheet, View } from 'react-native';

import { errorMessage, fieldErrors, status } from '@/api/client';
import { endpoints, type ContactTopic } from '@/api/endpoints';
import { goBack, IconTile, InlineAlert } from '@/components/AuthKit';
import { FormScroll, Heading, Spacer } from '@/components/Layout';
import { BackButton, Button } from '@/components/ui/Button';
import { ChoiceChip } from '@/components/ui/Controls';
import { Field, Input } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import { Txt } from '@/components/ui/Txt';
import { SUPPORT_EMAIL } from '@/lib/config';
import { emailError } from '@/lib/validate';
import { useAuth } from '@/store/auth';
import { C } from '@/theme/tokens';

const TOPICS: { key: ContactTopic; label: string }[] = [
  { key: 'question', label: 'Question' },
  { key: 'problem', label: 'Something isn’t working' },
  { key: 'account', label: 'My account' },
  { key: 'feedback', label: 'Feedback or idea' },
  { key: 'other', label: 'Something else' },
];

const MAX_BYTES = 2 * 1024 * 1024;
const MAX_MESSAGE = 3000;
const ACCEPT = [
  'image/*',
  'application/pdf',
  'text/plain',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
const EXT_OK = /\.(jpe?g|png|webp|heic|gif|pdf|txt|docx?)$/i;

type Picked = { uri: string; name: string; type: string; size: number | null };

const sizeLabel = (b: number | null) => (b == null ? '' : b >= 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

/** Model + OS for the team (never the phone's personal name). */
function deviceInfo(): string {
  if (Platform.OS === 'web') return 'Web browser';
  const model = Device.modelName ?? (Platform.OS === 'ios' ? 'iPhone' : 'Android phone');
  return `${model} · ${Platform.OS === 'ios' ? 'iOS' : 'Android'} ${Device.osVersion ?? ''}`.trim().slice(0, 60);
}

export default function Contact() {
  const me = useAuth((s) => s.user);
  const [name, setName] = useState(me?.name ?? '');
  const [email, setEmail] = useState(me?.email ?? '');
  const [topic, setTopic] = useState<ContactTopic | null>(null);
  const [message, setMessage] = useState('');
  const [file, setFile] = useState<Picked | null>(null);
  const [fileErr, setFileErr] = useState<string | null>(null);
  const [submitted, setSubmitted] = useState(false);
  const [server, setServer] = useState<Record<string, string | undefined>>({});
  const [general, setGeneral] = useState<string | null>(null);
  const [sent, setSent] = useState<string | null>(null);

  const nameErr = name.trim().length < 2 ? (name.trim() ? 'Name is too short.' : 'Enter your name.') : null;
  const emailErr = emailError(email, 'Enter your email so we can reply.');
  const topicErr = topic ? null : 'Choose what it’s about.';
  const msgErr = !message.trim()
    ? 'Write your message.'
    : message.trim().length < 10
      ? 'Tell us a little more (at least 10 characters).'
      : message.length > MAX_MESSAGE
        ? 'Keep it under 3,000 characters.'
        : null;

  const show = (local: string | null, key: string) => (submitted && local) || server[key] || null;
  const clear = (key: string) => server[key] && setServer((s) => ({ ...s, [key]: undefined }));

  const send = useMutation({
    mutationFn: () =>
      endpoints.contact({
        name: name.trim(),
        email: email.trim().toLowerCase(),
        topic: topic!,
        message: message.trim(),
        device: deviceInfo(),
        app_version: Constants.expoConfig?.version ?? undefined,
        attachment: file ? { uri: file.uri, name: file.name, type: file.type } : null,
      }),
    onSuccess: (res) => setSent(res.message),
    onError: (error) => {
      if (status(error) === 422) {
        const fe = fieldErrors(error);
        setServer(fe);
        if (fe.attachment) setFileErr(fe.attachment);
        if (!fe.name && !fe.email && !fe.topic && !fe.message && !fe.attachment) setGeneral(errorMessage(error));
      } else if (status(error) === 429) {
        setGeneral(`You’ve sent a few messages already. Please wait a while, or email us at ${SUPPORT_EMAIL}.`);
      } else if (status(error) === 413) {
        setFileErr('That file is too large. Files can be up to 2 MB.');
      } else {
        setGeneral(errorMessage(error));
      }
    },
  });

  const pick = async () => {
    setFileErr(null);
    try {
      const res = await DocumentPicker.getDocumentAsync({ type: ACCEPT, copyToCacheDirectory: true, multiple: false });
      if (res.canceled || !res.assets?.[0]) return;
      const a = res.assets[0];
      const size = a.size ?? a.file?.size ?? null;
      if (size != null && size > MAX_BYTES) {
        setFileErr(`That file is ${sizeLabel(size)}. Files can be up to 2 MB.`);
        return;
      }
      if (!EXT_OK.test(a.name)) {
        setFileErr('Attach an image, PDF, TXT or Word file.');
        return;
      }
      setFile({ uri: a.uri, name: a.name, type: a.mimeType ?? 'application/octet-stream', size });
    } catch {
      setFileErr('Couldn’t open your files. Please try again.');
    }
  };

  const submit = () => {
    if (send.isPending) return;
    setSubmitted(true);
    setGeneral(null);
    setServer({});
    if (nameErr || emailErr || topicErr || msgErr || fileErr) return;
    send.mutate();
  };

  if (sent) {
    return (
      <FormScroll gap={26}>
        <Spacer />
        <View style={{ alignItems: 'center', gap: 22 }}>
          <IconTile icon="check" bg={C.mint} color={C.primary} size={76} radius={26} iconSize={36} strokeWidth={2.4} />
          <Heading center title="Message sent" gap={10} sub={`${sent} We’ll write back to ${email.trim().toLowerCase()}.`} />
        </View>
        <Spacer />
        <Button label="Done" weight={600} onPress={() => goBack(me ? '/settings' : '/welcome')} />
      </FormScroll>
    );
  }

  return (
    <FormScroll gap={24}>
      <View style={{ alignSelf: 'flex-start' }}>
        <BackButton onPress={() => goBack(me ? '/settings' : '/welcome')} />
      </View>
      <IconTile icon="mail" bg={C.mint} color={C.primary} />
      <Heading title="Contact us" gap={8} sub="Questions, problems or ideas. Send us a message and we’ll reply by email, usually within one working day." />

      <View style={{ gap: 18 }}>
        {general ? <InlineAlert>{general}</InlineAlert> : null}

        <Field label="Your name" error={show(nameErr, 'name')}>
          <Input
            value={name}
            onChangeText={(t) => {
              setName(t);
              clear('name');
            }}
            state={show(nameErr, 'name') ? 'bad' : 'default'}
            placeholder="e.g. Ayesha Khan"
            autoComplete="name"
            textContentType="name"
            autoCapitalize="words"
            maxLength={80}
            returnKeyType="next"
            accessibilityLabel="Your name"
          />
        </Field>

        <Field label="Email" helper="We’ll reply here." error={show(emailErr, 'email')}>
          <Input
            icon="mail"
            value={email}
            onChangeText={(t) => {
              setEmail(t);
              clear('email');
            }}
            state={show(emailErr, 'email') ? 'bad' : 'default'}
            placeholder="name@example.com"
            keyboardType="email-address"
            autoComplete="email"
            textContentType="emailAddress"
            autoCapitalize="none"
            autoCorrect={false}
            accessibilityLabel="Email"
          />
        </Field>

        <Field label="What’s it about?" error={show(topicErr, 'topic')}>
          <View style={styles.chips} accessibilityRole="radiogroup">
            {TOPICS.map((t) => (
              <ChoiceChip
                key={t.key}
                label={t.label}
                selected={topic === t.key}
                height={40}
                accessibilityRole="radio"
                onPress={() => {
                  setTopic(t.key);
                  clear('topic');
                }}
              />
            ))}
          </View>
        </Field>

        <Field
          label="Message"
          error={show(msgErr, 'message')}
          helper={`${message.length.toLocaleString()} / ${MAX_MESSAGE.toLocaleString()}`}
          helperColor={message.length > MAX_MESSAGE ? C.danger : C.faint}>
          <Input
            value={message}
            onChangeText={(t) => {
              setMessage(t);
              clear('message');
            }}
            state={show(msgErr, 'message') ? 'bad' : 'default'}
            placeholder={topic === 'problem' ? 'What happened? What did you expect to happen?' : 'How can we help?'}
            multiline
            height={160}
            textAlignVertical="top"
            inputStyle={styles.multiline}
            accessibilityLabel="Message"
          />
        </Field>

        <Field label="Attachment (optional)" error={fileErr}>
          {file ? (
            <View style={styles.fileRow}>
              <View style={styles.fileIcon}>
                <Icon name={file.type.startsWith('image/') ? 'camera' : 'file'} size={20} color={C.primary} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Txt v="bodyS" weight={600} numberOfLines={1}>
                  {file.name}
                </Txt>
                {file.size != null ? (
                  <Txt v="helper" color={C.muted}>
                    {sizeLabel(file.size)}
                  </Txt>
                ) : null}
              </View>
              <Pressable onPress={() => setFile(null)} accessibilityRole="button" accessibilityLabel={`Remove ${file.name}`} hitSlop={6} style={styles.remove}>
                <Icon name="close" size={18} color={C.muted} strokeWidth={2.2} />
              </Pressable>
            </View>
          ) : (
            <Pressable
              onPress={pick}
              accessibilityRole="button"
              accessibilityLabel="Attach a file, up to 2 MB"
              style={({ pressed }) => [styles.drop, { backgroundColor: pressed ? C.surfaceAlt : C.white, borderColor: fileErr ? C.danger : C.dashed }]}>
              <Icon name="paperclip" size={20} color={C.primary} />
              <View style={{ flex: 1, gap: 2 }}>
                <Txt v="bodyS" weight={600} color={C.primary}>
                  Attach a file
                </Txt>
                <Txt v="helper" color={C.muted}>
                  Screenshot, photo, PDF or document · up to 2 MB
                </Txt>
              </View>
            </Pressable>
          )}
        </Field>

        <Button label="Send message" weight={600} icon="send" loading={send.isPending} onPress={submit} style={{ marginTop: 4 }} />
      </View>

      <Spacer />
      <Txt v="bodyS" lh={21} color={C.muted} align="center">
        Prefer email? Write to{' '}
        <Txt v="bodyS" weight={600} color={C.primary}>
          {SUPPORT_EMAIL}
        </Txt>
      </Txt>
    </FormScroll>
  );
}

const styles = StyleSheet.create({
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  multiline: { paddingTop: 14, paddingBottom: 14, minHeight: 157, textAlignVertical: 'top' },
  drop: { flexDirection: 'row', alignItems: 'center', gap: 12, borderWidth: 1.5, borderStyle: 'dashed', borderRadius: 14, paddingVertical: 14, paddingHorizontal: 16 },
  fileRow: { flexDirection: 'row', alignItems: 'center', gap: 12, borderWidth: 1.5, borderColor: C.primaryOk, backgroundColor: C.white, borderRadius: 14, paddingVertical: 10, paddingLeft: 12, paddingRight: 4 },
  fileIcon: { width: 40, height: 40, borderRadius: 12, backgroundColor: C.mint, alignItems: 'center', justifyContent: 'center' },
  remove: { width: 44, height: 44, alignItems: 'center', justifyContent: 'center' },
});
