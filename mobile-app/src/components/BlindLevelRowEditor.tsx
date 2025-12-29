/**
 * Blind Level Row Editor Component
 * Inline editable row for creating/editing blind levels
 */

import { View, Text, StyleSheet, TouchableOpacity, TextInput } from 'react-native';

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
  onChange: (data: BlindLevelData) => void;
  onDelete?: () => void;
  onMoveUp?: () => void;
  onMoveDown?: () => void;
  canDelete?: boolean;
  canMoveUp?: boolean;
  canMoveDown?: boolean;
  readOnly?: boolean;
}

export function BlindLevelRowEditor({
  level,
  data,
  onChange,
  onDelete,
  onMoveUp,
  onMoveDown,
  canDelete = true,
  canMoveUp = false,
  canMoveDown = false,
  readOnly = false,
}: Props) {
  const handleFieldChange = (field: keyof BlindLevelData, value: string | boolean) => {
    onChange({
      ...data,
      [field]: value,
    });
  };

  if (readOnly) {
    return (
      <View style={styles.row}>
        <Text style={styles.levelNumber}>#{level}</Text>
        {data.isBreak ? (
          <Text style={styles.breakText}>Break</Text>
        ) : (
          <>
            <Text style={styles.blinds}>
              {data.smallBlind} / {data.bigBlind}
              {data.ante && <Text style={styles.anteText}> • Ante: {data.ante}</Text>}
            </Text>
            <Text style={styles.duration}>{data.duration}m</Text>
          </>
        )}
      </View>
    );
  }

  return (
    <View style={styles.row}>
      <View style={styles.levelColumn}>
        <Text style={styles.levelNumber}>#{level}</Text>
      </View>

      {data.isBreak ? (
        <View style={styles.breakContainer}>
          <Text style={styles.breakLabel}>Break</Text>
          <Text style={styles.durationText}>{data.duration} min</Text>
        </View>
      ) : (
        <>
          <View style={styles.blindColumn}>
            <TextInput
              style={styles.blindInput}
              value={data.smallBlind}
              onChangeText={(text) => handleFieldChange('smallBlind', text)}
              placeholder="SB"
              placeholderTextColor="#999"
              keyboardType="numeric"
              editable={!readOnly}
            />
            <Text style={styles.separator}>/</Text>
            <TextInput
              style={styles.blindInput}
              value={data.bigBlind}
              onChangeText={(text) => handleFieldChange('bigBlind', text)}
              placeholder="BB"
              placeholderTextColor="#999"
              keyboardType="numeric"
              editable={!readOnly}
            />
          </View>

          <TextInput
            style={styles.anteInput}
            value={data.ante}
            onChangeText={(text) => handleFieldChange('ante', text)}
            placeholder="Ante (optional)"
            placeholderTextColor="#999"
            keyboardType="numeric"
            editable={!readOnly}
          />

          <TextInput
            style={styles.durationInput}
            value={data.duration}
            onChangeText={(text) => handleFieldChange('duration', text)}
            placeholder="Min"
            placeholderTextColor="#999"
            keyboardType="numeric"
            editable={!readOnly}
          />
        </>
      )}

      <View style={styles.actionsColumn}>
        {(onMoveUp || onMoveDown) && (
          <>
            {onMoveUp && (
              <TouchableOpacity
                style={[styles.moveButton, !canMoveUp && styles.moveButtonDisabled]}
                onPress={onMoveUp}
                disabled={!canMoveUp}
              >
                <Text style={styles.moveButtonText}>↑</Text>
              </TouchableOpacity>
            )}
            {onMoveDown && (
              <TouchableOpacity
                style={[styles.moveButton, !canMoveDown && styles.moveButtonDisabled]}
                onPress={onMoveDown}
                disabled={!canMoveDown}
              >
                <Text style={styles.moveButtonText}>↓</Text>
              </TouchableOpacity>
            )}
          </>
        )}

        {onDelete && (
          <TouchableOpacity
            style={[styles.deleteButton, !canDelete && styles.deleteButtonDisabled]}
            onPress={onDelete}
            disabled={!canDelete}
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
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
    minHeight: 56,
  },
  levelColumn: {
    width: 40,
    alignItems: 'flex-start',
  },
  levelNumber: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
  },
  blindColumn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 8,
  },
  blindInput: {
    fontSize: 15,
    fontWeight: '500',
    color: '#000',
    paddingVertical: 8,
    paddingHorizontal: 10,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 6,
    minWidth: 60,
    minHeight: 44,
    textAlign: 'center',
  },
  separator: {
    fontSize: 15,
    color: '#999',
    marginHorizontal: 6,
  },
  anteInput: {
    flex: 1,
    fontSize: 14,
    color: '#000',
    paddingVertical: 8,
    paddingHorizontal: 10,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 6,
    marginHorizontal: 4,
    minHeight: 44,
  },
  durationInput: {
    width: 70,
    fontSize: 14,
    color: '#000',
    paddingVertical: 8,
    paddingHorizontal: 10,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 6,
    marginHorizontal: 4,
    minHeight: 44,
    textAlign: 'center',
  },
  breakContainer: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 8,
  },
  breakLabel: {
    fontSize: 15,
    fontWeight: '600',
    color: '#f57c00',
    backgroundColor: '#fff8e1',
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 6,
  },
  breakText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#f57c00',
  },
  durationText: {
    fontSize: 13,
    color: '#666',
  },
  blinds: {
    flex: 1,
    fontSize: 15,
    fontWeight: '500',
    color: '#000',
  },
  anteText: {
    fontSize: 13,
    color: '#666',
  },
  duration: {
    fontSize: 13,
    color: '#666',
  },
  actionsColumn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  moveButton: {
    width: 32,
    height: 32,
    borderRadius: 4,
    backgroundColor: '#e3f2fd',
    alignItems: 'center',
    justifyContent: 'center',
  },
  moveButtonDisabled: {
    backgroundColor: '#f5f5f5',
    opacity: 0.5,
  },
  moveButtonText: {
    fontSize: 16,
    color: '#1976d2',
    fontWeight: '600',
  },
  deleteButton: {
    width: 32,
    height: 32,
    borderRadius: 4,
    backgroundColor: '#ffebee',
    alignItems: 'center',
    justifyContent: 'center',
  },
  deleteButtonDisabled: {
    backgroundColor: '#f5f5f5',
    opacity: 0.5,
  },
  deleteButtonText: {
    fontSize: 16,
    color: '#d32f2f',
    fontWeight: '600',
  },
});
