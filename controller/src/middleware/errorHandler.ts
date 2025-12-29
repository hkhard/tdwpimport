/**
 * Global error handler for Fastify server
 * Provides consistent error responses across all endpoints
 */

import { FastifyError, FastifyRequest, FastifyReply } from 'fastify';

/**
 * Custom error types
 */
export class ValidationError extends Error {
  constructor(message: string, public details?: any) {
    super(message);
    this.name = 'ValidationError';
  }
}

export class NotFoundError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'NotFoundError';
  }
}

export class ConflictError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'ConflictError';
  }
}

export class UnauthorizedError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'UnauthorizedError';
  }
}

export class ForbiddenError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'ForbiddenError';
  }
}

/**
 * Error response format
 */
interface ErrorResponse {
  error: string;
  message: string;
  details?: any;
  stack?: string;
}

/**
 * Global error handler
 */
export function errorHandler(
  error: FastifyError,
  request: FastifyRequest,
  reply: FastifyReply
): void {
  const isDevelopment = process.env.NODE_ENV === 'development';

  // Log error
  request.log.error({
    error: error.message,
    stack: error.stack,
    url: request.url,
    method: request.method,
  });

  // Handle validation errors (400)
  if (error instanceof ValidationError || (error as any).validation) {
    const statusCode = 400;
    const response: ErrorResponse = {
      error: 'Bad Request',
      message: error.message || 'Request validation failed',
    };

    if (error instanceof ValidationError && (error as any).details) {
      response.details = (error as any).details;
    } else if ((error as any).validation) {
      response.details = (error as any).validation;
    }

    reply.status(statusCode).send(response);
    return;
  }

  // Handle not found errors (404)
  if (error instanceof NotFoundError || error.statusCode === 404) {
    reply.status(404).send({
      error: 'Not Found',
      message: error.message || 'Resource not found',
    });
    return;
  }

  // Handle conflict errors (409)
  if (error instanceof ConflictError || error.statusCode === 409) {
    reply.status(409).send({
      error: 'Conflict',
      message: error.message || 'Resource conflict',
    });
    return;
  }

  // Handle unauthorized errors (401)
  if (error instanceof UnauthorizedError || error.statusCode === 401) {
    reply.status(401).send({
      error: 'Unauthorized',
      message: error.message || 'Authentication required',
    });
    return;
  }

  // Handle forbidden errors (403)
  if (error instanceof ForbiddenError || error.statusCode === 403) {
    reply.status(403).send({
      error: 'Forbidden',
      message: error.message || 'Access denied',
    });
    return;
  }

  // Handle Fastify built-in errors
  if (error.statusCode) {
    reply.status(error.statusCode).send({
      error: error.name || 'Error',
      message: error.message || 'An error occurred',
      ...(isDevelopment && { stack: error.stack }),
    });
    return;
  }

  // Handle unexpected errors (500)
  reply.status(500).send({
    error: 'Internal Server Error',
    message: 'An unexpected error occurred',
    ...(isDevelopment && { stack: error.stack }),
  });
}

/**
 * Async handler wrapper to catch unhandled promise rejections
 */
export function asyncHandler<T = any>(
  fn: (request: FastifyRequest, reply: FastifyReply) => Promise<T>
) {
  return async (request: FastifyRequest, reply: FastifyReply): Promise<T> => {
    try {
      return await fn(request, reply);
    } catch (error) {
      throw error;
    }
  };
}
