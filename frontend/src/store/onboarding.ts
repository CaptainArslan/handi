import AsyncStorage from '@react-native-async-storage/async-storage';
import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';

import type { Budget, LibraryDish, MealType, Weather } from '@/api/types';

export interface DraftLocation {
  mode: 'gps' | 'city';
  city_id?: number;
  lat?: number;
  lng?: number;
  /** "Lahore · near you" / "Lahore, Punjab" */
  label: string;
  city?: string;
  weather?: Weather | null;
}

export interface DraftCustomDish {
  key: string;
  name: string;
  category: string | null;
}

/**
 * Everything collected during setup (1.3–1.7). Kept on the phone until sign-up
 * sends it in one request ("Offline tweak keeps the setup on the phone").
 */
interface OnboardingState {
  familySize: number;
  budget: Budget | null;
  dishIds: number[];
  customDishes: DraftCustomDish[];
  repeatGap: 3 | 5 | 7 | 10;
  meals: MealType[];
  location: DraftLocation | null;
  /** Library dishes picked (for the preview and reminder copy). */
  pickedDishes: Pick<LibraryDish, 'id' | 'name'>[];
  set: (patch: Partial<Omit<OnboardingState, 'set' | 'reset'>>) => void;
  reset: () => void;
}

const initial = {
  familySize: 4,
  budget: null,
  dishIds: [] as number[],
  customDishes: [] as DraftCustomDish[],
  repeatGap: 5 as const,
  meals: ['lunch', 'dinner'] as MealType[],
  location: null,
  pickedDishes: [] as Pick<LibraryDish, 'id' | 'name'>[],
};

export const useOnboarding = create<OnboardingState>()(
  persist(
    (set) => ({
      ...initial,
      set: (patch) => set(patch),
      reset: () => set({ ...initial }),
    }),
    { name: 'handi.onboarding', storage: createJSONStorage(() => AsyncStorage) },
  ),
);
