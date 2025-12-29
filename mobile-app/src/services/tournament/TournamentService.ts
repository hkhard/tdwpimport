/**
 * Tournament Service
 * Glues API calls to the tournament store
 * Handles loading states and errors
 */

import type { Tournament } from '@shared/types/tournament';
import { useTournamentStore } from '../../stores/tournamentStore';
import * as tournamentApi from '../api/tournamentApi';

// Import types from API
import type {
  AddPlayerInput,
  UpdatePlayerInput,
  TournamentPlayer,
  TimerState,
} from '../api/tournamentApi';

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

/**
 * Load all tournaments from API
 */
export async function loadTournaments(): Promise<void> {
  const { setLoading, setError, setTournaments } = useTournamentStore.getState();

  try {
    setLoading(true);
    setError(null);

    const tournaments = await tournamentApi.fetchTournaments();
    setTournaments(tournaments);

    console.log(`[TournamentService] Loaded ${tournaments.length} tournaments`);
  } catch (error) {
    console.error('[TournamentService] Failed to load tournaments:', error);
    setError(error instanceof Error ? error.message : 'Failed to load tournaments');
  } finally {
    setLoading(false);
  }
}

/**
 * Load single tournament from API
 */
export async function loadTournament(id: string): Promise<Tournament | null> {
  const { setLoading, setError, updateTournament } = useTournamentStore.getState();

  try {
    setLoading(true);
    setError(null);

    const tournament = await tournamentApi.fetchTournament(id);
    updateTournament(id, tournament);

    console.log(`[TournamentService] Loaded tournament: ${tournament.name}`);
    return tournament;
  } catch (error) {
    console.error(`[TournamentService] Failed to load tournament ${id}:`, error);
    setError(error instanceof Error ? error.message : 'Failed to load tournament');
    return null;
  } finally {
    setLoading(false);
  }
}

/**
 * Create new tournament
 */
export async function createTournament(input: CreateTournamentInput): Promise<Tournament | null> {
  const { setLoading, setError, addTournament } = useTournamentStore.getState();

  try {
    setLoading(true);
    setError(null);

    const tournament = await tournamentApi.createTournament(input);
    addTournament(tournament);

    console.log(`[TournamentService] Created tournament: ${tournament.name}`);
    return tournament;
  } catch (error) {
    console.error('[TournamentService] Failed to create tournament:', error);
    setError(error instanceof Error ? error.message : 'Failed to create tournament');
    return null;
  } finally {
    setLoading(false);
  }
}

/**
 * Update tournament
 */
export async function updateTournamentData(id: string, input: UpdateTournamentInput): Promise<Tournament | null> {
  const { setLoading, setError } = useTournamentStore.getState();

  try {
    setLoading(true);
    setError(null);

    const tournament = await tournamentApi.updateTournament(id, input);

    // Update store
    useTournamentStore.getState().updateTournament(id, tournament);

    console.log(`[TournamentService] Updated tournament: ${id}`);
    return tournament;
  } catch (error) {
    console.error(`[TournamentService] Failed to update tournament ${id}:`, error);
    setError(error instanceof Error ? error.message : 'Failed to update tournament');
    return null;
  } finally {
    setLoading(false);
  }
}

/**
 * Delete tournament
 */
export async function deleteTournamentData(id: string): Promise<boolean> {
  const { setLoading, setError, removeTournament } = useTournamentStore.getState();

  try {
    setLoading(true);
    setError(null);

    await tournamentApi.deleteTournament(id);
    removeTournament(id);

    console.log(`[TournamentService] Deleted tournament: ${id}`);
    return true;
  } catch (error) {
    console.error(`[TournamentService] Failed to delete tournament ${id}:`, error);
    setError(error instanceof Error ? error.message : 'Failed to delete tournament');
    return false;
  } finally {
    setLoading(false);
  }
}

/**
 * Update tournament status
 */
export async function updateTournamentStatus(id: string, status: string): Promise<boolean> {
  const { setError } = useTournamentStore.getState();

  try {
    const tournament = await tournamentApi.patchTournamentStatus(id, status);

    // Update store
    useTournamentStore.getState().updateTournament(id, { status });

    console.log(`[TournamentService] Updated tournament status: ${id} -> ${status}`);
    return true;
  } catch (error) {
    console.error(`[TournamentService] Failed to update status for ${id}:`, error);
    setError(error instanceof Error ? error.message : 'Failed to update status');
    return false;
  }
}

