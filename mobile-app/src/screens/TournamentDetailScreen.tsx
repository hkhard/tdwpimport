/**
 * Tournament Detail Screen
 *
 * Displays tournament details with full management features:
 * - Tournament info (name, description, status)
 * - Edit tournament
 * - Player management (add, edit, remove)
 * - Timer controls (start, pause, resume, reset, level)
 * - Real-time timer display
 */

import { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  TextInput,
  Modal,
  Alert,
  ActivityIndicator,
  RefreshControl,
} from 'react-native';
import * as TournamentService from '../services/tournament/TournamentService';
import { usePlayerStore } from '../stores';
import type { Tournament } from '@shared/types/tournament';
import type { TournamentPlayer, TimerState } from '../services/api/tournamentApi';
import type { Player } from '@shared/types/player';
import type { CreatePlayerInput } from '../services/api/playerApi';
import type { BlindLevel } from '@shared/types/timer';
import { API_BASE_URL, WS_BASE_URL } from '../config/api';
import { createTimerWebSocketClient, type TimerWebSocketClient } from '../services/websocket/timerWebSocket';
import { BlindLevelDisplay } from '../components/BlindLevelDisplay';
import { BlindLevelsList } from '../components/BlindLevelsList';
import { blindScheduleApi } from '../services/api/blindScheduleApi';

interface Props {
  tournamentId: string;
  onBack: () => void;
}

