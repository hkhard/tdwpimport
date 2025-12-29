/**
 * Level Progression Service
 * Manages automatic blind level progression when timer expires
 *
 * Constitution Requirements:
 * - US1-A4: Automatic blind level progression within 0.5 seconds
 * - US1-A5: 10Hz (tenths-of-second) display update
 */

import type { BlindLevel, TimerState } from '@shared/types';
import { LocalTimerEngine } from './LocalTimerEngine';
import { TimerPersistence } from './TimerPersistence';

export interface LevelProgressionConfig {
  /** Tournament ID */
  tournamentId: string;
  /** Blind schedule for the tournament */
  blindSchedule: BlindLevel[];
  /** Callback when level changes */
  onLevelChange?: (newLevel: number, previousLevel: number) => void;
  /** Callback to sync with server */
  syncWithServer?: (level: number) => Promise<void>;
}

/**
 * Level Progression Service
 *
 * Manages automatic transitions between blind levels:
 * - Triggers next level when current level timer expires
 * - Handles break levels
 * - Syncs with server for authority
 * - Can be overridden by director
 */
export class LevelProgressionService {
  private config: LevelProgressionConfig;
  private currentLevel: number = 1;
  private isProcessingLevelChange: boolean = false;

  constructor(config: LevelProgressionConfig) {
    this.config = config;
    this.currentLevel = 1;
  }

  /**
   * Get current blind level
   */
  public getCurrentLevel(): number {
    return this.currentLevel;
  }

  /**
   * Get blind level info
   */
  public getBlindLevel(level: number): BlindLevel | undefined {
    return this.config.blindSchedule.find((bl) => bl.level === level);
  }

  /**
   * Get next blind level
   */
  public getNextLevel(): number | null {
    const currentIndex = this.config.blindSchedule.findIndex(
      (bl) => bl.level === this.currentLevel
    );

    if (currentIndex >= 0 && currentIndex < this.config.blindSchedule.length - 1) {
      return this.config.blindSchedule[currentIndex + 1].level;
    }

    return null; // No more levels
  }

  /**
   * Check if current level is a break
   */
  public isBreakLevel(): boolean {
    const level = this.getBlindLevel(this.currentLevel);
    return level?.isBreak || false;
  }

  /**
   * Progress to next level
   */
  public async progressToNextLevel(): Promise<void> {
    if (this.isProcessingLevelChange) {
      console.log('[LevelProgression] Already processing level change, skipping');
      return;
    }

    const nextLevel = this.getNextLevel();
    if (!nextLevel) {
      console.log('[LevelProgression] No more levels to progress to');
      return;
    }

    this.isProcessingLevelChange = true;

    const previousLevel = this.currentLevel;
    this.currentLevel = nextLevel;

    const blindInfo = this.getBlindLevel(nextLevel);
    console.log(
      `[LevelProgression] Progressing: ${previousLevel} -> ${nextLevel}${blindInfo?.isBreak ? ' (BREAK)' : ''}`
    );

    // Notify callback
    if (this.config.onLevelChange) {
      this.config.onLevelChange(nextLevel, previousLevel);
    }

    // Sync with server
    if (this.config.syncWithServer) {
      try {
        await this.config.syncWithServer(nextLevel);
      } catch (error) {
        console.error('[LevelProgression] Failed to sync with server:', error);
      }
    }

    this.isProcessingLevelChange = false;
  }

  /**
   * Jump to specific level (director override)
   */
  public async setLevel(level: number): Promise<void> {
    const blindInfo = this.getBlindLevel(level);
    if (!blindInfo) {
      throw new Error(`Level ${level} not found in blind schedule`);
    }

    const previousLevel = this.currentLevel;
    this.currentLevel = level;

    console.log(
      `[LevelProgression] Director override: ${previousLevel} -> ${level}${blindInfo.isBreak ? ' (BREAK)' : ''}`
    );

    // Notify callback
    if (this.config.onLevelChange) {
      this.config.onLevelChange(level, previousLevel);
    }

    // Sync with server
    if (this.config.syncWithServer) {
      try {
        await this.config.syncWithServer(level);
      } catch (error) {
        console.error('[LevelProgression] Failed to sync with server:', error);
      }
    }
  }

  /**
   * Check if timer should trigger level transition
   * Returns true if level should progress
   */
  public shouldProgressLevel(state: TimerState): boolean {
    // Check if remaining time has expired
    if (state.remainingTime !== undefined && state.remainingTime <= 0) {
      return true;
    }

    return false;
  }

  /**
   * Get remaining time in current level (ms)
   */
  public getRemainingTime(state: TimerState): number {
    if (state.remainingTime !== undefined) {
      return Math.max(0, state.remainingTime);
    }

    const blindInfo = this.getBlindLevel(state.level);
    if (blindInfo) {
      const levelDuration = blindInfo.duration * 60 * 1000; // Convert minutes to ms
      const elapsedTimeInLevel = state.elapsedTime % levelDuration;
      return Math.max(0, levelDuration - elapsedTimeInLevel);
    }

    return 0;
  }

  /**
   * Get progress percentage through current level
   */
  public getLevelProgress(state: TimerState): number {
    const blindInfo = this.getBlindLevel(state.level);
    if (!blindInfo) {
      return 0;
    }

    const levelDuration = blindInfo.duration * 60 * 1000;

    if (state.remainingTime !== undefined) {
      return 1 - state.remainingTime / levelDuration;
    }

    const elapsedTimeInLevel = state.elapsedTime % levelDuration;
    return elapsedTimeInLevel / levelDuration;
  }

  /**
   * Calculate estimated time until next break
   */
  public getTimeUntilNextBreak(state: TimerState): number {
    let timeUntilBreak = 0;
    let currentLevelCheck = state.level;

    while (currentLevelCheck <= this.config.blindSchedule.length) {
      const blindInfo = this.getBlindLevel(currentLevelCheck);

      if (!blindInfo) {
        break;
      }

      if (blindInfo.isBreak) {
        return timeUntilBreak;
      }

      timeUntilBreak += blindInfo.duration * 60 * 1000;

      if (state.remainingTime !== undefined && currentLevelCheck === state.level) {
        timeUntilBreak -= state.remainingTime;
      } else {
        timeUntilBreak -= blindInfo.duration * 60 * 1000;
      }

      currentLevelCheck++;
    }

    return -1; // No more breaks
  }

  /**
   * Reset to first level
   */
  public reset(): void {
    this.currentLevel = 1;
    this.isProcessingLevelChange = false;
  }

  /**
   * Update blind schedule
   */
  public updateBlindSchedule(newSchedule: BlindLevel[]): void {
    this.config.blindSchedule = newSchedule;

    // Validate current level is still in schedule
    const levelExists = this.config.blindSchedule.some(
      (bl) => bl.level === this.currentLevel
    );

    if (!levelExists) {
      this.currentLevel = this.config.blindSchedule[0]?.level || 1;
    }
  }
}

/**
 * Create a level progression service instance
 */
export function createLevelProgressionService(
  config: LevelProgressionConfig
): LevelProgressionService {
  return new LevelProgressionService(config);
}
