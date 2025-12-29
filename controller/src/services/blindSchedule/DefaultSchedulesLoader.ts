/**
 * Default Schedules Loader
 * Seeds the database with pre-loaded default blind schedules
 */

import { BlindScheduleRepository } from '../../db/repositories/BlindScheduleRepository';
import type { BlindLevel } from '@shared/types/timer';

/**
 * Default blind schedule definitions
 */
interface DefaultScheduleDefinition {
  name: string;
  description: string;
  startingStack: number;
  breakInterval: number;
  breakDuration: number;
  levels: Array<{
    level: number;
    smallBlind: number;
    bigBlind: number;
    ante?: number;
    duration: number;
    isBreak: boolean;
  }>;
}

const DEFAULT_SCHEDULES: DefaultScheduleDefinition[] = [
  {
    name: 'Turbo',
    description: 'Fast-paced tournament with 10-minute levels. Perfect for single-evening events.',
    startingStack: 10000,
    breakInterval: 0, // No automatic breaks
    breakDuration: 10,
    levels: [
      // Early levels
      { level: 1, smallBlind: 25, bigBlind: 50, duration: 10, isBreak: false },
      { level: 2, smallBlind: 50, bigBlind: 100, duration: 10, isBreak: false },
      { level: 3, smallBlind: 75, bigBlind: 150, duration: 10, isBreak: false },
      { level: 4, smallBlind: 100, bigBlind: 200, duration: 10, isBreak: false },
      { level: 5, smallBlind: 150, bigBlind: 300, duration: 10, isBreak: false },
      // Mid levels
      { level: 6, smallBlind: 200, bigBlind: 400, duration: 10, isBreak: false },
      { level: 7, smallBlind: 300, bigBlind: 600, duration: 10, isBreak: false },
      { level: 8, smallBlind: 400, bigBlind: 800, duration: 10, isBreak: false },
      { level: 9, smallBlind: 600, bigBlind: 1200, duration: 10, isBreak: false },
      { level: 10, smallBlind: 800, bigBlind: 1600, duration: 10, isBreak: false },
      // Late levels with antes
      { level: 11, smallBlind: 1000, bigBlind: 2000, ante: 200, duration: 10, isBreak: false },
      { level: 12, smallBlind: 1500, bigBlind: 3000, ante: 300, duration: 10, isBreak: false },
      { level: 13, smallBlind: 2000, bigBlind: 4000, ante: 400, duration: 10, isBreak: false },
      { level: 14, smallBlind: 3000, bigBlind: 6000, ante: 600, duration: 10, isBreak: false },
      { level: 15, smallBlind: 4000, bigBlind: 8000, ante: 800, duration: 10, isBreak: false },
      { level: 16, smallBlind: 6000, bigBlind: 12000, ante: 1000, duration: 10, isBreak: false },
      { level: 17, smallBlind: 8000, bigBlind: 16000, ante: 1500, duration: 10, isBreak: false },
      { level: 18, smallBlind: 10000, bigBlind: 20000, ante: 2000, duration: 10, isBreak: false },
      { level: 19, smallBlind: 15000, bigBlind: 30000, ante: 2500, duration: 10, isBreak: false },
      { level: 20, smallBlind: 20000, bigBlind: 40000, ante: 3000, duration: 10, isBreak: false },
    ],
  },
  {
    name: 'Standard',
    description: 'Classic tournament structure with 20-minute levels. Ideal for longer sessions.',
    startingStack: 15000,
    breakInterval: 5, // Break every 5 levels
    breakDuration: 15,
    levels: [
      // Early levels
      { level: 1, smallBlind: 25, bigBlind: 50, duration: 20, isBreak: false },
      { level: 2, smallBlind: 50, bigBlind: 100, duration: 20, isBreak: false },
      { level: 3, smallBlind: 75, bigBlind: 150, duration: 20, isBreak: false },
      { level: 4, smallBlind: 100, bigBlind: 200, duration: 20, isBreak: false },
      { level: 5, smallBlind: 150, bigBlind: 300, duration: 20, isBreak: false },
      // Break
      { level: 6, smallBlind: 0, bigBlind: 0, duration: 15, isBreak: true },
      // Mid levels
      { level: 7, smallBlind: 200, bigBlind: 400, duration: 20, isBreak: false },
      { level: 8, smallBlind: 300, bigBlind: 600, duration: 20, isBreak: false },
      { level: 9, smallBlind: 400, bigBlind: 800, duration: 20, isBreak: false },
      { level: 10, smallBlind: 600, bigBlind: 1200, duration: 20, isBreak: false },
      // Break
      { level: 11, smallBlind: 0, bigBlind: 0, duration: 15, isBreak: true },
      // Late levels with antes
      { level: 12, smallBlind: 800, bigBlind: 1600, ante: 150, duration: 20, isBreak: false },
      { level: 13, smallBlind: 1000, bigBlind: 2000, ante: 200, duration: 20, isBreak: false },
      { level: 14, smallBlind: 1500, bigBlind: 3000, ante: 300, duration: 20, isBreak: false },
      { level: 15, smallBlind: 2000, bigBlind: 4000, ante: 400, duration: 20, isBreak: false },
      // Break
      { level: 16, smallBlind: 0, bigBlind: 0, duration: 15, isBreak: true },
      // Final levels
      { level: 17, smallBlind: 3000, bigBlind: 6000, ante: 500, duration: 20, isBreak: false },
      { level: 18, smallBlind: 4000, bigBlind: 8000, ante: 750, duration: 20, isBreak: false },
      { level: 19, smallBlind: 6000, bigBlind: 12000, ante: 1000, duration: 20, isBreak: false },
      { level: 20, smallBlind: 8000, bigBlind: 16000, ante: 1500, duration: 20, isBreak: false },
      { level: 21, smallBlind: 10000, bigBlind: 20000, ante: 2000, duration: 20, isBreak: false },
      { level: 22, smallBlind: 15000, bigBlind: 30000, ante: 2500, duration: 20, isBreak: false },
    ],
  },
  {
    name: 'Deep Stack',
    description: 'Slow-paced tournament with 30-minute levels and deep starting stacks. For serious players.',
    startingStack: 25000,
    breakInterval: 4, // Break every 4 levels
    breakDuration: 20,
    levels: [
      // Early levels - gradual progression
      { level: 1, smallBlind: 25, bigBlind: 50, duration: 30, isBreak: false },
      { level: 2, smallBlind: 50, bigBlind: 100, duration: 30, isBreak: false },
      { level: 3, smallBlind: 75, bigBlind: 150, duration: 30, isBreak: false },
      { level: 4, smallBlind: 100, bigBlind: 200, duration: 30, isBreak: false },
      // Break
      { level: 5, smallBlind: 0, bigBlind: 0, duration: 20, isBreak: true },
      // Low-mid levels
      { level: 6, smallBlind: 150, bigBlind: 300, duration: 30, isBreak: false },
      { level: 7, smallBlind: 200, bigBlind: 400, duration: 30, isBreak: false },
      { level: 8, smallBlind: 300, bigBlind: 600, duration: 30, isBreak: false },
      { level: 9, smallBlind: 400, bigBlind: 800, duration: 30, isBreak: false },
      // Break
      { level: 10, smallBlind: 0, bigBlind: 0, duration: 20, isBreak: true },
      // Mid levels
      { level: 11, smallBlind: 600, bigBlind: 1200, duration: 30, isBreak: false },
      { level: 12, smallBlind: 800, bigBlind: 1600, duration: 30, isBreak: false },
      { level: 13, smallBlind: 1000, bigBlind: 2000, duration: 30, isBreak: false },
      { level: 14, smallBlind: 1500, bigBlind: 3000, duration: 30, isBreak: false },
      // Break
      { level: 15, smallBlind: 0, bigBlind: 0, duration: 20, isBreak: true },
      // Late levels with antes
      { level: 16, smallBlind: 2000, bigBlind: 4000, ante: 250, duration: 30, isBreak: false },
      { level: 17, smallBlind: 3000, bigBlind: 6000, ante: 400, duration: 30, isBreak: false },
      { level: 18, smallBlind: 4000, bigBlind: 8000, ante: 500, duration: 30, isBreak: false },
      { level: 19, smallBlind: 6000, bigBlind: 12000, ante: 750, duration: 30, isBreak: false },
      // Break
      { level: 20, smallBlind: 0, bigBlind: 0, duration: 20, isBreak: true },
      // Final levels
      { level: 21, smallBlind: 8000, bigBlind: 16000, ante: 1000, duration: 30, isBreak: false },
      { level: 22, smallBlind: 10000, bigBlind: 20000, ante: 1500, duration: 30, isBreak: false },
      { level: 23, smallBlind: 15000, bigBlind: 30000, ante: 2000, duration: 30, isBreak: false },
      { level: 24, smallBlind: 20000, bigBlind: 40000, ante: 2500, duration: 30, isBreak: false },
      { level: 25, smallBlind: 30000, bigBlind: 60000, ante: 3000, duration: 30, isBreak: false },
      { level: 26, smallBlind: 40000, bigBlind: 80000, ante: 4000, duration: 30, isBreak: false },
    ],
  },
];

