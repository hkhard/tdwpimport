/**
 * Timer Event repository
 */

import { BaseRepository } from './BaseRepository';
import type { TimerEvent } from '@shared/types/timer';

export interface TimerEventRow {
  event_id: string;
  tournament_id: string;
  timestamp: string;
  event_type: string;
  previous_level: number | null;
  previous_elapsed_time: number | null;
  previous_is_running: number | null;
  previous_is_paused: number | null;
  new_level: number;
  new_elapsed_time: number;
  new_is_running: number;
  new_is_paused: number;
  device_id: string | null;
  metadata: string | null;
  created_at: string;
}

export class TimerEventRepository extends BaseRepository<TimerEventRow> {
  constructor() {
    super('timer_events', 'event_id');
  }

  toDomain(row: TimerEventRow): TimerEvent {
    return {
      eventId: row.event_id,
      tournamentId: row.tournament_id,
      timestamp: new Date(row.timestamp),
      eventType: row.event_type as TimerEvent['eventType'],
      previousState: row.previous_level !== null ? {
        level: row.previous_level,
        elapsedTime: row.previous_elapsed_time!,
        isRunning: Boolean(row.previous_is_running),
        isPaused: Boolean(row.previous_is_paused),
      } : undefined,
      newState: {
        level: row.new_level,
        elapsedTime: row.new_elapsed_time,
        isRunning: Boolean(row.new_is_running),
        isPaused: Boolean(row.new_is_paused),
      },
      deviceId: row.device_id || undefined,
      metadata: row.metadata ? JSON.parse(row.metadata) : undefined,
    };
  }

  findByTournament(tournamentId: string, limit?: number): TimerEvent[] {
    const sql = limit
      ? `SELECT * FROM timer_events WHERE tournament_id = ? ORDER BY timestamp DESC LIMIT ?`
      : `SELECT * FROM timer_events WHERE tournament_id = ? ORDER BY timestamp DESC`;

    const params = limit ? [tournamentId, limit] : [tournamentId];
    const rows = this.query<TimerEventRow>(sql, params);
    return rows.map(row => this.toDomain(row));
  }

  findRecentByTournament(tournamentId: string, limit: number): TimerEvent[] {
    const rows = this.query<TimerEventRow>(
      'SELECT * FROM timer_events WHERE tournament_id = ? ORDER BY timestamp DESC LIMIT ?',
      [tournamentId, limit]
    );
    return rows.map(row => this.toDomain(row));
  }

  findLatest(tournamentId: string): TimerEvent | undefined {
    const row = this.queryOne<TimerEventRow>(
      'SELECT * FROM timer_events WHERE tournament_id = ? ORDER BY timestamp DESC LIMIT 1',
      [tournamentId]
    );
    return row ? this.toDomain(row) : undefined;
  }

  countByType(tournamentId: string, eventType: string): number {
    const row = this.queryOne<{ count: number }>(
      'SELECT COUNT(*) as count FROM timer_events WHERE tournament_id = ? AND event_type = ?',
      [tournamentId, eventType]
    );
    return row?.count || 0;
  }
}
