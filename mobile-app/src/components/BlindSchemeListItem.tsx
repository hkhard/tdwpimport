/**
 * Blind Scheme List Item Component
 * Displays a blind scheme summary in a list
 */

import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import type { BlindSchemeListItem } from '@shared/types/timer';

interface Props {
  scheme: BlindSchemeListItem;
  onPress: () => void;
}

export function BlindSchemeListItem({ scheme, onPress }: Props) {
  const formatDuration = (minutes: number): string => {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours > 0) {
      return `${hours}h ${mins}m`;
    }
    return `${mins}m`;
  };

  return (
    <TouchableOpacity style={styles.container} onPress={onPress} activeOpacity={0.7}>
      <View style={styles.content}>
        <View style={styles.header}>
          <Text style={styles.name} numberOfLines={1}>
            {scheme.name}
          </Text>
          {scheme.isDefault && (
            <View style={styles.defaultBadge}>
              <Text style={styles.defaultBadgeText}>Default</Text>
            </View>
          )}
        </View>

        {scheme.description && (
          <Text style={styles.description} numberOfLines={2}>
            {scheme.description}
          </Text>
        )}

        <View style={styles.metadata}>
          <View style={styles.metadataItem}>
            <Text style={styles.metadataLabel}>Levels:</Text>
            <Text style={styles.metadataValue}>{scheme.levelCount}</Text>
          </View>

          <View style={styles.metadataItem}>
            <Text style={styles.metadataLabel}>Duration:</Text>
            <Text style={styles.metadataValue}>{formatDuration(scheme.totalDurationMinutes)}</Text>
          </View>

          <View style={styles.metadataItem}>
            <Text style={styles.metadataLabel}>Stack:</Text>
            <Text style={styles.metadataValue}>{scheme.startingStack}</Text>
          </View>
        </View>
      </View>

      <View style={styles.chevron}>
        <Text style={styles.chevronText}>{'›'}</Text>
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
    paddingVertical: 12,
    paddingHorizontal: 16,
    minHeight: 80,
    alignItems: 'center',
  },
  content: {
    flex: 1,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  name: {
    fontSize: 16,
    fontWeight: '600',
    color: '#000',
    flex: 1,
    marginRight: 8,
  },
  defaultBadge: {
    backgroundColor: '#e3f2fd',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
  defaultBadgeText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#1976d2',
  },
  description: {
    fontSize: 14,
    color: '#666',
    marginBottom: 8,
  },
  metadata: {
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  metadataItem: {
    flexDirection: 'row',
    marginRight: 16,
  },
  metadataLabel: {
    fontSize: 13,
    color: '#888',
    marginRight: 4,
  },
  metadataValue: {
    fontSize: 13,
    fontWeight: '500',
    color: '#333',
  },
  chevron: {
    marginLeft: 8,
    justifyContent: 'center',
  },
  chevronText: {
    fontSize: 24,
    color: '#ccc',
    fontWeight: '300',
  },
});
