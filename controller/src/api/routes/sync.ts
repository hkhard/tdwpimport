/**
 * Sync API Routes
 * Handles bidirectional synchronization with mobile clients
 *
 * Constitution Requirements:
 * - US2-A2: Automatic sync when connection restored
 * - US2-A3: Conflict detection and resolution
 */

import type { FastifyInstance, FastifyRequest, FastifyReply } from 'fastify';
import { authMiddleware } from '../middleware/auth';

interface SyncChangeRequest {
  changeId: string;
  entityType: string;
  operation: 'create' | 'update' | 'delete';
  entityId: string;
  data: Record<string, unknown>;
  localTimestamp: string;
}

interface SyncUploadRequest {
  deviceId: string;
  changes: SyncChangeRequest[];
}

interface SyncUploadResponse {
  synced: string[];
  conflicts: Array<{
    conflictId: string;
    entityType: string;
    entityId: string;
    localVersion: Record<string, unknown>;
    serverVersion: Record<string, unknown>;
  }>;
}

interface SyncPullRequest {
  since?: string;
}

interface SyncPullResponse {
  changes: Array<{
    entityType: string;
    entityId: string;
    operation: 'create' | 'update' | 'delete';
    data: Record<string, unknown>;
    timestamp: string;
  }>;
  timestamp: string;
}

/**
 * Register sync routes
 */
export async function syncRoutes(fastify: FastifyInstance): Promise<void> {
  // Upload changes (POST /api/sync)
  fastify.post<{
    Body: SyncUploadRequest;
  }>('/sync', {
    onRequest: authMiddleware,
  }, async (request, reply) => {
    const { deviceId, changes } = request.body;

    if (!changes || changes.length === 0) {
      return reply.send({ synced: [], conflicts: [] });
    }

    // Get services from request scope
    const changeTracker = (request as any).changeTracker;
    const conflictDetector = (request as any).conflictDetector;

    const synced: string[] = [];
    const conflicts: any[] = [];

    // Process each change
    for (const change of changes) {
      try {
        // Track the incoming change
        await changeTracker.trackChange({
          deviceId,
          entityType: change.entityType,
          operation: change.operation,
          entityId: change.entityId,
          data: change.data,
          timestamp: new Date(change.localTimestamp),
        });

        // Check for conflicts
        const conflict = await conflictDetector.detectConflict(change);
        if (conflict) {
          conflicts.push(conflict);
        } else {
          // Apply change to database
          await applyChange(fastify, change);
          synced.push(change.changeId);
        }
      } catch (error: any) {
        console.error(`[Sync] Failed to process change ${change.changeId}:`, error);
        // Continue processing other changes
      }
    }

    const response: SyncUploadResponse = { synced, conflicts };
    return reply.send(response);
  });

  // Pull changes (GET /api/sync)
  fastify.get<{
    Querystring: SyncPullRequest;
  }>('/sync', {
    onRequest: authMiddleware,
  }, async (request, reply) => {
    const { since } = request.query;
    const changeTracker = (request as any).changeTracker;

    // Get changes since timestamp
    const sinceDate = since ? new Date(since) : new Date(0);
    const changes = await changeTracker.getChangesSince(sinceDate);

    const response: SyncPullResponse = {
      changes: changes.map((c: any) => ({
        entityType: c.entityType,
        entityId: c.entityId,
        operation: c.operation,
        data: c.data,
        timestamp: c.timestamp.toISOString(),
      })),
      timestamp: new Date().toISOString(),
    };

    return reply.send(response);
  });

  // Get sync status (GET /api/sync/status)
  fastify.get('/sync/status', {
    onRequest: authMiddleware,
  }, async (request, reply) => {
    const changeTracker = (request as any).changeTracker;

    const stats = await changeTracker.getStatistics();

    return reply.send({
      pendingChanges: stats.pending,
      lastSync: stats.lastSync,
      devices: stats.devices,
    });
  });
}

/**
 * Apply a change to the database
 */
