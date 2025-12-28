/**
 * Blind Schedule API Client
 * Handles all blind schedule API calls to the controller
 */

import AsyncStorage from '@react-native-async-storage/async-storage';
import type {
  BlindScheduleWithMetadata,
  BlindScheduleListItem,
  BlindScheduleFormData,
  BlindScheduleApiResponse,
  BlindScheduleListApiResponse,
} from '../../types/blindSchedule';
import { API_BASE_URL } from '../../config/api';

// Cache key for blind schedules
const BLIND_SCHEDULES_CACHE_KEY = '@blind_schedules';
const CACHE_EXPIRY_MS = 24 * 60 * 60 * 1000; // 24 hours

/**
 * Blind Schedule API Client
 */
export class BlindScheduleApiService {
  /**
   * Fetch all blind schedules from API
   * Uses local cache if available and fresh
   */
  async getAllBlindSchedules(includeDefaults = true): Promise<BlindScheduleListItem[]> {
    try {
      // Try cache first
      const cached = await this.getCachedSchedules();
      if (cached && this.isCacheValid(cached)) {
        console.log('[BlindScheduleAPI] Using cached schedules');
        return cached.schedules;
      }

      // Fetch from API
      const url = `${API_BASE_URL}/blind-schedules?includeDefault=${includeDefaults}`;
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          // Authorization header would be added here if auth is required
        },
      });

      if (!response.ok) {
        throw new Error(`API error: ${response.status} ${response.statusText}`);
      }

      const result: BlindScheduleListApiResponse = await response.json();

      if (!result.success || !result.data) {
        throw new Error(result.error || 'Failed to fetch blind schedules');
      }

      // Cache the result
      await this.cacheSchedules(result.data);

      return result.data;
    } catch (error) {
      console.error('[BlindScheduleAPI] Error fetching schedules:', error);

      // Fall back to cache if available
      const cached = await this.getCachedSchedules();
      if (cached) {
        console.log('[BlindScheduleAPI] Falling back to cached schedules');
        return cached.schedules;
      }

      throw error;
    }
  }

  /**
   * Get single blind schedule by ID
   */
  async getBlindSchedule(id: string): Promise<BlindScheduleWithMetadata> {
    const response = await fetch(`${API_BASE_URL}/blind-schedules/${id}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status} ${response.statusText}`);
    }

    const result: BlindScheduleApiResponse = await response.json();

    if (!result.success || !result.data) {
      throw new Error(result.error || 'Failed to fetch blind schedule');
    }

    return result.data;
  }

  /**
   * Create new blind schedule
   */
  async createBlindSchedule(formData: BlindScheduleFormData): Promise<BlindScheduleWithMetadata> {
    const response = await fetch(`${API_BASE_URL}/blind-schedules`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(formData),
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status} ${response.statusText}`);
    }

    const result: BlindScheduleApiResponse = await response.json();

    if (!result.success || !result.data) {
      throw new Error(result.error || 'Failed to create blind schedule');
    }

    // Invalidate cache
    await this.invalidateCache();

    return result.data;
  }

  /**
   * Update existing blind schedule
   */
  async updateBlindSchedule(
    id: string,
    formData: Partial<BlindScheduleFormData>
  ): Promise<BlindScheduleWithMetadata> {
    const response = await fetch(`${API_BASE_URL}/blind-schedules/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(formData),
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status} ${response.statusText}`);
    }

    const result: BlindScheduleApiResponse = await response.json();

    if (!result.success || !result.data) {
      throw new Error(result.error || 'Failed to update blind schedule');
    }

    // Invalidate cache
    await this.invalidateCache();

    return result.data;
  }

  /**
   * Delete blind schedule
   */
  async deleteBlindSchedule(id: string): Promise<void> {
    const response = await fetch(`${API_BASE_URL}/blind-schedules/${id}`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
      },
    });

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ error: 'Unknown error' }));
      throw new Error(errorData.error || 'Failed to delete blind schedule');
    }

    // Invalidate cache
    await this.invalidateCache();
  }

  /**
   * Get levels for a blind schedule
   */
  async getBlindLevels(scheduleId: string) {
    const response = await fetch(`${API_BASE_URL}/blind-schedules/${scheduleId}/levels`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status} ${response.statusText}`);
    }

    const result = await response.json();

    if (!result.success) {
      throw new Error(result.error || 'Failed to fetch blind levels');
    }

    return result.data;
  }

  /**
   * Get current blind level for a tournament
   */
  async getTournamentBlindLevel(tournamentId: string) {
    const response = await fetch(`${API_BASE_URL}/tournaments/${tournamentId}/blind-level`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status} ${response.statusText}`);
    }

    const result = await response.json();

    if (!result.success) {
      throw new Error(result.error || 'Failed to fetch tournament blind level');
    }

    return result.data;
  }

  /**
   * Manual level change control
   * action: 'next' | 'previous' | 'set'
   */
  async changeTournamentBlindLevel(tournamentId: string, action: 'next' | 'previous' | 'set', level?: number) {
    const response = await fetch(`${API_BASE_URL}/tournaments/${tournamentId}/blind-level`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ action, level }),
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status} ${response.statusText}`);
    }

    const result = await response.json();

    if (!result.success) {
      throw new Error(result.error || 'Failed to change blind level');
    }

    return result.data;
  }

  /**
   * Cache blind schedules locally
   */
  private async cacheSchedules(schedules: BlindScheduleListItem[]): Promise<void> {
    const cacheData = {
      schedules,
      timestamp: Date.now(),
    };
    await AsyncStorage.setItem(BLIND_SCHEDULES_CACHE_KEY, JSON.stringify(cacheData));
  }

  /**
   * Get cached schedules
   */
  private async getCachedSchedules(): Promise<{ schedules: BlindScheduleListItem[]; timestamp: number } | null> {
    try {
      const cached = await AsyncStorage.getItem(BLIND_SCHEDULES_CACHE_KEY);
      if (!cached) return null;
      return JSON.parse(cached);
    } catch {
      return null;
    }
  }

  /**
   * Check if cache is still valid
   */
  private isCacheValid(cache: { schedules: BlindScheduleListItem[]; timestamp: number } | null): boolean {
    if (!cache) return false;
    return Date.now() - cache.timestamp < CACHE_EXPIRY_MS;
  }

  /**
   * Invalidate cache
   */
  private async invalidateCache(): Promise<void> {
    await AsyncStorage.removeItem(BLIND_SCHEDULES_CACHE_KEY);
  }
}

// Export singleton instance
export const blindScheduleApi = new BlindScheduleApiService();
