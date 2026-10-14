import { router, useNavigation } from 'expo-router';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, View } from 'react-native';

import { AppHeader, StickyFooter } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { Sheet } from '@/components/ui/Sheet';
import { Txt } from '@/components/ui/Txt';
import { C } from '@/theme/tokens';

/**
 * 2.11 Edit-screen pattern (7.2–7.6): header with "Unsaved" chip, sticky Save footer
 * (Save changes / Saving… / ✓ Saved), Discard, "Saved" toast with Done → Settings,
 * and the "Leave without saving?" guard.
 */
export function EditScaffold({
  title,
  sub,
  dirty,
  canSave = true,
  saving,
  onSave,
  onDiscard,
  savedMessage,
  children,
  saveLabel = 'Save changes',
  doneHref = '/settings',
}: {
  title: string;
  sub: string;
  dirty: boolean;
  /** False when there are validation errors (button stays green but Save shows errors). */
  canSave?: boolean;
  saving: boolean;
  /** Return true when saved, false/throw to stay. */
  onSave: () => Promise<boolean>;
  onDiscard: () => void;
  /** Message in the "Saved" toast. */
  savedMessage: string | null;
  children: ReactNode;
  saveLabel?: string;
  doneHref?: '/settings';
}) {
  const navigation = useNavigation();
  const [justSaved, setJustSaved] = useState(false);
  const [everSaved, setEverSaved] = useState(false);
  const [leaveOpen, setLeaveOpen] = useState(false);
  const pendingAction = useRef<null | (() => void)>(null);
  const dirtyRef = useRef(dirty);
  useEffect(() => {
    dirtyRef.current = dirty;
  }, [dirty]);

  // Back gesture / hardware back with unsaved changes → ask first.
  useEffect(() => {
    const sub = navigation.addListener('beforeRemove', (e) => {
      if (!dirtyRef.current) return;
      e.preventDefault();
      pendingAction.current = () => navigation.dispatch(e.data.action);
      setLeaveOpen(true);
    });
    return sub;
  }, [navigation]);

  useEffect(() => {
    if (!justSaved) return;
    const t = setTimeout(() => setJustSaved(false), 3200);
    return () => clearTimeout(t);
  }, [justSaved]);

  const save = async () => {
    const ok = await onSave();
    if (ok) {
      setJustSaved(true);
      setEverSaved(true);
    }
  };

  const back = () => (router.canGoBack() ? router.back() : router.replace(doneHref));

  return (
    <View style={{ flex: 1, backgroundColor: C.bg }}>
      <AppHeader
        title={title}
        sub={sub}
        titleV="editTitle"
        onBack={back}
        backLabel="Back to settings"
        padding={{ h: 20, bottom: 12 }}
        right={
          dirty ? (
            <View style={{ paddingVertical: 5, paddingHorizontal: 10, borderRadius: 99, backgroundColor: C.amberSoft }}>
              <Txt v="badge" color={C.accentText}>
                Unsaved
              </Txt>
            </View>
          ) : null
        }
      />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={{ paddingHorizontal: 20, paddingTop: 6, paddingBottom: 24, gap: 20 }}>
          {children}
        </ScrollView>
        <StickyFooter padding={{ top: 12, h: 20, bottom: 30 }} gap={4}>
          <Button
            label={saving ? 'Saving…' : justSaved && !dirty ? '✓ Saved' : saveLabel}
            variant={justSaved && !dirty ? 'success' : 'primary'}
            height={54}
            loading={saving}
            disabled={!dirty && !justSaved}
            onPress={dirty ? save : undefined}
          />
          {dirty ? (
            <Pressable onPress={onDiscard} accessibilityRole="button" style={{ height: 40, alignItems: 'center', justifyContent: 'center' }}>
              <Txt v="buttonBold" size={14} color={C.muted}>
                Discard changes
              </Txt>
            </Pressable>
          ) : (
            <View style={{ height: 40, alignItems: 'center', justifyContent: 'center' }}>
              <Txt v="caption" color={C.muted}>
                {everSaved ? 'Saved · synced to your account' : 'Change something to enable Save'}
              </Txt>
            </View>
          )}
        </StickyFooter>
      </KeyboardAvoidingView>

      <SavedToast message={justSaved ? savedMessage : null} onDone={() => router.navigate(doneHref)} />

      <Sheet visible={leaveOpen} onClose={() => setLeaveOpen(false)} handle scrim="default">
        <Txt v="sheetTitle" size={21}>
          Leave without saving?
        </Txt>
        <Txt v="body" color={C.muted}>
          Your changes on this screen will be lost.
        </Txt>
        <View style={{ gap: 10, marginTop: 4 }}>
          <Button label="Keep editing" height={54} onPress={() => setLeaveOpen(false)} />
          <Button
            label="Discard & leave"
            variant="outlineDanger"
            height={54}
            onPress={() => {
              setLeaveOpen(false);
              onDiscard();
              dirtyRef.current = false;
              const act = pendingAction.current;
              pendingAction.current = null;
              setTimeout(() => (act ? act() : router.navigate(doneHref)), 50);
            }}
          />
        </View>
      </Sheet>
    </View>
  );
}

/** "Saved" toast above the footer (bottom 118) with a "Done" link. */
function SavedToast({ message, onDone }: { message: string | null; onDone: () => void }) {
  if (!message) return null;
  return (
    <View
      style={{
        position: 'absolute',
        left: 12,
        right: 12,
        bottom: 118,
        borderRadius: 16,
        backgroundColor: C.inkToast,
        flexDirection: 'row',
        alignItems: 'center',
        gap: 12,
        paddingVertical: 10,
        paddingLeft: 12,
        paddingRight: 8,
        shadowColor: '#000',
        shadowOpacity: 0.28,
        shadowRadius: 17,
        shadowOffset: { width: 0, height: 14 },
        elevation: 12,
      }}
      accessibilityLiveRegion="polite">
      <View style={{ width: 36, height: 36, borderRadius: 18, backgroundColor: C.success, alignItems: 'center', justifyContent: 'center' }}>
        <Txt v="buttonBold" color={C.white}>
          ✓
        </Txt>
      </View>
      <View style={{ flex: 1, gap: 1 }}>
        <Txt v="toastTitle" color={C.white}>
          Saved
        </Txt>
        <Txt v="toastSub" color={C.onDarkMuted} numberOfLines={2}>
          {message}
        </Txt>
      </View>
      <Pressable onPress={onDone} accessibilityRole="button" style={{ height: 40, paddingHorizontal: 12, borderRadius: 10, justifyContent: 'center' }}>
        <Txt v="toastTitle" color={C.accent}>
          Done
        </Txt>
      </Pressable>
    </View>
  );
}
