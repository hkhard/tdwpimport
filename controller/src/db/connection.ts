/**
 * SQLite database connection for controller
 * Uses better-sqlite3 with WAL mode for replication support
 */

import Database from 'better-sqlite3';
import fs from 'fs';
import path from 'path';

let db: Database.Database | null = null;

/**
 * Get or create database connection
 */
export function getConnection(dbPath?: string): Database.Database {
  if (db) {
    return db;
  }

  const databasePath = dbPath || process.env.DATABASE_PATH || path.join(__dirname, '../../../../data/tournaments.db');

  // Ensure data directory exists
  const dataDir = path.dirname(databasePath);
  if (!fs.existsSync(dataDir)) {
    fs.mkdirSync(dataDir, { recursive: true });
  }

  db = new Database(databasePath, {
    fileMustExist: false,
    readonly: false,
  });

  // Enable WAL mode for replication
  db.pragma('journal_mode = WAL');

  // Set busy timeout for concurrent access
  db.pragma('busy_timeout = 5000');

  // Enable foreign keys
  db.pragma('foreign_keys = ON');

  return db;
}

/**
 * Close database connection
 */
export function closeConnection(): void {
  if (db) {
    db.close();
    db = null;
  }
}

/**
 * Check if database is connected
 */
export function isConnected(): boolean {
  return db !== null && db.open;
}

export interface Migration {
  id: string;
  name: string;
  up: string;
  down?: string;
}

/**
 * Load migration files from migrations directory
 */
function loadMigrations(): Migration[] {
  // Migrations are always in the migrations directory relative to the connection file
  const migrationsDir = path.join(__dirname, 'migrations');

  const files = fs.readdirSync(migrationsDir)
    .filter(f => f.endsWith('.sql') && f.match(/^\d+_/))
    .sort();

  return files.map(file => {
    const content = fs.readFileSync(path.join(migrationsDir, file), 'utf-8');
    const [up, down] = content.split('-- DOWN --\n');

    return {
      id: file.split('_')[0],
      name: file.replace(/\.sql$/, ''),
      up: up.trim(),
      down: down?.trim(),
    };
  });
}

/**
 * Get applied migrations from database
 */
function getAppliedMigrations(): string[] {
  const db = getConnection();
  const rows = db.prepare('SELECT name FROM _migrations ORDER BY id').all() as { name: string }[];
  return rows.map(r => r.name);
}

/**
 * Run migrations
 */
export function runMigrations(): void {
  const connection = getConnection();

  // Create migrations table if not exists
  connection.exec(`
    CREATE TABLE IF NOT EXISTS _migrations (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL UNIQUE,
      applied_at TEXT NOT NULL DEFAULT (datetime('now'))
    )
  `);

  // Load and run pending migrations
  const migrations = loadMigrations();
  const applied = getAppliedMigrations();

  for (const migration of migrations) {
    if (!applied.includes(migration.name)) {
      console.log(`[Migration] Running: ${migration.name}`);

      // Run migration in transaction
      connection.transaction(() => {
        connection.exec(migration.up);
        connection.prepare('INSERT INTO _migrations (name) VALUES (?)').run(migration.name);
      })();

      console.log(`[Migration] Complete: ${migration.name}`);
    }
  }
}
