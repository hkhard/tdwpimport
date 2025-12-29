/**
 * Player repository
 */

import { BaseRepository } from './BaseRepository';
import type { Player } from '@shared/types/player';

export interface PlayerRow {
  player_id: string;
  name: string;
  email: string | null;
  phone: string | null;
  created_at: string;
  updated_at: string;
}

export interface TournamentPlayerRow {
  tournament_player_id: string;
  tournament_id: string;
  player_id: string;
  registration_time: string;
  starting_stack: number;
  finish_position: number | null;
  winnings: number | null;
  bustout_time: string | null;
  eliminations: number;
  table_name: string | null;
  seat_number: number | null;
  updated_at: string;
}

export class PlayerRepository extends BaseRepository<PlayerRow> {
  constructor() {
    super('players', 'player_id');
  }

  toDomain(row: PlayerRow): Player {
    return {
      playerId: row.player_id,
      name: row.name,
      email: row.email || undefined,
      phone: row.phone || undefined,
      stats: {
        tournamentsPlayed: 0, // TODO: calculate from tournament_players
        wins: 0,
        totalWinnings: 0,
        bustouts: 0,
      },
      createdAt: new Date(row.created_at),
      updatedAt: new Date(row.updated_at),
    };
  }

  findByName(name: string): Player[] {
    const rows = this.query<PlayerRow>(
      'SELECT * FROM players WHERE name LIKE ?',
      [`%${name}%`]
    );
    return rows.map(row => this.toDomain(row));
  }

  findByEmail(email: string): Player | undefined {
    const row = this.queryOne<PlayerRow>(
      'SELECT * FROM players WHERE email = ?',
      [email]
    );
    return row ? this.toDomain(row) : undefined;
  }

  createPlayer(data: { playerId: string; name: string; email?: string; phone?: string }): void {
    const row: Partial<PlayerRow> = {
      player_id: data.playerId,
      name: data.name,
      email: data.email || null,
      phone: data.phone || null,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };
    this.insert(row);
  }

  updatePlayer(playerId: string, updates: Partial<PlayerRow>): void {
    this.update(playerId, {
      ...updates,
      updated_at: new Date().toISOString(),
    });
  }
}

export class TournamentPlayerRepository extends BaseRepository<TournamentPlayerRow> {
  constructor() {
    super('tournament_players', 'tournament_player_id');
  }

  /**
   * Find all players for a tournament with player details
   */
  findPlayersByTournament(tournamentId: string): TournamentPlayerRow[] {
    return this.findWhere({ tournament_id: tournamentId });
  }

  /**
   * Add a player to a tournament
   */
  addPlayerToTournament(data: {
    tournamentPlayerId: string;
    tournamentId: string;
    playerId: string;
    startingStack: number;
    tableName?: string;
    seatNumber?: number;
  }): void {
    const row: Partial<TournamentPlayerRow> = {
      tournament_player_id: data.tournamentPlayerId,
      tournament_id: data.tournamentId,
      player_id: data.playerId,
      registration_time: new Date().toISOString(),
      starting_stack: data.startingStack,
      finish_position: null,
      winnings: null,
      bustout_time: null,
      eliminations: 0,
      table_name: data.tableName || null,
      seat_number: data.seatNumber || null,
      updated_at: new Date().toISOString(),
    };
    this.insert(row);
  }

  /**
   * Update player in tournament
   */
  updateTournamentPlayer(tournamentPlayerId: string, updates: Partial<TournamentPlayerRow>): void {
    this.update(tournamentPlayerId, {
      ...updates,
      updated_at: new Date().toISOString(),
    });
  }

  /**
   * Remove player from tournament
   */
  removePlayerFromTournament(tournamentPlayerId: string): void {
    this.delete(tournamentPlayerId);
  }
}
