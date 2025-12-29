/**
 * WebSocket server for real-time tournament updates
 * Handles client connections, broadcasts, and heartbeat
 */

import { FastifyInstance } from 'fastify';
import { randomUUID } from 'crypto';
import type { WSClient, WSMessage } from './types';
import { verifyToken } from '../middleware/auth';
import { getViewerTracker } from '../services/tournament/ViewerTracker';

/**
 * Connected clients by tournament ID
 */
const clientsByTournament = new Map<string, Set<WSClient>>();

/**
 * Public viewers (unauthenticated) by tournament ID for remote viewing
 */
const publicViewersByTournament = new Map<string, Set<WSClient>>();

/**
 * All connected clients
 */
const allClients = new Map<string, WSClient>();

/**
 * All public viewers (unauthenticated)
 */
const allPublicViewers = new Map<string, WSClient>();

/**
 * Heartbeat interval (ms)
 */
const HEARTBEAT_INTERVAL = 30000; // 30 seconds

/**
 * Ping timeout (ms)
 */
const PING_TIMEOUT = 60000; // 60 seconds

/**
 * Setup WebSocket server
 */
export function setupWebSocket(server: FastifyInstance): void {
  const viewerTracker = getViewerTracker();

  // WebSocket upgrade handler
  server.register(async function (fastify) {
    fastify.get('/ws', { websocket: true }, async (socket, req) => {
      const clientId = randomUUID();

      // Extract token from query params
      const url = new URL(req.url, `http://${req.headers.host}`);
      const token = url.searchParams.get('token');

      if (!token) {
        try {
          socket.close(1008, 'No authentication token provided');
        } catch {
          // Socket may already be closed
        }
        return;
      }

      // Verify token
      const payload = verifyToken(token);
      if (!payload) {
        try {
          socket.close(1008, 'Invalid authentication token');
        } catch {
          // Socket may already be closed
        }
        return;
      }

      // Create client
      const client: WSClient = {
        socket,
        id: clientId,
        userId: payload.userId,
        username: payload.username,
        role: payload.role,
        connectedAt: Date.now(),
        lastPing: Date.now(),
        isAlive: true,
      };

      // Add to all clients
      allClients.set(clientId, client);

      console.log(`WebSocket client connected: ${client.username} (${clientId})`);

      // Send welcome message
      sendMessage(client, {
        type: 'broadcast',
        data: { message: `Connected as ${client.username}` },
        timestamp: Date.now(),
      });

      // Handle incoming messages
      socket.on('message', async (data: Buffer) => {
        try {
          const message: WSMessage = JSON.parse(data.toString());

          // Handle ping/pong
          if (message.type === 'ping') {
            client.lastPing = Date.now();
            client.isAlive = true;
            sendMessage(client, {
              type: 'pong',
              data: { timestamp: message.timestamp },
              timestamp: Date.now(),
            });
            return;
          }

          // Handle tournament subscription
          if (message.type === 'tournament:update' && message.data?.tournamentId) {
            const tournamentId = message.data.tournamentId;
            client.tournamentId = tournamentId;

            // Add to tournament group
            if (!clientsByTournament.has(tournamentId)) {
              clientsByTournament.set(tournamentId, new Set());
            }
            clientsByTournament.get(tournamentId)!.add(client);

            console.log('[WebSocket] Client', client.username, 'subscribed to', tournamentId, 'total clients:', clientsByTournament.get(tournamentId)!.size);

            // Record viewer count
            viewerTracker.recordViewerCount(
              tournamentId,
              getTournamentClientCount(tournamentId),
              getPublicViewerCount(tournamentId)
            );

            console.log(`Client ${client.username} subscribed to tournament ${tournamentId}`);
          }
        } catch (error) {
          console.error('Error handling WebSocket message:', error);
          sendMessage(client, {
            type: 'error',
            data: { message: 'Invalid message format' },
            timestamp: Date.now(),
          });
        }
      });

      // Handle connection close
      socket.on('close', () => {
        console.log(`WebSocket client disconnected: ${client.username} (${clientId})`);

        // Remove from all clients
        allClients.delete(clientId);

        // Remove from tournament group
        if (client.tournamentId && clientsByTournament.has(client.tournamentId)) {
          clientsByTournament.get(client.tournamentId)!.delete(client);
          if (clientsByTournament.get(client.tournamentId)!.size === 0) {
            clientsByTournament.delete(client.tournamentId);
          }
        }
      });

      // Handle connection error
      socket.on('error', (error: unknown) => {
        console.error(`WebSocket error for client ${clientId}:`, error);
      });
    });

    // Public WebSocket route for remote viewing (no authentication required)
    fastify.get('/ws/public', { websocket: true }, async (socket, req) => {
      const clientId = randomUUID();

      // Extract tournament ID from query params
      const url = new URL(req.url, `http://${req.headers.host}`);
      const tournamentId = url.searchParams.get('tournamentId');

      if (!tournamentId) {
        try {
          socket.close(1008, 'tournamentId query parameter is required');
        } catch {
          // Socket may already be closed
        }
        return;
      }

      // Create public viewer client (read-only, no authentication)
      const client: WSClient = {
        socket,
        id: clientId,
        role: 'viewer',
        connectedAt: Date.now(),
        lastPing: Date.now(),
        isAlive: true,
      };

      // Add to all public viewers
      allPublicViewers.set(clientId, client);

      // Add to tournament's public viewer group
      if (!publicViewersByTournament.has(tournamentId)) {
        publicViewersByTournament.set(tournamentId, new Set());
      }
      publicViewersByTournament.get(tournamentId)!.add(client);
      console.log('[Server] Added public viewer to tournament', tournamentId,
        'total viewers:', publicViewersByTournament.get(tournamentId)!.size);

      // Record viewer count
      viewerTracker.recordViewerCount(
        tournamentId,
        getTournamentClientCount(tournamentId),
        getPublicViewerCount(tournamentId)
      );

      console.log(`Public viewer connected: ${clientId} for tournament ${tournamentId}`);

      // Send welcome message
      sendMessage(client, {
        type: 'broadcast',
        data: { message: `Connected to tournament ${tournamentId} as viewer` },
        timestamp: Date.now(),
      });
      console.log('[Server] Welcome sent to viewer:', clientId);

      // Handle incoming messages
      socket.on('message', async (data: Buffer) => {
        try {
          const message: WSMessage = JSON.parse(data.toString());

          // Handle ping/pong
          if (message.type === 'ping') {
            client.lastPing = Date.now();
            client.isAlive = true;
            sendMessage(client, {
              type: 'pong',
              data: { timestamp: message.timestamp },
              timestamp: Date.now(),
            });
            return;
          }
        } catch (error) {
          console.error('Error handling WebSocket message:', error);
        }
      });

      // Handle connection close
      socket.on('close', () => {
        console.log(`Public viewer disconnected: ${clientId} from tournament ${tournamentId}`);

        // Remove from all public viewers
        allPublicViewers.delete(clientId);

        // Remove from tournament group
        if (publicViewersByTournament.has(tournamentId)) {
          publicViewersByTournament.get(tournamentId)!.delete(client);
          if (publicViewersByTournament.get(tournamentId)!.size === 0) {
            publicViewersByTournament.delete(tournamentId);
          }
        }
      });

      // Handle connection error
      socket.on('error', (error: unknown) => {
        console.error(`WebSocket error for public viewer ${clientId}:`, error);
      });
    });
  });

  // Start heartbeat interval
  startHeartbeat();
}

