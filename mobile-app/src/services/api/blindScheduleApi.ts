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
import type {
  CreateBlindSchemeInput,
  UpdateBlindSchemeInput,
} from '@shared/types/timer';
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
   * Check if blind schedule is in use by any tournament
   */
  async isScheduleInUse(scheduleId: string): Promise<boolean> {
    const response = await fetch(`${API_BASE_URL}/blind-schedules/${scheduleId}/in-use`, {
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
      throw new Error(result.error || 'Failed to check schedule in-use status');
    }

    return result.data.inUse;
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
   * Create new blind scheme (alias for createBlindSchedule)
   * Uses new type definitions from shared types
   */
  async createScheme(input: CreateBlindSchemeInput): Promise<BlindScheduleWithMetadata> {
    return this.createBlindSchedule(input as BlindScheduleFormData);
  }

  /**
   * Update existing blind scheme (alias for updateBlindSchedule)
   * Uses new type definitions from shared types
   */
  async updateScheme(
    id: string,
    input: UpdateBlindSchemeInput
  ): Promise<BlindScheduleWithMetadata> {
    return this.updateBlindSchedule(id, input as Partial<BlindScheduleFormData>);
  }

  /**
   * Delete blind scheme (alias for deleteBlindSchedule)
   */
  async deleteScheme(id: string): Promise<void> {
    return this.deleteBlindSchedule(id);
  }

  /**
   * Duplicate blind scheme
   * Creates a copy of an existing scheme (used for editing defaults)
   */
  async duplicateScheme(id: string, newName?: string): Promise<BlindScheduleWithMetadata> {
    const response = await fetch(`${API_BASE_URL}/blind-schedules/${id}/duplicate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(newName ? { name: newName } : {}),
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status} ${response.statusText}`);
    }

    const result: BlindScheduleApiResponse = await response.json();

    if (!result.success || !result.data) {
      throw new Error(result.error || 'Failed to duplicate blind schedule');
    }

    // Invalidate cache
    await this.invalidateCache();

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
