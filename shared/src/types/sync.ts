/**
 * Synchronization-related type definitions
 */

export type SyncStatus = 'pending' | 'syncing' | 'success' | 'failed';

export interface SyncRecord {
  syncId: string;
  deviceId: string;
  timestamp: Date;
  changes: Change[];
  conflicts: Conflict[];
  status: SyncStatus;
  errorMessage?: string;
}

export interface Change {
  entityType: 'tournament' | 'player' | 'tournament_player' | 'timer_event';
  operation: 'create' | 'update' | 'delete';
  entityId: string;
  data: unknown; // Entity data
  localTimestamp: number; // When change was made locally
  serverTimestamp?: number; // When server received the change
}

export interface Conflict {
  conflictId: string; // Unique identifier for this conflict
  entityType: string;
  entityId: string;
  localVersion: unknown;
  serverVersion: unknown;
  resolvedVersion: unknown; // Last-write-wins resolution
  conflictType: 'concurrent_edit' | 'delete_conflict' | 'validation_error';
}

export interface SyncQueueItem {
  changeId: string;
  change: Change;
  retryCount: number;
  lastAttempt?: Date;
  nextRetry?: Date;
}
