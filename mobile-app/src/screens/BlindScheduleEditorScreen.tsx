/**
 * Blind Schedule Editor Screen
 *
 * Create or edit blind schedules with:
 * - Schedule name, description, starting stack
 * - Break interval and duration
 * - Level management (add, edit, remove, reorder)
 */

import { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ScrollView,
  Alert,
  ActivityIndicator,
} from 'react-native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootStackParamList';
import { useBlindScheduleStore } from '../stores/blindScheduleStore';
import type { BlindLevel, BlindScheduleWithMetadata } from '@shared/types/timer';
import type { BlindScheduleFormData, BlindLevelFormData } from '../types/blindSchedule';

type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'BlindScheduleEditor'>;

interface Props {
  navigation: NavigationProp;
  route: {
    params?: {
      scheduleId?: string;
      copyMode?: boolean;
    };
  };
}

interface FormErrors {
  name?: string;
  startingStack?: string;
  levels?: string;
}

export function BlindScheduleEditorScreen({ navigation, route }: Props) {
  const { scheduleId, copyMode } = route.params || {};
  const isEditMode = !!scheduleId && !copyMode;
  const isCopyMode = !!scheduleId && copyMode;

  const { selectedSchedule, isLoading, fetchSchedule, createSchedule, updateSchedule } = useBlindScheduleStore();

  // Form state
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [startingStack, setStartingStack] = useState('10000');
  const [breakInterval, setBreakInterval] = useState('0');
  const [breakDuration, setBreakDuration] = useState('10');
  const [levels, setLevels] = useState<BlindLevelFormData[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<FormErrors>({});

  // Load schedule data for edit/copy mode
  useEffect(() => {
    if (scheduleId && (isEditMode || isCopyMode)) {
      loadSchedule();
    }
  }, [scheduleId]);

  const loadSchedule = async () => {
    if (!scheduleId) return;

    try {
      await fetchSchedule(scheduleId);
      if (selectedSchedule) {
        setName(isCopyMode ? `${selectedSchedule.name} (Copy)` : selectedSchedule.name);
        setDescription(selectedSchedule.description || '');
        setStartingStack(selectedSchedule.startingStack.toString());
        setBreakInterval(selectedSchedule.breakInterval.toString());
        setBreakDuration(selectedSchedule.breakDuration.toString());
        // Convert levels to form data
        const levelFormData: BlindLevelFormData[] = selectedSchedule.levels.map(level => ({
          levelNumber: level.levelNumber,
          smallBlind: level.smallBlind,
          bigBlind: level.bigBlind,
          ante: level.ante,
          durationMinutes: level.durationMinutes,
          isBreak: level.isBreak,
        }));
        setLevels(levelFormData);
      }
    } catch (err) {
      Alert.alert('Error', 'Failed to load schedule');
      navigation.goBack();
    }
  };

  const validateForm = (): boolean => {
    const newErrors: FormErrors = {};

    if (!name.trim()) {
      newErrors.name = 'Schedule name is required';
    }

    const stackNum = parseInt(startingStack, 10);
    if (isNaN(stackNum) || stackNum < 0) {
      newErrors.startingStack = 'Invalid starting stack';
    }

    if (levels.length === 0) {
      newErrors.levels = 'At least one level is required';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validateForm()) {
      return;
    }

    setIsSubmitting(true);
    try {
      const formData: BlindScheduleFormData = {
        name: name.trim(),
        description: description.trim() || undefined,
        startingStack: parseInt(startingStack, 10),
        breakInterval: parseInt(breakInterval, 10),
        breakDuration: parseInt(breakDuration, 10),
        levels: levels.map((level, index) => ({
          ...level,
          levelNumber: index + 1, // Renumber levels sequentially
        })),
      };

      if (isEditMode && scheduleId) {
        await updateSchedule(scheduleId, formData);
        Alert.alert('Success', 'Schedule updated successfully', [
          { text: 'OK', onPress: () => navigation.goBack() }
        ]);
      } else {
        const newSchedule = await createSchedule(formData);
        Alert.alert('Success', 'Schedule created successfully', [
          { text: 'OK', onPress: () => navigation.goBack() }
        ]);
      }
    } catch (error) {
      Alert.alert('Error', error instanceof Error ? error.message : 'Failed to save schedule');
    } finally {
      setIsSubmitting(false);
    }
  };

  const addLevel = () => {
    const nextLevelNumber = levels.length + 1;
    const lastLevel = levels[levels.length - 1];

    setLevels([
      ...levels,
      {
        levelNumber: nextLevelNumber,
        smallBlind: lastLevel?.bigBlind || 25,
        bigBlind: lastLevel?.bigBlind ? lastLevel.bigBlind * 2 : 50,
        ante: lastLevel?.ante,
        durationMinutes: lastLevel?.durationMinutes || 20,
        isBreak: false,
      },
    ]);
  };

  const addBreakLevel = () => {
    const nextLevelNumber = levels.length + 1;
    setLevels([
      ...levels,
      {
        levelNumber: nextLevelNumber,
        smallBlind: 0,
        bigBlind: 0,
        durationMinutes: parseInt(breakDuration, 10) || 10,
        isBreak: true,
      },
    ]);
  };

  const updateLevel = (index: number, field: keyof BlindLevelFormData, value: string | number | boolean) => {
    const newLevels = [...levels];
    newLevels[index] = { ...newLevels[index], [field]: value };
    setLevels(newLevels);
  };

  const removeLevel = (index: number) => {
    if (levels.length <= 1) {
      Alert.alert('Cannot Remove', 'Schedule must have at least one level');
      return;
    }

    Alert.alert(
      'Remove Level',
      `Remove level ${index + 1}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Remove',
          style: 'destructive',
          onPress: () => {
            const newLevels = levels.filter((_, i) => i !== index);
            // Renumber remaining levels
            const renumbered = newLevels.map((level, i) => ({ ...level, levelNumber: i + 1 }));
            setLevels(renumbered);
          },
        },
      ]
    );
  };

  return (
    <ScrollView style={styles.container} keyboardShouldPersistTaps="handled">
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.cancelButton}>
          <Text style={styles.cancelButtonText}>Cancel</Text>
        </TouchableOpacity>
        <Text style={styles.title}>{isEditMode ? 'Edit Schedule' : isCopyMode ? 'Copy Schedule' : 'Create Schedule'}</Text>
        <TouchableOpacity
          style={[styles.saveButton, isSubmitting && styles.saveButtonDisabled]}
          onPress={handleSubmit}
          disabled={isSubmitting}
        >
          <Text style={styles.saveButtonText}>
            {isSubmitting ? 'Saving...' : 'Save'}
          </Text>
        </TouchableOpacity>
      </View>

      {/* Schedule Info */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Schedule Information</Text>

        {/* Name */}
        <View style={styles.formGroup}>
          <Text style={styles.label}>Name *</Text>
          <TextInput
            style={[styles.input, errors.name && styles.inputError]}
            value={name}
            onChangeText={setName}
            placeholder="e.g., Friday Night Turbo"
            placeholderTextColor="#999"
            autoCapitalize="words"
          />
          {errors.name && <Text style={styles.errorText}>{errors.name}</Text>}
        </View>

        {/* Description */}
        <View style={styles.formGroup}>
          <Text style={styles.label}>Description</Text>
          <TextInput
            style={[styles.input, styles.textArea]}
            value={description}
            onChangeText={setDescription}
            placeholder="Optional description..."
            placeholderTextColor="#999"
            multiline
            numberOfLines={3}
            textAlignVertical="top"
          />
        </View>

        {/* Starting Stack */}
        <View style={styles.formGroup}>
          <Text style={styles.label}>Starting Stack *</Text>
          <TextInput
            style={[styles.input, errors.startingStack && styles.inputError]}
            value={startingStack}
            onChangeText={setStartingStack}
            placeholder="10000"
            placeholderTextColor="#999"
            keyboardType="numeric"
          />
          {errors.startingStack && <Text style={styles.errorText}>{errors.startingStack}</Text>}
        </View>

        {/* Break Settings */}
        <View style={styles.row}>
          <View style={[styles.formGroup, { flex: 1 }]}>
            <Text style={styles.label}>Break Interval</Text>
            <TextInput
              style={styles.input}
              value={breakInterval}
              onChangeText={setBreakInterval}
              placeholder="0"
              placeholderTextColor="#999"
              keyboardType="numeric"
            />
            <Text style={styles.helpText}>Break every N levels (0 = no breaks)</Text>
          </View>

          <View style={[styles.formGroup, { flex: 1 }]}>
            <Text style={styles.label}>Break Duration</Text>
            <TextInput
              style={styles.input}
              value={breakDuration}
              onChangeText={setBreakDuration}
              placeholder="10"
              placeholderTextColor="#999"
              keyboardType="numeric"
            />
            <Text style={styles.helpText}>Minutes</Text>
          </View>
        </View>
      </View>

      {/* Levels */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Blind Levels ({levels.length})</Text>
          <View style={styles.levelButtons}>
            <TouchableOpacity style={styles.addLevelButton} onPress={addLevel}>
              <Text style={styles.addLevelButtonText}>+ Level</Text>
            </TouchableOpacity>
            <TouchableOpacity style={[styles.addLevelButton, styles.addBreakButton]} onPress={addBreakLevel}>
              <Text style={[styles.addLevelButtonText, styles.addBreakButtonText]}>+ Break</Text>
            </TouchableOpacity>
          </View>
        </View>

        {errors.levels && <Text style={styles.errorText}>{errors.levels}</Text>}

        {levels.map((level, index) => (
          <View key={index} style={styles.levelItem}>
            <View style={styles.levelHeader}>
              <Text style={styles.levelNumber}>
                {level.isBreak ? 'Break' : `Level ${level.levelNumber}`}
              </Text>
              <TouchableOpacity onPress={() => removeLevel(index)} style={styles.removeButton}>
                <Text style={styles.removeButtonText}>Remove</Text>
              </TouchableOpacity>
            </View>

            {level.isBreak ? (
              <View style={styles.breakInfo}>
                <Text style={styles.breakText}>Break: {level.durationMinutes} minutes</Text>
              </View>
            ) : (
              <View style={styles.row}>
                <View style={[styles.formGroup, { flex: 1 }]}>
                  <Text style={styles.smallLabel}>Small Blind</Text>
                  <TextInput
                    style={styles.input}
                    value={level.smallBlind.toString()}
                    onChangeText={(value) => updateLevel(index, 'smallBlind', parseInt(value, 10) || 0)}
                    keyboardType="numeric"
                  />
                </View>

                <View style={[styles.formGroup, { flex: 1 }]}>
                  <Text style={styles.smallLabel}>Big Blind</Text>
                  <TextInput
                    style={styles.input}
                    value={level.bigBlind.toString()}
                    onChangeText={(value) => updateLevel(index, 'bigBlind', parseInt(value, 10) || 0)}
                    keyboardType="numeric"
                  />
                </View>

                <View style={[styles.formGroup, { flex: 1 }]}>
                  <Text style={styles.smallLabel}>Ante</Text>
                  <TextInput
                    style={styles.input}
                    value={level.ante?.toString() || ''}
                    onChangeText={(value) => updateLevel(index, 'ante', value ? parseInt(value, 10) : undefined)}
                    keyboardType="numeric"
                    placeholder="Optional"
                  />
                </View>

                <View style={[styles.formGroup, { flex: 1 }]}>
                  <Text style={styles.smallLabel}>Duration</Text>
                  <TextInput
                    style={styles.input}
                    value={level.durationMinutes.toString()}
                    onChangeText={(value) => updateLevel(index, 'durationMinutes', parseInt(value, 10) || 20)}
                    keyboardType="numeric"
                  />
                </View>
              </View>
            )}
          </View>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  title: {
    fontSize: 17,
    fontWeight: '600',
    color: '#000',
  },
  cancelButton: {
    padding: 8,
  },
  cancelButtonText: {
    fontSize: 16,
    color: '#007AFF',
  },
  saveButton: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    backgroundColor: '#4CAF50',
    borderRadius: 8,
  },
  saveButtonDisabled: {
    backgroundColor: '#ccc',
  },
  saveButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#fff',
  },
  section: {
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#333',
    marginBottom: 16,
  },
  formGroup: {
    marginBottom: 16,
  },
  label: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 8,
    color: '#333',
  },
  smallLabel: {
    fontSize: 12,
    fontWeight: '500',
    marginBottom: 4,
    color: '#666',
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
    backgroundColor: '#fafafa',
  },
  inputError: {
    borderColor: '#FF3B30',
  },
  textArea: {
    height: 80,
    textAlignVertical: 'top',
  },
  errorText: {
    fontSize: 14,
    color: '#FF3B30',
    marginTop: 4,
  },
  helpText: {
    fontSize: 12,
    color: '#999',
    marginTop: 4,
  },
  row: {
    flexDirection: 'row',
    gap: 12,
  },
  levelButtons: {
    flexDirection: 'row',
    gap: 8,
  },
  addLevelButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: '#007AFF',
    borderRadius: 6,
  },
  addLevelButtonText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#fff',
  },
  addBreakButton: {
    backgroundColor: '#FFC107',
  },
  addBreakButtonText: {
    color: '#333',
  },
  levelItem: {
    backgroundColor: '#f8f9fa',
    borderRadius: 8,
    padding: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e0e0e0',
  },
  levelHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  levelNumber: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
  },
  removeButton: {
    padding: 4,
  },
  removeButtonText: {
    fontSize: 12,
    color: '#FF3B30',
    fontWeight: '600',
  },
  breakInfo: {
    padding: 8,
    backgroundColor: '#FFF8E1',
    borderRadius: 4,
  },
  breakText: {
    fontSize: 14,
    color: '#FF8F00',
    fontWeight: '500',
  },
});
