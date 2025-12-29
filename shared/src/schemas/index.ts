/**
 * Shared Zod validation schemas
 */

import { z } from 'zod';

export const tournamentSchema = z.object({
  tournamentId: z.string().uuid(),
  name: z.string().min(1).max(200),
  description: z.string().max(1000).optional(),
  startTime: z.coerce.date(),
  endTime: z.coerce.date().optional(),
  status: z.enum(['upcoming', 'active', 'completed', 'cancelled']),
  currentBlindLevel: z.number().int().min(0),
  blindScheduleId: z.string().uuid().optional(),
  createdBy: z.string().uuid(),
});

export const playerSchema = z.object({
  playerId: z.string().uuid(),
  name: z.string().min(1).max(100),
  email: z.string().email().optional(),
  phone: z.string().regex(/^\+?[\d\s-()]+$/).optional(),
});

export const tournamentPlayerSchema = z.object({
  tournamentPlayerId: z.string().uuid(),
  tournamentId: z.string().uuid(),
  playerId: z.string().uuid(),
  startingStack: z.number().int().positive(),
  finishPosition: z.number().int().positive().optional(),
  tableName: z.string().max(50).optional(),
  seatNumber: z.number().int().min(1).max(99).optional(),
});

export const timerEventSchema = z.object({
  eventId: z.string().uuid(),
  tournamentId: z.string().uuid(),
  eventType: z.enum([
    'tournament_start',
    'tournament_end',
    'level_start',
    'level_end',
    'break_start',
    'break_end',
    'pause',
    'resume',
    'director_override',
  ]),
  previousState: z.object({
    level: z.number().int(),
    elapsedTime: z.number(),
    isRunning: z.boolean(),
    isPaused: z.boolean(),
  }).optional(),
  newState: z.object({
    level: z.number().int(),
    elapsedTime: z.number(),
    isRunning: z.boolean(),
    isPaused: z.boolean(),
  }),
  deviceId: z.string().optional(),
});

export const syncUploadSchema = z.object({
  deviceId: z.string(),
  changes: z.array(z.object({
    entityType: z.enum(['tournament', 'player', 'tournament_player', 'timer_event']),
    operation: z.enum(['create', 'update', 'delete']),
    entityId: z.string(),
    data: z.unknown(),
    localTimestamp: z.number(),
  })),
  lastSyncTimestamp: z.number(),
});
