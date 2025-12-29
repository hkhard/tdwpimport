/**
 * Device Binding Service
 * Tracks which device controls which tournament timer
 *
 * Constitution Requirements:
 * - US5-A1: 100+ concurrent tournaments
 * - US1-A2: Device-specific timer control
 */

import type { Database } from 'better-sqlite3';
import { TournamentRepository } from '../../db/repositories/TournamentRepository';
import { TournamentAllocator } from './TournamentAllocator';

export interface DeviceBinding {
  /** Device ID */
  deviceId: string;
  /** Tournament ID this device controls */
  tournamentId: string;
  /** Binding timestamp */
  boundAt: Date;
  /** Last heartbeat from device */
  lastHeartbeat: Date;
  /** Whether device is online */
  isOnline: boolean;
}

export interface DeviceInfo {
  deviceId: string;
  deviceName?: string;
  platform?: string;
  appVersion?: string;
}

export interface BindingConfig {
  /** Database connection */
  db?: Database;
  /** Heartbeat timeout (ms) */
  heartbeatTimeout?: number;
}

/**
 * Device Binding Service
 *
 * Manages device-to-tournament bindings:
 * - One device controls one tournament at a time
 * - Heartbeat monitoring for online status
 * - Automatic release on timeout
 * - Device takeover detection
 */
export class DeviceBindingService {
  private config: Required<Omit<BindingConfig, 'heartbeatTimeout'>> & {
    heartbeatTimeout: number;
  };
  private tournamentRepo: TournamentRepository;
  private allocator: TournamentAllocator;
  private bindings: Map<string, DeviceBinding> = new Map();
  private heartbeatInterval: NodeJS.Timeout | null = null;

  constructor(db: Database, allocator: TournamentAllocator, config: BindingConfig = {}) {
    this.config = {
      db,
      heartbeatTimeout: config.heartbeatTimeout || 30000, // 30 seconds
    };

    this.tournamentRepo = new TournamentRepository();
    this.allocator = allocator;

    // Start heartbeat monitoring
    this.startHeartbeatMonitoring();
  }

  /**
   * Bind device to tournament
   */
  async bindDevice(deviceId: string, tournamentId: string, deviceInfo?: DeviceInfo): Promise<void> {
    // Verify tournament exists
    const tournament = await this.tournamentRepo.findTournamentById(tournamentId);
    if (!tournament) {
      throw new Error(`Tournament ${tournamentId} not found`);
    }

    // Check if device is already bound to another tournament
    const existingBinding = this.bindings.get(deviceId);
    if (existingBinding && existingBinding.tournamentId !== tournamentId) {
      // Release existing binding
      await this.unbindDevice(deviceId);
    }

    // Update database
    await this.tournamentRepo.updateTournament(tournamentId, {
      controllerDeviceId: deviceId,
    });

    // Update allocator
    this.allocator.bindDevice(tournamentId, deviceId);

    // Create binding
    const binding: DeviceBinding = {
      deviceId,
      tournamentId,
      boundAt: new Date(),
      lastHeartbeat: new Date(),
      isOnline: true,
    };

    this.bindings.set(deviceId, binding);

    console.log(`[DeviceBinding] Bound device ${deviceId} to tournament ${tournamentId}`);
  }

  /**
   * Unbind device from tournament
   */
  async unbindDevice(deviceId: string): Promise<void> {
    const binding = this.bindings.get(deviceId);
    if (!binding) {
      return;
    }

    // Update database
    await this.tournamentRepo.updateTournament(binding.tournamentId, {
      controllerDeviceId: undefined,
    });

    // Update allocator
    this.allocator.unbindDevice(binding.tournamentId);

    // Remove binding
    this.bindings.delete(deviceId);

    console.log(`[DeviceBinding] Unbound device ${deviceId} from tournament ${binding.tournamentId}`);
  }

  /**
   * Get tournament for device
   */
  getTournamentForDevice(deviceId: string): string | null {
    const binding = this.bindings.get(deviceId);
    return binding?.tournamentId || null;
  }

  /**
   * Get device binding
   */
  getBinding(deviceId: string): DeviceBinding | null {
    return this.bindings.get(deviceId) || null;
  }

  /**
   * Get all bindings
   */
  getAllBindings(): DeviceBinding[] {
    return Array.from(this.bindings.values());
  }

  /**
   * Get online devices
   */
  getOnlineDevices(): string[] {
    const online: string[] = [];
    const now = Date.now();

    for (const [deviceId, binding] of this.bindings) {
      const timeSinceHeartbeat = now - binding.lastHeartbeat.getTime();
      if (timeSinceHeartbeat <= this.config.heartbeatTimeout) {
        online.push(deviceId);
      }
    }

    return online;
  }

  /**
   * Send heartbeat from device
   */
  async heartbeat(deviceId: string): Promise<void> {
    const binding = this.bindings.get(deviceId);
    if (!binding) {
      return;
    }

    binding.lastHeartbeat = new Date();
    binding.isOnline = true;
  }

  /**
   * Check if device is online
   */
  isDeviceOnline(deviceId: string): boolean {
    const binding = this.bindings.get(deviceId);
    if (!binding) {
      return false;
    }

    const now = Date.now();
    const timeSinceHeartbeat = now - binding.lastHeartbeat.getTime();
    return timeSinceHeartbeat <= this.config.heartbeatTimeout;
  }

  /**
   * Load bindings from database
   */
  async loadBindings(): Promise<number> {
    const tournaments = await this.tournamentRepo.findByStatus('active');
    let loaded = 0;

    for (const tournament of tournaments) {
      if (tournament.controllerDeviceId) {
        const binding: DeviceBinding = {
          deviceId: tournament.controllerDeviceId,
          tournamentId: tournament.tournamentId,
          boundAt: tournament.updatedAt,
          lastHeartbeat: new Date(), // Will be updated on first heartbeat
          isOnline: false, // Will be marked online on first heartbeat
        };

        this.bindings.set(tournament.controllerDeviceId, binding);
        loaded++;
      }
    }

    console.log(`[DeviceBinding] Loaded ${loaded} device bindings`);
    return loaded;
  }

  /**
   * Start heartbeat monitoring
   */
  private startHeartbeatMonitoring(): void {
    this.heartbeatInterval = setInterval(() => {
      this.checkHeartbeats();
    }, 10000); // Check every 10 seconds
  }

  /**
   * Check heartbeats and mark offline devices
   */
  private checkHeartbeats(): void {
    const now = Date.now();

    for (const [deviceId, binding] of this.bindings) {
      const timeSinceHeartbeat = now - binding.lastHeartbeat.getTime();

      if (timeSinceHeartbeat > this.config.heartbeatTimeout && binding.isOnline) {
        binding.isOnline = false;
        console.warn(
          `[DeviceBinding] Device ${deviceId} went offline (${Math.round(timeSinceHeartbeat / 1000)}s since last heartbeat)`
        );
      }
    }
  }

  /**
   * Clean up
   */
  destroy(): void {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }

    this.bindings.clear();
  }

  /**
   * Get statistics
   */
  getStatistics() {
    const online = this.getOnlineDevices().length;
    return {
      total: this.bindings.size,
      online,
      offline: this.bindings.size - online,
    };
  }
}

/**
 * Create a device binding service instance
 */
export function createDeviceBindingService(
  db: Database,
  allocator: TournamentAllocator,
  config?: BindingConfig
): DeviceBindingService {
  return new DeviceBindingService(db, allocator, config);
}
