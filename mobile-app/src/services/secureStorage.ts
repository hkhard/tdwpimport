/**
 * Secure storage wrapper for Expo SecureStore
 * Provides token storage and retrieval for authentication
 */

import * as SecureStore from 'expo-secure-store';

/**
 * Storage keys
 */
const KEYS = {
  AUTH_TOKEN: 'auth_token',
  REFRESH_TOKEN: 'refresh_token',
  USER_ID: 'user_id',
  USERNAME: 'username',
  USER_ROLE: 'user_role',
} as const;

/**
 * Store authentication token securely
 */
export async function storeAuthToken(token: string): Promise<void> {
  await SecureStore.setItemAsync(KEYS.AUTH_TOKEN, token);
}

/**
 * Get authentication token
 */
export async function getAuthToken(): Promise<string | null> {
  return await SecureStore.getItemAsync(KEYS.AUTH_TOKEN);
}

/**
 * Remove authentication token (logout)
 */
export async function removeAuthToken(): Promise<void> {
  await SecureStore.deleteItemAsync(KEYS.AUTH_TOKEN);
}

/**
 * Store refresh token securely
 */
export async function storeRefreshToken(token: string): Promise<void> {
  await SecureStore.setItemAsync(KEYS.REFRESH_TOKEN, token);
}

/**
 * Get refresh token
 */
export async function getRefreshToken(): Promise<string | null> {
  return await SecureStore.getItemAsync(KEYS.REFRESH_TOKEN);
}

/**
 * Store user session data
 */
export async function storeUserData(params: {
  userId: string;
  username: string;
  role: string;
}): Promise<void> {
  await Promise.all([
    SecureStore.setItemAsync(KEYS.USER_ID, params.userId),
    SecureStore.setItemAsync(KEYS.USERNAME, params.username),
    SecureStore.setItemAsync(KEYS.USER_ROLE, params.role),
  ]);
}

/**
 * Get user session data
 */
export async function getUserData(): Promise<{
  userId: string | null;
  username: string | null;
  role: string | null;
}> {
  const [userId, username, role] = await Promise.all([
    SecureStore.getItemAsync(KEYS.USER_ID),
    SecureStore.getItemAsync(KEYS.USERNAME),
    SecureStore.getItemAsync(KEYS.USER_ROLE),
  ]);

  return { userId, username, role };
}

/**
 * Clear all user session data (logout)
 */
export async function clearUserData(): Promise<void> {
  await Promise.all([
    SecureStore.deleteItemAsync(KEYS.AUTH_TOKEN),
    SecureStore.deleteItemAsync(KEYS.REFRESH_TOKEN),
    SecureStore.deleteItemAsync(KEYS.USER_ID),
    SecureStore.deleteItemAsync(KEYS.USERNAME),
    SecureStore.deleteItemAsync(KEYS.USER_ROLE),
  ]);
}

/**
 * Check if user is authenticated
 */
export async function isAuthenticated(): Promise<boolean> {
  const token = await getAuthToken();
  return token !== null;
}
