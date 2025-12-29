-- Fix blind level initialization
-- Change default from 0 to 1 and update existing tournaments

-- Step 1: Update existing tournaments with level 0
UPDATE tournaments SET current_blind_level = 1 WHERE current_blind_level = 0;

-- Step 2: Recreate table with correct DEFAULT (handle NULLs with COALESCE)
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
  created_by TEXT NOT NULL DEFAULT 'demo-user',
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Copy data with COALESCE defaults for NULL values
INSERT INTO tournaments_new
SELECT
  tournament_id,
  name,
  description,
  start_time,
  end_time,
  status,
  current_blind_level,
  COALESCE(timer_is_running, 0),
  COALESCE(timer_is_paused, 1),
  COALESCE(timer_elapsed_time, 0),
  COALESCE(timer_tenths, 0),
  timer_remaining_time,
  COALESCE(timer_last_update, datetime('now')),
  prize_pool,
  blind_schedule_id,
  controller_device_id,
  COALESCE(created_by, 'demo-user'),
  COALESCE(created_at, datetime('now')),
  COALESCE(updated_at, datetime('now'))
FROM tournaments;

DROP TABLE tournaments;
ALTER TABLE tournaments_new RENAME TO tournaments;

-- Recreate indexes
CREATE INDEX IF NOT EXISTS idx_tournaments_status ON tournaments(status);
CREATE INDEX IF NOT EXISTS idx_tournaments_created_by ON tournaments(created_by);
