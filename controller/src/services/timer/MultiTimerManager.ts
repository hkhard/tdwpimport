/**
 * Multi-Tournament Timer Manager
 * Manages 100+ concurrent tournament timers with resource optimization
 *
 * Constitution Requirements:
 * - US5-A1: 100+ concurrent tournaments
 * - US5-A2: <1s recovery after restart
 * - US5-A3: Automatic failover detection
 * - US5-A4: <5s standby takeover
 */

import type { TimerState, BlindLevel } from '@shared/types';
import type { Database } from 'better-sqlite3';
import { TournamentRepository } from '../../db/repositories/TournamentRepository';
import { TimerEventRepository } from '../../db/repositories/TimerEventRepository';

export interface MultiTimerConfig {
  /** Database connection */
  db: Database;
  /** Timer update interval (ms) */
  updateInterval?: number;
  /** Max concurrent timers */
  maxConcurrentTimers?: number;
  /** Health check interval (ms) */
  healthCheckInterval?: number;
  /** Persist state interval (ms) - reduce DB writes */
  persistInterval?: number;
}

export interface TournamentTimerContext {
  /** Tournament ID */
  tournamentId: string;
  /** Current timer state */
  state: TimerState;
  /** Blind schedule for tournament */
  blindSchedule: BlindLevel[];
  /** Device ID controlling this timer */
  controllerDeviceId?: string;
  /** Last health check timestamp */
  lastHealthCheck: Date;
  /** Last persist timestamp */
  lastPersist: Date;
  /** Error count */
  errorCount: number;
  /** Whether timer is healthy */
  isHealthy: boolean;
}

export type HealthStatus = 'healthy' | 'degraded' | 'unhealthy';

/**
 * Multi-Tournament Timer Manager
 *
 * Manages hundreds of concurrent tournament timers:
 * - Efficient state management with batching
 * - Health monitoring for each timer
 * - Automatic recovery from failures
 * - Resource pooling for database writes
 * - Graceful degradation under load
 */
export class MultiTimerManager {
  private config: Required<MultiTimerConfig>;
  private tournamentRepo: TournamentRepository;
  private timerEventRepo: TimerEventRepository;

  /** Map of tournament ID to timer context */
  private timers: Map<string, TournamentTimerContext> = new Map();

  /** Active interval timers per tournament */
  private tickers: Map<string, NodeJS.Timeout> = new Map();

  /** Subscribers per tournament */
  private subscribers: Map<string, Set<(state: TimerState) => void>> = new Map();

  /** Health check interval */
  private healthCheckInterval: NodeJS.Timeout | null = null;

  /** Batch persist queue */
  private persistQueue: Set<string> = new Set();

  /** Batch persist interval */
  private batchPersistInterval: NodeJS.Timeout | null = null;

  /** Statistics */
  private stats = {
    totalTimers: 0,
    activeTimers: 0,
    pausedTimers: 0,
    unhealthyTimers: 0,
    totalTicks: 0,
    totalErrors: 0,
  };

  constructor(config: MultiTimerConfig) {
    this.config = {
      db: config.db,
      updateInterval: config.updateInterval || 100,
      maxConcurrentTimers: config.maxConcurrentTimers || 100,
      healthCheckInterval: config.healthCheckInterval || 30000, // 30 seconds
      persistInterval: config.persistInterval || 1000, // 1 second
    };

    this.tournamentRepo = new TournamentRepository();
    this.timerEventRepo = new TimerEventRepository();

    // Start health checks
    this.startHealthChecks();

    // Start batch persist
    this.startBatchPersist();
  }

