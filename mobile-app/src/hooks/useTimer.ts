/**
 * useTimer Hook
 * Combines local timer precision with server authority
 *
 * Architecture:
 * 1. LocalTimerEngine provides 100ms precision updates
 * 2. TimeSyncService periodically syncs with server
 * 3. TimerPersistence survives app restarts
 * 4. BackgroundHandler manages app state transitions
 * 5. Server syncs override local state for authority
 *
 * Constitution Requirements:
 * - 100ms precision for tenths-of-second display
 * - <1s drift over 8 hours
 * - <2s recovery after app restart
 * - <0.2s drift after 30min backgrounding
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import type { TimerState, BlindLevel } from '@shared/types';
import { LocalTimerEngine } from '../services/timer/LocalTimerEngine';
import { TimerPersistence } from '../services/timer/TimerPersistence';
import { TimerBackgroundHandler } from '../services/timer/TimerBackgroundHandler';
import { TimeSyncService } from '../services/timer/TimeSyncService';
import { timerStore } from '../stores/timerStore';

export interface UseTimerConfig {
  /** Tournament ID */
  tournamentId: string;
  /** Blind schedule for level progression */
  blindSchedule?: BlindLevel[];
  /** API fetch function for server sync */
  fetchApi?: (endpoint: string) => Promise<Response>;
  /** How often to sync with server (ms) */
  syncInterval?: number;
}

export interface UseTimerReturn {
  /** Current timer state */
  state: TimerState | null;
  /** Start the timer */
  start: () => Promise<void>;
  /** Pause the timer */
  pause: () => Promise<void>;
  /** Resume from pause */
  resume: () => Promise<void>;
  /** Reset the timer */
  reset: () => Promise<void>;
  /** Jump to next level */
  nextLevel: () => Promise<void>;
  /** Set specific level */
  setLevel: (level: number) => Promise<void>;
  /** Adjust time (ms) */
  adjustTime: (ms: number) => Promise<void>;
  /** Sync with server */
  sync: () => Promise<void>;
  /** Loading state */
  isLoading: boolean;
  /** Error state */
  error: Error | null;
  /** Clock offset from server (ms) */
  clockOffset: number;
  /** Is app backgrounded */
  isBackgrounded: boolean;
}

/**
 * useTimer Hook
 *
 * Manages tournament timer with hybrid local+server architecture:
 * - Local: High-precision 100ms updates using LocalTimerEngine
 * - Server: Authoritative state via periodic sync
 * - Persistence: Survives app restarts
 * - Background: Handles app state transitions
 */
