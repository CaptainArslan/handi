import { secure } from '@/lib/secure';
import { create } from 'zustand';

import type { Me } from '@/api/types';

const TOKEN_KEY = 'handi.token';

interface AuthState {
  token: string | null;
  user: Me | null;
  /** True once the token has been read from secure storage on app start. */
  hydrated: boolean;
  hydrate: () => Promise<void>;
  setSession: (token: string, user: Me) => Promise<void>;
  setUser: (user: Me) => void;
  clear: () => Promise<void>;
}

export const useAuth = create<AuthState>((set) => ({
  token: null,
  user: null,
  hydrated: false,

  hydrate: async () => {
    try {
      const token = await secure.get(TOKEN_KEY);
      set({ token, hydrated: true });
    } catch {
      set({ token: null, hydrated: true });
    }
  },

  setSession: async (token, user) => {
    await secure.set(TOKEN_KEY, token);
    set({ token, user });
  },

  setUser: (user) => set({ user }),

  clear: async () => {
    await secure.remove(TOKEN_KEY).catch(() => undefined);
    set({ token: null, user: null });
  },
}));

/** Read the token outside React (axios interceptor). */
export const getToken = () => useAuth.getState().token;
