/**
 * Blind Schedule Repository
 * Database operations for blind_schedules table
 */

import { getConnection } from '../connection';
import { BaseRepository } from './BaseRepository';
import type { BlindSchedule, BlindLevel } from '@shared/types/timer';

interface BlindScheduleRow {
  blind_schedule_id: string;
  name: string;
  description: string | null;
  starting_stack: number;
  break_interval: number;
  break_duration: number;
  isDefault: number;
  created_at: string;
  updated_at: string;
  created_by: string | null;
}

interface BlindScheduleWithLevels extends BlindSchedule {
  description?: string;
  breakInterval: number;
  breakDuration: number;
  isDefault: boolean;
  createdBy?: string;
  levels: BlindLevel[];
}

export class BlindScheduleRepository extends BaseRepository<BlindScheduleRow> {
  constructor() {
    super('blind_schedules', 'blind_schedule_id');
  }

  /**
   * Find blind schedule by ID with levels
   */
  findByIdWithLevels(id: string): BlindScheduleWithLevels | null {
    const schedule = this.findById(id);
    if (!schedule) return null;

    const levels = this.query<BlindLevel>(
      `SELECT * FROM blind_levels WHERE blindScheduleId = ? ORDER BY orderIndex`,
      [id]
    );

    return this.mapToScheduleWithLevels(schedule, levels);
  }

  /**
   * Find all schedules with metadata (without levels)
   */
  findAllList(includeDefaults = true): Array<{
    id: string;
    name: string;
    description: string | null;
    startingStack: number;
    levelCount: number;
    totalDurationMinutes: number;
    isDefault: boolean;
  }> {
    const db = getConnection();
    const result = db.prepare(`
      SELECT
        bs.blind_schedule_id as id,
        bs.name,
        bs.description,
        bs.starting_stack as startingStack,
        bs.isDefault as isDefault,
        COUNT(bl.blind_level_id) as levelCount,
        SUM(CASE WHEN bl.is_break = 0 THEN bl.duration ELSE 0 END) as totalDurationMinutes
      FROM blind_schedules bs
      LEFT JOIN blind_levels bl ON bs.blind_schedule_id = bl.blind_schedule_id
      ${includeDefaults ? '' : 'WHERE bs.isDefault = 0'}
      GROUP BY bs.blind_schedule_id
      ORDER BY bs.isDefault DESC, bs.name
    `).all() as Array<{
      id: string;
      name: string;
      description: string | null;
      startingStack: number;
      levelCount: number;
      totalDurationMinutes: number;
      isDefault: number;
    }>;

    // Convert isDefault from number to boolean
    return result.map(item => ({
      ...item,
      isDefault: item.isDefault === 1,
    }));
  }

  /**
   * Find schedules by user (createdBy)
   */
  findByUser(userId: string): BlindScheduleWithLevels[] {
    const schedules = this.query<BlindScheduleRow>(
      `SELECT * FROM blind_schedules WHERE created_by = ? ORDER BY name`,
      [userId]
    );
    return schedules.map((s) =>
      this.mapToScheduleWithLevels(
        s,
        this.query<BlindLevel>(
          `SELECT * FROM blind_levels WHERE blind_schedule_id = ? ORDER BY level`,
          [s.blind_schedule_id]
        )
      )
    );
  }

  /**
   * Find default schedules (isDefault = 1)
   */
  findDefaultSchedules(): BlindScheduleWithLevels[] {
    const schedules = this.query<BlindScheduleRow>(
      `SELECT * FROM blind_schedules WHERE isDefault = 1 ORDER BY name`
    );
    return schedules.map((s) =>
      this.mapToScheduleWithLevels(
        s,
        this.query<BlindLevel>(
          `SELECT * FROM blind_levels WHERE blind_schedule_id = ? ORDER BY level`,
          [s.blind_schedule_id]
        )
      )
    );
  }

  /**
   * Find schedules including or excluding defaults
   * Renamed from findAll to avoid conflict with BaseRepository.findAll
   */
  findAllWithLevels(includeDefaults = true): BlindScheduleWithLevels[] {
    let sql = `SELECT * FROM blind_schedules`;
    if (!includeDefaults) {
      sql += ` WHERE isDefault = 0`;
    }
    sql += ` ORDER BY isDefault DESC, name`;

    const schedules = this.query<BlindScheduleRow>(sql);
    return schedules.map((s) =>
      this.mapToScheduleWithLevels(
        s,
        this.query<BlindLevel>(
          `SELECT * FROM blind_levels WHERE blind_schedule_id = ? ORDER BY level`,
          [s.blind_schedule_id]
        )
      )
    );
  }

  /**
   * Check if schedule is default (protected from deletion)
   */
  isDefaultSchedule(id: string): boolean {
    const row = this.queryOne<{ isDefault: number }>(
      `SELECT isDefault FROM blind_schedules WHERE blind_schedule_id = ?`,
      [id]
    );
    return row?.isDefault === 1;
  }

  /**
   * Check if schedule is in use by any tournament
   */
  isInUse(id: string): boolean {
    const result = this.queryOne<{ count: number }>(
      `SELECT COUNT(*) as count FROM tournaments WHERE blind_schedule_id = ?`,
      [id]
    );
    return (result?.count || 0) > 0;
  }

