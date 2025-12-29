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
    data: unknown;
    localTimestamp: number;
    serverTimestamp?: number;
}
export interface Conflict {
    entityType: string;
    entityId: string;
    localVersion: unknown;
    serverVersion: unknown;
    resolvedVersion: unknown;
    conflictType: 'concurrent_edit' | 'delete_conflict' | 'validation_error';
}
export interface SyncQueueItem {
    changeId: string;
    change: Change;
    retryCount: number;
    lastAttempt?: Date;
    nextRetry?: Date;
}
//# sourceMappingURL=sync.d.ts.map