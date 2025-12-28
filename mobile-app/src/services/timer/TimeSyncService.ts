/**
 * Time Sync Service
 * Synchronizes local timer with server time for drift correction
 *
 * Constitution Requirements:
 * - US1-A1: <0.2s drift after 30 minutes of backgrounding
 * - US1-A1: <1s drift over 8 hours of operation
 * - TP-004: 100ms precision validation
 */

export interface TimeSyncConfig {
  /** API endpoint for server time */
  timeEndpoint?: string;
  /** How often to sync with server (ms) */
  syncInterval?: number;
  /** Maximum acceptable clock offset (ms) */
  maxClockOffset?: number;
  /** Callback when sync completes */
  onSync?: (offset: number) => void;
  /** Callback when sync fails */
  onSyncError?: (error: Error) => void;
}

export interface ServerTimeResponse {
  serverTime: string; // ISO timestamp
  requestTime: string; // When server received request
  responseTime?: string; // When server sent response
}

export interface TimeSyncResult {
  /** Clock offset in milliseconds (positive = local is ahead) */
  offset: number;
  /** Round-trip delay in milliseconds */
  rtt: number;
  /** Server timestamp */
  serverTime: Date;
  /** When sync was performed */
  syncedAt: Date;
}

/**
 * Time Sync Service
 *
 * Periodically synchronizes with server time to detect and correct
 * clock drift between device and server.
 */
export class TimeSyncService {
  private config: Required<TimeSyncConfig>;
  private syncIntervalId: ReturnType<typeof setInterval> | null = null;
  private currentOffset: number = 0;
  private lastSyncTime: Date | null = null;
  private lastRtt: number = 0;

  constructor(config: TimeSyncConfig = {}) {
    this.config = {
      timeEndpoint: config.timeEndpoint || '/api/time',
      syncInterval: config.syncInterval || 5 * 60 * 1000, // 5 minutes
      maxClockOffset: config.maxClockOffset || 1000, // 1 second
      onSync: config.onSync || (() => {}),
      onSyncError: config.onSyncError || (() => {}),
    };
  }

  /**
   * Start periodic time synchronization
   */
  public start(fetchFn: () => Promise<Response>): void {
    this.stop(); // Clear any existing interval

    // Do initial sync
    this.sync(fetchFn).catch((error) => {
      console.error('[TimeSyncService] Initial sync failed:', error);
    });

    // Start periodic sync
    this.syncIntervalId = setInterval(() => {
      this.sync(fetchFn).catch((error) => {
        console.error('[TimeSyncService] Periodic sync failed:', error);
        this.config.onSyncError(error as Error);
      });
    }, this.config.syncInterval);
  }

  /**
   * Stop time synchronization
   */
  public stop(): void {
    if (this.syncIntervalId !== null) {
      clearInterval(this.syncIntervalId);
      this.syncIntervalId = null;
    }
  }

  /**
   * Perform time synchronization with server
   */
  public async sync(fetchFn: () => Promise<Response>): Promise<TimeSyncResult> {
    const localBefore = Date.now();
    const localBeforePerf = performance.now();

    try {
      const response = await fetchFn();
      const localAfterPerf = performance.now();
      const localAfter = Date.now();

      if (!response.ok) {
        throw new Error(`Server returned ${response.status}`);
      }

      const data = (await response.json()) as ServerTimeResponse;
      const serverTime = new Date(data.serverTime);
      const requestTime = data.requestTime ? new Date(data.requestTime) : serverTime;

      // Calculate round-trip time
      const rtt = localAfter - localBefore;

      // Estimate one-way network delay (half of RTT)
      const networkDelay = rtt / 2;

      // Calculate offset: difference between server time and local time
      // adjusted for network delay
      const serverTimeAtLocalMidpoint = serverTime.getTime() + networkDelay;
      const offset = serverTimeAtLocalMidpoint - localBefore;

      // Update state
      this.currentOffset = offset;
      this.lastSyncTime = new Date();
      this.lastRtt = rtt;

      const result: TimeSyncResult = {
        offset,
        rtt,
        serverTime,
        syncedAt: this.lastSyncTime,
      };

      console.log('[TimeSyncService] Sync complete:', {
        offset: `${offset.toFixed(0)}ms`,
        rtt: `${rtt.toFixed(0)}ms`,
        serverTime: serverTime.toISOString(),
      });

      this.config.onSync(offset);

      return result;
    } catch (error) {
      console.error('[TimeSyncService] Sync failed:', error);
      throw error;
    }
  }

  /**
   * Get current clock offset (ms)
   * Positive value means local clock is ahead of server
   */
  public getOffset(): number {
    return this.currentOffset;
  }

  /**
   * Get server time based on current offset
   */
  public getServerTime(): Date {
    const localTime = Date.now();
    return new Date(localTime - this.currentOffset);
  }

  /**
   * Convert local timestamp to server time
   */
  public localToServerTime(localTime: Date): Date {
    return new Date(localTime.getTime() - this.currentOffset);
  }

  /**
   * Convert server timestamp to local time
   */
  public serverToLocalTime(serverTime: Date): Date {
    return new Date(serverTime.getTime() + this.currentOffset);
  }

  /**
   * Check if clock offset is within acceptable range
   */
  public isOffsetAcceptable(): boolean {
    return Math.abs(this.currentOffset) <= this.config.maxClockOffset;
  }

  /**
   * Get last sync time
   */
  public getLastSyncTime(): Date | null {
    return this.lastSyncTime;
  }

  /**
   * Get last round-trip time
   */
  public getLastRtt(): number {
    return this.lastRtt;
  }

  /**
   * Manually set offset (for testing or manual sync)
   */
  public setOffset(offset: number): void {
    this.currentOffset = offset;
    this.lastSyncTime = new Date();
    this.config.onSync(offset);
  }

  /**
   * Calculate drift since last sync
   */
  public calculateDrift(): number {
    if (!this.lastSyncTime) {
      return 0;
    }

    const timeSinceSync = Date.now() - this.lastSyncTime.getTime();
    // Assume linear drift (simplified - real drift may vary)
    return timeSinceSync;
  }

  /**
   * Predict server time at a future local time
   */
  public predictServerTime(localTime: Date): Date {
    return new Date(localTime.getTime() - this.currentOffset);
  }

  /**
   * Clean up resources
   */
  public destroy(): void {
    this.stop();
  }
}

/**
 * Create a time sync service instance
 */
export function createTimeSyncService(
  config?: TimeSyncConfig
): TimeSyncService {
  return new TimeSyncService(config);
}
