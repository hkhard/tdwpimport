/**
 * Tournament Service
 * Business logic for tournament management
 *
 * Constitution Requirements:
 * - US5-A1: 100+ concurrent tournaments
 * - US2-A1: Offline tournament CRUD
 */

import type { Database } from 'better-sqlite3';
import { TournamentRepository } from '../../db/repositories/TournamentRepository';
import { TournamentAllocator } from './TournamentAllocator';
import type { Tournament, TournamentStatus, BlindLevel } from '@shared/types';

export interface CreateTournamentInput {
  name: string;
  description?: string;
  startTime: Date;
  blindScheduleId?: string;
  createdBy: string;
}

export interface UpdateTournamentInput {
  name?: string;
  description?: string;
  startTime?: Date;
  endTime?: Date;
  status?: TournamentStatus;
  prizePool?: number;
  currentBlindLevel?: number;
}

export interface TournamentListFilter {
  status?: TournamentStatus;
  createdBy?: string;
  active?: boolean;
  limit?: number;
  offset?: number;
}

export interface TournamentListResult {
  tournaments: Tournament[];
  total: number;
  limit: number;
  offset: number;
}

/**
 * Tournament Service
 *
 * Business logic for tournament operations:
 * - Create tournaments with validation
 * - Update tournaments with authorization
 * - List/filter tournaments
 * - Delete tournaments
 * - Status transitions
 */
export class TournamentService {
  private db: Database;
  private tournamentRepo: TournamentRepository;
  private allocator: TournamentAllocator;

  constructor(db: Database, allocator: TournamentAllocator) {
    this.db = db;
    this.tournamentRepo = new TournamentRepository();
    this.allocator = allocator;
  }

  /**
   * Create a new tournament
   */
  async createTournament(input: CreateTournamentInput): Promise<Tournament> {
    // Allocate tournament ID
    const allocation = this.allocator.allocateTournament(input.name, input.createdBy);

    // Create tournament in database
    const tournament: Partial<Tournament> = {
      tournamentId: allocation.tournamentId,
      name: input.name,
      description: input.description || undefined,
      startTime: input.startTime,
      endTime: undefined,
      status: 'upcoming' as TournamentStatus,
      currentBlindLevel: 1,
      timerState: {
        isRunning: false,
        isPaused: true,
        level: 1,
        elapsedTime: 0,
        tenths: 0,
        lastUpdateTime: new Date(),
      },
      prizePool: undefined,
      blindScheduleId: input.blindScheduleId || undefined,
      createdBy: input.createdBy,
    };

    this.tournamentRepo.insertTournament(tournament);

    // Register with allocator
    await this.allocator.registerTournament(allocation.tournamentId);

    const created = await this.tournamentRepo.findTournamentById(allocation.tournamentId);
    if (!created) {
      throw new Error('Failed to create tournament');
    }

    console.log(`[TournamentService] Created tournament ${allocation.tournamentId}`);
    return created;
  }

  /**
   * Get tournament by ID
   */
  async getTournament(tournamentId: string): Promise<Tournament | null> {
    return this.tournamentRepo.findTournamentById(tournamentId) ?? null;
  }

  /**
   * List tournaments with filters
   */
  async listTournaments(filter: TournamentListFilter = {}): Promise<TournamentListResult> {
    const {
      status,
      createdBy,
      active,
      limit = 50,
      offset = 0,
    } = filter;

    // Build query
    let sql = 'SELECT * FROM tournaments WHERE 1=1';
    const params: any[] = [];

    if (status) {
      sql += ' AND status = ?';
      params.push(status);
    }

    if (createdBy) {
      sql += ' AND created_by = ?';
      params.push(createdBy);
    }

    if (active !== undefined) {
      sql += ' AND timer_is_running = ?';
      params.push(active ? 1 : 0);
    }

    // Get total count
    const countSql = sql.replace('SELECT *', 'SELECT COUNT(*) as count');
    const countResult = this.db.prepare(countSql).get(...params) as { count: number };
    const total = countResult.count;

    // Add pagination
    sql += ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
    params.push(limit, offset);

    const rows = this.db.prepare(sql).all(...params);
    const tournaments = rows.map((row: any) =>
      this.tournamentRepo.toDomain(row)
    );

    return {
      tournaments,
      total,
      limit,
      offset,
    };
  }

