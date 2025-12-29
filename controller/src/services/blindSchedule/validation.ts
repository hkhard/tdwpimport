/**
 * Blind Scheme Validation Service
 * Zod schemas for blind level and scheme validation
 */

import { z } from 'zod';
import type { BlindSchemeValidationError } from '@shared/types/timer';

/**
 * Blind Level Schema
 * Validates individual blind level data
 */
export const BlindLevelSchema = z.object({
  blindLevelId: z.string().uuid(),
  blindScheduleId: z.string().uuid(),
  level: z.number().int().positive(),
  smallBlind: z.number().int().nonnegative(),
  bigBlind: z.number().int().nonnegative(),
  ante: z.number().int().nonnegative().optional(),
  duration: z.number().int().positive(),
  isBreak: z.boolean(),
}).refine(
  (data) => !data.isBreak || (data.smallBlind === 0 && data.bigBlind === 0),
  {
    message: "Break levels must have zero blinds",
    path: ['smallBlind'], // Error path for break validation
  }
).refine(
  (data) => data.bigBlind >= data.smallBlind,
  {
    message: "Big blind must be greater than or equal to small blind",
    path: ['bigBlind'],
  }
);

/**
 * Blind Level Create Input Schema
 * For creating new levels (without IDs)
 */
export const CreateBlindLevelInputSchema = z.object({
  smallBlind: z.number().int().nonnegative(),
  bigBlind: z.number().int().nonnegative(),
  ante: z.number().int().nonnegative().optional(),
  duration: z.number().int().positive(),
  isBreak: z.boolean(),
}).refine(
  (data) => !data.isBreak || (data.smallBlind === 0 && data.bigBlind === 0),
  {
    message: "Break levels must have zero blinds",
  }
).refine(
  (data) => data.bigBlind >= data.smallBlind,
  {
    message: "Big blind must be greater than or equal to small blind",
  }
);

/**
 * Blind Scheme Schema
 * Validates complete blind scheme with levels
 */
export const BlindSchemeSchema = z.object({
  blindScheduleId: z.string().uuid(),
  name: z.string().min(1, "Scheme name is required").max(100, "Scheme name too long (max 100 characters)"),
  description: z.string().max(500, "Description too long (max 500 characters)").optional(),
  startingStack: z.number().int().positive("Starting stack must be greater than 0"),
  breakInterval: z.number().int().nonnegative("Break interval must be non-negative"),
  breakDuration: z.number().int().nonnegative("Break duration must be non-negative"),
  breakIntervals: z.array(z.number().int().positive()).optional(),
  isDefault: z.boolean().optional(),
  createdAt: z.date().optional(),
  updatedAt: z.date().optional(),
  levels: z.array(
    BlindLevelSchema
  ).min(1, "Scheme must have at least one level"),
}).refine(
  (data) => {
    // Check sequential numbering starting from 1
    for (let i = 0; i < data.levels.length; i++) {
      if (data.levels[i].level !== i + 1) {
        return false;
      }
    }
    return true;
  },
  {
    message: "Levels must be numbered sequentially from 1",
    path: ['levels'],
  }
);

/**
 * Blind Scheme Create Input Schema
 * For creating new schemes
 */
export const CreateBlindSchemeInputSchema = z.object({
  name: z.string().min(1, "Scheme name is required").max(100, "Scheme name too long (max 100 characters)"),
  description: z.string().max(500, "Description too long (max 500 characters)").optional(),
  startingStack: z.number().int().positive("Starting stack must be greater than 0"),
  breakInterval: z.number().int().nonnegative("Break interval must be non-negative"),
  breakDuration: z.number().int().nonnegative("Break duration must be non-negative"),
  levels: z.array(
    CreateBlindLevelInputSchema
  ).min(1, "Scheme must have at least one level"),
}).refine(
  (data) => {
    // Validate sequential numbering
    // Levels in create input don't have level numbers yet, will be auto-assigned
    return data.levels.length >= 1;
  },
  {
    message: "Scheme must have at least one level",
  }
);

/**
 * Blind Scheme Update Input Schema
 * For updating existing schemes (all fields optional)
 */
export const UpdateBlindSchemeInputSchema = z.object({
  name: z.string().min(1).max(100).optional(),
  description: z.string().max(500).optional(),
  startingStack: z.number().int().positive().optional(),
  breakInterval: z.number().int().nonnegative().optional(),
  breakDuration: z.number().int().nonnegative().optional(),
  levels: z.array(CreateBlindLevelInputSchema).min(1).optional(),
}).refine(
  (data) => {
    // If levels provided, must have at least one
    if (data.levels !== undefined) {
      return data.levels.length >= 1;
    }
    return true;
  },
  {
    message: "Scheme must have at least one level",
  }
);

/**
 * Validation error formatter
 * Converts Zod errors to BlindSchemeValidationError format
 */
export function formatValidationErrors(error: z.ZodError): BlindSchemeValidationError[] {
  const errors: BlindSchemeValidationError[] = [];

  error.errors.forEach((err) => {
    const field = err.path.join('.');
    const message = err.message;

    // Extract level number if present in path
    const levelMatch = field.match(/levels\[(\d+)\]/);
    const level = levelMatch ? parseInt(levelMatch[1], 10) + 1 : undefined;

    errors.push({
      field,
      message,
      level,
    });
  });

  return errors;
}

/**
 * Validate blind level
 * @param level - Level data to validate
 * @returns Validation result with errors if any
 */
export function validateLevel(level: unknown): {
  success: boolean;
  errors: BlindSchemeValidationError[];
} {
  const result = CreateBlindLevelInputSchema.safeParse(level);

  if (result.success) {
    return { success: true, errors: [] };
  }

  return {
    success: false,
    errors: formatValidationErrors(result.error),
  };
}

/**
 * Validate blind scheme
 * @param scheme - Scheme data to validate
 * @returns Validation result with errors if any
 */
export function validateScheme(scheme: unknown): {
  success: boolean;
  errors: BlindSchemeValidationError[];
} {
  const result = CreateBlindSchemeInputSchema.safeParse(scheme);

  if (result.success) {
    return { success: true, errors: [] };
  }

  return {
    success: false,
    errors: formatValidationErrors(result.error),
  };
}

/**
 * Check for duplicate scheme name
 * @param name - Scheme name to check
 * @param existingNames - Array of existing scheme names (case-insensitive)
 * @returns True if name is duplicate
 */
export function checkDuplicateName(
  name: string,
  existingNames: string[]
): boolean {
  const normalizedName = name.toLowerCase().trim();
  return existingNames.some(
    (existing) => existing.toLowerCase().trim() === normalizedName
  );
}
