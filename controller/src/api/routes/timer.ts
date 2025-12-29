/**
 * Timer API Routes
 * REST endpoints for tournament timer control
 *
 * Endpoints:
 * - POST   /api/tournaments/:id/start       - Start timer
 * - POST   /api/tournaments/:id/pause       - Pause timer
 * - POST   /api/tournaments/:id/resume      - Resume timer
 * - GET    /api/tournaments/:id/timer       - Get timer state
 * - DELETE /api/tournaments/:id/timer       - Reset timer
 * - POST   /api/tournaments/:id/level       - Set blind level
 * - POST   /api/tournaments/:id/timer/adjust - Adjust time
 */

import { FastifyInstance, FastifyRequest, FastifyReply } from 'fastify';
import { z } from 'zod';
import { TimerService } from '../../services/timerService';
import { getConnection } from '../../db/connection';
import { broadcastTimerStart, broadcastTimerPause, broadcastTimerResume, broadcastLevelChange } from '../../websocket/broadcaster';

/**
 * Schemas
 */
const startParamsSchema = z.object({
  id: z.string(),
});

const levelBodySchema = z.object({
  level: z.number().int().positive(),
});

const adjustBodySchema = z.object({
  milliseconds: z.number(),
});

/**
 * Register timer routes
 */
export async function timerRoutes(fastify: FastifyInstance): Promise<void> {
  const db = getConnection();
  const timerService = new TimerService({ db });

  /**
   * GET /api/tournaments/:id/timer
   * Get current timer state
   */
  fastify.get('/tournaments/:id/timer', async (request, reply) => {
    const params = startParamsSchema.parse(request.params);

    // Try to get from memory first
    let state = timerService.getState(params.id);

    // If not in memory, load from database
    if (!state) {
      state = await timerService.loadTimer(params.id);
    }

    if (!state) {
      return reply.status(404).send({
        error: 'Not Found',
        message: 'Timer not found for this tournament',
      });
    }

    return reply.send(state);
  });

  /**
   * POST /api/tournaments/:id/start
   * Start the timer
   */
  fastify.post('/tournaments/:id/start', async (request, reply) => {
    const params = startParamsSchema.parse(request.params);
    const deviceId = request.headers['x-device-id'] as string | undefined;

    try {
      const state = await timerService.start(params.id, deviceId);
      broadcastTimerStart(params.id, state);
      return reply.send(state);
    } catch (error) {
      return reply.status(400).send({
        error: 'Bad Request',
        message: (error as Error).message,
      });
    }
  });

  /**
   * POST /api/tournaments/:id/pause
   * Pause the timer
   */
  fastify.post('/tournaments/:id/pause', async (request, reply) => {
    const params = startParamsSchema.parse(request.params);
    const deviceId = request.headers['x-device-id'] as string | undefined;

    try {
      const state = await timerService.pause(params.id, deviceId);
      broadcastTimerPause(params.id, state);
      return reply.send(state);
    } catch (error) {
      return reply.status(400).send({
        error: 'Bad Request',
        message: (error as Error).message,
      });
    }
  });

  /**
   * POST /api/tournaments/:id/resume
   * Resume from pause
   */
  fastify.post('/tournaments/:id/resume', async (request, reply) => {
    const params = startParamsSchema.parse(request.params);
    const deviceId = request.headers['x-device-id'] as string | undefined;

    try {
      const state = await timerService.resume(params.id, deviceId);
      broadcastTimerResume(params.id, state);
      return reply.send(state);
    } catch (error) {
      return reply.status(400).send({
        error: 'Bad Request',
        message: (error as Error).message,
      });
    }
  });

  /**
   * DELETE /api/tournaments/:id/timer
   * Reset the timer
   */
  fastify.delete('/tournaments/:id/timer', async (request, reply) => {
    const params = startParamsSchema.parse(request.params);

    try {
      await timerService.stop(params.id);
      return reply.send({ success: true });
    } catch (error) {
      return reply.status(400).send({
        error: 'Bad Request',
        message: (error as Error).message,
      });
    }
  });

  /**
   * POST /api/tournaments/:id/level
   * Set blind level
   */
  fastify.post('/tournaments/:id/level', async (request, reply) => {
    const params = startParamsSchema.parse(request.params);
    const body = levelBodySchema.parse(request.body);
    const deviceId = request.headers['x-device-id'] as string | undefined;

    try {
      const previousState = timerService.getState(params.id);
      const state = await timerService.setLevel(params.id, body.level, deviceId);
      if (previousState) broadcastLevelChange(params.id, state, previousState);
      return reply.send(state);
    } catch (error) {
      return reply.status(400).send({
        error: 'Bad Request',
        message: (error as Error).message,
      });
    }
  });

  /**
   * POST /api/tournaments/:id/timer/adjust
   * Adjust timer time
   */
  fastify.post('/tournaments/:id/timer/adjust', async (request, reply) => {
    const params = startParamsSchema.parse(request.params);
    const body = adjustBodySchema.parse(request.body);
    const deviceId = request.headers['x-device-id'] as string | undefined;

    try {
      const state = await timerService.adjustTime(params.id, body.milliseconds, deviceId);
      return reply.send(state);
    } catch (error) {
      return reply.status(400).send({
        error: 'Bad Request',
        message: (error as Error).message,
      });
    }
  });

  /**
   * GET /api/time
   * Get server time for clock synchronization
   */
  fastify.get('/time', async (request, reply) => {
    const requestTime = new Date();

    return reply.send({
      serverTime: new Date().toISOString(),
      requestTime: requestTime.toISOString(),
      responseTime: new Date().toISOString(),
    });
  });
}

// Export TimerService for use in WebSocket server
export { TimerService };
