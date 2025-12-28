/**
 * Blind Levels List Component
 *
 * Displays a scrollable list of blind levels with the current level highlighted.
 * Shows upcoming levels to help players anticipate blind changes.
 */

import { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
} from 'react-native';
import type { BlindLevel } from '@shared/types/timer';
import { blindScheduleApi } from '../services/api/blindScheduleApi';

interface Props {
  blindScheduleId: string | null;
  currentLevelNumber: number;
  onLevelPress?: (level: BlindLevel) => void;
}

const PAGE_SIZE = 10; // Number of levels to load initially

export function BlindLevelsList({ blindScheduleId, currentLevelNumber, onLevelPress }: Props) {
  const [levels, setLevels] = useState<BlindLevel[]>([]);
  const [displayedCount, setDisplayedCount] = useState(PAGE_SIZE);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Load blind levels
  useEffect(() => {
    const loadLevels = async () => {
      if (!blindScheduleId) {
        setLevels([]);
        return;
      }

      try {
        setIsLoading(true);
        setError(null);
        const data = await blindScheduleApi.getBlindLevels(blindScheduleId);
        if (Array.isArray(data)) {
          setLevels(data);
        } else {
          setLevels([]);
        }
      } catch (err) {
        console.error('[BlindLevelsList] Failed to load levels:', err);
        setError('Failed to load blind levels');
      } finally {
        setIsLoading(false);
      }
    };

    loadLevels();
  }, [blindScheduleId]);

  // Reset displayed count when current level changes significantly
  useEffect(() => {
    // Show 10 levels including current and upcoming
    const startFromCurrent = Math.max(0, currentLevelNumber - 1);
    const newCount = Math.min(levels.length, startFromCurrent + PAGE_SIZE);
    setDisplayedCount(newCount);
  }, [currentLevelNumber, levels.length]);

  // Load more levels on scroll
  const handleLoadMore = () => {
    if (displayedCount < levels.length) {
      setDisplayedCount(prev => Math.min(prev + PAGE_SIZE, levels.length));
    }
  };

  // Handle level press (for preview, not change)
  const handleLevelPress = (level: BlindLevel) => {
    if (onLevelPress) {
      onLevelPress(level);
    }
  };

  if (isLoading) {
    return (
      <View style={styles.container}>
        <ActivityIndicator size="small" color="#007AFF" />
        <Text style={styles.loadingText}>Loading blind levels...</Text>
      </View>
    );
  }

  if (error) {
    return (
      <View style={styles.container}>
        <Text style={styles.errorText}>{error}</Text>
      </View>
    );
  }

  if (!blindScheduleId || levels.length === 0) {
    return (
      <View style={styles.container}>
        <Text style={styles.emptyText}>No blind schedule selected</Text>
      </View>
    );
  }

  // Show levels from current onwards, plus a few before for context
  const startIndex = Math.max(0, currentLevelNumber - 3);
  const displayedLevels = levels.slice(startIndex, startIndex + displayedCount);

  // Check if there are more levels to load
  const hasMore = startIndex + displayedCount < levels.length;

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Blind Levels</Text>

      <ScrollView
        style={styles.list}
        contentContainerStyle={styles.listContent}
        onScroll={({ nativeEvent }) => {
          const { layoutMeasurement, contentOffset, contentSize } = nativeEvent;
          const isCloseToBottom = layoutMeasurement.height + contentOffset.y >= contentSize.height - 50;
          if (isCloseToBottom && hasMore && !isLoading) {
            handleLoadMore();
          }
        }}
        scrollEventThrottle={400}
      >
        {displayedLevels.map((level) => {
          const isCurrent = level.level === currentLevelNumber;
          const isPast = level.level < currentLevelNumber;
          const isBreak = level.isBreak;

          return (
            <TouchableOpacity
              key={level.blindLevelId}
              style={[
                styles.levelItem,
                isCurrent && styles.currentLevel,
                isPast && styles.pastLevel,
                isBreak && styles.breakLevel,
              ]}
              onPress={() => handleLevelPress(level)}
              disabled={isPast} // Past levels can't be previewed
            >
              <View style={styles.levelHeader}>
                <View style={styles.levelNumberContainer}>
                  {isCurrent && <View style={styles.currentIndicator} />}
                  <Text
                    style={[
                      styles.levelNumber,
                      isCurrent && styles.currentLevelText,
                      isBreak && styles.breakLevelText,
                    ]}
                  >
                    {isBreak ? 'Break' : `L${level.level}`}
                  </Text>
                </View>
                <Text style={styles.duration}>{level.duration}m</Text>
              </View>

              <View style={styles.blindsContainer}>
                {isBreak ? (
                  <Text style={[styles.blinds, styles.breakText]}>Break Time</Text>
                ) : (
                  <>
                    <Text style={[styles.blinds, isCurrent && styles.currentBlinds]}>
                      {level.smallBlind} / {level.bigBlind}
                      {level.ante && <Text style={styles.ante}> • Ante: {level.ante}</Text>}
                    </Text>
                  </>
                )}
              </View>
            </TouchableOpacity>
          );
        })}

        {hasMore && (
          <TouchableOpacity style={styles.loadMoreButton} onPress={handleLoadMore}>
            <Text style={styles.loadMoreText}>Load more levels...</Text>
          </TouchableOpacity>
        )}
      </ScrollView>

      <Text style={styles.footer}>
        Showing {displayedLevels.length} of {levels.length} levels
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginTop: 12,
    borderWidth: 1,
    borderColor: '#e0e0e0',
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: '#333',
    marginBottom: 12,
  },
  loadingText: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
    marginTop: 8,
  },
  errorText: {
    fontSize: 14,
    color: '#FF3B30',
    textAlign: 'center',
  },
  emptyText: {
    fontSize: 14,
    color: '#999',
    textAlign: 'center',
    fontStyle: 'italic',
  },
  list: {
    maxHeight: 300,
  },
  listContent: {
    paddingRight: 4,
  },
  levelItem: {
    backgroundColor: '#f8f9fa',
    borderRadius: 8,
    padding: 12,
    marginBottom: 8,
    borderLeftWidth: 4,
    borderLeftColor: '#e0e0e0',
  },
  currentLevel: {
    backgroundColor: '#e3f2fd',
    borderLeftColor: '#007AFF',
  },
  pastLevel: {
    opacity: 0.5,
  },
  breakLevel: {
    backgroundColor: '#fff8e1',
    borderLeftColor: '#FFC107',
  },
  levelHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  levelNumberContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  currentIndicator: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#007AFF',
    marginRight: 8,
  },
  levelNumber: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
  },
  currentLevelText: {
    color: '#007AFF',
    fontSize: 15,
    fontWeight: '700',
  },
  breakLevelText: {
    color: '#FF8F00',
  },
  duration: {
    fontSize: 12,
    color: '#999',
  },
  blindsContainer: {
    marginTop: 2,
  },
  blinds: {
    fontSize: 16,
    fontWeight: '500',
    color: '#333',
  },
  currentBlinds: {
    fontSize: 18,
    fontWeight: '700',
    color: '#007AFF',
  },
  ante: {
    fontSize: 13,
    color: '#666',
    fontWeight: '400',
  },
  breakText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#FF8F00',
  },
  loadMoreButton: {
    padding: 12,
    alignItems: 'center',
    backgroundColor: '#f0f0f0',
    borderRadius: 8,
    marginTop: 8,
  },
  loadMoreText: {
    fontSize: 14,
    color: '#007AFF',
    fontWeight: '600',
  },
  footer: {
    fontSize: 12,
    color: '#999',
    textAlign: 'center',
    marginTop: 8,
  },
});