  /**
   * Initialize tournament timer
   */
  async initializeTimer(
    tournamentId: string,
    blindSchedule: BlindLevel[],
    initialState?: Partial<TimerState>
  ): Promise<TimerState> {
    // Check capacity
    if (this.timers.size >= this.config.maxConcurrentTimers) {
      throw new Error(`Max concurrent timers limit reached (${this.config.maxConcurrentTimers})`);
    }

    // Load existing tournament
    const tournament = await this.tournamentRepo.findTournamentById(tournamentId);
    if (!tournament) {
      throw new Error(`Tournament ${tournamentId} not found`);
    }

    // Create or restore state
    const state: TimerState = initialState
      ? {
          isRunning: initialState.isRunning ?? false,
          isPaused: initialState.isPaused ?? true,
          level: initialState.level ?? 1,
          elapsedTime: initialState.elapsedTime ?? 0,
          remainingTime: initialState.remainingTime,
          tenths: 0,
          lastUpdateTime: new Date(),
        }
      : tournament.timerState || {
          isRunning: false,
          isPaused: true,
          level: 1,
          elapsedTime: 0,
          tenths: 0,
          lastUpdateTime: new Date(),
        };

    // Create context
    const context: TournamentTimerContext = {
      tournamentId,
      state,
      blindSchedule,
      controllerDeviceId: tournament.controllerDeviceId,
      lastHealthCheck: new Date(),
      lastPersist: new Date(),
      errorCount: 0,
      isHealthy: true,
    };

    this.timers.set(tournamentId, context);
    this.stats.totalTimers = this.timers.size;

    // If timer was running, restart it
    if (state.isRunning && !state.isPaused) {
      this.startTicker(tournamentId);
    }

    console.log(`[MultiTimerManager] Initialized timer for ${tournamentId}`);
    return state;
  }

  /**
   * Start timer for tournament
   */
  async startTimer(tournamentId: string, deviceId?: string): Promise<TimerState> {
    const context = this.timers.get(tournamentId);
    if (!context) {
      throw new Error(`Timer for ${tournamentId} not initialized`);
    }

    const previousState = { ...context.state };

    context.state.isRunning = true;
    context.state.isPaused = false;
    context.state.lastUpdateTime = new Date();

    if (deviceId) {
      context.controllerDeviceId = deviceId;
    }

    // Start ticker
    this.startTicker(tournamentId);

    // Queue persist
    this.queuePersist(tournamentId);

    // Log event
    await this.logEvent(tournamentId, 'tournament_start', previousState, context.state, deviceId);

    // Notify subscribers
    this.notifySubscribers(tournamentId, context.state);

    this.updateStats();
    return context.state;
  }

  /**
   * Pause timer
   */
  async pauseTimer(tournamentId: string, deviceId?: string): Promise<TimerState> {
    const context = this.timers.get(tournamentId);
    if (!context) {
      throw new Error(`Timer for ${tournamentId} not found`);
    }

    if (!context.state.isRunning || context.state.isPaused) {
      return context.state;
    }

    const previousState = { ...context.state };

    context.state.isPaused = true;

    // Stop ticker
    this.stopTicker(tournamentId);

    // Queue persist
    this.queuePersist(tournamentId);

    // Log event
    await this.logEvent(tournamentId, 'pause', previousState, context.state, deviceId);

    // Notify subscribers
    this.notifySubscribers(tournamentId, context.state);

    this.updateStats();
    return context.state;
  }

  /**
   * Resume timer
   */
  async resumeTimer(tournamentId: string, deviceId?: string): Promise<TimerState> {
    const context = this.timers.get(tournamentId);
    if (!context) {
      throw new Error(`Timer for ${tournamentId} not found`);
    }

    if (!context.state.isPaused) {
      return context.state;
    }

    const previousState = { ...context.state };

    context.state.isPaused = false;
    context.state.lastUpdateTime = new Date();

    if (deviceId) {
      context.controllerDeviceId = deviceId;
    }

    // Start ticker
    this.startTicker(tournamentId);

    // Queue persist
    this.queuePersist(tournamentId);

    // Log event
    await this.logEvent(tournamentId, 'resume', previousState, context.state, deviceId);

    // Notify subscribers
    this.notifySubscribers(tournamentId, context.state);

    this.updateStats();
    return context.state;
  }

