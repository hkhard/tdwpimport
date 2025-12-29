/**
 * Timer-related type definitions
 */

// Re-export tournament types that are timer-related
export type { TournamentStatus, TimerState } from './tournament';

export type TimerEventType =
  | 'tournament_start'
  | 'tournament_end'
  | 'level_start'
  | 'level_end'
  | 'break_start'
  | 'break_end'
  | 'pause'
  | 'resume'
  | 'director_override';

export interface BlindLevel {
  blindLevelId: string;
  level: number;
  smallBlind: number;
  bigBlind: number;
  ante?: number;
  duration: number; // minutes
  isBreak: boolean;
}

export interface TimerEvent {
  eventId: string;
  tournamentId: string;
  timestamp: Date;
  eventType: TimerEventType;
  previousState?: TimerEventState;
  newState: TimerEventState;
  deviceId?: string; // which device triggered the event
  metadata?: Record<string, unknown>;
}

export interface TimerEventState {
  level: number;
  elapsedTime: number;
  isRunning: boolean;
  isPaused: boolean;
}

export interface BlindSchedule {
  blindScheduleId: string;
  name: string;
  levels: BlindLevel[];
  breakIntervals: number[]; // level numbers that are breaks
  startingStack: number;
  createdAt: Date;
  updatedAt: Date;
}

/**
 * Blind scheme list item (metadata only, no levels)
 * Used for list view performance
 */
export interface BlindSchemeListItem {
  id: string;
  name: string;
  description: string | null;
  startingStack: number;
  levelCount: number;
  totalDurationMinutes: number;
  isDefault: boolean;
}

/**
 * Blind scheme validation error
 */
export interface BlindSchemeValidationError {
  field: string;
  message: string;
  level?: number; // For level-specific errors
}

/**
 * Blind scheme create input
 */
export interface CreateBlindSchemeInput {
  name: string;
  description?: string;
  startingStack: number;
  breakInterval: number;
  breakDuration: number;
  levels: CreateBlindLevelInput[];
}

/**
 * Blind level create input
 */
export interface CreateBlindLevelInput {
  smallBlind: number;
  bigBlind: number;
  ante?: number;
  duration: number;
  isBreak: boolean;
}

/**
 * Blind scheme update input (all fields optional for partial updates)
 */
export interface UpdateBlindSchemeInput {
  name?: string;
  description?: string;
  startingStack?: number;
  breakInterval?: number;
  breakDuration?: number;
  levels?: CreateBlindLevelInput[];
}

/**
 * Blind level update input (all fields optional)
 */
export interface UpdateBlindLevelInput {
  smallBlind?: number;
  bigBlind?: number;
  ante?: number;
  duration?: number;
  isBreak?: boolean;
}

/**
 * Blind scheme sync queue item (for offline CRUD)
 */
export interface BlindSchemeSyncQueueItem {
  type: 'CREATE_SCHEME' | 'UPDATE_SCHEME' | 'DELETE_SCHEME';
  schemeId: string;
  data?: CreateBlindSchemeInput | UpdateBlindSchemeInput;
  timestamp: number;
}
