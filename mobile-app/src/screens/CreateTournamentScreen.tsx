/**
 * Create Tournament Screen
 *
 * Allows users to create a new tournament with:
 * - Tournament name
 * - Description
 * - Blind schedule selection
 * - Status selection
 */

import { useState } from 'react';
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
import { BlindScheduleSelector } from '../components/BlindScheduleSelector';
import { useBlindScheduleStore } from '../stores/blindScheduleStore';
import * as TournamentService from '../services/tournament/TournamentService';

interface Props {
  onComplete?: () => void;
  onCancel?: () => void;
  onTournamentCreated?: (tournamentId: string) => void;
}

interface FormErrors {
  name?: string;
  blindScheduleId?: string;
}

export function CreateTournamentScreen({ onComplete, onCancel, onTournamentCreated }: Props) {
  // Form state
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [status, setStatus] = useState<'upcoming' | 'active' | 'completed' | 'cancelled'>('upcoming');
  const [blindScheduleId, setBlindScheduleId] = useState<string>();

  // UI state
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<FormErrors>({});

  const { schedules } = useBlindScheduleStore();

  const validateForm = (): boolean => {
    const newErrors: FormErrors = {};

    if (!name.trim()) {
      newErrors.name = 'Tournament name is required';
    }

    if (!blindScheduleId) {
      newErrors.blindScheduleId = 'Please select a blind schedule';
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
      const tournament = await TournamentService.createTournament({
        name: name.trim(),
        description: description.trim() || undefined,
        status,
        blindScheduleId,
      });

      Alert.alert(
        'Success',
        'Tournament created successfully!',
        [
          { text: 'OK', onPress: () => {
            onTournamentCreated?.(tournament.tournamentId);
            onComplete?.();
          }}
        ]
      );
    } catch (error) {
      Alert.alert('Error', error instanceof Error ? error.message : 'Failed to create tournament');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={onCancel} style={styles.cancelButton}>
          <Text style={styles.cancelButtonText}>Cancel</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Create Tournament</Text>
        <TouchableOpacity
          style={[styles.createButton, isSubmitting && styles.createButtonDisabled]}
          onPress={handleSubmit}
          disabled={isSubmitting}
        >
          <Text style={styles.createButtonText}>
            {isSubmitting ? 'Creating...' : 'Create'}
          </Text>
        </TouchableOpacity>
      </View>

      {/* Form */}
      <ScrollView style={styles.content} keyboardShouldPersistTaps="handled">
        {/* Tournament Name */}
        <View style={styles.formGroup}>
          <Text style={styles.label}>Tournament Name *</Text>
          <TextInput
            style={[styles.input, errors.name && styles.inputError]}
            value={name}
            onChangeText={setName}
            placeholder="e.g., Friday Night Poker"
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
            numberOfLines={4}
            textAlignVertical="top"
          />
        </View>

        {/* Status */}
        <View style={styles.formGroup}>
          <Text style={styles.label}>Status</Text>
          <View style={styles.statusOptions}>
            {(['upcoming', 'active', 'completed', 'cancelled'] as const).map((statusOption) => (
              <TouchableOpacity
                key={statusOption}
                style={[
                  styles.statusOption,
                  status === statusOption && styles.statusOptionSelected,
                ]}
                onPress={() => setStatus(statusOption)}
              >
                <Text
                  style={[
                    styles.statusOptionText,
                    status === statusOption && styles.statusOptionTextSelected,
                  ]}
                >
                  {statusOption.charAt(0).toUpperCase() + statusOption.slice(1)}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        </View>

        {/* Blind Schedule Selector */}
        <View style={styles.formGroup}>
          <BlindScheduleSelector
            selectedScheduleId={blindScheduleId}
            onScheduleSelect={setBlindScheduleId}
            error={errors.blindScheduleId}
          />
        </View>

        {/* Info */}
        {schedules.length === 0 && (
          <View style={styles.infoBox}>
            <Text style={styles.infoText}>
              No blind schedules available. Create one first in the Blind Schedules section.
            </Text>
          </View>
        )}
      </ScrollView>
    </View>
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
  createButton: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    backgroundColor: '#4CAF50',
    borderRadius: 8,
  },
  createButtonDisabled: {
    backgroundColor: '#ccc',
  },
  createButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#fff',
  },
  content: {
    flex: 1,
    padding: 20,
  },
  formGroup: {
    marginBottom: 24,
  },
  label: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 8,
    color: '#333',
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
    height: 100,
    textAlignVertical: 'top',
  },
  errorText: {
    fontSize: 14,
    color: '#FF3B30',
    marginTop: 4,
  },
  statusOptions: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  statusOption: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#e0e0e0',
  },
  statusOptionSelected: {
    backgroundColor: '#007AFF',
    borderColor: '#007AFF',
  },
  statusOptionText: {
    fontSize: 14,
    color: '#666',
  },
  statusOptionTextSelected: {
    color: '#fff',
  },
  infoBox: {
    backgroundColor: '#FFF3CD',
    borderLeftWidth: 4,
    borderLeftColor: '#FFC107',
    padding: 12,
    marginBottom: 20,
  },
  infoText: {
    fontSize: 14,
    color: '#666',
  },
});
