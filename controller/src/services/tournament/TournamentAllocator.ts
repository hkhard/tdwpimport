/**
 * Tournament Allocator Service
 * Manages tournament ID allocation and active tournament tracking
 *
 * Constitution Requirements:
 * - US5-A1: 100+ concurrent tournaments
 * - US5-A2: <1s recovery after restart
 */

import type { Database } from 'better-sqlite3';
import { TournamentRepository } from '../../db/repositories/TournamentRepository';
import type { Tournament, TournamentStatus } from '@shared/types';

export interface TournamentAllocation {
  /** Unique tournament ID */
  tournamentId: string;
  /** Tournament name */
  name: string;
  /** Current status */
  status: TournamentStatus;
  /** Whether timer is running */
  isTimerRunning: boolean;
  /** Allocated timestamp */
  allocatedAt: Date;
  /** Device ID controlling this tournament */
  controllerDeviceId?: string;
}

export interface AllocatorConfig {
  /** Database connection */
  db: Database;
  /** ID prefix for tournaments */
  idPrefix?: string;
  /** Max active tournaments */
  maxActive?: number;
}

/**
 * Tournament Allocator Service
 *
 * Manages tournament ID allocation and lifecycle:
 * - UUID-based ID generation with optional prefix
 * - Active tournament tracking
 * - Status management
 * - Device-to-tournament binding
 */
export class TournamentAllocator {
  private config: Required<AllocatorConfig>;
  private tournamentRepo: TournamentRepository;
  private activeTournaments: Map<string, TournamentAllocation> = new Map();

  constructor(config: AllocatorConfig) {
    this.config = {
      db: config.db,
      idPrefix: config.idPrefix || 'td',
      maxActive: config.maxActive || 100,
    };

    this.tournamentRepo = new TournamentRepository();
  }

  /**
   * Allocate a new tournament ID
   */
  allocateTournament(name: string, createdBy: string): TournamentAllocation {
    // Check capacity
    if (this.activeTournaments.size >= this.config.maxActive) {
      throw new Error(`Max active tournaments limit reached (${this.config.maxActive})`);
    }

    // Generate unique ID
    const tournamentId = this.generateId();

    const allocation: TournamentAllocation = {
      tournamentId,
      name,
      status: 'upcoming',
      isTimerRunning: false,
      allocatedAt: new Date(),
    };

    this.activeTournaments.set(tournamentId, allocation);

    console.log(`[TournamentAllocator] Allocated ${tournamentId} for "${name}"`);
    return allocation;
  }

  /**
   * Register tournament (after creation in database)
   */
  async registerTournament(tournamentId: string): Promise<void> {
    const tournament = await this.tournamentRepo.findTournamentById(tournamentId);
    if (!tournament) {
      throw new Error(`Tournament ${tournamentId} not found in database`);
    }

    const allocation = this.activeTournaments.get(tournamentId);
    if (allocation) {
      allocation.status = tournament.status;
      allocation.isTimerRunning = tournament.timerState.isRunning;
      allocation.controllerDeviceId = tournament.controllerDeviceId;
    }
  }

  /**
   * Update tournament status
   */
  updateStatus(tournamentId: string, status: TournamentStatus): void {
    const allocation = this.activeTournaments.get(tournamentId);
    if (allocation) {
      allocation.status = status;
    }
  }

  /**
   * Update timer running state
   */
  updateTimerState(tournamentId: string, isRunning: boolean): void {
    const allocation = this.activeTournaments.get(tournamentId);
    if (allocation) {
      allocation.isTimerRunning = isRunning;
    }
  }

  /**
   * Bind device to tournament
   */
  bindDevice(tournamentId: string, deviceId: string): void {
    const allocation = this.activeTournaments.get(tournamentId);
    if (allocation) {
      allocation.controllerDeviceId = deviceId;
    }
  }

  /**
   * Unbind device from tournament
   */
  unbindDevice(tournamentId: string): void {
    const allocation = this.activeTournaments.get(tournamentId);
    if (allocation) {
      allocation.controllerDeviceId = undefined;
    }
  }

