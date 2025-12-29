/**
 * Player API Routes
 * REST endpoints for player and tournament player management
 *
 * Endpoints:
 * - GET    /api/tournaments/:id/players        - List tournament players
 * - POST   /api/tournaments/:id/players        - Add player to tournament
 * - PUT    /api/tournaments/:id/players/:pid   - Update tournament player
 * - DELETE /api/tournaments/:id/players/:pid   - Remove player from tournament
 * - GET    /api/players                         - Search players
 * - POST   /api/players                         - Create new player
 */

import { FastifyInstance, FastifyRequest, FastifyReply } from 'fastify';
import { z } from 'zod';
import { PlayerRepository, TournamentPlayerRepository } from '../../db/repositories/PlayerRepository';
import { TournamentRepository } from '../../db/repositories/TournamentRepository';
import { nanoid } from 'nanoid';

/**
 * Schemas
 */
const addPlayerSchema = z.object({
  playerId: z.string().optional(),
  name: z.string().min(1),
  email: z.string().email().optional(),
  phone: z.string().optional(),
  startingStack: z.number().int().positive(),
  tableName: z.string().optional(),
  seatNumber: z.number().int().optional(),
});

const updatePlayerSchema = z.object({
  finishPosition: z.number().int().optional(),
  winnings: z.number().optional(),
  tableName: z.string().optional(),
  seatNumber: z.number().int().optional(),
});

/**
 * Register player routes
 */
