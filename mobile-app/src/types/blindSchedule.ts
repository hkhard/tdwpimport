/**
 * Blind Schedule Types for Mobile App
 * Matches the API response from controller
 */

import { BlindLevel } from '@shared/types/timer';

/**
 * Blind schedule with computed properties for UI display
 * Matches the API response from BlindScheduleService.getScheduleById
 */
export interface BlindScheduleWithMetadata {
  id: string;
  name: string;
  description: string | null;
  startingStack: number;
  breakInterval: number | null;
  breakDuration: number | null;
  isDefault: boolean;
  levelCount: number;
  totalDurationMinutes: number;
  createdAt: Date;
  updatedAt: Date;
  createdBy: string | null;
  levels: BlindLevel[];
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
