/**
 * Fastify server setup for Tournament Director controller
 * Handles HTTP API and WebSocket connections
 */

import Fastify, { FastifyInstance } from 'fastify';
import websocket from '@fastify/websocket';
import cors from '@fastify/cors';
import staticFile from '@fastify/static';
import * as path from 'path';
import { authMiddleware } from './middleware/auth';
import { validationMiddleware } from './middleware/validation';
import { errorHandler } from './middleware/errorHandler';
import { healthRoutes } from './routes/health';
import { authRoutes } from './routes/auth';
import { tournamentRoutes } from './api/routes/tournaments';
import { timerRoutes } from './api/routes/timer';
import { playerRoutes } from './api/routes/players';
import { setupWebSocket } from './websocket/server';
import { getConnection, runMigrations } from './db/connection';
import { createTournamentService } from './services/tournament/TournamentService';
import { TournamentAllocator } from './services/tournament/TournamentAllocator';

export async function createServer(): Promise<FastifyInstance> {
  const server = Fastify({
    logger: {
      level: process.env.LOG_LEVEL || 'info',
      transport:
        process.env.NODE_ENV === 'development'
          ? { target: 'pino-pretty', options: { translateTime: 'HH:MM:ss Z', colorize: true } }
          : undefined,
    },
  });

  // Register WebSocket plugin
  await server.register(websocket);

  // Register CORS
  await server.register(cors, {
    origin: process.env.CORS_ORIGIN || '*',
    credentials: true,
  });

  // Register static file serving for remote view
  await server.register(staticFile, {
    root: path.join(__dirname, '../public'),
    prefix: '/public/', // Optional: default is '/'
  });

  // Initialize database
  const db = getConnection();
  runMigrations();

  // Initialize services
  const tournamentAllocator = new TournamentAllocator({ db });
  const tournamentService = createTournamentService(db, tournamentAllocator);

  // Decorate Fastify instance with services
  server.decorate('tournamentService', tournamentService);
  server.decorate('tournamentAllocator', tournamentAllocator);

  // Global middleware - register before routes
  server.addHook('onRequest', validationMiddleware);
  server.setErrorHandler(errorHandler);

  // Register routes
  await server.register(healthRoutes, { prefix: '/api' });
  await server.register(authRoutes, { prefix: '/api/auth' });
  await server.register(tournamentRoutes, { prefix: '/api' });
  await server.register(timerRoutes, { prefix: '/api' });
  await server.register(playerRoutes, { prefix: '/api' });

  // Setup WebSocket
  setupWebSocket(server);

  // Graceful shutdown
  const gracefulShutdown = async (signal: string) => {
    server.log.info(`${signal} received, closing server...`);
    await server.close();
    process.exit(0);
  };

  process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
  process.on('SIGINT', () => gracefulShutdown('SIGINT'));

  return server;
}
