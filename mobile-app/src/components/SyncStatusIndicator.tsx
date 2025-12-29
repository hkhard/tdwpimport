/**
 * Sync Status Indicator Component
 * Displays current sync status (connected/syncing/offline)
 *
 * Constitution Requirements:
 * - US2-A2: Automatic sync when connection restored
 * - Visual feedback for sync state
 */

import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { getSyncService } from '../services/sync/SyncService';
import { getNetworkMonitor } from '../services/sync/NetworkMonitor';

export type SyncStatusState = 'idle' | 'syncing' | 'offline' | 'error';

export interface SyncStatus {
  state: SyncStatusState;
  lastSyncAt: Date | null;
  pendingChanges: number;
  failedChanges: number;
  progress: number;
}

export interface SyncStatusIndicatorProps {
  /** Custom style for container */
  style?: any;
  /** Show detailed info */
  showDetails?: boolean;
}

/**
 * Sync Status Indicator
 *
 * Visual indicator for sync status:
 * - Green dot: Online and synced
 * - Yellow dot: Syncing
 * - Red dot: Offline or error
 * - Shows pending changes count
 */
export function SyncStatusIndicator({ style, showDetails = false }: SyncStatusIndicatorProps) {
  const [status, setStatus] = useState<SyncStatus>({
    state: 'idle',
    lastSyncAt: null,
    pendingChanges: 0,
    failedChanges: 0,
    progress: 0,
  });

  useEffect(() => {
    // Subscribe to sync status changes
    const syncService = getSyncService();
    const unsubscribe = syncService.onStatusChange((newStatus) => {
      setStatus(newStatus);
    });

    // Get initial status
    setStatus(syncService.getStatus());

    return unsubscribe;
  }, []);

  const getStatusColor = () => {
    switch (status.state) {
      case 'idle':
        return '#4CAF50'; // Green
      case 'syncing':
        return '#FFC107'; // Yellow
      case 'offline':
        return '#F44336'; // Red
      case 'error':
        return '#F44336'; // Red
    }
  };

  const getStatusText = () => {
    switch (status.state) {
      case 'idle':
        return 'Synced';
      case 'syncing':
        return 'Syncing...';
      case 'offline':
        return 'Offline';
      case 'error':
        return 'Sync Error';
    }
  };

  const formatLastSync = () => {
    if (!status.lastSyncAt) {
      return 'Never';
    }

    const now = new Date();
    const diffMs = now.getTime() - status.lastSyncAt.getTime();
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 1) {
      return 'Just now';
    } else if (diffMins < 60) {
      return `${diffMins}m ago`;
    } else {
      const diffHours = Math.floor(diffMins / 60);
      return `${diffHours}h ago`;
    }
  };

  return (
    <View style={[styles.container, style]}>
      {/* Status dot */}
      <View style={[styles.statusDot, { backgroundColor: getStatusColor() }]} />

      {/* Status text */}
      <Text style={styles.statusText}>{getStatusText()}</Text>

      {/* Syncing progress bar */}
      {status.state === 'syncing' && (
        <View style={styles.progressBarContainer}>
          <View
            style={[
              styles.progressBar,
              { width: `${status.progress * 100}%` }
            ]}
          />
        </View>
      )}

      {/* Details */}
      {showDetails && (
        <View style={styles.details}>
          {status.pendingChanges > 0 && (
            <Text style={styles.detailText}>
              {status.pendingChanges} pending
            </Text>
          )}

          {status.failedChanges > 0 && (
            <Text style={[styles.detailText, styles.errorText]}>
              {status.failedChanges} failed
            </Text>
          )}

          <Text style={styles.detailText}>
            Last sync: {formatLastSync()}
          </Text>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 4,
    paddingHorizontal: 8,
    backgroundColor: '#F5F5F5',
    borderRadius: 16,
  },
  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginRight: 6,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '500',
    color: '#333',
  },
  progressBarContainer: {
    width: 60,
    height: 3,
    backgroundColor: '#E0E0E0',
    borderRadius: 2,
    marginLeft: 8,
    overflow: 'hidden',
  },
  progressBar: {
    height: '100%',
    backgroundColor: '#FFC107',
  },
  details: {
    marginTop: 4,
    marginLeft: 14,
  },
  detailText: {
    fontSize: 11,
    color: '#666',
    marginTop: 2,
  },
  errorText: {
    color: '#F44336',
  },
});

export default SyncStatusIndicator;
