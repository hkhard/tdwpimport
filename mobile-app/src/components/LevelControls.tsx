/**
 * Level Controls Component
 * Buttons for manually adjusting blind levels during tournament play
 * (US5: Manual Level Control During Tournament)
 */

import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';

interface Props {
  currentLevel: number;
  maxLevel: number;
  onLevelUp: () => void;
  onLevelDown: () => void;
  disabled?: boolean;
}

export function LevelControls({ currentLevel, maxLevel, onLevelUp, onLevelDown, disabled }: Props) {
  const canGoUp = currentLevel < maxLevel;
  const canGoDown = currentLevel > 1;

  return (
    <View style={styles.container}>
      <TouchableOpacity
        style={[styles.button, !canGoDown && styles.buttonDisabled]}
        onPress={onLevelDown}
        disabled={!canGoDown || disabled}
        activeOpacity={0.7}
      >
        <Text style={[styles.buttonText, (!canGoDown || disabled) && styles.buttonTextDisabled]}>
          − Level
        </Text>
      </TouchableOpacity>

      <View style={styles.levelContainer}>
        <Text style={styles.levelLabel}>Current Level</Text>
        <Text style={styles.levelNumber}>{currentLevel}</Text>
        <Text style={styles.levelOf}>of {maxLevel}</Text>
      </View>

      <TouchableOpacity
        style={[styles.button, !canGoUp && styles.buttonDisabled]}
        onPress={onLevelUp}
        disabled={!canGoUp || disabled}
        activeOpacity={0.7}
      >
        <Text style={[styles.buttonText, (!canGoUp || disabled) && styles.buttonTextDisabled]}>
          + Level
        </Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#f5f5f5',
    borderRadius: 12,
    marginHorizontal: 16,
    marginBottom: 16,
  },
  button: {
    backgroundColor: '#007AFF',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
    minWidth: 100,
    alignItems: 'center',
  },
  buttonDisabled: {
    backgroundColor: '#ccc',
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  buttonTextDisabled: {
    color: '#999',
  },
  levelContainer: {
    alignItems: 'center',
    flex: 1,
  },
  levelLabel: {
    fontSize: 12,
    color: '#666',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  levelNumber: {
    fontSize: 32,
    fontWeight: '700',
    color: '#333',
    marginVertical: 4,
  },
  levelOf: {
    fontSize: 14,
    color: '#999',
  },
});
