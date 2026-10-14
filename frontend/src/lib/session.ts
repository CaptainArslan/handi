import { router, type Href } from 'expo-router';

import { qk } from '@/api/queries';
import type { Me } from '@/api/types';
import { persister, queryClient } from '@/lib/query-client';
import { useAuth } from '@/store/auth';
import { useQueue } from '@/store/queue';

/** Forget everything cached for the previous account (memory + persisted cache + offline queue). */
async function wipe() {
  await queryClient.cancelQueries();
  queryClient.clear();
  try {
    await persister.removeClient();
  } catch {
    // nothing persisted yet
  }
  useQueue.getState().clear();
}

/** Signed in (login / sign-up): start from a clean cache, then store the new token. */
export async function startSession(token: string, user: Me) {
  await wipe();
  await useAuth.getState().setSession(token, user);
  queryClient.setQueryData(qk.me, user);
}

/** Logged out, account deleted, or token revoked. */
export async function endSession() {
  await useAuth.getState().clear();
  await wipe();
}

/** Navigate to `href` as a fresh stack (no going "back" into login/onboarding/settings). */
export function resetTo(href: Href) {
  if (router.canDismiss()) router.dismissAll();
  router.replace(href);
}
