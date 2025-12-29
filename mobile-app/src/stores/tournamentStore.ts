/**
 * Tournament store using Zustand
 * Manages active tournament and tournament list
 */

import { create } from 'zustand';
import type { Tournament } from '@shared/types/tournament';

export interface TournamentStoreState {
  // Data
  activeTournament: Tournament | null;
  tournaments: Tournament[];
  isLoading: boolean;
  error: string | null;

  // Actions
  setActiveTournament: (tournament: Tournament | null) => void;
  setTournaments: (tournaments: Tournament[]) => void;
  addTournament: (tournament: Tournament) => void;
  updateTournament: (tournamentId: string, updates: Partial<Tournament>) => void;
  removeTournament: (tournamentId: string) => void;
  setLoading: (loading: boolean) => void;
  setError: (error: string | null) => void;
  reset: () => void;
}

const initialState = {
  activeTournament: null,
  tournaments: [],
  isLoading: false,
  error: null,
};

export const useTournamentStore = create<TournamentStoreState>((set, get) => ({
  ...initialState,

  setActiveTournament: (tournament: Tournament | null) => {
    set({ activeTournament: tournament });
  },

  setTournaments: (tournaments: Tournament[]) => {
    set({ tournaments });
  },

  addTournament: (tournament: Tournament) => {
    set((state) => ({
      tournaments: [...state.tournaments, tournament],
    }));
  },

  updateTournament: (tournamentId: string, updates: Partial<Tournament>) => {
    set((state) => ({
      tournaments: state.tournaments.map((t) =>
        t.tournamentId === tournamentId ? { ...t, ...updates } : t
      ),
      activeTournament:
        state.activeTournament?.tournamentId === tournamentId
          ? { ...state.activeTournament, ...updates }
          : state.activeTournament,
    }));
  },

  removeTournament: (tournamentId: string) => {
    set((state) => ({
      tournaments: state.tournaments.filter((t) => t.tournamentId !== tournamentId),
      activeTournament:
        state.activeTournament?.tournamentId === tournamentId
          ? null
          : state.activeTournament,
    }));
  },

  setLoading: (loading: boolean) => {
    set({ isLoading: loading });
  },

  setError: (error: string | null) => {
    set({ error });
  },

  reset: () => {
    set(initialState);
  },
}));
