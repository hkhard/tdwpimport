import { TournamentRepository } from '../../db/repositories/TournamentRepository';
import { TournamentPlayerRepository } from '../../db/repositories/TournamentPlayerRepository';
import { TimerEventRepository } from '../../db/repositories/TimerEventRepository';
import { PlayerRepository } from '../../db/repositories/PlayerRepository';

/**
 * JSON Export Service
 * Implements US6: Data export for external systems and migration
 *
 * Features:
 * - Export complete tournament data including all related entities
 * - Export in-progress tournaments with current timer state
 * - Export individual tournaments or all tournaments
 * - Validate exported data integrity
 */
export interface ExportOptions {
  includeTimerEvents?: boolean;
  includePlayers?: boolean;
  includeSyncRecords?: boolean;
  format?: 'pretty' | 'compact';
}

export interface TournamentExport {
  version: string;
  exportDate: string;
  tournaments: TournamentExportData[];
}

export interface TournamentExportData {
  tournament: any;
  players?: any[];
  timerEvents?: any[];
  blindSchedule?: any;
  metadata: {
    exportTimestamp: string;
    recordCounts: {
      players: number;
      timerEvents: number;
    };
    snapshotTime?: string;
    timerState?: {
      status: string;
      currentLevel: number;
      elapsedTime: number;
      remainingTime: number | null;
    };
  };
}

export class JsonExportService {
  private tournamentRepo: TournamentRepository;
  private playerRepo: TournamentPlayerRepository;
  private timerEventRepo: TimerEventRepository;
  private playerMasterRepo: PlayerRepository;

  constructor() {
    this.tournamentRepo = new TournamentRepository();
    this.playerRepo = new TournamentPlayerRepository();
    this.timerEventRepo = new TimerEventRepository();
    this.playerMasterRepo = new PlayerRepository();
  }

  /**
   * Export a single tournament with all related data
   * Implements US6: JSON export including all tournament data, timer events
   */
  async exportTournament(
    tournamentId: string,
    options: ExportOptions = {}
  ): Promise<TournamentExportData> {
    const {
      includeTimerEvents = true,
      includePlayers = true,
      includeSyncRecords = false,
      format = 'pretty',
    } = options;

    // Fetch tournament data
    const tournament = await this.tournamentRepo.findById(tournamentId);
    if (!tournament) {
      throw new Error(`Tournament ${tournamentId} not found`);
    }

    // Fetch related data
    const players = includePlayers
      ? await this.playerRepo.findByTournament(tournamentId)
      : [];

    const timerEvents = includeTimerEvents
      ? await this.timerEventRepo.findByTournament(tournamentId)
      : [];

    // Get blind schedule if available
    let blindSchedule = null;
    if (tournament.blind_schedule_id) {
      // blindSchedule = await this.blindScheduleRepo.findById(tournament.blind_schedule_id);
    }

    const exportData: TournamentExportData = {
      tournament,
      players,
      timerEvents,
      blindSchedule,
      metadata: {
        exportTimestamp: new Date().toISOString(),
        recordCounts: {
          players: players.length,
          timerEvents: timerEvents.length,
        },
      },
    };

    return exportData;
  }

  /**
   * Export all tournaments
   */
  async exportAllTournaments(
    options: ExportOptions = {}
  ): Promise<TournamentExport> {
    const tournaments = await this.tournamentRepo.findAll();

    const tournamentExports = await Promise.all(
      tournaments.map((t) => this.exportTournament(t.tournament_id, options))
    );

    return {
      version: '1.0.0',
      exportDate: new Date().toISOString(),
      tournaments: tournamentExports,
    };
  }

  /**
   * Export in-progress tournament (snapshot)
   * Implements US6 (T126): Include current timer state
   */
  async exportSnapshot(tournamentId: string): Promise<TournamentExportData> {
    const tournament = await this.tournamentRepo.findById(tournamentId);
    if (!tournament) {
      throw new Error(`Tournament ${tournamentId} not found`);
    }

    // Include current timer state
    const exportData = await this.exportTournament(tournamentId, {
      includeTimerEvents: true,
      includePlayers: true,
    });

    // Add snapshot metadata
    exportData.metadata = {
      ...exportData.metadata,
      snapshotTime: new Date().toISOString(),
      timerState: {
        status: tournament.status,
        currentLevel: tournament.current_blind_level,
        elapsedTime: tournament.timer_elapsed_time,
        remainingTime: tournament.timer_remaining_time,
      },
    };

    return exportData;
  }

  /**
   * Validate exported data integrity
   * Returns list of validation errors
   */
  validateExport(exportData: TournamentExportData): string[] {
    const errors: string[] = [];

    // Validate tournament structure
    if (!exportData.tournament) {
      errors.push('Missing tournament data');
    }

    if (!exportData.tournament.tournament_id) {
      errors.push('Missing tournament ID');
    }

    // Validate player references
    if (exportData.players) {
      exportData.players.forEach((player, index) => {
        if (player.tournamentId !== exportData.tournament.id) {
          errors.push(`Player ${index} has incorrect tournamentId`);
        }
        if (!player.playerId) {
          errors.push(`Player ${index} missing playerId`);
        }
      });
    }

    // Validate timer event sequence
    if (exportData.timerEvents && exportData.timerEvents.length > 0) {
      for (let i = 0; i < exportData.timerEvents.length - 1; i++) {
        const current = new Date(exportData.timerEvents[i].timestamp);
        const next = new Date(exportData.timerEvents[i + 1].timestamp);
        if (current > next) {
          errors.push(`Timer event ${i} timestamp out of order`);
        }
      }
    }

    return errors;
  }

  /**
   * Convert export to JSON string
   */
  toJson(exportData: TournamentExport | TournamentExportData, format: 'pretty' | 'compact' = 'pretty'): string {
    return format === 'pretty'
      ? JSON.stringify(exportData, null, 2)
      : JSON.stringify(exportData);
  }

  /**
   * Save export to file
   */
  async saveToFile(exportData: TournamentExport | TournamentExportData, filepath: string): Promise<void> {
    const fs = require('fs').promises;
    const json = this.toJson(exportData, 'pretty');
    await fs.writeFile(filepath, json, 'utf8');
  }
}

// Singleton instance
export const jsonExportService = new JsonExportService();
