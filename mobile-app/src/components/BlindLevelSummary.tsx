/**
 * Blind Level Summary Component
 * Read-only display of a blind level with tap-to-edit functionality
 */

import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';

export interface BlindLevelData {
  smallBlind: string;
  bigBlind: string;
  ante: string;
  duration: string;
  isBreak: boolean;
}

interface Props {
  level: number;
  data: BlindLevelData;
  onPress: () => void;
  onDelete?: () => void;
  onMoveUp?: () => void;
  onMoveDown?: () => void;
  onInsertAfter?: () => void;
  canDelete?: boolean;
  canMoveUp?: boolean;
  canMoveDown?: boolean;
}

export function BlindLevelSummary({
  level,
  data,
  onPress,
  onDelete,
  onMoveUp,
  onMoveDown,
  onInsertAfter,
  canDelete = true,
  canMoveUp = false,
  canMoveDown = false,
}: Props) {
  return (
    <View style={styles.row} accessibilityLabel={`Blind level ${level}`}>
      {/* Level Number */}
      <View style={styles.levelColumn}>
        <Text style={styles.levelNumber}>#{level}</Text>
      </View>

      {/* Level Content */}
      <TouchableOpacity
        style={styles.contentColumn}
        onPress={onPress}
        activeOpacity={0.7}
        accessibilityLabel={`Edit level ${level}`}
        accessibilityHint={`Shows ${data.isBreak ? 'break' : `${data.smallBlind}/${data.bigBlind}`} level`}
        accessibilityRole="button"
      >
        {data.isBreak ? (
          <View style={styles.breakContent}>
            <Text style={styles.breakLabel}>Break</Text>
            <Text style={styles.durationText}>{data.duration} min</Text>
          </View>
        ) : (
          <View style={styles.blindContent}>
            <Text style={styles.blinds}>
              {data.smallBlind} / {data.bigBlind}
            </Text>
            {data.ante && (
              <Text style={styles.anteText}> • Ante: {data.ante}</Text>
            )}
            <Text style={styles.durationText}> • {data.duration} min</Text>
          </View>
        )}
        <Text style={styles.editHint}>Tap to edit</Text>
      </TouchableOpacity>

      {/* Actions */}
      <View style={styles.actionsColumn}>
        {(onMoveUp || onMoveDown) && (
          <>
            {onMoveUp && (
              <TouchableOpacity
                style={[styles.moveButton, !canMoveUp && styles.moveButtonDisabled]}
                onPress={onMoveUp}
                disabled={!canMoveUp}
                accessibilityLabel="Move level up"
                accessibilityHint="Move this level up one position"
                accessibilityRole="button"
              >
                <Text style={styles.moveButtonText}>↑</Text>
              </TouchableOpacity>
            )}
            {onMoveDown && (
              <TouchableOpacity
                style={[styles.moveButton, !canMoveDown && styles.moveButtonDisabled]}
                onPress={onMoveDown}
                disabled={!canMoveDown}
                accessibilityLabel="Move level down"
                accessibilityHint="Move this level down one position"
                accessibilityRole="button"
              >
                <Text style={styles.moveButtonText}>↓</Text>
              </TouchableOpacity>
            )}
          </>
        )}

        {onInsertAfter && (
          <TouchableOpacity
            style={styles.insertButton}
            onPress={onInsertAfter}
            accessibilityLabel="Insert level after"
            accessibilityHint="Add a new level after this one"
            accessibilityRole="button"
          >
            <Text style={styles.insertButtonText}>+</Text>
          </TouchableOpacity>
        )}

        {onDelete && (
          <TouchableOpacity
            style={[styles.deleteButton, !canDelete && styles.deleteButtonDisabled]}
            onPress={onDelete}
            disabled={!canDelete}
            accessibilityLabel="Delete level"
            accessibilityHint={canDelete ? "Remove this level" : "Cannot delete - at least one level required"}
            accessibilityRole="button"
          >
            <Text style={styles.deleteButtonText}>✕</Text>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    paddingHorizontal: 12,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
    minHeight: 64,
  },
  levelColumn: {
    width: 50,
    alignItems: 'flex-start',
  },
  levelNumber: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1976d2',
  },
  contentColumn: {
    flex: 1,
    paddingHorizontal: 12,
    justifyContent: 'center',
  },
  blindContent: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
  },
  blinds: {
    fontSize: 16,
    fontWeight: '600',
    color: '#000',
  },
  anteText: {
    fontSize: 14,
    color: '#666',
  },
  durationText: {
    fontSize: 14,
    color: '#666',
  },
  breakContent: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  breakLabel: {
    fontSize: 15,
    fontWeight: '700',
    color: '#f57c00',
    backgroundColor: '#fff8e1',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 6,
  },
  editHint: {
    fontSize: 12,
    color: '#1976d2',
    marginTop: 4,
    fontStyle: 'italic',
  },
  actionsColumn: {
    flexDirection: 'column',
    alignItems: 'center',
    gap: 6,
  },
  moveButton: {
    width: 36,
    height: 36,
    borderRadius: 6,
    backgroundColor: '#e3f2fd',
    alignItems: 'center',
    justifyContent: 'center',
  },
  moveButtonDisabled: {
    backgroundColor: '#f5f5f5',
    opacity: 0.4,
  },
  moveButtonText: {
    fontSize: 18,
    color: '#1976d2',
    fontWeight: '700',
    lineHeight: 20,
  },
  insertButton: {
    width: 36,
    height: 36,
    borderRadius: 6,
    backgroundColor: '#e8f5e9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  insertButtonText: {
    fontSize: 20,
    color: '#4caf50',
    fontWeight: '700',
    lineHeight: 22,
  },
  deleteButton: {
    width: 36,
    height: 36,
    borderRadius: 6,
    backgroundColor: '#ffebee',
    alignItems: 'center',
    justifyContent: 'center',
  },
  deleteButtonDisabled: {
    backgroundColor: '#f5f5f5',
    opacity: 0.4,
  },
  deleteButtonText: {
    fontSize: 18,
    color: '#d32f2f',
    fontWeight: '700',
    lineHeight: 20,
  },
});
