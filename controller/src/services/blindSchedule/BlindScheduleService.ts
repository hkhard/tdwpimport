/**
 * Blind Schedule Service
 * Business logic for blind schedule management
 */

import { BlindScheduleRepository } from '../../db/repositories/BlindScheduleRepository';
import { BlindLevelRepository } from '../../db/repositories/BlindLevelRepository';
import type { BlindSchedule, BlindLevel } from '@shared/types/timer';
import { randomUUID } from 'node:crypto';

interface CreateScheduleInput {
  name: string;
  description?: string;
  startingStack: number;
  breakInterval: number;
  breakDuration: number;
  levels: Array<{
    smallBlind: number;
    bigBlind: number;
    ante?: number;
    duration: number;
    isBreak: boolean;
  }>;
  createdBy?: string;
}

interface UpdateScheduleInput {
  name?: string;
  description?: string;
  startingStack?: number;
  breakInterval?: number;
  breakDuration?: number;
  levels?: Array<{
    smallBlind: number;
    bigBlind: number;
    ante?: number;
    duration: number;
    isBreak: boolean;
  }>;
}

interface ScheduleWithMetadata {
  id: string;
  name: string;
  description?: string;
  startingStack: number;
  breakInterval: number;
  breakDuration: number;
  isDefault: boolean;
  levelCount: number;
  totalDurationMinutes: number;
  createdAt: Date;
  updatedAt: Date;
  createdBy?: string;
  levels: BlindLevel[];
}

export class BlindScheduleService {
  private scheduleRepo: BlindScheduleRepository;
  private levelRepo: BlindLevelRepository;

  constructor() {
    this.scheduleRepo = new BlindScheduleRepository();
    this.levelRepo = new BlindLevelRepository();
  }

  /**
   * Get all schedules with list metadata (without levels)
   */
  async getAllSchedules(includeDefaults = true): Promise<ScheduleWithMetadata[]> {
    const list = this.scheduleRepo.findAllList(includeDefaults);
    return list.map(item => ({
      id: item.id,
      name: item.name,
      description: item.description || undefined,
      startingStack: item.startingStack,
      breakInterval: 0, // Will populate from full query if needed
      breakDuration: 0,
      isDefault: item.isDefault,
      levelCount: item.levelCount,
      totalDurationMinutes: item.totalDurationMinutes,
      createdAt: new Date(),
      updatedAt: new Date(),
      levels: [],
    }));
  }

  /**
   * Get single schedule with full levels
   */
  async getScheduleById(id: string): Promise<ScheduleWithMetadata | null> {
    const schedule = this.scheduleRepo.findByIdWithLevels(id);
    if (!schedule) return null;

    return {
      id: schedule.blindScheduleId,
      name: schedule.name,
      description: schedule.description,
      startingStack: schedule.startingStack,
      breakInterval: schedule.breakInterval,
      breakDuration: schedule.breakDuration,
      isDefault: schedule.isDefault,
      levelCount: schedule.levels.length,
      totalDurationMinutes: schedule.levels.reduce(
        (sum, level) => sum + (level.isBreak ? 0 : level.duration),
        0
      ),
      createdAt: schedule.createdAt,
      updatedAt: schedule.updatedAt,
      createdBy: schedule.createdBy,
      levels: schedule.levels,
    };
  }

  /**
   * Create new blind schedule
   */
  async createSchedule(input: CreateScheduleInput): Promise<ScheduleWithMetadata> {
    // Validate
    this.validateScheduleInput(input);

    // Check for duplicate name
    const existing = this.scheduleRepo.findWhere({ name: input.name });
    if (existing.length > 0) {
      throw new Error(`Blind schedule with name "${input.name}" already exists`);
    }

    // Validate levels and auto-generate level numbers
    const levelsWithNumbers: BlindLevel[] = input.levels.map((level, index) => ({
      blindLevelId: randomUUID(),
      level: index + 1, // Auto-generate sequential level numbers
      ...level,
    }));

    for (let i = 0; i < input.levels.length; i++) {
      const validation = this.levelRepo.validateLevel(input.levels[i]);
      if (!validation.valid) {
        throw new Error(`Level ${i + 1} invalid: ${validation.errors.join(', ')}`);
      }
    }

    // Insert with levels
    const scheduleId = this.scheduleRepo.insertWithLevels(
      {
        name: input.name,
        description: input.description,
        startingStack: input.startingStack,
        breakInterval: input.breakInterval,
        breakDuration: input.breakDuration,
        isDefault: false,
        createdAt: new Date(),
        updatedAt: new Date(),
        createdBy: input.createdBy,
      },
      levelsWithNumbers
    );

    const created = await this.getScheduleById(scheduleId);
    if (!created) throw new Error('Failed to retrieve created schedule');
    return created;
  }

