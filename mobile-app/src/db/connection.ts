/**
 * Expo SQLite database connection for mobile app
 * Uses async API as required by React Native
 */

import * as SQLite from 'expo-sqlite';
import { Platform } from 'react-native';

const DB_NAME = 'tournament_local.db';

let db: SQLite.SQLiteDatabase | null = null;

/**
 * Get or create database connection
 */
export async function getConnection(): Promise<SQLite.SQLiteDatabase> {
  if (db) {
    return db;
  }

  try {
    db = await SQLite.openDatabaseAsync(DB_NAME);

    // Enable foreign keys
    await db.execAsync('PRAGMA foreign_keys = ON');

    // Set WAL mode for better performance
    await db.execAsync('PRAGMA journal_mode = WAL');

    console.log('Database opened successfully');
    return db;
  } catch (error) {
    console.error('Error opening database:', error);
    throw error;
  }
}

/**
 * Close database connection
 */
export async function closeConnection(): Promise<void> {
  if (db) {
    await db.closeAsync();
    db = null;
  }
}

/**
 * Check if database is connected
 */
export function isConnected(): boolean {
  return db !== null;
}

/**
 * Initialize database schema
 */
export async function initializeSchema(): Promise<void> {
  const connection = await getConnection();

  // Create tournaments table
  await connection.execAsync(`
    CREATE TABLE IF NOT EXISTS tournaments (
      tournament_id TEXT PRIMARY KEY,
      name TEXT NOT NULL,
      description TEXT,
      start_time TEXT NOT NULL,
      end_time TEXT,
      status TEXT NOT NULL CHECK (status IN ('upcoming', 'active', 'completed', 'cancelled')),
      current_blind_level INTEGER NOT NULL DEFAULT 0,
      timer_is_running INTEGER NOT NULL DEFAULT 0,
      timer_is_paused INTEGER NOT NULL DEFAULT 0,
      timer_elapsed_time INTEGER NOT NULL DEFAULT 0,
      timer_last_update TEXT NOT NULL DEFAULT (datetime('now')),
      prize_pool REAL,
      blind_schedule_id TEXT,
      created_by TEXT,
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )
  `);

  // Create players table
  await connection.execAsync(`
    CREATE TABLE IF NOT EXISTS players (
      player_id TEXT PRIMARY KEY,
      name TEXT NOT NULL,
      email TEXT UNIQUE,
      phone TEXT,
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )
  `);

  // Create tournament_players table
  await connection.execAsync(`
    CREATE TABLE IF NOT EXISTS tournament_players (
      tournament_player_id TEXT PRIMARY KEY,
      tournament_id TEXT NOT NULL,
      player_id TEXT NOT NULL,
      registration_time TEXT NOT NULL DEFAULT (datetime('now')),
      starting_stack INTEGER NOT NULL,
      finish_position INTEGER UNIQUE,
      winnings REAL,
      bustout_time TEXT,
      eliminations INTEGER DEFAULT 0,
      table_name TEXT,
      seat_number INTEGER,
      updated_at TEXT NOT NULL DEFAULT (datetime('now')),
      FOREIGN KEY (tournament_id) REFERENCES tournaments(tournament_id) ON DELETE CASCADE,
      FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE,
      UNIQUE(tournament_id, player_id)
    )
  `);

  // Create sync_queue table for offline changes
  await connection.execAsync(`
    CREATE TABLE IF NOT EXISTS sync_queue (
      change_id TEXT PRIMARY KEY,
      entity_type TEXT NOT NULL,
      operation TEXT NOT NULL,
      entity_id TEXT NOT NULL,
      data TEXT NOT NULL,
      local_timestamp INTEGER NOT NULL,
      retry_count INTEGER DEFAULT 0,
      last_attempt INTEGER,
      next_retry INTEGER,
      status TEXT NOT NULL DEFAULT 'pending'
    )
  `);

  // Create indexes
  await connection.execAsync(`
    CREATE INDEX IF NOT EXISTS idx_tournaments_status ON tournaments(status);
    CREATE INDEX IF NOT EXISTS idx_tournament_players_tournament ON tournament_players(tournament_id);
    CREATE INDEX IF NOT EXISTS idx_tournament_players_player ON tournament_players(player_id);
    CREATE INDEX IF NOT EXISTS idx_sync_queue_status ON sync_queue(status);
    CREATE INDEX IF NOT EXISTS idx_sync_queue_next_retry ON sync_queue(next_retry)
  `);
}
