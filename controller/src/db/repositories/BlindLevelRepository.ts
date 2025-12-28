/**
 * Blind Level Repository
 * Database operations for blind_levels table
 */

import { getConnection } from '../connection';
import { BaseRepository } from './BaseRepository';
import type { BlindLevel } from '@shared/types/timer';

interface BlindLevelRow {
  id: string;
  blindScheduleId: string;
  levelNumber: number;
  smallBlind: number;
  bigBlind: number;
  ante: number | null;
  durationMinutes: number;
  isBreak: number;
  orderIndex: number;
}

export class BlindLevelRepository extends BaseRepository<BlindLevelRow> {
  constructor() {
    super('blind_levels', 'id');
  }

  /**
   * Find all levels for a schedule, ordered by level number
   */
  findByScheduleId(scheduleId: string): BlindLevel[] {
    const rows = this.query<BlindLevelRow>(
      `SELECT * FROM blind_levels WHERE blindScheduleId = ? ORDER BY orderIndex`,
      [scheduleId]
    );
    return rows.map(this.mapToBlindLevel);
  }

  /**
   * Find specific level by schedule and level number
   */
  findByScheduleAndLevel(scheduleId: string, levelNumber: number): BlindLevel | null {
    const row = this.queryOne<BlindLevelRow>(
      `SELECT * FROM blind_levels WHERE blindScheduleId = ? AND levelNumber = ?`,
      [scheduleId, levelNumber]
    );
    return row ? this.mapToBlindLevel(row) : null;
  }

  /**
   * Find next level after current
   */
  findNextLevel(scheduleId: string, currentLevel: number): BlindLevel | null {
    const row = this.queryOne<BlindLevelRow>(
      `SELECT * FROM blind_levels WHERE blindScheduleId = ? AND levelNumber > ? ORDER BY levelNumber ASC LIMIT 1`,
      [scheduleId, currentLevel]
    );
    return row ? this.mapToBlindLevel(row) : null;
  }

  /**
   * Find previous level before current
   */
  findPreviousLevel(scheduleId: string, currentLevel: number): BlindLevel | null {
    const row = this.queryOne<BlindLevelRow>(
      `SELECT * FROM blind_levels WHERE blindScheduleId = ? AND levelNumber < ? ORDER BY levelNumber DESC LIMIT 1`,
      [scheduleId, currentLevel]
    );
    return row ? this.mapToBlindLevel(row) : null;
  }

  /**
   * Get level at specific position (0-indexed)
   */
  getLevelAtPosition(scheduleId: string, position: number): BlindLevel | null {
    const row = this.queryOne<BlindLevelRow>(
      `SELECT * FROM blind_levels WHERE blindScheduleId = ? ORDER BY orderIndex LIMIT 1 OFFSET ?`,
      [scheduleId, position]
    );
    return row ? this.mapToBlindLevel(row) : null;
  }

  /**
   * Get levels paginated (for mobile "load more")
   */
  getLevelsPaginated(scheduleId: string, offset: number, limit: number): BlindLevel[] {
    const rows = this.query<BlindLevelRow>(
      `SELECT * FROM blind_levels WHERE blindScheduleId = ? ORDER BY orderIndex LIMIT ? OFFSET ?`,
      [scheduleId, limit, offset]
    );
    return rows.map(this.mapToBlindLevel);
  }

  /**
   * Count levels in schedule
   */
  countByScheduleId(scheduleId: string): number {
    const result = this.queryOne<{ count: number }>(
      `SELECT COUNT(*) as count FROM blind_levels WHERE blindScheduleId = ?`,
      [scheduleId]
    );
    return result?.count || 0;
  }

  /**
   * Count non-break levels in schedule
   */
  countPlayLevels(scheduleId: string): number {
    const result = this.queryOne<{ count: number }>(
      `SELECT COUNT(*) as count FROM blind_levels WHERE blindScheduleId = ? AND isBreak = 0`,
      [scheduleId]
    );
    return result?.count || 0;
  }

  /**
   * Insert single level
   */
  insertLevel(level: Partial<BlindLevel>, scheduleId: string): string {
    const levelId = level.blindLevelId || crypto.randomUUID();
    const db = getConnection();

    db.prepare(`
      INSERT INTO blind_levels (id, blindScheduleId, levelNumber, smallBlind, bigBlind, ante, durationMinutes, isBreak, orderIndex)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(
      levelId,
      scheduleId,
      level.level,
      level.smallBlind,
      level.bigBlind,
      level.ante || null,
      level.duration,
      level.isBreak ? 1 : 0,
      level.level
    );

    return levelId;
  }

  /**
   * Insert multiple levels in batch
   */
  insertLevels(levels: Partial<BlindLevel>[], scheduleId: string): string[] {
    const db = getConnection();
    const insert = db.prepare(`
      INSERT INTO blind_levels (id, blindScheduleId, levelNumber, smallBlind, bigBlind, ante, durationMinutes, isBreak, orderIndex)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        level.isBreak ? 1 : 0,
        level.level
      );
      ids.push(levelId);
    }

    return ids;
  }

  /**
   * Update level
   */
  updateLevel(levelId: string, updates: Partial<Omit<BlindLevel, 'blindLevelId'>>): void {
    const allowedKeys = ['levelNumber', 'smallBlind', 'bigBlind', 'ante', 'duration', 'isBreak'];
    const setClause: string[] = [];
    const values: unknown[] = [];

    for (const key of allowedKeys) {
      if (key in updates) {
        setClause.push(`${key === 'duration' ? 'durationMinutes' : key === 'isBreak' ? 'isBreak' : key} = ?`);
        values.push((updates as Record<string, unknown>)[key]);
      }
    }

    if (setClause.length === 0) return;

    values.push(levelId);

    this.execute(
      `UPDATE blind_levels SET ${setClause.join(', ')} WHERE id = ?`,
      values
    );
  }

  /**
   * Delete level
   */
  deleteLevel(levelId: string): void {
    this.delete(levelId);
  }

  /**
   * Delete all levels for a schedule
   */
  deleteByScheduleId(scheduleId: string): void {
    const db = getConnection();
    db.prepare(`DELETE FROM blind_levels WHERE blindScheduleId = ?`).run(scheduleId);
  }

  /**
   * Reorder levels (after insert/delete/update)
   */
  reorderLevels(scheduleId: string): void {
    const db = getConnection();
    db.prepare(`
      UPDATE blind_levels SET orderIndex = (
        SELECT COUNT(*) FROM blind_levels bl2
        WHERE bl2.blindScheduleId = blind_levels.blindScheduleId
        AND bl2.levelNumber <= blind_levels.levelNumber
      )
      WHERE blindScheduleId = ?
    `).run(scheduleId);
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
      blindLevelId: row.id,
      level: row.levelNumber,
      smallBlind: row.smallBlind,
      bigBlind: row.bigBlind,
      ante: row.ante || undefined,
      duration: row.durationMinutes,
      isBreak: row.isBreak === 1,
    };
  }
}