/**
 * Send message to specific client
 */
export function sendMessage(client: WSClient, message: WSMessage): void {
  try {
    if (client.socket && client.socket.readyState === 1) { // OPEN
      client.socket.send(JSON.stringify(message));
    }
  } catch (error) {
    console.error(`Error sending message to client ${client.id}:`, error);
  }
}

/**
 * Broadcast message to all clients
 */
export function broadcastToAll(message: WSMessage): void {
  for (const client of allClients.values()) {
    sendMessage(client, message);
  }
}

/**
 * Broadcast message to all clients subscribed to a tournament (authenticated)
 */
export function broadcastToTournament(tournamentId: string, message: WSMessage): void {
  const clients = clientsByTournament.get(tournamentId);
  const publicViewers = publicViewersByTournament.get(tournamentId);

  console.log('[Broadcast] Sending', message.type, 'to tournament', tournamentId,
    'auth clients:', clients?.size || 0,
    'public viewers:', publicViewers?.size || 0);

  if (clients) {
    for (const client of clients) {
      sendMessage(client, { ...message, tournamentId });
    }
  }

  // Also broadcast to public viewers
  if (publicViewers) {
    for (const viewer of publicViewers) {
      sendMessage(viewer, { ...message, tournamentId });
    }
  }
}

/**
 * Broadcast message to public viewers only
 */