export async function playerRoutes(fastify: FastifyInstance): Promise<void> {
  const playerRepo = new PlayerRepository();
  const tournamentPlayerRepo = new TournamentPlayerRepository();
  const tournamentRepo = new TournamentRepository();

  /**
   * GET /api/tournaments/:id/players
   * List all players in a tournament
   */
  fastify.get<{
    Params: { id: string };
  }>('/tournaments/:id/players', async (request, reply) => {
    const { id } = request.params;

    try {
      const tournamentPlayers = tournamentPlayerRepo.findPlayersByTournament(id);

      // Get player details for each tournament player
      const players = tournamentPlayers.map(tp => {
        const player = playerRepo.findById(tp.player_id);
        return {
          tournamentPlayerId: tp.tournament_player_id,
          player: {
            playerId: tp.player_id,
            name: player?.name || 'Unknown',
            email: player?.email,
            phone: player?.phone,
          },
          startingStack: tp.starting_stack,
          finishPosition: tp.finish_position,
          winnings: tp.winnings,
          bustoutTime: tp.bustout_time,
          eliminations: tp.eliminations,
          tableName: tp.table_name,
          seatNumber: tp.seat_number,
          registeredAt: tp.registration_time,
        };
      });

      return reply.send({ players });
    } catch (error: any) {
      return reply.status(500).send({
        error: 'Internal Server Error',
        message: error.message,
      });
    }
  });

  /**
   * POST /api/tournaments/:id/players
   * Add a player to a tournament
   */
  fastify.post<{
    Params: { id: string };
    Body: z.infer<typeof addPlayerSchema>;
  }>('/tournaments/:id/players', async (request, reply) => {
    const { id: tournamentId } = request.params;
    const input = addPlayerSchema.parse(request.body);

    try {
      // Create or find player
      let playerId = input.playerId;

      if (!playerId) {
        // Check if player exists by email
        if (input.email) {
          const existing = playerRepo.findByEmail(input.email);
          if (existing) {
            playerId = existing.playerId;
          }
        }

        // Create new player if not found
        if (!playerId) {
          playerId = `pl-${nanoid(8)}`;
          playerRepo.createPlayer({
            playerId,
            name: input.name,
            email: input.email,
            phone: input.phone,
          });
        }
      }

      // Check if player already in tournament
      const existing = tournamentPlayerRepo.findPlayersByTournament(tournamentId)
        .find(tp => tp.player_id === playerId);

      if (existing) {
        return reply.status(400).send({
          error: 'Bad Request',
          message: 'Player already in tournament',
        });
      }

      // Add player to tournament
      const tournamentPlayerId = `tp-${nanoid(8)}`;
      tournamentPlayerRepo.addPlayerToTournament({
        tournamentPlayerId,
        tournamentId,
        playerId,
        startingStack: input.startingStack,
        tableName: input.tableName,
        seatNumber: input.seatNumber,
      });

      return reply.status(201).send({
        tournamentPlayerId,
        playerId,
        tournamentId,
        startingStack: input.startingStack,
      });
    } catch (error: any) {
      return reply.status(500).send({
        error: 'Internal Server Error',
        message: error.message,
      });
    }
  });

  /**
   * PUT /api/tournaments/:id/players/:playerId
   * Update a player in a tournament
   */
  fastify.put<{
    Params: { id: string; playerId: string };
    Body: z.infer<typeof updatePlayerSchema>;
  }>('/tournaments/:id/players/:playerId', async (request, reply) => {
    const { playerId } = request.params;
    const input = updatePlayerSchema.parse(request.body);

    try {
      tournamentPlayerRepo.updateTournamentPlayer(playerId, {
        finish_position: input.finishPosition,
        winnings: input.winnings,
        table_name: input.tableName,
        seat_number: input.seatNumber,
      });

      return reply.send({ success: true });
    } catch (error: any) {
      return reply.status(500).send({
        error: 'Internal Server Error',
        message: error.message,
      });
    }
  });

  /**
   * DELETE /api/tournaments/:id/players/:playerId
   * Remove a player from a tournament (only allowed for upcoming or completed tournaments)
   */
  fastify.delete<{
    Params: { id: string; playerId: string };
  }>('/tournaments/:id/players/:playerId', async (request, reply) => {
    const { id: tournamentId, playerId: tournamentPlayerId } = request.params;

    try {
      // Check tournament status
      const tournament = tournamentRepo.findById(tournamentId);
      if (!tournament) {
        return reply.status(404).send({
          error: 'Not Found',
          message: 'Tournament not found',
        });
      }

      // Only allow removal from upcoming or completed tournaments
      if (tournament.status === 'active') {
        return reply.status(400).send({
          error: 'Bad Request',
          message: 'Cannot remove players from active tournaments',
        });
      }

      tournamentPlayerRepo.removePlayerFromTournament(tournamentPlayerId);
      return reply.status(204).send();
    } catch (error: any) {
      return reply.status(500).send({
        error: 'Internal Server Error',
        message: error.message,
      });
    }
  });

  /**
   * GET /api/players?search=query
   * Search for players by name
   */
  fastify.get<{
    Querystring: { search?: string };
  }>('/players', async (request, reply) => {
    const { search } = request.query;

    try {
      if (!search) {
        // Return all players when no search query
        const allPlayers = playerRepo.findAll();
        return reply.send({
          players: allPlayers.map(p => ({
            playerId: p.player_id,
            name: p.name,
            email: p.email || undefined,
            phone: p.phone || undefined,
          })),
        });
      }

      const players = playerRepo.findByName(search);
      return reply.send({
        players: players.map(p => ({
          playerId: p.playerId,
          name: p.name,
          email: p.email,
          phone: p.phone,
        })),
      });
    } catch (error: any) {
      return reply.status(500).send({
        error: 'Internal Server Error',
        message: error.message,
      });
    }
  });

  /**
   * POST /api/players
   * Create a new player
   */
  fastify.post<{
    Body: { name: string; email?: string; phone?: string };
  }>('/players', async (request, reply) => {
    const { name, email, phone } = request.body;

    try {
      const playerId = `pl-${nanoid(8)}`;
      playerRepo.createPlayer({ playerId, name, email, phone });

      return reply.status(201).send({
        playerId,
        name,
        email,
        phone,
      });
    } catch (error: any) {
      return reply.status(500).send({
        error: 'Internal Server Error',
        message: error.message,
      });
    }
  });

  /**
   * DELETE /api/players/:playerId
   * Delete a player (only if not in any tournament)
   */
  fastify.delete<{
    Params: { playerId: string };
  }>('/players/:playerId', async (request, reply) => {
    const { playerId } = request.params;

    try {
      // Check if player is in any tournament
      const tournamentPlayers = tournamentPlayerRepo.findAll();
      const playerInTournament = tournamentPlayers.find(tp => tp.player_id === playerId);

      if (playerInTournament) {
        return reply.status(400).send({
          error: 'Bad Request',
          message: 'Cannot delete player who has participated in tournaments',
        });
      }

      // Safe to delete
      playerRepo.delete(playerId);
      return reply.status(204).send();
    } catch (error: any) {
      return reply.status(500).send({
        error: 'Internal Server Error',
        message: error.message,
      });
    }
  });
}
