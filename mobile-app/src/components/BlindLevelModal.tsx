/**
 * Blind Level Editor Modal
 * Centered popover for editing a single blind level
 */

import { useState, useEffect, useMemo } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  Modal,
  ScrollView,
  Switch,
} from 'react-native';

export interface BlindLevelData {
  smallBlind: string;
  bigBlind: string;
  ante: string;
  duration: string;
  isBreak: boolean;
}

interface Props {
  visible: boolean;
  levelNumber?: number;
  initialData?: BlindLevelData;
  onSave: (data: BlindLevelData) => void;
  onCancel: () => void;
}

// Default values for new level
const DEFAULT_DATA: BlindLevelData = {
  smallBlind: '25',
  bigBlind: '50',
  ante: '',
  duration: '20',
  isBreak: false,
};

// Default values for break
const BREAK_DATA: BlindLevelData = {
  smallBlind: '',
  bigBlind: '',
  ante: '',
  duration: '10',
  isBreak: true,
};

export function BlindLevelModal({
  visible,
  levelNumber,
  initialData,
  onSave,
  onCancel,
}: Props) {
  const [formData, setFormData] = useState<BlindLevelData>(DEFAULT_DATA);

  // Initialize form data when modal opens
  useEffect(() => {
    if (visible) {
      if (initialData) {
        setFormData(initialData);
      } else if (levelNumber === undefined) {
        // New level, use defaults
        setFormData(DEFAULT_DATA);
      }
    }
  }, [visible, initialData, levelNumber]);

  // Toggle break mode
  const toggleBreak = (value: boolean) => {
    if (value) {
      setFormData(BREAK_DATA);
    } else {
      setFormData(DEFAULT_DATA);
    }
  };

  const handleSave = () => {
    // Basic validation
    if (!formData.isBreak) {
      const sb = parseInt(formData.smallBlind, 10);
      const bb = parseInt(formData.bigBlind, 10);
      const dur = parseInt(formData.duration, 10);

      if (!formData.smallBlind || isNaN(sb) || sb <= 0) {
        alert('Small blind is required and must be greater than 0');
        return;
      }
      if (!formData.bigBlind || isNaN(bb) || bb <= 0) {
        alert('Big blind is required and must be greater than 0');
        return;
      }
      if (!formData.duration || isNaN(dur) || dur <= 0) {
        alert('Duration is required and must be greater than 0');
        return;
      }
      if (sb >= bb) {
        alert('Big blind must be greater than small blind');
        return;
      }
    } else {
      const dur = parseInt(formData.duration, 10);
      if (!formData.duration || isNaN(dur) || dur <= 0) {
        alert('Duration is required for breaks');
        return;
      }
    }

    onSave(formData);
  };

  const title = levelNumber !== undefined
    ? `Edit Level #${levelNumber}`
    : 'Add New Level';

  // Computed validation state for check mark enablement
  const isFormValid = useMemo(() => {
    if (formData.isBreak) {
      const dur = parseInt(formData.duration, 10);
      return !isNaN(dur) && dur > 0;
    } else {
      const sb = parseInt(formData.smallBlind, 10);
      const bb = parseInt(formData.bigBlind, 10);
      const dur = parseInt(formData.duration, 10);
      return !isNaN(sb) && sb > 0 &&
             !isNaN(bb) && bb > 0 && bb > sb &&
             !isNaN(dur) && dur > 0;
    }
  }, [formData]);

  return (
    <Modal
      visible={visible}
      animationType="fade"
      transparent={true}
      onRequestClose={onCancel}
    >
      <View style={styles.modalOverlay}>
        <View style={styles.modalContent}>
          {/* Header */}
          <View style={styles.header}>
            <TouchableOpacity onPress={onCancel}>
              <Text style={styles.headerIcon}>✕</Text>
            </TouchableOpacity>
            <Text style={styles.headerTitle}>{title}</Text>
            <TouchableOpacity onPress={handleSave} disabled={!isFormValid}>
              <Text style={[styles.headerIcon, styles.checkMark, !isFormValid && styles.checkMarkDisabled]}>
                ✓
              </Text>
            </TouchableOpacity>
          </View>

          {/* Form */}
          <ScrollView
            style={styles.scrollView}
            keyboardShouldPersistTaps="handled"
            contentContainerStyle={styles.scrollContent}
          >
            {formData.isBreak ? (
              // Break Level Form
              <>
                <View style={styles.breakBanner}>
                  <Text style={styles.breakBannerText}>⚠️ Break Level</Text>
                  <Text style={styles.breakBannerSubtext}>
                    Break levels have no blinds and are for resting
                  </Text>
                </View>

                <View style={styles.fieldContainer}>
                  <Text style={styles.fieldLabel}>Duration (minutes)</Text>
                  <TextInput
                    style={styles.input}
                    value={formData.duration}
                    onChangeText={(text) => setFormData({ ...formData, duration: text })}
                    placeholder="20"
                    placeholderTextColor="#999"
                    keyboardType="numeric"
                  />
                </View>

                <View style={styles.fieldContainer}>
                  <View style={styles.switchRow}>
                    <Text style={styles.fieldLabel}>This is a break level</Text>
                    <Switch
                      value={true}
                      onValueChange={() => toggleBreak(false)}
                      trackColor={{ false: '#ccc', true: '#1976d2' }}
                      thumbColor="#fff"
                    />
                  </View>
                </View>
              </>
            ) : (
              // Regular Level Form
              <>
                <View style={styles.fieldContainer}>
                  <Text style={styles.fieldLabel}>Small Blind</Text>
                  <TextInput
                    style={styles.input}
                    value={formData.smallBlind}
                    onChangeText={(text) => setFormData({ ...formData, smallBlind: text })}
                    placeholder="25"
                    placeholderTextColor="#999"
                    keyboardType="numeric"
                    autoFocus
                  />
                </View>

                <View style={styles.fieldContainer}>
                  <Text style={styles.fieldLabel}>Big Blind</Text>
                  <TextInput
                    style={styles.input}
                    value={formData.bigBlind}
                    onChangeText={(text) => setFormData({ ...formData, bigBlind: text })}
                    placeholder="50"
                    placeholderTextColor="#999"
                    keyboardType="numeric"
                  />
                </View>

                <View style={styles.fieldContainer}>
                  <Text style={styles.fieldLabel}>Ante (Optional)</Text>
                  <TextInput
                    style={styles.input}
                    value={formData.ante}
                    onChangeText={(text) => setFormData({ ...formData, ante: text })}
                    placeholder="Leave empty if no ante"
                    placeholderTextColor="#999"
                    keyboardType="numeric"
                  />
                </View>

                <View style={styles.fieldContainer}>
                  <Text style={styles.fieldLabel}>Duration (minutes)</Text>
                  <TextInput
                    style={styles.input}
                    value={formData.duration}
                    onChangeText={(text) => setFormData({ ...formData, duration: text })}
                    placeholder="20"
                    placeholderTextColor="#999"
                    keyboardType="numeric"
                  />
                </View>

                <View style={styles.fieldContainer}>
                  <View style={styles.switchRow}>
                    <Text style={styles.fieldLabel}>This is a break level</Text>
                    <Switch
                      value={false}
                      onValueChange={() => toggleBreak(true)}
                      trackColor={{ false: '#ccc', true: '#1976d2' }}
                      thumbColor="#fff"
                    />
                  </View>
                </View>
              </>
            )}

            {/* Info Section */}
            <View style={styles.infoSection}>
              <Text style={styles.infoTitle}>Tips</Text>
              <Text style={styles.infoText}>
                • Big blind must be greater than small blind
              </Text>
              <Text style={styles.infoText}>
                • Duration is in minutes
              </Text>
              <Text style={styles.infoText}>
                • Break levels have no blinds
              </Text>
              <Text style={styles.infoText}>
                • Tap ✓ when all fields are valid to save
              </Text>
            </View>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContent: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 20,
    width: '100%',
    maxWidth: 400,
    maxHeight: '80%',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#000',
    flex: 1,
    textAlign: 'center',
  },
  headerIcon: {
    fontSize: 24,
    color: '#666',
    padding: 4,
  },
  checkMark: {
    color: '#4caf50',
  },
  checkMarkDisabled: {
    color: '#ccc',
    opacity: 0.4,
  },
  scrollView: {
    // flex: 1 removed - let content define height naturally
  },
  scrollContent: {
    paddingBottom: 16,
  },
  breakBanner: {
    backgroundColor: '#fff8e1',
    borderLeftWidth: 4,
    borderLeftColor: '#f57c00',
    padding: 16,
    borderRadius: 8,
    marginBottom: 20,
  },
  breakBannerText: {
    fontSize: 18,
    fontWeight: '700',
    color: '#f57c00',
    marginBottom: 4,
  },
  breakBannerSubtext: {
    fontSize: 14,
    color: '#666',
  },
  fieldContainer: {
    marginBottom: 20,
  },
  fieldLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    marginBottom: 8,
  },
  input: {
    backgroundColor: '#fff',
    fontSize: 18,
    padding: 16,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    minHeight: 56,
  },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#fff',
    padding: 16,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    minHeight: 56,
  },
  infoSection: {
    backgroundColor: '#e3f2fd',
    padding: 16,
    borderRadius: 8,
    marginTop: 20,
  },
  infoTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1976d2',
    marginBottom: 8,
  },
  infoText: {
    fontSize: 14,
    color: '#424242',
    lineHeight: 20,
    marginBottom: 4,
  },
});
