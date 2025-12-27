import { TournamentRepository } from '../../db/repositories/TournamentRepository';
import { TournamentPlayerRepository } from '../../db/repositories/TournamentPlayerRepository';
import { TimerEventRepository } from '../../db/repositories/TimerEventRepository';
import { PlayerRepository } from '../../db/repositories/PlayerRepository';
import { tournamentSchema } from '../../../../shared/src/schemas';
import type { TournamentExport, TournamentExportData } from '../export/JsonExportService';

/**
 * JSON Import Service
 * Implements US6: Import tournament data from JSON exports
 *
 * Features:
 * - Import tournaments with validation
 * - Handle existing tournaments (skip, update, or error)
 * - Report import statistics
 * - Validate data integrity before import
 */
export interface ImportOptions {
  onDuplicate?: 'skip' | 'update' | 'error';
  dryRun?: boolean;
  validateOnly?: boolean;
}

export interface ImportResult {
  success: boolean;
  tournamentsImported: number;
  tournamentsSkipped: number;
  tournamentsFailed: number;
  errors: string[];
  warnings: string[];
  details: {
    tournamentId: string;
    status: 'imported' | 'skipped' | 'failed';
    error?: string;
  }[];
}

export interface ValidationResult {
  valid: boolean;
  errors: string[];
  warnings: string[];
}

export class JsonImportService {
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
   * Import tournament(s) from JSON export
   */
  async importFromJson(
    jsonData: string | TournamentExport | TournamentExportData,
    options: ImportOptions = {}
  ): Promise<ImportResult> {
    const {
      onDuplicate = 'skip',
      dryRun = false,
      validateOnly = false,
    } = options;

    const result: ImportResult = {
      success: true,
      tournamentsImported: 0,
      tournamentsSkipped: 0,
      tournamentsFailed: 0,
      errors: [],
      warnings: [],
      details: [],
    };

    try {
      // Parse JSON if string provided
      let data: TournamentExport | TournamentExportData;
      if (typeof jsonData === 'string') {
        data = JSON.parse(jsonData);
      } else {
        data = jsonData;
      }

      // Validate data structure
      const validation = this.validateImportData(data);
      if (!validation.valid) {
        result.success = false;
        result.errors.push(...validation.errors);
        return result;
      }

      if (validateOnly) {
        result.warnings.push(...validation.warnings);
        return result;
      }

      // Handle single tournament or batch export
      const tournaments = this.isTournamentExport(data)
        ? data.tournaments
        : [data];

      // Import each tournament
      for (const tournamentData of tournaments) {
        const detail = await this.importTournament(tournamentData, {
          onDuplicate,
          dryRun,
        });

        result.details.push(detail);

        if (detail.status === 'imported') {
          result.tournamentsImported++;
        } else if (detail.status === 'skipped') {
          result.tournamentsSkipped++;
        } else {
          result.tournamentsFailed++;
          result.success = false;
        }
      }

      return result;
    } catch (error) {
      result.success = false;
      result.errors.push(`Import failed: ${(error as Error).message}`);
      return result;
    }
  }