  /**
   * Get device's tournament
   */
  getDeviceTournament(deviceId: string): TournamentAllocation | undefined {
    for (const allocation of this.activeTournaments.values()) {
      if (allocation.controllerDeviceId === deviceId) {
        return allocation;
      }
    }
    return undefined;
  }

  /**
   * Get tournament allocation
   */
  getTournament(tournamentId: string): TournamentAllocation | undefined {
    return this.activeTournaments.get(tournamentId);
  }

  /**
   * Get all active tournaments
   */
  getActiveTournaments(): TournamentAllocation[] {
    return Array.from(this.activeTournaments.values()).filter(
      (t) => t.status === 'active' || t.isTimerRunning
    );
  }

  /**
   * Get all tournaments by status
   */
  getTournamentsByStatus(status: TournamentStatus): TournamentAllocation[] {
    return Array.from(this.activeTournaments.values()).filter((t) => t.status === status);
  }

  /**
   * Get all allocations
   */
  getAllTournaments(): TournamentAllocation[] {
    return Array.from(this.activeTournaments.values());
  }

  /**
   * Release tournament (after completion/deletion)
   */
  releaseTournament(tournamentId: string): void {
    const allocation = this.activeTournaments.get(tournamentId);
    if (allocation) {
      console.log(`[TournamentAllocator] Released ${tournamentId} (${allocation.name})`);
      this.activeTournaments.delete(tournamentId);
    }
  }

  /**
   * Load active tournaments from database (for recovery after restart)
   */
  async loadActiveTournaments(): Promise<number> {
    const activeTournaments = await this.tournamentRepo.findByStatus('active');

    let loaded = 0;
    for (const tournament of activeTournaments) {
      const allocation: TournamentAllocation = {
        tournamentId: tournament.tournamentId,
        name: tournament.name,
        status: tournament.status,
        isTimerRunning: tournament.timerState.isRunning,
        allocatedAt: tournament.createdAt,
        controllerDeviceId: tournament.controllerDeviceId,
      };

      this.activeTournaments.set(tournament.tournamentId, allocation);
      loaded++;
    }

    // Also load upcoming tournaments
    const upcomingTournaments = await this.tournamentRepo.findByStatus('upcoming');
    for (const tournament of upcomingTournaments) {
      if (!this.activeTournaments.has(tournament.tournamentId)) {
        const allocation: TournamentAllocation = {
          tournamentId: tournament.tournamentId,
          name: tournament.name,
          status: tournament.status,
          isTimerRunning: false,
          allocatedAt: tournament.createdAt,
          controllerDeviceId: tournament.controllerDeviceId,
        };

        this.activeTournaments.set(tournament.tournamentId, allocation);
        loaded++;
      }
    }

    console.log(`[TournamentAllocator] Loaded ${loaded} tournaments from database`);
    return loaded;
  }

  /**
   * Get statistics
   */
  getStatistics() {
    const stats = {
      total: this.activeTournaments.size,
      upcoming: 0,
      active: 0,
      completed: 0,
      cancelled: 0,
      withRunningTimers: 0,
      withDeviceBindings: 0,
    };

    for (const allocation of this.activeTournaments.values()) {
      stats[allocation.status]++;
      if (allocation.isTimerRunning) {
        stats.withRunningTimers++;
      }
      if (allocation.controllerDeviceId) {
        stats.withDeviceBindings++;
      }
    }

    return stats;
  }

  /**
   * Clear all allocations (for testing)
   */
  clear(): void {
    this.activeTournaments.clear();
  }

  /**
   * Generate unique tournament ID
   */
  private generateId(): string {
    const uuid = crypto.randomUUID();
    // Use first 8 chars of UUID for shorter ID
    const shortId = uuid.split('-')[0];
    return `${this.config.idPrefix}-${shortId}`;
  }
}

/**
 * Create a tournament allocator instance
 */
export function createTournamentAllocator(config: AllocatorConfig): TournamentAllocator {
  return new TournamentAllocator(config);
}
