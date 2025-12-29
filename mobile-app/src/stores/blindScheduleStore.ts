/**
 * Blind Schedule Store
 * Zustand store for blind schedule state management
 */

import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';
import type {
  BlindScheduleWithMetadata,
  BlindScheduleListItem,
  BlindScheduleFormData,
} from '../types/blindSchedule';
import type {
  CreateBlindSchemeInput,
  UpdateBlindSchemeInput,
  BlindSchemeSyncQueueItem,
  CreateBlindLevelInput,
} from '@shared/types/timer';
import { blindScheduleApi } from '../services/api/blindScheduleApi';

interface BlindScheduleState {
  // State
  schedules: BlindScheduleListItem[];
  selectedSchedule: BlindScheduleWithMetadata | null;
  isLoading: boolean;
  error: string | null;
  lastFetch: number | null;
  syncQueue: BlindSchemeSyncQueueItem[];

  // Actions
  fetchSchedules: (includeDefaults?: boolean) => Promise<void>;
  fetchSchedule: (id: string) => Promise<void>;
  selectSchedule: (schedule: BlindScheduleWithMetadata | null) => void;
  createSchedule: (formData: BlindScheduleFormData) => Promise<BlindScheduleWithMetadata>;
  createScheme: (input: CreateBlindSchemeInput) => Promise<BlindScheduleWithMetadata>;
  updateSchedule: (id: string, formData: Partial<BlindScheduleFormData>) => Promise<void>;
  updateScheme: (id: string, input: UpdateBlindSchemeInput) => Promise<void>;
  deleteSchedule: (id: string) => Promise<void>;
  duplicateScheme: (id: string, newName?: string) => Promise<BlindScheduleWithMetadata>;
  clearError: () => void;
  clearSelectedSchedule: () => void;
  addToSyncQueue: (item: BlindSchemeSyncQueueItem) => void;
  flushSyncQueue: () => Promise<void>;
}

