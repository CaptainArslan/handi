import { isNetworkError } from '@/api/client';
import { endpoints, type MealInput } from '@/api/endpoints';
import type { MealLog, MealType } from '@/api/types';
import { useNet } from '@/store/net';
import { useQueue, type QueuedOp } from '@/store/queue';

/** Meal logging that works offline: try the API, fall back to the offline queue (store/queue). */

export const MEAL_LABEL: Record<MealType, string> = { breakfast: 'Breakfast', lunch: 'Lunch', dinner: 'Dinner' };

export type MealPreview = NonNullable<QueuedOp['preview']>;
export type NewMeal = MealInput & { id: string };

const opId = (o: QueuedOp) => (o.payload as { id?: string }).id;

/** Unsynced meals from the queue as History rows (`pending: true`). */
export function pendingLogs(ops: QueuedOp[]): MealLog[] {
  return ops
    .filter((o) => o.op === 'meal.create' && o.preview)
    .map((o) => ({ ...o.preview!, category_key: null, logged_at: o.queuedAt, pending: true }));
}

/** Ids of meals deleted while offline (hide them until the queue syncs). */
export function queuedDeletes(ops: QueuedOp[]): Set<string> {
  return new Set(ops.filter((o) => o.op === 'meal.delete').map((o) => opId(o) ?? ''));
}

export const queuedMealCount = (ops: QueuedOp[]) => ops.filter((o) => o.op === 'meal.create').length;

/** Remove a queued op for this meal id. Returns true if one was removed. */
export function unqueue(id: string, op?: QueuedOp['op']): boolean {
  const before = useQueue.getState().ops;
  const after = before.filter((o) => !(opId(o) === id && (!op || o.op === op)));
  if (after.length === before.length) return false;
  useQueue.setState({ ops: after });
  return true;
}

function queueCreate(payload: NewMeal, preview: MealPreview): MealLog {
  const queuedAt = new Date().toISOString();
  useQueue.getState().add({ op: 'meal.create', payload, preview, queuedAt });
  return { ...preview, category_key: null, logged_at: queuedAt, pending: true };
}

function queueDelete(id: string) {
  useQueue.getState().add({ op: 'meal.delete', payload: { id }, queuedAt: new Date().toISOString() });
}

/** POST /me/meals, or queue it when offline / unreachable. */
export async function createMeal(payload: NewMeal, preview: MealPreview): Promise<{ log: MealLog; duplicate: boolean; queued: boolean }> {
  if (!useNet.getState().online) return { log: queueCreate(payload, preview), duplicate: false, queued: true };
  try {
    const res = await endpoints.logMeal(payload);
    return { log: res.data, duplicate: !!res.meta?.duplicate, queued: false };
  } catch (e) {
    if (isNetworkError(e)) return { log: queueCreate(payload, preview), duplicate: false, queued: true };
    throw e;
  }
}

/** Delete a meal: drop it from the queue if it never synced, else DELETE (or queue the delete offline). */
export async function removeMeal(id: string, pending?: boolean): Promise<'unqueued' | 'deleted' | 'queued'> {
  if (pending && unqueue(id, 'meal.create')) return 'unqueued';
  if (!useNet.getState().online) {
    queueDelete(id);
    return 'queued';
  }
  try {
    await endpoints.deleteMeal(id);
    return 'deleted';
  } catch (e) {
    if (isNetworkError(e)) {
      queueDelete(id);
      return 'queued';
    }
    throw e;
  }
}
