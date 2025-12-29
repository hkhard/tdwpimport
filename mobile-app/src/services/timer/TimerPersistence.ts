/**
 * Timer Persistence Service
 * Handles persistence of timer state across app restarts and crashes
 *
 * Constitution Requirements:
 * - US1-A3: Timer state must survive app force-quit and device restarts
 * - Recovery time: <2 seconds after app restart
 */

import AsyncStorage from '@react-native-async-storage/async-storage';
import type { TimerState } from '@shared/types';

const STORAGE_KEY_PREFIX = '@timer_state:';
const BACKGROUND_TIME_KEY = '@background_time:';
const LAST_SYNC_KEY = '@last_sync:';

export interface PersistedTimerState extends TimerState {
  /** When this state was persisted */
  persistedAt: string;
  /** Device timezone offset */
  timezoneOffset: number;
}

export interface BackgroundTimeRecord {
  /** When app went to background */
  backgroundedAt: number;
  /** Expected return time estimate */
  expectedReturnAt?: number;
}

/**
 * Timer Persistence Service
 *
 * Manages AsyncStorage persistence for tournament timer states.
 * Automatically handles background time tracking for accuracy recovery.
 */
export class TimerPersistence {
  /**
   * Save timer state for a tournament
   */
  public static async saveState(
    tournamentId: string,
    state: TimerState
  ): Promise<void> {
    try {
      const key = STORAGE_KEY_PREFIX + tournamentId;
      const persistedState: PersistedTimerState = {
        ...state,
        persistedAt: new Date().toISOString(),
        timezoneOffset: new Date().getTimezoneOffset(),
      };

      await AsyncStorage.setItem(key, JSON.stringify(persistedState));
    } catch (error) {
      console.error('[TimerPersistence] Failed to save state:', error);
      throw new Error('Failed to save timer state');
    }
  }

  /**
   * Load timer state for a tournament
   */
  public static async loadState(
    tournamentId: string
  ): Promise<PersistedTimerState | null> {
    try {
      const key = STORAGE_KEY_PREFIX + tournamentId;
      const data = await AsyncStorage.getItem(key);

      if (!data) {
        return null;
      }

      const state = JSON.parse(data) as PersistedTimerState;

      // Validate loaded state has required fields
      if (
        typeof state.elapsedTime !== 'number' ||
        typeof state.level !== 'number' ||
        typeof state.tenths !== 'number'
      ) {
        console.warn('[TimerPersistence] Invalid state format, discarding');
        await this.deleteState(tournamentId);
        return null;
      }

      return state;
    } catch (error) {
      console.error('[TimerPersistence] Failed to load state:', error);
      return null;
    }
  }

  /**
   * Delete timer state for a tournament
   */
  public static async deleteState(tournamentId: string): Promise<void> {
    try {
      const key = STORAGE_KEY_PREFIX + tournamentId;
      await AsyncStorage.removeItem(key);
    } catch (error) {
      console.error('[TimerPersistence] Failed to delete state:', error);
    }
  }

  /**
   * Record when app goes to background
   */
  public static async recordBackgroundTime(
    tournamentId: string,
    expectedDuration?: number
  ): Promise<void> {
    try {
      const key = BACKGROUND_TIME_KEY + tournamentId;
      const record: BackgroundTimeRecord = {
        backgroundedAt: Date.now(),
        expectedReturnAt: expectedDuration
          ? Date.now() + expectedDuration
          : undefined,
      };

      await AsyncStorage.setItem(key, JSON.stringify(record));
    } catch (error) {
      console.error('[TimerPersistence] Failed to record background time:', error);
    }
  }

  /**
   * Get and clear background time record
   */
  public static async getBackgroundTime(
    tournamentId: string
  ): Promise<BackgroundTimeRecord | null> {
    try {
      const key = BACKGROUND_TIME_KEY + tournamentId;
      const data = await AsyncStorage.getItem(key);

      if (!data) {
        return null;
      }

      // Clear after reading
      await AsyncStorage.removeItem(key);

      return JSON.parse(data) as BackgroundTimeRecord;
    } catch (error) {
      console.error('[TimerPersistence] Failed to get background time:', error);
      return null;
    }
  }

  /**
   * Calculate drift correction for backgrounded app
   *
   * When app returns from background, calculate how much time passed
   * and return the elapsed time that should be added to the timer.
   */
  public static async calculateDrift(
    tournamentId: string,
    currentState: TimerState
  ): Promise<number> {
    const backgroundRecord = await this.getBackgroundTime(tournamentId);

    if (!backgroundRecord) {
      return 0;
    }

    const now = Date.now();
    const backgroundDuration = now - backgroundRecord.backgroundedAt;

    // If timer was running when backgrounded, add elapsed time
    if (currentState.isRunning && !currentState.isPaused) {
      return backgroundDuration;
    }

    return 0;
  }

  /**
   * Save last sync timestamp with server
   */
  public static async saveLastSync(
    tournamentId: string,
    timestamp: Date
  ): Promise<void> {
    try {
      const key = LAST_SYNC_KEY + tournamentId;
      await AsyncStorage.setItem(key, timestamp.toISOString());
    } catch (error) {
      console.error('[TimerPersistence] Failed to save last sync:', error);
    }
  }

  /**
   * Get last sync timestamp with server
   */
  public static async getLastSync(
    tournamentId: string
  ): Promise<Date | null> {
    try {
      const key = LAST_SYNC_KEY + tournamentId;
      const data = await AsyncStorage.getItem(key);

      return data ? new Date(data) : null;
    } catch (error) {
      console.error('[TimerPersistence] Failed to get last sync:', error);
      return null;
    }
  }

  /**
   * Clear all timer data for a tournament
   */
  public static async clearTournament(tournamentId: string): Promise<void> {
    try {
      await Promise.all([
        this.deleteState(tournamentId),
        AsyncStorage.removeItem(BACKGROUND_TIME_KEY + tournamentId),
        AsyncStorage.removeItem(LAST_SYNC_KEY + tournamentId),
      ]);
    } catch (error) {
      console.error('[TimerPersistence] Failed to clear tournament:', error);
    }
  }

  /**
   * Get all tournament IDs with persisted state
   */
  public static async getAllPersistedTournaments(): Promise<string[]> {
    try {
      const keys = await AsyncStorage.getAllKeys();
      const timerKeys = keys.filter((key) => key.startsWith(STORAGE_KEY_PREFIX));

      return timerKeys.map((key) => key.replace(STORAGE_KEY_PREFIX, ''));
    } catch (error) {
      console.error('[TimerPersistence] Failed to get tournaments:', error);
      return [];
    }
  }

  /**
   * Validate persisted state is recent enough to use
   *
   * @param state - The persisted state to validate
   * @param maxAge - Maximum age in milliseconds (default: 24 hours)
   */
  public static isValidState(
    state: PersistedTimerState,
    maxAge: number = 24 * 60 * 60 * 1000
  ): boolean {
    const now = Date.now();
    const persistedAt = new Date(state.persistedAt).getTime();
    const age = now - persistedAt;

    return age < maxAge;
  }
}

/**
 * Calculate drift correction based on elapsed wall-clock time
 *
 * This is used when app returns from background to determine
 * how much the timer should be adjusted to match real time.
 */
export function calculateBackgroundDrift(
  backgroundTime: BackgroundTimeRecord,
  timerState: TimerState
): number {
  if (!timerState.isRunning || timerState.isPaused) {
    return 0; // Timer wasn't running, no drift
  }

  const now = Date.now();
  return now - backgroundTime.backgroundedAt;
}
