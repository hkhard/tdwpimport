/**
 * Timer Screen
 * Precision tournament timer with 100ms accuracy
 */

import { useState, useEffect, useMemo, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Alert } from 'react-native';
import { useTournamentStore } from '../stores';
import * as TournamentService from '../services/tournament/TournamentService';
import type { TimerState } from '../services/api/tournamentApi';
import { API_BASE_URL, WS_BASE_URL } from '../config/api';
import { createTimerWebSocketClient, type TimerWebSocketClient } from '../services/websocket/timerWebSocket';

export function TimerScreen() {
  const { tournaments, activeTournament, setActiveTournament } = useTournamentStore();
  const [selectedTournamentId, setSelectedTournamentId] = useState<string | null>(null);

  // Timer state with polling for real-time updates
  const [timerState, setTimerState] = useState<TimerState | null>(null);
  const pollIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const wsClientRef = useRef<TimerWebSocketClient | null>(null);

  // Filter active tournaments (status === 'active')
  const activeTournaments = useMemo(
    () => tournaments.filter((t) => t.status === 'active'),
    [tournaments]
  );

  // Auto-select if exactly one active tournament
  useEffect(() => {
    if (activeTournaments.length === 1 && !selectedTournamentId) {
      const tournament = activeTournaments[0];
      setSelectedTournamentId(tournament.tournamentId);
      setActiveTournament(tournament);
    }
  }, [activeTournaments, selectedTournamentId, setActiveTournament]);

  // Get the currently displayed tournament
  const displayedTournament = useMemo(() => {
    if (selectedTournamentId) {
      return tournaments.find((t) => t.tournamentId === selectedTournamentId) || null;
    }
    return activeTournament;
  }, [selectedTournamentId, tournaments, activeTournament]);

  // Handle tournament selection
  const handleTournamentChange = (tournamentId: string) => {
    setSelectedTournamentId(tournamentId);
    const tournament = tournaments.find((t) => t.tournamentId === tournamentId);
    if (tournament) {
      setActiveTournament(tournament);
    }
  };

  // Fetch timer state from server
  const fetchTimerState = async () => {
    if (!displayedTournament?.tournamentId) return;
    try {
      const response = await fetch(
        `${API_BASE_URL}/tournaments/${displayedTournament.tournamentId}/timer`
      );
      if (response.ok) {
        const state = await response.json();
        setTimerState(state);
      }
    } catch (err) {
      console.debug('[Timer] Poll failed', err);
    }
  };

  // WebSocket connection for real-time timer updates (public route, no auth)
  useEffect(() => {
    const setupWebSocket = async () => {
      console.log('[TimerScreen] Setting up WebSocket, tournamentId:', displayedTournament?.tournamentId);

      if (!displayedTournament?.tournamentId) {
        console.warn('[TimerScreen] WebSocket setup aborted - no tournamentId');
        return;
      }

      console.log('[TimerScreen] Creating WebSocket client (public route)...');
      const client = createTimerWebSocketClient({
        url: WS_BASE_URL,
        tournamentId: displayedTournament.tournamentId,
      });

      client.subscribe((state) => {
        console.log('[TimerScreen] WebSocket callback: state updated, isPaused:', state.isPaused);
        setTimerState(state);
      });

      client.connect();
      wsClientRef.current = client;
      console.log('[TimerScreen] WebSocket client created and connecting...');
    };

    setupWebSocket();

    return () => {
      console.log('[TimerScreen] Cleanup: disconnecting WebSocket');
      wsClientRef.current?.disconnect();
    };
  }, [displayedTournament?.tournamentId]);

  // Poll for timer updates when timer is running
  useEffect(() => {
    // Initial fetch when tournament changes
    if (displayedTournament?.tournamentId) {
      fetchTimerState();
    }

    // Start/stop polling based on timer state
    if (timerState?.isRunning && !timerState?.isPaused) {
      pollIntervalRef.current = setInterval(() => {
        fetchTimerState();
      }, 1000);
    } else {
      if (pollIntervalRef.current) {
        clearInterval(pollIntervalRef.current);
        pollIntervalRef.current = null;
      }
    }

    // Cleanup on unmount
    return () => {
      if (pollIntervalRef.current) {
        clearInterval(pollIntervalRef.current);
      }
    };
  }, [displayedTournament?.tournamentId, timerState?.isRunning, timerState?.isPaused]);

  // Timer control handlers
  const handleStart = async () => {
    if (!displayedTournament?.tournamentId) return;
    try {
      if (timerState?.isRunning && !timerState?.isPaused) {
        // Timer is running, pause it
        await TournamentService.pauseTimer(displayedTournament.tournamentId);
      } else {
        // Timer is paused or stopped, start/resume it
        await TournamentService.startTimer(displayedTournament.tournamentId);
      }
      await fetchTimerState();
    } catch (err) {
      Alert.alert('Error', 'Failed to control timer');
    }
  };

  const handleReset = async () => {
    if (!displayedTournament?.tournamentId) return;
    try {
      await TournamentService.resetTimer(displayedTournament.tournamentId);
      setTimerState(null);
      await fetchTimerState();
    } catch (err) {
      Alert.alert('Error', 'Failed to reset timer');
    }
  };

  // No active tournaments
  if (activeTournaments.length === 0) {
    return (
      <View style={styles.center}>
        <Text style={styles.noTournamentText}>No active tournaments</Text>
        <Text style={styles.subtext}>Start a tournament from the Tournaments tab</Text>
      </View>
    );
  }

  // No tournament selected (but active tournaments exist)
  if (!displayedTournament) {
    return (
      <View style={styles.center}>
        <Text style={styles.noTournamentText}>Select a tournament</Text>
      </View>
    );
  }

  const formatTime = (ms: number) => {
    const totalSeconds = Math.floor(ms / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
  };

  // Tournament selector (only show if multiple active tournaments)
  const tournamentSelector = activeTournaments.length > 1 ? (
    <View style={styles.selectorContainer}>
      <Text style={styles.selectorLabel}>Tournament:</Text>
      <View style={styles.selectorButtons}>
        {activeTournaments.map((tournament) => (
          <TouchableOpacity
            key={tournament.tournamentId}
            style={[
              styles.selectorButton,
              selectedTournamentId === tournament.tournamentId && styles.selectorButtonActive,
            ]}
            onPress={() => handleTournamentChange(tournament.tournamentId)}
          >
            <Text
              style={[
                styles.selectorButtonText,
                selectedTournamentId === tournament.tournamentId && styles.selectorButtonTextActive,
              ]}
            >
              {tournament.name}
            </Text>
          </TouchableOpacity>
        ))}
      </View>
    </View>
  ) : null;

  return (
    <View style={styles.container}>
      {tournamentSelector}

      <Text style={styles.tournamentName}>{displayedTournament.name}</Text>

      <View style={styles.timerContainer}>
        <Text style={styles.timer}>
          {timerState ? formatTime(timerState.elapsedTime) : '--:--'}
        </Text>
        <Text style={styles.level}>
          Level {timerState?.level ?? displayedTournament.currentBlindLevel}
        </Text>
      </View>

      <View style={styles.controls}>
        <TouchableOpacity
          style={[
            styles.controlButton,
            !timerState?.isRunning
              ? styles.startButton
              : timerState?.isPaused
              ? styles.resumeButton
              : styles.pauseButton,
          ]}
          onPress={handleStart}
        >
          <Text style={styles.buttonText}>
            {!timerState?.isRunning
              ? 'Start'
              : timerState?.isPaused
              ? 'Resume'
              : 'Pause'}
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.controlButton, styles.resetButton]}
          onPress={handleReset}
        >
          <Text style={styles.buttonText}>Reset</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#121212',
    padding: 20,
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  noTournamentText: {
    fontSize: 20,
    fontWeight: '600',
    color: '#333',
    marginBottom: 8,
  },
  subtext: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
  },
  selectorContainer: {
    marginTop: 20,
    marginBottom: 10,
  },
  selectorLabel: {
    fontSize: 14,
    color: '#888',
    marginBottom: 8,
  },
  selectorButtons: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  selectorButton: {
    backgroundColor: '#333',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#444',
  },
  selectorButtonActive: {
    backgroundColor: '#4CAF50',
    borderColor: '#4CAF50',
  },
  selectorButtonText: {
    color: '#ccc',
    fontSize: 14,
    fontWeight: '500',
  },
  selectorButtonTextActive: {
    color: '#fff',
  },
  tournamentName: {
    fontSize: 18,
    color: '#fff',
    textAlign: 'center',
    marginTop: 40,
    marginBottom: 20,
  },
  timerContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  timer: {
    fontSize: 72,
    fontWeight: '700',
    color: '#fff',
    fontVariant: ['tabular-nums'],
  },
  level: {
    fontSize: 24,
    color: '#4CAF50',
    marginTop: 20,
  },
  controls: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 16,
    marginBottom: 40,
  },
  controlButton: {
    paddingHorizontal: 32,
    paddingVertical: 16,
    borderRadius: 12,
    minWidth: 120,
    alignItems: 'center',
  },
  startButton: {
    backgroundColor: '#4CAF50',
  },
  pauseButton: {
    backgroundColor: '#4CAF50',
  },
  resumeButton: {
    backgroundColor: '#FF9800',
  },
  resetButton: {
    backgroundColor: '#444',
  },
  buttonText: {
    color: '#fff',
    fontSize: 18,
    fontWeight: '600',
  },
});