export const useBlindScheduleStore = create<BlindScheduleState>()(
  persist(
    (set, get) => ({
      // Initial state
      schedules: [],
      selectedSchedule: null,
      isLoading: false,
      error: null,
      lastFetch: null,
      syncQueue: [],

      // Fetch all schedules
      fetchSchedules: async (includeDefaults = true) => {
        set({ isLoading: true, error: null });
        try {
          const schedules = await blindScheduleApi.getAllBlindSchedules(includeDefaults);
          set({ schedules, isLoading: false, lastFetch: Date.now() });
        } catch (error) {
          set({
            error: (error as Error).message,
            isLoading: false,
          });
        }
      },

      // Fetch single schedule
      fetchSchedule: async (id: string) => {
        set({ isLoading: true, error: null });
        try {
          const schedule = await blindScheduleApi.getBlindSchedule(id);
          set({ selectedSchedule: schedule, isLoading: false });
        } catch (error) {
          set({
            error: (error as Error).message,
            isLoading: false,
          });
        }
      },

      // Select a schedule (for tournament setup)
      selectSchedule: (schedule: BlindScheduleWithMetadata | null) => {
        set({ selectedSchedule: schedule });
      },

      // Create new schedule (uses shared CreateBlindSchemeInput type)
      createSchedule: async (input: CreateBlindSchemeInput) => {
        set({ isLoading: true, error: null });
        try {
          const newSchedule = await blindScheduleApi.createScheme(input);

          // Add to local state
          set((state) => ({
            schedules: [...state.schedules, {
              id: newSchedule.id,
              name: newSchedule.name,
              description: newSchedule.description,
              startingStack: newSchedule.startingStack,
              levelCount: newSchedule.levelCount,
              totalDurationMinutes: newSchedule.totalDurationMinutes,
              isDefault: newSchedule.isDefault,
            }],
            selectedSchedule: newSchedule,
            isLoading: false,
          }));

          return newSchedule;
        } catch (error) {
          set({
            error: (error as Error).message,
            isLoading: false,
          });
          throw error;
        }
      },

      // Update existing schedule
      updateSchedule: async (id: string, formData: Partial<BlindScheduleFormData>) => {
        set({ isLoading: true, error: null });
        try {
          const updatedSchedule = await blindScheduleApi.updateBlindSchedule(id, formData);

          // Update in local state
          set((state) => ({
            schedules: state.schedules.map((s) =>
              s.id === id
                ? {
                    id: updatedSchedule.id,
                    name: updatedSchedule.name,
                    description: updatedSchedule.description,
                    startingStack: updatedSchedule.startingStack,
                    levelCount: updatedSchedule.levelCount,
                    totalDurationMinutes: updatedSchedule.totalDurationMinutes,
                    isDefault: updatedSchedule.isDefault,
                  }
                : s
            ),
            selectedSchedule:
              state.selectedSchedule?.id === id ? updatedSchedule : state.selectedSchedule,
            isLoading: false,
          }));
        } catch (error) {
          set({
            error: (error as Error).message,
            isLoading: false,
          });
          throw error;
        }
      },

      // Delete schedule
      deleteSchedule: async (id: string) => {
        // Optimistic update: remove from local state immediately
        const previousSchedules = get().schedules;
        const previousSelected = get().selectedSchedule;

        // Remove from local state optimistically
        set((state) => ({
          schedules: state.schedules.filter((s) => s.id !== id),
          selectedSchedule: state.selectedSchedule?.id === id ? null : state.selectedSchedule,
        }));

        try {
          await blindScheduleApi.deleteBlindSchedule(id);

          // Add to sync queue for offline support
          get().addToSyncQueue({
            type: 'DELETE_SCHEME',
            schemeId: id,
            timestamp: Date.now(),
          });
        } catch (error) {
          // Rollback on error
          set({
            schedules: previousSchedules,
            selectedSchedule: previousSelected,
            error: (error as Error).message,
          });
          throw error;
        }
      },

      // Clear error
      clearError: () => {
        set({ error: null });
      },

      // Clear selected schedule
      clearSelectedSchedule: () => {
        set({ selectedSchedule: null });
      },

      // Create new scheme using shared types
      createScheme: async (input: CreateBlindSchemeInput) => {
        set({ isLoading: true, error: null });
        try {
          const newSchedule = await blindScheduleApi.createScheme(input);

          // Add to local state
          set((state) => ({
            schedules: [...state.schedules, {
              id: newSchedule.id,
              name: newSchedule.name,
              description: newSchedule.description,
              startingStack: newSchedule.startingStack,
              levelCount: newSchedule.levelCount,
              totalDurationMinutes: newSchedule.totalDurationMinutes,
              isDefault: newSchedule.isDefault,
            }],
            selectedSchedule: newSchedule,
            isLoading: false,
          }));

          // Add to sync queue for offline support
          get().addToSyncQueue({
            type: 'CREATE_SCHEME',
            schemeId: newSchedule.id,
            data: input,
            timestamp: Date.now(),
          });

          return newSchedule;
        } catch (error) {
          set({
            error: (error as Error).message,
            isLoading: false,
          });
          throw error;
        }
      },

      // Update scheme using shared types
      updateScheme: async (id: string, input: UpdateBlindSchemeInput) => {
        set({ isLoading: true, error: null });
        try {
          const updatedSchedule = await blindScheduleApi.updateScheme(id, input);

          // Update in local state
          set((state) => ({
            schedules: state.schedules.map((s) =>
              s.id === id
                ? {
                    id: updatedSchedule.id,
                    name: updatedSchedule.name,
                    description: updatedSchedule.description,
                    startingStack: updatedSchedule.startingStack,
                    levelCount: updatedSchedule.levelCount,
                    totalDurationMinutes: updatedSchedule.totalDurationMinutes,
                    isDefault: updatedSchedule.isDefault,
                  }
                : s
            ),
            selectedSchedule:
              state.selectedSchedule?.id === id ? updatedSchedule : state.selectedSchedule,
            isLoading: false,
          }));

          // Add to sync queue for offline support
          get().addToSyncQueue({
            type: 'UPDATE_SCHEME',
            schemeId: id,
            data: input,
            timestamp: Date.now(),
          });
        } catch (error) {
          set({
            error: (error as Error).message,
            isLoading: false,
          });
          throw error;
        }
      },

      // Duplicate scheme (used for editing defaults)
      duplicateScheme: async (id: string, newName?: string) => {
        set({ isLoading: true, error: null });
        try {
          const duplicatedSchedule = await blindScheduleApi.duplicateScheme(id, newName);

          // Add to local state
          set((state) => ({
            schedules: [...state.schedules, {
              id: duplicatedSchedule.id,
              name: duplicatedSchedule.name,
              description: duplicatedSchedule.description,
              startingStack: duplicatedSchedule.startingStack,
              levelCount: duplicatedSchedule.levelCount,
              totalDurationMinutes: duplicatedSchedule.totalDurationMinutes,
              isDefault: duplicatedSchedule.isDefault,
            }],
            selectedSchedule: duplicatedSchedule,
            isLoading: false,
          }));

          return duplicatedSchedule;
        } catch (error) {
          set({
            error: (error as Error).message,
            isLoading: false,
          });
          throw error;
        }
      },

      // Add operation to sync queue
      addToSyncQueue: (item: BlindSchemeSyncQueueItem) => {
        set((state) => ({
          syncQueue: [...state.syncQueue, item],
        }));
      },

      // Flush sync queue (called when back online)
      flushSyncQueue: async () => {
        const state = get();
        const { syncQueue } = state;

        if (syncQueue.length === 0) {
          return;
        }

        set({ isLoading: true, error: null });

        try {
          // Process queue in order
          for (const item of syncQueue) {
            try {
              switch (item.type) {
                case 'CREATE_SCHEME':
                  if (item.data) {
                    await blindScheduleApi.createScheme(item.data as CreateBlindSchemeInput);
                  }
                  break;
                case 'UPDATE_SCHEME':
                  if (item.data) {
                    await blindScheduleApi.updateScheme(item.schemeId, item.data as UpdateBlindSchemeInput);
                  }
                  break;
                case 'DELETE_SCHEME':
                  await blindScheduleApi.deleteScheme(item.schemeId);
                  break;
              }
            } catch (err) {
              console.error(`[SyncQueue] Failed to process ${item.type} for scheme ${item.schemeId}:`, err);
            }
          }

          // Clear queue after processing
          set({ syncQueue: [], isLoading: false });
        } catch (error) {
          set({
            error: (error as Error).message,
            isLoading: false,
          });
        }
      },
    }),
    {
      name: 'blind-schedule-storage',
      storage: createJSONStorage(() => AsyncStorage),
    }
  )
);
