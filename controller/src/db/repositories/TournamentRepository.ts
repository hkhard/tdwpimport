/**
 * Tournament repository
 */

import { BaseRepository } from './BaseRepository';
import type { Tournament } from '@shared/types/tournament';

export interface TournamentRow {
  tournament_id: string;
  name: string;
  description: string | null;
  start_time: string;
  end_time: string | null;
  status: string;
  current_blind_level: number;
  timer_is_running: number;
  timer_is_paused: number;
  timer_elapsed_time: number;
  timer_tenths: number;
  timer_remaining_time: number | null;
  timer_last_update: string;
  prize_pool: number | null;
  blind_schedule_id: string | null;
  controller_device_id: string | null;
  created_by: string;
  created_at: string;
  updated_at: string;
}

export class TournamentRepository extends BaseRepository<TournamentRow> {
  constructor() {
    super('tournaments', 'tournament_id');
  }

  /**
   * Find by ID and return Tournament domain object
   * Note: Cannot override base class method due to type incompatibility
   */
  findTournamentById(id: string): Tournament | undefined {
    const row = super.findById(id);
    return row ? this.toDomain(row) : undefined;
  }

  /**
   * Find all tournaments and return Tournament domain objects
   * Note: Cannot override base class method due to type incompatibility
   */
  findAllTournaments(): Tournament[] {
    const rows = super.findAll();
    return rows.map(row => this.toDomain(row));
  }

  /**
   * Update tournament with Tournament domain object partial
   */
  updateTournament(id: string, updates: Partial<Tournament>): void {
    const rowUpdates = this.toRow(updates);
    super.update(id, rowUpdates);
  }

  /**
   * Insert tournament with Tournament domain object
   */
  insertTournament(tournament: Partial<Tournament>): void {
    const row = this.toRow(tournament);
    super.insert(row);
  }

  toDomain(row: TournamentRow): Tournament {
    return {
      tournamentId: row.tournament_id,
      name: row.name,
      description: row.description || undefined,
      startTime: new Date(row.start_time),
      endTime: row.end_time ? new Date(row.end_time) : undefined,
      status: row.status as Tournament['status'],
      currentBlindLevel: row.current_blind_level,
      timerState: {
        isRunning: Boolean(row.timer_is_running),
        isPaused: Boolean(row.timer_is_paused),
        level: row.current_blind_level,
        elapsedTime: row.timer_elapsed_time,
        tenths: row.timer_tenths,
        remainingTime: row.timer_remaining_time ?? undefined,
        lastUpdateTime: new Date(row.timer_last_update),
      },
      prizePool: row.prize_pool || undefined,
      blindScheduleId: row.blind_schedule_id || undefined,
      controllerDeviceId: row.controller_device_id ?? undefined,
      createdBy: row.created_by,
      createdAt: new Date(row.created_at),
      updatedAt: new Date(row.updated_at),
    };
  }

  /**
   * Convert Tournament domain object to TournamentRow for database operations
   */
  toRow(tournament: Partial<Tournament>): Partial<TournamentRow> {
    const row: Partial<TournamentRow> = {};

    if (tournament.tournamentId !== undefined) row.tournament_id = tournament.tournamentId;
    if (tournament.name !== undefined) row.name = tournament.name;
    if (tournament.description !== undefined) row.description = tournament.description || null;
    if (tournament.startTime !== undefined) row.start_time = tournament.startTime.toISOString();
    if (tournament.endTime !== undefined) row.end_time = tournament.endTime.toISOString();
    if (tournament.status !== undefined) row.status = tournament.status;
    if (tournament.currentBlindLevel !== undefined) row.current_blind_level = tournament.currentBlindLevel;
    if (tournament.prizePool !== undefined) row.prize_pool = tournament.prizePool || null;
    if (tournament.blindScheduleId !== undefined) row.blind_schedule_id = tournament.blindScheduleId || null;
    if (tournament.controllerDeviceId !== undefined) row.controller_device_id = tournament.controllerDeviceId ?? null;
    if (tournament.createdBy !== undefined) row.created_by = tournament.createdBy;

    // Handle timerState - convert to individual columns
    if (tournament.timerState !== undefined) {
      row.timer_is_running = tournament.timerState.isRunning ? 1 : 0;
      row.timer_is_paused = tournament.timerState.isPaused ? 1 : 0;
      row.timer_elapsed_time = tournament.timerState.elapsedTime;
      row.timer_tenths = tournament.timerState.tenths;
      row.timer_remaining_time = tournament.timerState.remainingTime ?? null;
      row.timer_last_update = tournament.timerState.lastUpdateTime.toISOString();
    }

    return row;
  }

  findByStatus(status: Tournament['status']): Tournament[] {
    const rows = this.findWhere({ status: status });
    return rows.map(row => this.toDomain(row as TournamentRow));
  }

  findActive(): Tournament[] {
    const rows = this.query<TournamentRow>(
      'SELECT * FROM tournaments WHERE status = ? AND timer_is_running = 1',
      ['active']
    );
    return rows.map(row => this.toDomain(row));
  }
}
