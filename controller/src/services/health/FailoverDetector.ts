/**
 * Failover Detector Service
 * Monitors primary controller health and triggers standby promotion
 *
 * Constitution Requirements:
 * - US5-A3: Automatic failover detection
 * - US5-A4: <5s standby takeover
 */

export interface FailoverConfig {
  /** Heartbeat interval (ms) */
  heartbeatInterval?: number;
  /** Failure threshold (missed heartbeats) */
  failureThreshold?: number;
  /** Standby promotion timeout (ms) */
  promotionTimeout?: number;
  /** Primary controller URL (for standby) */
  primaryUrl?: string;
  /** This controller's role */
  role: 'primary' | 'standby' | 'auto';
}

export interface HeartbeatStatus {
  /** Controller role */
  role: 'primary' | 'standby';
  /** Whether controller is healthy */
  isHealthy: boolean;
  /** Last heartbeat timestamp */
  lastHeartbeat: Date;
  /** Consecutive failures */
  consecutiveFailures: number;
  /** Whether failover has occurred */
  hasFailedOver: boolean;
  /** Failover timestamp */
  failoverTime?: Date;
}

export type FailoverEvent = 'heartbeat_sent' | 'heartbeat_received' | 'failure_detected' | 'failover_triggered' | 'primary_recovered';

export type FailoverCallback = (event: FailoverEvent, data: unknown) => void;

/**
 * Failover Detector Service
 *
 * Monitors controller health and manages failover:
 * - Primary: Sends heartbeats to standby
 * - Standby: Monitors primary health, promotes on failure
 * - Automatic detection and recovery
 */
export class FailoverDetector {
  private config: Required<FailoverConfig>;
  private role: 'primary' | 'standby' = 'primary';
  private isHealthy: boolean = true;
  private lastHeartbeat: Date = new Date();
  private consecutiveFailures: number = 0;
  private hasFailedOver: boolean = false;
  private failoverTime?: Date;

  private heartbeatInterval: NodeJS.Timeout | null = null;
  private checkInterval: NodeJS.Timeout | null = null;
  private callbacks: Set<FailoverCallback> = new Set();

  constructor(config: FailoverConfig) {
    this.config = {
      heartbeatInterval: config.heartbeatInterval || 1000, // 1 second
      failureThreshold: config.failureThreshold || 5, // 5 missed heartbeats = 5 seconds
      promotionTimeout: config.promotionTimeout || 5000, // 5 seconds
      primaryUrl: config.primaryUrl || '',
      role: config.role,
    };

    if (config.role === 'auto') {
      // Determine role based on availability of primary
      this.role = config.primaryUrl ? 'standby' : 'primary';
    } else {
      this.role = config.role;
    }

    console.log(`[FailoverDetector] Initialized as ${this.role}`);
  }

  /**
   * Start failover detection
   */
  start(): void {
    if (this.role === 'primary') {
      this.startHeartbeatSender();
    } else {
      this.startHealthChecker();
    }
  }

  /**
   * Stop failover detection
   */
  stop(): void {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }

