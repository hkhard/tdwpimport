/**
 * Conflict Detection Service
 * Detects conflicts between client and server changes
 *
 * Constitution Requirements:
 * - US2-A3: Conflict detection and resolution
 */

import type { Database } from 'better-sqlite3';
import { ChangeTracker } from './ChangeTracker';
import type { Conflict } from '@shared/types/sync';

export interface IncomingChange {
  changeId: string;
  entityType: string;
  operation: 'create' | 'update' | 'delete';
  entityId: string;
  data: Record<string, unknown>;
  localTimestamp: Date;
  deviceId: string;
}

/**
 * Conflict Detection Service
 *
 * Detects conflicts between concurrent changes:
 * - Compares timestamps
 * - Detects concurrent edits
 * - Identifies delete conflicts
 * - Provides conflict details
 */
export class ConflictDetectionService {
  private db: Database;
  private changeTracker: ChangeTracker;

  constructor(db: Database, changeTracker: ChangeTracker) {
    this.db = db;
    this.changeTracker = changeTracker;
  }

  /**
   * Detect conflict for incoming change
   */
  async detectConflict(incoming: IncomingChange): Promise<Conflict | null> {
    // Get latest server change for this entity
    const latestChange = await this.changeTracker.getLatestChange(
      incoming.entityType,
      incoming.entityId
    );

    if (!latestChange) {
      // No previous change, no conflict
      return null;
    }

    // Check for delete conflicts
    if (latestChange.operation === 'delete' && incoming.operation !== 'delete') {
      return {
        conflictId: `conflict-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
        entityType: incoming.entityType,
        entityId: incoming.entityId,
        localVersion: incoming.data,
        serverVersion: latestChange.data,
        conflictType: 'delete_conflict',
      };
    }

    if (incoming.operation === 'delete' && latestChange.operation !== 'delete') {
      return {
        conflictId: `conflict-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
        entityType: incoming.entityType,
        entityId: incoming.entityId,
        localVersion: incoming.data,
        serverVersion: latestChange.data,
        conflictType: 'delete_conflict',
      };
    }

    // Check for concurrent edits
    const timeDiff = Math.abs(
      incoming.localTimestamp.getTime() - latestChange.serverTimestamp.getTime()
    );

    // If changes occurred within 5 seconds of each other, flag as potential conflict
    if (timeDiff < 5000 && incoming.operation === 'update' && latestChange.operation === 'update') {
      // Check if same fields were modified
      const localFields = new Set(Object.keys(incoming.data));
      const serverFields = new Set(Object.keys(latestChange.data));

      const intersection = [...localFields].filter((x) => serverFields.has(x));

      if (intersection.length > 0) {
        return {
          conflictId: `conflict-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
          entityType: incoming.entityType,
          entityId: incoming.entityId,
          localVersion: incoming.data,
          serverVersion: latestChange.data,
          conflictType: 'concurrent_edit',
        };
      }
    }

    // No conflict detected
    return null;
  }

  /**
   * Batch detect conflicts
   */
  async detectConflicts(changes: IncomingChange[]): Promise<Map<string, Conflict>> {
    const conflicts = new Map<string, Conflict>();

    for (const change of changes) {
      const conflict = await this.detectConflict(change);
      if (conflict) {
        conflicts.set(change.changeId, conflict);
      }
    }

    return conflicts;
  }

  /**
   * Check for validation errors
   */
  async detectValidationError(change: IncomingChange): Promise<Conflict | null> {
    // Check entity-specific validation rules
    switch (change.entityType) {
      case 'tournament':
        return this.validateTournamentChange(change);
      case 'player':
        return this.validatePlayerChange(change);
      case 'tournament_player':
        return this.validateTournamentPlayerChange(change);
      default:
        return null;
    }
  }

  /**
   * Validate tournament change
   */
  private async validateTournamentChange(change: IncomingChange): Promise<Conflict | null> {
    const data = change.data as any;

    // Check if tournament exists
    if (change.operation === 'update' || change.operation === 'delete') {
      const existing = this.db
        .prepare('SELECT tournament_id FROM tournaments WHERE tournament_id = ?')
        .get(change.entityId);

      if (!existing) {
        return {
          conflictId: `validation-${Date.now()}`,
          entityType: change.entityType,
          entityId: change.entityId,
          localVersion: data,
          serverVersion: {},
          conflictType: 'validation_error',
        };
      }
    }

    return null;
  }

  /**
   * Validate player change
   */
  private async validatePlayerChange(change: IncomingChange): Promise<Conflict | null> {
    const data = change.data as any;

    // Check if player exists
    if (change.operation === 'update' || change.operation === 'delete') {
      const existing = this.db
        .prepare('SELECT player_id FROM players WHERE player_id = ?')
        .get(change.entityId);

      if (!existing) {
        return {
          conflictId: `validation-${Date.now()}`,
          entityType: change.entityType,
          entityId: change.entityId,
          localVersion: data,
          serverVersion: {},
          conflictType: 'validation_error',
        };
      }
    }

    return null;
  }

  /**
   * Validate tournament player change
   */
  private async validateTournamentPlayerChange(change: IncomingChange): Promise<Conflict | null> {
    const data = change.data as any;

    // Check if tournament exists
    if (data.tournamentId) {
      const tournament = this.db
        .prepare('SELECT tournament_id FROM tournaments WHERE tournament_id = ?')
        .get(data.tournamentId);

      if (!tournament) {
        return {
          conflictId: `validation-${Date.now()}`,
          entityType: change.entityType,
          entityId: change.entityId,
          localVersion: data,
          serverVersion: {},
          conflictType: 'validation_error',
        };
      }
    }

    // Check if player exists
    if (data.playerId) {
      const player = this.db
        .prepare('SELECT player_id FROM players WHERE player_id = ?')
        .get(data.playerId);

      if (!player) {
        return {
          conflictId: `validation-${Date.now()}`,
          entityType: change.entityType,
          entityId: change.entityId,
          localVersion: data,
          serverVersion: {},
          conflictType: 'validation_error',
        };
      }
    }

    return null;
  }
}

/**
 * Create a conflict detection service instance
 */
export function createConflictDetectionService(
  db: Database,
  changeTracker: ChangeTracker
): ConflictDetectionService {
  return new ConflictDetectionService(db, changeTracker);
}
