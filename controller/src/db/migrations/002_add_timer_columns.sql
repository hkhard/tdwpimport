-- Add timer precision and device binding columns to tournaments table

-- Add tenths column for 100ms precision display
ALTER TABLE tournaments ADD COLUMN timer_tenths INTEGER NOT NULL DEFAULT 0;

-- Add remaining time column for current level
ALTER TABLE tournaments ADD COLUMN timer_remaining_time INTEGER;

-- Add controller device ID for device binding
ALTER TABLE tournaments ADD COLUMN controller_device_id TEXT;

-- Create index for controller device lookups
CREATE INDEX IF NOT EXISTS idx_tournaments_controller_device ON tournaments(controller_device_id);
