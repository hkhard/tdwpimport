/**
 * Blind Schedule List Screen
 *
 * Displays all blind schedules with options to:
 * - View schedule details
 * - Create new schedule
 * - Edit existing schedule
 * - Delete custom schedules
 */

import { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
} from 'react-native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootStackParamList';
import { useBlindScheduleStore } from '../stores/blindScheduleStore';

type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'BlindScheduleList'>;

interface Props {
  navigation: NavigationProp;
}

export function BlindScheduleListScreen({ navigation }: Props) {
  const { schedules, isLoading, error, fetchSchedules, deleteSchedule } = useBlindScheduleStore();

  useEffect(() => {
    fetchSchedules(true); // Include default schedules
  }, []);

  const handleCreateSchedule = () => {
    navigation.navigate('BlindScheduleEditor', { scheduleId: undefined });
  };

  const handleEditSchedule = (scheduleId: string, isDefault: boolean) => {
    if (isDefault) {
      Alert.alert(
        'Edit Default Schedule',
        'This is a default schedule. You cannot edit it directly, but you can create a copy.',
        [
          { text: 'Cancel', style: 'cancel' },
          {
            text: 'Create Copy',
            onPress: () => navigation.navigate('BlindScheduleEditor', { scheduleId, copyMode: true }),
          },
        ]
      );
    } else {
      navigation.navigate('BlindScheduleEditor', { scheduleId });
    }
  };

  const handleDeleteSchedule = async (scheduleId: string, scheduleName: string, isDefault: boolean) => {
    if (isDefault) {
      Alert.alert('Cannot Delete', 'Default schedules cannot be deleted.');
      return;
    }

    Alert.alert(
      'Delete Schedule',
      `Are you sure you want to delete "${scheduleName}"? This action cannot be undone.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
              await deleteSchedule(scheduleId);
              Alert.alert('Success', 'Schedule deleted successfully');
            } catch (err) {
              Alert.alert('Error', err instanceof Error ? err.message : 'Failed to delete schedule');
            }
          },
        },
      ]
    );
  };

  const renderScheduleItem = ({ item }: { item: { id: string; name: string; description?: string; levelCount: number; totalDurationMinutes: number; isDefault: boolean } }) => (
    <TouchableOpacity
      style={[styles.scheduleItem, item.isDefault && styles.defaultSchedule]}
      onPress={() => handleEditSchedule(item.id, item.isDefault)}
    >
      <View style={styles.scheduleHeader}>
        <View style={styles.scheduleTitleContainer}>
          <Text style={styles.scheduleName}>{item.name}</Text>
          {item.isDefault && <View style={styles.defaultBadge}><Text style={styles.defaultBadgeText}>DEFAULT</Text></View>}
        </View>
        <View style={styles.scheduleActions}>
          <TouchableOpacity
            style={styles.actionButton}
            onPress={() => handleEditSchedule(item.id, item.isDefault)}
          >
            <Text style={styles.actionButtonText}>Edit</Text>
          </TouchableOpacity>
          {!item.isDefault && (
            <TouchableOpacity
              style={[styles.actionButton, styles.deleteButton]}
              onPress={() => handleDeleteSchedule(item.id, item.name, item.isDefault)}
            >
              <Text style={[styles.actionButtonText, styles.deleteButtonText]}>Delete</Text>
            </TouchableOpacity>
          )}
        </View>
      </View>

      {item.description && (
        <Text style={styles.scheduleDescription}>{item.description}</Text>
      )}

      <View style={styles.scheduleMeta}>
        <Text style={styles.metaText}>{item.levelCount} levels</Text>
        <Text style={styles.metaSeparator}>•</Text>
        <Text style={styles.metaText}>{Math.floor(item.totalDurationMinutes / 60)}h {item.totalDurationMinutes % 60}m</Text>
      </View>
    </TouchableOpacity>
  );

  const renderEmptyState = () => (
    <View style={styles.emptyState}>
      <Text style={styles.emptyTitle}>No Blind Schedules</Text>
      <Text style={styles.emptyText}>
        Create your first blind schedule to get started with tournament management.
      </Text>
      <TouchableOpacity style={styles.createButton} onPress={handleCreateSchedule}>
        <Text style={styles.createButtonText}>Create Schedule</Text>
      </TouchableOpacity>
    </View>
  );

  if (isLoading && schedules.length === 0) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#007AFF" />
        <Text style={styles.loadingText}>Loading blind schedules...</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Blind Schedules</Text>
        <TouchableOpacity style={styles.headerButton} onPress={handleCreateSchedule}>
          <Text style={styles.headerButtonText}>+ Create New</Text>
        </TouchableOpacity>
      </View>

      {/* Error state */}
      {error && (
        <View style={styles.errorContainer}>
          <Text style={styles.errorText}>{error}</Text>
          <TouchableOpacity onPress={() => fetchSchedules(true)}>
            <Text style={styles.retryText}>Tap to retry</Text>
          </TouchableOpacity>
        </View>
      )}

      {/* Schedule list */}
      {schedules.length === 0 ? (
        renderEmptyState()
      ) : (
        <FlatList
          data={schedules}
          renderItem={renderScheduleItem}
          keyExtractor={(item) => item.id}
          contentContainerStyle={styles.listContent}
          refreshing={isLoading}
          onRefresh={() => fetchSchedules(true)}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: '#666',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#333',
  },
  headerButton: {
    backgroundColor: '#007AFF',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
  },
  headerButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  errorContainer: {
    backgroundColor: '#FFEBEE',
    padding: 16,
    margin: 16,
    borderRadius: 8,
  },
  errorText: {
    color: '#D32F2F',
    fontSize: 14,
  },
  retryText: {
    color: '#007AFF',
    fontSize: 14,
    marginTop: 8,
    fontWeight: '600',
  },
  listContent: {
    padding: 16,
  },
  scheduleItem: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e0e0e0',
  },
  defaultSchedule: {
    backgroundColor: '#E3F2FD',
    borderColor: '#2196F3',
  },
  scheduleHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  scheduleTitleContainer: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
  },
  scheduleName: {
    fontSize: 18,
    fontWeight: '600',
    color: '#333',
  },
  defaultBadge: {
    backgroundColor: '#2196F3',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
    marginLeft: 8,
  },
  defaultBadgeText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '700',
  },
  scheduleActions: {
    flexDirection: 'row',
    gap: 8,
  },
  actionButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
    backgroundColor: '#f0f0f0',
  },
  actionButtonText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#007AFF',
  },
  deleteButton: {
    backgroundColor: '#FFEBEE',
  },
  deleteButtonText: {
    color: '#D32F2F',
  },
  scheduleDescription: {
    fontSize: 14,
    color: '#666',
    marginBottom: 12,
  },
  scheduleMeta: {
    flexDirection: 'row',
    gap: 8,
  },
  metaText: {
    fontSize: 13,
    color: '#999',
  },
  metaSeparator: {
    color: '#ccc',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 32,
  },
  emptyTitle: {
    fontSize: 24,
    fontWeight: '700',
    color: '#333',
    marginBottom: 12,
  },
  emptyText: {
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
    marginBottom: 24,
  },
  createButton: {
    backgroundColor: '#007AFF',
    paddingHorizontal: 32,
    paddingVertical: 12,
    borderRadius: 8,
  },
  createButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});
