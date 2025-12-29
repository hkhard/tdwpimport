/**
 * Local Timer Engine
 * Provides 100ms precision timer updates using requestAnimationFrame-based approach
 * Designed for high-accuracy tournament timer display on mobile devices
 *
 * Constitution Requirement II: Precision Timing NON-NEGOTIABLE
 * - 100ms precision for tenths-of-second display
 * - <1 second drift over 8 hours of operation
 * - Must maintain accuracy through app backgrounding
 */

import type { TimerState, BlindLevel } from '@shared/types';

export interface LocalTimerEngineConfig {
  /** Update interval in milliseconds (default: 100ms for tenths precision) */
  updateInterval?: number;
  /** Callback for timer state updates */
  onUpdate: (state: TimerState) => void;
  /** Callback for level transitions */
  onLevelChange?: (level: number, previousLevel: number) => void;
}

export interface LocalTimerState {
  isRunning: boolean;
  isPaused: boolean;
  level: number;
  elapsedTime: number; // milliseconds
  remainingTime?: number; // milliseconds for current level
  tenths: number; // 0-9
  levelDuration?: number; // milliseconds for current level
  lastUpdateTime: number; // monotonic timestamp
  pausedAt?: number; // when timer was paused
}

/**
 * Local Timer Engine
 *
 * Uses performance.now() for monotonic timing unaffected by system clock changes
 * Updates at 100ms intervals for tenths-of-second precision
 * Automatically calculates tenths digit for smooth display
 */
export class LocalTimerEngine {
  private config: Required<LocalTimerEngineConfig>;
  private state: LocalTimerState;
  private animationFrameId: number | null = null;
  private intervalId: number | null = null;
  private blindSchedule: BlindLevel[] = [];
  private tournamentId: string;

  constructor(tournamentId: string, config: LocalTimerEngineConfig) {
    this.tournamentId = tournamentId;
    this.config = {
      updateInterval: config.updateInterval || 100,
      onUpdate: config.onUpdate,
      onLevelChange: config.onLevelChange || (() => {}),
    };

    this.state = this.getInitialState();
  }

  /**
   * Initialize timer state
   */
  private getInitialState(): LocalTimerState {
    return {
      isRunning: false,
      isPaused: false,
      level: 1,
      elapsedTime: 0,
      tenths: 0,
      lastUpdateTime: performance.now(),
    };
  }

  /**
   * Set the blind schedule for level progression
   */
  public setBlindSchedule(levels: BlindLevel[]): void {
    this.blindSchedule = levels;
    this.updateLevelDuration();
  }

  /**
   * Start or resume the timer
   */
  public start(): void {
    if (this.state.isRunning && !this.state.isPaused) {
      return; // Already running
    }

    this.state.isRunning = true;
    this.state.isPaused = false;
    this.state.lastUpdateTime = performance.now();

    this.startTicker();
    this.notifyUpdate();
  }

  /**
   * Pause the timer
   */
  public pause(): void {
    if (!this.state.isRunning || this.state.isPaused) {
      return;
    }

    this.state.isPaused = true;
    this.state.pausedAt = performance.now();

    this.stopTicker();
    this.notifyUpdate();
  }

  /**
   * Resume from pause
   */
  public resume(): void {
    if (!this.state.isRunning || !this.state.isPaused) {
      return;
    }

    // Adjust lastUpdateTime to account for pause duration
    if (this.state.pausedAt) {
      const pauseDuration = performance.now() - this.state.pausedAt;
      this.state.lastUpdateTime += pauseDuration;
      this.state.pausedAt = undefined;
    }

    this.state.isPaused = false;
    this.startTicker();
    this.notifyUpdate();
  }

  /**
   * Reset the timer
   */
  public reset(): void {
    this.stopTicker();
    this.state = this.getInitialState();
    this.notifyUpdate();
  }

  /**
   * Get current timer state
   */
  public getState(): LocalTimerState {
    return { ...this.state };
  }

  /**
   * Set current level
   */
  public setLevel(level: number): void {
    const previousLevel = this.state.level;
    this.state.level = level;
    this.state.remainingTime = this.getLevelDuration(level);
    this.updateLevelDuration();

    if (previousLevel !== level && this.config.onLevelChange) {
      this.config.onLevelChange(level, previousLevel);
    }

    this.notifyUpdate();
  }

  /**
   * Adjust time (add or remove milliseconds)
   */
  public adjustTime(milliseconds: number): void {
    this.state.elapsedTime += milliseconds;
    if (this.state.remainingTime !== undefined) {
      this.state.remainingTime -= milliseconds;
    }
    this.notifyUpdate();
  }

