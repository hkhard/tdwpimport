/**
 * Primary controller replication support
 * Manages WAL file for standby controller synchronization
 */

import fs from 'fs';
import path from 'path';
import { getConnection } from '../connection';

export class PrimaryReplicationManager {
  private dataDir: string;
  private walFilePath: string;
  private walBackupDir: string;

  constructor() {
    this.dataDir = path.dirname(process.env.DATABASE_PATH || path.join(__dirname, '../../data/tournaments.db'));
    this.walFilePath = this.dataDir + '/tournaments.db-wal';
    this.walBackupDir = path.join(this.dataDir, 'replication');
  }

  /**
   * Initialize replication backup directory
   */
  initialize(): void {
    if (!fs.existsSync(this.walBackupDir)) {
      fs.mkdirSync(this.walBackupDir, { recursive: true });
    }
  }

  /**
   * Get WAL file info for standby polling
   */
  getWalInfo(): {
    exists: boolean;
    size: number;
    lastModified: Date;
    checksum: string;
  } {
    const exists = fs.existsSync(this.walFilePath);

    if (!exists) {
      return {
        exists: false,
        size: 0,
        lastModified: new Date(),
        checksum: '',
      };
    }

    const stats = fs.statSync(this.walFilePath);
    const content = fs.readFileSync(this.walFilePath);
    const checksum = this.calculateChecksum(content);

    return {
      exists: true,
      size: stats.size,
      lastModified: stats.mtime,
      checksum,
    };
  }

  /**
   * Create WAL backup for standby to download
   */
  createWalBackup(): string {
    if (!fs.existsSync(this.walFilePath)) {
      throw new Error('WAL file does not exist');
    }

    const timestamp = Date.now();
    const backupPath = path.join(this.walBackupDir, `wal-${timestamp}.db`);

    fs.copyFileSync(this.walFilePath, backupPath);

    // Clean old backups (keep last 10)
    this.cleanOldBackups();

    return backupPath;
  }

  /**
   * Get list of available WAL backups
   */
  getWalBackups(): Array<{ name: string; path: string; created: Date }> {
    if (!fs.existsSync(this.walBackupDir)) {
      return [];
    }

    const files = fs.readdirSync(this.walBackupDir)
      .filter(f => f.startsWith('wal-') && f.endsWith('.db'))
      .map(f => {
        const filePath = path.join(this.walBackupDir, f);
        const stats = fs.statSync(filePath);
        return {
          name: f,
          path: filePath,
          created: stats.mtime,
        };
      })
      .sort((a, b) => b.created.getTime() - a.created.getTime());

    return files;
  }

  /**
   * Clean old WAL backups (keep last 10)
   */
  private cleanOldBackups(): void {
    const backups = this.getWalBackups();

    // Remove backups beyond the last 10
    for (let i = 10; i < backups.length; i++) {
      fs.unlinkSync(backups[i].path);
    }
  }

  /**
   * Calculate checksum of WAL file
   */
  private calculateChecksum(content: Buffer): string {
    const crypto = require('crypto');
    return crypto.createHash('sha256').update(content).digest('hex');
  }

  /**
   * Trigger checkpoint to reduce WAL size
   */
  triggerCheckpoint(): void {
    const db = getConnection();
    db.pragma('wal_checkpoint(PASSIVE)');
  }
}
