/**
 * Change Tracker Service
 * Tracks entity changes with timestamps for sync
 *
 * Constitution Requirements:
 * - US2-A2: Automatic sync when connection restored
 * - US2-A3: Conflict detection and resolution
 */

import type { Database } from 'better-sqlite3';

export interface TrackedChange {
  /** Unique change ID */
  changeId: string;
  /** Device that made the change */
  deviceId: string;
  /** Entity type */
  entityType: string;
  /** Operation type */
  operation: 'create' | 'update' | 'delete';
  /** Entity ID */
  entityId: string;
  /** Change data */
  data: Record<string, unknown>;
  /** When change was made (client timestamp) */
  timestamp: Date;
  /** When change was recorded on server */
  serverTimestamp: Date;
}

export interface ChangeStatistics {
  /** Pending changes (not yet synced to all clients) */
  pending: number;
  /** Last sync timestamp */
  lastSync: Date | null;
  /** Active devices (with pending changes) */
  devices: string[];
}

/**
 * Change Tracker Service
 *
 * Tracks all entity changes:
 * - Records changes from all devices
 * - Maintains change history
 * - Provides changes for sync
 * - Cleans up old changes
 */
export class ChangeTracker {
  private db: Database;

  constructor(db: Database) {
    this.db = db;

    // Initialize changes table if not exists
    this.initializeSchema();
  }

  /**
   * Track a change
   */
  async trackChange(change: Omit<TrackedChange, 'changeId' | 'serverTimestamp'>): Promise<void> {
    const changeId = crypto.randomUUID();
    const serverTimestamp = new Date();

    this.db.prepare(
      `INSERT INTO sync_changes (change_id, sync_id, entity_type, operation, entity_id, data, local_timestamp, server_timestamp, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`
    ).run(
      changeId,
      generateSyncId(),
      change.entityType,
      change.operation,
      change.entityId,
      JSON.stringify(change.data),
      change.timestamp.getTime(),
      serverTimestamp.getTime(),
      Date.now()
    );

    console.log(`[ChangeTracker] Tracked ${change.operation} on ${change.entityType}:${change.entityId}`);
  }

  /**
   * Get changes since timestamp
   */
  async getChangesSince(since: Date): Promise<TrackedChange[]> {
    const sinceMs = since.getTime();

    const rows = this.db.prepare(
      `SELECT * FROM sync_changes WHERE server_timestamp > ? ORDER BY server_timestamp ASC`
    ).all(sinceMs);

    return rows.map((row: any) => ({
      changeId: row.change_id,
      deviceId: row.sync_id, // Using sync_id as device identifier
      entityType: row.entity_type,
      operation: row.operation,
      entityId: row.entity_id,
      data: JSON.parse(row.data),
      timestamp: new Date(row.local_timestamp),
      serverTimestamp: new Date(row.server_timestamp),
    }));
  }

  /**
   * Get latest change for entity
   */
  async getLatestChange(entityType: string, entityId: string): Promise<TrackedChange | null> {
    const row = this.db.prepare(
      `SELECT * FROM sync_changes
       WHERE entity_type = ? AND entity_id = ?
       ORDER BY server_timestamp DESC
       LIMIT 1`
    ).get(entityType, entityId);

    if (!row) {
      return null;
    }

    return {
      changeId: row.change_id,
      deviceId: row.sync_id,
      entityType: row.entity_type,
      operation: row.operation,
      entityId: row.entity_id,
      data: JSON.parse(row.data),
      timestamp: new Date(row.local_timestamp),
      serverTimestamp: new Date(row.server_timestamp),
    };
  }

  /**
   * Get statistics
   */
  async getStatistics(): Promise<ChangeStatistics> {
    const pendingRow = this.db.prepare(
      'SELECT COUNT(*) as count FROM sync_changes'
    ).get() as { count: number };

    const lastSyncRow = this.db.prepare(
      'SELECT MAX(server_timestamp) as last_sync FROM sync_changes'
    ).get() as { last_sync: number | null };

    const deviceRows = this.db.prepare(
      'SELECT DISTINCT sync_id FROM sync_changes'
    ).all() as Array<{ sync_id: string }>;

    return {
      pending: pendingRow.count,
      lastSync: lastSyncRow.last_sync ? new Date(lastSyncRow.last_sync) : null,
      devices: deviceRows.map((r) => r.sync_id),
    };
  }

  /**
   * Clean up old changes
   */
  async cleanup(olderThanMs: number = 7 * 24 * 60 * 60 * 1000): Promise<number> {
    const cutoff = Date.now() - olderThanMs;

    const result = this.db.prepare(
      'DELETE FROM sync_changes WHERE server_timestamp < ?'
    ).run(cutoff);

    console.log(`[ChangeTracker] Cleaned up ${result.changes} old changes`);
    return result.changes;
  }

  /**
   * Initialize schema
   */
  private initializeSchema(): void {
    // Table created by migration, verify it exists
    const tableExists = this.db.prepare(
      "SELECT name FROM sqlite_master WHERE type='table' AND name='sync_changes'"
    ).get();

    if (!tableExists) {
      console.warn('[ChangeTracker] sync_changes table not found, migrations may not have run');
    }
  }
}

/**
 * Generate a sync ID (used as device/session identifier)
 */
function generateSyncId(): string {
  return `sync-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
}

/**
 * Create a change tracker instance
 */
export function createChangeTracker(db: Database): ChangeTracker {
  return new ChangeTracker(db);
}
