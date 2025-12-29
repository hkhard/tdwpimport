/**
 * Blind Scheme Form Component
 * Form fields for blind scheme metadata
 */

import { View, Text, StyleSheet, TextInput } from 'react-native';

export interface BlindSchemeFormData {
  name: string;
  description: string;
  startingStack: string;
  breakInterval: string;
  breakDuration: string;
}

interface Props {
  data: BlindSchemeFormData;
  onChange: (data: BlindSchemeFormData) => void;
  errors?: Record<string, string>;
  readOnly?: boolean;
}

export function BlindSchemeForm({ data, onChange, errors = {}, readOnly = false }: Props) {
  const handleChange = (field: keyof BlindSchemeFormData, value: string) => {
    onChange({
      ...data,
      [field]: value,
    });
  };

  return (
    <View style={styles.container}>
      {/* Scheme Name */}
      <View style={styles.fieldContainer}>
        <Text style={styles.label}>Scheme Name *</Text>
        <TextInput
          style={[styles.input, errors.name && styles.inputError]}
          value={data.name}
          onChangeText={(text) => handleChange('name', text)}
          placeholder="e.g., Tournament Standard"
          placeholderTextColor="#999"
          editable={!readOnly}
          maxLength={100}
        />
        {errors.name && <Text style={styles.errorText}>{errors.name}</Text>}
      </View>

      {/* Description */}
      <View style={styles.fieldContainer}>
        <Text style={styles.label}>Description</Text>
        <TextInput
          style={[styles.input, styles.textArea]}
          value={data.description}
          onChangeText={(text) => handleChange('description', text)}
          placeholder="Optional description of this blind structure"
          placeholderTextColor="#999"
          editable={!readOnly}
          multiline
          numberOfLines={3}
          maxLength={500}
        />
        {errors.description && <Text style={styles.errorText}>{errors.description}</Text>}
      </View>

      {/* Starting Stack */}
      <View style={styles.fieldContainer}>
        <Text style={styles.label}>Starting Stack *</Text>
        <TextInput
          style={[styles.input, errors.startingStack && styles.inputError]}
          value={data.startingStack}
          onChangeText={(text) => handleChange('startingStack', text)}
          placeholder="e.g., 10000"
          placeholderTextColor="#999"
          editable={!readOnly}
          keyboardType="numeric"
        />
        {errors.startingStack && <Text style={styles.errorText}>{errors.startingStack}</Text>}
      </View>

      {/* Break Settings */}
      <View style={styles.row}>
        <View style={[styles.fieldContainer, styles.halfWidth]}>
          <Text style={styles.label}>Break Interval</Text>
          <TextInput
            style={styles.input}
            value={data.breakInterval}
            onChangeText={(text) => handleChange('breakInterval', text)}
            placeholder="Levels"
            placeholderTextColor="#999"
            editable={!readOnly}
            keyboardType="numeric"
          />
          {errors.breakInterval && <Text style={styles.errorText}>{errors.breakInterval}</Text>}
        </View>

        <View style={[styles.fieldContainer, styles.halfWidth]}>
          <Text style={styles.label}>Break Duration</Text>
          <TextInput
            style={styles.input}
            value={data.breakDuration}
            onChangeText={(text) => handleChange('breakDuration', text)}
            placeholder="Minutes"
            placeholderTextColor="#999"
            editable={!readOnly}
            keyboardType="numeric"
          />
          {errors.breakDuration && <Text style={styles.errorText}>{errors.breakDuration}</Text>}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: '#fff',
    padding: 16,
  },
  fieldContainer: {
    marginBottom: 16,
  },
  halfWidth: {
    flex: 1,
    marginRight: 8,
  },
  row: {
    flexDirection: 'row',
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
    marginBottom: 6,
  },
  input: {
    fontSize: 15,
    color: '#000',
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    backgroundColor: '#fafafa',
  },
  textArea: {
    minHeight: 80,
    textAlignVertical: 'top',
  },
  inputError: {
    borderColor: '#d32f2f',
    backgroundColor: '#ffebee',
  },
  errorText: {
    fontSize: 12,
    color: '#d32f2f',
    marginTop: 4,
  },
});
