/**
 * Mobile repository pattern
 * Similar to controller repositories but uses async API
 */

import { getConnection } from '../connection';
import type { Tournament } from '@shared/types/tournament';
import type { Player } from '@shared/types/player';
import type { TournamentPlayer } from '@shared/types/player';

// Tournament repository
export class MobileTournamentRepository {
  async findById(id: string): Promise<Tournament | undefined> {
    const db = await getConnection();
    const row = await db.getFirstAsync<any>(
      'SELECT * FROM tournaments WHERE tournament_id = ?',
      [id]
    );
    return row ? this.toDomain(row) : undefined;
  }

  async findAll(): Promise<Tournament[]> {
    const db = await getConnection();
    const rows = await db.getAllAsync<any>('SELECT * FROM tournaments');
    return rows.map((row: any) => this.toDomain(row));
  }

  async insert(tournament: Tournament): Promise<void> {
    const db = await getConnection();
    await db.runAsync(
      `INSERT INTO tournaments (
        tournament_id, name, description, start_time, end_time, status,
        current_blind_level, timer_is_running, timer_is_paused, timer_elapsed_time,
        timer_last_update, prize_pool, blind_schedule_id, created_by, created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        tournament.tournamentId,
        tournament.name,
        tournament.description ?? null,
        tournament.startTime.toISOString(),
        tournament.endTime?.toISOString() ?? null,
        tournament.status,
        tournament.currentBlindLevel,
        Number(tournament.timerState.isRunning),
        Number(tournament.timerState.isPaused),
        tournament.timerState.elapsedTime,
        tournament.timerState.lastUpdateTime.toISOString(),
        tournament.prizePool ?? null,
        tournament.blindScheduleId ?? null,
        tournament.createdBy,
        tournament.createdAt.toISOString(),
        tournament.updatedAt.toISOString(),
      ]
    );
  }

  async update(id: string, updates: Partial<Tournament>): Promise<void> {
    // Implementation similar to insert but with UPDATE
    const db = await getConnection();
    // TODO: Implement update logic
  }

  toDomain(row: any): Tournament {
    return {
      tournamentId: row.tournament_id,
      name: row.name,
      description: row.description,
      startTime: new Date(row.start_time),
      endTime: row.end_time ? new Date(row.end_time) : undefined,
      status: row.status,
      currentBlindLevel: row.current_blind_level,
      timerState: {
        isRunning: Boolean(row.timer_is_running),
        isPaused: Boolean(row.timer_is_paused),
        level: row.current_blind_level,
        elapsedTime: row.timer_elapsed_time,
        remainingTime: row.timer_remaining_time,
        tenths: Math.floor((row.timer_elapsed_time % 1000) / 100),
        lastUpdateTime: new Date(row.timer_last_update),
      },
      prizePool: row.prize_pool,
      blindScheduleId: row.blind_schedule_id,
      createdBy: row.created_by,
      createdAt: new Date(row.created_at),
      updatedAt: new Date(row.updated_at),
    };
  }
}

// Player repository
export class MobilePlayerRepository {
  async findById(id: string): Promise<Player | undefined> {
    const db = await getConnection();
    const row = await db.getFirstAsync<any>(
      'SELECT * FROM players WHERE player_id = ?',
      [id]
    );
    return row ? this.toDomain(row) : undefined;
  }

  async insert(player: Player): Promise<void> {
    const db = await getConnection();
    await db.runAsync(
      'INSERT INTO players (player_id, name, email, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
      [
        player.playerId,
        player.name,
        player.email ?? null,
        player.phone ?? null,
        player.createdAt.toISOString(),
        player.updatedAt.toISOString(),
      ]
    );
  }

  toDomain(row: any): Player {
    return {
      playerId: row.player_id,
      name: row.name,
      email: row.email,
      phone: row.phone,
      stats: {
        tournamentsPlayed: 0,
        wins: 0,
        totalWinnings: 0,
        bustouts: 0,
      },
      createdAt: new Date(row.created_at),
      updatedAt: new Date(row.updated_at),
    };
  }
}

// Tournament Player repository
export class MobileTournamentPlayerRepository {
  async findByTournament(tournamentId: string): Promise<TournamentPlayer[]> {
    const db = await getConnection();
    const rows = await db.getAllAsync<any>(
      'SELECT * FROM tournament_players WHERE tournament_id = ? ORDER BY finish_position',
      [tournamentId]
    );
    return rows.map((row: any) => this.toDomain(row));
  }

  async insert(tp: TournamentPlayer): Promise<void> {
    const db = await getConnection();
    await db.runAsync(
      `INSERT INTO tournament_players (
        tournament_player_id, tournament_id, player_id, registration_time,
        starting_stack, finish_position, winnings, bustout_time, eliminations,
        table_name, seat_number, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        tp.tournamentPlayerId,
        tp.tournamentId,
        tp.playerId,
        tp.registrationTime.toISOString(),
        tp.startingStack,
        tp.finishPosition ?? null,
        tp.winnings ?? null,
        tp.bustoutTime?.toISOString() ?? null,
        tp.eliminations ?? null,
        tp.tableName ?? null,
        tp.seatNumber ?? null,
        tp.updatedAt.toISOString(),
      ]
    );
  }

  toDomain(row: any): TournamentPlayer {
    return {
      tournamentPlayerId: row.tournament_player_id,
      tournamentId: row.tournament_id,
      playerId: row.player_id,
      registrationTime: new Date(row.registration_time),
      startingStack: row.starting_stack,
      finishPosition: row.finishPosition,
      winnings: row.winnings,
      bustoutTime: row.bustout_time ? new Date(row.bustout_time) : undefined,
      eliminations: row.eliminations,
      tableName: row.table_name,
      seatNumber: row.seat_number,
      updatedAt: new Date(row.updated_at),
    };
  }
}
