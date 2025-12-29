import React from 'react';
import {
  TouchableOpacity,
  Text,
  StyleSheet,
  Platform,
  GestureResponderEvent,
  ViewStyle,
  TextStyle,
} from 'react-native';

interface PlatformButtonProps {
  title: string;
  onPress: (event: GestureResponderEvent) => void;
  variant?: 'primary' | 'secondary' | 'danger';
  disabled?: boolean;
  style?: ViewStyle;
  textStyle?: TextStyle;
}

/**
 * Platform-specific button component
 * Implements US4-A3: Platform-specific UI patterns (Material Design for Android, iOS design for iOS)
 *
 * iOS Style:
 * - Rounded corners (12px)
 * - Minimal shadows
 * - San Francisco font
 * - Subtle transitions
 *
 * Android Style (Material Design):
 * - Slight rounded corners (4px)
 * - Elevation/shadows
 * - Roboto font
 * - Ripple effect (handled by TouchableOpacity)
 */
export const PlatformButton: React.FC<PlatformButtonProps> = ({
  title,
  onPress,
  variant = 'primary',
  disabled = false,
  style,
  textStyle,
}) => {
  const platform = Platform.OS;

  return (
    <TouchableOpacity
      onPress={onPress}
      disabled={disabled}
      style={[
        styles.button,
        styles[variant],
        platform === 'ios' ? styles.iosButton : styles.androidButton,
        disabled && styles.disabled,
        style,
      ]}
      activeOpacity={platform === 'ios' ? 0.7 : 0.9}
    >
      <Text
        style={[
          styles.text,
          styles[`${variant}Text`],
          platform === 'ios' ? styles.iosText : styles.androidText,
          disabled && styles.disabledText,
          textStyle,
        ]}
      >
        {title}
      </Text>
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  button: {
    paddingVertical: 14,
    paddingHorizontal: 24,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 48, // WCAG AA compliance + 44px touch target
  },
  iosButton: {
    borderRadius: 12,
    backgroundColor: '#007AFF',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  },
  androidButton: {
    borderRadius: 4,
    backgroundColor: '#6200EE',
    elevation: 4,
  },
  primary: {
    // Base styles, overridden by platform styles above
  },
  secondary: {
    backgroundColor: Platform.select({
      ios: '#F2F2F7',
      android: '#E0E0E0',
    }),
  },
  danger: {
    backgroundColor: Platform.select({
      ios: '#FF3B30',
      android: '#D32F2F',
    }),
  },
  text: {
    fontSize: 16,
    fontWeight: '600',
    letterSpacing: Platform.select({
      ios: 0,
      android: 0.5,
    }),
  },
  iosText: {
    color: '#FFFFFF',
    fontFamily: Platform.select({
      ios: 'System',
      android: 'sans-serif',
    }),
  },
  androidText: {
    color: '#FFFFFF',
    fontFamily: 'sans-serif-medium',
    textTransform: 'uppercase',
    fontSize: 14,
    letterSpacing: 1.25,
  },
  primaryText: {
    // Color overridden by platform styles
  },
  secondaryText: {
    color: Platform.select({
      ios: '#007AFF',
      android: '#000000',
    }),
  },
  dangerText: {
    color: '#FFFFFF',
  },
  disabled: {
    backgroundColor: Platform.select({
      ios: '#E5E5EA',
      android: '#F5F5F5',
    }),
    opacity: 0.6,
  },
  disabledText: {
    color: '#8E8E93',
  },
});
