/**
 * API-related type definitions
 */

export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  error?: ApiError;
  timestamp: Date;
}

export interface ApiError {
  code: string;
  message: string;
  details?: unknown;
  statusCode: number;
}

export interface PaginatedResponse<T> {
  items: T[];
  total: number;
  page: number;
  pageSize: number;
  hasMore: boolean;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  token: string;
  user: User;
}

export interface User {
  userId: string;
  email: string;
  role: 'admin' | 'director' | 'scorekeeper' | 'viewer';
  createdAt: Date;
  lastLogin?: Date;
}

export interface SyncUploadRequest {
  deviceId: string;
  changes: import('./sync').Change[];
  lastSyncTimestamp: number;
}

export interface SyncPullResponse {
  changes: import('./sync').Change[];
  serverTimestamp: number;
  conflicts: import('./sync').Conflict[];
}
