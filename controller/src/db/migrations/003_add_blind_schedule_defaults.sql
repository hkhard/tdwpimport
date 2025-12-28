-- Add isDefault column to blind_schedules table
-- This marks pre-loaded default schedules (Turbo, Standard, Deep Stack)
-- that cannot be deleted by users
-- Also add description, break_interval, break_duration, and created_by columns

-- Add description column
ALTER TABLE blind_schedules ADD COLUMN description TEXT;

-- Add break_interval column (levels between breaks)
ALTER TABLE blind_schedules ADD COLUMN break_interval INTEGER NOT NULL DEFAULT 0;

-- Add break_duration column (break length in minutes)
ALTER TABLE blind_schedules ADD COLUMN break_duration INTEGER NOT NULL DEFAULT 0;

-- Add isDefault column
ALTER TABLE blind_schedules ADD COLUMN isDefault INTEGER NOT NULL DEFAULT 0;

-- Add created_by column
ALTER TABLE blind_schedules ADD COLUMN created_by TEXT;

-- Create index for filtering default schedules
CREATE INDEX IF NOT EXISTS idx_blindschedule_isDefault ON blind_schedules(isDefault);

-- DOWN migration (for rollback)
-- DROP INDEX idx_blindschedule_isDefault;
-- ALTER TABLE blind_schedules DROP COLUMN created_by;
-- ALTER TABLE blind_schedules DROP COLUMN isDefault;
-- ALTER TABLE blind_schedules DROP COLUMN break_duration;
-- ALTER TABLE blind_schedules DROP COLUMN break_interval;
-- ALTER TABLE blind_schedules DROP COLUMN description;
