/**
 * Tournament API Service
 * HTTP client for tournament CRUD operations
 *
 * Connects to controller with platform-aware URL configuration
 */

import type { Tournament } from '@shared/types/tournament';
import { API_BASE_URL as DEFAULT_API_BASE_URL } from '../../config/api';

export interface CreateTournamentInput {
  name: string;
  description?: string;
  startTime?: Date;
  blindScheduleId?: string;
}

export interface UpdateTournamentInput {
  name?: string;
  description?: string;
  status?: string;
  currentBlindLevel?: number;
}

export interface TournamentListFilter {
  status?: string;
  limit?: number;
  offset?: number;
}

/**
 * API configuration
 * Uses platform-aware default, can be overridden at runtime
 */
let API_BASE_URL = DEFAULT_API_BASE_URL;

/**
 * Set API base URL (for runtime configuration)
 */
export function setApiBaseUrl(url: string): void {
  API_BASE_URL = url;
}

/**
 * Get current API base URL
 */
export function getApiBaseUrl(): string {
  return API_BASE_URL;
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
 * Fetch all tournaments
 */
export async function fetchTournaments(filter: TournamentListFilter = {}): Promise<Tournament[]> {
  const params = new URLSearchParams();
  if (filter.status) params.append('status', filter.status);
  if (filter.limit) params.append('limit', String(filter.limit));
  if (filter.offset) params.append('offset', String(filter.offset));

  const queryString = params.toString();
  const url = `${API_BASE_URL}/tournaments${queryString ? `?${queryString}` : ''}`;

  const response = await fetchWithTimeout(url, undefined, 10000);
  if (!response.ok) {
    throw new Error(`Failed to fetch tournaments: ${response.status} ${response.statusText}`);
  }

  const data = await response.json();

  // Handle both array and paginated response formats
  return Array.isArray(data) ? data : (data.tournaments || data.items || []);
}

/**
 * Fetch single tournament by ID
 */
export async function fetchTournament(id: string): Promise<Tournament> {
  const response = await fetchWithTimeout(`${API_BASE_URL}/tournaments/${id}`, undefined, 10000);
  if (!response.ok) {
    throw new Error(`Failed to fetch tournament: ${response.status} ${response.statusText}`);
  }

  return response.json();
}

/**
 * Create new tournament
 */
export async function createTournament(input: CreateTournamentInput): Promise<Tournament> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        ...input,
        startTime: input.startTime || new Date().toISOString(),
      }),
    },
    15000
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(`Failed to create tournament: ${error.error || response.statusText}`);
  }

  return response.json();
}

/**
 * Update tournament
 */
export async function updateTournament(id: string, input: UpdateTournamentInput): Promise<Tournament> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments/${id}`,
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
    throw new Error(`Failed to update tournament: ${error.error || response.statusText}`);
  }

  return response.json();
}

/**
 * Delete tournament
 */
export async function deleteTournament(id: string): Promise<void> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments/${id}`,
    { method: 'DELETE' },
    10000
  );

  if (!response.ok) {
    throw new Error(`Failed to delete tournament: ${response.status} ${response.statusText}`);
  }
}

/**
 * Patch tournament status
 */
export async function patchTournamentStatus(id: string, status: string): Promise<Tournament> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments/${id}/status`,
    {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ status }),
    },
    15000
  );

  if (!response.ok) {
    throw new Error(`Failed to update tournament status: ${response.status} ${response.statusText}`);
  }

  return response.json();
}

// ============================================================================
// Player Management API
// ============================================================================

export interface AddPlayerInput {
  name: string;
  email?: string;
  phone?: string;
  startingStack: number;
  tableName?: string;
  seatNumber?: number;
}

export interface UpdatePlayerInput {
  finishPosition?: number;
  winnings?: number;
  tableName?: string;
  seatNumber?: number;
}

export interface TournamentPlayer {
  tournamentPlayerId: string;
  player: {
    playerId: string;
    name: string;
    email?: string;
    phone?: string;
  };
  startingStack: number;
  finishPosition?: number;
  winnings?: number;
  bustoutTime?: string;
  eliminations: number;
  tableName?: string;
  seatNumber?: number;
  registeredAt: string;
}

/**
 * Fetch players for a tournament
 */
export async function fetchTournamentPlayers(tournamentId: string): Promise<TournamentPlayer[]> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments/${tournamentId}/players`,
    undefined,
    10000
  );
  if (!response.ok) {
    throw new Error(`Failed to fetch players: ${response.status} ${response.statusText}`);
  }

  const data = await response.json();
  return data.players || [];
}

/**
 * Add a player to a tournament
 */
export async function addPlayerToTournament(
  tournamentId: string,
  data: AddPlayerInput
): Promise<{ tournamentPlayerId: string; playerId: string }> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments/${tournamentId}/players`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
    },
    15000
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(`Failed to add player: ${error.error || error.message || response.statusText}`);
  }

  return response.json();
}

/**
 * Update a player in a tournament
 */
export async function updateTournamentPlayer(
  tournamentId: string,
  playerId: string,
  data: UpdatePlayerInput
): Promise<{ success: boolean }> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments/${tournamentId}/players/${playerId}`,
    {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
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
 * Remove a player from a tournament
 */
export async function removePlayerFromTournament(
  tournamentId: string,
  playerId: string
): Promise<void> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments/${tournamentId}/players/${playerId}`,
    { method: 'DELETE' },
    10000
  );

  if (!response.ok) {
    throw new Error(`Failed to remove player: ${response.status} ${response.statusText}`);
  }
}

// ============================================================================
// Timer Control API
// ============================================================================

export interface TimerState {
  isRunning: boolean;
  isPaused: boolean;
  level: number;
  elapsedTime: number;
  tenths: number;
  remainingTime?: number;
  lastUpdateTime: string;
}

/**
 * Start the timer
 */
export async function startTimer(tournamentId: string): Promise<TimerState> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments/${tournamentId}/start`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({}),
    },
    10000
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(`Failed to start timer: ${error.message || response.statusText}`);
  }

  return response.json();
}

/**
 * Pause the timer
 */
export async function pauseTimer(tournamentId: string): Promise<TimerState> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments/${tournamentId}/pause`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({}),
    },
    10000
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(`Failed to pause timer: ${error.message || response.statusText}`);
  }

  return response.json();
}

/**
 * Resume the timer
 */
export async function resumeTimer(tournamentId: string): Promise<TimerState> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments/${tournamentId}/resume`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({}),
    },
    10000
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(`Failed to resume timer: ${error.message || response.statusText}`);
  }

  return response.json();
}

/**
 * Reset the timer
 */
export async function resetTimer(tournamentId: string): Promise<{ success: boolean }> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments/${tournamentId}/timer`,
    { method: 'DELETE' },
    10000
  );

  if (!response.ok) {
    throw new Error(`Failed to reset timer: ${response.status} ${response.statusText}`);
  }

  return response.json();
}

/**
 * Set blind level
 */
export async function setTimerLevel(tournamentId: string, level: number): Promise<TimerState> {
  const response = await fetchWithTimeout(
    `${API_BASE_URL}/tournaments/${tournamentId}/level`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ level }),
    },
    10000
  );

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(`Failed to set level: ${error.message || response.statusText}`);
  }

  return response.json();
}
