/**
 * Server-Side Timer Service
 * Authoritative timer state management for tournaments
 *
 * Constitution Requirements:
 * - US1-A1: <1s drift over 8 hours
 * - US5-A2: <1s recovery after restart
 * - US5-A3: Automatic failover detection
 * - US5-A4: <5s standby takeover
 */

import type { TimerState, BlindLevel, TimerEventType } from '@shared/types';
import { TournamentRepository } from '../db/repositories/TournamentRepository';
import { TimerEventRepository } from '../db/repositories/TimerEventRepository';
import type { Database } from 'better-sqlite3';

export interface TimerServiceConfig {
  /** Database connection */
  db: Database;
  /** Timer update interval (ms) - should be 100ms for precision */
  updateInterval?: number;
}

export interface TimerUpdate {
  tournamentId: string;
  state: TimerState;
  timestamp: Date;
}

/**
 * Server Timer Service
 *
 * Manages authoritative timer state for all tournaments:
 * - High-precision 100ms updates
 * - Event logging for audit/replay
 * - Automatic level progression
 * - Recovery from restart
 */
export class TimerService {
  private config: Required<TimerServiceConfig>;
  private tournamentRepo: TournamentRepository;
  private timerEventRepo: TimerEventRepository;
  private activeTimers: Map<string, NodeJS.Timeout> = new Map();
  private timerStates: Map<string, TimerState> = new Map();
  private subscribers: Map<string, Set<(state: TimerState) => void>> = new Map();

  constructor(config: TimerServiceConfig) {
    this.config = {
      db: config.db,
      updateInterval: config.updateInterval || 100,
    };

    this.tournamentRepo = new TournamentRepository();
    this.timerEventRepo = new TimerEventRepository();
  }

  /**
   * Start timer for a tournament
   */
  async start(tournamentId: string, deviceId?: string): Promise<TimerState> {
    const tournament = await this.tournamentRepo.findTournamentById(tournamentId);
    if (!tournament) {
      throw new Error('Tournament not found');
    }

    // Get current state or initialize
    let state = this.timerStates.get(tournamentId) || tournament.timerState;

    const previousState = { ...state };

    state.isRunning = true;
    state.isPaused = false;
    state.lastUpdateTime = new Date();

    // Save to database and memory
    this.timerStates.set(tournamentId, state);
    await this.tournamentRepo.updateTournament(tournamentId, { timerState: state });

    // Log event
    await this.logEvent(tournamentId, 'tournament_start', previousState, state, deviceId);

    // Start timer ticker
    this.startTicker(tournamentId);

    // Notify subscribers
    this.notifySubscribers(tournamentId, state);

    return state;
  }

  /**
   * Pause timer
   */
  async pause(tournamentId: string, deviceId?: string): Promise<TimerState> {
    const state = await this.getOrLoadTimer(tournamentId);
    if (!state || !state.isRunning) {
      throw new Error('Timer is not running');
    }

    const previousState = { ...state };

    state.isPaused = true;

    // Stop ticker
    this.stopTicker(tournamentId);

    // Save to database
    await this.tournamentRepo.updateTournament(tournamentId, { timerState: state });

    // Log event
    await this.logEvent(tournamentId, 'pause', previousState, state, deviceId);

    // Notify subscribers
    this.notifySubscribers(tournamentId, state);

    return state;
  }

  /**
   * Resume from pause
   */
  async resume(tournamentId: string, deviceId?: string): Promise<TimerState> {
    const state = await this.getOrLoadTimer(tournamentId);
    if (!state || !state.isRunning) {
      throw new Error('Timer is not running');
    }

    if (!state.isPaused) {
      return state; // Already running
    }

    const previousState = { ...state };

    state.isPaused = false;
    state.lastUpdateTime = new Date();

    // Restart ticker
    this.startTicker(tournamentId);

    // Save to database
    await this.tournamentRepo.updateTournament(tournamentId, { timerState: state });

    // Log event
    await this.logEvent(tournamentId, 'resume', previousState, state, deviceId);

    // Notify subscribers
    this.notifySubscribers(tournamentId, state);

    return state;
  }

  /**
   * Get current timer state
   */
  getState(tournamentId: string): TimerState | null {
    return this.timerStates.get(tournamentId) || null;
  }