  /**
   * Set blind level
   */
  async setLevel(tournamentId: string, level: number, deviceId?: string): Promise<TimerState> {
    const context = this.timers.get(tournamentId);
    if (!context) {
      throw new Error(`Timer for ${tournamentId} not found`);
    }

    const blindLevel = context.blindSchedule.find((bl) => bl.level === level);
    if (!blindLevel) {
      throw new Error(`Level ${level} not found in blind schedule`);
    }

    const previousState = { ...context.state };

    context.state.level = level;
    context.state.remainingTime = blindLevel.duration * 60 * 1000;
    context.state.lastUpdateTime = new Date();

    if (deviceId) {
      context.controllerDeviceId = deviceId;
    }

    // Queue persist
    this.queuePersist(tournamentId);

    // Log event
    await this.logEvent(tournamentId, 'level_start', previousState, context.state, deviceId);

    // Notify subscribers
    this.notifySubscribers(tournamentId, context.state);

    return context.state;
  }

  /**
   * Adjust time
   */
  async adjustTime(tournamentId: string, milliseconds: number, deviceId?: string): Promise<TimerState> {
    const context = this.timers.get(tournamentId);
    if (!context) {
      throw new Error(`Timer for ${tournamentId} not found`);
    }

    const previousState = { ...context.state };

    context.state.elapsedTime += milliseconds;
    if (context.state.remainingTime !== undefined) {
      context.state.remainingTime -= milliseconds;
    }
    context.state.lastUpdateTime = new Date();

    if (deviceId) {
      context.controllerDeviceId = deviceId;
    }

    // Queue persist
    this.queuePersist(tournamentId);

    // Log event
    await this.logEvent(tournamentId, 'director_override', previousState, context.state, deviceId);

    // Notify subscribers
    this.notifySubscribers(tournamentId, context.state);

    return context.state;
  }

  /**
   * Get timer state
   */
  getTimerState(tournamentId: string): TimerState | null {
    const context = this.timers.get(tournamentId);
    return context ? context.state : null;
  }

  /**
   * Subscribe to timer updates
   */
  subscribe(tournamentId: string, callback: (state: TimerState) => void): () => void {
    if (!this.subscribers.has(tournamentId)) {
      this.subscribers.set(tournamentId, new Set());
    }

    this.subscribers.get(tournamentId)!.add(callback);

    return () => {
      this.subscribers.get(tournamentId)?.delete(callback);
    };
  }

  /**
   * Remove timer (tournament ended)
   */
  async removeTimer(tournamentId: string): Promise<void> {
    const context = this.timers.get(tournamentId);
    if (!context) {
      return;
    }

    const previousState = { ...context.state };
    context.state.isRunning = false;

    // Stop ticker
    this.stopTicker(tournamentId);

    // Remove from maps
    this.timers.delete(tournamentId);
    this.subscribers.delete(tournamentId);
    this.persistQueue.delete(tournamentId);

    // Log event
    await this.logEvent(tournamentId, 'tournament_end', previousState, context.state);

    // Persist final state
    await this.tournamentRepo.updateTournament(tournamentId, { timerState: context.state });

    this.updateStats();
    console.log(`[MultiTimerManager] Removed timer for ${tournamentId}`);
  }

  /**
   * Get health status for all timers
   */
  getHealthStatus(): Map<string, HealthStatus> {
    const healthMap = new Map<string, HealthStatus>();

    for (const [tournamentId, context] of this.timers) {
      healthMap.set(tournamentId, this.getTimerHealthForContext(context));
    }

    return healthMap;
  }

  /**
   * Get health status for specific timer
   */
  getTimerHealthById(tournamentId: string): HealthStatus {
    const context = this.timers.get(tournamentId);
    if (!context) {
      return 'unhealthy';
    }

    return this.getTimerHealthForContext(context);
  }

  /**
   * Get statistics
   */
  getStatistics() {
    return {
      ...this.stats,
      activeTickers: this.tickers.size,
      subscribers: this.subscribers.size,
      queueSize: this.persistQueue.size,
    };
  }

