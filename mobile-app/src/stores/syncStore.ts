/**
 * Sync store using Zustand
 * Manages sync status, offline queue, and conflicts
 */

import { create } from 'zustand';
import type { SyncStatus, Change, Conflict } from '@shared/types/sync';

export interface SyncStoreState {
  // Sync state
  syncStatus: SyncStatus;
  isOnline: boolean;
  lastSyncTime: Date | null;
  pendingChanges: number;
  conflicts: Conflict[];

  // Actions
  setSyncStatus: (status: SyncStatus) => void;
  setOnlineStatus: (online: boolean) => void;
  setLastSyncTime: (time: Date) => void;
  addPendingChange: (change: Change) => void;
  removePendingChange: (changeId: string) => void;
  setPendingChanges: (count: number) => void;
  addConflict: (conflict: Conflict) => void;
  resolveConflict: (conflictId: string) => void;
  clearConflicts: () => void;
  reset: () => void;
}

const initialState = {
  syncStatus: 'idle' as SyncStatus,
  isOnline: true,
  lastSyncTime: null,
  pendingChanges: 0,
  conflicts: [],
};

export const useSyncStore = create<SyncStoreState>((set, get) => ({
  ...initialState,

  setSyncStatus: (status: SyncStatus) => {
    set({ syncStatus: status });
  },

  setOnlineStatus: (online: boolean) => {
    set({ isOnline: online });

    // Auto-sync when coming back online
    if (online && get().pendingChanges > 0) {
      set({ syncStatus: 'pending' });
    }
  },

  setLastSyncTime: (time: Date) => {
    set({ lastSyncTime: time });
  },

  addPendingChange: (change: Change) => {
    set((state) => ({
      pendingChanges: state.pendingChanges + 1,
    }));

    // Trigger sync if online
    if (get().isOnline) {
      set({ syncStatus: 'pending' });
    }
  },

  removePendingChange: (changeId: string) => {
    set((state) => ({
      pendingChanges: Math.max(0, state.pendingChanges - 1),
    }));
  },

  setPendingChanges: (count: number) => {
    set({ pendingChanges: count });
  },

  addConflict: (conflict: Conflict) => {
    set((state) => ({
      conflicts: [...state.conflicts, conflict],
    }));
  },

  resolveConflict: (conflictId: string) => {
    set((state) => ({
      conflicts: state.conflicts.filter((c) => c.conflictId !== conflictId),
    }));
  },

  clearConflicts: () => {
    set({ conflicts: [] });
  },

  reset: () => {
    set(initialState);
  },
}));