  /**
   * Update existing schedule
   */
  async updateSchedule(
    id: string,
    updates: UpdateScheduleInput
  ): Promise<ScheduleWithMetadata> {
    const schedule = this.scheduleRepo.findById(id);
    if (!schedule) {
      throw new Error('Blind schedule not found');
    }

    // If default schedule, create a copy instead
    if (schedule.isDefault) {
      throw new Error('Cannot update default schedule. Create a copy instead.');
    }

    // Update name validation
    if (updates.name && updates.name !== schedule.name) {
      const existing = this.scheduleRepo.findWhere({ name: updates.name });
      if (existing.length > 0) {
        throw new Error(`Blind schedule with name "${updates.name}" already exists`);
      }
    }

    // Update schedule metadata
    const { levels, ...metadataUpdates } = updates;
    this.scheduleRepo.updateSchedule(id, metadataUpdates);

    // Update levels if provided
    if (levels && levels.length > 0) {
      // Auto-generate level numbers and IDs
      const levelsWithNumbers: BlindLevel[] = levels.map((level, index) => ({
        blindLevelId: randomUUID(),
        level: index + 1,
        ...level,
      }));

      // Validate all levels
      for (let i = 0; i < levels.length; i++) {
        const validation = this.levelRepo.validateLevel(levelsWithNumbers[i]);
        if (!validation.valid) {
          throw new Error(`Level ${i + 1} invalid: ${validation.errors.join(', ')}`);
        }
      }

      // Delete existing levels and insert new ones
      this.levelRepo.deleteByScheduleId(id);
      this.levelRepo.insertLevels(levelsWithNumbers, id);
    }

    const updated = await this.getScheduleById(id);
    if (!updated) throw new Error('Failed to retrieve updated schedule');
    return updated;
  }

  /**
   * Delete blind schedule
   */
  async deleteSchedule(id: string): Promise<void> {
    const schedule = this.scheduleRepo.findById(id);
    if (!schedule) {
      throw new Error('Blind schedule not found');
    }

    // Prevent deletion of default schedules
    if (this.scheduleRepo.isDefaultSchedule(id)) {
      throw new Error('Cannot delete default blind schedule');
    }

    // Check if in use by any tournament
    if (this.scheduleRepo.isInUse(id)) {
      throw new Error('Cannot delete blind schedule that is in use by tournaments');
    }

    this.scheduleRepo.deleteSchedule(id);
  }

  /**
   * Duplicate a schedule (for editing default schedules)
   */
  async duplicateSchedule(id: string, newName?: string): Promise<ScheduleWithMetadata> {
    const newId = this.scheduleRepo.duplicate(id, newName);
    if (!newId) {
      throw new Error('Failed to duplicate schedule');
    }

    const duplicated = await this.getScheduleById(newId);
    if (!duplicated) throw new Error('Failed to retrieve duplicated schedule');
    return duplicated;
  }

  /**
   * Get levels for a schedule (paginated)
   */
  async getLevels(
    scheduleId: string,
    offset = 0,
    limit = 50
  ): Promise<{ levels: BlindLevel[]; total: number }> {
    const schedule = this.scheduleRepo.findById(scheduleId);
    if (!schedule) {
      throw new Error('Blind schedule not found');
    }

    const levels = this.levelRepo.getLevelsPaginated(scheduleId, offset, limit);
    const total = this.levelRepo.countByScheduleId(scheduleId);

    return { levels, total };
  }

  /**
   * Update levels for a schedule (replace all)
   */
  async updateLevels(
    scheduleId: string,
    levels: BlindLevel[]
  ): Promise<void> {
    const schedule = this.scheduleRepo.findById(scheduleId);
    if (!schedule) {
      throw new Error('Blind schedule not found');
    }

    // If default schedule, prevent modification
    if (schedule.isDefault) {
      throw new Error('Cannot modify levels of default schedule');
    }

    // Validate all levels
    for (const level of levels) {
      const validation = this.levelRepo.validateLevel(level);
      if (!validation.valid) {
        throw new Error(`Level ${level.level} invalid: ${validation.errors.join(', ')}`);
      }
    }

    // Delete existing levels and insert new ones
    this.levelRepo.deleteByScheduleId(scheduleId);
    this.levelRepo.insertLevels(levels, scheduleId);
  }

  /**
   * Validate schedule input
   */
  private validateScheduleInput(input: CreateScheduleInput): void {
    if (!input.name || input.name.trim().length === 0) {
      throw new Error('Schedule name is required');
    }

    if (input.name.length > 255) {
      throw new Error('Schedule name must be 255 characters or less');
    }

    if (input.startingStack < 0) {
      throw new Error('Starting stack must be non-negative');
    }

    if (input.breakInterval < 0) {
      throw new Error('Break interval must be non-negative');
    }

    if (input.breakDuration < 0) {
      throw new Error('Break duration must be non-negative');
    }

    if (!input.levels || input.levels.length === 0) {
      throw new Error('At least one blind level is required');
    }
    // Note: Level numbers are auto-generated sequentially starting at 1
  }

  /**
   * Check if schedule is in use
   */
  isScheduleInUse(scheduleId: string): boolean {
    return this.scheduleRepo.isInUse(scheduleId);
  }

  /**
   * Get default schedules
   */
  async getDefaultSchedules(): Promise<ScheduleWithMetadata[]> {
    const schedules = this.scheduleRepo.findDefaultSchedules();
    return schedules.map(s => ({
      id: s.blindScheduleId,
      name: s.name,
      description: s.description,
      startingStack: s.startingStack,
      breakInterval: s.breakInterval,
      breakDuration: s.breakDuration,
      isDefault: s.isDefault,
      levelCount: s.levels.length,
      totalDurationMinutes: s.levels.reduce(
        (sum, level) => sum + (level.isBreak ? 0 : level.duration),
        0
      ),
      createdAt: s.createdAt,
      updatedAt: s.updatedAt,
      createdBy: s.createdBy,
      levels: s.levels,
    }));
  }
}

// Export singleton
export const blindScheduleService = new BlindScheduleService();
