import { FastifyInstance, FastifyRequest, FastifyReply } from 'fastify';
import { jsonExportService } from '../../services/export';

/**
 * Export API Routes
 * Implements US6 (T120): Export API endpoint for tournament data
 *
 * Routes:
 * - GET /tournaments/:id/export - Export single tournament as JSON
 * - GET /tournaments/export - Export all tournaments as JSON
 * - GET /tournaments/:id/snapshot - Export tournament snapshot (in-progress state)
 */
export async function exportRoutes(fastify: FastifyInstance) {
  /**
   * Export single tournament
   * Query params:
   * - includeTimerEvents: boolean (default: true)
   * - includePlayers: boolean (default: true)
   * - format: 'pretty' | 'compact' (default: 'pretty')
   */
  fastify.get<{
    Params: { id: string };
    Querystring: {
      includeTimerEvents?: string;
      includePlayers?: string;
      format?: 'pretty' | 'compact';
    };
  }>(
    '/tournaments/:id/export',
    {
      schema: {
        params: {
          type: 'object',
          properties: {
            id: { type: 'string', format: 'uuid' },
          },
          required: ['id'],
        },
        querystring: {
          type: 'object',
          properties: {
            includeTimerEvents: { type: 'string' },
            includePlayers: { type: 'string' },
            format: { type: 'string', enum: ['pretty', 'compact'] },
          },
        },
        response: {
          200: {
            type: 'object',
            description: 'Tournament export data',
          },
          404: {
            type: 'object',
            properties: {
              error: { type: 'string' },
            },
          },
        },
      },
    },
    async (request, reply) => {
      const { id } = request.params;
      const {
        includeTimerEvents = 'true',
        includePlayers = 'true',
        format = 'pretty',
      } = request.query;

      try {
        const exportData = await jsonExportService.exportTournament(id, {
          includeTimerEvents: includeTimerEvents === 'true',
          includePlayers: includePlayers === 'true',
          format,
        });

        // Validate export
        const errors = jsonExportService.validateExport(exportData);
        if (errors.length > 0) {
          return reply.status(500).send({
            error: 'Export validation failed',
            details: errors,
          });
        }

        reply.type('application/json');
        return reply.send(jsonExportService.toJson(exportData, format));
      } catch (error) {
        fastify.log.error(error);
        return reply.status(404).send({
          error: (error as Error).message,
        });
      }
    }
  );

  /**
   * Export all tournaments
   */
  fastify.get<{
    Querystring: {
      includeTimerEvents?: string;
      includePlayers?: string;
      format?: 'pretty' | 'compact';
    };
  }>(
    '/tournaments/export',
    {
      schema: {
        querystring: {
          type: 'object',
          properties: {
            includeTimerEvents: { type: 'string' },
            includePlayers: { type: 'string' },
            format: { type: 'string', enum: ['pretty', 'compact'] },
          },
        },
        response: {
          200: {
            type: 'object',
            description: 'All tournaments export data',
          },
        },
      },
    },
    async (request, reply) => {
      const {
        includeTimerEvents = 'true',
        includePlayers = 'true',
        format = 'pretty',
      } = request.query;

      try {
        const exportData = await jsonExportService.exportAllTournaments({
          includeTimerEvents: includeTimerEvents === 'true',
          includePlayers: includePlayers === 'true',
          format,
        });

        reply.type('application/json');
        return reply.send(jsonExportService.toJson(exportData, format));
      } catch (error) {
        fastify.log.error(error);
        return reply.status(500).send({
          error: 'Export failed',
          message: (error as Error).message,
        });
      }
    }
  );

  /**
   * Export tournament snapshot (in-progress state)
   * Implements US6 (T126): Snapshot export with current timer state
   */
  fastify.get<{
    Params: { id: string };
    Querystring: {
      format?: 'pretty' | 'compact';
    };
  }>(
    '/tournaments/:id/snapshot',
    {
      schema: {
        params: {
          type: 'object',
          properties: {
            id: { type: 'string', format: 'uuid' },
          },
          required: ['id'],
        },
        querystring: {
          type: 'object',
          properties: {
            format: { type: 'string', enum: ['pretty', 'compact'] },
          },
        },
        response: {
          200: {
            type: 'object',
            description: 'Tournament snapshot data',
          },
          404: {
            type: 'object',
            properties: {
              error: { type: 'string' },
            },
          },
        },
      },
    },
    async (request, reply) => {
      const { id } = request.params;
      const { format = 'pretty' } = request.query;

      try {
        const snapshotData = await jsonExportService.exportSnapshot(id);

        reply.type('application/json');
        return reply.send(jsonExportService.toJson(snapshotData, format));
      } catch (error) {
        fastify.log.error(error);
        return reply.status(404).send({
          error: (error as Error).message,
        });
      }
    }
  );
}
