/**
 * Blind Schedule Selector Component
 * Dropdown/picker for selecting a blind schedule during tournament setup
 */

import { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Modal,
  ScrollView,
  ActivityIndicator,
} from 'react-native';
import { useBlindScheduleStore } from '../stores/blindScheduleStore';
import type { BlindScheduleListItem } from '../types/blindSchedule';

interface Props {
  selectedScheduleId?: string;
  onScheduleSelect: (scheduleId: string) => void;
  error?: string;
}

export function BlindScheduleSelector({ selectedScheduleId, onScheduleSelect, error }: Props) {
  console.log('[BlindScheduleSelector] RENDER START', Date.now());
  const { schedules, fetchSchedules, isLoading } = useBlindScheduleStore();
  console.log('[BlindScheduleSelector] After store access', Date.now());
  const [modalVisible, setModalVisible] = useState(false);

  const selectedSchedule = schedules.find(s => s.id === selectedScheduleId);

  // Diagnostic logging
  useEffect(() => {
    console.log('[BlindScheduleSelector] MOUNT START', Date.now());
    console.log('[BlindScheduleSelector] Store state:', {
      schedulesCount: schedules.length,
      isLoading,
      hasError: !!error,
      selectedScheduleId,
    });
    console.log('[BlindScheduleSelector] MOUNT END', Date.now());
    return () => console.log('[BlindScheduleSelector] UNMOUNTED', Date.now());
  }, []);

  const handleOpenModal = () => {
    console.log('[BlindScheduleSelector] handleOpenModal called', Date.now());
    // Lazy load: only fetch when user opens the picker
    if (schedules.length === 0 && !isLoading) {
      console.log('[BlindScheduleSelector] Fetching schedules...');
      fetchSchedules(true);
    }
    setModalVisible(true);
  };

  const handleSelect = (scheduleId: string) => {
    onScheduleSelect(scheduleId);
    setModalVisible(false);
  };

  console.log('[BlindScheduleSelector] RENDER END', Date.now());

  return (
    <View style={styles.container}>
      <Text style={styles.label}>Blind Schedule *</Text>

      <TouchableOpacity
        style={[styles.selector, error && styles.selectorError]}
        onPress={handleOpenModal}
        activeOpacity={0.7}
      >
        <Text style={styles.selectorText} numberOfLines={1}>
          {selectedSchedule ? selectedSchedule.name : 'Select a blind schedule...'}
        </Text>
        <Text style={styles.chevron}>▼</Text>
      </TouchableOpacity>

      {error && <Text style={styles.errorText}>{error}</Text>}

      {selectedSchedule && (
        <View style={styles.scheduleInfo}>
          <Text style={styles.infoLabel}>Starting Stack: </Text>
          <Text style={styles.infoValue}>{selectedSchedule.startingStack.toLocaleString()}</Text>
          <Text style={styles.infoLabel}> • Levels: </Text>
          <Text style={styles.infoValue}>{selectedSchedule.levelCount}</Text>
          <Text style={styles.infoLabel}> • Duration: </Text>
          <Text style={styles.infoValue}>{Math.floor(selectedSchedule.totalDurationMinutes / 60)}h {selectedSchedule.totalDurationMinutes % 60}m</Text>
        </View>
      )}

      <Modal
        visible={modalVisible}
        transparent
        animationType="slide"
        onRequestClose={() => setModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Select Blind Schedule</Text>
              <TouchableOpacity onPress={() => setModalVisible(false)}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>

            {isLoading ? (
              <View style={styles.loadingContainer}>
                <ActivityIndicator size="large" color="#007AFF" />
                <Text style={styles.loadingText}>Loading schedules...</Text>
              </View>
            ) : (
              <ScrollView style={styles.schedulesList}>
                {schedules.length === 0 ? (
                  <View style={styles.emptyState}>
                    <Text style={styles.emptyText}>No blind schedules available</Text>
                    <Text style={styles.emptySubtext}>Create a schedule first in the Blind Schedules section</Text>
                  </View>
                ) : (
                  schedules.map((schedule) => (
                    <TouchableOpacity
                      key={schedule.id}
                      style={[
                        styles.scheduleItem,
                        selectedScheduleId === schedule.id && styles.scheduleItemSelected,
                      ]}
                      onPress={() => handleSelect(schedule.id)}
                    >
                      <View style={styles.scheduleItemHeader}>
                        <Text style={styles.scheduleName}>{schedule.name}</Text>
                        {schedule.isDefault && (
                          <View style={styles.defaultBadge}>
                            <Text style={styles.defaultBadgeText}>Default</Text>
                          </View>
                        )}
                      </View>

                      {schedule.description && (
                        <Text style={styles.scheduleDescription} numberOfLines={2}>
                          {schedule.description}
                        </Text>
                      )}

                      <View style={styles.scheduleMeta}>
                        <Text style={styles.metaText}>
                          Stack: {schedule.startingStack.toLocaleString()}
                        </Text>
                        <Text style={styles.metaText}>
                          {schedule.levelCount} levels
                        </Text>
                        <Text style={styles.metaText}>
                          {Math.floor(schedule.totalDurationMinutes / 60)}h {schedule.totalDurationMinutes % 60}m
                        </Text>
                      </View>
                    </TouchableOpacity>
                  ))
                )}
              </ScrollView>
            )}

            <TouchableOpacity
              style={styles.modalCancelButton}
              onPress={() => setModalVisible(false)}
            >
              <Text style={styles.modalCancelButtonText}>Cancel</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginBottom: 20,
  },
  label: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 8,
    color: '#333',
  },
  selector: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 12,
    backgroundColor: '#fafafa',
  },
  selectorError: {
    borderColor: '#FF3B30',
  },
  selectorText: {
    fontSize: 16,
    color: '#333',
    flex: 1,
  },
  chevron: {
    fontSize: 12,
    color: '#666',
  },
  errorText: {
    fontSize: 14,
    color: '#FF3B30',
    marginTop: 4,
  },
  scheduleInfo: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginTop: 8,
    alignItems: 'center',
  },
  infoLabel: {
    fontSize: 14,
    color: '#666',
  },
  infoValue: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
  },
  // Modal styles
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: '#fff',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    maxHeight: '80%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#333',
  },
  modalClose: {
    fontSize: 24,
    color: '#666',
    width: 30,
    height: 30,
    textAlign: 'center',
    lineHeight: 24,
  },
  loadingContainer: {
    padding: 40,
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: '#666',
  },
  schedulesList: {
    maxHeight: 400,
  },
  emptyState: {
    padding: 40,
    alignItems: 'center',
  },
  emptyText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    marginBottom: 8,
  },
  emptySubtext: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
  },
  scheduleItem: {
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  scheduleItemSelected: {
    backgroundColor: '#E3F2FD',
  },
  scheduleItemHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  scheduleName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    flex: 1,
  },
  defaultBadge: {
    backgroundColor: '#4CAF50',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
  defaultBadgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#fff',
  },
  scheduleDescription: {
    fontSize: 14,
    color: '#666',
    marginBottom: 8,
  },
  scheduleMeta: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  metaText: {
    fontSize: 13,
    color: '#999',
  },
  modalCancelButton: {
    padding: 16,
    borderTopWidth: 1,
    borderTopColor: '#eee',
    alignItems: 'center',
  },
  modalCancelButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#007AFF',
  },
});
