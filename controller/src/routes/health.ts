/**
 * Health check routes
 * Provides status endpoint for load balancers and monitoring
 */

import { FastifyInstance } from 'fastify';
import { getConnection } from '../db/connection';

export async function healthRoutes(fastify: FastifyInstance): Promise<void> {
  /**
   * GET /api/health
   * Basic health check - returns server status
   */
  fastify.get('/health', async (request, reply) => {
    return {
      status: 'ok',
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      environment: process.env.NODE_ENV || 'development',
    };
  });

  /**
   * GET /api/health/detailed
   * Detailed health check - includes database status
   */
  fastify.get('/health/detailed', async (request, reply) => {
    const health = {
      status: 'ok',
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      environment: process.env.NODE_ENV || 'development',
      checks: {
        database: 'unknown',
        replication: 'unknown',
      } as Record<string, string>,
    };

    // Check database connectivity
    try {
      const db = getConnection();
      const result = db.prepare('SELECT 1 as value').get() as { value: number };
      health.checks.database = result.value === 1 ? 'ok' : 'error';
    } catch (error) {
      health.checks.database = 'error';
      health.status = 'degraded';
    }

    // Check replication status if standby
    if (process.env.IS_STANDBY === 'true') {
      try {
        // TODO: Add replication health check when standby module is implemented
        health.checks.replication = 'syncing';
      } catch {
        health.checks.replication = 'error';
        health.status = 'degraded';
      }
    } else {
      health.checks.replication = 'primary';
    }

    // Return appropriate status code
    const statusCode = health.status === 'ok' ? 200 : 503;
    reply.status(statusCode).send(health);
  });

  /**
   * GET /api/health/ready
   * Readiness probe - checks if server is ready to accept traffic
   */
  fastify.get('/health/ready', async (request, reply) => {
    try {
      const db = getConnection();
      db.prepare('SELECT 1').get();
      return reply.status(200).send({ ready: true });
    } catch {
      return reply.status(503).send({ ready: false });
    }
  });

  /**
   * GET /api/health/live
   * Liveness probe - checks if server is alive
   */
  fastify.get('/health/live', async (request, reply) => {
    return reply.status(200).send({ alive: true });
  });
}
