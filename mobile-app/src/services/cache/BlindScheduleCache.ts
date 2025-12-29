/**
 * Blind Schedule Cache Service
 * AsyncStorage-based caching for blind schedules with offline support
 *
 * Constitution Requirements:
 * - US2-A1: Offline tournament state viewing
 * - US8-A1: 500ms response time for cached data
 */

import AsyncStorage from '@react-native-async-storage/async-storage';
import type {
  BlindScheduleListItem,
  BlindScheduleWithMetadata,
} from '../../types/blindSchedule';

const CACHE_KEYS = {
  SCHEDULES_LIST: '@blind_schedules_list',
  SCHEDULE_DETAIL_PREFIX: '@blind_schedule_detail_',
  LAST_FETCH: '@blind_schedules_last_fetch',
};

const CACHE_EXPIRY_MS = 24 * 60 * 60 * 1000; // 24 hours

interface CacheMetadata {
  timestamp: number;
  version: string;
}

interface CachedData<T> {
  data: T;
  meta: CacheMetadata;
}

/**
 * Blind Schedule Cache Service
 */
export class BlindScheduleCache {
  /**
   * Cache schedules list
   */
  async setSchedulesList(schedules: BlindScheduleListItem[]): Promise<void> {
    const cacheData: CachedData<BlindScheduleListItem[]> = {
      data: schedules,
      meta: {
        timestamp: Date.now(),
        version: '1.0',
      },
    };

    await AsyncStorage.setItem(
      CACHE_KEYS.SCHEDULES_LIST,
      JSON.stringify(cacheData)
    );
    await AsyncStorage.setItem(
      CACHE_KEYS.LAST_FETCH,
      Date.now().toString()
    );
  }

  /**
   * Get cached schedules list
   */
  async getSchedulesList(): Promise<BlindScheduleListItem[] | null> {
    try {
      const cached = await AsyncStorage.getItem(CACHE_KEYS.SCHEDULES_LIST);
      if (!cached) return null;

      const parsed: CachedData<BlindScheduleListItem[]> = JSON.parse(cached);

      // Check cache expiry
      if (!this.isCacheValid(parsed.meta.timestamp)) {
        await this.clearSchedulesList();
        return null;
      }

      return parsed.data;
    } catch {
      return null;
    }
  }

  /**
   * Cache single schedule detail
   */
  async setScheduleDetail(
    scheduleId: string,
    schedule: BlindScheduleWithMetadata
  ): Promise<void> {
    const key = CACHE_KEYS.SCHEDULE_DETAIL_PREFIX + scheduleId;
    const cacheData: CachedData<BlindScheduleWithMetadata> = {
      data: schedule,
      meta: {
        timestamp: Date.now(),
        version: '1.0',
      },
    };

    await AsyncStorage.setItem(key, JSON.stringify(cacheData));
  }

  /**
   * Get cached schedule detail
   */
  async getScheduleDetail(
    scheduleId: string
  ): Promise<BlindScheduleWithMetadata | null> {
    try {
      const key = CACHE_KEYS.SCHEDULE_DETAIL_PREFIX + scheduleId;
      const cached = await AsyncStorage.getItem(key);
      if (!cached) return null;

      const parsed: CachedData<BlindScheduleWithMetadata> = JSON.parse(cached);

      // Check cache expiry
      if (!this.isCacheValid(parsed.meta.timestamp)) {
        await this.removeScheduleDetail(scheduleId);
        return null;
      }

      return parsed.data;
    } catch {
      return null;
    }
  }

  /**
   * Remove single schedule from cache
   */
  async removeScheduleDetail(scheduleId: string): Promise<void> {
    const key = CACHE_KEYS.SCHEDULE_DETAIL_PREFIX + scheduleId;
    await AsyncStorage.removeItem(key);
  }

  /**
   * Clear schedules list cache
   */
  async clearSchedulesList(): Promise<void> {
    await AsyncStorage.removeItem(CACHE_KEYS.SCHEDULES_LIST);
    await AsyncStorage.removeItem(CACHE_KEYS.LAST_FETCH);
  }

  /**
   * Clear all blind schedule cache
   */
  async clearAll(): Promise<void> {
    await AsyncStorage.removeItem(CACHE_KEYS.SCHEDULES_LIST);
    await AsyncStorage.removeItem(CACHE_KEYS.LAST_FETCH);

    // Clear all schedule detail caches
    const keys = await AsyncStorage.getAllKeys();
    const scheduleDetailKeys = keys.filter(k =>
      k.startsWith(CACHE_KEYS.SCHEDULE_DETAIL_PREFIX)
    );

    if (scheduleDetailKeys.length > 0) {
      await AsyncStorage.multiRemove(scheduleDetailKeys);
    }
  }

  /**
   * Get last fetch timestamp
   */
  async getLastFetch(): Promise<number | null> {
    try {
      const timestamp = await AsyncStorage.getItem(CACHE_KEYS.LAST_FETCH);
      return timestamp ? parseInt(timestamp, 10) : null;
    } catch {
      return null;
    }
  }

  /**
   * Check if cache is still valid
   */
  isCacheValid(timestamp: number): boolean {
    return Date.now() - timestamp < CACHE_EXPIRY_MS;
  }

  /**
   * Get cache size info
   */
  async getCacheInfo(): Promise<{
    listCached: boolean;
    detailCount: number;
    lastFetch: number | null;
    totalSize: number;
  }> {
    const keys = await AsyncStorage.getAllKeys();
    const scheduleDetailKeys = keys.filter(k =>
      k.startsWith(CACHE_KEYS.SCHEDULE_DETAIL_PREFIX)
    );

    const listData = await AsyncStorage.getItem(CACHE_KEYS.SCHEDULES_LIST);
    let totalSize = 0;

    if (listData) {
      totalSize += listData.length;
    }

    for (const key of scheduleDetailKeys) {
      const data = await AsyncStorage.getItem(key);
      if (data) {
        totalSize += data.length;
      }
    }

    return {
      listCached: listData !== null,
      detailCount: scheduleDetailKeys.length,
      lastFetch: await this.getLastFetch(),
      totalSize,
    };
  }

  /**
   * Invalidate stale cache entries
   */
  async invalidateStale(): Promise<void> {
    const keys = await AsyncStorage.getAllKeys();
    const scheduleDetailKeys = keys.filter(k =>
      k.startsWith(CACHE_KEYS.SCHEDULE_DETAIL_PREFIX)
    );

    const staleKeys: string[] = [];

    for (const key of scheduleDetailKeys) {
      try {
        const cached = await AsyncStorage.getItem(key);
        if (cached) {
          const parsed: CachedData<any> = JSON.parse(cached);
          if (!this.isCacheValid(parsed.meta.timestamp)) {
            staleKeys.push(key);
          }
        }
      } catch {
        staleKeys.push(key);
      }
    }

    if (staleKeys.length > 0) {
      await AsyncStorage.multiRemove(staleKeys);
    }
  }
}

// Export singleton
export const blindScheduleCache = new BlindScheduleCache();
