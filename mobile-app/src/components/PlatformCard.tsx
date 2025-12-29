import React from 'react';
import { View, StyleSheet, Platform, ViewStyle } from 'react-native';

interface PlatformCardProps {
  children: React.ReactNode;
  style?: ViewStyle;
  elevated?: boolean;
}

/**
 * Platform-specific card component
 * Implements US4-A3: Platform-specific UI patterns
 *
 * iOS Style:
 * - Rounded corners (12px)
 * - Minimal shadows, subtle borders
 * - Light background (#FFFFFF)
 *
 * Android Style (Material Design):
 * - Slight rounded corners (8px)
 * - Elevation shadows
 * - Card surface color
 */
export const PlatformCard: React.FC<PlatformCardProps> = ({
  children,
  style,
  elevated = true,
}) => {
  const platform = Platform.OS;

  return (
    <View
      style={[
        styles.card,
        platform === 'ios' ? styles.iosCard : styles.androidCard,
        elevated && (platform === 'ios' ? styles.iosElevated : styles.androidElevated),
        style,
      ]}
    >
      {children}
    </View>
  );
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#FFFFFF',
    padding: 16,
  },
  iosCard: {
    borderRadius: 12,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: '#E5E5EA',
  },
  androidCard: {
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
  },
  iosElevated: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 8,
    elevation: 2,
  },
  androidElevated: {
    elevation: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 4,
  },
});
