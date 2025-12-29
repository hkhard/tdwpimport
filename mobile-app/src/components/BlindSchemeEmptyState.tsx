/**
 * Blind Scheme Empty State Component
 * Shown when there are no blind schemes available
 */

import { View, Text, StyleSheet } from 'react-native';

interface Props {
  message?: string;
}

export function BlindSchemeEmptyState({ message }: Props) {
  return (
    <View style={styles.container}>
      <View style={styles.iconContainer}>
        <Text style={styles.icon}>📋</Text>
      </View>
      <Text style={styles.title}>No Blind Schemes</Text>
      <Text style={styles.message}>
        {message || 'Create your first blind level scheme to get started.'}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
    backgroundColor: '#f9f9f9',
  },
  iconContainer: {
    marginBottom: 16,
  },
  icon: {
    fontSize: 64,
  },
  title: {
    fontSize: 20,
    fontWeight: '600',
    color: '#333',
    marginBottom: 8,
    textAlign: 'center',
  },
  message: {
    fontSize: 15,
    color: '#666',
    textAlign: 'center',
    lineHeight: 22,
  },
});
