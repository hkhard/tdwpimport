/**
 * Tournament List Screen
 * Shows list of tournaments with ability to create new ones
 */

import { useEffect, useState } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, RefreshControl } from 'react-native';
import { useTournamentStore } from '../stores';
import { loadTournaments } from '../services/tournament/TournamentService';
import type { Tournament } from '@shared/types/tournament';

interface Props {
  onCreateTournament?: () => void;
  onTournamentPress?: (tournamentId: string) => void;
}

export function TournamentListScreen({ onCreateTournament, onTournamentPress }: Props) {
  const { tournaments, isLoading, error } = useTournamentStore();
  const [isRefreshing, setIsRefreshing] = useState(false);

  // Load tournaments on mount
  useEffect(() => {
    console.log('[TournamentListScreen] Mounted - loading tournaments');
    loadTournaments();
  }, []);

  const handleRefresh = async () => {
    setIsRefreshing(true);
    await loadTournaments();
    setIsRefreshing(false);
  };

  const handleCreateTournament = () => {
    onCreateTournament?.();
  };

  const handleTournamentPress = (tournament: Tournament) => {
    onTournamentPress?.(tournament.tournamentId);
  };

  const renderTournamentItem = ({ item }: { item: Tournament }) => (
    <TouchableOpacity
      style={styles.item}
      onPress={() => handleTournamentPress(item)}
    >
      <Text style={styles.name}>{item.name}</Text>
      <View style={styles.itemMeta}>
        <Text style={styles.status}>{item.status}</Text>
        <Text style={styles.level}>Level {item.currentBlindLevel}</Text>
      </View>
      {item.description && (
        <Text style={styles.description}>{item.description}</Text>
      )}
    </TouchableOpacity>
  );

  if (isLoading && tournaments.length === 0) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#007AFF" />
        <Text style={styles.loadingText}>Loading tournaments...</Text>
      </View>
    );
  }

  if (error && tournaments.length === 0) {
    return (
      <View style={styles.center}>
        <Text style={styles.errorText}>Error: {error}</Text>
        <TouchableOpacity style={styles.button} onPress={handleRefresh}>
          <Text style={styles.buttonText}>Retry</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (tournaments.length === 0) {
    return (
      <View style={styles.center}>
        <Text style={styles.emptyText}>No tournaments yet</Text>
        <TouchableOpacity style={styles.button} onPress={handleCreateTournament}>
          <Text style={styles.buttonText}>Create Tournament</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Tournaments</Text>
        <TouchableOpacity style={styles.createButton} onPress={handleCreateTournament}>
          <Text style={styles.createButtonText}>+ Create</Text>
        </TouchableOpacity>
      </View>

      <FlatList
        data={tournaments}
        keyExtractor={(item) => item.tournamentId}
        renderItem={renderTournamentItem}
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing}
            onRefresh={handleRefresh}
            colors={['#007AFF']}
          />
        }
        ListEmptyComponent={
          <View style={styles.center}>
            <Text style={styles.emptyText}>No tournaments found</Text>
          </View>
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  emptyText: {
    fontSize: 18,
    color: '#666',
    marginBottom: 20,
  },
  loadingText: {
    fontSize: 16,
    color: '#666',
    marginTop: 12,
  },
  errorText: {
    fontSize: 16,
    color: '#FF3B30',
    marginBottom: 20,
    textAlign: 'center',
  },
  button: {
    backgroundColor: '#007AFF',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
    backgroundColor: '#f8f8f8',
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
  },
  createButton: {
    backgroundColor: '#007AFF',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
  },
  createButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  item: {
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  name: {
    fontSize: 18,
    fontWeight: '600',
  },
  itemMeta: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 4,
  },
  status: {
    fontSize: 14,
    color: '#666',
    textTransform: 'capitalize',
  },
  level: {
    fontSize: 14,
    color: '#007AFF',
    fontWeight: '600',
  },
  description: {
    fontSize: 14,
    color: '#888',
    marginTop: 4,
  },
});
