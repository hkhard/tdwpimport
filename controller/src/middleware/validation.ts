/**
 * Request validation middleware
 * Uses Zod schemas for runtime validation
 */

import { FastifyRequest, FastifyReply } from 'fastify';
import { z } from 'zod';
import { fromZodError } from 'zod-validation-error';

type ZodSchema = z.ZodTypeAny;

/**
 * Validation error response
 */
interface ValidationError {
  path: string[];
  message: string;
  code: string;
}

/**
 * Validate request body against Zod schema
 */
export function validateBody<T extends ZodSchema>(schema: T) {
  return async (request: FastifyRequest, reply: FastifyReply): Promise<void> => {
    try {
      const parsed = schema.safeParse(request.body);

      if (!parsed.success) {
        const errors: ValidationError[] = parsed.error.errors.map((err) => ({
          path: err.path.map(String),
          message: err.message,
          code: err.code,
        }));

        reply.status(400).send({
          error: 'Validation Error',
          message: 'Request body validation failed',
          details: errors,
        });
        return;
      }

      request.body = parsed.data;
    } catch (error) {
      reply.status(500).send({
        error: 'Internal Server Error',
        message: 'Validation middleware error',
      });
    }
  };
}

/**
 * Validate request query parameters against Zod schema
 */
export function validateQuery<T extends ZodSchema>(schema: T) {
  return async (request: FastifyRequest, reply: FastifyReply): Promise<void> => {
    try {
      const parsed = schema.safeParse(request.query);

      if (!parsed.success) {
        const errors: ValidationError[] = parsed.error.errors.map((err) => ({
          path: err.path.map(String),
          message: err.message,
          code: err.code,
        }));

        reply.status(400).send({
          error: 'Validation Error',
          message: 'Query parameters validation failed',
          details: errors,
        });
        return;
      }

      request.query = parsed.data;
    } catch (error) {
      reply.status(500).send({
        error: 'Internal Server Error',
        message: 'Validation middleware error',
      });
    }
  };
}

/**
 * Validate request params against Zod schema
 */
export function validateParams<T extends ZodSchema>(schema: T) {
  return async (request: FastifyRequest, reply: FastifyReply): Promise<void> => {
    try {
      const parsed = schema.safeParse(request.params);

      if (!parsed.success) {
        const errors: ValidationError[] = parsed.error.errors.map((err) => ({
          path: err.path.map(String),
          message: err.message,
          code: err.code,
        }));

        reply.status(400).send({
          error: 'Validation Error',
          message: 'URL parameters validation failed',
          details: errors,
        });
        return;
      }

      request.params = parsed.data;
    } catch (error) {
      reply.status(500).send({
        error: 'Internal Server Error',
        message: 'Validation middleware error',
      });
    }
  };
}

/**
 * Global validation middleware - checks for common issues
 */
export async function validationMiddleware(
  request: FastifyRequest,
  reply: FastifyReply
): Promise<void> {
  // Skip for websocket
  if (request.headers['upgrade'] === 'websocket') {
    return;
  }

  // Validate content-type for POST/PUT/PATCH
  if (['POST', 'PUT', 'PATCH'].includes(request.method)) {
    const contentType = request.headers['content-type'];

    if (!contentType?.includes('application/json')) {
      reply.status(415).send({
        error: 'Unsupported Media Type',
        message: 'Content-Type must be application/json',
      });
      return;
    }
  }

  // Validate request body is parsable JSON
  if (['POST', 'PUT', 'PATCH'].includes(request.method) && request.body) {
    try {
      JSON.parse(JSON.stringify(request.body));
    } catch {
      reply.status(400).send({
        error: 'Bad Request',
        message: 'Invalid JSON in request body',
      });
    }
  }
}

export { z };
