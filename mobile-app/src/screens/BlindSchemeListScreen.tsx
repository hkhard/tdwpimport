/**
 * Blind Scheme List Screen
 * Displays list of all blind schemes with pull-to-refresh
 */

import { useState, useEffect, useCallback } from 'react';
import { View, Text, StyleSheet, FlatList, RefreshControl, ActivityIndicator, TouchableOpacity } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { BlindSchemeListItem } from '../components/BlindSchemeListItem';
import { BlindSchemeEmptyState } from '../components/BlindSchemeEmptyState';
import { useBlindScheduleStore } from '../stores/blindScheduleStore';

interface Props {
  onBack: () => void;
  onSchemePress: (schemeId: string) => void;
  onCreateScheme: () => void;
}

export function BlindSchemeListScreen({ onBack, onSchemePress, onCreateScheme }: Props) {
  const {
    schedules,
    isLoading,
    error,
    lastFetch,
    fetchSchedules,
    clearError,
  } = useBlindScheduleStore();

  const [refreshing, setRefreshing] = useState(false);

  // Load schemes on mount if needed
  useEffect(() => {
    if (!lastFetch || Date.now() - lastFetch > 5 * 60 * 1000) {
      fetchSchedules(true);
    }
  }, [lastFetch, fetchSchedules]);

  const handleRefresh = useCallback(async () => {
    setRefreshing(true);
    clearError();
    await fetchSchedules(true);
    setRefreshing(false);
  }, [fetchSchedules, clearError]);

  const handleSchemePress = useCallback((schemeId: string) => {
    onSchemePress(schemeId);
  }, [onSchemePress]);

  const handleCreateScheme = useCallback(() => {
    onCreateScheme();
  }, [onCreateScheme]);

  const renderScheme = useCallback(({ item }: { item: typeof schedules[0] }) => {
    return (
      <BlindSchemeListItem
        scheme={item}
        onPress={() => handleSchemePress(item.id)}
      />
    );
  }, [handleSchemePress]);

  const renderEmptyState = useCallback(() => {
    if (error) {
      return (
        <View style={styles.errorContainer}>
          <Text style={styles.errorTitle}>Error Loading Schemes</Text>
          <Text style={styles.errorMessage}>{error}</Text>
        </View>
      );
    }

    if (isLoading) {
      return (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#1976d2" />
        </View>
      );
    }

    return <BlindSchemeEmptyState />;
  }, [error, isLoading]);

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={onBack} style={styles.backButton}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <View style={styles.headerContent}>
          <Text style={styles.headerTitle}>Blind Level Schemes</Text>
          <Text style={styles.headerSubtitle}>
            {schedules.length} {schedules.length === 1 ? 'scheme' : 'schemes'}
          </Text>
        </View>
        <TouchableOpacity style={styles.createButton} onPress={handleCreateScheme}>
          <Ionicons name="add-circle-outline" size={28} color="#1976d2" />
        </TouchableOpacity>
      </View>

      <FlatList
        data={schedules}
        keyExtractor={(item) => item.id}
        renderItem={renderScheme}
        ListEmptyComponent={renderEmptyState}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={handleRefresh}
            colors={['#1976d2']}
            tintColor="#1976d2"
          />
        }
        contentContainerStyle={schedules.length === 0 ? styles.emptyContainer : undefined}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  header: {
    backgroundColor: '#fff',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
    flexDirection: 'row',
    alignItems: 'center',
  },
  backButton: {
    marginRight: 12,
  },
  backButtonText: {
    fontSize: 16,
    color: '#1976d2',
    fontWeight: '600',
  },
  headerContent: {
    flex: 1,
  },
  createButton: {
    padding: 4,
    marginLeft: 8,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '600',
    color: '#000',
  },
  headerSubtitle: {
    fontSize: 14,
    color: '#666',
    marginTop: 2,
  },
  emptyContainer: {
    flex: 1,
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
  },
  errorTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#d32f2f',
    marginBottom: 8,
  },
  errorMessage: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
});
