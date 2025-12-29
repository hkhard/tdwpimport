/**
 * Timer WebSocket Broadcaster
 * Broadcasts timer state updates to subscribed clients
 *
 * Constitution Requirements:
 * - US3-A1: <2s updates for remote viewing
 * - US3-A2: <1s updates for blind changes
 * - US3-A5: Graceful degradation on poor network (T104)
 */

import type { TimerState } from '@shared/types';
import { broadcastToTournament } from './server';

export interface TimerBroadcastMessage {
  type: 'timer:update' | 'timer:start' | 'timer:pause' | 'timer:resume' | 'level:change';
  tournamentId: string;
  data: {
    state: TimerState;
    previousState?: TimerState;
  };
  timestamp: number;
}

/**
 * Throttle configuration for T104 graceful degradation
 */
interface ThrottleConfig {
  enabled: boolean;
  interval: number; // ms between bulk sends
  pendingUpdates: Map<string, TimerBroadcastMessage[]>;
  intervalId: NodeJS.Timeout | null;
}

/**
 * Global throttle state for T104
 */
const throttleConfig: ThrottleConfig = {
  enabled: false,
  interval: 1000, // 1 second bulk send interval
  pendingUpdates: new Map(),
  intervalId: null,
};

/**
 * Enable throttling mode (T104 - graceful degradation)
 * Used when network conditions are poor or viewer count is high
 */
export function enableThrottling(): void {
  if (!throttleConfig.enabled) {
    throttleConfig.enabled = true;
    console.log('[Broadcaster] Throttling enabled - bulk sending every 1s');

    // Start bulk send interval
    if (!throttleConfig.intervalId) {
      throttleConfig.intervalId = setInterval(() => {
        flushBulkUpdates();
      }, throttleConfig.interval);
    }
  }
}

/**
 * Disable throttling mode (return to immediate updates)
 */
export function disableThrottling(): void {
  if (throttleConfig.enabled) {
    throttleConfig.enabled = false;
    console.log('[Broadcaster] Throttling disabled - immediate updates');

    // Send any pending updates
    flushBulkUpdates();

    // Clear interval
    if (throttleConfig.intervalId) {
      clearInterval(throttleConfig.intervalId);
      throttleConfig.intervalId = null;
    }
  }
}

/**
 * Flush all pending bulk updates
 */
function flushBulkUpdates(): void {
  if (throttleConfig.pendingUpdates.size === 0) {
    return;
  }

  const updates = new Map(throttleConfig.pendingUpdates);
  throttleConfig.pendingUpdates.clear();

  for (const [tournamentId, messages] of updates.entries()) {
    // Send all pending updates for this tournament
    // Combine into a single bulk message to reduce bandwidth
    const bulkMessage = {
      type: 'bulk' as const,
      tournamentId,
      data: {
        updates: messages,
        count: messages.length,
      },
      timestamp: Date.now(),
    };

    // Import broadcastToTournament dynamically to avoid circular dependency
    try {
      broadcastToTournament(tournamentId, bulkMessage);
    } catch (error) {
      console.error(`[Broadcaster] Error sending bulk updates for ${tournamentId}:`, error);
    }
  }

  if (updates.size > 0) {
    console.log(`[Broadcaster] Sent bulk updates for ${updates.size} tournaments`);
  }
}

/**
 * Queue update for bulk sending (T104)
 * When throttling is enabled, updates are queued and sent in batches
 */
function queueUpdate(message: TimerBroadcastMessage): void {
  if (!throttleConfig.pendingUpdates.has(message.tournamentId)) {
    throttleConfig.pendingUpdates.set(message.tournamentId, []);
  }

  const updates = throttleConfig.pendingUpdates.get(message.tournamentId)!;

  // Keep only the latest update for each message type to avoid duplicates
  const existingIndex = updates.findIndex(m => m.type === message.type);
  if (existingIndex >= 0) {
    updates[existingIndex] = message; // Replace with latest
  } else {
    updates.push(message);
  }

  // Limit queue size to prevent memory issues
  if (updates.length > 100) {
    updates.splice(0, updates.length - 100); // Keep only latest 100
  }
}

/**
 * Broadcast timer update to all subscribed clients
 */
export function broadcastTimerUpdate(
  tournamentId: string,
  state: TimerState,
  previousState?: TimerState
): void {
  const message: TimerBroadcastMessage = {
    type: 'timer:update',
    tournamentId,
    data: {
      state,
      previousState,
    },
    timestamp: Date.now(),
  };

  if (throttleConfig.enabled) {
    queueUpdate(message);
  } else {
    broadcastToTournament(tournamentId, message);
  }
}

/**
 * Broadcast timer start event
 */
export function broadcastTimerStart(tournamentId: string, state: TimerState): void {
  console.log('[Broadcast] timer:start for tournament:', tournamentId, 'isRunning:', state.isRunning, 'isPaused:', state.isPaused);
  const message: TimerBroadcastMessage = {
    type: 'timer:start',
    tournamentId,
    data: { state },
    timestamp: Date.now(),
  };

  if (throttleConfig.enabled) {
    queueUpdate(message);
  } else {
    broadcastToTournament(tournamentId, message);
  }
}

/**
 * Broadcast timer pause event
 */
export function broadcastTimerPause(tournamentId: string, state: TimerState): void {
  console.log('[Broadcast] timer:pause for tournament:', tournamentId, 'isRunning:', state.isRunning, 'isPaused:', state.isPaused);
  const message: TimerBroadcastMessage = {
    type: 'timer:pause',
    tournamentId,
    data: { state },
    timestamp: Date.now(),
  };

  if (throttleConfig.enabled) {
    queueUpdate(message);
  } else {
    broadcastToTournament(tournamentId, message);
  }
}

/**
 * Broadcast timer resume event
 */
export function broadcastTimerResume(tournamentId: string, state: TimerState): void {
  console.log('[Broadcast] timer:resume for tournament:', tournamentId, 'isRunning:', state.isRunning, 'isPaused:', state.isPaused);
  const message: TimerBroadcastMessage = {
    type: 'timer:resume',
    tournamentId,
    data: { state },
    timestamp: Date.now(),
  };

  if (throttleConfig.enabled) {
    queueUpdate(message);
  } else {
    broadcastToTournament(tournamentId, message);
  }
}

/**
 * Broadcast level change event
 * NOTE: Level changes are ALWAYS sent immediately (US3-A2: <1s for blind changes)
 * Even when throttling is enabled, level changes bypass the queue
 */
export function broadcastLevelChange(
  tournamentId: string,
  state: TimerState,
  previousState: TimerState
): void {
  const message: TimerBroadcastMessage = {
    type: 'level:change',
    tournamentId,
    data: { state, previousState },
    timestamp: Date.now(),
  };

  // Level changes always sent immediately per US3-A2 (<1s for blind changes)
  broadcastToTournament(tournamentId, message);
}

/**
 * Setup timer broadcasting with TimerService
 * Subscribes to timer updates and broadcasts them
 */
export function setupTimerBroadcasting(timerService: any): void {
  // Get all active tournaments
  const activeTournaments = timerService.getActiveTournaments();

  for (const tournamentId of activeTournaments) {
    timerService.subscribe(tournamentId, (state: TimerState) => {
      broadcastTimerUpdate(tournamentId, state);
    });
  }
}
