-- Fix blind level initialization
-- Change default from 0 to 1 and update existing tournaments

-- Step 1: Update existing tournaments with current_blind_level = 0 to set them to 1
UPDATE tournaments SET current_blind_level = 1 WHERE current_blind_level = 0;

-- Step 2: Recreate the tournaments table with the correct DEFAULT
-- SQLite doesn't support ALTER COLUMN directly, so we need to recreate

-- Create new table with correct schema
CREATE TABLE IF NOT EXISTS tournaments_new (
  tournament_id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  description TEXT,
  start_time TEXT NOT NULL,
  end_time TEXT,
  status TEXT NOT NULL CHECK (status IN ('upcoming', 'active', 'completed', 'cancelled')),
  current_blind_level INTEGER NOT NULL DEFAULT 1,
  timer_is_running INTEGER NOT NULL DEFAULT 0,
  timer_is_paused INTEGER NOT NULL DEFAULT 0,
  timer_elapsed_time INTEGER NOT NULL DEFAULT 0,
  timer_tenths INTEGER NOT NULL DEFAULT 0,
  timer_remaining_time INTEGER,
  timer_last_update TEXT NOT NULL DEFAULT (datetime('now')),
  prize_pool REAL,
  blind_schedule_id TEXT,
  controller_device_id TEXT,
  created_by TEXT NOT NULL REFERENCES users(user_id),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Copy data from old table to new table
INSERT INTO tournaments_new
SELECT * FROM tournaments;

-- Drop old table
DROP TABLE tournaments;

-- Rename new table to original name
ALTER TABLE tournaments_new RENAME TO tournaments;

-- Recreate indexes
CREATE INDEX IF NOT EXISTS idx_tournaments_status ON tournaments(status);
CREATE INDEX IF NOT EXISTS idx_tournaments_created_by ON tournaments(created_by);
