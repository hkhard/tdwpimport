/**
 * Conflict Resolver Service
 * Resolves conflicts using last-write-wins strategy
 *
 * Constitution Requirements:
 * - US2-A3: Conflict detection and resolution
 */

import type { Database } from 'better-sqlite3';
import type { Conflict } from '@shared/types/sync';

export interface ResolutionResult {
  /** Was conflict resolved */
  resolved: boolean;
  /** Resolved version */
  resolvedVersion: Record<string, unknown>;
  /** Resolution strategy used */
  strategy: 'server_wins' | 'client_wins' | 'manual' | 'merged';
}

export interface ResolutionOptions {
  /** Resolution strategy */
  strategy?: 'server_wins' | 'client_wins' | 'manual';
  /** Manual resolution (if strategy is manual) */
  manualResolution?: Record<string, unknown>;
}

/**
 * Conflict Resolver Service
 *
 * Resolves sync conflicts:
 * - Last-write-wins (server timestamp)
 * - Client-wins (for manual override)
 * - Field-level merging
 * - Records resolution in database
 */
export class ConflictResolver {
  private db: Database;

  constructor(db: Database) {
    this.db = db;
  }

  /**
   * Resolve conflict using last-write-wins
   */
  async resolveConflict(
    conflict: Conflict,
    options: ResolutionOptions = {}
  ): Promise<ResolutionResult> {
    const { strategy = 'server_wins' } = options;

    switch (strategy) {
      case 'server_wins':
        return this.resolveServerWins(conflict);

      case 'client_wins':
        return this.resolveClientWins(conflict);

      case 'manual':
        if (!options.manualResolution) {
          throw new Error('Manual resolution requires resolvedVersion');
        }
        return this.resolveManual(conflict, options.manualResolution);

      default:
        return this.resolveServerWins(conflict);
    }
  }

  /**
   * Server wins - use server version
   */
  private async resolveServerWins(conflict: Conflict): Promise<ResolutionResult> {
    // Apply server version to database
    await this.applyVersion(conflict.entityType, conflict.entityId, conflict.serverVersion);

    // Record resolution
    await this.recordResolution(conflict, 'server_wins', conflict.serverVersion);

    return {
      resolved: true,
      resolvedVersion: conflict.serverVersion,
      strategy: 'server_wins',
    };
  }

  /**
   * Client wins - use client (local) version
   */
  private async resolveClientWins(conflict: Conflict): Promise<ResolutionResult> {
    // Apply client version to database
    await this.applyVersion(conflict.entityType, conflict.entityId, conflict.localVersion);

    // Record resolution
    await this.recordResolution(conflict, 'client_wins', conflict.localVersion);

    return {
      resolved: true,
      resolvedVersion: conflict.localVersion,
      strategy: 'client_wins',
    };
  }

  /**
   * Manual resolution - use provided resolved version
   */
  private async resolveManual(
    conflict: Conflict,
    resolvedVersion: Record<string, unknown>
  ): Promise<ResolutionResult> {
    // Apply resolved version to database
    await this.applyVersion(conflict.entityType, conflict.entityId, resolvedVersion);

    // Record resolution
    await this.recordResolution(conflict, 'manual', resolvedVersion);

    return {
      resolved: true,
      resolvedVersion,
      strategy: 'manual',
    };
  }

  /**
   * Merge two versions (field-level)
   */
  async mergeVersions(
    conflict: Conflict,
    fieldPreferences: 'server' | 'client' = 'server'
  ): Promise<ResolutionResult> {
    const merged: Record<string, unknown> = { ...conflict.serverVersion };

    // Merge fields that differ
    for (const key of Object.keys(conflict.localVersion)) {
      const serverValue = conflict.serverVersion[key];
      const clientValue = conflict.localVersion[key];

      if (serverValue === undefined) {
        // Field doesn't exist on server, use client value
        merged[key] = clientValue;
      } else if (clientValue !== undefined && clientValue !== serverValue) {
        // Field differs - use preferred source
        merged[key] = fieldPreferences === 'server' ? serverValue : clientValue;
      }
    }

    // Apply merged version
    await this.applyVersion(conflict.entityType, conflict.entityId, merged);

    // Record resolution
    await this.recordResolution(conflict, 'merged', merged);

    return {
      resolved: true,
      resolvedVersion: merged,
      strategy: 'merged',
    };
  }