// ============================================================================
// Player Management
// ============================================================================

/**
 * Load players for a tournament
 */
export async function loadTournamentPlayers(tournamentId: string): Promise<TournamentPlayer[]> {
  try {
    const players = await tournamentApi.fetchTournamentPlayers(tournamentId);
    console.log(`[TournamentService] Loaded ${players.length} players for tournament ${tournamentId}`);
    return players;
  } catch (error) {
    console.error(`[TournamentService] Failed to load players for tournament ${tournamentId}:`, error);
    throw error;
  }
}

/**
 * Add a player to a tournament
 */
export async function addPlayer(
  tournamentId: string,
  data: AddPlayerInput
): Promise<{ tournamentPlayerId: string; playerId: string } | null> {
  try {
    const result = await tournamentApi.addPlayerToTournament(tournamentId, data);
    console.log(`[TournamentService] Added player to tournament ${tournamentId}`);
    return result;
  } catch (error) {
    console.error(`[TournamentService] Failed to add player to tournament ${tournamentId}:`, error);
    throw error;
  }
}

/**
 * Update a player in a tournament
 */
export async function updatePlayer(
  tournamentId: string,
  playerId: string,
  data: UpdatePlayerInput
): Promise<boolean> {
  try {
    await tournamentApi.updateTournamentPlayer(tournamentId, playerId, data);
    console.log(`[TournamentService] Updated player ${playerId} in tournament ${tournamentId}`);
    return true;
  } catch (error) {
    console.error(`[TournamentService] Failed to update player ${playerId}:`, error);
    throw error;
  }
}

/**
 * Remove a player from a tournament
 */
export async function removePlayer(tournamentId: string, playerId: string): Promise<boolean> {
  try {
    await tournamentApi.removePlayerFromTournament(tournamentId, playerId);
    console.log(`[TournamentService] Removed player ${playerId} from tournament ${tournamentId}`);
    return true;
  } catch (error) {
    console.error(`[TournamentService] Failed to remove player ${playerId}:`, error);
    throw error;
  }
}

// ============================================================================
// Timer Controls
// ============================================================================

/**
 * Start the timer
 */
export async function startTimer(tournamentId: string): Promise<TimerState> {
  try {
    const state = await tournamentApi.startTimer(tournamentId);
    console.log(`[TournamentService] Started timer for tournament ${tournamentId}`);
    return state;
  } catch (error) {
    console.error(`[TournamentService] Failed to start timer for tournament ${tournamentId}:`, error);
    throw error;
  }
}

/**
 * Pause the timer
 */
export async function pauseTimer(tournamentId: string): Promise<TimerState> {
  try {
    const state = await tournamentApi.pauseTimer(tournamentId);
    console.log(`[TournamentService] Paused timer for tournament ${tournamentId}`);
    return state;
  } catch (error) {
    console.error(`[TournamentService] Failed to pause timer for tournament ${tournamentId}:`, error);
    throw error;
  }
}

/**
 * Resume the timer
 */
export async function resumeTimer(tournamentId: string): Promise<TimerState> {
  try {
    const state = await tournamentApi.resumeTimer(tournamentId);
    console.log(`[TournamentService] Resumed timer for tournament ${tournamentId}`);
    return state;
  } catch (error) {
    console.error(`[TournamentService] Failed to resume timer for tournament ${tournamentId}:`, error);
    throw error;
  }
}

/**
 * Reset the timer
 */
export async function resetTimer(tournamentId: string): Promise<boolean> {
  try {
    await tournamentApi.resetTimer(tournamentId);
    console.log(`[TournamentService] Reset timer for tournament ${tournamentId}`);
    return true;
  } catch (error) {
    console.error(`[TournamentService] Failed to reset timer for tournament ${tournamentId}:`, error);
    throw error;
  }
}

/**
 * Set blind level
 */
export async function setTimerLevel(tournamentId: string, level: number): Promise<TimerState> {
  try {
    const state = await tournamentApi.setTimerLevel(tournamentId, level);
    console.log(`[TournamentService] Set level ${level} for tournament ${tournamentId}`);
    return state;
  } catch (error) {
    console.error(`[TournamentService] Failed to set level for tournament ${tournamentId}:`, error);
    throw error;
  }
}
