import { create } from 'zustand';

/** Small cross-screen UI state (the prototype's sessionStorage keys). */
interface UiState {
  /** FloatNav: index of the last active tab, to slide the indicator from it. */
  lastTab: number;
  /** 7.8 Legal: where to go back to. */
  legalFrom: 'signup' | 'settings';
  /** 2.3 Verify code: the email the reset code went to, and its masked form. */
  resetEmail: string | null;
  resetMasked: string | null;
  resetToken: string | null;
  set: (patch: Partial<Omit<UiState, 'set'>>) => void;
}

export const useUi = create<UiState>((set) => ({
  lastTab: 0,
  legalFrom: 'settings',
  resetEmail: null,
  resetMasked: null,
  resetToken: null,
  set: (patch) => set(patch),
}));
