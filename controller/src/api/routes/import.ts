import { FastifyInstance, FastifyRequest, FastifyReply } from 'fastify';
import { jsonImportService } from '../../services/import';
import type { TournamentExport, TournamentExportData } from '../../services/export';

/**
 * Import API Routes
 * Implements US6 (T121): Import API endpoint for tournament data
 *
 * Routes:
 * - POST /import - Import tournaments from JSON
 * - POST /import/validate - Validate import data without importing
 * - POST /import/file - Import from uploaded file
 */
export async function importRoutes(fastify: FastifyInstance) {
  /**
   * Import tournaments from JSON
   * Body: JSON export data (single or batch)
   * Query params:
   * - onDuplicate: 'skip' | 'update' | 'error' (default: 'skip')
   * - dryRun: boolean (default: false)
   */
  fastify.post<{
    Querystring: {
      onDuplicate?: 'skip' | 'update' | 'error';
      dryRun?: string;
    };
    Body: any;
  }>(
    '/import',
    {
      schema: {
        querystring: {
          type: 'object',
          properties: {
            onDuplicate: { type: 'string', enum: ['skip', 'update', 'error'] },
            dryRun: { type: 'string' },
          },
        },
        body: {
          type: 'object',
          description: 'Tournament export data',
        },
        response: {
          200: {
            type: 'object',
            properties: {
              success: { type: 'boolean' },
              tournamentsImported: { type: 'number' },
              tournamentsSkipped: { type: 'number' },
              tournamentsFailed: { type: 'number' },
              errors: { type: 'array', items: { type: 'string' } },
              warnings: { type: 'array', items: { type: 'string' } },
              details: { type: 'array' },
            },
          },
          400: {
            type: 'object',
            properties: {
              error: { type: 'string' },
            },
          },
        },
      },
    },
    async (request, reply) => {
      const {
        onDuplicate = 'skip',
        dryRun = 'false',
      } = request.query;

      try {
        const result = await jsonImportService.importFromJson(request.body as string | TournamentExport | TournamentExportData, {
          onDuplicate,
          dryRun: dryRun === 'true',
        });

        const statusCode = result.success ? 200 : 400;
        return reply.status(statusCode).send(result);
      } catch (error) {
        fastify.log.error(error);
        return reply.status(500).send({
          success: false,
          error: 'Import failed',
          message: (error as Error).message,
        });
      }
    }
  );

  /**
   * Validate import data without importing
   */
  fastify.post<{
    Body: any;
  }>(
    '/import/validate',
    {
      schema: {
        body: {
          type: 'object',
          description: 'Tournament export data to validate',
        },
        response: {
          200: {
            type: 'object',
            properties: {
              valid: { type: 'boolean' },
              errors: { type: 'array', items: { type: 'string' } },
              warnings: { type: 'array', items: { type: 'string' } },
            },
          },
        },
      },
    },
    async (request, reply) => {
      try {
        const result = await jsonImportService.importFromJson(request.body as string | TournamentExport | TournamentExportData, {
          validateOnly: true,
        });

        return reply.send({
          valid: result.success,
          errors: result.errors,
          warnings: result.warnings,
        });
      } catch (error) {
        fastify.log.error(error);
        return reply.status(400).send({
          valid: false,
          errors: [(error as Error).message],
          warnings: [],
        });
      }
    }
  );

  /**
   * Import from uploaded file
   * Implements US6: File-based import
   */
  fastify.post<{
    Querystring: {
      onDuplicate?: 'skip' | 'update' | 'error';
    };
    Body: {
      file: {
        data: Buffer;
        filename: string;
        encoding: string;
        mimetype: string;
      };
    };
  }>(
    '/import/file',
    {
      schema: {
        querystring: {
          type: 'object',
          properties: {
            onDuplicate: { type: 'string', enum: ['skip', 'update', 'error'] },
          },
        },
        response: {
          200: {
            type: 'object',
            properties: {
              success: { type: 'boolean' },
              tournamentsImported: { type: 'number' },
              tournamentsSkipped: { type: 'number' },
              tournamentsFailed: { type: 'number' },
              errors: { type: 'array', items: { type: 'string' } },
              warnings: { type: 'array', items: { type: 'string' } },
              details: { type: 'array' },
            },
          },
        },
      },
    },
    async (request, reply) => {
      const { onDuplicate = 'skip' } = request.query;

      try {
        // Parse uploaded file
        const jsonData = request.body.file.data.toString('utf8');

        const result = await jsonImportService.importFromJson(jsonData, {
          onDuplicate,
        });

        const statusCode = result.success ? 200 : 400;
        return reply.status(statusCode).send(result);
      } catch (error) {
        fastify.log.error(error);
        return reply.status(500).send({
          success: false,
          error: 'File import failed',
          message: (error as Error).message,
        });
      }
    }
  );
}
