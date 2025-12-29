/**
 * Mobile database migrations
 * Simple version-based migrations for offline database
 */

import { getConnection } from './connection';
import type * as SQLite from 'expo-sqlite';

export interface Migration {
  version: number;
  name: string;
  up: (db: SQLite.SQLiteDatabase) => Promise<void>;
}

export const migrations: Migration[] = [
  {
    version: 1,
    name: 'initial_schema',
    up: async (db) => {
      // Handled by initializeSchema() in connection.ts
      console.log('Migration 1: Initial schema applied');
    },
  },
  {
    version: 2,
    name: 'add_sync_queue_indexes',
    up: async (db) => {
      await db.execAsync(`
        CREATE INDEX IF NOT EXISTS idx_sync_queue_entity ON sync_queue(entity_type, entity_id)
      `);
    },
  },
];

/**
 * Get current migration version
 */
export async function getCurrentVersion(): Promise<number> {
  const db = await getConnection();

  try {
    const result = await db.getFirstAsync<{ version: number }>(
      'SELECT version FROM _migrations ORDER BY version DESC LIMIT 1'
    );
    return result?.version || 0;
  } catch {
    // Migrations table doesn't exist yet
    return 0;
  }
}

/**
 * Create migrations tracking table
 */
export async function createMigrationsTable(): Promise<void> {
  const db = await getConnection();

  await db.execAsync(`
    CREATE TABLE IF NOT EXISTS _migrations (
      version INTEGER PRIMARY KEY,
      name TEXT NOT NULL,
      applied_at TEXT NOT NULL DEFAULT (datetime('now'))
    )
  `);
}

/**
 * Run pending migrations
 */
export async function runMigrations(): Promise<void> {
  const db = await getConnection();
  const currentVersion = await getCurrentVersion();

  await createMigrationsTable();

  for (const migration of migrations) {
    if (migration.version > currentVersion) {
      console.log(`Running migration ${migration.version}: ${migration.name}`);

      await db.execAsync('BEGIN TRANSACTION');

      try {
        await migration.up(db);
        await db.runAsync(
          'INSERT INTO _migrations (version, name) VALUES (?, ?)',
          [migration.version, migration.name]
        );
        await db.execAsync('COMMIT');
      } catch (error) {
        await db.execAsync('ROLLBACK');
        throw error;
      }

      console.log(`Migration ${migration.version} complete`);
    }
  }
}
