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
import { blindScheduleApi } from '../services/api/blindScheduleApi';

interface BlindScheduleState {
  // State
  schedules: BlindScheduleListItem[];
  selectedSchedule: BlindScheduleWithMetadata | null;
  isLoading: boolean;
  error: string | null;
  lastFetch: number | null;

  // Actions
  fetchSchedules: (includeDefaults?: boolean) => Promise<void>;
  fetchSchedule: (id: string) => Promise<void>;
  selectSchedule: (schedule: BlindScheduleWithMetadata | null) => void;
  createSchedule: (formData: BlindScheduleFormData) => Promise<BlindScheduleWithMetadata>;
  updateSchedule: (id: string, formData: Partial<BlindScheduleFormData>) => Promise<void>;
  deleteSchedule: (id: string) => Promise<void>;
  clearError: () => void;
  clearSelectedSchedule: () => void;
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

      // Create new schedule
      createSchedule: async (formData: BlindScheduleFormData) => {
        set({ isLoading: true, error: null });
        try {
          const newSchedule = await blindScheduleApi.createBlindSchedule(formData);

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
        set({ isLoading: true, error: null });
        try {
          await blindScheduleApi.deleteBlindSchedule(id);

          // Remove from local state
          set((state) => ({
            schedules: state.schedules.filter((s) => s.id !== id),
            selectedSchedule: state.selectedSchedule?.id === id ? null : state.selectedSchedule,
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

      // Clear error
      clearError: () => {
        set({ error: null });
      },

      // Clear selected schedule
      clearSelectedSchedule: () => {
        set({ selectedSchedule: null });
      },
    }),
    {
      name: 'blind-schedule-storage',
      storage: createJSONStorage(() => AsyncStorage),
    }
  )
);
