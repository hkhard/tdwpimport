/**
 * Sync Orchestrator base class
 * Coordinates bidirectional synchronization between mobile and controller
 * Handles offline queue, conflict detection, and retry logic
 */

import type { Change, SyncStatus, SyncRecord, Conflict } from '../../types/sync';

export interface SyncConfig {
  apiBaseUrl: string;
  syncInterval: number; // ms
  maxRetries: number;
  retryBaseDelay: number; // ms
}

export interface SyncResult {
  success: boolean;
  uploaded: number;
  downloaded: number;
  conflicts: Conflict[];
  errors: string[];
}

export abstract class SyncOrchestrator {
  protected config: SyncConfig;
  protected status: SyncStatus = 'idle';
  protected pendingChanges: Change[] = [];
  protected conflicts: Conflict[] = [];

  constructor(config: SyncConfig) {
    this.config = config;
  }

  /**
   * Add change to offline queue
   */
  async queueChange(change: Change): Promise<void> {
    this.pendingChanges.push(change);
    await this.persistQueue();

    // Trigger sync if online
    if (this.status !== 'offline') {
      this.sync().catch((error) => console.error('Sync error:', error));
    }
  }

  /**
   * Perform full synchronization
   */
  async sync(): Promise<SyncResult> {
    const result: SyncResult = {
      success: true,
      uploaded: 0,
      downloaded: 0,
      conflicts: [],
      errors: [],
    };

    try {
      this.setStatus('syncing');

      // 1. Upload pending changes
      const uploadResult = await this.uploadChanges();
      result.uploaded = uploadResult.uploaded;
      result.conflicts.push(...uploadResult.conflicts);
      result.errors.push(...uploadResult.errors);

      // 2. Download server changes
      const downloadResult = await this.downloadChanges();
      result.downloaded = downloadResult.downloaded;
      result.errors.push(...downloadResult.errors);

      // 3. Update sync record
      if (result.conflicts.length === 0 && result.errors.length === 0) {
        await this.recordSuccessfulSync();
        this.setStatus('success');
      } else if (result.conflicts.length > 0) {
        this.setStatus('conflict');
      } else {
        this.setStatus('error');
      }
    } catch (error: any) {
      result.success = false;
      result.errors.push(error.message);
      this.setStatus('error');
    }

    return result;
  }

  /**
   * Upload pending changes to server
   */
  protected async uploadChanges(): Promise<{
    uploaded: number;
    conflicts: Conflict[];
    errors: string[];
  }> {
    const result = {
      uploaded: 0,
      conflicts: [] as Conflict[],
      errors: [] as string[],
    };

    for (const change of this.pendingChanges) {
      try {
        const response = await this.uploadChange(change);

        if (response.conflict) {
          result.conflicts.push(response.conflict);
        } else {
          result.uploaded++;
          // Remove from queue
          this.pendingChanges = this.pendingChanges.filter((c) => c.changeId !== change.changeId);
        }
      } catch (error: any) {
        result.errors.push(`Failed to upload ${change.entityId}: ${error.message}`);
      }
    }

    await this.persistQueue();
    return result;
  }

  /**
   * Download changes from server
   */
  protected async downloadChanges(): Promise<{
    downloaded: number;
    errors: string[];
  }> {
    const result = {
      downloaded: 0,
      errors: [] as string[],
    };

    try {
      const changes = await this.fetchServerChanges();

      for (const change of changes) {
        try {
          await this.applyChange(change);
          result.downloaded++;
        } catch (error: any) {
          result.errors.push(`Failed to apply ${change.entityId}: ${error.message}`);
        }
      }
    } catch (error: any) {
      result.errors.push(`Failed to download changes: ${error.message}`);
    }

    return result;
  }

  /**
   * Resolve a conflict
   */
  async resolveConflict(conflictId: string, resolution: 'local' | 'server'): Promise<void> {
    const conflict = this.conflicts.find((c) => c.conflictId === conflictId);
    if (!conflict) {
      throw new Error('Conflict not found');
    }

    if (resolution === 'local') {
      // Re-upload local change with force flag
      await this.uploadChange(conflict.localChange);
    } else {
      // Accept server change and discard local
      await this.applyChange(conflict.serverChange);
    }

    // Remove from conflicts
    this.conflicts = this.conflicts.filter((c) => c.conflictId !== conflictId);
  }

  /**
   * Get current sync status
   */
  getStatus(): SyncStatus {
    return this.status;
  }

  /**
   * Get pending changes count
   */
  getPendingCount(): number {
    return this.pendingChanges.length;
  }

  /**
   * Get conflicts
   */
  getConflicts(): Conflict[] {
    return this.conflicts;
  }

  /**
   * Set sync status
   */
  protected setStatus(status: SyncStatus): void {
    this.status = status;
    this.onStatusChange(status);
  }

  /**
   * Abstract methods to be implemented by platform
   */
  protected abstract uploadChange(change: Change): Promise<{
    success: boolean;
    conflict?: Conflict;
  }>;
  protected abstract fetchServerChanges(): Promise<Change[]>;
  protected abstract applyChange(change: Change): Promise<void>;
  protected abstract persistQueue(): Promise<void>;
  protected abstract recordSuccessfulSync(): Promise<SyncRecord>;
  protected abstract onStatusChange(status: SyncStatus): void;
}
