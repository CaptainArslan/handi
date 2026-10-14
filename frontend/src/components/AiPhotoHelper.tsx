import * as Clipboard from 'expo-clipboard';
import { useState } from 'react';
import { Linking, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { errorMessage } from '@/api/client';
import { useImagePrompt } from '@/api/queries';
import type { ImagePromptSettings } from '@/api/types';
import { Button } from '@/components/ui/Button';
import { Icon, type IconName } from '@/components/ui/Icon';
import { Sheet } from '@/components/ui/Sheet';
import { flash } from '@/components/ui/Toasts';
import { Txt } from '@/components/ui/Txt';
import { C } from '@/theme/tokens';

/** Intro + JSON spec with the dish name filled in (JSON-escaped inside the spec). */
export function buildImagePrompt(p: ImagePromptSettings, dishName: string): string {
  const name = dishName.trim();
  const jsonSafe = JSON.stringify(name).slice(1, -1);
  return `${p.intro.split(p.placeholder).join(name)}\n\n${p.template.split(p.placeholder).join(jsonSafe)}`;
}

const MONO = Platform.select({ ios: 'Menlo', android: 'monospace', default: 'ui-monospace, Menlo, Consolas, monospace' });

/**
 * "No photo? Make one with AI" — sits under Add a photo.
 * Copies the admin-managed JSON spec (with the dish name) so the user can paste it into Gemini
 * and get an image in the same style as every other dish in the app.
 */
export function AiPhotoHelper({ dishName, onUpload }: { dishName: string; onUpload: () => void }) {
  const prompt = useImagePrompt();
  const [open, setOpen] = useState(false);
  const [copied, setCopied] = useState(false);
  const name = dishName.trim();
  const p = prompt.data;

  if (p && !p.enabled) return null;

  const ready = (): ImagePromptSettings | null => {
    if (p) return p;
    flash({ title: 'Couldn’t load the AI prompt', sub: prompt.error ? errorMessage(prompt.error) : 'Check your connection and try again.', tone: 'error' });
    prompt.refetch();
    return null;
  };

  const copy = async (): Promise<boolean> => {
    const s = ready();
    if (!s) return false;
    if (!name) {
      flash({ title: 'Type the dish name first', sub: 'We put it in the prompt so the AI draws the right dish.', tone: 'info' });
      return false;
    }
    try {
      await Clipboard.setStringAsync(buildImagePrompt(s, name));
      setCopied(true);
      setTimeout(() => setCopied(false), 2500);
      flash({ title: 'AI prompt copied', sub: `Paste it in ${s.ai_name} to make a photo of ${name}.` });
      return true;
    } catch {
      flash({ title: 'Couldn’t copy the prompt', sub: 'Open Details and copy it from there.', tone: 'error' });
      return false;
    }
  };

  const openAi = async () => {
    const s = ready();
    if (!s) return;
    try {
      await Linking.openURL(s.ai_url);
    } catch {
      flash({ title: `Couldn’t open ${s.ai_name}`, sub: s.ai_url, tone: 'error' });
    }
  };

  const copyAndOpen = async () => {
    if (await copy()) await openAi();
  };

  const aiName = p?.ai_name ?? 'Gemini';
  const preview = p ? buildImagePrompt(p, name || 'Your dish') : '';

  return (
    <>
      <View style={styles.card}>
        <View style={styles.head}>
          <View style={styles.tile}>
            <Icon name="sparkles" size={20} color={C.accentDark} />
          </View>
          <View style={{ flex: 1, gap: 2 }}>
            <Txt v="itemTitle" size={14}>
              No photo? Make one with AI
            </Txt>
            <Txt v="caption" color={C.muted} lh={17}>
              {name
                ? `Copy our prompt for ${name}, paste it in ${aiName}, then upload the image. It keeps every dish in the same style.`
                : `Type the dish name above first — we put it in the prompt for ${aiName}.`}
            </Txt>
          </View>
        </View>
        <View style={styles.row}>
          <Chip icon={copied ? 'check' : 'copy'} label={copied ? 'Copied' : 'Copy AI prompt'} primary={!!name} wide onPress={copy} loading={prompt.isLoading} />
          <Chip icon="info" label="Details" onPress={() => setOpen(true)} />
        </View>
      </View>

      <Sheet visible={open} onClose={() => setOpen(false)} handle gap={12} accessibilityLabel="Make a photo with AI">
        <Txt v="sheetTitle" accessibilityRole="header">
          Make a photo with AI
        </Txt>
        <View style={{ gap: 8 }}>
          <Step n={1} text={name ? `Dish name: ${name}` : 'Type the dish name above first.'} warn={!name} />
          <Step n={2} text="Copy the prompt below — it already has your dish name." />
          <Step n={3} text={`Open ${aiName}, paste it and send.`} />
          <Step n={4} text="Save the image, then tap Add a photo → Choose from gallery." />
        </View>

        <View style={styles.codeBox}>
          <View style={styles.codeHead}>
            <Txt v="caption" weight={600} color={C.ink2}>
              Prompt · JSON
            </Txt>
            <Pressable onPress={copy} accessibilityRole="button" accessibilityLabel="Copy prompt" hitSlop={8} style={styles.codeCopy}>
              <Icon name={copied ? 'check' : 'copy'} size={15} color={C.primary} />
              <Txt v="caption" weight={600} color={C.primary}>
                {copied ? 'Copied' : 'Copy'}
              </Txt>
            </Pressable>
          </View>
          <ScrollView style={{ maxHeight: 210 }} nestedScrollEnabled showsVerticalScrollIndicator>
            <Txt v="caption" color={C.ink} selectable style={{ fontFamily: MONO, fontSize: 11, lineHeight: 16 }}>
              {prompt.isLoading ? 'Loading…' : preview || 'Couldn’t load the prompt. Check your connection and try again.'}
            </Txt>
          </ScrollView>
        </View>

        <Button label={`Copy & open ${aiName}`} icon="sparkles" height={54} onPress={copyAndOpen} disabled={!p} />
        <Button label={`Open ${aiName}`} iconRight="external" variant="outline" height={50} onPress={openAi} disabled={!p} />
        <Button
          label="I have the image — upload it"
          variant="text"
          height={44}
          onPress={() => {
            setOpen(false);
            onUpload();
          }}
        />
      </Sheet>
    </>
  );
}

function Chip({ icon, label, onPress, primary, wide, loading }: { icon: IconName; label: string; onPress: () => void; primary?: boolean; wide?: boolean; loading?: boolean }) {
  return (
    <Pressable
      onPress={onPress}
      disabled={loading}
      accessibilityRole="button"
      accessibilityLabel={label}
      style={({ pressed }) => [styles.chip, wide ? styles.chipWide : null, primary ? styles.chipPrimary : null, { opacity: loading ? 0.6 : pressed ? 0.8 : 1 }]}>
      <Icon name={icon} size={16} color={primary ? C.white : C.ink2} strokeWidth={2.2} />
      <Txt v="toastTitle" size={13} color={primary ? C.white : C.ink2}>
        {label}
      </Txt>
    </Pressable>
  );
}

function Step({ n, text, warn }: { n: number; text: string; warn?: boolean }) {
  return (
    <View style={{ flexDirection: 'row', gap: 10, alignItems: 'flex-start' }}>
      <View style={[styles.stepDot, warn ? { backgroundColor: C.amberSoft } : null]}>
        <Txt v="badge" size={11} color={warn ? C.accentDark : C.primary}>
          {n}
        </Txt>
      </View>
      <Txt v="bodyS" lh={20} color={warn ? C.accentDark : C.ink2} style={{ flex: 1 }}>
        {text}
      </Txt>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderRadius: 18, borderWidth: 1, borderColor: C.border, backgroundColor: C.white, padding: 14, gap: 12, marginTop: -8 },
  head: { flexDirection: 'row', gap: 12, alignItems: 'flex-start' },
  tile: { width: 40, height: 40, borderRadius: 12, backgroundColor: C.amberSoft, alignItems: 'center', justifyContent: 'center' },
  row: { flexDirection: 'row', gap: 8 },
  chip: { height: 40, paddingHorizontal: 14, borderRadius: 12, backgroundColor: C.surfaceAlt, flexDirection: 'row', alignItems: 'center', gap: 6 },
  chipWide: { flex: 1, justifyContent: 'center' },
  chipPrimary: { backgroundColor: C.primary },
  stepDot: { width: 22, height: 22, borderRadius: 11, backgroundColor: C.mint, alignItems: 'center', justifyContent: 'center', marginTop: -1 },
  codeBox: { borderRadius: 14, borderWidth: 1, borderColor: C.border, backgroundColor: C.surfaceAlt, padding: 12, gap: 8 },
  codeHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  codeCopy: { flexDirection: 'row', alignItems: 'center', gap: 4 },
});
