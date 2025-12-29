/**
 * Sync Record repository
 */

import { BaseRepository } from './BaseRepository';
import type { SyncRecord } from '@shared/types/sync';

export interface SyncRecordRow {
  sync_id: string;
  device_id: string;
  timestamp: string;
  status: string;
  error_message: string | null;
  created_at: string;
  updated_at: string;
}

export class SyncRecordRepository extends BaseRepository<SyncRecordRow> {
  constructor() {
    super('sync_records', 'sync_id');
  }

  toDomain(row: SyncRecordRow): Omit<SyncRecord, 'changes' | 'conflicts'> {
    return {
      syncId: row.sync_id,
      deviceId: row.device_id,
      timestamp: new Date(row.timestamp),
      status: row.status as SyncRecord['status'],
      errorMessage: row.error_message || undefined,
    };
  }

  findByDevice(deviceId: string, limit?: number): SyncRecordRow[] {
    const sql = limit
      ? `SELECT * FROM sync_records WHERE device_id = ? ORDER BY timestamp DESC LIMIT ?`
      : `SELECT * FROM sync_records WHERE device_id = ? ORDER BY timestamp DESC`;
    const params = limit ? [deviceId, limit] : [deviceId];
    return this.query<SyncRecordRow>(sql, params);
  }

  findByStatus(status: SyncRecord['status']): SyncRecordRow[] {
    return this.findWhere({ status: status });
  }

  findPending(): SyncRecordRow[] {
    return this.query<SyncRecordRow>(
      `SELECT * FROM sync_records WHERE status IN ('pending', 'syncing') ORDER BY timestamp ASC`
    );
  }
}
