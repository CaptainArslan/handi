import AsyncStorage from '@react-native-async-storage/async-storage';
import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';

import { endpoints, type MealInput } from '@/api/endpoints';
import type { MealLog } from '@/api/types';
import { queryClient } from '@/lib/query-client';

import { useNet } from './net';
import { flash } from '@/components/ui/Toasts';

export interface QueuedOp {
  op: 'meal.create' | 'meal.delete';
  payload: MealInput | { id: string };
  /** For showing queued meals in History/Today before they sync. */
  preview?: Pick<MealLog, 'id' | 'dish_name' | 'image_url' | 'image_key' | 'image_bg' | 'date' | 'meal' | 'source' | 'kitchen_dish_id'>;
  queuedAt: string;
}

interface QueueState {
  ops: QueuedOp[];
  syncing: boolean;
  lastSyncedAt: string | null;
  add: (op: QueuedOp) => void;
  /** Drop queued ops for a meal id (undo before it synced). */
  remove: (mealId: string) => void;
  /** Send everything waiting to POST /me/sync. Safe to call any time. */
  flush: () => Promise<void>;
  clear: () => void;
}

/** Offline queue for meal logs ("Works offline: saved on your phone, synced later"). */
export const useQueue = create<QueueState>()(
  persist(
    (set, get) => ({
      ops: [],
      syncing: false,
      lastSyncedAt: null,
      add: (op) => set((s) => ({ ops: [...s.ops, op] })),
      remove: (mealId) => set((s) => ({ ops: s.ops.filter((o) => (o.payload as { id?: string }).id !== mealId) })),
      clear: () => set({ ops: [] }),
      flush: async () => {
        const { ops, syncing } = get();
        if (syncing) return;
        if (ops.length === 0) {
          set({ lastSyncedAt: new Date().toISOString() });
          return;
        }
        if (!useNet.getState().online) return;
        set({ syncing: true });
        try {
          // The API takes up to 200 operations per call.
          let failed = 0;
          for (let i = 0; i < ops.length; i += 200) {
            const chunk = ops.slice(i, i + 200);
            const res = await endpoints.sync(chunk.map((o) => ({ op: o.op, payload: o.payload as unknown as Record<string, unknown> })));
            failed += res.results.filter((r) => !r.ok).length;
            // Drop everything that was answered (ok, or a validation error that would never succeed).
            set((s) => ({ ops: s.ops.slice(chunk.length), lastSyncedAt: res.synced_at }));
          }
          if (failed > 0) {
            flash({ title: `${failed} offline change${failed === 1 ? '' : 's'} couldn’t be saved`, sub: 'The dish may have been removed. Check your History.', tone: 'info' });
          }
          await Promise.all([
            queryClient.invalidateQueries({ queryKey: ['today'] }),
            queryClient.invalidateQueries({ queryKey: ['meals'] }),
            queryClient.invalidateQueries({ queryKey: ['notifications'] }),
            queryClient.invalidateQueries({ queryKey: ['me'] }),
          ]);
        } catch {
          // still offline / server down — keep the queue
        } finally {
          set({ syncing: false });
        }
      },
    }),
    {
      name: 'handi.queue',
      storage: createJSONStorage(() => AsyncStorage),
      partialize: (s) => ({ ops: s.ops, lastSyncedAt: s.lastSyncedAt }),
    },
  ),
);

// Back online → sync.
useNet.subscribe((s, prev) => {
  if (s.online && !prev.online) void useQueue.getState().flush();
});

export const pendingCount = () => useQueue.getState().ops.length;
