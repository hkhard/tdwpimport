/**
 * Blind Level Repository
 * Database operations for blind_levels table
 */

import { getConnection } from '../connection';
import type { BlindLevel } from '@shared/types/timer';

interface BlindLevelRow {
  blind_level_id: string;
  blind_schedule_id: string;
  level: number;
  small_blind: number;
  big_blind: number;
  ante: number | null;
  duration: number;
  is_break: number;
}

export class BlindLevelRepository {
  /**
   * Find all levels for a schedule, ordered by level number
   */
  findByScheduleId(scheduleId: string): BlindLevel[] {
    const db = getConnection();
    const rows = db.prepare(
      `SELECT * FROM blind_levels WHERE blind_schedule_id = ? ORDER BY level`
    ).all(scheduleId) as BlindLevelRow[];
    return rows.map(this.mapToBlindLevel);
  }

  /**
   * Find specific level by schedule and level number
   */
  findByScheduleAndLevel(scheduleId: string, levelNumber: number): BlindLevel | null {
    const db = getConnection();
    const row = db.prepare(
      `SELECT * FROM blind_levels WHERE blind_schedule_id = ? AND level = ?`
    ).get(scheduleId, levelNumber) as BlindLevelRow | undefined;
    return row ? this.mapToBlindLevel(row) : null;
  }

  /**
   * Find next level after current
   */
  findNextLevel(scheduleId: string, currentLevel: number): BlindLevel | null {
    const db = getConnection();
    const row = db.prepare(
      `SELECT * FROM blind_levels WHERE blind_schedule_id = ? AND level > ? ORDER BY level ASC LIMIT 1`
    ).get(scheduleId, currentLevel) as BlindLevelRow | undefined;
    return row ? this.mapToBlindLevel(row) : null;
  }

  /**
   * Find previous level before current
   */
  findPreviousLevel(scheduleId: string, currentLevel: number): BlindLevel | null {
    const db = getConnection();
    const row = db.prepare(
      `SELECT * FROM blind_levels WHERE blind_schedule_id = ? AND level < ? ORDER BY level DESC LIMIT 1`
    ).get(scheduleId, currentLevel) as BlindLevelRow | undefined;
    return row ? this.mapToBlindLevel(row) : null;
  }

  /**
   * Get level at specific position (0-indexed)
   */
  getLevelAtPosition(scheduleId: string, position: number): BlindLevel | null {
    const db = getConnection();
    const row = db.prepare(
      `SELECT * FROM blind_levels WHERE blind_schedule_id = ? ORDER BY level LIMIT 1 OFFSET ?`
    ).get(scheduleId, position) as BlindLevelRow | undefined;
    return row ? this.mapToBlindLevel(row) : null;
  }

  /**
   * Get levels paginated (for mobile "load more")
   */
  getLevelsPaginated(scheduleId: string, offset: number, limit: number): BlindLevel[] {
    const db = getConnection();
    const stmt = db.prepare(
      `SELECT * FROM blind_levels WHERE blind_schedule_id = ? ORDER BY level LIMIT ? OFFSET ?`
    );
    const rows = stmt.all(scheduleId, limit, offset) as BlindLevelRow[];
    return rows.map(row => this.mapToBlindLevel(row));
  }

  /**
   * Count levels in schedule
   */
  countByScheduleId(scheduleId: string): number {
    const db = getConnection();
    const result = db.prepare(
      `SELECT COUNT(*) as count FROM blind_levels WHERE blind_schedule_id = ?`
    ).get(scheduleId) as { count: number };
    return result?.count || 0;
  }

  /**
   * Count non-break levels in schedule
   */
  countPlayLevels(scheduleId: string): number {
    const db = getConnection();
    const result = db.prepare(
      `SELECT COUNT(*) as count FROM blind_levels WHERE blind_schedule_id = ? AND is_break = 0`
    ).get(scheduleId) as { count: number };
    return result?.count || 0;
  }

  /**
   * Insert single level
   */
  insertLevel(level: Partial<BlindLevel>, scheduleId: string): string {
    const levelId = level.blindLevelId || crypto.randomUUID();
    const db = getConnection();

    db.prepare(`
      INSERT INTO blind_levels (blind_level_id, blind_schedule_id, level, small_blind, big_blind, ante, duration, is_break)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    `).run(
      levelId,
      scheduleId,
      level.level,
      level.smallBlind,
      level.bigBlind,
      level.ante || null,
      level.duration,
      level.isBreak ? 1 : 0
    );

    return levelId;
  }

  /**
   * Insert multiple levels in batch
   */
  insertLevels(levels: Partial<BlindLevel>[], scheduleId: string): string[] {
    const db = getConnection();
    const insert = db.prepare(`
      INSERT INTO blind_levels (blind_level_id, blind_schedule_id, level, small_blind, big_blind, ante, duration, is_break)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    `);

    const ids: string[] = [];
    for (const level of levels) {
      const levelId = level.blindLevelId || crypto.randomUUID();
      insert.run(
        levelId,
        scheduleId,
        level.level,
        level.smallBlind,
        level.bigBlind,
        level.ante || null,
        level.duration,
        level.isBreak ? 1 : 0
      );
      ids.push(levelId);
    }

    return ids;
  }

  /**
   * Update level
   */
  updateLevel(levelId: string, updates: Partial<Omit<BlindLevel, 'blindLevelId'>>): void {
    const db = getConnection();
    const allowedKeys: (keyof Omit<BlindLevel, 'blindLevelId'>)[] = ['level', 'smallBlind', 'bigBlind', 'ante', 'duration', 'isBreak'];
    const columnMap: Record<string, string> = {
      level: 'level',
      smallBlind: 'small_blind',
      bigBlind: 'big_blind',
      ante: 'ante',
      duration: 'duration',
      isBreak: 'is_break',
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

    values.push(levelId);

    db.prepare(
      `UPDATE blind_levels SET ${setClause.join(', ')} WHERE blind_level_id = ?`
    ).run(...values);
  }

  /**
   * Delete level
   */
  deleteLevel(levelId: string): void {
    const db = getConnection();
    db.prepare(`DELETE FROM blind_levels WHERE blind_level_id = ?`).run(levelId);
  }

  /**
   * Delete all levels for a schedule
   */
  deleteByScheduleId(scheduleId: string): void {
    const db = getConnection();
    db.prepare(`DELETE FROM blind_levels WHERE blind_schedule_id = ?`).run(scheduleId);
  }

  /**
   * Validate level constraints
   */
  validateLevel(level: Partial<BlindLevel>): { valid: boolean; errors: string[] } {
    const errors: string[] = [];

    if (level.bigBlind !== undefined && level.smallBlind !== undefined) {
      if (level.bigBlind < level.smallBlind) {
        errors.push('Big blind must be greater than or equal to small blind');
      }
    }

    if (level.isBreak && (level.smallBlind !== 0 || level.bigBlind !== 0)) {
      errors.push('Break levels must have zero blinds');
    }

    if (level.duration !== undefined && level.duration < 1) {
      errors.push('Duration must be at least 1 minute');
    }

    return {
      valid: errors.length === 0,
      errors,
    };
  }

  /**
   * Map database row to domain model
   */
  private mapToBlindLevel(row: BlindLevelRow): BlindLevel {
    return {
      blindLevelId: row.blind_level_id,
      level: row.level,
      smallBlind: row.small_blind,
      bigBlind: row.big_blind,
      ante: row.ante || undefined,
      duration: row.duration,
      isBreak: row.is_break === 1,
    };
  }
}