  /**
   * Update tournament
   */
  async updateTournament(
    tournamentId: string,
    input: UpdateTournamentInput
  ): Promise<Tournament> {
    const existing = await this.tournamentRepo.findTournamentById(tournamentId);
    if (!existing) {
      throw new Error(`Tournament ${tournamentId} not found`);
    }

    // Build update object
    const update: any = {};

    if (input.name !== undefined) {
      update.name = input.name;
    }

    if (input.description !== undefined) {
      update.description = input.description;
    }

    if (input.startTime !== undefined) {
      update.start_time = input.startTime.toISOString();
    }

    if (input.endTime !== undefined) {
      update.end_time = input.endTime ? input.endTime.toISOString() : null;
    }

    if (input.status !== undefined) {
      update.status = input.status;
      this.allocator.updateStatus(tournamentId, input.status);
    }

    if (input.prizePool !== undefined) {
      update.prize_pool = input.prizePool;
    }

    if (input.currentBlindLevel !== undefined) {
      update.current_blind_level = input.currentBlindLevel;
    }

    update.updated_at = new Date().toISOString();

    // Apply update
    this.tournamentRepo.updateTournament(tournamentId, update);

    const updated = await this.tournamentRepo.findTournamentById(tournamentId);
    if (!updated) {
      throw new Error('Failed to update tournament');
    }

    console.log(`[TournamentService] Updated tournament ${tournamentId}`);
    return updated;
  }

  /**
   * Delete tournament
   */
  async deleteTournament(tournamentId: string): Promise<void> {
    const existing = await this.tournamentRepo.findTournamentById(tournamentId);
    if (!existing) {
      throw new Error(`Tournament ${tournamentId} not found`);
    }

    // Check if tournament is active
    if (existing.status === 'active' || existing.timerState.isRunning) {
      throw new Error('Cannot delete active tournament');
    }

    // Delete from database
    this.tournamentRepo.delete(tournamentId);

    // Release from allocator
    this.allocator.releaseTournament(tournamentId);

    console.log(`[TournamentService] Deleted tournament ${tournamentId}`);
  }

  /**
   * Update tournament status
   */
  async updateStatus(tournamentId: string, status: TournamentStatus): Promise<Tournament> {
    return this.updateTournament(tournamentId, { status });
  }

  /**
   * Bind device to tournament
   */
  async bindDevice(tournamentId: string, deviceId: string): Promise<void> {
    const existing = await this.tournamentRepo.findTournamentById(tournamentId);
    if (!existing) {
      throw new Error(`Tournament ${tournamentId} not found`);
    }

    // Update in database
    this.tournamentRepo.updateTournament(tournamentId, {
      controllerDeviceId: deviceId,
    });

    // Update in allocator
    this.allocator.bindDevice(tournamentId, deviceId);

    console.log(`[TournamentService] Bound device ${deviceId} to tournament ${tournamentId}`);
  }

  /**
   * Unbind device from tournament
   */
  async unbindDevice(tournamentId: string): Promise<void> {
    const existing = await this.tournamentRepo.findTournamentById(tournamentId);
    if (!existing) {
      throw new Error(`Tournament ${tournamentId} not found`);
    }

    // Update in database
    this.tournamentRepo.updateTournament(tournamentId, {
      controllerDeviceId: undefined,
    });

    // Update in allocator
    this.allocator.unbindDevice(tournamentId);

    console.log(`[TournamentService] Unbound device from tournament ${tournamentId}`);
  }

  /**
   * Get tournament by device
   */
  async getTournamentByDevice(deviceId: string): Promise<Tournament | null> {
    const allocation = this.allocator.getDeviceTournament(deviceId);
    if (!allocation) {
      return null;
    }

    return this.tournamentRepo.findTournamentById(allocation.tournamentId) ?? null;
  }

  /**
   * Get tournament statistics
   */
  getStatistics() {
    return this.allocator.getStatistics();
  }
}

/**
 * Create a tournament service instance
 */
export function createTournamentService(
  db: Database,
  allocator: TournamentAllocator
): TournamentService {
  return new TournamentService(db, allocator);
}
