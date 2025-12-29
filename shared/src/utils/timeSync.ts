/**
 * Time Synchronization Utilities
 * Handles drift correction and NTP-style server time sync
 */

export interface TimeSyncResult {
  localTime: Date;
  serverTime: Date;
  offset: number; // ms difference (server - local)
  roundTripTime: number; // ms
}

export interface DriftCorrection {
  correctedTime: Date;
  driftOffset: number;
  confidence: number; // 0-1
}

export class TimeSync {
  private offset: number = 0; // server - local in ms
  private roundTripTime: number = 0;
  private syncHistory: TimeSyncResult[] = [];
  private maxHistorySize: number = 10;

  /**
   * Perform NTP-style time sync with server
   * Returns server time adjusted for network latency
   */
  async syncWithServer(fetchServerTime: () => Promise<Date>): Promise<TimeSyncResult> {
    const t1 = Date.now(); // Local time before request

    const serverTime = await fetchServerTime();

    const t4 = Date.now(); // Local time after response

    // Calculate round trip time
    this.roundTripTime = t4 - t1;

    // Offset = serverTime - (t1 + roundTrip/2)
    // This assumes symmetric network latency
    const estimatedLocalTimeAtServer = t1 + this.roundTripTime / 2;
    this.offset = serverTime.getTime() - estimatedLocalTimeAtServer;

    const result: TimeSyncResult = {
      localTime: new Date(t1),
      serverTime,
      offset: this.offset,
      roundTripTime: this.roundTripTime,
    };

    // Store in history
    this.syncHistory.push(result);
    if (this.syncHistory.length > this.maxHistorySize) {
      this.syncHistory.shift();
    }

    return result;
  }

  /**
   * Get current server-adjusted time
   */
  getServerTime(): Date {
    return new Date(Date.now() + this.offset);
  }

  /**
   * Get local time
   */
  getLocalTime(): Date {
    return new Date();
  }

  /**
   * Get current offset
   */
  getOffset(): number {
    return this.offset;
  }

  /**
   * Convert local timestamp to server time
   */
  localToServer(localTime: Date): Date {
    return new Date(localTime.getTime() + this.offset);
  }

  /**
   * Convert server timestamp to local time
   */
  serverToLocal(serverTime: Date): Date {
    return new Date(serverTime.getTime() - this.offset);
  }

  /**
   * Calculate drift correction based on recent sync history
   */
  calculateDriftCorrection(): DriftCorrection {
    if (this.syncHistory.length === 0) {
      return {
        correctedTime: this.getLocalTime(),
        driftOffset: 0,
        confidence: 0,
      };
    }

    // Average offset from recent syncs
    const totalOffset = this.syncHistory.reduce((sum, sync) => sum + sync.offset, 0);
    const avgOffset = totalOffset / this.syncHistory.length;

    // Calculate standard deviation for confidence
    const variance =
      this.syncHistory.reduce((sum, sync) => sum + Math.pow(sync.offset - avgOffset, 2), 0) /
      this.syncHistory.length;
    const stdDev = Math.sqrt(variance);

    // Confidence based on consistency (lower stdDev = higher confidence)
    const confidence = Math.max(0, Math.min(1, 1 - stdDev / 1000));

    return {
      correctedTime: new Date(Date.now() + avgOffset),
      driftOffset: avgOffset,
      confidence,
    };
  }

  /**
   * Get sync statistics
   */
  getStats(): {
    averageOffset: number;
    minOffset: number;
    maxOffset: number;
    averageRoundTrip: number;
    syncCount: number;
  } {
    if (this.syncHistory.length === 0) {
      return {
        averageOffset: 0,
        minOffset: 0,
        maxOffset: 0,
        averageRoundTrip: 0,
        syncCount: 0,
      };
    }

    const offsets = this.syncHistory.map((s) => s.offset);
    const roundTrips = this.syncHistory.map((s) => s.roundTripTime);

    return {
      averageOffset: offsets.reduce((a, b) => a + b, 0) / offsets.length,
      minOffset: Math.min(...offsets),
      maxOffset: Math.max(...offsets),
      averageRoundTrip: roundTrips.reduce((a, b) => a + b, 0) / roundTrips.length,
      syncCount: this.syncHistory.length,
    };
  }

  /**
   * Clear sync history
   */
  clearHistory(): void {
    this.syncHistory = [];
    this.offset = 0;
    this.roundTripTime = 0;
  }

  /**
   * Estimate time elapsed on server for a local time range
   * Accounts for drift during the interval
   */
  estimateServerElapsedTime(startTime: Date, endTime: Date): number {
    const serverStart = this.localToServer(startTime);
    const serverEnd = this.localToServer(endTime);
    return serverEnd.getTime() - serverStart.getTime();
  }

  /**
   * Convert centiseconds to server-adjusted centiseconds
   * Used for timer precision
   */
  centisecondsToServer(localCentiseconds: number): number {
    const now = Date.now();
    const serverNow = this.getServerTime().getTime();
    const adjustment = serverNow - now;

    // Convert ms adjustment to centiseconds (rounded)
    return localCentiseconds + Math.round(adjustment / 10);
  }
}

/**
 * Default time sync instance
 */
export const timeSync = new TimeSync();