export function broadcastToPublicViewers(tournamentId: string, message: WSMessage): void {
  const publicViewers = publicViewersByTournament.get(tournamentId);
  if (publicViewers) {
    for (const viewer of publicViewers) {
      sendMessage(viewer, { ...message, tournamentId });
    }
  }
}

/**
 * Get connected client count (authenticated)
 */
export function getClientCount(): number {
  return allClients.size;
}

/**
 * Get client count for tournament (authenticated)
 */
export function getTournamentClientCount(tournamentId: string): number {
  return clientsByTournament.get(tournamentId)?.size || 0;
}

/**
 * Get public viewer count for tournament (T106)
 */
export function getPublicViewerCount(tournamentId: string): number {
  return publicViewersByTournament.get(tournamentId)?.size || 0;
}

/**
 * Get all viewer count for tournament (authenticated + public)
 */
export function getTotalViewerCount(tournamentId: string): number {
  const authenticated = getTournamentClientCount(tournamentId);
  const publicViewers = getPublicViewerCount(tournamentId);
  return authenticated + publicViewers;
}

/**
 * Start heartbeat to detect dead connections
 */
function startHeartbeat(): void {
  setInterval(() => {
    const now = Date.now();
    const deadClients: WSClient[] = [];
    const deadViewers: WSClient[] = [];

    // Check for stale authenticated connections
    for (const client of allClients.values()) {
      if (now - client.lastPing > PING_TIMEOUT) {
        client.isAlive = false;
        deadClients.push(client);
      } else {
        // Send ping to check connection
        try {
          sendMessage(client, {
            type: 'ping',
            data: { timestamp: now },
            timestamp: now,
          });
        } catch {
          deadClients.push(client);
        }
      }
    }

    // Check for stale public viewer connections
    for (const viewer of allPublicViewers.values()) {
      if (now - viewer.lastPing > PING_TIMEOUT) {
        viewer.isAlive = false;
        deadViewers.push(viewer);
      } else {
        // Send ping to check connection
        try {
          sendMessage(viewer, {
            type: 'ping',
            data: { timestamp: now },
            timestamp: now,
          });
        } catch {
          deadViewers.push(viewer);
        }
      }
    }

    // Close dead authenticated connections
    for (const client of deadClients) {
      console.log(`Closing stale connection: ${client.username} (${client.id})`);
      try {
        client.socket.close(1000, 'Connection timeout');
      } catch {
        // Connection already closed
      }
      allClients.delete(client.id);
      if (client.tournamentId && clientsByTournament.has(client.tournamentId)) {
        clientsByTournament.get(client.tournamentId)!.delete(client);
      }
    }

    // Close dead public viewer connections
    for (const viewer of deadViewers) {
      console.log(`Closing stale viewer connection: ${viewer.id}`);
      try {
        viewer.socket.close(1000, 'Connection timeout');
      } catch {
        // Connection already closed
      }
      allPublicViewers.delete(viewer.id);
      // Remove from tournament groups
      for (const [tournamentId, viewers] of publicViewersByTournament.entries()) {
        if (viewers.has(viewer)) {
          viewers.delete(viewer);
          if (viewers.size === 0) {
            publicViewersByTournament.delete(tournamentId);
          }
          break;
        }
      }
    }

    if (deadClients.length > 0 || deadViewers.length > 0) {
      console.log(`Closed ${deadClients.length} stale authenticated connections and ${deadViewers.length} stale viewer connections`);
    }
  }, HEARTBEAT_INTERVAL);
}

export { clientsByTournament, allClients, publicViewersByTournament, allPublicViewers };
