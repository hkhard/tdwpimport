/**
 * Mobile WebSocket Timer Listener
 * Subscribes to timer updates from server and syncs local state
 *
 * Constitution Requirements:
 * - US3-A1: <2s updates for remote viewing
 * - US3-A2: <1s updates for blind changes
 * - Graceful degradation on poor network
 */

import type { TimerState } from '@shared/types';

export interface WebSocketConfig {
  /** WebSocket URL */
  url: string;
  /** Tournament ID to subscribe to (public route) */
  tournamentId: string;
  /** Reconnection interval (ms) */
  reconnectInterval?: number;
  /** Maximum reconnection attempts */
  maxReconnectAttempts?: number;
}

export interface TimerWebSocketMessage {
  type: 'timer:update' | 'timer:start' | 'timer:pause' | 'timer:resume' | 'level:change';
  tournamentId: string;
  data: {
    state: TimerState;
    previousState?: TimerState;
  };
  timestamp: number;
}

export type TimerUpdateCallback = (state: TimerState) => void;
export type ConnectionCallback = (connected: boolean) => void;
export type ErrorCallback = (error: Error) => void;

/**
 * Timer WebSocket Client
 *
 * Connects to server WebSocket and listens for timer updates.
 * Automatically handles reconnection and state synchronization.
 */
export class TimerWebSocketClient {
  private config: Required<WebSocketConfig>;
  private ws: WebSocket | null = null;
  private isConnected: boolean = false;
  private reconnectAttempts: number = 0;
  private reconnectTimeoutId: ReturnType<typeof setTimeout> | null = null;
  private subscribers: Set<TimerUpdateCallback> = new Set();
  private connectionCallbacks: Set<ConnectionCallback> = new Set();
  private errorCallbacks: Set<ErrorCallback> = new Set();

  constructor(config: WebSocketConfig) {
    this.config = {
      url: config.url,
      tournamentId: config.tournamentId,
      reconnectInterval: config.reconnectInterval || 3000,
      maxReconnectAttempts: config.maxReconnectAttempts || 10,
    };
  }

  /**
   * Connect to WebSocket server
   */
  public connect(): void {
    if (this.ws && this.isConnected) {
      return; // Already connected
    }

    try {
      // Build WebSocket URL with tournamentId (public route, no token needed)
      const wsUrl = `${this.config.url}?tournamentId=${encodeURIComponent(this.config.tournamentId)}`;

      this.ws = new WebSocket(wsUrl);

      this.ws.onopen = this.handleOpen;
      this.ws.onmessage = this.handleMessage;
      this.ws.onclose = this.handleClose;
      this.ws.onerror = this.handleError;

      console.log('[TimerWebSocket] Connecting to', this.config.url);
    } catch (error) {
      console.error('[TimerWebSocket] Connection error:', error);
      this.notifyError(error as Error);
      this.scheduleReconnect();
    }
  }

  /**
   * Disconnect from server
   */
  public disconnect(): void {
    this.clearReconnectTimeout();

    if (this.reconnectTimeoutId) {
      clearTimeout(this.reconnectTimeoutId);
      this.reconnectTimeoutId = null;
    }

    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }

    this.isConnected = false;
    this.notifyConnectionChange(false);
  }

  /**
   * Subscribe to timer updates
   */
  public subscribe(callback: TimerUpdateCallback): () => void {
    this.subscribers.add(callback);

    // Return unsubscribe function
    return () => {
      this.subscribers.delete(callback);
    };
  }

  /**
   * Subscribe to connection state changes
   */
  public onConnectionChange(callback: ConnectionCallback): () => void {
    this.connectionCallbacks.add(callback);

    // Return unsubscribe function
    return () => {
      this.connectionCallbacks.delete(callback);
    };
  }

  /**
   * Subscribe to errors
   */
  public onError(callback: ErrorCallback): () => void {
    this.errorCallbacks.add(callback);

    // Return unsubscribe function
    return () => {
      this.errorCallbacks.delete(callback);
    };
  }

  /**
   * Send message to server
   */
  public send(type: string, data: Record<string, unknown>): void {
    if (!this.ws || !this.isConnected) {
      console.warn('[TimerWebSocket] Cannot send message: not connected');
      return;
    }

    const message = {
      type,
      data,
      timestamp: Date.now(),
    };

    this.ws.send(JSON.stringify(message));
  }

  /**
   * Subscribe to tournament updates
   */
  public subscribeToTournament(): void {
    this.send('tournament:update', {
      tournamentId: this.config.tournamentId,
    });
  }

  /**
   * Handle WebSocket open event
   */
  private handleOpen = (): void => {
    console.log('[TimerWebSocket] Connected');
    this.isConnected = true;
    this.reconnectAttempts = 0;

    // Subscribe to tournament
    this.subscribeToTournament();

    this.notifyConnectionChange(true);
  };

  /**
   * Handle WebSocket message event
   */
  private handleMessage = (event: MessageEvent): void => {
    console.log('[TimerWebSocket] Raw data:', typeof event.data, event.data);
    try {
      const message = JSON.parse(event.data) as TimerWebSocketMessage;

      // Log all received messages
      console.log('[TimerWebSocket] Received:', message.type, 'for tournament:', message.tournamentId);

      // Handle ping/pong
      if (message.type === 'ping') {
        this.send('pong', { timestamp: message.timestamp });
        return;
      }

      // Handle broadcast messages (welcome, etc.)
      if (message.type === 'broadcast') {
        console.log('[TimerWebSocket] Broadcast:', message.data?.message);
        return;
      }

      // Handle timer updates
      if (
        [
          'timer:update',
          'timer:start',
          'timer:pause',
          'timer:resume',
          'level:change',
        ].includes(message.type)
      ) {
        console.log('[TimerWebSocket] Processing timer event, my tournament:', this.config.tournamentId);
        if (message.tournamentId === this.config.tournamentId) {
          console.log('[TimerWebSocket] Match! Notifying subscribers, isPaused:', message.data.state.isPaused);
          this.notifySubscribers(message.data.state);
        } else {
          console.warn('[TimerWebSocket] Tournament ID mismatch:', message.tournamentId, '!=', this.config.tournamentId);
        }
      }
    } catch (error) {
      console.error('[TimerWebSocket] Failed to parse message:', error, 'Raw data:', event.data);
    }
  };

  /**
   * Handle WebSocket close event
   */
  private handleClose = (): void => {
    console.log('[TimerWebSocket] Disconnected');
    this.isConnected = false;
    this.ws = null;

    this.notifyConnectionChange(false);

    // Schedule reconnection
    this.scheduleReconnect();
  };

  /**
   * Handle WebSocket error event
   */
  private handleError = (error: Event): void => {
    console.error('[TimerWebSocket] WebSocket error:', error);
    this.notifyError(new Error('WebSocket connection error'));
  };

  /**
   * Schedule reconnection attempt
   */
  private scheduleReconnect(): void {
    if (this.reconnectAttempts >= this.config.maxReconnectAttempts) {
      console.error('[TimerWebSocket] Max reconnection attempts reached');
      this.notifyError(
        new Error(`Failed to reconnect after ${this.config.maxReconnectAttempts} attempts`)
      );
      return;
    }

    this.clearReconnectTimeout();

    this.reconnectTimeoutId = setTimeout(() => {
      this.reconnectAttempts++;
      console.log(
        `[TimerWebSocket] Reconnection attempt ${this.reconnectAttempts}/${this.config.maxReconnectAttempts}`
      );
      this.connect();
    }, this.config.reconnectInterval);
  }

  /**
   * Clear reconnection timeout
   */
  private clearReconnectTimeout(): void {
    if (this.reconnectTimeoutId) {
      clearTimeout(this.reconnectTimeoutId);
      this.reconnectTimeoutId = null;
    }
  }

  /**
   * Notify subscribers of timer update
   */
  private notifySubscribers(state: TimerState): void {
    for (const callback of this.subscribers) {
      try {
        callback(state);
      } catch (error) {
        console.error('[TimerWebSocket] Subscriber callback error:', error);
      }
    }
  }

  /**
   * Notify connection state subscribers
   */
  private notifyConnectionChange(connected: boolean): void {
    for (const callback of this.connectionCallbacks) {
      try {
        callback(connected);
      } catch (error) {
        console.error('[TimerWebSocket] Connection callback error:', error);
      }
    }
  }

  /**
   * Notify error subscribers
   */
  private notifyError(error: Error): void {
    for (const callback of this.errorCallbacks) {
      try {
        callback(error);
      } catch (err) {
        console.error('[TimerWebSocket] Error callback error:', err);
      }
    }
  }

  /**
   * Get connection state
   */
  public getConnected(): boolean {
    return this.isConnected;
  }
}

/**
 * Create a timer WebSocket client
 */
export function createTimerWebSocketClient(config: WebSocketConfig): TimerWebSocketClient {
  return new TimerWebSocketClient(config);
}
