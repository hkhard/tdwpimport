/**
 * Blind Schedule Types for Mobile App
 * Extends shared types from shared/src/types/timer.ts
 */

import { BlindSchedule, BlindLevel } from '@shared/types/timer';

/**
 * Blind schedule with computed properties for UI display
 */
export interface BlindScheduleWithMetadata extends BlindSchedule {
  /** Number of levels in this schedule */
  levelCount: number;
  /** Total duration in minutes (excluding breaks) */
  totalDurationMinutes: number;
  /** Whether this is a pre-loaded default schedule */
  isDefault: boolean;
}

/**
 * Blind schedule list item for selector UI
 */
export interface BlindScheduleListItem {
  id: string;
  name: string;
  description?: string;
  startingStack: number;
  levelCount: number;
  totalDurationMinutes: number;
  isDefault: boolean;
}

/**
 * Current blind level info for tournament detail
 */
export interface CurrentBlindLevel {
  tournamentId: string;
  currentLevel: number;
  blindSchedule: BlindScheduleWithMetadata;
  currentLevelInfo: BlindLevel;
  nextLevel?: BlindLevel;
  previousLevel?: BlindLevel;
}

/**
 * Blind schedule form data for creation/editing
 */
export interface BlindScheduleFormData {
  name: string;
  description?: string;
  startingStack: number;
  breakInterval: number;
  breakDuration: number;
  levels: BlindLevelFormData[];
}

/**
 * Blind level form data
 */
export interface BlindLevelFormData {
  levelNumber: number;
  smallBlind: number;
  bigBlind: number;
  ante?: number;
  durationMinutes: number;
  isBreak: boolean;
}

/**
 * Blind schedule API response
 */
export interface BlindScheduleApiResponse {
  success: boolean;
  data?: BlindScheduleWithMetadata;
  error?: string;
}

/**
 * Blind schedule list API response
 */
export interface BlindScheduleListApiResponse {
  success: boolean;
  data?: BlindScheduleListItem[];
  error?: string;
}
