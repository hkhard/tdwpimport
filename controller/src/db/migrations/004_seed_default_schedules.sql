-- Seed Default Blind Schedules
-- This migration inserts the three default blind schedules: Turbo, Standard, and Deep Stack

-- Note: Schedule IDs use UUIDs that must be consistent across inserts

-- ============================================
-- TURBO Schedule
-- 10-minute levels, fast-paced
-- ============================================

-- Insert Turbo schedule
INSERT INTO blind_schedules (blind_schedule_id, name, description, starting_stack, break_interval, break_duration, isDefault, created_at, updated_at, created_by)
VALUES (
  'turbo-schedule-uuid-001',
  'Turbo',
  'Fast-paced tournament with 10-minute levels. Perfect for single-evening events.',
  10000,
  0,
  10,
  1,
  datetime('now'),
  datetime('now'),
  NULL
);

-- Insert Turbo levels (20 levels total)
INSERT INTO blind_levels (blind_level_id, blind_schedule_id, level, small_blind, big_blind, ante, duration, is_break)
VALUES
  -- Early levels
  ('turbo-level-001', 'turbo-schedule-uuid-001', 1, 25, 50, NULL, 10, 0),
  ('turbo-level-002', 'turbo-schedule-uuid-001', 2, 50, 100, NULL, 10, 0),
  ('turbo-level-003', 'turbo-schedule-uuid-001', 3, 75, 150, NULL, 10, 0),
  ('turbo-level-004', 'turbo-schedule-uuid-001', 4, 100, 200, NULL, 10, 0),
  ('turbo-level-005', 'turbo-schedule-uuid-001', 5, 150, 300, NULL, 10, 0),
  -- Mid levels
  ('turbo-level-006', 'turbo-schedule-uuid-001', 6, 200, 400, NULL, 10, 0),
  ('turbo-level-007', 'turbo-schedule-uuid-001', 7, 300, 600, NULL, 10, 0),
  ('turbo-level-008', 'turbo-schedule-uuid-001', 8, 400, 800, NULL, 10, 0),
  ('turbo-level-009', 'turbo-schedule-uuid-001', 9, 600, 1200, NULL, 10, 0),
  ('turbo-level-010', 'turbo-schedule-uuid-001', 10, 800, 1600, NULL, 10, 0),
  -- Late levels with antes
  ('turbo-level-011', 'turbo-schedule-uuid-001', 11, 1000, 2000, 200, 10, 0),
  ('turbo-level-012', 'turbo-schedule-uuid-001', 12, 1500, 3000, 300, 10, 0),
  ('turbo-level-013', 'turbo-schedule-uuid-001', 13, 2000, 4000, 400, 10, 0),
  ('turbo-level-014', 'turbo-schedule-uuid-001', 14, 3000, 6000, 600, 10, 0),
  ('turbo-level-015', 'turbo-schedule-uuid-001', 15, 4000, 8000, 800, 10, 0),
  ('turbo-level-016', 'turbo-schedule-uuid-001', 16, 6000, 12000, 1000, 10, 0),
  ('turbo-level-017', 'turbo-schedule-uuid-001', 17, 8000, 16000, 1500, 10, 0),
  ('turbo-level-018', 'turbo-schedule-uuid-001', 18, 10000, 20000, 2000, 10, 0),
  ('turbo-level-019', 'turbo-schedule-uuid-001', 19, 15000, 30000, 2500, 10, 0),
  ('turbo-level-020', 'turbo-schedule-uuid-001', 20, 20000, 40000, 3000, 10, 0);

-- ============================================
-- STANDARD Schedule
-- 20-minute levels with breaks every 5 levels
-- ============================================

-- Insert Standard schedule
INSERT INTO blind_schedules (blind_schedule_id, name, description, starting_stack, break_interval, break_duration, isDefault, created_at, updated_at, created_by)
VALUES (
  'standard-schedule-uuid-002',
  'Standard',
  'Classic tournament structure with 20-minute levels. Ideal for longer sessions.',
  15000,
  5,
  15,
  1,
  datetime('now'),
  datetime('now'),
  NULL
);

-- Insert Standard levels (22 levels with breaks)
INSERT INTO blind_levels (blind_level_id, blind_schedule_id, level, small_blind, big_blind, ante, duration, is_break)
VALUES
  -- Early levels
  ('std-level-001', 'standard-schedule-uuid-002', 1, 25, 50, NULL, 20, 0),
  ('std-level-002', 'standard-schedule-uuid-002', 2, 50, 100, NULL, 20, 0),
  ('std-level-003', 'standard-schedule-uuid-002', 3, 75, 150, NULL, 20, 0),
  ('std-level-004', 'standard-schedule-uuid-002', 4, 100, 200, NULL, 20, 0),
  ('std-level-005', 'standard-schedule-uuid-002', 5, 150, 300, NULL, 20, 0),
  -- Break
  ('std-level-006', 'standard-schedule-uuid-002', 6, 0, 0, NULL, 15, 1),
  -- Mid levels
  ('std-level-007', 'standard-schedule-uuid-002', 7, 200, 400, NULL, 20, 0),
  ('std-level-008', 'standard-schedule-uuid-002', 8, 300, 600, NULL, 20, 0),
  ('std-level-009', 'standard-schedule-uuid-002', 9, 400, 800, NULL, 20, 0),
  ('std-level-010', 'standard-schedule-uuid-002', 10, 600, 1200, NULL, 20, 0),
  -- Break
  ('std-level-011', 'standard-schedule-uuid-002', 11, 0, 0, NULL, 15, 1),
  -- Late levels with antes
  ('std-level-012', 'standard-schedule-uuid-002', 12, 800, 1600, 150, 20, 0),
  ('std-level-013', 'standard-schedule-uuid-002', 13, 1000, 2000, 200, 20, 0),
  ('std-level-014', 'standard-schedule-uuid-002', 14, 1500, 3000, 300, 20, 0),
  ('std-level-015', 'standard-schedule-uuid-002', 15, 2000, 4000, 400, 20, 0),
  -- Break
  ('std-level-016', 'standard-schedule-uuid-002', 16, 0, 0, NULL, 15, 1),
  -- Final levels
  ('std-level-017', 'standard-schedule-uuid-002', 17, 3000, 6000, 500, 20, 0),
  ('std-level-018', 'standard-schedule-uuid-002', 18, 4000, 8000, 750, 20, 0),
  ('std-level-019', 'standard-schedule-uuid-002', 19, 6000, 12000, 1000, 20, 0),
  ('std-level-020', 'standard-schedule-uuid-002', 20, 8000, 16000, 1500, 20, 0),
  ('std-level-021', 'standard-schedule-uuid-002', 21, 10000, 20000, 2000, 20, 0),
  ('std-level-022', 'standard-schedule-uuid-002', 22, 15000, 30000, 2500, 20, 0);

