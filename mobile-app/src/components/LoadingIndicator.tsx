import React from 'react';
import { View, ActivityIndicator, Text, StyleSheet } from 'react-native';

/**
 * Loading Indicator Component
 * Implements T133: Loading states for all async operations
 *
 * Features:
 * - Consistent loading UI across the app
 * - Optional message display
 * - Size variants (small, medium, large)
 */
interface LoadingIndicatorProps {
  size?: 'small' | 'medium' | 'large';
  message?: string;
  color?: string;
}

export const LoadingIndicator: React.FC<LoadingIndicatorProps> = ({
  size = 'medium',
  message,
  color = '#007AFF',
}) => {
  const sizeMap = {
    small: 'small' as const,
    medium: undefined, // Default size
    large: 'large' as const,
  };

  return (
    <View style={styles.container}>
      <ActivityIndicator
        size={sizeMap[size]}
        color={color}
        style={styles.spinner}
      />
      {message && <Text style={styles.message}>{message}</Text>}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  spinner: {
    marginBottom: 16,
  },
  message: {
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
  },
});

export default LoadingIndicator;
