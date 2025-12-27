/**
 * Authentication service for Tournament Director controller
 * Handles password hashing, JWT token generation/validation, and user management
 */

import bcrypt from 'bcrypt';
import { randomUUID } from 'crypto';
import type { TokenPayload } from '../middleware/auth';
import { generateToken, verifyToken } from '../middleware/auth';
import { UserRepository } from '../db/repositories/UserRepository';

/**
 * User role types
 */
export type UserRole = 'admin' | 'director' | 'viewer';

/**
 * User registration data
 */
export interface RegisterData {
  username: string;
  email: string;
  password: string;
  role?: UserRole;
}

/**
 * User login data
 */
export interface LoginData {
  username: string;
  password: string;
}

/**
 * Auth response
 */
export interface AuthResponse {
  token: string;
  user: {
    userId: string;
    username: string;
    email: string;
    role: UserRole;
  };
}

/**
 * User entity (internal)
 */
interface User {
  user_id: string;
  username: string;
  email: string;
  password_hash: string;
  role: UserRole;
  created_at: string;
  updated_at: string;
}

const SALT_ROUNDS = 10;
const userRepo = new UserRepository();

/**
 * Hash a password using bcrypt
 */
export async function hashPassword(password: string): Promise<string> {
  return bcrypt.hash(password, SALT_ROUNDS);
}

/**
 * Verify a password against a hash
 */
export async function verifyPassword(password: string, hash: string): Promise<boolean> {
  return bcrypt.compare(password, hash);
}

/**
 * Register a new user
 */
export async function register(data: RegisterData): Promise<AuthResponse> {
  // Check if username exists
  const existingByUsername = userRepo.findByUsername(data.username);
  if (existingByUsername) {
    throw new Error('Username already exists');
  }

  // Check if email exists
  const existingByEmail = userRepo.findByEmail(data.email);
  if (existingByEmail) {
    throw new Error('Email already exists');
  }

  // Validate password strength
  if (data.password.length < 8) {
    throw new Error('Password must be at least 8 characters');
  }

  // Hash password
  const passwordHash = await hashPassword(data.password);

  // Create user
  const userId = randomUUID();
  const now = new Date().toISOString();

  userRepo.insert({
    userId,
    username: data.username,
    email: data.email,
    passwordHash,
    role: data.role || 'director',
    createdAt: now,
    updatedAt: now,
  });

  // Generate token
  const user = userRepo.findById(userId)!;
  const token = generateToken({
    userId: user.user_id,
    username: user.username,
    role: user.role,
  });

  return {
    token,
    user: {
      userId: user.user_id,
      username: user.username,
      email: user.email,
      role: user.role,
    },
  };
}

/**
 * Login user
 */
export async function login(data: LoginData): Promise<AuthResponse> {
  // Find user by username
  const user = userRepo.findByUsername(data.username);
  if (!user) {
    throw new Error('Invalid username or password');
  }

  // Verify password
  const isValid = await verifyPassword(data.password, user.password_hash);
  if (!isValid) {
    throw new Error('Invalid username or password');
  }

  // Generate token
  const token = generateToken({
    userId: user.user_id,
    username: user.username,
    role: user.role,
  });

  return {
    token,
    user: {
      userId: user.user_id,
      username: user.username,
      email: user.email,
      role: user.role,
    },
  };
}

/**
 * Verify token and return user info
 */
export function authenticateToken(token: string): TokenPayload | null {
  return verifyToken(token);
}

/**
 * Refresh token (generate new token for existing user)
 */
export function refreshToken(userId: string): string | null {
  const user = userRepo.findById(userId);
  if (!user) {
    return null;
  }

  return generateToken({
    userId: user.user_id,
    username: user.username,
    role: user.role,
  });
}

/**
 * Change password
 */
export async function changePassword(
  userId: string,
  oldPassword: string,
  newPassword: string
): Promise<void> {
  const user = userRepo.findById(userId);
  if (!user) {
    throw new Error('User not found');
  }

  // Verify old password
  const isValid = await verifyPassword(oldPassword, user.password_hash);
  if (!isValid) {
    throw new Error('Invalid password');
  }

  // Validate new password
  if (newPassword.length < 8) {
    throw new Error('Password must be at least 8 characters');
  }

  // Hash new password
  const passwordHash = await hashPassword(newPassword);

  // Update password
  userRepo.update(userId, {
    passwordHash,
    updatedAt: new Date().toISOString(),
  });
}
