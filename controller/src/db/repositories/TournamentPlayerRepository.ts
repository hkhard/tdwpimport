/**
 * Tournament Player repository
 */

import { BaseRepository } from './BaseRepository';
import type { TournamentPlayer, PlayerListItem } from '@shared/types/player';

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

export class TournamentPlayerRepository extends BaseRepository<TournamentPlayerRow> {
  constructor() {
    super('tournament_players', 'tournament_player_id');
  }

  toDomain(row: TournamentPlayerRow): TournamentPlayer {
    return {
      tournamentPlayerId: row.tournament_player_id,
      tournamentId: row.tournament_id,
      playerId: row.player_id,
      registrationTime: new Date(row.registration_time),
      startingStack: row.starting_stack,
      finishPosition: row.finish_position || undefined,
      winnings: row.winnings || undefined,
      bustoutTime: row.bustout_time ? new Date(row.bustout_time) : undefined,
      eliminations: row.eliminations,
      tableName: row.table_name || undefined,
      seatNumber: row.seat_number || undefined,
      updatedAt: new Date(row.updated_at),
    };
  }

  toListItem(row: TournamentPlayerRow & { player_name: string }): PlayerListItem {
    return {
      tournamentPlayerId: row.tournament_player_id,
      playerId: row.player_id,
      playerName: row.player_name,
      tableName: row.table_name || undefined,
      seatNumber: row.seat_number || undefined,
      finishPosition: row.finish_position || undefined,
      isBusted: row.finish_position !== null,
    };
  }

  findByTournament(tournamentId: string): TournamentPlayer[] {
    const rows = this.query<TournamentPlayerRow>(
      'SELECT * FROM tournament_players WHERE tournament_id = ? ORDER BY finish_position',
      [tournamentId]
    );
    return rows.map(row => this.toDomain(row));
  }

  findActivePlayers(tournamentId: string): PlayerListItem[] {
    const rows = this.query<TournamentPlayerRow & { player_name: string }>(
      `SELECT tp.*, p.name as player_name
       FROM tournament_players tp
       JOIN players p ON tp.player_id = p.player_id
       WHERE tp.tournament_id = ? AND tp.finish_position IS NULL
       ORDER BY tp.table_name, tp.seat_number`,
      [tournamentId]
    );
    return rows.map(row => this.toListItem(row));
  }

  countActivePlayers(tournamentId: string): number {
    const row = this.queryOne<{ count: number }>(
      'SELECT COUNT(*) as count FROM tournament_players WHERE tournament_id = ? AND finish_position IS NULL',
      [tournamentId]
    );
    return row?.count || 0;
  }
}
