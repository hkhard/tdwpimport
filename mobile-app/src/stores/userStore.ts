/**
 * User store using Zustand
 * Manages current user state and preferences
 */

import { create } from 'zustand';

export type UserRole = 'admin' | 'director' | 'viewer';

export interface UserStoreState {
  // User data
  userId: string | null;
  username: string | null;
  role: UserRole | null;

  // User preferences
  preferredTournamentId: string | null;
  notificationsEnabled: boolean;
  soundEnabled: boolean;

  // Actions
  setUser: (userId: string, username: string, role: UserRole) => void;
  clearUser: () => void;
  setPreferredTournament: (tournamentId: string | null) => void;
  setNotificationsEnabled: (enabled: boolean) => void;
  setSoundEnabled: (enabled: boolean) => void;
  reset: () => void;
}

const initialState = {
  userId: null,
  username: null,
  role: null,
  preferredTournamentId: null,
  notificationsEnabled: true,
  soundEnabled: true,
};

export const useUserStore = create<UserStoreState>((set) => ({
  ...initialState,

  setUser: (userId: string, username: string, role: UserRole) => {
    set({ userId, username, role });
  },

  clearUser: () => {
    set({
      userId: null,
      username: null,
      role: null,
      preferredTournamentId: null,
    });
  },

  setPreferredTournament: (tournamentId: string | null) => {
    set({ preferredTournamentId: tournamentId });
  },

  setNotificationsEnabled: (enabled: boolean) => {
    set({ notificationsEnabled: enabled });
  },

  setSoundEnabled: (enabled: boolean) => {
    set({ soundEnabled: enabled });
  },

  reset: () => {
    set(initialState);
  },
}));
