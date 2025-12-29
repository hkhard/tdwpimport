-- Initial schema for Tournament Director Platform
-- Creates all core tables for tournaments, players, timer events, and sync records

-- Users table for authentication
CREATE TABLE IF NOT EXISTS users (
  user_id TEXT PRIMARY KEY,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN ('admin', 'director', 'scorekeeper', 'viewer')),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  last_login TEXT
);

-- Tournaments table
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
  created_by TEXT NOT NULL REFERENCES users(user_id),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Blind schedules for tournament templates
CREATE TABLE IF NOT EXISTS blind_schedules (
  blind_schedule_id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  starting_stack INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Blind levels within schedules
CREATE TABLE IF NOT EXISTS blind_levels (
  blind_level_id TEXT PRIMARY KEY,
  blind_schedule_id TEXT NOT NULL REFERENCES blind_schedules(blind_schedule_id) ON DELETE CASCADE,
  level INTEGER NOT NULL,
  small_blind INTEGER NOT NULL,
  big_blind INTEGER NOT NULL,
  ante INTEGER,
  duration INTEGER NOT NULL, -- minutes
  is_break INTEGER NOT NULL DEFAULT 0,
  UNIQUE(blind_schedule_id, level)
);

-- Players master table
CREATE TABLE IF NOT EXISTS players (
  player_id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  email TEXT UNIQUE,
  phone TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Tournament players (participation)
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
);

-- Timer event log for audit trail
CREATE TABLE IF NOT EXISTS timer_events (
  event_id TEXT PRIMARY KEY,
  tournament_id TEXT NOT NULL REFERENCES tournaments(tournament_id) ON DELETE CASCADE,
  timestamp TEXT NOT NULL DEFAULT (datetime('now')),
  event_type TEXT NOT NULL CHECK (event_type IN (
    'tournament_start', 'tournament_end',
    'level_start', 'level_end',
    'break_start', 'break_end',
    'pause', 'resume', 'director_override'
  )),
  previous_level INTEGER,
  previous_elapsed_time INTEGER,
  previous_is_running INTEGER,
  previous_is_paused INTEGER,
  new_level INTEGER,
  new_elapsed_time INTEGER,
  new_is_running INTEGER,
  new_is_paused INTEGER,
  device_id TEXT,
  metadata TEXT, -- JSON
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Sync records for offline synchronization
CREATE TABLE IF NOT EXISTS sync_records (
  sync_id TEXT PRIMARY KEY,
  device_id TEXT NOT NULL,
  timestamp TEXT NOT NULL DEFAULT (datetime('now')),
  status TEXT NOT NULL CHECK (status IN ('pending', 'syncing', 'success', 'failed')),
  error_message TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Individual changes within a sync record
CREATE TABLE IF NOT EXISTS sync_changes (
  change_id TEXT PRIMARY KEY,
  sync_id TEXT NOT NULL REFERENCES sync_records(sync_id) ON DELETE CASCADE,
  entity_type TEXT NOT NULL CHECK (entity_type IN ('tournament', 'player', 'tournament_player', 'timer_event')),
  operation TEXT NOT NULL CHECK (operation IN ('create', 'update', 'delete')),
  entity_id TEXT NOT NULL,
  data TEXT NOT NULL, -- JSON
  local_timestamp INTEGER NOT NULL,
  server_timestamp INTEGER,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Conflicts detected during sync
CREATE TABLE IF NOT EXISTS sync_conflicts (
  conflict_id TEXT PRIMARY KEY,
  sync_id TEXT NOT NULL REFERENCES sync_records(sync_id) ON DELETE CASCADE,
  entity_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  local_version TEXT NOT NULL, -- JSON
  server_version TEXT NOT NULL, -- JSON
  resolved_version TEXT, -- JSON
  conflict_type TEXT NOT NULL CHECK (conflict_type IN ('concurrent_edit', 'delete_conflict', 'validation_error')),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  resolved_at TEXT
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_tournaments_status ON tournaments(status);
CREATE INDEX IF NOT EXISTS idx_tournaments_created_by ON tournaments(created_by);
CREATE INDEX IF NOT EXISTS idx_tournament_players_tournament ON tournament_players(tournament_id);
CREATE INDEX IF NOT EXISTS idx_tournament_players_player ON tournament_players(player_id);
CREATE INDEX IF NOT EXISTS idx_tournament_players_finish_position ON tournament_players(finish_position);
CREATE INDEX IF NOT EXISTS idx_timer_events_tournament ON timer_events(tournament_id);
CREATE INDEX IF NOT EXISTS idx_timer_events_timestamp ON timer_events(timestamp);
CREATE INDEX IF NOT EXISTS idx_sync_records_device ON sync_records(device_id);
CREATE INDEX IF NOT EXISTS idx_sync_records_status ON sync_records(status);
CREATE INDEX IF NOT EXISTS idx_sync_changes_sync ON sync_changes(sync_id);
