/**
 * Player API Service
 * HTTP client for player CRUD operations
 *
 * Connects to controller with platform-aware URL configuration
 */

import type { Player } from '@shared/types/player';
import { API_BASE_URL } from '../../config/api';

export interface CreatePlayerInput {
  name: string;
  email?: string;
  phone?: string;
}

export interface UpdatePlayerInput {
  name?: string;
  email?: string;
  phone?: string;
}

/**
 * Fetch with timeout to prevent indefinite hangs
 */
async function fetchWithTimeout(
  url: string,
  options?: RequestInit,
  timeout = 10000
): Promise<Response> {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeout);

  try {
    const response = await fetch(url, {
      ...options,
      signal: controller.signal,
    });
    clearTimeout(timeoutId);
    return response;
  } catch (error) {
    clearTimeout(timeoutId);
    if (error instanceof Error && error.name === 'AbortError') {
      throw new Error(`Request timeout after ${timeout}ms`);
    }
    throw error;
  }
}

/**
 * Fetch all players
 */
export async function fetchPlayers(): Promise<Player[]> {
  const response = await fetchWithTimeout(`${API_BASE_URL}/players`, undefined, 10000);
  if (!response.ok) {
    throw new Error(`Failed to fetch players: ${response.status} ${response.statusText}`);
  }

  const data = await response.json();
  return data.players || [];
}

/**
 * Search players by name or email
 */
export async function searchPlayers(query: string): Promise<Player[]> {
  const params = new URLSearchParams();
  if (query) params.append('search', query);

  const queryString = params.toString();
  const url = `${API_BASE_URL}/players${queryString ? `?${queryString}` : ''}`;

  const response = await fetchWithTimeout(url, undefined, 10000);
  if (!response.ok) {
    throw new Error(`Failed to search players: ${response.status} ${response.statusText}`);
  }

  const data = await response.json();
  return data.players || [];
}

/**
 * Create new player
 */
export async function createPlayer(input: CreatePlayerInput): Promise<Player> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/players`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(input),
    },
    15000
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(`Failed to create player: ${error.error || error.message || response.statusText}`);
  }

  return response.json();
}

/**
 * Update player
 */
export async function updatePlayer(playerId: string, input: UpdatePlayerInput): Promise<Player> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/players/${playerId}`,
    {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(input),
    },
    15000
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(`Failed to update player: ${error.error || error.message || response.statusText}`);
  }

  return response.json();
}

/**
 * Delete player
 */
export async function deletePlayer(playerId: string): Promise<void> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/players/${playerId}`,
    { method: 'DELETE' },
    10000
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(error.message || error.error || `Failed to delete player: ${response.status}`);
  }
}