    if (this.checkInterval) {
      clearInterval(this.checkInterval);
      this.checkInterval = null;
    }
  }

  /**
   * Get current status
   */
  getStatus(): HeartbeatStatus {
    return {
      role: this.role,
      isHealthy: this.isHealthy,
      lastHeartbeat: this.lastHeartbeat,
      consecutiveFailures: this.consecutiveFailures,
      hasFailedOver: this.hasFailedOver,
      failoverTime: this.failoverTime,
    };
  }

  /**
   * Receive heartbeat (called by standby when receiving from primary)
   */
  receiveHeartbeat(): void {
    if (this.role !== 'standby') {
      return;
    }

    this.lastHeartbeat = new Date();
    this.consecutiveFailures = 0;
    this.isHealthy = true;

    // Notify callbacks
    this.notifyCallbacks('heartbeat_received', { timestamp: this.lastHeartbeat });

    // If we had failed over and primary recovered, notify
    if (this.hasFailedOver) {
      this.notifyCallbacks('primary_recovered', { timestamp: this.lastHeartbeat });
      // Note: Actual demotion would be manual to prevent flip-flopping
    }
  }

  /**
   * Send heartbeat (called by primary)
   */
  sendHeartbeat(): void {
    this.lastHeartbeat = new Date();
    this.notifyCallbacks('heartbeat_sent', { timestamp: this.lastHeartbeat });
  }

  /**
   * Force failover (manual trigger)
   */
  async forceFailover(): Promise<void> {
    if (this.role !== 'standby') {
      throw new Error('Only standby can force failover');
    }

    console.warn('[FailoverDetector] Manual failover triggered');
    await this.triggerFailover();
  }

  /**
   * Check if should promote to primary
   */
  shouldPromote(): boolean {
    return this.role === 'standby' && this.hasFailedOver;
  }

  /**
   * Promote to primary (after failover)
   */
  promoteToPrimary(): void {
    if (this.role !== 'standby') {
      return;
    }

    console.log('[FailoverDetector] Promoting to primary role');
    this.role = 'primary';
    this.hasFailedOver = false;

    // Start sending heartbeats
    this.stop();
    this.startHeartbeatSender();
  }

  /**
   * Subscribe to failover events
   */
  onFailoverEvent(callback: FailoverCallback): () => void {
    this.callbacks.add(callback);

    return () => {
      this.callbacks.delete(callback);
    };
  }

  /**
   * Start heartbeat sender (primary only)
   */
  private startHeartbeatSender(): void {
    this.heartbeatInterval = setInterval(() => {
      this.sendHeartbeat();

      // TODO: Actually send to standby via HTTP/WS
      // For now, just emit the event
    }, this.config.heartbeatInterval);
  }

  /**
   * Start health checker (standby only)
   */
  private startHealthChecker(): void {
    this.checkInterval = setInterval(() => {
      this.checkPrimaryHealth();
    }, this.config.heartbeatInterval);
  }

  /**
   * Check primary controller health
   */
  private async checkPrimaryHealth(): Promise<void> {
    if (!this.config.primaryUrl) {
      return;
    }

    try {
      // Try to fetch heartbeat from primary
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), this.config.heartbeatInterval);

      const response = await fetch(`${this.config.primaryUrl}/api/health/heartbeat`, {
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      if (response.ok) {
        this.receiveHeartbeat();
      } else {
        this.handleMissedHeartbeat();
      }
    } catch (error) {
      // Primary is unreachable
      this.handleMissedHeartbeat();
    }
  }

  /**
   * Handle missed heartbeat
   */
  private handleMissedHeartbeat(): void {
    this.consecutiveFailures++;

    this.notifyCallbacks('failure_detected', {
      consecutiveFailures: this.consecutiveFailures,
      threshold: this.config.failureThreshold,
    });

    // Check if we've exceeded threshold
    if (this.consecutiveFailures >= this.config.failureThreshold && !this.hasFailedOver) {
      console.warn(
        `[FailoverDetector] Primary failure detected (${this.consecutiveFailures} missed heartbeats)`
      );
      this.triggerFailover();
    }
  }

  /**
   * Trigger failover to standby
   */
  private async triggerFailover(): Promise<void> {
    console.warn('[FailoverDetector] Triggering failover to standby');
    this.hasFailedOver = true;
    this.failoverTime = new Date();
    this.isHealthy = false;

    this.notifyCallbacks('failover_triggered', {
      failoverTime: this.failoverTime,
      consecutiveFailures: this.consecutiveFailures,
    });

    // TODO: Implement actual failover logic:
    // 1. Stop health checking
    // 2. Recover timer state from database
    // 3. Start timer services
    // 4. Update DNS/load balancer
  }

  /**
   * Notify all callbacks
   */
  private notifyCallbacks(event: FailoverEvent, data: unknown): void {
    for (const callback of this.callbacks) {
      try {
        callback(event, data);
      } catch (error) {
        console.error('[FailoverDetector] Callback error:', error);
      }
    }
  }

  /**
   * Clean up
   */
  destroy(): void {
    this.stop();
    this.callbacks.clear();
  }
}

/**
 * Create a failover detector instance
 */
export function createFailoverDetector(config: FailoverConfig): FailoverDetector {
  return new FailoverDetector(config);
}
