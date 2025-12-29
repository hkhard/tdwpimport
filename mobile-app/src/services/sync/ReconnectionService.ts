/**
 * Reconnection Service
 * Exponential backoff reconnection logic
 *
 * Constitution Requirements:
 * - US2-A2: Automatic sync when connection restored
 * - 1s → 2s → 4s → 8s → 15s max backoff
 */

import { NetworkMonitor, getNetworkMonitor } from './NetworkMonitor';

export interface ReconnectionConfig {
  /** Initial backoff (ms) */
  initialBackoff?: number;
  /** Maximum backoff (ms) */
  maxBackoff?: number;
  /** Backoff multiplier */
  backoffMultiplier?: number;
  /** Max retry attempts */
  maxRetries?: number;
}

export type ReconnectionState = 'connected' | 'disconnected' | 'reconnecting';

export type ReconnectionCallback = (state: ReconnectionState) => void;

/**
 * Reconnection Service
 *
 * Manages reconnection with exponential backoff:
 * - 1s → 2s → 4s → 8s → 15s (max)
 * - Automatic retry on connection restore
 * - Jitter to prevent thundering herd
 * - Manual retry capability
 */
export class ReconnectionService {
  private config: Required<ReconnectionConfig>;
  private network: NetworkMonitor;
  private state: ReconnectionState = 'connected';
  private retryCount: number = 0;
  private currentBackoff: number = 0;
  private timeoutId: NodeJS.Timeout | null = null;
  private subscribers: Set<ReconnectionCallback> = new Set();

  constructor(config: ReconnectionConfig = {}) {
    this.config = {
      initialBackoff: config.initialBackoff || 1000,
      maxBackoff: config.maxBackoff || 15000,
      backoffMultiplier: config.backoffMultiplier || 2,
      maxRetries: config.maxRetries || Infinity,
    };

    this.network = getNetworkMonitor();

    // Subscribe to network changes
    this.network.onNetworkChange((status) => {
      if (status.isConnected) {
        this.onConnectionRestored();
      } else {
        this.onConnectionLost();
      }
    });
  }

  /**
   * Start reconnection monitoring
   */
  start(): void {
    if (this.network.isOnline()) {
      this.setState('connected');
    } else {
      this.onConnectionLost();
    }
  }

  /**
   * Stop reconnection monitoring
   */
  stop(): void {
    this.clearTimeout();
    this.subscribers.clear();
  }

  /**
   * Manual retry
   */
  async retry(): Promise<boolean> {
    this.clearTimeout();

    const success = await this.attemptReconnection();

    if (success) {
      this.retryCount = 0;
      this.currentBackoff = 0;
      this.setState('connected');
    }

    return success;
  }

  /**
   * Get current state
   */
  getState(): ReconnectionState {
    return this.state;
  }

  /**
   * Get current backoff
   */
  getCurrentBackoff(): number {
    return this.currentBackoff;
  }

  /**
   * Get retry count
   */
  getRetryCount(): number {
    return this.retryCount;
  }

  /**
   * Subscribe to state changes
   */
  onStateChange(callback: ReconnectionCallback): () => void {
    this.subscribers.add(callback);
    callback(this.getState());

    return () => {
      this.subscribers.delete(callback);
    };
  }

  /**
   * Connection lost handler
   */
  private onConnectionLost(): void {
    this.setState('disconnected');
    this.scheduleReconnect();
  }

  /**
   * Connection restored handler
   */
  private async onConnectionRestored(): Promise<void> {
    this.clearTimeout();

    const success = await this.attemptReconnection();

    if (success) {
      this.retryCount = 0;
      this.currentBackoff = 0;
      this.setState('connected');
    } else {
      this.onConnectionLost();
    }
  }

  /**
   * Schedule reconnection attempt
   */
  private scheduleReconnect(): void {
    if (this.retryCount >= this.config.maxRetries) {
      console.warn('[Reconnection] Max retries reached');
      return;
    }

    // Calculate backoff with jitter
    const baseBackoff = this.retryCount === 0
      ? this.config.initialBackoff
      : Math.min(
          this.config.initialBackoff * Math.pow(this.config.backoffMultiplier, this.retryCount),
          this.config.maxBackoff
        );

    // Add jitter (±25%)
    const jitter = baseBackoff * 0.25 * (Math.random() * 2 - 1);
    this.currentBackoff = Math.round(baseBackoff + jitter);

    this.setState('reconnecting');

    console.log(
      `[Reconnection] Scheduling retry ${this.retryCount + 1} in ${this.currentBackoff}ms`
    );

    this.timeoutId = setTimeout(async () => {
      const success = await this.attemptReconnection();

      if (success) {
        this.retryCount = 0;
        this.currentBackoff = 0;
        this.setState('connected');
      } else {
        this.retryCount++;
        this.scheduleReconnect();
      }
    }, this.currentBackoff);
  }

  /**
   * Attempt reconnection
   */
  private async attemptReconnection(): Promise<boolean> {
    try {
      // Verify network is actually available
      const isOnline = this.network.isOnline();

      if (!isOnline) {
        console.log('[Reconnection] Network not available');
        return false;
      }

      // Try to reach server
      // This would integrate with sync service
      console.log('[Reconnection] Attempting connection...');

      // Simulate connection attempt
      await new Promise((resolve) => setTimeout(resolve, 100));

      // In production, would actually try to sync
      return true;
    } catch (error) {
      console.error('[Reconnection] Connection attempt failed:', error);
      return false;
    }
  }

  /**
   * Set state and notify subscribers
   */
  private setState(newState: ReconnectionState): void {
    this.state = newState;

    for (const callback of this.subscribers) {
      try {
        callback(newState);
      } catch (error) {
        console.error('[Reconnection] Callback error:', error);
      }
    }
  }

  /**
   * Clear reconnection timeout
   */
  private clearTimeout(): void {
    if (this.timeoutId) {
      clearTimeout(this.timeoutId);
      this.timeoutId = null;
    }
  }
}

/**
 * Create reconnection service instance
 */
export function createReconnectionService(config?: ReconnectionConfig): ReconnectionService {
  return new ReconnectionService(config);
}

/**
 * Global reconnection service instance
 */
let globalReconnectionService: ReconnectionService | null = null;

/**
 * Get or create global reconnection service
 */
export function getReconnectionService(): ReconnectionService {
  if (!globalReconnectionService) {
    globalReconnectionService = createReconnectionService();
  }
  return globalReconnectionService;
}

/**
 * Initialize reconnection monitoring (call on app start)
 */
export function initializeReconnection(): void {
  const service = getReconnectionService();
  service.start();
}
