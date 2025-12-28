/**
 * Mobile database schema definition
 * Matches controller schema for compatibility
 */

export const schema = {
  // Tournaments table
  tournaments: `
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
  `,

  // Players table
  players: `
    CREATE TABLE IF NOT EXISTS players (
      player_id TEXT PRIMARY KEY,
      name TEXT NOT NULL,
      email TEXT UNIQUE,
      phone TEXT,
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )
  `,

  // Tournament players (participation)
  tournament_players: `
    CREATE TABLE IF NOT EXISTS tournament_players (
      tournament_player_id TEXT PRIMARY KEY,
      tournament_id TEXT NOT NULL REFERENCES tournaments(tournament_id) ON DELETE CASCADE,
      player_id TEXT NOT NULL REFERENCES players(player_id) ON DELETE CASCADE,
      registration_time TEXT NOT NULL DEFAULT (datetime('now')),
      starting_stack INTEGER NOT NULL,
      finish_position INTEGER UNIQUE,
      winnings REAL,
      bustout_time TEXT,
      eliminations INTEGER DEFAULT 0,
      table_name TEXT,
      seat_number INTEGER,
      updated_at TEXT NOT NULL DEFAULT (datetime('now')),
      UNIQUE(tournament_id, player_id)
    )
  `,

  // Sync queue for offline changes
  sync_queue: `
    CREATE TABLE IF NOT EXISTS sync_queue (
      change_id TEXT PRIMARY KEY,
      entity_type TEXT NOT NULL CHECK (entity_type IN ('tournament', 'player', 'tournament_player', 'timer_event')),
      operation TEXT NOT NULL CHECK (operation IN ('create', 'update', 'delete')),
      entity_id TEXT NOT NULL,
      data TEXT NOT NULL,
      local_timestamp INTEGER NOT NULL,
      retry_count INTEGER DEFAULT 0,
      last_attempt INTEGER,
      next_retry INTEGER,
      status TEXT NOT NULL DEFAULT 'pending'
    )
  `,

  // Offline storage for synced data cache
  synced_data: `
    CREATE TABLE IF NOT EXISTS synced_data (
      key TEXT PRIMARY KEY,
      data TEXT NOT NULL,
      timestamp INTEGER NOT NULL
    )
  `,
};

export const indexes = [
  'CREATE INDEX IF NOT EXISTS idx_tournaments_status ON tournaments(status)',
  'CREATE INDEX IF NOT EXISTS idx_tournament_players_tournament ON tournament_players(tournament_id)',
  'CREATE INDEX IF NOT EXISTS idx_tournament_players_player ON tournament_players(player_id)',
  'CREATE INDEX IF NOT EXISTS idx_sync_queue_status ON sync_queue(status)',
  'CREATE INDEX IF NOT EXISTS idx_sync_queue_next_retry ON sync_queue(next_retry)',
];
