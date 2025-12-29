/**
 * Authentication hook for mobile app
 * Provides authentication state and methods
 */

import { useState, useEffect, useCallback } from 'react';
import * as SecureStore from 'expo-secure-store';
import {
  getAuthToken,
  storeAuthToken,
  removeAuthToken,
  getUserData,
  storeUserData,
  clearUserData,
} from '../services/secureStorage';
import { CONTROLLER_URL } from '../config/api';

export type UserRole = 'admin' | 'director' | 'viewer';

export interface AuthState {
  isAuthenticated: boolean;
  userId: string | null;
  username: string | null;
  role: UserRole | null;
  isLoading: boolean;
  error: string | null;
}

export interface LoginCredentials {
  username: string;
  password: string;
}

export interface RegisterData {
  username: string;
  email: string;
  password: string;
  role?: UserRole;
}

const API_BASE_URL = CONTROLLER_URL;

/**
 * Authentication hook
 */
export function useAuth() {
  const [authState, setAuthState] = useState<AuthState>({
    isAuthenticated: false,
    userId: null,
    username: null,
    role: null,
    isLoading: true,
    error: null,
  });

  /**
   * Initialize auth state from secure storage
   */
  useEffect(() => {
    loadAuthState();
  }, []);

  /**
   * Load authentication state from secure storage
   */
  const loadAuthState = async () => {
    try {
      const token = await getAuthToken();
      const userData = await getUserData();

      if (token && userData.userId) {
        setAuthState({
          isAuthenticated: true,
          userId: userData.userId,
          username: userData.username,
          role: userData.role as UserRole,
          isLoading: false,
          error: null,
        });
      } else {
        setAuthState((prev) => ({ ...prev, isLoading: false }));
      }
    } catch (error) {
      console.error('Error loading auth state:', error);
      setAuthState((prev) => ({ ...prev, isLoading: false, error: 'Failed to load auth state' }));
    }
  };

  /**
   * Login user
   */
  const login = useCallback(async (credentials: LoginCredentials): Promise<void> => {
    setAuthState((prev) => ({ ...prev, isLoading: true, error: null }));

    try {
      const response = await fetch(`${API_BASE_URL}/api/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(credentials),
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Login failed');
      }

      const data = await response.json();

      // Store token and user data
      await storeAuthToken(data.token);
      await storeUserData({
        userId: data.user.userId,
        username: data.user.username,
        role: data.user.role,
      });

      setAuthState({
        isAuthenticated: true,
        userId: data.user.userId,
        username: data.user.username,
        role: data.user.role,
        isLoading: false,
        error: null,
      });
    } catch (error: any) {
      setAuthState((prev) => ({ ...prev, isLoading: false, error: error.message }));
      throw error;
    }
  }, []);

  /**
   * Register new user
   */
  const register = useCallback(async (data: RegisterData): Promise<void> => {
    setAuthState((prev) => ({ ...prev, isLoading: true, error: null }));

    try {
      const response = await fetch(`${API_BASE_URL}/api/auth/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Registration failed');
      }

      const result = await response.json();

      // Store token and user data
      await storeAuthToken(result.token);
      await storeUserData({
        userId: result.user.userId,
        username: result.user.username,
        role: result.user.role,
      });

      setAuthState({
        isAuthenticated: true,
        userId: result.user.userId,
        username: result.user.username,
        role: result.user.role,
        isLoading: false,
        error: null,
      });
    } catch (error: any) {
      setAuthState((prev) => ({ ...prev, isLoading: false, error: error.message }));
      throw error;
    }
  }, []);

  /**
   * Logout user
   */
  const logout = useCallback(async (): Promise<void> => {
    try {
      await clearUserData();
      setAuthState({
        isAuthenticated: false,
        userId: null,
        username: null,
        role: null,
        isLoading: false,
        error: null,
      });
    } catch (error) {
      console.error('Error during logout:', error);
    }
  }, []);

  /**
   * Get auth token for API requests
   */
  const getToken = useCallback(async (): Promise<string | null> => {
    return await getAuthToken();
  }, []);

  return {
    ...authState,
    login,
    register,
    logout,
    getToken,
    refresh: loadAuthState,
  };
}