async function applyChange(
  fastify: FastifyInstance,
  change: SyncChangeRequest
): Promise<void> {
  const db = (fastify as any).db;

  switch (change.entityType) {
    case 'tournament':
      await applyTournamentChange(db, change);
      break;

    case 'player':
      await applyPlayerChange(db, change);
      break;

    case 'tournament_player':
      await applyTournamentPlayerChange(db, change);
      break;

    default:
      console.warn(`[Sync] Unknown entity type: ${change.entityType}`);
  }
}

/**
 * Apply tournament change
 */
async function applyTournamentChange(db: any, change: SyncChangeRequest): Promise<void> {
  const data = change.data as any;

  switch (change.operation) {
    case 'create':
      db.prepare(
        'INSERT INTO tournaments (tournament_id, name, description, start_time, status, current_blind_level, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
      ).run(
        change.entityId,
        data.name,
        data.description || null,
        data.startTime,
        data.status || 'upcoming',
        1,
        data.createdBy,
        Date.now(),
        Date.now()
      );
      break;

    case 'update':
      const updateFields: string[] = [];
      const updateValues: any[] = [];

      if (data.name !== undefined) {
        updateFields.push('name = ?');
        updateValues.push(data.name);
      }
      if (data.description !== undefined) {
        updateFields.push('description = ?');
        updateValues.push(data.description);
      }
      if (data.status !== undefined) {
        updateFields.push('status = ?');
        updateValues.push(data.status);
      }

      if (updateFields.length > 0) {
        updateFields.push('updated_at = ?');
        updateValues.push(Date.now());
        updateValues.push(change.entityId);

        db.prepare(
          `UPDATE tournaments SET ${updateFields.join(', ')} WHERE tournament_id = ?`
        ).run(...updateValues);
      }
      break;

    case 'delete':
      db.prepare('DELETE FROM tournaments WHERE tournament_id = ?').run(change.entityId);
      break;
  }
}

/**
 * Apply player change
 */
async function applyPlayerChange(db: any, change: SyncChangeRequest): Promise<void> {
  const data = change.data as any;

  switch (change.operation) {
    case 'create':
      db.prepare(
        'INSERT INTO players (player_id, name, email, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
      ).run(
        change.entityId,
        data.name,
        data.email || null,
        data.phone || null,
        Date.now(),
        Date.now()
      );
      break;

    case 'update':
      db.prepare(
        'UPDATE players SET name = COALESCE(?, name), email = COALESCE(?, email), phone = COALESCE(?, phone), updated_at = ? WHERE player_id = ?'
      ).run(
        data.name ?? null,
        data.email ?? null,
        data.phone ?? null,
        Date.now(),
        change.entityId
      );
      break;

    case 'delete':
      db.prepare('DELETE FROM players WHERE player_id = ?').run(change.entityId);
      break;
  }
}

/**
 * Apply tournament player change
 */
async function applyTournamentPlayerChange(db: any, change: SyncChangeRequest): Promise<void> {
  const data = change.data as any;

  switch (change.operation) {
    case 'create':
      db.prepare(
        'INSERT INTO tournament_players (tournament_player_id, tournament_id, player_id, starting_stack, updated_at) VALUES (?, ?, ?, ?, ?)'
      ).run(
        change.entityId,
        data.tournamentId,
        data.playerId,
        data.startingStack || 0,
        Date.now()
      );
      break;

    case 'update':
      const updateFields: string[] = [];
      const updateValues: any[] = [];

      if (data.finishPosition !== undefined) {
        updateFields.push('finish_position = ?');
        updateValues.push(data.finishPosition);
      }
      if (data.winnings !== undefined) {
        updateFields.push('winnings = ?');
        updateValues.push(data.winnings);
      }
      if (data.bustoutTime !== undefined) {
        updateFields.push('bustout_time = ?');
        updateValues.push(data.bustoutTime);
      }

      if (updateFields.length > 0) {
        updateFields.push('updated_at = ?');
        updateValues.push(Date.now());
        updateValues.push(change.entityId);

        db.prepare(
          `UPDATE tournament_players SET ${updateFields.join(', ')} WHERE tournament_player_id = ?`
        ).run(...updateValues);
      }
      break;

    case 'delete':
      db.prepare('DELETE FROM tournament_players WHERE tournament_player_id = ?').run(change.entityId);
      break;
  }
}