  /**
   * Get timer state from memory, or load from database if not present
   * This ensures operations work after server restart
   */
  private async getOrLoadTimer(tournamentId: string): Promise<TimerState | null> {
    // Check memory first
    let state: TimerState | null | undefined = this.timerStates.get(tournamentId);
    if (state) {
      return state;
    }

    // Not in memory - try loading from database
    state = await this.loadTimer(tournamentId);
    return state ?? null;
  }

  /**
   * Set blind level
   */
  async setLevel(tournamentId: string, level: number, deviceId?: string): Promise<TimerState> {
    const state = await this.getOrLoadTimer(tournamentId);
    if (!state) {
      throw new Error('Timer not found');
    }

    const previousState = { ...state };

    state.level = level;
    state.remainingTime = this.getLevelDuration(level);
    state.lastUpdateTime = new Date();

    // Save to database
    await this.tournamentRepo.updateTournament(tournamentId, { timerState: state });

    // Log event
    await this.logEvent(tournamentId, 'level_start', previousState, state, deviceId);

    // Notify subscribers
    this.notifySubscribers(tournamentId, state);

    return state;
  }

  /**
   * Adjust time (add/remove milliseconds)
   */
  async adjustTime(tournamentId: string, milliseconds: number, deviceId?: string): Promise<TimerState> {
    const state = await this.getOrLoadTimer(tournamentId);
    if (!state) {
      throw new Error('Timer not found');
    }

    const previousState = { ...state };

    state.elapsedTime += milliseconds;
    if (state.remainingTime !== undefined) {
      state.remainingTime -= milliseconds;
    }
    state.lastUpdateTime = new Date();

    // Save to database
    await this.tournamentRepo.updateTournament(tournamentId, { timerState: state });

    // Log event
    await this.logEvent(tournamentId, 'director_override', previousState, state, deviceId);

    // Notify subscribers
    this.notifySubscribers(tournamentId, state);

    return state;
  }

  /**
   * Subscribe to timer updates
   */
  subscribe(tournamentId: string, callback: (state: TimerState) => void): () => void {
    if (!this.subscribers.has(tournamentId)) {
      this.subscribers.set(tournamentId, new Set());
    }

    this.subscribers.get(tournamentId)!.add(callback);

    // Return unsubscribe function
    return () => {
      this.subscribers.get(tournamentId)?.delete(callback);
    };
  }

  /**
   * Load timer from database (for recovery after restart)
   */
  async loadTimer(tournamentId: string): Promise<TimerState | null> {
    const tournament = await this.tournamentRepo.findTournamentById(tournamentId);
    if (!tournament) {
      return null;
    }

    const state = tournament.timerState;

    // If timer was running, calculate elapsed time since last update
    if (state.isRunning && !state.isPaused) {
      const now = new Date();
      const elapsedSinceUpdate = now.getTime() - state.lastUpdateTime.getTime();
      state.elapsedTime += elapsedSinceUpdate;

      if (state.remainingTime !== undefined) {
        state.remainingTime -= elapsedSinceUpdate;

        // Check for level transition
        if (state.remainingTime <= 0) {
          await this.nextLevel(tournamentId);
        }
      }

      state.lastUpdateTime = now;
    }

    this.timerStates.set(tournamentId, state);

    // Restart ticker if timer was running
    if (state.isRunning && !state.isPaused) {
      this.startTicker(tournamentId);
    }

    return state;
  }

  /**
   * Stop timer and clean up resources
   */
  async stop(tournamentId: string): Promise<void> {
    const state = this.timerStates.get(tournamentId);
    if (!state) {
      return;
    }

    const previousState = { ...state };
    state.isRunning = false;

    this.stopTicker(tournamentId);
    this.timerStates.delete(tournamentId);
    this.subscribers.delete(tournamentId);

    await this.tournamentRepo.updateTournament(tournamentId, { timerState: state });

    await this.logEvent(tournamentId, 'tournament_end', previousState, state);
  }

  /**
   * Clean up all timers
   */
  destroy(): void {
    for (const tournamentId of this.activeTimers.keys()) {
      this.stopTicker(tournamentId);
    }

    this.activeTimers.clear();
    this.timerStates.clear();
    this.subscribers.clear();
  }

  /**
   * Start timer ticker for a tournament
   */
  private startTicker(tournamentId: string): void {
    // Stop existing ticker if any
    this.stopTicker(tournamentId);

    const ticker = setInterval(() => {
      this.tick(tournamentId);
    }, this.config.updateInterval);

    this.activeTimers.set(tournamentId, ticker);
  }

