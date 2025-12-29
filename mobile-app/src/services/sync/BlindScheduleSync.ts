/**
 * Blind Schedule Sync Service
 * Synchronizes blind schedules between API and local cache
 *
 * Constitution Requirements:
 * - US2-A1: Offline tournament state viewing
 * - US8-A1: 500ms response time for cached data
 */

import { blindScheduleApi } from '../api/blindScheduleApi';
import { blindScheduleCache } from '../cache/BlindScheduleCache';
import { useBlindScheduleStore } from '../../stores/blindScheduleStore';
import type {
  BlindScheduleWithMetadata,
  BlindScheduleListItem,
  BlindScheduleFormData,
} from '../../types/blindSchedule';

/**
 * Sync status for UI feedback
 */
export interface SyncStatus {
  isSyncing: boolean;
  lastSync: number | null;
  error: string | null;
}

/**
 * Blind Schedule Sync Service
 */
export class BlindScheduleSync {
  private isSyncing: boolean = false;
  private lastSync: number | null = null;
  private error: string | null = null;

  /**
   * Get current sync status
   */
  getStatus(): SyncStatus {
    return {
      isSyncing: this.isSyncing,
      lastSync: this.lastSync,
      error: this.error,
    };
  }

  /**
   * Sync schedules list from API
   * Falls back to cache if offline
   */
  async syncSchedulesList(includeDefaults = true): Promise<BlindScheduleListItem[]> {
    this.isSyncing = true;
    this.error = null;

    try {
      // Try to fetch from API
      const schedules = await blindScheduleApi.getAllBlindSchedules(includeDefaults);

      // Cache the result
      await blindScheduleCache.setSchedulesList(schedules);

      // Update store
      const store = useBlindScheduleStore.getState();
      store.schedules = schedules;
      store.lastFetch = Date.now();

      this.lastSync = Date.now();
      return schedules;
    } catch (error) {
      console.error('[BlindScheduleSync] API fetch failed, using cache:', error);

      // Fall back to cache
      const cached = await blindScheduleCache.getSchedulesList();
      if (cached) {
        this.error = 'Offline - using cached data';

        // Update store with cached data
        const store = useBlindScheduleStore.getState();
        store.schedules = cached;

        return cached;
      }

      this.error = 'Offline - no cached data available';
      throw new Error('Failed to fetch schedules and no cache available');
    } finally {
      this.isSyncing = false;
    }
  }

  /**
   * Sync single schedule from API
   */
  async syncScheduleDetail(scheduleId: string): Promise<BlindScheduleWithMetadata> {
    this.isSyncing = true;
    this.error = null;

    try {
      // Try to fetch from API
      const schedule = await blindScheduleApi.getBlindSchedule(scheduleId);

      // Cache the result
      await blindScheduleCache.setScheduleDetail(scheduleId, schedule);

      // Update store if this is the selected schedule
      const store = useBlindScheduleStore.getState();
      if (store.selectedSchedule?.id === schedule.id) {
        store.selectedSchedule = schedule;
      }

      this.lastSync = Date.now();
      return schedule;
    } catch (error) {
      console.error('[BlindScheduleSync] Schedule fetch failed, using cache:', error);

      // Fall back to cache
      const cached = await blindScheduleCache.getScheduleDetail(scheduleId);
      if (cached) {
        this.error = 'Offline - using cached data';

        // Update store if this is the selected schedule
        const store = useBlindScheduleStore.getState();
        if (store.selectedSchedule?.id === scheduleId) {
          store.selectedSchedule = cached;
        }

        return cached;
      }

      this.error = 'Offline - no cached data available';
      throw new Error('Failed to fetch schedule and no cache available');
    } finally {
      this.isSyncing = false;
    }
  }

  /**
   * Create schedule (API only, no cache until synced)
   */
  async createSchedule(formData: BlindScheduleFormData): Promise<BlindScheduleWithMetadata> {
    this.isSyncing = true;
    this.error = null;

    try {
      const newSchedule = await blindScheduleApi.createBlindSchedule(formData);

      // Cache the new schedule
      await blindScheduleCache.setScheduleDetail(newSchedule.id, newSchedule);

      // Invalidate list cache to force refresh
      await blindScheduleCache.clearSchedulesList();

      this.lastSync = Date.now();
      return newSchedule;
    } catch (error) {
      this.error = 'Failed to create schedule';
      throw error;
    } finally {
      this.isSyncing = false;
    }
  }

  /**
   * Update schedule (API only, no cache until synced)
   */
  async updateSchedule(
    scheduleId: string,
    formData: Partial<BlindScheduleFormData>
  ): Promise<BlindScheduleWithMetadata> {
    this.isSyncing = true;
    this.error = null;

    try {
      const updatedSchedule = await blindScheduleApi.updateBlindSchedule(
        scheduleId,
        formData
      );

      // Update cache
      await blindScheduleCache.setScheduleDetail(scheduleId, updatedSchedule);

      // Invalidate list cache
      await blindScheduleCache.clearSchedulesList();

      // Update store
      const store = useBlindScheduleStore.getState();
      if (store.selectedSchedule?.id === scheduleId) {
        store.selectedSchedule = updatedSchedule;
      }

      this.lastSync = Date.now();
      return updatedSchedule;
    } catch (error) {
      this.error = 'Failed to update schedule';
      throw error;
    } finally {
      this.isSyncing = false;
    }
  }

  /**
   * Delete schedule (API only)
   */
  async deleteSchedule(scheduleId: string): Promise<void> {
    this.isSyncing = true;
    this.error = null;

    try {
      await blindScheduleApi.deleteBlindSchedule(scheduleId);

      // Remove from cache
      await blindScheduleCache.removeScheduleDetail(scheduleId);

      // Invalidate list cache
      await blindScheduleCache.clearSchedulesList();

      // Update store
      const store = useBlindScheduleStore.getState();
      store.schedules = store.schedules.filter(s => s.id !== scheduleId);
      if (store.selectedSchedule?.id === scheduleId) {
        store.selectedSchedule = null;
      }

      this.lastSync = Date.now();
    } catch (error) {
      this.error = 'Failed to delete schedule';
      throw error;
    } finally {
      this.isSyncing = false;
    }
  }

  /**
   * Background sync - refreshes cached data periodically
   */
  async backgroundSync(): Promise<void> {
    if (this.isSyncing) {
      return; // Already syncing
    }

    try {
      await this.syncSchedulesList(true);
    } catch (error) {
      console.error('[BlindScheduleSync] Background sync failed:', error);
      // Don't update error state for background sync failures
    }
  }

  /**
   * Clear all cached data
   */
  async clearCache(): Promise<void> {
    await blindScheduleCache.clearAll();

    // Reset store
    const store = useBlindScheduleStore.getState();
    store.schedules = [];
    store.selectedSchedule = null;
    store.lastFetch = null;

    this.lastSync = null;
    this.error = null;
  }

  /**
   * Initialize sync on app startup
   * Loads from cache first, then refreshes in background
   */
  async initialize(): Promise<void> {
    // Try to load from cache immediately
    const cached = await blindScheduleCache.getSchedulesList();
    if (cached) {
      const store = useBlindScheduleStore.getState();
      store.schedules = cached;
    }

    // Background refresh
    setTimeout(() => {
      this.backgroundSync();
    }, 100);
  }
}

// Export singleton
export const blindScheduleSync = new BlindScheduleSync();
