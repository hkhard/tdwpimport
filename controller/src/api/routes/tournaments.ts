/**
 * Tournament API Routes
 * CRUD operations for tournaments
 *
 * Constitution Requirements:
 * - US5-A1: 100+ concurrent tournaments
 * - US2-A1: Offline tournament CRUD
 */

import type { FastifyInstance, FastifyRequest, FastifyReply } from 'fastify';
import { authMiddleware } from '../../middleware/auth';
import type { TournamentService } from '../../services/tournament/TournamentService';
import type { CreateTournamentInput, UpdateTournamentInput, TournamentListFilter } from '../../services/tournament/TournamentService';

interface AuthenticatedRequest extends FastifyRequest {
  userId?: string;
}

// Extend Fastify schema
declare module 'fastify' {
  interface FastifyInstance {
    tournamentService: TournamentService;
  }
}

/**
 * Register tournament routes
 */
export async function tournamentRoutes(fastify: FastifyInstance): Promise<void> {
  // List tournaments
  fastify.get<{
    Querystring: TournamentListFilter;
  }>('/tournaments', async (request, reply) => {
    const filter = request.query;
    const result = await fastify.tournamentService.listTournaments(filter);
    return reply.send(result);
  });

  // Get tournament by ID
  fastify.get<{
    Params: { id: string };
  }>('/tournaments/:id', async (request, reply) => {
    const { id } = request.params;
    const tournament = await fastify.tournamentService.getTournament(id);

    if (!tournament) {
      return reply.code(404).send({ error: 'Tournament not found' });
    }

    return reply.send(tournament);
  });

  // Create tournament (DEMO MODE - no auth required)
  fastify.post<{
    Body: CreateTournamentInput;
  }>('/tournaments', async (request, reply) => {
    const input = request.body;

    // Convert startTime from string to Date if needed
    const startTime = input.startTime instanceof Date
      ? input.startTime
      : new Date(input.startTime);

    // Demo mode: use a default creator ID
    const tournament = await fastify.tournamentService.createTournament({
      ...input,
      startTime,
      createdBy: 'demo-user',
    });

    return reply.code(201).send(tournament);
  });

  // Update tournament (DEMO MODE - no auth required)
  fastify.put<{
    Params: { id: string };
    Body: UpdateTournamentInput;
  }>('/tournaments/:id', async (request, reply) => {
    const { id } = request.params;
    const input = request.body;

    // Convert Date fields from strings to Date objects
    const updateData: UpdateTournamentInput = { ...input };
    if (input.startTime && !(input.startTime instanceof Date)) {
      updateData.startTime = new Date(input.startTime);
    }
    if (input.endTime && !(input.endTime instanceof Date)) {
      updateData.endTime = new Date(input.endTime);
    }

    try {
      const tournament = await fastify.tournamentService.updateTournament(id, updateData);
      return reply.send(tournament);
    } catch (error: any) {
      if (error.message.includes('not found')) {
        return reply.code(404).send({ error: error.message });
      }
      throw error;
    }
  });

  // Patch tournament status (DEMO MODE - no auth required)
  fastify.patch<{
    Params: { id: string };
    Body: { status: string };
  }>('/tournaments/:id/status', async (request, reply) => {
    const { id } = request.params;
    const { status } = request.body;

    try {
      const tournament = await fastify.tournamentService.updateStatus(id, status as any);
      return reply.send(tournament);
    } catch (error: any) {
      if (error.message.includes('not found')) {
        return reply.code(404).send({ error: error.message });
      }
      throw error;
    }
  });

  // Delete tournament (DEMO MODE - no auth required)
  fastify.delete<{
    Params: { id: string };
  }>('/tournaments/:id', async (request, reply) => {
    const { id } = request.params;

    try {
      await fastify.tournamentService.deleteTournament(id);
      return reply.code(204).send();
    } catch (error: any) {
      if (error.message.includes('not found')) {
        return reply.code(404).send({ error: error.message });
      }
      if (error.message.includes('active tournament')) {
        return reply.code(400).send({ error: error.message });
      }
      throw error;
    }
  });

  // Bind device to tournament (DEMO MODE - no auth required)
  fastify.post<{
    Params: { id: string };
    Body: { deviceId: string };
  }>('/tournaments/:id/bind-device', async (request, reply) => {
    const { id } = request.params;
    const { deviceId } = request.body;

    try {
      await fastify.tournamentService.bindDevice(id, deviceId);
      return reply.send({ success: true });
    } catch (error: any) {
      if (error.message.includes('not found')) {
        return reply.code(404).send({ error: error.message });
      }
      throw error;
    }
  });

  // Unbind device from tournament (DEMO MODE - no auth required)
  fastify.post<{
    Params: { id: string };
  }>('/tournaments/:id/unbind-device', async (request, reply) => {
    const { id } = request.params;

    try {
      await fastify.tournamentService.unbindDevice(id);
      return reply.send({ success: true });
    } catch (error: any) {
      if (error.message.includes('not found')) {
        return reply.code(404).send({ error: error.message });
      }
      throw error;
    }
  });

  // Get tournament by device
  fastify.get<{
    Querystring: { deviceId: string };
  }>('/tournaments/by-device', async (request, reply) => {
    const { deviceId } = request.query;
    const tournament = await fastify.tournamentService.getTournamentByDevice(deviceId);

    if (!tournament) {
      return reply.code(404).send({ error: 'No tournament found for device' });
    }

    return reply.send(tournament);
  });

  // Get tournament statistics
  fastify.get('/tournaments/stats', async (request, reply) => {
    const stats = fastify.tournamentService.getStatistics();
    return reply.send(stats);
  });

  /**
   * Public tournament view endpoint (T098)
   * Read-only access for spectators/players - no auth required
   * Returns sanitized tournament data safe for public viewing
   */
  fastify.get<{
    Params: { id: string };
  }>('/tournaments/:id/public', async (request, reply) => {
    const { id } = request.params;
    const tournament = await fastify.tournamentService.getTournament(id);

    if (!tournament) {
      return reply.code(404).send({ error: 'Tournament not found' });
    }

    // Return sanitized public view (exclude sensitive data)
    return reply.send({
      id: tournament.tournamentId,
      name: tournament.name,
      status: tournament.status,
      startTime: tournament.startTime,
      currentLevel: tournament.currentBlindLevel,
      clock: {
        remaining: tournament.timerState?.remainingTime,
        paused: tournament.timerState?.isPaused,
      },
      // Players would come from a separate players table/service
      // payouts: tournament.payoutStructure,
      // Exclude: createdBy, controllerDeviceId, internal metadata
    });
  });

  /**
   * SSE endpoint for real-time tournament updates (T099)
   * No auth required for public/spectator access
   * Streams tournament state changes via Server-Sent Events
   */
  fastify.get<{
    Params: { id: string };
  }>('/tournaments/:id/stream', async (request, reply) => {
    const { id } = request.params;

    // Verify tournament exists
    const tournament = await fastify.tournamentService.getTournament(id);
    if (!tournament) {
      return reply.code(404).send({ error: 'Tournament not found' });
    }

    // Set SSE headers
    reply.raw.setHeader('Content-Type', 'text/event-stream');
    reply.raw.setHeader('Cache-Control', 'no-cache');
    reply.raw.setHeader('Connection', 'keep-alive');
    reply.raw.setHeader('X-Accel-Buffering', 'no'); // Disable nginx buffering

    // Send initial data
    const sendEvent = (event: string, data: any) => {
      reply.raw.write(`event: ${event}\n`);
      reply.raw.write(`data: ${JSON.stringify(data)}\n\n`);
    };

    // Send initial state
    sendEvent('connected', {
      tournamentId: id,
      timestamp: Date.now(),
    });

    sendEvent('state', {
      id: tournament.tournamentId,
      name: tournament.name,
      status: tournament.status,
      currentLevel: tournament.currentBlindLevel,
      clock: {
        remaining: tournament.timerState?.remainingTime,
        paused: tournament.timerState?.isPaused,
      },
    });

    // Setup update interval (poll every 1s for changes)
    let lastState = JSON.stringify({
      status: tournament.status,
      currentLevel: tournament.currentBlindLevel,
      clock: {
        remaining: tournament.timerState?.remainingTime,
        paused: tournament.timerState?.isPaused,
      },
    });

    const intervalId = setInterval(async () => {
      try {
        const current = await fastify.tournamentService.getTournament(id);
        if (!current) {
          sendEvent('error', { message: 'Tournament no longer exists' });
          clearInterval(intervalId);
          reply.raw.end();
          return;
        }

        const currentState = JSON.stringify({
          status: current.status,
          currentLevel: current.currentBlindLevel,
          clock: {
            remaining: current.timerState?.remainingTime,
            paused: current.timerState?.isPaused,
          },
        });

        // Only send if state changed
        if (currentState !== lastState) {
          sendEvent('update', {
            status: current.status,
            currentLevel: current.currentBlindLevel,
            clock: {
              remaining: current.timerState?.remainingTime,
              paused: current.timerState?.isPaused,
            },
            timestamp: Date.now(),
          });
          lastState = currentState;
        }

        // Send heartbeat every 15s
        sendEvent('heartbeat', { timestamp: Date.now() });
      } catch (error) {
        console.error('SSE stream error:', error);
        sendEvent('error', { message: 'Stream error occurred' });
        clearInterval(intervalId);
        reply.raw.end();
      }
    }, 1000);

    // Clean up on client disconnect
    reply.raw.on('close', () => {
      clearInterval(intervalId);
      console.log(`SSE client disconnected from tournament ${id}`);
    });

    // Keep connection alive
    request.raw.on('close', () => {
      clearInterval(intervalId);
    });
  });
}
