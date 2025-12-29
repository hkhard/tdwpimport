/**
 * Authentication middleware
 * Validates JWT tokens from Authorization header
 */

import { FastifyRequest, FastifyReply } from 'fastify';
import jwt from 'jsonwebtoken';

const JWT_SECRET = process.env.JWT_SECRET || 'change-me-in-production';
const JWT_ALGORITHM = 'HS256';

export interface TokenPayload {
  userId: string;
  username: string;
  role: 'admin' | 'director' | 'viewer';
  iat: number;
  exp: number;
}

export interface AuthenticatedRequest extends FastifyRequest {
  user?: TokenPayload;
}

/**
 * Extract and verify JWT token from Authorization header
 * Note: This middleware should be added per-route using onRequest hook, not globally
 */
export async function authMiddleware(
  request: AuthenticatedRequest,
  reply: FastifyReply
): Promise<void> {
  const authHeader = request.headers.authorization;

  if (!authHeader) {
    return reply.status(401).send({
      error: 'Unauthorized',
      message: 'Missing Authorization header',
    });
  }

  const [type, token] = authHeader.split(' ');

  if (type !== 'Bearer' || !token) {
    return reply.status(401).send({
      error: 'Unauthorized',
      message: 'Invalid Authorization header format. Expected: Bearer <token>',
    });
  }

  try {
    const decoded = jwt.verify(token, JWT_SECRET, { algorithms: [JWT_ALGORITHM] }) as TokenPayload;
    request.user = decoded;
  } catch (error: any) {
    if (error.name === 'TokenExpiredError') {
      return reply.status(401).send({
        error: 'Unauthorized',
        message: 'Token expired',
      });
    }
    if (error.name === 'JsonWebTokenError') {
      return reply.status(401).send({
        error: 'Unauthorized',
        message: 'Invalid token',
      });
    }
    throw error;
  }
}

/**
 * Generate JWT token for user
 */
export function generateToken(payload: Omit<TokenPayload, 'iat' | 'exp'>): string {
  return jwt.sign(payload, JWT_SECRET, {
    algorithm: JWT_ALGORITHM,
    expiresIn: '24h',
  });
}

/**
 * Verify JWT token without request context
 */
export function verifyToken(token: string): TokenPayload | null {
  try {
    return jwt.verify(token, JWT_SECRET, { algorithms: [JWT_ALGORITHM] }) as TokenPayload;
  } catch {
    return null;
  }
}