  /**
   * Apply version to database
   */
  private async applyVersion(
    entityType: string,
    entityId: string,
    version: Record<string, unknown>
  ): Promise<void> {
    switch (entityType) {
      case 'tournament':
        await this.applyTournamentVersion(entityId, version);
        break;

      case 'player':
        await this.applyPlayerVersion(entityId, version);
        break;

      case 'tournament_player':
        await this.applyTournamentPlayerVersion(entityId, version);
        break;
    }
  }

  /**
   * Apply tournament version
   */
  private async applyTournamentVersion(tournamentId: string, version: Record<string, unknown>): Promise<void> {
    const updateFields: string[] = [];
    const updateValues: any[] = [];

    for (const [key, value] of Object.entries(version)) {
      if (key === 'tournamentId') continue;

      const dbKey = this.camelToSnake(key);
      updateFields.push(`${dbKey} = ?`);
      updateValues.push(value);
    }

    if (updateFields.length > 0) {
      updateFields.push('updated_at = ?');
      updateValues.push(Date.now());
      updateValues.push(tournamentId);

      this.db.prepare(
        `UPDATE tournaments SET ${updateFields.join(', ')} WHERE tournament_id = ?`
      ).run(...updateValues);
    }
  }

  /**
   * Apply player version
   */
  private async applyPlayerVersion(playerId: string, version: Record<string, unknown>): Promise<void> {
    const updateFields: string[] = [];
    const updateValues: any[] = [];

    for (const [key, value] of Object.entries(version)) {
      if (key === 'playerId') continue;

      const dbKey = this.camelToSnake(key);
      updateFields.push(`${dbKey} = ?`);
      updateValues.push(value);
    }

    if (updateFields.length > 0) {
      updateFields.push('updated_at = ?');
      updateValues.push(Date.now());
      updateValues.push(playerId);

      this.db.prepare(
        `UPDATE players SET ${updateFields.join(', ')} WHERE player_id = ?`
      ).run(...updateValues);
    }
  }

  /**
   * Apply tournament player version
   */
  private async applyTournamentPlayerVersion(
    tournamentPlayerId: string,
    version: Record<string, unknown>
  ): Promise<void> {
    const updateFields: string[] = [];
    const updateValues: any[] = [];

    for (const [key, value] of Object.entries(version)) {
      if (key === 'tournamentPlayerId') continue;

      const dbKey = this.camelToSnake(key);
      updateFields.push(`${dbKey} = ?`);
      updateValues.push(value);
    }

    if (updateFields.length > 0) {
      updateFields.push('updated_at = ?');
      updateValues.push(Date.now());
      updateValues.push(tournamentPlayerId);

      this.db.prepare(
        `UPDATE tournament_players SET ${updateFields.join(', ')} WHERE tournament_player_id = ?`
      ).run(...updateValues);
    }
  }

  /**
   * Record conflict resolution
   */
  private async recordResolution(
    conflict: Conflict,
    strategy: string,
    resolvedVersion: Record<string, unknown>
  ): Promise<void> {
    this.db.prepare(
      `INSERT INTO sync_conflicts (conflict_id, sync_id, entity_type, entity_id, local_version, server_version, resolved_version, conflict_type, created_at, resolved_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`
    ).run(
      conflict.conflictId,
      generateSyncId(),
      conflict.entityType,
      conflict.entityId,
      JSON.stringify(conflict.localVersion),
      JSON.stringify(conflict.serverVersion),
      JSON.stringify(resolvedVersion),
      conflict.conflictType,
      Date.now(),
      Date.now()
    );
  }

  /**
   * Get unresolved conflicts
   */
  getUnresolvedConflicts(): Conflict[] {
    const rows = this.db.prepare(
      `SELECT * FROM sync_conflicts WHERE resolved_at IS NULL ORDER BY created_at DESC`
    ).all();

    return rows.map((row: any) => ({
      conflictId: row.conflict_id,
      entityType: row.entity_type,
      entityId: row.entity_id,
      localVersion: JSON.parse(row.local_version),
      serverVersion: JSON.parse(row.server_version),
      conflictType: row.conflict_type,
    }));
  }

  /**
   * Convert camelCase to snake_case
   */
  private camelToSnake(str: string): string {
    return str.replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`);
  }
}

/**
 * Generate a sync ID
 */
function generateSyncId(): string {
  return `sync-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
}

/**
 * Create a conflict resolver instance
 */
export function createConflictResolver(db: Database): ConflictResolver {
  return new ConflictResolver(db);
}