  /**
   * Set timer state (used for sync from server)
   */
  public setState(state: TimerState): void {
    const wasRunning = this.state.isRunning;
    const wasPaused = this.state.isPaused;

    this.state.isRunning = state.isRunning;
    this.state.isPaused = state.isPaused;
    this.state.level = state.level;
    this.state.elapsedTime = state.elapsedTime;
    this.state.remainingTime = state.remainingTime;
    this.state.tenths = Math.floor((state.elapsedTime % 1000) / 100);
    this.state.lastUpdateTime = performance.now();
    this.updateLevelDuration();

    // Restart ticker if needed
    if (this.state.isRunning && !this.state.isPaused) {
      if (!wasRunning || wasPaused) {
        this.startTicker();
      }
    } else if (wasRunning && !wasPaused) {
      this.stopTicker();
    }

    this.notifyUpdate();
  }

  /**
   * Clean up resources
   */
  public destroy(): void {
    this.stopTicker();
  }

  /**
   * Start the ticker loop
   */
  private startTicker(): void {
    if (this.animationFrameId !== null || this.intervalId !== null) {
      return;
    }

    // Use setInterval for consistent 100ms updates
    this.intervalId = setInterval(() => {
      this.tick();
    }, this.config.updateInterval) as unknown as number;
  }

  /**
   * Stop the ticker loop
   */
  private stopTicker(): void {
    if (this.intervalId !== null) {
      clearInterval(this.intervalId);
      this.intervalId = null;
    }
    if (this.animationFrameId !== null) {
      cancelAnimationFrame(this.animationFrameId);
      this.animationFrameId = null;
    }
  }

  /**
   * Timer tick - updates state and checks for level transitions
   */
  private tick(): void {
    if (!this.state.isRunning || this.state.isPaused) {
      return;
    }

    const now = performance.now();
    const delta = now - this.state.lastUpdateTime;
    this.state.lastUpdateTime = now;

    this.state.elapsedTime += delta;

    if (this.state.remainingTime !== undefined) {
      this.state.remainingTime -= delta;

      // Check for level transition
      if (this.state.remainingTime <= 0) {
        this.nextLevel();
      }
    }

    // Calculate tenths digit (0-9)
    this.state.tenths = Math.floor((this.state.elapsedTime % 1000) / 100);

    this.notifyUpdate();
  }

  /**
   * Transition to next level
   */
  private nextLevel(): void {
    const previousLevel = this.state.level;
    const nextLevelNumber = this.findNextLevel();

    if (nextLevelNumber !== null) {
      this.state.level = nextLevelNumber;
      this.state.remainingTime = this.getLevelDuration(nextLevelNumber);
      this.updateLevelDuration();

      if (this.config.onLevelChange) {
        this.config.onLevelChange(nextLevelNumber, previousLevel);
      }
    }
  }

  /**
   * Find next level in schedule
   */
  private findNextLevel(): number | null {
    const currentIndex = this.blindSchedule.findIndex(
      (level) => level.level === this.state.level
    );

    if (currentIndex >= 0 && currentIndex < this.blindSchedule.length - 1) {
      return this.blindSchedule[currentIndex + 1].level;
    }

    return null; // No more levels
  }

  /**
   * Get duration for a specific level
   */
  private getLevelDuration(level: number): number {
    const blindLevel = this.blindSchedule.find((bl) => bl.level === level);
    return blindLevel ? blindLevel.duration * 60 * 1000 : 20 * 60 * 1000; // Default 20 minutes
  }

  /**
   * Update current level duration
   */
  private updateLevelDuration(): void {
    this.state.levelDuration = this.getLevelDuration(this.state.level);

    if (this.state.remainingTime === undefined) {
      this.state.remainingTime = this.state.levelDuration;
    }
  }

  /**
   * Notify callback of state change
   */
  private notifyUpdate(): void {
    const publicState: TimerState = {
      isRunning: this.state.isRunning,
      isPaused: this.state.isPaused,
      level: this.state.level,
      elapsedTime: this.state.elapsedTime,
      remainingTime: this.state.remainingTime,
      tenths: this.state.tenths,
      lastUpdateTime: new Date(),
    };

    this.config.onUpdate(publicState);
  }
}

/**
 * Create a local timer engine instance
 */
export function createLocalTimerEngine(
  tournamentId: string,
  config: LocalTimerEngineConfig
): LocalTimerEngine {
  return new LocalTimerEngine(tournamentId, config);
}