-- ============================================
-- DEEP STACK Schedule
-- 30-minute levels with breaks every 4 levels, deep starting stack
-- ============================================

-- Insert Deep Stack schedule
INSERT INTO blind_schedules (blind_schedule_id, name, description, starting_stack, break_interval, break_duration, isDefault, created_at, updated_at, created_by)
VALUES (
  'deepstack-schedule-uuid-003',
  'Deep Stack',
  'Slow-paced tournament with 30-minute levels and deep starting stacks. For serious players.',
  25000,
  4,
  20,
  1,
  datetime('now'),
  datetime('now'),
  NULL
);

-- Insert Deep Stack levels (26 levels with breaks)
INSERT INTO blind_levels (blind_level_id, blind_schedule_id, level, small_blind, big_blind, ante, duration, is_break)
VALUES
  -- Early levels - gradual progression
  ('ds-level-001', 'deepstack-schedule-uuid-003', 1, 25, 50, NULL, 30, 0),
  ('ds-level-002', 'deepstack-schedule-uuid-003', 2, 50, 100, NULL, 30, 0),
  ('ds-level-003', 'deepstack-schedule-uuid-003', 3, 75, 150, NULL, 30, 0),
  ('ds-level-004', 'deepstack-schedule-uuid-003', 4, 100, 200, NULL, 30, 0),
  -- Break
  ('ds-level-005', 'deepstack-schedule-uuid-003', 5, 0, 0, NULL, 20, 1),
  -- Low-mid levels
  ('ds-level-006', 'deepstack-schedule-uuid-003', 6, 150, 300, NULL, 30, 0),
  ('ds-level-007', 'deepstack-schedule-uuid-003', 7, 200, 400, NULL, 30, 0),
  ('ds-level-008', 'deepstack-schedule-uuid-003', 8, 300, 600, NULL, 30, 0),
  ('ds-level-009', 'deepstack-schedule-uuid-003', 9, 400, 800, NULL, 30, 0),
  -- Break
  ('ds-level-010', 'deepstack-schedule-uuid-003', 10, 0, 0, NULL, 20, 1),
  -- Mid levels
  ('ds-level-011', 'deepstack-schedule-uuid-003', 11, 600, 1200, NULL, 30, 0),
  ('ds-level-012', 'deepstack-schedule-uuid-003', 12, 800, 1600, NULL, 30, 0),
  ('ds-level-013', 'deepstack-schedule-uuid-003', 13, 1000, 2000, NULL, 30, 0),
  ('ds-level-014', 'deepstack-schedule-uuid-003', 14, 1500, 3000, NULL, 30, 0),
  -- Break
  ('ds-level-015', 'deepstack-schedule-uuid-003', 15, 0, 0, NULL, 20, 1),
  -- Late levels with antes
  ('ds-level-016', 'deepstack-schedule-uuid-003', 16, 2000, 4000, 250, 30, 0),
  ('ds-level-017', 'deepstack-schedule-uuid-003', 17, 3000, 6000, 400, 30, 0),
  ('ds-level-018', 'deepstack-schedule-uuid-003', 18, 4000, 8000, 500, 30, 0),
  ('ds-level-019', 'deepstack-schedule-uuid-003', 19, 6000, 12000, 750, 30, 0),
  -- Break
  ('ds-level-020', 'deepstack-schedule-uuid-003', 20, 0, 0, NULL, 20, 1),
  -- Final levels
  ('ds-level-021', 'deepstack-schedule-uuid-003', 21, 8000, 16000, 1000, 30, 0),
  ('ds-level-022', 'deepstack-schedule-uuid-003', 22, 10000, 20000, 1500, 30, 0),
  ('ds-level-023', 'deepstack-schedule-uuid-003', 23, 15000, 30000, 2000, 30, 0),
  ('ds-level-024', 'deepstack-schedule-uuid-003', 24, 20000, 40000, 2500, 30, 0),
  ('ds-level-025', 'deepstack-schedule-uuid-003', 25, 30000, 60000, 3000, 30, 0),
  ('ds-level-026', 'deepstack-schedule-uuid-003', 26, 40000, 80000, 4000, 30, 0);

-- DOWN migration (for rollback)
-- DELETE FROM blind_levels WHERE blind_schedule_id IN ('turbo-schedule-uuid-001', 'standard-schedule-uuid-002', 'deepstack-schedule-uuid-003');
-- DELETE FROM blind_schedules WHERE blind_schedule_id IN ('turbo-schedule-uuid-001', 'standard-schedule-uuid-002', 'deepstack-schedule-uuid-003');
