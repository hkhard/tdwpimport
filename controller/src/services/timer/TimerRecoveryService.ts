/**
 * Timer Recovery Service
 * Fast recovery of timer state after server restart
 *
 * Constitution Requirements:
 * - US5-A2: <1s recovery after restart
 * - US1-A3: <2s recovery on mobile after app restart
 */

import type { Database } from 'better-sqlite3';
import { TournamentRepository } from '../../db/repositories/TournamentRepository';
import { TimerEventRepository } from '../../db/repositories/TimerEventRepository';
import type { TimerState } from '@shared/types';

export interface RecoveryConfig {
  /** Database connection */
  db: Database;
  /** Max recovery time (ms) */
  maxRecoveryTime?: number;
}

export interface RecoveryResult {
  /** Tournament ID */
  tournamentId: string;
  /** Whether recovery was successful */
  success: boolean;
  /** Recovered timer state */
  state?: TimerState;
  /** Recovery time (ms) */
  recoveryTimeMs: number;
  /** Error message if failed */
  error?: string;
}

export interface RecoverySummary {
  /** Total tournaments to recover */
  total: number;
  /** Successfully recovered */
  successful: number;
  /** Failed recoveries */
  failed: number;
  /** Total recovery time (ms) */
  totalTimeMs: number;
  /** Average recovery time (ms) */
  avgTimeMs: number;
  /** Individual results */
  results: RecoveryResult[];
}

/**
 * Timer Recovery Service
 *
 * Recovers timer state after server restart:
 * - Loads tournament state from database
 * - Calculates elapsed time since last update
 * - Replays recent timer events for accuracy
 * - Restores running timers within 1 second
 */
export class TimerRecoveryService {
  private config: Required<RecoveryConfig>;
  private tournamentRepo: TournamentRepository;
  private timerEventRepo: TimerEventRepository;

  constructor(config: RecoveryConfig) {
    this.config = {
      db: config.db,
      maxRecoveryTime: config.maxRecoveryTime || 1000, // 1 second
    };

    this.tournamentRepo = new TournamentRepository();
    this.timerEventRepo = new TimerEventRepository();
  }

  /**
   * Recover all active tournament timers
   */
  async recoverAll(): Promise<RecoverySummary> {
    const startTime = performance.now();
    const results: RecoveryResult[] = [];

    // Get all active tournaments
    const activeTournaments = await this.tournamentRepo.findActive();

    console.log(`[TimerRecovery] Recovering ${activeTournaments.length} active timers...`);

    // Recover each tournament in parallel
    const recoveryPromises = activeTournaments.map((tournament) =>
      this.recoverTournament(tournament.tournamentId)
    );

    const recoveredResults = await Promise.all(recoveryPromises);
    results.push(...recoveredResults);

    const endTime = performance.now();
    const totalTimeMs = endTime - startTime;

    const summary: RecoverySummary = {
      total: results.length,
      successful: results.filter((r) => r.success).length,
      failed: results.filter((r) => !r.success).length,
      totalTimeMs,
      avgTimeMs: totalTimeMs / results.length,
      results,
    };

    console.log(
      `[TimerRecovery] Recovery complete: ${summary.successful}/${summary.total} in ${totalTimeMs.toFixed(2)}ms`
    );

    return summary;
  }