export class DefaultSchedulesLoader {
  private scheduleRepo: BlindScheduleRepository;

  constructor() {
    this.scheduleRepo = new BlindScheduleRepository();
  }

  /**
   * Seed default schedules if they don't exist
   */
  async seedDefaultSchedules(): Promise<void> {
    const existingDefaults = this.scheduleRepo.findDefaultSchedules();

    for (const definition of DEFAULT_SCHEDULES) {
      // Check if this default schedule already exists
      const exists = existingDefaults.some(s => s.name === definition.name);

      if (!exists) {
        console.log(`[DefaultSchedulesLoader] Seeding default schedule: ${definition.name}`);

        // Generate UUID for schedule
        const scheduleId = crypto.randomUUID();

        // Insert schedule with levels
        this.scheduleRepo.insertWithLevels(
          {
            blindScheduleId: scheduleId,
            name: definition.name,
            description: definition.description,
            startingStack: definition.startingStack,
            breakInterval: definition.breakInterval,
            breakDuration: definition.breakDuration,
            isDefault: true,
            createdAt: new Date(),
            updatedAt: new Date(),
            createdBy: undefined,
          },
          definition.levels.map((level, idx) => ({
            blindLevelId: crypto.randomUUID(),
            level: level.level,
            smallBlind: level.smallBlind,
            bigBlind: level.bigBlind,
            ante: level.ante,
            duration: level.duration,
            isBreak: level.isBreak,
          }))
        );

        console.log(`[DefaultSchedulesLoader] Seeded ${definition.name} with ${definition.levels.length} levels`);
      }
    }

    console.log(`[DefaultSchedulesLoader] Default schedules seeding complete`);
  }

  /**
   * Get all default schedule definitions (for reference)
   */
  getDefaultDefinitions(): DefaultScheduleDefinition[] {
    return DEFAULT_SCHEDULES;
  }

  /**
   * Reset and reseed all default schedules
   * WARNING: This will delete existing default schedules
   */
  async resetDefaultSchedules(): Promise<void> {
    console.log(`[DefaultSchedulesLoader] Resetting default schedules...`);

    // Delete existing default schedules
    const existingDefaults = this.scheduleRepo.findDefaultSchedules();
    for (const schedule of existingDefaults) {
      this.scheduleRepo.deleteSchedule(schedule.blindScheduleId);
    }

    // Reseed
    await this.seedDefaultSchedules();

    console.log(`[DefaultSchedulesLoader] Reset complete`);
  }
}

// Export singleton
export const defaultSchedulesLoader = new DefaultSchedulesLoader();
