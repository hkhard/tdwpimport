/**
 * Blind Scheme Editor Screen
 * Create/edit blind schemes with form and level editor
 */

import { useState, useCallback, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { BlindSchemeForm, BlindSchemeFormData } from '../components/BlindSchemeForm';
import { BlindLevelRowEditor, BlindLevelData } from '../components/BlindLevelRowEditor';
import { BlindLevelSummary } from '../components/BlindLevelSummary';
import { BlindLevelModal } from '../components/BlindLevelModal';
import { useBlindScheduleStore } from '../stores/blindScheduleStore';
import { flushQueue } from '../utils/syncQueue';
import { blindScheduleApi } from '../services/api/blindScheduleApi';

type Mode = 'create' | 'edit';

interface Props {
  mode: Mode;
  schemeId?: string;
  onBack: () => void;
  onSuccess?: () => void;
}

// Initial form state for tracking unsaved changes
const INITIAL_FORM_DATA: BlindSchemeFormData = {
  name: '',
  description: '',
  startingStack: '10000',
  breakInterval: '0',
  breakDuration: '10',
};

const INITIAL_LEVELS: BlindLevelData[] = [
  {
    smallBlind: '25',
    bigBlind: '50',
    ante: '',
    duration: '20',
    isBreak: false,
  },
];

export function BlindSchemeEditorScreen({ mode, schemeId, onBack, onSuccess }: Props) {
  const { createSchedule, updateScheme, isLoading, error, fetchSchedule } = useBlindScheduleStore();

  const [formData, setFormData] = useState<BlindSchemeFormData>(INITIAL_FORM_DATA);
  const [levels, setLevels] = useState<BlindLevelData[]>(INITIAL_LEVELS);

  // Track original state for dirty detection
  const [originalFormData, setOriginalFormData] = useState<BlindSchemeFormData>(INITIAL_FORM_DATA);
  const [originalLevels, setOriginalLevels] = useState<BlindLevelData[]>(INITIAL_LEVELS);

  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(false);

  // Modal state
  const [modalVisible, setModalVisible] = useState(false);
  const [editingLevelIndex, setEditingLevelIndex] = useState<number | null>(null);

  // Check if form has unsaved changes
  const hasUnsavedChanges = useCallback((): boolean => {
    if (mode === 'create') {
      // For create mode, check if anything differs from initial empty state
      return (
        formData.name !== INITIAL_FORM_DATA.name ||
        formData.description !== INITIAL_FORM_DATA.description ||
        formData.startingStack !== INITIAL_FORM_DATA.startingStack ||
        formData.breakInterval !== INITIAL_FORM_DATA.breakInterval ||
        formData.breakDuration !== INITIAL_FORM_DATA.breakDuration ||
        levels.length !== INITIAL_LEVELS.length ||
        levels.some((level, i) => {
          const init = INITIAL_LEVELS[i];
          if (!init) return true;
          return (
            level.smallBlind !== init.smallBlind ||
            level.bigBlind !== init.bigBlind ||
            level.ante !== init.ante ||
            level.duration !== init.duration ||
            level.isBreak !== init.isBreak
          );
        })
      );
    } else {
      // For edit mode, compare with original loaded state
      return JSON.stringify({ formData, levels }) !== JSON.stringify({ originalFormData, originalLevels });
    }
  }, [mode, formData, levels, originalFormData, originalLevels]);

  // Load existing scheme data in edit mode
  useEffect(() => {
    if (mode === 'edit' && schemeId) {
      setLoading(true);
      const loadScheme = async () => {
        try {
          const scheme = await blindScheduleApi.getBlindSchedule(schemeId);

          // Diagnostic logging
          console.log('[Editor] API Response - scheme:', scheme.name);
          console.log('[Editor] API Response - levels:', JSON.stringify(scheme.levels, null, 2));

          // Populate form data
          const populatedFormData: BlindSchemeFormData = {
            name: scheme.name,
            description: scheme.description || '',
            startingStack: scheme.startingStack?.toString() || '10000',
            breakInterval: scheme.breakInterval?.toString() || '0',
            breakDuration: scheme.breakDuration?.toString() || '10',
          };

          // Populate levels (convert BlindLevel to BlindLevelData)
          // Note: API returns snake_case, need to map accordingly
          const populatedLevels: BlindLevelData[] = scheme.levels.map((level) => ({
            smallBlind: (level as any).small_blind?.toString() || '0',
            bigBlind: (level as any).big_blind?.toString() || '0',
            ante: (level as any).ante?.toString() || '',
            duration: (level as any).duration?.toString() || '20',
            isBreak: (level as any).is_break === 1,
          }));

          // Diagnostic logging
          console.log('[Editor] Populated levels:', JSON.stringify(populatedLevels, null, 2));

          setFormData(populatedFormData);
          setLevels(populatedLevels);

          // Store originals for dirty detection
          setOriginalFormData(populatedFormData);
          setOriginalLevels(populatedLevels);
        } catch (err) {
          console.error('[Editor] Failed to load scheme:', err);
          Alert.alert('Load Failed', 'Failed to load blind scheme. Please try again.');
          onBack();
        } finally {
          setLoading(false);
        }
      };

      loadScheme();
    }
  }, [mode, schemeId, onBack]);

  const handleFormChange = useCallback((data: BlindSchemeFormData) => {
    setFormData(data);
    // Clear field error when user starts typing
    setFormErrors((prev) => {
      const updated = { ...prev };
      if (data.name) delete updated.name;
      if (data.startingStack) delete updated.startingStack;
      return updated;
    });
  }, []);

  const handleLevelChange = useCallback((index: number, data: BlindLevelData) => {
    setLevels((prev) => {
      const updated = [...prev];
      updated[index] = data;
      return updated;
    });
  }, []);

  const handleAddLevel = useCallback(() => {
    // Open modal to add new level
    setEditingLevelIndex(levels.length);
    setModalVisible(true);
  }, [levels.length]);

  const handleAddBreak = useCallback(() => {
    // Open modal to add break (set index to length for new level)
    setEditingLevelIndex(levels.length);
    setModalVisible(true);
  }, [levels.length]);

  // Modal handlers
  const handleEditLevel = useCallback((index: number) => {
    setEditingLevelIndex(index);
    setModalVisible(true);
  }, [levels]);

  const handleModalSave = useCallback((data: BlindLevelData) => {
    if (editingLevelIndex === null) return;

    setLevels((prevLevels) => {
      if (editingLevelIndex === prevLevels.length) {
        // Adding new level
        return [...prevLevels, data];
      } else {
        // Editing existing level
        const updated = [...prevLevels];
        updated[editingLevelIndex] = data;
        return updated;
      }
    });
    setModalVisible(false);
    setEditingLevelIndex(null);
  }, [editingLevelIndex]);

  const handleModalCancel = useCallback(() => {
    setModalVisible(false);
    setEditingLevelIndex(null);
  }, []);

  const handleDeleteLevel = useCallback((index: number) => {
    if (levels.length <= 1) {
      Alert.alert('Cannot Delete', 'You must have at least one level.');
      return;
    }
    setLevels((prev) => prev.filter((_, i) => i !== index));
  }, [levels.length]);

  const handleMoveLevel = useCallback((index: number, direction: 'up' | 'down') => {
    setLevels((prev) => {
      const updated = [...prev];
      if (direction === 'up' && index > 0) {
        [updated[index - 1], updated[index]] = [updated[index], updated[index - 1]];
      } else if (direction === 'down' && index < updated.length - 1) {
        [updated[index], updated[index + 1]] = [updated[index + 1], updated[index]];
      }
      return updated;
    });
  }, []);

  const validateForm = useCallback((): boolean => {
    const errors: Record<string, string> = {};

    if (!formData.name.trim()) {
      errors.name = 'Scheme name is required';
    }

    if (!formData.startingStack.trim()) {
      errors.startingStack = 'Starting stack is required';
    } else {
      const stack = parseInt(formData.startingStack, 10);
      if (isNaN(stack) || stack <= 0) {
        errors.startingStack = 'Starting stack must be a positive number';
      }
    }

    if (formData.breakInterval) {
      const interval = parseInt(formData.breakInterval, 10);
      const duration = parseInt(formData.breakDuration, 10);
      if (isNaN(interval) || interval < 0) {
        errors.breakInterval = 'Break interval must be 0 or greater';
      }
      if (isNaN(duration) || duration <= 0) {
        errors.breakDuration = 'Break duration must be a positive number';
      }
    }

    // Validate levels
    let hasValidLevel = false;
    levels.forEach((level, index) => {
      if (level.isBreak) {
        if (!level.duration || parseInt(level.duration, 10) <= 0) {
          errors[`level_${index}_duration`] = 'Break duration is required';
        }
      } else {
        const sb = parseInt(level.smallBlind, 10);
        const bb = parseInt(level.bigBlind, 10);
        const dur = parseInt(level.duration, 10);

        if (!level.smallBlind || isNaN(sb) || sb <= 0) {
          errors[`level_${index}_smallBlind`] = 'Small blind is required';
        }
        if (!level.bigBlind || isNaN(bb) || bb <= 0) {
          errors[`level_${index}_bigBlind`] = 'Big blind is required';
        }
        if (!level.duration || isNaN(dur) || dur <= 0) {
          errors[`level_${index}_duration`] = 'Duration is required';
        }
        if (sb && bb && sb >= bb) {
          errors[`level_${index}_bigBlind`] = 'Big blind must be greater than small blind';
        }
        if (!hasValidLevel && sb && bb && dur) {
          hasValidLevel = true;
        }
      }
    });

    if (!hasValidLevel) {
      errors.levels = 'You must have at least one valid level';
    }

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  }, [formData, levels]);

  const handleSave = useCallback(async () => {
    if (!validateForm()) {
      Alert.alert('Validation Error', 'Please fix the errors before saving.');
      return;
    }

    setSaving(true);
    try {
      // Prepare levels for API (align with CreateBlindLevelInput type)
      const apiLevels = levels.map((level) => {
        if (level.isBreak) {
          // Break levels must have 0 for blinds
          return {
            smallBlind: 0,
            bigBlind: 0,
            duration: parseInt(level.duration, 10),
            isBreak: true,
          };
        }
        return {
          smallBlind: parseInt(level.smallBlind, 10),
          bigBlind: parseInt(level.bigBlind, 10),
          ante: level.ante ? parseInt(level.ante, 10) : undefined,
          duration: parseInt(level.duration, 10),
          isBreak: false,
        };
      });

      // Diagnostic logging
      console.log('[Editor] Saving to API - levels:', JSON.stringify(apiLevels, null, 2));

      // Prepare scheme data (align with shared CreateBlindSchemeInput type)
      const schemeData = {
        name: formData.name.trim(),
        description: formData.description.trim() || undefined,
        startingStack: parseInt(formData.startingStack, 10),
        breakInterval: parseInt(formData.breakInterval, 10) || 0,
        breakDuration: parseInt(formData.breakDuration, 10) || 10,
        levels: apiLevels,
      };

      if (mode === 'create') {
        await createSchedule(schemeData);
      } else {
        // Edit mode - update existing scheme
        if (!schemeId) {
          throw new Error('Scheme ID is required for edit mode');
        }

        // Check if this is a default scheme (should have been handled by API, but double-check)
        // The API will return 403 if isDefault=true and automatically create a copy instead
        await updateScheme(schemeId, schemeData);
      }

      // Flush sync queue if online
      try {
        await flushQueue(blindScheduleApi);
      } catch (syncError) {
        console.warn('[Editor] Sync queue flush failed (will retry later):', syncError);
      }

      Alert.alert(
        'Success',
        mode === 'create' ? 'Blind scheme created!' : 'Blind scheme updated!',
        [
          {
            text: 'OK',
            onPress: () => {
              onSuccess?.();
              onBack();
            },
          },
        ]
      );
    } catch (err) {
      console.error('[Editor] Save failed:', err);
      const errorMessage = (err as Error).message || error || 'Failed to save blind scheme. Please try again.';

      // Check if this was a default scheme that got duplicated
      if (errorMessage.includes('default') || errorMessage.includes('copy')) {
        Alert.alert(
          'Copy Created',
          'Default schemes cannot be edited. A copy has been created instead.',
          [
            {
              text: 'OK',
              onPress: () => {
                onSuccess?.();
                onBack();
              },
            },
          ]
        );
      } else {
        Alert.alert('Save Failed', errorMessage);
      }
    } finally {
      setSaving(false);
    }
  }, [validateForm, levels, formData, createSchedule, updateScheme, mode, schemeId, error, onSuccess, onBack]);

  // Handle cancel with unsaved changes check
  const handleCancel = useCallback(() => {
    if (hasUnsavedChanges()) {
      Alert.alert(
        'Discard Changes?',
        mode === 'create'
          ? 'You have unsaved changes. Do you want to discard them and exit?'
          : 'You have unsaved changes. Do you want to discard them and go back?',
        [
          {
            text: 'Keep Editing',
            style: 'cancel',
          },
          {
            text: 'Discard',
            style: 'destructive',
            onPress: onBack,
          },
        ]
      );
    } else {
      onBack();
    }
  }, [hasUnsavedChanges, mode, onBack]);

  // Show loading spinner while fetching scheme in edit mode
  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#1976d2" />
          <Text style={styles.loadingText}>Loading scheme...</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.container}
        keyboardVerticalOffset={100}
      >
        <View style={styles.header}>
          <TouchableOpacity onPress={handleCancel} style={styles.backButton}>
            <Text style={styles.backButtonText}>Cancel</Text>
          </TouchableOpacity>
          <Text style={styles.headerTitle}>
            {mode === 'create' ? 'New Blind Scheme' : 'Edit Scheme'}
          </Text>
          <TouchableOpacity
            style={[styles.saveButton, saving && styles.saveButtonDisabled]}
            onPress={handleSave}
            disabled={saving}
          >
            {saving ? (
              <ActivityIndicator size="small" color="#fff" />
            ) : (
              <Text style={styles.saveButtonText}>Save</Text>
            )}
          </TouchableOpacity>
        </View>

        <ScrollView
          style={styles.scrollView}
          keyboardShouldPersistTaps="handled"
          contentContainerStyle={styles.scrollContent}
        >
        {/* Scheme Metadata Form */}
        <BlindSchemeForm
          data={formData}
          onChange={handleFormChange}
          errors={formErrors}
        />

        {/* Levels Section */}
        <View style={styles.levelsSection}>
          <View style={styles.levelsHeader}>
            <Text style={styles.levelsTitle}>Blind Levels</Text>
            <View style={styles.levelButtons}>
              <TouchableOpacity
                style={styles.addButton}
                onPress={handleAddLevel}
              >
                <Text style={styles.addButtonText}>+ Level</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.addButton, styles.breakButton]}
                onPress={handleAddBreak}
              >
                <Text style={styles.addButtonText}>+ Break</Text>
              </TouchableOpacity>
            </View>
          </View>

          {formErrors.levels && (
            <Text style={styles.sectionError}>{formErrors.levels}</Text>
          )}

          {/* Level Rows */}
          {levels.map((level, index) => (
            <BlindLevelSummary
              key={index}
              level={index + 1}
              data={level}
              onPress={() => handleEditLevel(index)}
              onDelete={() => handleDeleteLevel(index)}
              onMoveUp={() => handleMoveLevel(index, 'up')}
              onMoveDown={() => handleMoveLevel(index, 'down')}
              canDelete={levels.length > 1}
              canMoveUp={index > 0}
              canMoveDown={index < levels.length - 1}
            />
          ))}
        </View>

        {/* Info Section */}
        <View style={styles.infoSection}>
          <Text style={styles.infoTitle}>Tips</Text>
          <Text style={styles.infoText}>
            • Enter blind amounts and duration for each level
          </Text>
          <Text style={styles.infoText}>
            • Add breaks between levels as needed
          </Text>
          <Text style={styles.infoText}>
            • Use ↑↓ buttons to reorder levels
          </Text>
          <Text style={styles.infoText}>
            • Big blind must be greater than small blind
          </Text>
        </View>
        </ScrollView>

        {/* Blind Level Editor Modal */}
        <BlindLevelModal
          key={editingLevelIndex ?? 'new'}
          visible={modalVisible}
          levelNumber={editingLevelIndex !== null ? editingLevelIndex + 1 : undefined}
          initialData={editingLevelIndex !== null ? levels[editingLevelIndex] : undefined}
          onSave={handleModalSave}
          onCancel={handleModalCancel}
        />
      </KeyboardAvoidingView>
    </SafeAreaView>
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
    backgroundColor: '#fff',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  backButton: {
    paddingVertical: 4,
  },
  backButtonText: {
    fontSize: 16,
    color: '#1976d2',
    fontWeight: '600',
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#000',
    flex: 1,
    textAlign: 'center',
  },
  saveButton: {
    backgroundColor: '#1976d2',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 6,
  },
  saveButtonDisabled: {
    backgroundColor: '#90caf9',
  },
  saveButtonText: {
    color: '#fff',
    fontWeight: '600',
    fontSize: 15,
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingBottom: 20,
  },
  levelsSection: {
    backgroundColor: '#fff',
    marginTop: 12,
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: '#e0e0e0',
  },
  levelsHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  levelsTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#000',
  },
  levelButtons: {
    flexDirection: 'row',
    gap: 8,
  },
  addButton: {
    backgroundColor: '#1976d2',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
  },
  breakButton: {
    backgroundColor: '#f57c00',
  },
  addButtonText: {
    color: '#fff',
    fontWeight: '600',
    fontSize: 13,
  },
  sectionError: {
    fontSize: 13,
    color: '#d32f2f',
    paddingHorizontal: 16,
    paddingTop: 8,
  },
  infoSection: {
    margin: 16,
    padding: 16,
    backgroundColor: '#e3f2fd',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#bbdefb',
  },
  infoTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: '#1976d2',
    marginBottom: 8,
  },
  infoText: {
    fontSize: 13,
    color: '#424242',
    lineHeight: 20,
    marginBottom: 4,
  },
});
