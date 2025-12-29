/**
 * Blind Scheme Detail Screen
 * Read-only view of a blind scheme with all levels
 */

import { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  ActivityIndicator,
  TouchableOpacity,
  Alert,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { BlindLevelsList } from '../components/BlindLevelsList';
import { useBlindScheduleStore } from '../stores/blindScheduleStore';
import { blindScheduleApi } from '../services/api/blindScheduleApi';
import type { BlindScheduleWithMetadata } from '../types/blindSchedule';

interface Props {
  schemeId: string;
  onBack: () => void;
  onEdit?: (scheme: BlindScheduleWithMetadata) => void;
  onDelete?: () => void;
}

export function BlindSchemeDetailScreen({ schemeId, onBack, onEdit, onDelete }: Props) {
  const { selectedSchedule, fetchSchedule, isLoading, error, deleteSchedule } = useBlindScheduleStore();

  const [scheme, setScheme] = useState<BlindScheduleWithMetadata | null>(selectedSchedule);
  const [isDeleting, setIsDeleting] = useState(false);
  const [isInUse, setIsInUse] = useState(false);
  const [checkingInUse, setCheckingInUse] = useState(false);

  useEffect(() => {
    if (!selectedSchedule || selectedSchedule.id !== schemeId) {
      fetchSchedule(schemeId);
    } else {
      setScheme(selectedSchedule);
    }
  }, [schemeId, selectedSchedule, fetchSchedule]);

  useEffect(() => {
    if (selectedSchedule?.id === schemeId) {
      setScheme(selectedSchedule);
    }
  }, [selectedSchedule, schemeId]);

  // Check if schedule is in use when scheme loads
  useEffect(() => {
    if (scheme && !scheme.isDefault) {
      setCheckingInUse(true);
      blindScheduleApi.isScheduleInUse(scheme.id)
        .then((inUse) => setIsInUse(inUse))
        .catch(() => setIsInUse(false))
        .finally(() => setCheckingInUse(false));
    }
  }, [scheme]);

  const handleDelete = () => {
    if (!scheme) return;

    Alert.alert(
      'Delete Blind Scheme',
      `Are you sure you want to delete "${scheme.name}"? This action cannot be undone.`,
      [
        {
          text: 'Cancel',
          style: 'cancel',
        },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            setIsDeleting(true);
            try {
              await deleteSchedule(scheme.id);
              onDelete?.();
            } catch (error) {
              Alert.alert('Delete Failed', (error as Error).message);
              setIsDeleting(false);
            }
          },
        },
      ]
    );
  };

  if (isLoading && !scheme) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#1976d2" />
          <Text style={styles.loadingText}>Loading scheme...</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (error && !scheme) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.headerContainer}>
          <TouchableOpacity onPress={onBack} style={styles.backButton}>
            <Text style={styles.backButtonText}>← Back</Text>
          </TouchableOpacity>
        </View>
        <View style={styles.errorContainer}>
          <Text style={styles.errorTitle}>Error Loading Scheme</Text>
          <Text style={styles.errorMessage}>{error}</Text>
          <TouchableOpacity style={styles.retryButton} onPress={() => fetchSchedule(schemeId)}>
            <Text style={styles.retryButtonText}>Retry</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  if (!scheme) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.headerContainer}>
          <TouchableOpacity onPress={onBack} style={styles.backButton}>
            <Text style={styles.backButtonText}>← Back</Text>
          </TouchableOpacity>
        </View>
        <View style={styles.errorContainer}>
          <Text style={styles.errorTitle}>Scheme Not Found</Text>
          <Text style={styles.errorMessage}>Unable to load blind scheme details.</Text>
        </View>
      </SafeAreaView>
    );
  }

  const formatDuration = (minutes: number): string => {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours > 0) {
      return `${hours}h ${mins}m`;
    }
    return `${mins}m`;
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.headerContainer}>
        <TouchableOpacity onPress={onBack} style={styles.backButton}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
      </View>
      <ScrollView style={styles.scrollView}>
        {/* Header */}
        <View style={styles.header}>
          <View style={styles.headerTop}>
            <Text style={styles.name}>{scheme.name}</Text>
            {scheme.isDefault && (
              <View style={styles.defaultBadge}>
                <Text style={styles.defaultBadgeText}>Default</Text>
              </View>
            )}
          </View>

          {scheme.description && (
            <Text style={styles.description}>{scheme.description}</Text>
          )}
        </View>

        {/* Metadata */}
        <View style={styles.metadataSection}>
          <View style={styles.metadataRow}>
            <View style={styles.metadataItem}>
              <Text style={styles.metadataLabel}>Starting Stack</Text>
              <Text style={styles.metadataValue}>{scheme.startingStack}</Text>
            </View>

            <View style={styles.metadataItem}>
              <Text style={styles.metadataLabel}>Total Duration</Text>
              <Text style={styles.metadataValue}>{formatDuration(scheme.totalDurationMinutes)}</Text>
            </View>
          </View>

          <View style={styles.metadataRow}>
            <View style={styles.metadataItem}>
              <Text style={styles.metadataLabel}>Number of Levels</Text>
              <Text style={styles.metadataValue}>{scheme.levelCount}</Text>
            </View>

            <View style={styles.metadataItem}>
              <Text style={styles.metadataLabel}>Break Interval</Text>
              <Text style={styles.metadataValue}>{scheme.breakInterval || 'None'}</Text>
            </View>
          </View>
        </View>

        {/* Levels List */}
        <BlindLevelsList
          blindScheduleId={scheme.id}
          currentLevelNumber={1} // Show first level as current (read-only)
        />

        {/* Edit/Delete Actions */}
        <View style={styles.actionsSection}>
          {scheme.isDefault ? (
            // For default schemes, edit creates a copy
            <TouchableOpacity
              style={[styles.actionButton, styles.editButton]}
              onPress={() => onEdit?.(scheme)}
            >
              <Text style={styles.editButtonText}>Create Copy to Edit</Text>
            </TouchableOpacity>
          ) : (
            // For custom schemes, edit directly
            <TouchableOpacity
              style={[styles.actionButton, styles.editButton]}
              onPress={() => onEdit?.(scheme)}
            >
              <Text style={styles.editButtonText}>Edit Scheme</Text>
            </TouchableOpacity>
          )}

          {!scheme.isDefault && (
            <TouchableOpacity
              style={[
                styles.actionButton,
                styles.deleteButton,
                (isDeleting || isInUse || checkingInUse) && styles.deleteButtonDisabled
              ]}
              onPress={handleDelete}
              disabled={isDeleting || isInUse || checkingInUse}
            >
              <Text style={styles.deleteButtonText}>
                {checkingInUse ? 'Checking...' :
                 isInUse ? 'In Use - Cannot Delete' :
                 isDeleting ? 'Deleting...' : 'Delete Scheme'}
              </Text>
            </TouchableOpacity>
          )}
        </View>

        {/* Info for default schemes */}
        {scheme.isDefault && (
          <View style={styles.infoSection}>
            <Text style={styles.infoText}>
              This is a default blind scheme. Tap "Create Copy to Edit" to make a copy that you can modify.
            </Text>
          </View>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  headerContainer: {
    backgroundColor: '#fff',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  backButton: {
    alignSelf: 'flex-start',
  },
  backButtonText: {
    fontSize: 16,
    color: '#1976d2',
    fontWeight: '600',
  },
  scrollView: {
    flex: 1,
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
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
  },
  errorTitle: {
    fontSize: 20,
    fontWeight: '600',
    color: '#d32f2f',
    marginBottom: 8,
  },
  errorMessage: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
    marginBottom: 16,
  },
  retryButton: {
    backgroundColor: '#1976d2',
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
  },
  retryButtonText: {
    color: '#fff',
    fontWeight: '600',
  },
  header: {
    backgroundColor: '#fff',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  headerTop: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  name: {
    flex: 1,
    fontSize: 24,
    fontWeight: '700',
    color: '#000',
  },
  defaultBadge: {
    backgroundColor: '#e3f2fd',
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 6,
  },
  defaultBadgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#1976d2',
  },
  description: {
    fontSize: 15,
    color: '#666',
    lineHeight: 22,
  },
  metadataSection: {
    backgroundColor: '#fff',
    marginTop: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: '#e0e0e0',
  },
  metadataRow: {
    flexDirection: 'row',
    marginBottom: 12,
  },
  metadataItem: {
    flex: 1,
  },
  metadataLabel: {
    fontSize: 12,
    color: '#888',
    marginBottom: 4,
  },
  metadataValue: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
  actionsSection: {
    flexDirection: 'row',
    padding: 16,
    gap: 12,
  },
  actionButton: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  editButton: {
    backgroundColor: '#1976d2',
  },
  editButtonText: {
    color: '#fff',
    fontWeight: '600',
    fontSize: 15,
  },
  deleteButton: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d32f2f',
  },
  deleteButtonDisabled: {
    opacity: 0.5,
  },
  deleteButtonText: {
    color: '#d32f2f',
    fontWeight: '600',
    fontSize: 15,
  },
  infoSection: {
    margin: 16,
    padding: 12,
    backgroundColor: '#fff8e1',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#ffecb3',
  },
  infoText: {
    fontSize: 13,
    color: '#f57c00',
    lineHeight: 18,
  },
});
