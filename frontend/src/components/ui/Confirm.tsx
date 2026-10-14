import { type ReactNode, useState } from 'react';
import { View } from 'react-native';
import { create } from 'zustand';

import { errorMessage } from '@/api/client';
import { C } from '@/theme/tokens';

import { Button } from './Button';
import { Icon, type IconName } from './Icon';
import { Sheet } from './Sheet';
import { flash, type FlashMessage } from './Toasts';
import { Txt } from './Txt';

export interface ConfirmOptions {
  title: string;
  message?: string;
  /** Label of the confirm button. Default "Delete". */
  confirmLabel?: string;
  /** Label of the cancel button. Default "Cancel". */
  cancelLabel?: string;
  /** Red (delete/remove) or dark (log out, leave…). Default danger. */
  tone?: 'danger' | 'dark';
  icon?: IconName;
  /** Optional picture instead of the icon (e.g. the dish image). */
  media?: ReactNode;
  /**
   * The action. The confirm button shows the app's spinner while it runs and the sheet can't be
   * dismissed. Return a FlashMessage to override the success toast, or `false` to show none.
   */
  run: () => Promise<void | FlashMessage | false>;
  /** Success toast (when `run` doesn't return one). */
  success?: FlashMessage;
  /** Title of the error toast; the sub line is the server's message. */
  errorTitle?: string;
}

interface ConfirmState {
  current: (ConfirmOptions & { id: number; resolve: (ok: boolean) => void }) | null;
  open: (o: ConfirmOptions) => Promise<boolean>;
  close: () => void;
}

const useConfirm = create<ConfirmState>((set, get) => ({
  current: null,
  open: (o) =>
    new Promise<boolean>((resolve) => {
      get().current?.resolve(false);
      set({ current: { ...o, id: Date.now(), resolve } });
    }),
  close: () => set({ current: null }),
}));

/**
 * Ask before a destructive action, run it with a loader, then toast the result.
 * Resolves `true` when the action succeeded, `false` when cancelled or failed.
 *
 *   await confirmAction({ title: 'Delete this meal?', run: () => endpoints.deleteMeal(id), success: { title: 'Meal deleted' } })
 */
export const confirmAction = (o: ConfirmOptions) => useConfirm.getState().open(o);

/** Global host — render once in the root layout (next to FlashHost). */
export function ConfirmHost() {
  const current = useConfirm((s) => s.current);
  const close = useConfirm((s) => s.close);
  const [busy, setBusy] = useState(false);
  const [shown, setShown] = useState<ConfirmState['current']>(null);

  // Keep the last content while the sheet animates out.
  if (current && current !== shown) setShown(current);
  const o = current ?? shown;

  const cancel = () => {
    if (busy || !current) return;
    current.resolve(false);
    close();
  };

  const confirm = async () => {
    if (!current || busy) return;
    const c = current;
    setBusy(true);
    try {
      const result = await c.run();
      close();
      if (result !== false) {
        const toast = result ?? c.success;
        if (toast) flash({ tone: 'success', ...toast });
      }
      c.resolve(true);
    } catch (e) {
      close();
      flash({ title: c.errorTitle ?? 'Couldn’t complete that', sub: errorMessage(e), tone: 'error' });
      c.resolve(false);
    } finally {
      setBusy(false);
    }
  };

  const danger = (o?.tone ?? 'danger') === 'danger';

  return (
    <Sheet visible={!!current} onClose={cancel} scrim="light" accessibilityLabel={o?.title}>
      {o ? (
        <>
          {o.media ?? (
            <View
              style={{
                width: 56,
                height: 56,
                borderRadius: 18,
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: danger ? C.dangerSoft : C.surfaceAlt2,
              }}>
              <Icon name={o.icon ?? (danger ? 'trash' : 'alert')} size={26} color={danger ? C.danger : C.ink} strokeWidth={2} />
            </View>
          )}
          <Txt v="sheetTitle" accessibilityRole="header">
            {o.title}
          </Txt>
          {o.message ? (
            <Txt v="body" color={C.muted}>
              {o.message}
            </Txt>
          ) : null}
          <Button label={o.confirmLabel ?? 'Delete'} variant={danger ? 'danger' : 'dark'} height={54} loading={busy} onPress={confirm} />
          <Button label={o.cancelLabel ?? 'Cancel'} variant="outline" height={54} disabled={busy} onPress={cancel} />
        </>
      ) : null}
    </Sheet>
  );
}