  /**
   * Load all active timers from database (for recovery after restart)
   */
  async loadActiveTimers(): Promise<number> {
    // Load all tournaments that have active timers
    const activeTournaments = await this.tournamentRepo.findActive();

    let loaded = 0;
    for (const tournament of activeTournaments) {
      try {
        const state = tournament.timerState;

        // Calculate elapsed time since last update
        if (state.isRunning && !state.isPaused) {
          const now = new Date();
          const elapsedSinceUpdate = now.getTime() - state.lastUpdateTime.getTime();
          state.elapsedTime += elapsedSinceUpdate;

          if (state.remainingTime !== undefined) {
            state.remainingTime -= elapsedSinceUpdate;
          }

          state.lastUpdateTime = now;
        }

        // Get blind schedule for tournament
        // TODO: Load from database
        const blindSchedule: BlindLevel[] = [];

        await this.initializeTimer(tournament.tournamentId, blindSchedule, state);
        loaded++;
      } catch (error) {
        console.error(`[MultiTimerManager] Failed to load timer for ${tournament.tournamentId}:`, error);
      }
    }

    console.log(`[MultiTimerManager] Loaded ${loaded} active timers`);
    return loaded;
  }

  /**
   * Clean up all resources
   */
  destroy(): void {
    // Stop health checks
    if (this.healthCheckInterval) {
      clearInterval(this.healthCheckInterval);
      this.healthCheckInterval = null;
    }

    // Stop batch persist
    if (this.batchPersistInterval) {
      clearInterval(this.batchPersistInterval);
      this.batchPersistInterval = null;
    }

    // Stop all tickers
    for (const tournamentId of this.tickers.keys()) {
      this.stopTicker(tournamentId);
    }

    // Clear all maps
    this.timers.clear();
    this.tickers.clear();
    this.subscribers.clear();
    this.persistQueue.clear();
  }

  /**
   * Start ticker for tournament
   */
  private startTicker(tournamentId: string): void {
    this.stopTicker(tournamentId);

    const ticker = setInterval(() => {
      this.tick(tournamentId);
    }, this.config.updateInterval);

    this.tickers.set(tournamentId, ticker);
  }

  /**
   * Stop ticker for tournament
   */
  private stopTicker(tournamentId: string): void {
    const ticker = this.tickers.get(tournamentId);
    if (ticker) {
      clearInterval(ticker);
      this.tickers.delete(tournamentId);
    }
  }

  /**
   * Timer tick
   */
  private tick(tournamentId: string): void {
    const context = this.timers.get(tournamentId);
    if (!context || !context.state.isRunning || context.state.isPaused) {
      return;
    }

    try {
      const delta = this.config.updateInterval;

      context.state.elapsedTime += delta;

      if (context.state.remainingTime !== undefined) {
        context.state.remainingTime -= delta;

        // Check for level transition
        if (context.state.remainingTime <= 0) {
          this.transitionToNextLevel(context);
        }
      }

      // Calculate tenths digit
      context.state.tenths = Math.floor((context.state.elapsedTime % 1000) / 100);
      context.state.lastUpdateTime = new Date();

      this.stats.totalTicks++;

      // Notify subscribers
      this.notifySubscribers(tournamentId, context.state);

      // Queue persist (will be batched)
      this.queuePersist(tournamentId);
    } catch (error) {
      console.error(`[MultiTimerManager] Tick error for ${tournamentId}:`, error);
      context.errorCount++;
      this.stats.totalErrors++;
    }
  }

  /**
   * Transition to next blind level
   */
  private async transitionToNextLevel(context: TournamentTimerContext): Promise<void> {
    const currentLevel = context.state.level;
    const nextLevel = context.blindSchedule.find((bl) => bl.level === currentLevel + 1);

    if (nextLevel) {
      const previousState = { ...context.state };

      context.state.level = nextLevel.level;
      context.state.remainingTime = nextLevel.duration * 60 * 1000;
      context.state.lastUpdateTime = new Date();

      await this.logEvent(
        context.tournamentId,
        'level_start',
        previousState,
        context.state,
        context.controllerDeviceId
      );

      // Notify subscribers of level change
      this.notifySubscribers(context.tournamentId, context.state);
    }
  }

