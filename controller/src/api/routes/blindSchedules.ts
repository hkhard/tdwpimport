/**
 * Blind Schedules API Routes
 * REST API for blind schedule management
 *
 * Constitution Requirements:
 * - US4-A1: 100+ blind schedules supported
 * - US1-A1: Schedule selection during tournament setup
 * - US2-A1: Real-time blind level updates
 * - US5-A1: Manual level controls during tournament
 */

import type { FastifyInstance, FastifyRequest, FastifyReply } from 'fastify';
import { blindScheduleService } from '../../services/blindSchedule/BlindScheduleService';
import { broadcastToTournament } from '../../websocket/server';
import {
  CreateBlindSchemeInputSchema,
  UpdateBlindSchemeInputSchema,
  formatValidationErrors,
} from '../../services/blindSchedule/validation';
import type { BlindLevel } from '@shared/types/timer';

// Extend Fastify schema
declare module 'fastify' {
  interface FastifyInstance {
    blindScheduleService: typeof blindScheduleService;
  }
}

/**
 * Register blind schedule routes
 */
export async function blindScheduleRoutes(fastify: FastifyInstance): Promise<void> {
  // Attach service to fastify instance
  fastify.decorate('blindScheduleService', blindScheduleService);

  // ============================================================
  // T010: Blind Schedules CRUD
  // ============================================================

  /**
   * GET /blind-schedules
   * List all blind schedules with metadata
   * Query: includeDefault=true
   */
  fastify.get<{
    Querystring: { includeDefault?: string };
  }>('/blind-schedules', async (request, reply) => {
    const includeDefault = request.query.includeDefault !== 'false';
    const schedules = await fastify.blindScheduleService.getAllSchedules(includeDefault);
    return reply.send({
      success: true,
      data: schedules,
    });
  });

  /**
   * GET /blind-schedules/:id
   * Get single blind schedule with full levels
   */
  fastify.get<{
    Params: { id: string };
  }>('/blind-schedules/:id', async (request, reply) => {
    const { id } = request.params;

    try {
      const schedule = await fastify.blindScheduleService.getScheduleById(id);

      if (!schedule) {
        return reply.code(404).send({
          success: false,
          error: 'Blind schedule not found',
        });
      }

      return reply.send({
        success: true,
        data: schedule,
      });
    } catch (error: any) {
      return reply.code(500).send({
        success: false,
        error: error.message,
      });
    }
  });

  /**
   * GET /blind-schedules/:id/in-use
   * Check if blind schedule is in use by any tournament
   */
  fastify.get<{
    Params: { id: string };
  }>('/blind-schedules/:id/in-use', async (request, reply) => {
    const { id } = request.params;

    try {
      const schedule = await fastify.blindScheduleService.getScheduleById(id);

      if (!schedule) {
        return reply.code(404).send({
          success: false,
          error: 'Blind schedule not found',
        });
      }

      const inUse = fastify.blindScheduleService.isScheduleInUse(id);

      return reply.send({
        success: true,
        data: {
          id,
          inUse,
        },
      });
    } catch (error: any) {
      return reply.code(500).send({
        success: false,
        error: error.message,
      });
    }
  });

  /**
   * POST /blind-schedules
   * Create new blind schedule
   */
  fastify.post<{
    Body: {
      name: string;
      description?: string;
      startingStack: number;
      breakInterval: number;
      breakDuration: number;
      levels: Array<{
        smallBlind: number;
        bigBlind: number;
        ante?: number;
        duration: number;
        isBreak: boolean;
      }>;
    };
  }>('/blind-schedules', async (request, reply) => {
    const input = request.body;

    // Validate input with Zod
    const validationResult = CreateBlindSchemeInputSchema.safeParse(input);
    if (!validationResult.success) {
      const errors = formatValidationErrors(validationResult.error);
      return reply.code(400).send({
        success: false,
        error: 'Validation failed',
        validationErrors: errors,
      });
    }

    try {
      const schedule = await fastify.blindScheduleService.createSchedule({
        ...input,
        createdBy: 'demo-user', // Demo mode
      });

      return reply.code(201).send({
        success: true,
        data: schedule,
      });
    } catch (error: any) {
      return reply.code(400).send({
        success: false,
        error: error.message,
      });
    }
  });

  /**
   * PUT /blind-schedules/:id
   * Update existing blind schedule
   */
  fastify.put<{
    Params: { id: string };
    Body: {
      name?: string;
      description?: string;
      startingStack?: number;
      breakInterval?: number;
      breakDuration?: number;
      levels?: Array<{
        smallBlind: number;
        bigBlind: number;
        ante?: number;
        duration: number;
        isBreak: boolean;
      }>;
    };
  }>('/blind-schedules/:id', async (request, reply) => {
    const { id } = request.params;
    const updates = request.body;

    // Validate input with Zod (partial update schema)
    const validationResult = UpdateBlindSchemeInputSchema.safeParse(updates);
    if (!validationResult.success) {
      const errors = formatValidationErrors(validationResult.error);
      return reply.code(400).send({
        success: false,
        error: 'Validation failed',
        validationErrors: errors,
      });
    }

    try {
      const schedule = await fastify.blindScheduleService.updateSchedule(id, updates);

      return reply.send({
        success: true,
        data: schedule,
      });
    } catch (error: any) {
      if (error.message.includes('not found')) {
        return reply.code(404).send({
          success: false,
          error: error.message,
        });
      }
      // Check for isDefault conflict - return 403 and suggest duplicate
      if (error.message.includes('default') || error.message.includes('Cannot update')) {
        return reply.code(403).send({
          success: false,
          error: error.message,
          suggestion: 'Use POST /blind-schedules/:id/duplicate to create a copy',
        });
      }
      return reply.code(400).send({
        success: false,
        error: error.message,
      });
    }
  });

  /**
   * DELETE /blind-schedules/:id
   * Delete blind schedule
   */
  fastify.delete<{
    Params: { id: string };
  }>('/blind-schedules/:id', async (request, reply) => {
    const { id } = request.params;

    try {
      await fastify.blindScheduleService.deleteSchedule(id);

      return reply.code(204).send();
    } catch (error: any) {
      if (error.message.includes('not found')) {
        return reply.code(404).send({
          success: false,
          error: error.message,
        });
      }
      return reply.code(400).send({
        success: false,
        error: error.message,
      });
    }
  });

  /**
   * POST /blind-schedules/:id/duplicate
   * Duplicate a blind schedule (for editing defaults)
   */
  fastify.post<{
    Params: { id: string };
    Body: { newName?: string };
  }>('/blind-schedules/:id/duplicate', async (request, reply) => {
    const { id } = request.params;
    const { newName } = request.body || {};

    try {
      const duplicated = await fastify.blindScheduleService.duplicateSchedule(id, newName);

      return reply.code(201).send({
        success: true,
        data: duplicated,
      });
    } catch (error: any) {
      console.error('[Duplicate] Error:', error);
      return reply.code(400).send({
        success: false,
        error: error.message || 'Failed to duplicate scheme',
      });
    }
  });

  // ============================================================
  // T011: Blind Levels CRUD
  // ============================================================

  /**
   * GET /blind-schedules/:id/levels
   * Get levels for a schedule (paginated)
   */
  fastify.get<{
    Params: { id: string };
    Querystring: { offset?: string; limit?: string };
  }>('/blind-schedules/:id/levels', async (request, reply) => {
    const { id } = request.params;
    const offset = parseInt(request.query.offset || '0', 10);
    const limit = parseInt(request.query.limit || '50', 10);

    try {
      const result = await fastify.blindScheduleService.getLevels(id, offset, limit);

      return reply.send({
        success: true,
        data: result.levels,
        meta: {
          total: result.total,
          offset,
          limit,
        },
      });
    } catch (error: any) {
      return reply.code(404).send({
        success: false,
        error: error.message,
      });
    }
  });

  /**
   * PUT /blind-schedules/:id/levels
   * Replace all levels for a schedule
   */
  fastify.put<{
    Params: { id: string };
    Body: { levels: BlindLevel[] };
  }>('/blind-schedules/:id/levels', async (request, reply) => {
    const { id } = request.params;
    const { levels } = request.body;

    try {
      await fastify.blindScheduleService.updateLevels(id, levels);

      // Return updated levels
      const result = await fastify.blindScheduleService.getLevels(id, 0, 1000);

      return reply.send({
        success: true,
        data: result.levels,
      });
    } catch (error: any) {
      return reply.code(400).send({
        success: false,
        error: error.message,
      });
    }
  });

  // ============================================================
  // T012: Tournament Blind Level API
  // ============================================================

  /**
   * GET /tournaments/:id/blind-level
   * Get current blind level for a tournament
   * Returns current level info, next level, previous level
   */
  fastify.get<{
    Params: { id: string };
  }>('/tournaments/:id/blind-level', async (request, reply) => {
    const { id: tournamentId } = request.params;

    // TODO: Implement when TournamentService is updated
    // For now, return placeholder response
    try {
      const tournament = await fastify.tournamentService.getTournament(tournamentId);

      if (!tournament) {
        return reply.code(404).send({
          success: false,
          error: 'Tournament not found',
        });
      }

      if (!tournament.blindScheduleId) {
        return reply.code(400).send({
          success: false,
          error: 'Tournament does not have a blind schedule assigned',
        });
      }

      const schedule = await fastify.blindScheduleService.getScheduleById(tournament.blindScheduleId);

      if (!schedule) {
        return reply.code(404).send({
          success: false,
          error: 'Blind schedule not found',
        });
      }

      const currentLevel = schedule.levels.find(
        l => l.level === tournament.currentBlindLevel
      );

      if (!currentLevel) {
        return reply.code(400).send({
          success: false,
          error: 'Current blind level not found in schedule',
        });
      }

      const nextLevel = schedule.levels.find(
        l => l.level === tournament.currentBlindLevel + 1
      );

      const previousLevel = schedule.levels.find(
        l => l.level === tournament.currentBlindLevel - 1
      );

      return reply.send({
        success: true,
        data: {
          tournamentId,
          currentLevel: tournament.currentBlindLevel,
          blindSchedule: schedule,
          currentLevelInfo: currentLevel,
          nextLevel,
          previousLevel,
        },
      });
    } catch (error: any) {
      return reply.code(500).send({
        success: false,
        error: error.message,
      });
    }
  });

  /**
   * PUT /tournaments/:id/blind-level
   * Manual level change control
   * Body: { action: 'next' | 'previous' | 'set', level?: number }
   */
  fastify.put<{
    Params: { id: string };
    Body: { action: 'next' | 'previous' | 'set'; level?: number };
  }>('/tournaments/:id/blind-level', async (request, reply) => {
    const { id: tournamentId } = request.params;
    const { action, level } = request.body;

    try {
      const tournament = await fastify.tournamentService.getTournament(tournamentId);

      if (!tournament) {
        return reply.code(404).send({
          success: false,
          error: 'Tournament not found',
        });
      }

      if (!tournament.blindScheduleId) {
        return reply.code(400).send({
          success: false,
          error: 'Tournament does not have a blind schedule assigned',
        });
      }

      const schedule = await fastify.blindScheduleService.getScheduleById(tournament.blindScheduleId);

      if (!schedule) {
        return reply.code(404).send({
          success: false,
          error: 'Blind schedule not found',
        });
      }

      let newLevel: number;

      switch (action) {
        case 'next':
          newLevel = tournament.currentBlindLevel + 1;
          if (newLevel > schedule.levels.length) {
            return reply.code(400).send({
              success: false,
              error: 'Already at last level',
            });
          }
          break;

        case 'previous':
          newLevel = tournament.currentBlindLevel - 1;
          if (newLevel < 1) {
            return reply.code(400).send({
              success: false,
              error: 'Already at first level',
            });
          }
          break;

        case 'set':
          if (level === undefined || level < 1 || level > schedule.levels.length) {
            return reply.code(400).send({
              success: false,
              error: `Invalid level. Must be between 1 and ${schedule.levels.length}`,
            });
          }
          newLevel = level;
          break;

        default:
          return reply.code(400).send({
            success: false,
            error: 'Invalid action. Must be: next, previous, or set',
          });
      }

      // Update tournament's current blind level
      await fastify.tournamentService.updateTournament(tournamentId, {
        currentBlindLevel: newLevel,
      });

      // Get new level info
      const currentLevelInfo = schedule.levels.find(l => l.level === newLevel);
      const nextLevel = schedule.levels.find(l => l.level === newLevel + 1);
      const previousLevel = schedule.levels.find(l => l.level === newLevel - 1);

      // Broadcast level change via WebSocket
      broadcastToTournament(tournamentId, {
        type: 'level:change',
        data: {
          tournamentId,
          currentLevel: newLevel,
          currentLevelInfo,
          nextLevel,
          previousLevel,
        },
        timestamp: Date.now(),
      });

      return reply.send({
        success: true,
        data: {
          tournamentId,
          currentLevel: newLevel,
          blindSchedule: schedule,
          currentLevelInfo,
          nextLevel,
          previousLevel,
        },
      });
    } catch (error: any) {
      return reply.code(500).send({
        success: false,
        error: error.message,
      });
    }
  });
}
