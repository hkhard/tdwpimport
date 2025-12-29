#!/usr/bin/env ts-node

/**
 * WordPress Plugin Migration Tool
 * Implements US6: Migration path from old WordPress plugin to new system
 *
 * This script:
 * - Reads WordPress database tables
 * - Converts data to new format
 * - Exports as JSON for import into new system
 * - Shows progress for large datasets
 *
 * Usage:
 *   npm run migrate:wordpress -- --db-host=localhost --db-name=wordpress --db-user=root --db-pass=password
 */

import mysql from 'mysql2/promise';
import { writeFileSync } from 'fs';
import { join } from 'path';
import { TournamentExport } from '../../controller/src/services/export';

interface MigrationOptions {
  dbHost: string;
  dbPort?: number;
  dbName: string;
  dbUser: string;
  dbPassword: string;
  dbPrefix?: string;
  outputPath?: string;
}

interface MigrationProgress {
  tournaments: number;
  players: number;
  timerEvents: number;
  current: string;
}

class WordPressMigrator {
  private connection: mysql.Connection | null = null;
  private progress: MigrationProgress = {
    tournaments: 0,
    players: 0,
    timerEvents: 0,
    current: 'Initializing',
  };

  constructor(private options: MigrationOptions) {}

  /**
   * Main migration workflow
   */
  async migrate(): Promise<void> {
    try {
      this.log('Starting WordPress migration...');
      this.log(`Database: ${this.options.dbHost}/${this.options.dbName}`);

      // Connect to WordPress database
      await this.connect();

      // Fetch data from WordPress tables
      const wordpressData = await this.fetchWordPressData();

      // Transform to new format
      const exportData = await this.transformData(wordpressData);

      // Write to file
      const outputPath = this.options.outputPath || join(process.cwd(), 'wordpress-export.json');
      this.writeExport(exportData, outputPath);

      this.log(`\nMigration complete!`);
      this.log(`Export written to: ${outputPath}`);
      this.log(`Tournaments: ${this.progress.tournaments}`);
      this.log(`Players: ${this.progress.players}`);
      this.log(`Timer Events: ${this.progress.timerEvents}`);
    } catch (error) {
      this.log(`\nMigration failed: ${error.message}`, 'error');
      throw error;
    } finally {
      await this.disconnect();
    }
  }

  /**
   * Connect to WordPress MySQL database
   */
  private async connect(): Promise<void> {
    const { dbHost, dbPort = 3306, dbName, dbUser, dbPassword } = this.options;

    this.connection = await mysql.createConnection({
      host: dbHost,
      port: dbPort,
      user: dbUser,
      password: dbPassword,
      database: dbName,
    });

    this.log('Connected to WordPress database');
  }

  /**
   * Disconnect from database
   */
  private async disconnect(): Promise<void> {
    if (this.connection) {
      await this.connection.end();
      this.log('Disconnected from database');
    }
  }

  /**
   * Fetch all data from WordPress tables
   */
  private async fetchWordPressData() {
    const prefix = this.options.dbPrefix || 'wp_';

    this.progress.current = 'Fetching tournaments';
    this.log('Fetching tournaments...');

    const [tournaments] = await this.connection!.query(`
      SELECT
        p.ID as id,
        p.post_title as name,
        p.post_excerpt as description,
        p.post_date as createdAt,
        p.post_modified as updatedAt,
        pm_start.meta_value as startTime,
        pm_end.meta_value as endTime,
        pm_status.meta_value as status
      FROM ${prefix}posts p
      LEFT JOIN ${prefix}postmeta pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_tournament_start_time'
      LEFT JOIN ${prefix}postmeta pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = '_tournament_end_time'
      LEFT JOIN ${prefix}postmeta pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_tournament_status'
      WHERE p.post_type = 'tournament'
      ORDER BY p.post_date DESC
    `);

    this.progress.tournaments = (tournaments as any[]).length;
    this.log(`Found ${this.progress.tournaments} tournaments`);

    return {
      tournaments: tournaments as any[],
    };
  }

  /**
   * Transform WordPress data to new format
   */
  private async transformData(wordpressData: any): Promise<TournamentExport> {
    this.progress.current = 'Transforming data';
    this.log('Transforming data to new format...');

    const tournamentExports = await Promise.all(
      wordpressData.tournaments.map(async (wpTournament: any, index: number) => {
        this.progress.current = `Processing tournament ${index + 1}/${wordpressData.tournaments.length}`;

        // Get players for this tournament
        const players = await this.getTournamentPlayers(wpTournament.id);

        // Get timer events for this tournament
        const timerEvents = await this.getTimerEvents(wpTournament.id);

        this.progress.players += players.length;
        this.progress.timerEvents += timerEvents.length;

        // Transform tournament
        const tournament = {
          id: this.generateUuid(),
          name: wpTournament.name || 'Tournament',
          description: wpTournament.description || null,
          blindScheduleId: null,
          status: wpTournament.status || 'upcoming',
          startTime: wpTournament.startTime || null,
          endTime: wpTournament.endTime || null,
          currentLevel: 1,
          elapsedTime: 0,
          remainingTime: 0,
          prizePool: 0,
          payoutStructure: '{}',
          createdAt: wpTournament.createdAt || new Date().toISOString(),
          updatedAt: wpTournament.updatedAt || new Date().toISOString(),
          createdBy: null,
          deviceId: null,
        };

        return {
          tournament,
          players,
          timerEvents,
          blindSchedule: null,
          metadata: {
            exportTimestamp: new Date().toISOString(),
            source: 'wordpress-plugin',
            sourceId: wpTournament.id,
            recordCounts: {
              players: players.length,
              timerEvents: timerEvents.length,
            },
          },
        };
      })
    );

    return {
      version: '1.0.0',
      exportDate: new Date().toISOString(),
      tournaments: tournamentExports,
    };
  }

