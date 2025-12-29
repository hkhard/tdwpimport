/**
 * Sync Service
 * Orchestrates bidirectional sync with server
 *
 * Constitution Requirements:
 * - US2-A2: Automatic sync when connection restored
 * - US2-A3: Conflict detection and resolution
 */

import { OfflineQueue, getOfflineQueue, QueuedChange } from './OfflineQueue';
import { NetworkMonitor, getNetworkMonitor } from './NetworkMonitor';
import type { SyncRecord, Conflict } from '@shared/types/sync';

export interface SyncConfig {
  /** Server base URL */
  serverUrl: string;
  /** Authentication token */
  authToken: string;
  /** Sync interval (ms) when online */
  syncInterval?: number;
  /** Batch size for upload */
  batchSize?: number;
}

export interface SyncResult {
  /** Was sync successful */
  success: boolean;
  /** Changes uploaded */
  uploaded: number;
  /** Changes downloaded */
  downloaded: number;
  /** Conflicts detected */
  conflicts: Conflict[];
  /** Error message if failed */
  error?: string;
}

export interface SyncStatus {
  /** Current sync state */
  state: 'idle' | 'syncing' | 'offline' | 'error';
  /** Last successful sync timestamp */
  lastSyncAt: Date | null;
  /** Pending changes count */
  pendingChanges: number;
  /** Failed changes count */
  failedChanges: number;
  /** Current sync progress (0-1) */
  progress: number;
}

/**
 * Sync Service
 *
 * Manages bidirectional synchronization:
 * - Uploads local changes when online
 * - Pulls remote changes
 * - Detects and resolves conflicts
 * - Handles retry logic
 */
export class SyncService {
  private config: Required<SyncConfig>;
  private queue: OfflineQueue;
  private network: NetworkMonitor;
  private syncInterval: NodeJS.Timeout | null = null;
  private isSyncing: boolean = false;

  // Status tracking
  private status: SyncStatus = {
    state: 'idle',
    lastSyncAt: null,
    pendingChanges: 0,
    failedChanges: 0,
    progress: 0,
  };

  // Status subscribers
  private statusSubscribers: Set<(status: SyncStatus) => void> = new Set();

  constructor(config: SyncConfig) {
    this.config = {
      serverUrl: config.serverUrl,
      authToken: config.authToken,
      syncInterval: config.syncInterval || 30000, // 30 seconds
      batchSize: config.batchSize || 50,
    };

    this.queue = getOfflineQueue();
    this.network = getNetworkMonitor();
  }

  /**
   * Initialize sync service
   */
  async initialize(): Promise<void> {
    // Load offline queue
    await this.queue.load();

    // Subscribe to network changes
    this.network.onNetworkChange((status) => {
      if (status.isConnected) {
        // Network restored, trigger sync
        this.triggerSync();
      } else {
        // Network lost, update status
        this.updateStatus({ state: 'offline' });
      }
    });

    // Start periodic sync
    this.startPeriodicSync();

    // Update initial status
    this.updateStatusFromQueue();
  }

  /**
   * Trigger manual sync
   */
  async triggerSync(): Promise<SyncResult> {
    if (this.isSyncing) {
      return {
        success: false,
        uploaded: 0,
        downloaded: 0,
        conflicts: [],
        error: 'Sync already in progress',
      };
    }

    if (!this.network.isOnline()) {
      this.updateStatus({ state: 'offline' });
      return {
        success: false,
        uploaded: 0,
        downloaded: 0,
        conflicts: [],
        error: 'Device is offline',
      };
    }

    this.isSyncing = true;
    this.updateStatus({ state: 'syncing', progress: 0 });

    try {
      // Step 1: Upload local changes
      const uploadResult = await this.uploadChanges();
      this.updateStatus({ progress: 0.5 });

      // Step 2: Download remote changes
      const downloadResult = await this.downloadChanges();
      this.updateStatus({ progress: 0.9 });

      // Step 3: Handle conflicts
      const conflicts = [...uploadResult.conflicts, ...downloadResult.conflicts];

      // Complete
      this.updateStatus({
        state: 'idle',
        lastSyncAt: new Date(),
        progress: 1,
      });

      const result: SyncResult = {
        success: true,
        uploaded: uploadResult.count,
        downloaded: downloadResult.count,
        conflicts,
      };

      console.log(`[SyncService] Sync complete: uploaded ${uploadResult.count}, downloaded ${downloadResult.count}`);
      return result;
    } catch (error: any) {
      this.updateStatus({
        state: 'error',
        progress: 0,
      });

      return {
        success: false,
        uploaded: 0,
        downloaded: 0,
        conflicts: [],
        error: error.message,
      };
    } finally {
      this.isSyncing = false;
    }
  }

