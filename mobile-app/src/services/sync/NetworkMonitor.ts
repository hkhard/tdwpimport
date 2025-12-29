/**
 * Network Monitor Service
 * Detects connectivity changes and provides network status
 *
 * Constitution Requirements:
 * - US2-A2: Automatic sync when connection restored
 * - React to network state changes
 */

import { NetInfo, getState } from '@react-native-community/netinfo';
import type { NetInfoState } from '@react-native-community/netinfo';

export interface NetworkStatus {
  /** Is device connected to network */
  isConnected: boolean;
  /** Connection type (wifi, cellular, none) */
  type: string;
  /** Is connection expensive (cellular) */
  isExpensive: boolean;
  /** Is internet reachable */
  isInternetReachable: boolean;
  /** Last check timestamp */
  lastChecked: Date;
}

export type NetworkCallback = (status: NetworkStatus) => void;

/**
 * Network Monitor Service
 *
 * Monitors network connectivity:
 * - Detects online/offline transitions
 * - Notifies subscribers of changes
 * - Provides current status
 * - Tracks connection quality
 */
export class NetworkMonitor {
  private currentStatus: NetworkStatus;
  private subscribers: Set<NetworkCallback> = new Set();
  private unsubscribe: (() => void) | null = null;
  private isMonitoring: boolean = false;

  constructor(initialStatus?: Partial<NetworkStatus>) {
    this.currentStatus = {
      isConnected: initialStatus?.isConnected ?? false,
      type: initialStatus?.type ?? 'none',
      isExpensive: initialStatus?.isExpensive ?? false,
      isInternetReachable: initialStatus?.isInternetReachable ?? false,
      lastChecked: new Date(),
    };
  }

  /**
   * Start monitoring network changes
   */
  async start(): Promise<void> {
    if (this.isMonitoring) {
      return;
    }

    // Get initial state
    try {
      const initialState = await getState();
      this.updateStatus(initialState);
    } catch (error) {
      console.error('[NetworkMonitor] Failed to get initial state:', error);
    }

    // Subscribe to changes
    this.unsubscribe = NetInfo.addEventListener((state) => {
      this.updateStatus(state);
    });

    this.isMonitoring = true;
    console.log('[NetworkMonitor] Started monitoring');
  }

  /**
   * Stop monitoring network changes
   */
  stop(): void {
    if (this.unsubscribe) {
      this.unsubscribe();
      this.unsubscribe = null;
    }

    this.subscribers.clear();
    this.isMonitoring = false;

    console.log('[NetworkMonitor] Stopped monitoring');
  }

  /**
   * Get current network status
   */
  getStatus(): NetworkStatus {
    return { ...this.currentStatus };
  }

  /**
   * Check if device is online
   */
  isOnline(): boolean {
    return this.currentStatus.isConnected && this.currentStatus.isInternetReachable;
  }

  /**
   * Check if device is offline
   */
  isOffline(): boolean {
    return !this.isOnline();
  }

  /**
   * Check if connection is expensive (cellular)
   */
  isExpensive(): boolean {
    return this.currentStatus.isExpensive;
  }

  /**
   * Subscribe to network changes
   */
  onNetworkChange(callback: NetworkCallback): () => void {
    this.subscribers.add(callback);

    // Immediately call with current status
    callback(this.getStatus());

    // Return unsubscribe function
    return () => {
      this.subscribers.delete(callback);
    };
  }

  /**
   * Manually refresh network status
   */
  async refresh(): Promise<NetworkStatus> {
    try {
      const state = await getState();
      this.updateStatus(state);
      return this.getStatus();
    } catch (error) {
      console.error('[NetworkMonitor] Failed to refresh status:', error);
      return this.getStatus();
    }
  }

  /**
   * Update status from NetInfo state
   */
  private updateStatus(state: NetInfoState): void {
    const previousStatus = { ...this.currentStatus };

    this.currentStatus = {
      isConnected: state.isConnected ?? false,
      type: state.type ?? 'none',
      isExpensive: state.details?.isConnectionExpensive ?? false,
      isInternetReachable: state.isInternetReachable ?? false,
      lastChecked: new Date(),
    };

    // Notify subscribers if status changed
    const statusChanged =
      previousStatus.isConnected !== this.currentStatus.isConnected ||
      previousStatus.isInternetReachable !== this.currentStatus.isInternetReachable;

    if (statusChanged) {
      console.log(
        `[NetworkMonitor] Status changed: ${this.currentStatus.isConnected ? 'ONLINE' : 'OFFLINE'}`
      );

      for (const callback of this.subscribers) {
        try {
          callback(this.getStatus());
        } catch (error) {
          console.error('[NetworkMonitor] Callback error:', error);
        }
      }
    }
  }

  /**
   * Get connection quality score (0-100)
   */
  getConnectionQuality(): number {
    if (!this.isOnline()) {
      return 0;
    }

    // Simple quality scoring
    let score = 50; // Base score for being online

    if (this.currentStatus.type === 'wifi') {
      score += 30; // WiFi is better than cellular
    }

    if (!this.currentStatus.isExpensive) {
      score += 20; // Non-expensive is better
    }

    return Math.min(score, 100);
  }
}

/**
 * Create network monitor instance
 */
export function createNetworkMonitor(initialStatus?: Partial<NetworkStatus>): NetworkMonitor {
  return new NetworkMonitor(initialStatus);
}

/**
 * Global network monitor instance
 */
let globalMonitor: NetworkMonitor | null = null;

/**
 * Get or create global network monitor
 */
export function getNetworkMonitor(): NetworkMonitor {
  if (!globalMonitor) {
    globalMonitor = createNetworkMonitor();
  }
  return globalMonitor;
}

/**
 * Initialize network monitoring (call on app start)
 */
export async function initializeNetworkMonitoring(): Promise<void> {
  const monitor = getNetworkMonitor();
  await monitor.start();
}