  /**
   * Get timer health status
   */
  private getTimerHealthForContext(context: TournamentTimerContext): HealthStatus {
    if (context.errorCount > 10) {
      return 'unhealthy';
    }

    if (context.errorCount > 5 || !context.state.isRunning) {
      return 'degraded';
    }

    return 'healthy';
  }

  /**
   * Queue timer for persist
   */
  private queuePersist(tournamentId: string): void {
    this.persistQueue.add(tournamentId);
  }

  /**
   * Start batch persist interval
   */
  private startBatchPersist(): void {
    this.batchPersistInterval = setInterval(async () => {
      if (this.persistQueue.size === 0) {
        return;
      }

      const batch = Array.from(this.persistQueue);
      this.persistQueue.clear();

      for (const tournamentId of batch) {
        const context = this.timers.get(tournamentId);
        if (!context) {
          continue;
        }

        try {
          await this.tournamentRepo.updateTournament(tournamentId, { timerState: context.state });
          context.lastPersist = new Date();
        } catch (error) {
          console.error(`[MultiTimerManager] Failed to persist ${tournamentId}:`, error);
          context.errorCount++;
        }
      }
    }, this.config.persistInterval);
  }

  /**
   * Start health check interval
   */
  private startHealthChecks(): void {
    this.healthCheckInterval = setInterval(() => {
      const now = new Date();

      for (const [tournamentId, context] of this.timers) {
        context.lastHealthCheck = now;

        // Check if ticker is running but should be
        if (context.state.isRunning && !context.state.isPaused) {
          if (!this.tickers.has(tournamentId)) {
            console.warn(`[MultiTimerManager] Restarting ticker for ${tournamentId}`);
            this.startTicker(tournamentId);
          }
        }

        // Check health status
        const health = this.getTimerHealthForContext(context);
        context.isHealthy = health === 'healthy';

        if (health === 'unhealthy') {
          console.error(`[MultiTimerManager] Unhealthy timer: ${tournamentId} (${context.errorCount} errors)`);
        }
      }

      this.updateStats();
    }, this.config.healthCheckInterval);
  }

  /**
   * Update statistics
   */
  private updateStats(): void {
    let active = 0;
    let paused = 0;
    let unhealthy = 0;

    for (const context of this.timers.values()) {
      if (context.state.isRunning) {
        if (context.state.isPaused) {
          paused++;
        } else {
          active++;
        }
      }

      if (!context.isHealthy) {
        unhealthy++;
      }
    }

    this.stats.totalTimers = this.timers.size;
    this.stats.activeTimers = active;
    this.stats.pausedTimers = paused;
    this.stats.unhealthyTimers = unhealthy;
  }

  /**
   * Notify subscribers
   */
  private notifySubscribers(tournamentId: string, state: TimerState): void {
    const subscribers = this.subscribers.get(tournamentId);
    if (subscribers) {
      for (const callback of subscribers) {
        try {
          callback(state);
        } catch (error) {
          console.error(`[MultiTimerManager] Subscriber error for ${tournamentId}:`, error);
        }
      }
    }
  }

  /**
   * Log timer event
   */
  private async logEvent(
    tournamentId: string,
    eventType: string,
    previousState: any,
    newState: TimerState,
    deviceId?: string
  ): Promise<void> {
    try {
      await this.timerEventRepo.insert({
        event_id: crypto.randomUUID(),
        tournament_id: tournamentId,
        timestamp: new Date().toISOString(),
        event_type: eventType as any,
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
      console.error(`[MultiTimerManager] Failed to log event:`, error);
    }
  }
}

/**
 * Create a multi-timer manager instance
 */
export function createMultiTimerManager(config: MultiTimerConfig): MultiTimerManager {
  return new MultiTimerManager(config);
}
