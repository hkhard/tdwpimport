/**
 * Health Monitoring Routes
 * System health, metrics, and active tournament monitoring
 *
 * Constitution Requirements:
 * - US5-A3: Automatic failover detection
 * - Health monitoring dashboard
 */

import type { FastifyInstance, FastifyRequest, FastifyReply } from 'fastify';

export interface HealthResponse {
  /** Service status */
  status: 'healthy' | 'degraded' | 'unhealthy';
  /** Current timestamp */
  timestamp: string;
  /** Uptime in seconds */
  uptime: number;
  /** Service version */
  version: string;
  /** Environment */
  environment: string;
}

export interface DetailedHealthResponse extends HealthResponse {
  /** Memory usage */
  memory: {
    heapUsed: number;
    heapTotal: number;
    rss: number;
    external: number;
  };
  /** CPU usage */
  cpu: {
    usage: number;
    loadAverage: number[];
  };
  /** Active tournaments */
  tournaments: {
    total: number;
    active: number;
    withRunningTimers: number;
  };
  /** Database status */
  database: {
    status: 'connected' | 'disconnected';
    size: number;
    walSize: number;
  };
  /** Failover status */
  failover?: {
    role: 'primary' | 'standby';
    isHealthy: boolean;
    hasFailedOver: boolean;
  };
}

/**
 * Register health monitoring routes
 */
export async function healthRoutes(fastify: FastifyInstance): Promise<void> {
  // Basic health check
  fastify.get('/health', async (request, reply) => {
    const health: HealthResponse = {
      status: 'healthy',
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      version: process.env.APP_VERSION || '1.0.0',
      environment: process.env.NODE_ENV || 'development',
    };

    return reply.send(health);
  });

  // Detailed health check (with system metrics)
  fastify.get('/health/detailed', async (request, reply) => {
    const memUsage = process.memoryUsage();
    const cpus = require('os').cpus();
    const loadAvg = require('os').loadavg();

    const health: DetailedHealthResponse = {
      status: 'healthy',
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      version: process.env.APP_VERSION || '1.0.0',
      environment: process.env.NODE_ENV || 'development',
      memory: {
        heapUsed: memUsage.heapUsed,
        heapTotal: memUsage.heapTotal,
        rss: memUsage.rss,
        external: memUsage.external,
      },
      cpu: {
        usage: process.cpuUsage().user,
        loadAverage: loadAvg,
      },
      tournaments: {
        total: 0, // Populated from tournament allocator
        active: 0,
        withRunningTimers: 0,
      },
      database: {
        status: 'connected',
        size: 0,
        walSize: 0,
      },
    };

    // Try to get additional stats from services
    try {
      const tournamentAllocator = (request as any).tournamentAllocator;
      if (tournamentAllocator) {
        const stats = tournamentAllocator.getStatistics();
        health.tournaments = {
          total: stats.total,
          active: stats.active,
          withRunningTimers: stats.withRunningTimers,
        };
      }

      const multiTimerManager = (request as any).multiTimerManager;
      if (multiTimerManager) {
        const stats = multiTimerManager.getStatistics();
        health.tournaments.total = stats.totalTimers;
        health.tournaments.active = stats.activeTimers;
        health.tournaments.withRunningTimers = stats.activeTimers;
      }

      const db = (request as any).db;
      if (db) {
        const dbStats = db.pragma('page_count', { simple: true });
        const pageSize = db.pragma('page_size', { simple: true });
        health.database.size = dbStats * pageSize;

        const walSize = db.pragma('wal_checkpoint(PASSIVE)');
        health.database.walSize = walSize as number;
      }

      const failoverDetector = (request as any).failoverDetector;
      if (failoverDetector) {
        const status = failoverDetector.getStatus();
        health.failover = {
          role: status.role,
          isHealthy: status.isHealthy,
          hasFailedOver: status.hasFailedOver,
        };
      }
    } catch (error) {
      console.error('[Health] Error getting detailed stats:', error);
      health.status = 'degraded';
    }

    // Determine overall health status
    if (health.memory.heapUsed / health.memory.heapTotal > 0.9) {
      health.status = 'degraded';
    }

    return reply.send(health);
  });

  // Heartbeat endpoint for failover detection
  fastify.get('/health/heartbeat', async (request, reply) => {
    return reply.send({
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      status: 'ok',
    });
  });

  // Ready check (for Kubernetes/liveness probes)
  fastify.get('/health/ready', async (request, reply) => {
    const isReady = true; // TODO: Check actual readiness

    if (isReady) {
      return reply.code(200).send({ status: 'ready' });
    } else {
      return reply.code(503).send({ status: 'not ready' });
    }
  });

  // Live check (for Kubernetes/liveness probes)
  fastify.get('/health/live', async (request, reply) => {
    const isAlive = true; // TODO: Check actual liveness

    if (isAlive) {
      return reply.code(200).send({ status: 'alive' });
    } else {
      return reply.code(503).send({ status: 'not alive' });
    }
  });
}
