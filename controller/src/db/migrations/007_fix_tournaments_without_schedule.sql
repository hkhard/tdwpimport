-- Fix Tournaments Without Blind Schedule
-- Assign the Standard blind schedule to any tournaments that don't have one

-- Note: The Standard schedule (standard-schedule-uuid-002) was created in migration 004
-- This migration ensures all tournaments have a blind schedule assigned

-- Update tournaments without a schedule
UPDATE tournaments
SET blind_schedule_id = 'standard-schedule-uuid-002',
    updated_at = datetime('now')
WHERE blind_schedule_id IS NULL;

-- Verify the update
-- SELECT tournament_id, name, blind_schedule_id FROM tournaments WHERE blind_schedule_id IS NULL;
-- Should return 0 rows after this migration

-- DOWN migration (for rollback)
-- To rollback, we would need to know which tournaments were originally NULL
-- This is intentionally a one-way migration since we can't accurately revert
