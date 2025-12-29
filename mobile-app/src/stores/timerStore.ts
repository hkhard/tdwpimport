/**
 * Timer store using Zustand
 * Manages timer state for tournament countdown
 */

import { create } from 'zustand';
import { TimerState, TournamentStatus } from '@shared/types/timer';

export interface TimerStoreState {
  // Current timer state
  tournamentId: string | null;
  isRunning: boolean;
  isPaused: boolean;
  currentLevel: number;
  elapsedTime: number; // centiseconds
  lastUpdateTime: Date;

  // Computed values
  remainingTime: number; // centiseconds
  blindLevel: {
    small: number;
    big: number;
    ante: number;
  } | null;

  // Actions
  setTournament: (tournamentId: string) => void;
  startTimer: () => void;
  pauseTimer: () => void;
  resumeTimer: () => void;
  stopTimer: () => void;
  setLevel: (level: number) => void;
  setElapsedTime: (time: number) => void;
  updateFromServer: (state: TimerState) => void;
  reset: () => void;
}

const initialState = {
  tournamentId: null,
  isRunning: false,
  isPaused: false,
  currentLevel: 0,
  elapsedTime: 0,
  lastUpdateTime: new Date(),
  remainingTime: 0,
  blindLevel: null,
};

export const useTimerStore = create<TimerStoreState>((set, get) => ({
  ...initialState,

  setTournament: (tournamentId: string) => {
    set({ tournamentId });
  },

  startTimer: () => {
    const now = new Date();
    set({
      isRunning: true,
      isPaused: false,
      lastUpdateTime: now,
    });
  },

  pauseTimer: () => {
    set({
      isPaused: true,
      isRunning: false,
    });
  },

  resumeTimer: () => {
    const now = new Date();
    set({
      isRunning: true,
      isPaused: false,
      lastUpdateTime: now,
    });
  },

  stopTimer: () => {
    set({
      isRunning: false,
      isPaused: false,
      elapsedTime: 0,
      remainingTime: 0,
      currentLevel: 0,
    });
  },

  setLevel: (level: number) => {
    set({ currentLevel: level });
  },

  setElapsedTime: (time: number) => {
    set({ elapsedTime: time });
  },

  updateFromServer: (state: TimerState) => {
    set({
      isRunning: state.isRunning,
      isPaused: state.isPaused,
      currentLevel: state.level,
      elapsedTime: state.elapsedTime,
      lastUpdateTime: state.lastUpdateTime,
    });
  },

  reset: () => {
    set(initialState);
  },
}));
