/**
 * Authentication API routes
 * Provides user registration, login, and token management
 */

import { FastifyInstance } from 'fastify';
import { z } from 'zod';
import {
  register,
  login,
  authenticateToken,
  refreshToken,
  changePassword,
} from '../services/authService';
import { validateBody, validateParams } from '../middleware/validation';
import { UnauthorizedError, ValidationError } from '../middleware/errorHandler';
import { authMiddleware, type AuthenticatedRequest } from '../middleware/auth';

/**
 * Schemas
 */
const registerSchema = z.object({
  username: z.string().min(3).max(50),
  email: z.string().email(),
  password: z.string().min(8),
  role: z.enum(['admin', 'director', 'viewer']).optional(),
});

const loginSchema = z.object({
  username: z.string(),
  password: z.string(),
});

const changePasswordSchema = z.object({
  oldPassword: z.string(),
  newPassword: z.string().min(8),
});

const refreshTokenSchema = z.object({
  userId: z.string().uuid(),
});

export async function authRoutes(fastify: FastifyInstance): Promise<void> {
  /**
   * POST /api/auth/register
   * Register a new user
   */
  fastify.post('/register', async (request, reply) => {
    const data = registerSchema.parse(request.body);
    const result = await register(data);
    reply.status(201).send(result);
  });

  /**
   * POST /api/auth/login
   * Login user
   */
  fastify.post('/login', async (request, reply) => {
    const data = loginSchema.parse(request.body);
    const result = await login(data);
    reply.send(result);
  });

  /**
   * POST /api/auth/refresh
   * Refresh access token
   */
  fastify.post('/refresh', async (request, reply) => {
    const { userId } = refreshTokenSchema.parse(request.body);
    const token = refreshToken(userId);

    if (!token) {
      throw new UnauthorizedError('Invalid user ID');
    }

    reply.send({ token });
  });

  /**
   * POST /api/auth/change-password
   * Change user password (requires authentication)
   */
  fastify.post('/change-password', {
    onRequest: authMiddleware,
  }, async (request, reply) => {
    const authRequest = request as AuthenticatedRequest;
    const data = changePasswordSchema.parse(request.body);
    await changePassword(authRequest.user!.userId, data.oldPassword, data.newPassword);
    reply.send({ success: true });
  });

  /**
   * GET /api/auth/me
   * Get current user info
   */
  fastify.get('/me', {
    onRequest: authMiddleware,
  }, async (request, reply) => {
    const authRequest = request as AuthenticatedRequest;
    reply.send({
      userId: authRequest.user!.userId,
      username: authRequest.user!.username,
      role: authRequest.user!.role,
    });
  });

  /**
   * POST /api/auth/logout
   * Logout (client-side token removal)
   */
  fastify.post('/logout', async (request, reply) => {
    // Token removal is handled client-side
    reply.send({ success: true });
  });
}