export function useTimer(config: UseTimerConfig): UseTimerReturn {
  const {
    tournamentId,
    blindSchedule = [],
    fetchApi = fetch,
    syncInterval = 5000,
  } = config;

  const [state, setState] = useState<TimerState | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  const [clockOffset, setClockOffset] = useState(0);
  const [isBackgrounded, setIsBackgrounded] = useState(false);

  // Use refs to avoid re-initializing services
  const engineRef = useRef<LocalTimerEngine | null>(null);
  const timeSyncRef = useRef<TimeSyncService | null>(null);
  const backgroundHandlerRef = useRef<TimerBackgroundHandler | null>(null);
  const isInitializedRef = useRef(false);

  // Initialize timer engine and services
  useEffect(() => {
    if (isInitializedRef.current) {
      return;
    }

    const initialize = async () => {
      try {
        setIsLoading(true);

        // Try to load persisted state first
        const persistedState = await TimerPersistence.loadState(tournamentId);

        // Create local timer engine
        const engine = new LocalTimerEngine(tournamentId, {
          updateInterval: 100, // 100ms precision
          onUpdate: (newState) => {
            setState(newState);
            timerStore.setTimerState(tournamentId, newState);
          },
          onLevelChange: (level, previousLevel) => {
            console.log(`[useTimer] Level changed: ${previousLevel} -> ${level}`);
          },
        });

        engine.setBlindSchedule(blindSchedule);
        engineRef.current = engine;

        // Create time sync service
        const timeSync = new TimeSyncService({
          syncInterval,
          onSync: (offset) => {
            setClockOffset(offset);
          },
        });
        timeSyncRef.current = timeSync;

        // Create background handler
        const backgroundHandler = new TimerBackgroundHandler({
          tournamentId,
          onBackground: () => {
            setIsBackgrounded(true);
            // Save state before backgrounding
            if (state) {
              TimerPersistence.saveState(tournamentId, state);
            }
          },
          onForeground: async (backgroundDuration) => {
            setIsBackgrounded(false);

            // Recover timer state
            const persistedState = await TimerPersistence.loadState(tournamentId);
            if (persistedState) {
              // Apply drift correction
              const drift = await TimerPersistence.calculateDrift(tournamentId, persistedState);
              engine.adjustTime(drift);
            }

            // Sync with server for authoritative time
            await syncWithServer();
          },
          syncWithServer: syncWithServer,
          applyDrift: (driftMs) => {
            engine.adjustTime(driftMs);
          },
        });
        backgroundHandlerRef.current = backgroundHandler;

        // Restore or initialize state
        if (persistedState && TimerPersistence.isValidState(persistedState)) {
          engine.setState(persistedState);
          console.log('[useTimer] Restored persisted state');
        } else {
          // Initialize fresh state
          const initialState: TimerState = {
            isRunning: false,
            isPaused: false,
            level: 1,
            elapsedTime: 0,
            remainingTime: blindSchedule[0]?.duration
              ? blindSchedule[0].duration * 60 * 1000
              : undefined,
            tenths: 0,
            lastUpdateTime: new Date(),
          };
          engine.setState(initialState);
        }

        // Start time sync
        timeSync.start(() => fetchApi('/api/time'));

        isInitializedRef.current = true;
        setIsLoading(false);
      } catch (err) {
        console.error('[useTimer] Initialization failed:', err);
        setError(err as Error);
        setIsLoading(false);
      }
    };

    initialize();

    // Cleanup
    return () => {
      engineRef.current?.destroy();
      timeSyncRef.current?.destroy();
      backgroundHandlerRef.current?.destroy();
      isInitializedRef.current = false;
    };
  }, [tournamentId]); // Only run on mount/tournamentId change

  /**
   * Sync with server for authoritative timer state
   */
  const syncWithServer = useCallback(async (): Promise<TimerState | null> => {
    try {
      const response = await fetchApi(`/api/tournaments/${tournamentId}/timer`);
      if (!response.ok) {
        throw new Error(`Server returned ${response.status}`);
      }

      const serverState = (await response.json()) as TimerState;
      engineRef.current?.setState(serverState);
      await TimerPersistence.saveState(tournamentId, serverState);

      return serverState;
    } catch (err) {
      console.warn('[useTimer] Server sync failed:', err);
      return null;
    }
  }, [tournamentId, fetchApi]);

  /**
   * Start the timer
   */
  const start = useCallback(async () => {
    try {
      const response = await fetchApi(`/api/tournaments/${tournamentId}/start`, {
        method: 'POST',
      });

      if (!response.ok) {
        throw new Error(`Failed to start: ${response.status}`);
      }

      const serverState = (await response.json()) as TimerState;
      engineRef.current?.setState(serverState);
      await TimerPersistence.saveState(tournamentId, serverState);
    } catch (err) {
      setError(err as Error);
      throw err;
    }
  }, [tournamentId, fetchApi]);

  /**
   * Pause the timer
   */
  const pause = useCallback(async () => {
    try {
      const response = await fetchApi(`/api/tournaments/${tournamentId}/pause`, {
        method: 'POST',
      });

      if (!response.ok) {
        throw new Error(`Failed to pause: ${response.status}`);
      }

      const serverState = (await response.json()) as TimerState;
      engineRef.current?.setState(serverState);
      await TimerPersistence.saveState(tournamentId, serverState);
    } catch (err) {
      setError(err as Error);
      throw err;
    }
  }, [tournamentId, fetchApi]);

  /**
   * Resume from pause
   */
  const resume = useCallback(async () => {
    try {
      const response = await fetchApi(`/api/tournaments/${tournamentId}/resume`, {
        method: 'POST',
      });

      if (!response.ok) {
        throw new Error(`Failed to resume: ${response.status}`);
      }

      const serverState = (await response.json()) as TimerState;
      engineRef.current?.setState(serverState);
      await TimerPersistence.saveState(tournamentId, serverState);
    } catch (err) {
      setError(err as Error);
      throw err;
    }
  }, [tournamentId, fetchApi]);

  /**
   * Reset the timer
   */
  const reset = useCallback(async () => {
    try {
      engineRef.current?.reset();

      const response = await fetchApi(`/api/tournaments/${tournamentId}/timer`, {
        method: 'DELETE',
      });

      if (!response.ok) {
        throw new Error(`Failed to reset: ${response.status}`);
      }

      await TimerPersistence.deleteState(tournamentId);
    } catch (err) {
      setError(err as Error);
      throw err;
    }
  }, [tournamentId, fetchApi]);

  /**
   * Advance to next level
   */
  const nextLevel = useCallback(async () => {
    try {
      const currentState = engineRef.current?.getState();
      if (!currentState) return;

      const response = await fetchApi(`/api/tournaments/${tournamentId}/level`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ level: currentState.level + 1 }),
      });

      if (!response.ok) {
        throw new Error(`Failed to change level: ${response.status}`);
      }

      const serverState = (await response.json()) as TimerState;
      engineRef.current?.setState(serverState);
      await TimerPersistence.saveState(tournamentId, serverState);
    } catch (err) {
      setError(err as Error);
      throw err;
    }
  }, [tournamentId, fetchApi]);

  /**
   * Set specific level
   */
  const setLevel = useCallback(async (level: number) => {
    try {
      const response = await fetchApi(`/api/tournaments/${tournamentId}/level`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ level }),
      });

      if (!response.ok) {
        throw new Error(`Failed to set level: ${response.status}`);
      }

      const serverState = (await response.json()) as TimerState;
      engineRef.current?.setState(serverState);
      await TimerPersistence.saveState(tournamentId, serverState);
    } catch (err) {
      setError(err as Error);
      throw err;
    }
  }, [tournamentId, fetchApi]);

  /**
   * Adjust timer time
   */
  const adjustTime = useCallback(async (ms: number) => {
    try {
      engineRef.current?.adjustTime(ms);

      const response = await fetchApi(`/api/tournaments/${tournamentId}/timer/adjust`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ milliseconds: ms }),
      });

      if (!response.ok) {
        throw new Error(`Failed to adjust time: ${response.status}`);
      }

      const serverState = (await response.json()) as TimerState;
      await TimerPersistence.saveState(tournamentId, serverState);
    } catch (err) {
      setError(err as Error);
      throw err;
    }
  }, [tournamentId, fetchApi]);

  /**
   * Manual sync with server
   */
  const sync = useCallback(async () => {
    await syncWithServer();
  }, [syncWithServer]);

  return {
    state,
    start,
    pause,
    resume,
    reset,
    nextLevel,
    setLevel,
    adjustTime,
    sync,
    isLoading,
    error,
    clockOffset,
    isBackgrounded,
  };
}
