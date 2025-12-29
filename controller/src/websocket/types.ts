/**
 * WebSocket types and interfaces
 */

import { WebSocket } from '@fastify/websocket';
import { TokenPayload } from '../middleware/auth';

/**
 * WebSocket client connection
 */
export interface WSClient {
  socket: WebSocket;
  id: string;
  userId?: string;
  username?: string;
  role?: 'admin' | 'director' | 'viewer';
  tournamentId?: string;
  connectedAt: number;
  lastPing: number;
  isAlive: boolean;
}

/**
 * WebSocket message types
 */
export type WSMessageType =
  | 'timer:update'
  | 'timer:start'
  | 'timer:pause'
  | 'timer:resume'
  | 'timer:stop'
  | 'level:change'
  | 'tournament:update'
  | 'player:update'
  | 'blind:update'
  | 'sync:start'
  | 'sync:complete'
  | 'sync:error'
  | 'broadcast'
  | 'bulk'
  | 'ping'
  | 'pong'
  | 'error';

/**
 * Base WebSocket message
 */
export interface WSMessage<T = any> {
  type: WSMessageType;
  data: T;
  timestamp: number;
  tournamentId?: string;
}

/**
 * Timer update payload
 */
export interface TimerUpdatePayload {
  tournamentId: string;
  blindLevel: number;
  isRunning: boolean;
  isPaused: boolean;
  elapsedTime: number; // centiseconds
  lastUpdateTime: string;
}

/**
 * Tournament update payload
 */
export interface TournamentUpdatePayload {
  tournamentId: string;
  status: string;
  currentBlindLevel: number;
  playerCount: number;
}

/**
 * Broadcast payload
 */
export interface BroadcastPayload {
  message: string;
  from?: string;
}
