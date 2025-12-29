import { Platform } from 'react-native';

/**
 * Centralized API Configuration
 * Handles platform-specific API base URLs for development
 *
 * Platform Notes:
 * - iOS Simulator: Uses localhost (works as-is)
 * - Android Emulator: Uses 10.0.2.2 (special IP for host machine)
 * - Physical Android: User can override in Settings or via environment variable
 * - Production: Override with EXPO_PUBLIC_API_URL environment variable
 */

/**
 * Get the appropriate localhost URL based on platform
 */
const getLocalhostUrl = (): string => {
  if (Platform.OS === 'android') {
    // Android emulator uses special IP 10.0.2.2 to reach host machine's localhost
    return 'http://10.0.2.2:3000';
  }
  // iOS Simulator and web can use regular localhost
  return 'http://localhost:3000';
};

/**
 * API base URL for HTTP requests
 * Can be overridden with EXPO_PUBLIC_API_URL environment variable
 */
export const API_BASE_URL = process.env.EXPO_PUBLIC_API_URL || `${getLocalhostUrl()}/api`;

/**
 * WebSocket base URL for real-time connections
 * Can be overridden with EXPO_PUBLIC_WS_URL environment variable
 */
export const WS_BASE_URL = process.env.EXPO_PUBLIC_WS_URL || `${getLocalhostUrl().replace('http', 'ws')}/ws/public`;

/**
 * Controller base URL (without /api path)
 * Can be overridden with EXPO_PUBLIC_CONTROLLER_URL environment variable
 */
export const CONTROLLER_URL = process.env.EXPO_PUBLIC_CONTROLLER_URL || getLocalhostUrl();

/**
 * Get the current platform-aware default URL
 * Useful for settings UI to display the current default
 */
export const getDefaultApiUrl = (): string => API_BASE_URL;
