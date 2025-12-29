/**
 * Offline Sync Queue Utilities
 * Manages write-ahead queue for blind scheme CRUD operations
 */

import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import type { SyncQueueItem } from '@shared/types/timer';

const SYNC_QUEUE_KEY = '@blind_scheme_sync_queue';
const MAX_QUEUE_SIZE = 100;

/**
 * Add operation to sync queue
 */
export async function addToQueue(item: SyncQueueItem): Promise<void> {
  try {
    const queue = await getQueue();

    // Prevent queue from growing too large
    if (queue.length >= MAX_QUEUE_SIZE) {
      console.warn('[SyncQueue] Queue full, removing oldest item');
      queue.shift();
    }

    queue.push(item);
    await AsyncStorage.setItem(SYNC_QUEUE_KEY, JSON.stringify(queue));

    console.log(`[SyncQueue] Added ${item.type} for scheme ${item.schemeId}`);
  } catch (error) {
    console.error('[SyncQueue] Error adding to queue:', error);
  }
}

/**
 * Get all items from sync queue
 */
export async function getQueue(): Promise<SyncQueueItem[]> {
  try {
    const queueData = await AsyncStorage.getItem(SYNC_QUEUE_KEY);
    if (!queueData) return [];
    return JSON.parse(queueData);
  } catch {
    return [];
  }
}

/**
 * Clear sync queue
 */
export async function clearQueue(): Promise<void> {
  try {
    await AsyncStorage.removeItem(SYNC_QUEUE_KEY);
    console.log('[SyncQueue] Queue cleared');
  } catch (error) {
    console.error('[SyncQueue] Error clearing queue:', error);
  }
}

/**
 * Remove specific item from queue
 */
export async function removeFromQueue(timestamp: number): Promise<void> {
  try {
    const queue = await getQueue();
    const filtered = queue.filter((item) => item.timestamp !== timestamp);
    await AsyncStorage.setItem(SYNC_QUEUE_KEY, JSON.stringify(filtered));
  } catch (error) {
    console.error('[SyncQueue] Error removing from queue:', error);
  }
}

/**
 * Check if device is online
 */
export async function isOnline(): Promise<boolean> {
  try {
    const state = await NetInfo.fetch();
    return state.isConnected ?? false;
  } catch {
    return false;
  }
}

/**
 * Process sync queue (called when back online)
 * @param apiClient - API client with createScheme, updateScheme, deleteScheme methods
 * @returns Number of items processed
 */
export async function flushQueue(
  apiClient: {
    createScheme: (input: any) => Promise<any>;
    updateScheme: (id: string, input: any) => Promise<any>;
    deleteScheme: (id: string) => Promise<void>;
  }
): Promise<{ processed: number; failed: number }> {
  const queue = await getQueue();
  let processed = 0;
  let failed = 0;

  if (queue.length === 0) {
    return { processed: 0, failed: 0 };
  }

  console.log(`[SyncQueue] Processing ${queue.length} items`);

  // Process queue in order (FIFO)
  for (const item of queue) {
    try {
      switch (item.type) {
        case 'CREATE_SCHEME':
          if (item.data) {
            await apiClient.createScheme(item.data);
            processed++;
          }
          break;

        case 'UPDATE_SCHEME':
          if (item.data) {
            await apiClient.updateScheme(item.schemeId, item.data);
            processed++;
          }
          break;

        case 'DELETE_SCHEME':
          await apiClient.deleteScheme(item.schemeId);
          processed++;
          break;

        default:
          console.warn(`[SyncQueue] Unknown item type: ${(item as any).type}`);
          failed++;
      }

      // Remove processed item from queue
      await removeFromQueue(item.timestamp);
    } catch (error) {
      console.error(`[SyncQueue] Failed to process ${item.type} for scheme ${item.schemeId}:`, error);
      failed++;
    }
  }

  console.log(`[SyncQueue] Finished: ${processed} processed, ${failed} failed`);

  return { processed, failed };
}

/**
 * Get queue size
 */
export async function getQueueSize(): Promise<number> {
  const queue = await getQueue();
  return queue.length;
}

/**
 * Check if queue has items
 */
export async function hasPendingItems(): Promise<boolean> {
  const size = await getQueueSize();
  return size > 0;
}