  /**
   * Stop timer ticker for a tournament
   */
  private stopTicker(tournamentId: string): void {
    const ticker = this.activeTimers.get(tournamentId);
    if (ticker) {
      clearInterval(ticker);
      this.activeTimers.delete(tournamentId);
    }
  }

  /**
   * Timer tick - updates state and checks for level transitions
   */
  private tick(tournamentId: string): void {
    const state = this.timerStates.get(tournamentId);
    if (!state || !state.isRunning || state.isPaused) {
      return;
    }

    const now = Date.now();
    const delta = this.config.updateInterval;

    state.elapsedTime += delta;

    if (state.remainingTime !== undefined) {
      state.remainingTime -= delta;

      // Check for level transition
      if (state.remainingTime <= 0) {
        this.nextLevel(tournamentId);
      }
    }

    // Calculate tenths digit
    state.tenths = Math.floor((state.elapsedTime % 1000) / 100);
    state.lastUpdateTime = new Date();

    // Notify subscribers
    this.notifySubscribers(tournamentId, state);

    // Persist every 10 ticks (1 second) to reduce DB writes
    if (state.elapsedTime % 1000 < this.config.updateInterval) {
      try {
        this.tournamentRepo.updateTournament(tournamentId, { timerState: state });
      } catch (err) {
        console.error('[TimerService] Failed to persist state:', err);
      }
    }
  }

  /**
   * Transition to next level
   */
  private async nextLevel(tournamentId: string): Promise<void> {
    const state = this.timerStates.get(tournamentId);
    if (!state) {
      return;
    }

    const previousLevel = state.level;
    const nextLevelNumber = await this.findNextLevel(tournamentId, previousLevel);

    if (nextLevelNumber !== null) {
      state.level = nextLevelNumber;
      state.remainingTime = this.getLevelDuration(nextLevelNumber);
      state.lastUpdateTime = new Date();

      await this.logEvent(tournamentId, 'level_start', null, state);
      await this.tournamentRepo.updateTournament(tournamentId, {
        timerState: state,
        currentBlindLevel: nextLevelNumber,
      });

      this.notifySubscribers(tournamentId, state);
    }
  }

  /**
   * Find next level in blind schedule
   */
  private async findNextLevel(tournamentId: string, currentLevel: number): Promise<number | null> {
    // TODO: Query blind schedule from database
    // For now, just increment
    return currentLevel + 1;
  }

  /**
   * Get duration for a specific level
   */
  private getLevelDuration(level: number): number {
    // TODO: Query from blind schedule
    // Default 20 minutes
    return 20 * 60 * 1000;
  }

  /**
   * Log timer event
   */
  private async logEvent(
    tournamentId: string,
    eventType: TimerEventType,
    previousState: TimerState | null,
    newState: TimerState,
    deviceId?: string
  ): Promise<void> {
    try {
      await this.timerEventRepo.insert({
        event_id: crypto.randomUUID(),
        tournament_id: tournamentId,
        timestamp: new Date().toISOString(),
        event_type: eventType,
        previous_level: previousState?.level ?? null,
        previous_elapsed_time: previousState?.elapsedTime ?? null,
        previous_is_running: previousState?.isRunning ? 1 : null,
        previous_is_paused: previousState?.isPaused ? 1 : null,
        new_level: newState.level,
        new_elapsed_time: newState.elapsedTime,
        new_is_running: newState.isRunning ? 1 : 0,
        new_is_paused: newState.isPaused ? 1 : 0,
        device_id: deviceId ?? null,
        metadata: null,
        created_at: new Date().toISOString(),
      });
    } catch (error) {
      console.error('[TimerService] Failed to log event:', error);
    }
  }

  /**
   * Notify subscribers of state change
   */
  private notifySubscribers(tournamentId: string, state: TimerState): void {
    const subscribers = this.subscribers.get(tournamentId);
    if (subscribers) {
      for (const callback of subscribers) {
        try {
          callback(state);
        } catch (error) {
          console.error('[TimerService] Subscriber callback error:', error);
        }
      }
    }
  }

  /**
   * Get all active tournament IDs
   */
  getActiveTournaments(): string[] {
    return Array.from(this.timerStates.keys());
  }
}
