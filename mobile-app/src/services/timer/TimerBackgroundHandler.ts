/**
 * Timer Background Handler
 * Manages timer behavior when app goes to background and returns to foreground
 *
 * Constitution Requirements:
 * - US1-A1: Timer must maintain <0.2s drift after 30 minutes of backgrounding
 * - US1-A3: Timer state must recover within <2s after app restart
 *
 * Strategy:
 * 1. Record timestamp when app goes to background
 * 2. On return, calculate elapsed wall-clock time
 * 3. Sync with server to get authoritative time
 * 4. Apply drift correction to local timer
 */

import { AppState, AppStateStatus } from 'react-native';
import type { TimerState } from '@shared/types';
import { TimerPersistence } from './TimerPersistence';

export interface BackgroundHandlerConfig {
  /** Tournament ID to track */
  tournamentId: string;
  /** Callback when app goes to background */
  onBackground?: () => void;
  /** Callback when app returns from background */
  onForeground?: (backgroundDuration: number) => void;
  /** Callback to sync with server for authoritative time */
  syncWithServer?: () => Promise<TimerState | null>;
  /** Callback to apply drift correction */
  applyDrift?: (driftMs: number) => void;
  /** Maximum background time before requiring server sync (ms) */
  maxBackgroundTime?: number;
}

/**
 * Timer Background Handler
 *
 * Monitors app state changes and manages timer accuracy
 * during background/foreground transitions.
 */
export class TimerBackgroundHandler {
  private config: Required<BackgroundHandlerConfig>;
  private appStateSubscription: ReturnType<typeof AppState.addEventListener> | null = null;
  private backgroundTimestamp: number | null = null;
  private isBackgrounded: boolean = false;

  constructor(config: BackgroundHandlerConfig) {
    this.config = {
      tournamentId: config.tournamentId,
      onBackground: config.onBackground || (() => {}),
      onForeground: config.onForeground || (() => {}),
      syncWithServer: config.syncWithServer || (async () => null),
      applyDrift: config.applyDrift || (() => {}),
      maxBackgroundTime: config.maxBackgroundTime || 5 * 60 * 1000, // 5 minutes default
    };

    this.startListening();
  }

  /**
   * Start listening for app state changes
   */
  private startListening(): void {
    this.appStateSubscription = AppState.addEventListener(
      'change',
      this.handleAppStateChange
    );
  }

  /**
   * Handle app state changes
   */
  private handleAppStateChange = async (nextAppState: AppStateStatus): Promise<void> => {
    const wasBackgrounded = this.isBackgrounded;

    if (nextAppState === 'background' || nextAppState === 'inactive') {
      if (!this.isBackgrounded) {
        await this.handleBackground();
      }
    } else if (nextAppState === 'active' && wasBackgrounded) {
      await this.handleForeground();
    }
  };

  /**
   * Handle app going to background
   */
  private async handleBackground(): Promise<void> {
    this.isBackgrounded = true;
    this.backgroundTimestamp = Date.now();

    // Record background time for later recovery
    await TimerPersistence.recordBackgroundTime(this.config.tournamentId);

    // Notify callback
    this.config.onBackground();

    console.log('[TimerBackgroundHandler] App backgrounded at:', new Date(this.backgroundTimestamp).toISOString());
  }

  /**
   * Handle app returning to foreground
   */
  private async handleForeground(): Promise<void> {
    const now = Date.now();
    const backgroundDuration = this.backgroundTimestamp
      ? now - this.backgroundTimestamp
      : 0;

    this.isBackgrounded = false;
    this.backgroundTimestamp = null;

    console.log('[TimerBackgroundHandler] App foregrounded after', backgroundDuration, 'ms');

    // Calculate and apply drift correction
    await this.recoverFromBackground(backgroundDuration);

    // Notify callback
    this.config.onForeground(backgroundDuration);
  }

  /**
   * Recover timer state after backgrounding
   */
  private async recoverFromBackground(backgroundDuration: number): Promise<void> {
    // Strategy: Try server sync first for authoritative time, fall back to calculation

    const shouldSyncWithServer = backgroundDuration > this.config.maxBackgroundTime;

    if (shouldSyncWithServer && this.config.syncWithServer) {
      try {
        console.log('[TimerBackgroundHandler] Backgrounded too long, syncing with server');
        const serverState = await this.config.syncWithServer();

        if (serverState) {
          console.log('[TimerBackgroundHandler] Server sync successful, applying server state');
          // Server state will be applied by the caller
          return;
        }
      } catch (error) {
        console.warn('[TimerBackgroundHandler] Server sync failed, falling back to calculation', error);
      }
    }

    // Fall back to calculating drift based on background time
    const backgroundRecord = await TimerPersistence.getBackgroundTime(this.config.tournamentId);

    if (backgroundRecord) {
      const persistedState = await TimerPersistence.loadState(this.config.tournamentId);

      if (persistedState && persistedState.isRunning && !persistedState.isPaused) {
        // Timer was running when backgrounded, calculate drift
        const drift = backgroundDuration;
        this.config.applyDrift(drift);

        console.log('[TimerBackgroundHandler] Applied drift correction:', drift, 'ms');
      }
    }
  }

  /**
   * Manually trigger background handling (for testing)
   */
  public async simulateBackground(duration: number): Promise<void> {
    await this.handleBackground();

    // Simulate time passing
    await new Promise((resolve) => setTimeout(resolve, 100));

    // Update background timestamp to simulate longer duration
    if (this.backgroundTimestamp) {
      this.backgroundTimestamp -= duration;
    }

    await this.handleForeground();
  }

  /**
   * Check if app is currently backgrounded
   */
  public getIsBackgrounded(): boolean {
    return this.isBackgrounded;
  }

  /**
   * Get how long app has been backgrounded (ms)
   */
  public getBackgroundDuration(): number {
    if (!this.backgroundTimestamp || !this.isBackgrounded) {
      return 0;
    }

    return Date.now() - this.backgroundTimestamp;
  }

  /**
   * Clean up resources
   */
  public destroy(): void {
    if (this.appStateSubscription) {
      this.appStateSubscription.remove();
      this.appStateSubscription = null;
    }

    this.isBackgrounded = false;
    this.backgroundTimestamp = null;
  }
}

/**
 * Create a timer background handler instance
 */
export function createTimerBackgroundHandler(
  config: BackgroundHandlerConfig
): TimerBackgroundHandler {
  return new TimerBackgroundHandler(config);
}