export default function TournamentDetailScreen({ tournamentId, onBack }: Props) {
  const { players: allPlayers, searchPlayers: searchPlayersApi, createPlayer } = usePlayerStore();

  // Tournament state
  const [tournament, setTournament] = useState<Tournament | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Players state
  const [players, setPlayers] = useState<TournamentPlayer[]>([]);
  const [isLoadingPlayers, setIsLoadingPlayers] = useState(false);

  // Timer state with polling for real-time updates
  const [timerState, setTimerState] = useState<TimerState | null>(null);
  const [currentBlindLevel, setCurrentBlindLevel] = useState<BlindLevel | null>(null);
  const pollIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const wsClientRef = useRef<TimerWebSocketClient | null>(null);

  // Function to fetch just the timer state (lightweight)
  const fetchTimerState = async () => {
    if (!tournamentId) return;
    try {
      const response = await fetch(`${API_BASE_URL}/tournaments/${tournamentId}/timer`);
      if (response.ok) {
        const state = await response.json();
        setTimerState(state);
      }
    } catch (err) {
      // Silent fail - don't spam errors on poll
      console.debug('[Timer] Poll failed', err);
    }
  };

  // Function to fetch current blind level
  const fetchCurrentBlindLevel = async () => {
    if (!tournamentId) return;
    try {
      const data = await blindScheduleApi.getTournamentBlindLevel(tournamentId);
      if (data?.currentLevelInfo) {
        setCurrentBlindLevel(data.currentLevelInfo);
      }
    } catch (err) {
      console.debug('[BlindLevel] Fetch failed', err);
    }
  };

  // Edit modal state
  const [editModalVisible, setEditModalVisible] = useState(false);
  const [editName, setEditName] = useState('');
  const [editDescription, setEditDescription] = useState('');
  const [editStatus, setEditStatus] = useState('');

  // Add player modal state
  const [addPlayerModalVisible, setAddPlayerModalVisible] = useState(false);
  const [playerSearchQuery, setPlayerSearchQuery] = useState('');
  const [playerSearchResults, setPlayerSearchResults] = useState<Player[]>([]);
  const [selectedPlayer, setSelectedPlayer] = useState<Player | null>(null);
  const [newPlayerName, setNewPlayerName] = useState('');
  const [newPlayerEmail, setNewPlayerEmail] = useState('');
  const [newPlayerStartingStack, setNewPlayerStartingStack] = useState('10000');
  const [isSearchingPlayers, setIsSearchingPlayers] = useState(false);

  // Load tournament data
  const loadTournament = async () => {
    if (!tournamentId) return;

    try {
      setIsLoading(true);
      setError(null);
      const data = await TournamentService.loadTournament(tournamentId);
      setTournament(data);
      setEditName(data?.name || '');
      setEditDescription(data?.description || '');
      setEditStatus(data?.status || '');
      setTimerState(data?.timerState || null);
      // Fetch current blind level
      await fetchCurrentBlindLevel();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load tournament');
    } finally {
      setIsLoading(false);
    }
  };

  // Load players
  const loadPlayers = async () => {
    if (!tournamentId) return;

    try {
      setIsLoadingPlayers(true);
      const playerList = await TournamentService.loadTournamentPlayers(tournamentId);
      setPlayers(playerList);
    } catch (err) {
      console.error('Failed to load players:', err);
    } finally {
      setIsLoadingPlayers(false);
    }
  };

  // Initial load
  useEffect(() => {
    loadTournament();
    loadPlayers();
  }, [tournamentId]);

  // WebSocket connection for real-time timer updates (public route, no auth)
  useEffect(() => {
    const setupWebSocket = async () => {
      console.log('[TournamentDetail] Setting up WebSocket, tournamentId:', tournamentId);

      if (!tournamentId) {
        console.warn('[TournamentDetail] WebSocket setup aborted - no tournamentId');
        return;
      }

      console.log('[TournamentDetail] Creating WebSocket client (public route)...');
      const client = createTimerWebSocketClient({
        url: WS_BASE_URL,
        tournamentId,
      });

      client.subscribe((state) => {
        console.log('[TournamentDetail] WebSocket callback: state updated, isPaused:', state.isPaused);
        setTimerState(state);
      });

      client.connect();
      wsClientRef.current = client;
      console.log('[TournamentDetail] WebSocket client created and connecting...');
    };

    setupWebSocket();

    return () => {
      console.log('[TournamentDetail] Cleanup: disconnecting WebSocket');
      wsClientRef.current?.disconnect();
    };
  }, [tournamentId]);

  // Poll for timer updates when timer is running
  useEffect(() => {
    // Start polling if timer is running and not paused
    if (timerState?.isRunning && !timerState?.isPaused) {
      // Poll every 1 second for timer updates
      pollIntervalRef.current = setInterval(() => {
        fetchTimerState();
      }, 1000);
    } else {
      // Stop polling if timer is not running
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
  }, [timerState?.isRunning, timerState?.isPaused]);

  // Refresh blind level when timer level changes
  useEffect(() => {
    if (timerState?.level) {
      fetchCurrentBlindLevel();
    }
  }, [timerState?.level]);

  // Handle refresh
  const handleRefresh = async () => {
    setIsRefreshing(true);
    await loadTournament();
    await loadPlayers();
    setIsRefreshing(false);
  };

  // Handle save tournament edit
  const handleSaveEdit = async () => {
    if (!tournamentId) return;

    try {
      await TournamentService.updateTournamentData(tournamentId, {
        name: editName,
        description: editDescription || undefined,
        status: editStatus,
      });
      setEditModalVisible(false);
      await loadTournament();
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'Failed to update tournament');
    }
  };

  // Handle add player
  const handleAddPlayer = async () => {
    if (!tournamentId) return;

    // If a player is selected, add them to tournament
    if (selectedPlayer) {
      try {
        await TournamentService.addPlayer(tournamentId, {
          playerId: selectedPlayer.playerId,
          name: selectedPlayer.name,
          email: selectedPlayer.email || undefined,
          startingStack: parseInt(newPlayerStartingStack, 10),
        });
        setAddPlayerModalVisible(false);
        resetAddPlayerForm();
        await loadPlayers();
      } catch (err) {
        Alert.alert('Error', err instanceof Error ? err.message : 'Failed to add player');
      }
      return;
    }

    // Otherwise, create a new player
    if (!newPlayerName.trim()) {
      Alert.alert('Error', 'Please enter player name or search for an existing player');
      return;
    }

    try {
      // First create the player globally
      const createdPlayer = await createPlayer({
        name: newPlayerName.trim(),
        email: newPlayerEmail.trim() || undefined,
        phone: undefined,
      } as CreatePlayerInput);

      // Then add them to the tournament
      await TournamentService.addPlayer(tournamentId, {
        name: createdPlayer.name,
        email: createdPlayer.email || undefined,
        startingStack: parseInt(newPlayerStartingStack, 10),
      });

      setAddPlayerModalVisible(false);
      resetAddPlayerForm();
      await loadPlayers();
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'Failed to add player');
    }
  };

  // Reset add player form
  const resetAddPlayerForm = () => {
    setPlayerSearchQuery('');
    setPlayerSearchResults([]);
    setSelectedPlayer(null);
    setNewPlayerName('');
    setNewPlayerEmail('');
    setNewPlayerStartingStack('10000');
    setIsSearchingPlayers(false);
  };

  // Handle player search
  const handlePlayerSearch = async (query: string) => {
    setPlayerSearchQuery(query);

    if (!query.trim()) {
      setPlayerSearchResults([]);
      setSelectedPlayer(null);
      return;
    }

    setIsSearchingPlayers(true);
    try {
      const results = await searchPlayersApi(query);
      setPlayerSearchResults(results);
      setSelectedPlayer(null);
    } catch (err) {
      console.error('Failed to search players:', err);
    } finally {
      setIsSearchingPlayers(false);
    }
  };

  // Handle selecting a player from search results
  const handleSelectPlayer = (player: Player) => {
    setSelectedPlayer(player);
    setNewPlayerName(player.name);
    setNewPlayerEmail(player.email || '');
    setPlayerSearchQuery('');
    setPlayerSearchResults([]);
  };

  // Handle remove player
  const handleRemovePlayer = async (playerId: string, playerName: string) => {
    if (!tournamentId) return;

    Alert.alert(
      'Remove Player',
      `Remove ${playerName} from tournament?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Remove',
          style: 'destructive',
          onPress: async () => {
            try {
              await TournamentService.removePlayer(tournamentId, playerId);
              await loadPlayers();
            } catch (err) {
              Alert.alert('Error', err instanceof Error ? err.message : 'Failed to remove player');
            }
          },
        },
      ]
    );
  };

  // Handle timer controls
  const handleStartTimer = async () => {
    if (!tournamentId) return;
    try {
      const state = await TournamentService.startTimer(tournamentId);
      setTimerState(state);
      // Fetch immediately after starting to get the latest state
      fetchTimerState();
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'Failed to start timer');
    }
  };

  const handlePauseTimer = async () => {
    if (!tournamentId) return;
    try {
      const state = await TournamentService.pauseTimer(tournamentId);
      setTimerState(state);
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'Failed to pause timer');
    }
  };

  const handleResumeTimer = async () => {
    if (!tournamentId) return;
    try {
      const state = await TournamentService.resumeTimer(tournamentId);
      setTimerState(state);
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'Failed to resume timer');
    }
  };

  const handleResetTimer = async () => {
    if (!tournamentId) return;
    try {
      await TournamentService.resetTimer(tournamentId);
      setTimerState(null);
      await loadTournament();
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'Failed to reset timer');
    }
  };

  const handleLevelUp = async () => {
    if (!tournamentId) return;
    try {
      const data = await blindScheduleApi.changeTournamentBlindLevel(tournamentId, 'next');
      // Update timer state with new level from response
      if (data?.currentLevel) {
        setTimerState(prev => prev ? { ...prev, level: data.currentLevel } : null);
      }
      // Refresh blind level display
      await fetchCurrentBlindLevel();
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'Failed to change level');
    }
  };

  const handleLevelDown = async () => {
    if (!tournamentId) return;
    try {
      const data = await blindScheduleApi.changeTournamentBlindLevel(tournamentId, 'previous');
      // Update timer state with new level from response
      if (data?.currentLevel) {
        setTimerState(prev => prev ? { ...prev, level: data.currentLevel } : null);
      }
      // Refresh blind level display
      await fetchCurrentBlindLevel();
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'Failed to change level');
    }
  };

  // Format time display
  const formatTime = (ms: number) => {
    const totalSeconds = Math.floor(ms / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
  };

  if (isLoading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#007AFF" />
        <Text style={styles.loadingText}>Loading tournament...</Text>
      </View>
    );
  }

  if (error || !tournament) {
    return (
      <View style={styles.centerContainer}>
        <Text style={styles.errorText}>{error || 'Tournament not found'}</Text>
        <TouchableOpacity style={styles.button} onPress={() => onBack()}>
          <Text style={styles.buttonText}>Go Back</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={isRefreshing} onRefresh={handleRefresh} />
      }
    >
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={() => onBack()}>
          <Text style={styles.backText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Tournament Details</Text>
        <View style={styles.headerSpacer} />
      </View>

      {/* Tournament Info */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Text style={styles.cardTitle}>{tournament.name}</Text>
          <TouchableOpacity
            style={styles.editButton}
            onPress={() => setEditModalVisible(true)}
          >
            <Text style={styles.editButtonText}>Edit</Text>
          </TouchableOpacity>
        </View>
        <Text style={styles.cardText}>
          Status: <Text style={styles.status}>{tournament.status}</Text>
        </Text>
        {tournament.description && (
          <Text style={styles.cardText}>{tournament.description}</Text>
        )}
        <Text style={styles.cardText}>
          Players: <Text style={styles.highlight}>{players.length}</Text>
        </Text>
      </View>

      {/* Timer Section */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Timer</Text>

        {/* Timer Display */}
        <View style={styles.timerDisplay}>
          <Text style={styles.timerTime}>
            {timerState ? formatTime(timerState.elapsedTime) : '--:--'}
          </Text>
          <Text style={styles.timerLevel}>Level: {timerState?.level || 1}</Text>
        </View>

        {/* Timer Status */}
        {timerState && (
          <View style={styles.timerStatus}>
            <Text style={[
              styles.timerStatusText,
              { color: timerState.isRunning ? '#4CAF50' : '#FF9800' }
            ]}>
              {timerState.isRunning ? 'Running' : 'Paused'}
            </Text>
          </View>
        )}

        {/* Blind Level Display */}
        {currentBlindLevel && (
          <BlindLevelDisplay
            blindLevel={currentBlindLevel}
            colorScheme="dark"
            size="medium"
            showLevel={false}
          />
        )}

        {/* Timer Controls */}
        <View style={styles.timerControls}>
          {!timerState?.isRunning ? (
            <TouchableOpacity
              style={[styles.timerButton, styles.startButton]}
              onPress={handleStartTimer}
            >
              <Text style={styles.timerButtonText}>▶ Start</Text>
            </TouchableOpacity>
          ) : timerState?.isPaused ? (
            <TouchableOpacity
              style={[styles.timerButton, styles.resumeButton]}
              onPress={handleResumeTimer}
            >
              <Text style={styles.timerButtonText}>▶ Resume</Text>
            </TouchableOpacity>
          ) : (
            <TouchableOpacity
              style={[styles.timerButton, styles.pauseButton]}
              onPress={handlePauseTimer}
            >
              <Text style={styles.timerButtonText}>⏸ Pause</Text>
            </TouchableOpacity>
          )}

          <TouchableOpacity
            style={[styles.timerButton, styles.resetButton]}
            onPress={handleResetTimer}
          >
            <Text style={styles.timerButtonText}>↺ Reset</Text>
          </TouchableOpacity>
        </View>

        {/* Level Controls */}
        <View style={styles.levelControls}>
          <TouchableOpacity
            style={[styles.levelButton, styles.levelDownButton]}
            onPress={handleLevelDown}
            disabled={!timerState || timerState.level <= 1}
          >
            <Text style={styles.levelButtonText}>− Level</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.levelButton, styles.levelUpButton]}
            onPress={handleLevelUp}
          >
            <Text style={styles.levelButtonText}>+ Level</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Blind Levels Section */}
      <BlindLevelsList
        blindScheduleId={tournament.blindScheduleId || null}
        currentLevelNumber={timerState?.level || tournament.currentBlindLevel || 1}
        onLevelPress={(level) => {
          // Optional: Show level preview modal
          console.log('[TournamentDetail] Level pressed:', level);
        }}
      />

      {/* Players Section */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Text style={styles.cardTitle}>Players ({players.length})</Text>
          <TouchableOpacity
            style={styles.addButton}
            onPress={() => setAddPlayerModalVisible(true)}
          >
            <Text style={styles.addButtonText}>+ Add</Text>
          </TouchableOpacity>
        </View>

        {isLoadingPlayers ? (
          <ActivityIndicator size="small" color="#007AFF" />
        ) : players.length === 0 ? (
          <Text style={styles.emptyText}>No players yet</Text>
        ) : (
          players.map((player) => (
            <View key={player.tournamentPlayerId} style={styles.playerItem}>
              <View style={styles.playerInfo}>
                <Text style={styles.playerName}>{player.player.name}</Text>
                <Text style={styles.playerDetails}>
                  Stack: {player.startingStack} | {player.tableName || 'Unseated'}
                  {player.seatNumber && ` • Seat ${player.seatNumber}`}
                </Text>
              </View>
              <TouchableOpacity
                style={styles.removePlayerButton}
                onPress={() => handleRemovePlayer(player.tournamentPlayerId, player.player.name)}
              >
                <Text style={styles.removePlayerButtonText}>Remove</Text>
              </TouchableOpacity>
            </View>
          ))
        )}
      </View>

      {/* Edit Tournament Modal */}
      <Modal
        visible={editModalVisible}
        transparent
        animationType="slide"
        onRequestClose={() => setEditModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Edit Tournament</Text>

            <TextInput
              style={styles.input}
              value={editName}
              onChangeText={setEditName}
              placeholder="Tournament Name"
            />

            <TextInput
              style={[styles.input, styles.textArea]}
              value={editDescription}
              onChangeText={setEditDescription}
              placeholder="Description"
              multiline
            />

            <Text style={styles.label}>Status:</Text>
            <View style={styles.statusOptions}>
              {['upcoming', 'active', 'completed', 'cancelled'].map((status) => (
                <TouchableOpacity
                  key={status}
                  style={[
                    styles.statusOption,
                    editStatus === status && styles.statusOptionSelected,
                  ]}
                  onPress={() => setEditStatus(status)}
                >
                  <Text
                    style={[
                      styles.statusOptionText,
                      editStatus === status && styles.statusOptionTextSelected,
                    ]}
                  >
                    {status}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>

            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.modalButton, styles.cancelButton]}
                onPress={() => setEditModalVisible(false)}
              >
                <Text style={styles.modalButtonText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalButton, styles.saveButton]}
                onPress={handleSaveEdit}
              >
                <Text style={styles.modalButtonText}>Save</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Add Player Modal */}
      <Modal
        visible={addPlayerModalVisible}
        transparent
        animationType="slide"
        onRequestClose={() => {
          setAddPlayerModalVisible(false);
          resetAddPlayerForm();
        }}
      >
        <ScrollView contentContainerStyle={styles.modalScrollContent}>
          <View style={styles.modalOverlay}>
            <View style={styles.modalContent}>
              <Text style={styles.modalTitle}>Add Player to Tournament</Text>

              {/* Player Search */}
              <View style={styles.formGroup}>
                <Text style={styles.label}>Search Existing Players</Text>
                <TextInput
                  style={styles.input}
                  value={playerSearchQuery}
                  onChangeText={handlePlayerSearch}
                  placeholder="Type to search players..."
                  placeholderTextColor="#999"
                  autoCapitalize="none"
                />
              </View>

              {/* Search Results or Selected Player */}
              {selectedPlayer ? (
                <View style={styles.selectedPlayerContainer}>
                  <Text style={styles.selectedPlayerLabel}>Selected:</Text>
                  <View style={styles.selectedPlayerInfo}>
                    <Text style={styles.selectedPlayerName}>{selectedPlayer.name}</Text>
                    {selectedPlayer.email && (
                      <Text style={styles.selectedPlayerDetail}>{selectedPlayer.email}</Text>
                    )}
                    {selectedPlayer.phone && (
                      <Text style={styles.selectedPlayerDetail}>{selectedPlayer.phone}</Text>
                    )}
                  </View>
                  <TouchableOpacity
                    style={styles.clearSelectionButton}
                    onPress={() => {
                      setSelectedPlayer(null);
                      setNewPlayerName('');
                      setNewPlayerEmail('');
                    }}
                  >
                    <Text style={styles.clearSelectionText}>Clear Selection</Text>
                  </TouchableOpacity>
                </View>
              ) : playerSearchResults.length > 0 ? (
                <View style={styles.searchResultsContainer}>
                  <Text style={styles.searchResultsLabel}>Results:</Text>
                  {playerSearchResults.map((player) => (
                    <TouchableOpacity
                      key={player.playerId}
                      style={styles.playerResultItem}
                      onPress={() => handleSelectPlayer(player)}
                    >
                      <View style={styles.playerResultInfo}>
                        <Text style={styles.playerResultName}>{player.name}</Text>
                        {player.email && (
                          <Text style={styles.playerResultDetail}>{player.email}</Text>
                        )}
                      </View>
                      <Text style={styles.playerResultAddText}>Select</Text>
                    </TouchableOpacity>
                  ))}
                </View>
              ) : playerSearchQuery && isSearchingPlayers ? (
                <View style={styles.searchLoadingContainer}>
                  <ActivityIndicator size="small" color="#007AFF" />
                  <Text style={styles.searchLoadingText}>Searching...</Text>
                </View>
              ) : null}

              {/* Or create new player */}
              {!selectedPlayer && (
                <View style={styles.divider}>
                  <Text style={styles.dividerText}>OR CREATE NEW PLAYER</Text>
                </View>
              )}

              {!selectedPlayer && (
                <>
                  <View style={styles.formGroup}>
                    <Text style={styles.label}>Player Name *</Text>
                    <TextInput
                      style={styles.input}
                      value={newPlayerName}
                      onChangeText={setNewPlayerName}
                      placeholder="Enter player name"
                      placeholderTextColor="#999"
                      autoCapitalize="words"
                    />
                  </View>

                  <View style={styles.formGroup}>
                    <Text style={styles.label}>Email</Text>
                    <TextInput
                      style={styles.input}
                      value={newPlayerEmail}
                      onChangeText={setNewPlayerEmail}
                      placeholder="Email (optional)"
                      placeholderTextColor="#999"
                      autoCapitalize="none"
                      keyboardType="email-address"
                    />
                  </View>

                  <View style={styles.formGroup}>
                    <Text style={styles.label}>Starting Stack *</Text>
                    <TextInput
                      style={styles.input}
                      value={newPlayerStartingStack}
                      onChangeText={setNewPlayerStartingStack}
                      placeholder="10000"
                      placeholderTextColor="#999"
                      keyboardType="number-pad"
                    />
                  </View>
                </>
              )}

              <View style={styles.modalButtons}>
                <TouchableOpacity
                  style={[styles.modalButton, styles.cancelButton]}
                  onPress={() => {
                    setAddPlayerModalVisible(false);
                    resetAddPlayerForm();
                  }}
                >
                  <Text style={styles.modalButtonText}>Cancel</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.modalButton, styles.saveButton]}
                  onPress={handleAddPlayer}
                  disabled={isSearchingPlayers}
                >
                  <Text style={styles.modalButtonText}>
                    {selectedPlayer ? 'Add Player' : 'Create & Add'}
                  </Text>
                </TouchableOpacity>
              </View>
            </View>
          </View>
        </ScrollView>
      </Modal>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F5F5F5',
  },
  centerContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#F5F5F5',
  },
  loadingText: {
    marginTop: 10,
    color: '#666',
  },
  errorText: {
    fontSize: 16,
    color: '#FF3B30',
    marginBottom: 20,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#FFF',
    borderBottomWidth: 1,
    borderBottomColor: '#E0E0E0',
  },
  backText: {
    fontSize: 16,
    color: '#007AFF',
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '600',
  },
  headerSpacer: {
    width: 50,
  },
  card: {
    backgroundColor: '#FFF',
    margin: 16,
    padding: 16,
    borderRadius: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  cardTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#000',
  },
  cardText: {
    fontSize: 14,
    color: '#666',
    marginBottom: 4,
  },
  status: {
    fontWeight: '600',
    textTransform: 'capitalize',
  },
  highlight: {
    fontWeight: '600',
    color: '#007AFF',
  },
  editButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: '#007AFF',
    borderRadius: 4,
  },
  editButtonText: {
    color: '#FFF',
    fontSize: 12,
    fontWeight: '600',
  },
  timerDisplay: {
    backgroundColor: '#F0F0F0',
    padding: 20,
    borderRadius: 8,
    alignItems: 'center',
    marginVertical: 12,
  },
  timerTime: {
    fontSize: 48,
    fontWeight: '700',
    color: '#000',
  },
  timerLevel: {
    fontSize: 16,
    color: '#666',
    marginTop: 4,
  },
  timerStatus: {
    alignItems: 'center',
    marginBottom: 12,
  },
  timerStatusText: {
    fontSize: 14,
    fontWeight: '600',
  },
  timerControls: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    marginBottom: 12,
  },
  timerButton: {
    flex: 1,
    paddingVertical: 12,
    marginHorizontal: 4,
    borderRadius: 6,
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
    backgroundColor: '#FF3B30',
  },
  timerButtonText: {
    color: '#FFF',
    fontSize: 14,
    fontWeight: '600',
  },
  levelControls: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  levelButton: {
    flex: 1,
    paddingVertical: 10,
    marginHorizontal: 4,
    borderRadius: 6,
    alignItems: 'center',
  },
  levelDownButton: {
    backgroundColor: '#E0E0E0',
  },
  levelUpButton: {
    backgroundColor: '#007AFF',
  },
  levelButtonText: {
    color: '#FFF',
    fontSize: 14,
    fontWeight: '600',
  },
  addButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: '#4CAF50',
    borderRadius: 4,
  },
  addButtonText: {
    color: '#FFF',
    fontSize: 12,
    fontWeight: '600',
  },
  emptyText: {
    textAlign: 'center',
    color: '#999',
    fontStyle: 'italic',
    padding: 20,
  },
  playerItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#E0E0E0',
  },
  playerInfo: {
    flex: 1,
  },
  playerName: {
    fontSize: 16,
    fontWeight: '500',
    color: '#000',
  },
  playerDetails: {
    fontSize: 12,
    color: '#666',
    marginTop: 2,
  },
  removePlayerButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: '#FF3B30',
    borderRadius: 4,
  },
  removePlayerButtonText: {
    color: '#FFF',
    fontSize: 12,
  },
  button: {
    paddingHorizontal: 20,
    paddingVertical: 10,
    backgroundColor: '#007AFF',
    borderRadius: 6,
  },
  buttonText: {
    color: '#FFF',
    fontSize: 16,
    fontWeight: '600',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContent: {
    backgroundColor: '#FFF',
    borderRadius: 12,
    padding: 20,
    width: '100%',
    maxWidth: 400,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    marginBottom: 20,
    color: '#000',
  },
  input: {
    borderWidth: 1,
    borderColor: '#E0E0E0',
    borderRadius: 6,
    padding: 12,
    fontSize: 16,
    marginBottom: 12,
  },
  textArea: {
    height: 80,
    textAlignVertical: 'top',
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#000',
    marginBottom: 8,
  },
  statusOptions: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginBottom: 20,
  },
  statusOption: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#E0E0E0',
    marginRight: 8,
    marginBottom: 8,
  },
  statusOptionSelected: {
    backgroundColor: '#007AFF',
    borderColor: '#007AFF',
  },
  statusOptionText: {
    fontSize: 14,
    color: '#666',
  },
  statusOptionTextSelected: {
    color: '#FFF',
  },
  modalButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 20,
  },
  modalButton: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 6,
    alignItems: 'center',
  },
  cancelButton: {
    backgroundColor: '#E0E0E0',
    marginRight: 8,
  },
  saveButton: {
    backgroundColor: '#007AFF',
    marginLeft: 8,
  },
  modalButtonText: {
    color: '#FFF',
    fontSize: 16,
    fontWeight: '600',
  },
  // Player search modal styles
  modalScrollContent: {
    flexGrow: 1,
  },
  selectedPlayerContainer: {
    backgroundColor: '#E3F2FD',
    borderRadius: 8,
    padding: 12,
    marginBottom: 16,
  },
  selectedPlayerLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: '#007AFF',
    marginBottom: 8,
    textTransform: 'uppercase',
  },
  selectedPlayerInfo: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  selectedPlayerName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#000',
  },
  selectedPlayerDetail: {
    fontSize: 14,
    color: '#666',
  },
  clearSelectionButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: '#FF3B30',
    borderRadius: 6,
  },
  clearSelectionText: {
    color: '#FFF',
    fontSize: 12,
    fontWeight: '600',
  },
  searchResultsContainer: {
    marginBottom: 16,
  },
  searchResultsLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
    marginBottom: 8,
  },
  playerResultItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 12,
    backgroundColor: '#FAFAFA',
    borderRadius: 8,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  playerResultInfo: {
    flex: 1,
  },
  playerResultName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#000',
  },
  playerResultDetail: {
    fontSize: 14,
    color: '#666',
  },
  playerResultAddText: {
    color: '#FFF',
    fontSize: 14,
    fontWeight: '600',
  },
  searchLoadingContainer: {
    alignItems: 'center',
    padding: 16,
  },
  searchLoadingText: {
    fontSize: 14,
    color: '#666',
    marginTop: 8,
  },
  divider: {
    flexDirection: 'row',
    alignItems: 'center',
    marginVertical: 20,
  },
  dividerText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#999',
    paddingHorizontal: 8,
  },
  formGroup: {
    marginBottom: 16,
  },
});
