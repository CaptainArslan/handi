import { useQueryClient } from '@tanstack/react-query';
import type { ReactNode } from 'react';
import { Pressable, View } from 'react-native';

import { errorMessage } from '@/api/client';
import { qk } from '@/api/queries';
import type { Kitchen, Me } from '@/api/types';
import { Button } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import { Spinner } from '@/components/ui/Spinner';
import { Txt } from '@/components/ui/Txt';
import { useAuth } from '@/store/auth';
import { C } from '@/theme/tokens';

/** Settings/account helpers shared by 7.1–7.7. */

/** After a save: put the fresh user/kitchen in the `me` cache and refresh Today. */
export function useApplyMe() {
  const queryClient = useQueryClient();
  const applyMe = (me: Me) => {
    queryClient.setQueryData(qk.me, me);
    useAuth.getState().setUser(me);
    void queryClient.invalidateQueries({ queryKey: qk.me });
    void queryClient.invalidateQueries({ queryKey: qk.today });
  };
  const applyKitchen = (kitchen: Kitchen) => {
    const prev = queryClient.getQueryData<Me>(qk.me);
    if (prev) {
      const next = { ...prev, kitchen };
      queryClient.setQueryData(qk.me, next);
      useAuth.getState().setUser(next);
    }
    void queryClient.invalidateQueries({ queryKey: qk.me });
    void queryClient.invalidateQueries({ queryKey: qk.today });
  };
  return { applyMe, applyKitchen };
}

/** 2.11 validation visibility: shown if submitted OR value differs from original OR touched. */
export const visible = (error: string | null | undefined, submitted: boolean, changed: boolean, touched: boolean) =>
  error && (submitted || changed || touched) ? error : null;

/** Loading / error placeholder while `me` isn't in the cache yet. */
export function MeState({ error, onRetry }: { error: unknown; onRetry: () => void }) {
  return (
    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: 14, padding: 24, backgroundColor: C.bg }}>
      {error ? (
        <>
          <Txt v="body" color={C.muted} align="center">
            {errorMessage(error)}
          </Txt>
          <Button label="Try again" height={48} fill={false} onPress={onRetry} />
        </>
      ) : (
        <Spinner size={24} color={C.primary} track={C.dashed} />
      )}
    </View>
  );
}

/** White link card: title 15/700 + sub 13 muted + chevron (Profile → Change password). */
export function LinkCard({ title, sub, onPress }: { title: string; sub?: string | null; onPress: () => void }) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="link"
      style={({ pressed }) => ({
        flexDirection: 'row',
        alignItems: 'center',
        gap: 12,
        padding: 14,
        borderRadius: 18,
        backgroundColor: pressed ? C.surfaceAlt2 : C.white,
        borderWidth: 1,
        borderColor: C.borderSoft,
      })}>
      <View style={{ flex: 1, gap: 2 }}>
        <Txt v="itemTitle" size={15}>
          {title}
        </Txt>
        {sub ? (
          <Txt v="helper" color={C.muted}>
            {sub}
          </Txt>
        ) : null}
      </View>
      <Icon name="chevronRight" size={16} color={C.faint} strokeWidth={2.2} />
    </Pressable>
  );
}

/** Info box (mint/warm) with 20px icon, padding 14, radius 18, text 14/1.45. */
export function InfoBox({ children, icon = 'info' }: { children: ReactNode; icon?: 'info' | 'shieldCheck' }) {
  const fg = C.primaryPressed;
  return (
    <View style={{ flexDirection: 'row', gap: 12, padding: 14, borderRadius: 18, backgroundColor: C.mint }}>
      <View style={{ marginTop: 1 }}>
        <Icon name={icon} size={20} color={fg} strokeWidth={2} />
      </View>
      <View style={{ flex: 1 }}>
        {typeof children === 'string' ? (
          <Txt v="bodyS" lh={20.3} color={fg}>
            {children}
          </Txt>
        ) : (
          children
        )}
      </View>
    </View>
  );
}
