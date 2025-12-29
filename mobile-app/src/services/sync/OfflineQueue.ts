/**
 * Offline Queue Manager
 * Persists changes to AsyncStorage when offline, queues for sync
 *
 * Constitution Requirements:
 * - US2-A1: Offline tournament CRUD operations
 * - US2-A2: Automatic sync when connection restored
 * - US2-A3: Conflict detection and resolution
 */

import AsyncStorage from '@react-native-async-storage/async-storage';
import type { SyncChange } from '@shared/types/sync';

export interface QueuedChange extends SyncChange {
  /** Queue entry ID */
  queueId: string;
  /** Number of retry attempts */
  retryCount: number;
  /** When this change was queued */
  queuedAt: Date;
  /** Last sync attempt timestamp */
  lastSyncAttempt?: Date;
  /** Error from last sync attempt */
  lastError?: string;
}

export interface QueueStats {
  /** Total changes in queue */
  total: number;
  /** Changes pending sync */
  pending: number;
  /** Changes that failed sync */
  failed: number;
  /** Oldest change in queue age (ms) */
  oldestAgeMs: number;
}

const QUEUE_KEY = '@tdp:offline_queue';
const MAX_QUEUE_SIZE = 1000;
const MAX_RETRY_COUNT = 5;

/**
 * Offline Queue Manager
 *
 * Manages local changes made while offline:
 * - Persists changes to AsyncStorage
 * - Tracks retry attempts
 * - Provides queue for sync service
 * - Handles queue overflow
 */
export class OfflineQueue {
  private queue: QueuedChange[] = [];
  private isLoaded: boolean = false;

  /**
   * Initialize queue from storage
   */
  async load(): Promise<void> {
    try {
      const data = await AsyncStorage.getItem(QUEUE_KEY);
      if (data) {
        const parsed = JSON.parse(data);
        this.queue = parsed.map((item: any) => ({
          ...item,
          queuedAt: new Date(item.queuedAt),
          lastSyncAttempt: item.lastSyncAttempt ? new Date(item.lastSyncAttempt) : undefined,
        }));
      }
      this.isLoaded = true;
      console.log(`[OfflineQueue] Loaded ${this.queue.length} changes from storage`);
    } catch (error) {
      console.error('[OfflineQueue] Failed to load queue:', error);
      this.queue = [];
      this.isLoaded = true;
    }
  }

  /**
   * Enqueue a change for sync
   */
  async enqueue(change: Omit<QueuedChange, 'queueId' | 'retryCount' | 'queuedAt'>): Promise<void> {
    if (!this.isLoaded) {
      await this.load();
    }

    // Check queue size limit
    if (this.queue.length >= MAX_QUEUE_SIZE) {
      // Remove oldest failed item first, then oldest pending
      const failedIndex = this.queue.findIndex((c) => c.retryCount >= MAX_RETRY_COUNT);
      if (failedIndex >= 0) {
        this.queue.splice(failedIndex, 1);
      } else {
        this.queue.shift();
      }
    }

    const queuedChange: QueuedChange = {
      ...change,
      queueId: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      retryCount: 0,
      queuedAt: new Date(),
    };

    this.queue.push(queuedChange);
    await this.persist();

    console.log(`[OfflineQueue] Enqueued ${change.operation} on ${change.entityType}:${change.entityId}`);
  }

  /**
   * Get all pending changes (not at max retry)
   */
  getPendingChanges(): QueuedChange[] {
    return this.queue.filter((c) => c.retryCount < MAX_RETRY_COUNT);
  }

  /**
   * Get failed changes (at max retry)
   */
  getFailedChanges(): QueuedChange[] {
    return this.queue.filter((c) => c.retryCount >= MAX_RETRY_COUNT);
  }

  /**
   * Mark change as synced (remove from queue)
   */
  async markSynced(queueId: string): Promise<void> {
    const index = this.queue.findIndex((c) => c.queueId === queueId);
    if (index >= 0) {
      this.queue.splice(index, 1);
      await this.persist();
    }
  }

  /**
   * Mark change as failed (increment retry count)
   */
  async markFailed(queueId: string, error: string): Promise<void> {
    const change = this.queue.find((c) => c.queueId === queueId);
    if (change) {
      change.retryCount++;
      change.lastSyncAttempt = new Date();
      change.lastError = error;
      await this.persist();
    }
  }

  /**
   * Reset retry count for a change (for manual retry)
   */
  async resetRetryCount(queueId: string): Promise<void> {
    const change = this.queue.find((c) => c.queueId === queueId);
    if (change) {
      change.retryCount = 0;
      change.lastError = undefined;
      await this.persist();
    }
  }

  /**
   * Get queue statistics
   */
  getStats(): QueueStats {
    const now = Date.now();
    const oldest = this.queue[0];

    return {
      total: this.queue.length,
      pending: this.queue.filter((c) => c.retryCount < MAX_RETRY_COUNT).length,
      failed: this.queue.filter((c) => c.retryCount >= MAX_RETRY_COUNT).length,
      oldestAgeMs: oldest ? now - oldest.queuedAt.getTime() : 0,
    };
  }

  /**
   * Clear all queued changes
   */
  async clear(): Promise<void> {
    this.queue = [];
    await this.persist();
  }

  /**
   * Get change by queue ID
   */
  getChange(queueId: string): QueuedChange | undefined {
    return this.queue.find((c) => c.queueId === queueId);
  }

  /**
   * Get changes by entity type
   */
  getChangesByType(entityType: string): QueuedChange[] {
    return this.queue.filter((c) => c.entityType === entityType);
  }

  /**
   * Get changes by operation
   */
  getChangesByOperation(operation: string): QueuedChange[] {
    return this.queue.filter((c) => c.operation === operation);
  }

  /**
   * Persist queue to storage
   */
  private async persist(): Promise<void> {
    try {
      const data = JSON.stringify(this.queue);
      await AsyncStorage.setItem(QUEUE_KEY, data);
    } catch (error) {
      console.error('[OfflineQueue] Failed to persist queue:', error);
    }
  }

  /**
   * Remove all changes for a specific entity
   */
  async removeEntityChanges(entityType: string, entityId: string): Promise<void> {
    this.queue = this.queue.filter(
      (c) => !(c.entityType === entityType && c.entityId === entityId)
    );
    await this.persist();
  }
}

/**
 * Create offline queue instance
 */
export function createOfflineQueue(): OfflineQueue {
  return new OfflineQueue();
}

/**
 * Global queue instance
 */
let globalQueue: OfflineQueue | null = null;

/**
 * Get or create global offline queue
 */
export function getOfflineQueue(): OfflineQueue {
  if (!globalQueue) {
    globalQueue = createOfflineQueue();
  }
  return globalQueue;
}