  /**
   * Import a single tournament
   */
  private async importTournament(
    data: TournamentExportData,
    options: { onDuplicate: 'skip' | 'update' | 'error'; dryRun: boolean }
  ): Promise<{ tournamentId: string; status: 'imported' | 'skipped' | 'failed'; error?: string }> {
    try {
      // Check if tournament already exists
      const existing = await this.tournamentRepo.findById(data.tournament.id);

      if (existing) {
        if (options.onDuplicate === 'skip') {
          return { tournamentId: data.tournament.id, status: 'skipped' };
        } else if (options.onDuplicate === 'error') {
          return {
            tournamentId: data.tournament.id,
            status: 'failed',
            error: 'Tournament already exists',
          };
        }
        // onDuplicate === 'update': continue with update
      }

      if (options.dryRun) {
        return { tournamentId: data.tournament.id, status: 'imported' };
      }

      // Import tournament
      if (existing && options.onDuplicate === 'update') {
        await this.tournamentRepo.update(data.tournament.id, data.tournament);
      } else {
        await this.tournamentRepo.create(data.tournament);
      }

      // Import players
      if (data.players && data.players.length > 0) {
        for (const player of data.players) {
          const playerExists = await this.playerRepo.findById(player.id);
          if (!playerExists) {
            await this.playerRepo.create(player);
          }
        }
      }

      // Import timer events
      if (data.timerEvents && data.timerEvents.length > 0) {
        for (const event of data.timerEvents) {
          const eventExists = await this.timerEventRepo.findById(event.id);
          if (!eventExists) {
            await this.timerEventRepo.create(event);
          }
        }
      }

      return { tournamentId: data.tournament.id, status: 'imported' };
    } catch (error) {
      return {
        tournamentId: data.tournament.id,
        status: 'failed',
        error: (error as Error).message,
      };
    }
  }

  /**
   * Validate import data structure and content
   */
  validateImportData(data: any): ValidationResult {
    const errors: string[] = [];
    const warnings: string[] = [];

    // Check if data is valid structure
    if (!data) {
      errors.push('Empty import data');
      return { valid: false, errors, warnings };
    }

    // Determine if single tournament or batch export
    const isBatch = this.isTournamentExport(data);
    const tournaments = isBatch ? data.tournaments : [data];

    // Validate version if batch export
    if (isBatch && !data.version) {
      warnings.push('Missing version information');
    }

    // Validate each tournament
    for (let i = 0; i < tournaments.length; i++) {
      const tournamentData = tournaments[i];

      // Validate tournament data
      if (!tournamentData.tournament) {
        errors.push(`Tournament ${i + 1}: Missing tournament data`);
        continue;
      }

      if (!tournamentData.tournament.id) {
        errors.push(`Tournament ${i + 1}: Missing tournament ID`);
      }

      if (!tournamentData.tournament.name) {
        errors.push(`Tournament ${i + 1}: Missing tournament name`);
      }

      // Validate players if present
      if (tournamentData.players) {
        for (let j = 0; j < tournamentData.players.length; j++) {
          const player = tournamentData.players[j];
          if (!player.id) {
            errors.push(`Tournament ${i + 1}, Player ${j + 1}: Missing player ID`);
          }
          if (!player.playerId) {
            errors.push(`Tournament ${i + 1}, Player ${j + 1}: Missing master player ID`);
          }
        }
      }

      // Validate timer events if present
      if (tournamentData.timerEvents) {
        for (let j = 0; j < tournamentData.timerEvents.length; j++) {
          const event = tournamentData.timerEvents[j];
          if (!event.id) {
            errors.push(`Tournament ${i + 1}, Event ${j + 1}: Missing event ID`);
          }
          if (!event.timestamp) {
            errors.push(`Tournament ${i + 1}, Event ${j + 1}: Missing timestamp`);
          }
        }
      }
    }

    return {
      valid: errors.length === 0,
      errors,
      warnings,
    };
  }

  /**
   * Type guard to check if data is TournamentExport (batch) or TournamentExportData (single)
   */
  private isTournamentExport(data: any): data is TournamentExport {
    return data.tournaments && Array.isArray(data.tournaments);
  }

  /**
   * Load JSON from file
   */
  async loadFromFile(filepath: string): Promise<string> {
    const fs = require('fs').promises;
    return await fs.readFile(filepath, 'utf8');
  }

  /**
   * Import from file
   */
  async importFromFile(
    filepath: string,
    options: ImportOptions = {}
  ): Promise<ImportResult> {
    const jsonData = await this.loadFromFile(filepath);
    return await this.importFromJson(jsonData, options);
  }
}

// Singleton instance
export const jsonImportService = new JsonImportService();
