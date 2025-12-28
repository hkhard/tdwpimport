/**
 * Player Store using Zustand
 * Manages global player list and loading state
 */

import { create } from 'zustand';
import type { Player } from '@shared/types/player';
import * as playerApi from '../services/api/playerApi';

export interface PlayerStoreState {
  // Data
  players: Player[];
  isLoading: boolean;
  error: string | null;

  // Actions
  loadPlayers: () => Promise<void>;
  searchPlayers: (query: string) => Promise<Player[]>;
  createPlayer: (data: playerApi.CreatePlayerInput) => Promise<Player>;
  updatePlayer: (playerId: string, data: playerApi.UpdatePlayerInput) => Promise<void>;
  deletePlayer: (playerId: string) => Promise<void>;
  setPlayers: (players: Player[]) => void;
  setLoading: (loading: boolean) => void;
  setError: (error: string | null) => void;
  reset: () => void;
}

const initialState = {
  players: [],
  isLoading: false,
  error: null,
};

export const usePlayerStore = create<PlayerStoreState>((set, get) => ({
  ...initialState,

  loadPlayers: async () => {
    try {
      set({ isLoading: true, error: null });
      const players = await playerApi.fetchPlayers();
      set({ players, isLoading: false });
    } catch (error) {
      console.error('[PlayerStore] Failed to load players:', error);
      set({
        error: error instanceof Error ? error.message : 'Failed to load players',
        isLoading: false,
      });
    }
  },

  searchPlayers: async (query: string) => {
    try {
      set({ isLoading: true, error: null });
      const players = await playerApi.searchPlayers(query);
      set({ players, isLoading: false });
      return players;
    } catch (error) {
      console.error('[PlayerStore] Failed to search players:', error);
      set({
        error: error instanceof Error ? error.message : 'Failed to search players',
        isLoading: false,
      });
      return [];
    }
  },

  createPlayer: async (data: playerApi.CreatePlayerInput) => {
    try {
      set({ isLoading: true, error: null });
      const player = await playerApi.createPlayer(data);
      set((state) => ({
        players: [...state.players, player],
        isLoading: false,
      }));
      return player;
    } catch (error) {
      console.error('[PlayerStore] Failed to create player:', error);
      set({
        error: error instanceof Error ? error.message : 'Failed to create player',
        isLoading: false,
      });
      throw error;
    }
  },

  updatePlayer: async (playerId: string, data: playerApi.UpdatePlayerInput) => {
    try {
      set({ isLoading: true, error: null });
      const updatedPlayer = await playerApi.updatePlayer(playerId, data);
      set((state) => ({
        players: state.players.map((p) =>
          p.playerId === playerId ? updatedPlayer : p
        ),
        isLoading: false,
      }));
    } catch (error) {
      console.error('[PlayerStore] Failed to update player:', error);
      set({
        error: error instanceof Error ? error.message : 'Failed to update player',
        isLoading: false,
      });
      throw error;
    }
  },

  deletePlayer: async (playerId: string) => {
    try {
      set({ isLoading: true, error: null });
      await playerApi.deletePlayer(playerId);
      set((state) => ({
        players: state.players.filter((p) => p.playerId !== playerId),
        isLoading: false,
      }));
    } catch (error) {
      console.error('[PlayerStore] Failed to delete player:', error);
      set({
        error: error instanceof Error ? error.message : 'Failed to delete player',
        isLoading: false,
      });
      throw error;
    }
  },

  setPlayers: (players: Player[]) => {
    set({ players });
  },

  setLoading: (isLoading: boolean) => {
    set({ isLoading });
  },

  setError: (error: string | null) => {
    set({ error });
  },

  reset: () => {
    set(initialState);
  },
}));
