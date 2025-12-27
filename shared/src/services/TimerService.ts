/**
 * Timer Service Interface
 * Defines the contract for tournament timer operations across mobile and controller
 */

import type { TimerState, BlindLevel } from '../types';

export interface TimerService {
  /**
   * Start the timer for a tournament
   * @param tournamentId - ID of the tournament
   * @returns Promise resolving to the new timer state
   */
  start(tournamentId: string): Promise<TimerState>;

  /**
   * Pause the timer
   * @param tournamentId - ID of the tournament
   * @returns Promise resolving to the updated timer state
   */
  pause(tournamentId: string): Promise<TimerState>;

  /**
   * Resume the timer from paused state
   * @param tournamentId - ID of the tournament
   * @returns Promise resolving to the updated timer state
   */
  resume(tournamentId: string): Promise<TimerState>;

  /**
   * Get the current timer state
   * @param tournamentId - ID of the tournament
   * @returns Promise resolving to the current timer state
   */
  getState(tournamentId: string): Promise<TimerState | null>;

  /**
   * Update to the next blind level
   * @param tournamentId - ID of the tournament
   * @returns Promise resolving to the updated timer state
   */
  nextLevel(tournamentId: string): Promise<TimerState>;

  /**
   * Set a specific blind level
   * @param tournamentId - ID of the tournament
   * @param level - Level number to set
   * @returns Promise resolving to the updated timer state
   */
  setLevel(tournamentId: string, level: number): Promise<TimerState>;

  /**
   * Adjust timer (add/remove time)
   * @param tournamentId - ID of the tournament
   * @param milliseconds - Milliseconds to add (positive) or remove (negative)
   * @returns Promise resolving to the updated timer state
   */
  adjustTime(tournamentId: string, milliseconds: number): Promise<TimerState>;

  /**
   * Subscribe to timer state updates
   * @param tournamentId - ID of the tournament
   * @param callback - Function to call when timer state changes
   * @returns Unsubscribe function
   */
  subscribe(
    tournamentId: string,
    callback: (state: TimerState) => void
  ): () => void;
}

/**
 * Timer configuration options
 */
export interface TimerConfig {
  /** Initial blind level (default: 1) */
  startingLevel?: number;
  /** Blind schedule ID to use */
  blindScheduleId?: string;
  /** Custom blind levels (if not using schedule) */
  customLevels?: BlindLevel[];
  /** Starting stack size */
  startingStack?: number;
}

/**
 * Timer event for state change tracking
 */
export interface TimerChangeEvent {
  tournamentId: string;
  eventType: 'start' | 'pause' | 'resume' | 'level_change' | 'time_adjust';
  previousState: TimerState | null;
  newState: TimerState;
  timestamp: Date;
  deviceId?: string;
}