  /**
   * Recover a single tournament timer
   */
  async recoverTournament(tournamentId: string): Promise<RecoveryResult> {
    const startTime = performance.now();

    try {
      // Load tournament from database
      const tournament = await this.tournamentRepo.findTournamentById(tournamentId);
      if (!tournament) {
        return {
          tournamentId,
          success: false,
          recoveryTimeMs: performance.now() - startTime,
          error: 'Tournament not found',
        };
      }

      let state = { ...tournament.timerState };

      // If timer was running and not paused, calculate elapsed time
      if (state.isRunning && !state.isPaused) {
        const now = new Date();
        const elapsedSinceUpdate = now.getTime() - state.lastUpdateTime.getTime();

        // Update elapsed time
        state.elapsedTime += elapsedSinceUpdate;

        // Update remaining time if set
        if (state.remainingTime !== undefined) {
          state.remainingTime -= elapsedSinceUpdate;

          // Check if level should have transitioned
          if (state.remainingTime <= 0) {
            // Find next level from timer events
            const nextState = await this.recoverFromEvents(tournamentId, state);
            if (nextState) {
              state = nextState;
            }
          }
        }

        // Update tenths
        state.tenths = Math.floor((state.elapsedTime % 1000) / 100);
        state.lastUpdateTime = now;
      }

      const recoveryTimeMs = performance.now() - startTime;

      // Check if recovery was fast enough
      if (recoveryTimeMs > this.config.maxRecoveryTime) {
        console.warn(
          `[TimerRecovery] Slow recovery for ${tournamentId}: ${recoveryTimeMs.toFixed(2)}ms`
        );
      }

      return {
        tournamentId,
        success: true,
        state,
        recoveryTimeMs,
      };
    } catch (error) {
      const recoveryTimeMs = performance.now() - startTime;
      console.error(`[TimerRecovery] Failed to recover ${tournamentId}:`, error);

      return {
        tournamentId,
        success: false,
        recoveryTimeMs,
        error: error instanceof Error ? error.message : String(error),
      };
    }
  }

  /**
   * Recover state by replaying timer events
   */
  private async recoverFromEvents(
    tournamentId: string,
    currentState: TimerState
  ): Promise<TimerState | null> {
    try {
      // Get recent timer events (last 100)
      const recentEvents = await this.timerEventRepo.findRecentByTournament(tournamentId, 100);

      if (recentEvents.length === 0) {
        return currentState;
      }

      // Find the most recent state from events
      // Start from most recent and work backwards
      for (let i = recentEvents.length - 1; i >= 0; i--) {
        const event = recentEvents[i];

        if (event.newState && event.eventType !== 'pause' && event.eventType !== 'tournament_end') {
          // Calculate time elapsed since this event
          const elapsedSinceEvent = Date.now() - new Date(event.timestamp).getTime();

          return {
            ...currentState,
            level: event.newState.level,
            elapsedTime: event.newState.elapsedTime + elapsedSinceEvent,
            tenths: Math.floor(((event.newState.elapsedTime + elapsedSinceEvent) % 1000) / 100),
            lastUpdateTime: new Date(),
          };
        }
      }

      return currentState;
    } catch (error) {
      console.error('[TimerRecovery] Failed to recover from events:', error);
      return currentState;
    }
  }

  /**
   * Validate recovered state
   */
  async validateRecoveredState(tournamentId: string, state: TimerState): Promise<boolean> {
    try {
      // Get most recent timer event
      const recentEvents = await this.timerEventRepo.findRecentByTournament(tournamentId, 1);

      if (recentEvents.length === 0) {
        return true; // No events to validate against
      }

      const latestEvent = recentEvents[0];
      const timeDiff = Math.abs(
        state.elapsedTime - (latestEvent.newState?.elapsedTime ?? state.elapsedTime)
      );

      // Allow up to 5 seconds difference (accounting for event replay calculation)
      if (timeDiff > 5000) {
        console.warn(
          `[TimerRecovery] State validation mismatch for ${tournamentId}: ${timeDiff}ms difference`
        );
        return false;
      }

      return true;
    } catch (error) {
      console.error('[TimerRecovery] Validation error:', error);
      return false;
    }
  }

  /**
   * Get recovery statistics
   */
  getRecoveryStatistics(summary: RecoverySummary) {
    return {
      total: summary.total,
      successful: summary.successful,
      failed: summary.failed,
      totalTimeMs: summary.totalTimeMs,
      avgTimeMs: summary.avgTimeMs,
      maxTimeMs: Math.max(...summary.results.map((r) => r.recoveryTimeMs)),
      minTimeMs: Math.min(...summary.results.map((r) => r.recoveryTimeMs)),
      successRate: (summary.successful / summary.total) * 100,
      meetsSLA: summary.totalTimeMs <= this.config.maxRecoveryTime * summary.total,
    };
  }
}

/**
 * Create a timer recovery service instance
 */
export function createTimerRecoveryService(config: RecoveryConfig): TimerRecoveryService {
  return new TimerRecoveryService(config);
}
