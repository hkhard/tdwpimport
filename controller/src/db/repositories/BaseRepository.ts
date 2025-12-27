/**
 * Base repository class for database operations
 * Provides common CRUD functionality for all repositories
 */

import { getConnection } from '../connection';

export abstract class BaseRepository<T> {
  protected tableName: string;
  protected primaryKey: string;

  constructor(tableName: string, primaryKey: string) {
    this.tableName = tableName;
    this.primaryKey = primaryKey;
  }

  /**
   * Find entity by ID
   */
  findById(id: string): T | undefined {
    const db = getConnection();
    const row = db.prepare(
      `SELECT * FROM ${this.tableName} WHERE ${this.primaryKey} = ?`
    ).get(id);
    return row as T | undefined;
  }

  /**
   * Find all entities
   */
  findAll(): T[] {
    const db = getConnection();
    const rows = db.prepare(`SELECT * FROM ${this.tableName}`).all() as T[];
    return rows;
  }

  /**
   * Find entities with conditions
   */
  findWhere(conditions: Record<string, unknown>): T[] {
    const db = getConnection();
    const keys = Object.keys(conditions);
    const whereClause = keys.map(k => `${k} = ?`).join(' AND ');
    const values = Object.values(conditions);

    const rows = db.prepare(
      `SELECT * FROM ${this.tableName} WHERE ${whereClause}`
    ).all(...values) as T[];
    return rows;
  }

  /**
   * Insert new entity
   */
  insert(entity: Partial<T>): void {
    const db = getConnection();
    const keys = Object.keys(entity);
    const placeholders = keys.map(() => '?').join(', ');
    const columns = keys.join(', ');
    const values = Object.values(entity);

    db.prepare(
      `INSERT INTO ${this.tableName} (${columns}) VALUES (${placeholders})`
    ).run(...values);
  }

  /**
   * Create new entity (alias for insert)
   */
  create(entity: Partial<T>): void {
    this.insert(entity);
  }

  /**
   * Update entity
   */
  update(id: string, updates: Partial<T>): void {
    const db = getConnection();
    const keys = Object.keys(updates);
    const setClause = keys.map(k => `${k} = ?`).join(', ');
    const values = [...Object.values(updates), id];

    db.prepare(
      `UPDATE ${this.tableName} SET ${setClause} WHERE ${this.primaryKey} = ?`
    ).run(...values);
  }

  /**
   * Delete entity
   */
  delete(id: string): void {
    const db = getConnection();
    db.prepare(
      `DELETE FROM ${this.tableName} WHERE ${this.primaryKey} = ?`
    ).run(id);
  }

  /**
   * Count entities
   */
  count(): number {
    const db = getConnection();
    const row = db.prepare(`SELECT COUNT(*) as count FROM ${this.tableName}`).get() as { count: number };
    return row.count;
  }

  /**
   * Execute custom query
   */
  query<R = T>(sql: string, params: unknown[] = []): R[] {
    const db = getConnection();
    return db.prepare(sql).all(...params) as R[];
  }

  /**
   * Execute custom query (single row)
   */
  queryOne<R = T>(sql: string, params: unknown[] = []): R | undefined {
    const db = getConnection();
    return db.prepare(sql).get(...params) as R | undefined;
  }

  /**
   * Execute statement and return info
   */
  execute(sql: string, params: unknown[] = []): { changes: number; lastInsertRowid: number } {
    const db = getConnection();
    const result = db.prepare(sql).run(...params);
    return {
      changes: result.changes,
      lastInsertRowid: typeof result.lastInsertRowid === 'bigint'
        ? Number(result.lastInsertRowid)
        : result.lastInsertRowid,
    };
  }
}
