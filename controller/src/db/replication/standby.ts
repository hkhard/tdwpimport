/**
 * Standby controller replication logic
 * Polls primary controller for WAL files and handles failover promotion
 */

import fs from 'fs';
import path from 'path';
import { getConnection } from '../connection';

export interface StandbyConfig {
  primaryControllerUrl: string;
  pollingInterval: number; // milliseconds
  localDataPath: string;
}

export class StandbyReplicationManager {
  private config: StandbyConfig;
  private pollingTimer: NodeJS.Timeout | null = null;
  private isRunning = false;
  private lastChecksum = '';

  constructor(config: StandbyConfig) {
    this.config = config;
  }

  /**
   * Start polling primary for WAL updates
   */
  start(): void {
    if (this.isRunning) {
      console.log('Standby replication already running');
      return;
    }

    this.isRunning = true;
    console.log(`Starting standby replication, polling every ${this.config.pollingInterval}ms`);

    this.poll();
  }

  /**
   * Stop polling
   */
  stop(): void {
    if (this.pollingTimer) {
      clearTimeout(this.pollingTimer);
      this.pollingTimer = null;
    }
    this.isRunning = false;
    console.log('Stopped standby replication');
  }

  /**
   * Poll primary for WAL updates
   */
  private async poll(): Promise<void> {
    if (!this.isRunning) {
      return;
    }

    try {
      const walInfo = await this.fetchWalInfo();

      if (walInfo.exists && walInfo.checksum !== this.lastChecksum) {
        console.log('WAL update detected, downloading...');
        await this.downloadWal();
        this.lastChecksum = walInfo.checksum;
        console.log('WAL synced successfully');
      }
    } catch (error) {
      console.error('Error polling primary:', error);
      // Check if primary is down - might need to promote
      await this.checkPrimaryHealth();
    }

    // Schedule next poll
    this.pollingTimer = setTimeout(() => this.poll(), this.config.pollingInterval);
  }

  /**
   * Fetch WAL info from primary controller
   */
  private async fetchWalInfo(): Promise<{
    exists: boolean;
    size: number;
    lastModified: Date;
    checksum: string;
  }> {
    const response = await fetch(`${this.config.primaryControllerUrl}/api/replication/wal-info`);

    if (!response.ok) {
      throw new Error(`Failed to fetch WAL info: ${response.statusText}`);
    }

    return await response.json() as {
      exists: boolean;
      size: number;
      lastModified: Date;
      checksum: string;
    };
  }

  /**
   * Download and apply WAL file from primary
   */
  private async downloadWal(): Promise<void> {
    const response = await fetch(`${this.config.primaryControllerUrl}/api/replication/wal-backup`);

    if (!response.ok) {
      throw new Error(`Failed to download WAL: ${response.statusText}`);
    }

    const walPath = path.join(this.config.localDataPath, 'tournaments.db-wal');
    const buffer = await response.arrayBuffer();

    // Close database connection before replacing WAL
    const db = getConnection();
    db.close();

    // Write new WAL file
    fs.writeFileSync(walPath, Buffer.from(buffer));

    // Reconnect to database
    getConnection(); // This will recreate the connection
  }

  /**
   * Check if primary controller is healthy
   */
  private async checkPrimaryHealth(): Promise<boolean> {
    try {
      const response = await fetch(`${this.config.primaryControllerUrl}/api/health`, {
        signal: AbortSignal.timeout(5000),
      });

      if (response.ok) {
        return true;
      }

      // Primary is not responding
      console.warn('Primary controller health check failed');
      return false;
    } catch {
      console.error('Primary controller unreachable');
      return false;
    }
  }

  /**
   * Promote standby to primary
   */
  async promoteToPrimary(): Promise<void> {
    console.log('Promoting standby to primary...');

    // Stop polling
    this.stop();

    // Update environment/flag to indicate primary status
    process.env.IS_STANDBY = 'false';

    // Start accepting writes
    console.log('Standby promoted to primary successfully');
  }

  /**
   * Get replication status
   */
  getStatus(): {
    isRunning: boolean;
    lastChecksum: string;
    pollingInterval: number;
    isStandby: boolean;
  } {
    return {
      isRunning: this.isRunning,
      lastChecksum: this.lastChecksum,
      pollingInterval: this.config.pollingInterval,
      isStandby: process.env.IS_STANDBY === 'true',
    };
  }
}