  /**
   * Count levels in a schedule
   */
  countLevels(id: string): number {
    const result = this.queryOne<{ count: number }>(
      `SELECT COUNT(*) as count FROM blind_levels WHERE blind_schedule_id = ?`,
      [id]
    );
    return result?.count || 0;
  }

  /**
   * Get total duration (excluding breaks) for a schedule
   */
  getTotalDuration(id: string): number {
    const result = this.queryOne<{ total: number }>(
      `SELECT SUM(CASE WHEN is_break = 0 THEN duration ELSE 0 END) as total FROM blind_levels WHERE blind_schedule_id = ?`,
      [id]
    );
    return result?.total || 0;
  }

  /**
   * Insert schedule with levels in a transaction
   */
  insertWithLevels(
    schedule: Partial<BlindScheduleWithLevels> & { id?: string },
    levels: BlindLevel[]
  ): string {
    const db = getConnection();
    const now = new Date().toISOString();

    // Insert schedule - use id from parameter or blindScheduleId if available
    const scheduleId = schedule.id || schedule.blindScheduleId || crypto.randomUUID();
    db.prepare(`
      INSERT INTO blind_schedules (blind_schedule_id, name, description, starting_stack, break_interval, break_duration, isDefault, created_at, updated_at, created_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(
      scheduleId,
      schedule.name,
      schedule.description || null,
      schedule.startingStack,
      schedule.breakInterval,
      schedule.breakDuration,
      schedule.isDefault ? 1 : 0,
      now,
      now,
      schedule.createdBy || null
    );

    // Insert levels
    const insertLevel = db.prepare(`
      INSERT INTO blind_levels (blind_level_id, blind_schedule_id, level, small_blind, big_blind, ante, duration, is_break)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    `);

    for (const level of levels) {
      insertLevel.run(
        level.blindLevelId || crypto.randomUUID(),
        scheduleId,
        level.level,
        level.smallBlind,
        level.bigBlind,
        level.ante || null,
        level.duration,
        level.isBreak ? 1 : 0
      );
    }

    return scheduleId;
  }

  /**
   * Update schedule (not levels)
   */
  updateSchedule(id: string, updates: Partial<Omit<BlindScheduleWithLevels, 'levels' | 'id'>>): void {
    const allowedKeys = ['name', 'description', 'startingStack', 'breakInterval', 'breakDuration'];
    const columnMap: Record<string, string> = {
      name: 'name',
      description: 'description',
      startingStack: 'starting_stack',
      breakInterval: 'break_interval',
      breakDuration: 'break_duration',
    };
    const setClause: string[] = [];
    const values: unknown[] = [];

    for (const key of allowedKeys) {
      if (key in updates) {
        setClause.push(`${columnMap[key]} = ?`);
        values.push((updates as Record<string, unknown>)[key]);
      }
    }

    if (setClause.length === 0) return;

    setClause.push('updated_at = ?');
    values.push(new Date().toISOString());
    values.push(id);

    this.execute(
      `UPDATE blind_schedules SET ${setClause.join(', ')} WHERE blind_schedule_id = ?`,
      values
    );
  }

  /**
   * Delete schedule (cascades to levels)
   */
  deleteSchedule(id: string): void {
    // Transaction handled by database ON DELETE CASCADE
    this.delete(id);
  }

  /**
   * Duplicate a schedule (for editing default schedules)
   */
  duplicate(id: string, newName?: string): string | null {
    const schedule = this.findByIdWithLevels(id);
    if (!schedule) return null;

    const newId = crypto.randomUUID();
    const now = new Date().toISOString();

    const db = getConnection();

    // Copy schedule
    db.prepare(`
      INSERT INTO blind_schedules (blind_schedule_id, name, description, starting_stack, break_interval, break_duration, isDefault, created_at, updated_at, created_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(
      newId,
      newName || `${schedule.name} (Copy)`,
      schedule.description,
      schedule.startingStack,
      schedule.breakInterval,
      schedule.breakDuration,
      0, // Not default
      now,
      now,
      schedule.createdBy || null
    );

    // Copy levels
    const insertLevel = db.prepare(`
      INSERT INTO blind_levels (blind_level_id, blind_schedule_id, level, small_blind, big_blind, ante, duration, is_break)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    `);

    for (const level of schedule.levels) {
      insertLevel.run(
        crypto.randomUUID(),
        newId,
        level.level,
        level.smallBlind,
        level.bigBlind,
        level.ante || null,
        level.duration,
        level.isBreak ? 1 : 0
      );
    }

    return newId;
  }

  /**
   * Map database row to domain model
   */
  private mapToScheduleWithLevels(row: BlindScheduleRow, levels: BlindLevel[]): any {
    // Calculate breakIntervals from levels - level numbers where isBreak is true
    const breakIntervals = levels
      .filter(l => l.isBreak)
      .map(l => l.level);

    return {
      blindScheduleId: row.blind_schedule_id,
      name: row.name,
      description: row.description || undefined,
      startingStack: row.starting_stack,
      breakInterval: row.break_interval,
      breakDuration: row.break_duration,
      breakIntervals,
      isDefault: row.isDefault === 1,
      levels,
      createdAt: new Date(row.created_at),
      updatedAt: new Date(row.updated_at),
      createdBy: row.created_by || undefined,
    };
  }
}