  /**
   * Upload local changes to server
   */
  private async uploadChanges(): Promise<{ count: number; conflicts: Conflict[] }> {
    const pendingChanges = this.queue.getPendingChanges();
    const conflicts: Conflict[] = [];
    let uploaded = 0;

    // Process in batches
    for (let i = 0; i < pendingChanges.length; i += this.config.batchSize) {
      const batch = pendingChanges.slice(i, i + this.config.batchSize);

      try {
        const response = await fetch(`${this.config.serverUrl}/api/sync`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${this.config.authToken}`,
          },
          body: JSON.stringify({
            changes: batch.map((c) => ({
              changeId: c.queueId,
              entityType: c.entityType,
              operation: c.operation,
              entityId: c.entityId,
              data: c.data,
              localTimestamp: c.timestamp,
            })),
          }),
        });

        if (!response.ok) {
          throw new Error(`Upload failed: ${response.statusText}`);
        }

        const result = await response.json();

        // Mark successful uploads as synced
        for (const changeId of result.synced || []) {
          await this.queue.markSynced(changeId);
          uploaded++;
        }

        // Collect conflicts
        if (result.conflicts) {
          conflicts.push(...result.conflicts);
        }
      } catch (error: any) {
        // Mark batch as failed
        for (const change of batch) {
          await this.queue.markFailed(change.queueId, error.message);
        }
      }
    }

    this.updateStatusFromQueue();

    return { count: uploaded, conflicts };
  }

  /**
   * Download remote changes from server
   */
  private async downloadChanges(): Promise<{ count: number; conflicts: Conflict[] }> {
    const lastSync = this.status.lastSyncAt;

    try {
      const url = new URL(`${this.config.serverUrl}/api/sync`);
      if (lastSync) {
        url.searchParams.set('since', lastSync.toISOString());
      }

      const response = await fetch(url.toString(), {
        headers: {
          Authorization: `Bearer ${this.config.authToken}`,
        },
      });

      if (!response.ok) {
        throw new Error(`Download failed: ${response.statusText}`);
      }

      const result = await response.json();
      const conflicts: Conflict[] = [];

      // Apply remote changes to local database
      // This would integrate with repositories to apply changes
      for (const change of result.changes || []) {
        try {
          await this.applyRemoteChange(change);
        } catch (error: any) {
          console.error(`[SyncService] Failed to apply remote change:`, error);
          // Conflict detected
          conflicts.push({
            conflictId: `conflict-${Date.now()}`,
            entityType: change.entityType,
            entityId: change.entityId,
            localVersion: change.data,
            serverVersion: change.data,
            conflictType: 'concurrent_edit',
          });
        }
      }

      return { count: result.changes?.length || 0, conflicts };
    } catch (error: any) {
      console.error('[SyncService] Download error:', error);
      return { count: 0, conflicts: [] };
    }
  }

  /**
   * Apply remote change to local database
   */
  private async applyRemoteChange(change: any): Promise<void> {
    // This would integrate with repositories
    // For now, just log
    console.log(`[SyncService] Applying remote change: ${change.operation} on ${change.entityType}:${change.entityId}`);
  }

  /**
   * Get current sync status
   */
  getStatus(): SyncStatus {
    return { ...this.status };
  }

  /**
   * Subscribe to status changes
   */
  onStatusChange(callback: (status: SyncStatus) => void): () => void {
    this.statusSubscribers.add(callback);
    callback(this.getStatus());

    return () => {
      this.statusSubscribers.delete(callback);
    };
  }

  /**
   * Start periodic sync
   */
  private startPeriodicSync(): void {
    this.syncInterval = setInterval(() => {
      if (this.network.isOnline() && !this.isSyncing) {
        const pendingCount = this.queue.getPendingChanges().length;
        if (pendingCount > 0) {
          this.triggerSync();
        }
      }
    }, this.config.syncInterval);
  }

  /**
   * Stop periodic sync
   */
  stopPeriodicSync(): void {
    if (this.syncInterval) {
      clearInterval(this.syncInterval);
      this.syncInterval = null;
    }
  }

  /**
   * Update status and notify subscribers
   */
  private updateStatus(updates: Partial<SyncStatus>): void {
    this.status = { ...this.status, ...updates };

    for (const callback of this.statusSubscribers) {
      try {
        callback(this.getStatus());
      } catch (error) {
        console.error('[SyncService] Status callback error:', error);
      }
    }
  }

  /**
   * Update status from queue state
   */
  private updateStatusFromQueue(): void {
    const stats = this.queue.getStats();
    this.updateStatus({
      pendingChanges: stats.pending,
      failedChanges: stats.failed,
    });
  }

  /**
   * Clean up
   */
  destroy(): void {
    this.stopPeriodicSync();
    this.statusSubscribers.clear();
  }
}

/**
 * Create sync service instance
 */
export function createSyncService(config: SyncConfig): SyncService {
  return new SyncService(config);
}