  /**
   * Get players for a tournament
   */
  private async getTournamentPlayers(tournamentId: number): Promise<any[]> {
    const prefix = this.options.dbPrefix || 'wp_';

    const [players] = await this.connection!.query(`
      SELECT
        tp.id as id,
        tp.player_id as playerId,
        tp.starting_stack as startingStack,
        tp.current_stack as currentStack,
        tp.finish_position as finishPosition,
        tp.winnings as winnings,
        tp.bustout_time as bustoutTime,
        tp.eliminations as eliminations,
        tp.registered_at as registeredAt
      FROM ${prefix}poker_tournament_players tp
      WHERE tp.tournament_id = ?
      ORDER BY tp.finish_position ASC
    `, [tournamentId]);

    return (players as any[]).map((p: any) => ({
      ...p,
      id: this.generateUuid(),
      tournamentId: tournamentId.toString(),
      updatedAt: p.registeredAt || new Date().toISOString(),
    }));
  }

  /**
   * Get timer events for a tournament
   */
  private async getTimerEvents(tournamentId: number): Promise<any[]> {
    const prefix = this.options.dbPrefix || 'wp_';

    const [events] = await this.connection!.query(`
      SELECT
        te.id as id,
        te.tournament_id as tournamentId,
        te.timestamp as timestamp,
        te.event_type as eventType,
        te.previous_state as previousState,
        te.new_state as newState,
        te.device_id as deviceId
      FROM ${prefix}poker_timer_events te
      WHERE te.tournament_id = ?
      ORDER BY te.timestamp ASC
    `, [tournamentId]);

    return (events as any[]).map((e: any) => ({
      ...e,
      id: this.generateUuid(),
      tournamentId: tournamentId.toString(),
      previousState: e.previousState ? JSON.parse(e.previousState) : null,
      newState: e.newState ? JSON.parse(e.newState) : null,
      serverTimestamp: e.timestamp,
    }));
  }

  /**
   * Write export to file
   */
  private writeExport(exportData: TournamentExport, filepath: string): void {
    const json = JSON.stringify(exportData, null, 2);
    writeFileSync(filepath, json, 'utf8');
  }

  /**
   * Generate UUID v4
   */
  private generateUuid(): string {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
      const r = (Math.random() * 16) | 0;
      const v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  /**
   * Log progress
   * Implements US6 (T125): Migration progress indicator
   */
  private log(message: string, level: 'info' | 'error' | 'warn' = 'info'): void {
    const timestamp = new Date().toISOString();
    const prefix = `[${timestamp}] [${level.toUpperCase()}]`;

    if (level === 'error') {
      console.error(`${prefix} ${message}`);
    } else {
      console.log(`${prefix} ${message}`);
    }
  }

  /**
   * Show progress
   */
  private showProgress(): void {
    console.log(`Progress:`);
    console.log(`  Tournaments: ${this.progress.tournaments}`);
    console.log(`  Players: ${this.progress.players}`);
    console.log(`  Timer Events: ${this.progress.timerEvents}`);
    console.log(`  Current: ${this.progress.current}`);
  }
}

/**
 * CLI entry point
 */
async function main() {
  const args = process.argv.slice(2);

  // Parse command line arguments
  const options: MigrationOptions = {
    dbHost: args.find((a) => a.startsWith('--db-host='))?.split('=')[1] || 'localhost',
    dbPort: args.find((a) => a.startsWith('--db-port='))?.split('=')[1]
      ? parseInt(args.find((a) => a.startsWith('--db-port='))!.split('=')[1])
      : 3306,
    dbName: args.find((a) => a.startsWith('--db-name='))?.split('=')[1] || 'wordpress',
    dbUser: args.find((a) => a.startsWith('--db-user='))?.split('=')[1] || 'root',
    dbPassword: args.find((a) => a.startsWith('--db-pass='))?.split('=')[1] || '',
    dbPrefix: args.find((a) => a.startsWith('--db-prefix='))?.split('=')[1] || 'wp_',
    outputPath: args.find((a) => a.startsWith('--output='))?.split('=')[1],
  };

  const migrator = new WordPressMigrator(options);
  await migrator.migrate();
}

// Run if executed directly
if (require.main === module) {
  main().catch(console.error);
}

export { WordPressMigrator, MigrationOptions };
