import React from 'react';
import { View, ViewStyle, StyleSheet, Dimensions } from 'react-native';
import { useDeviceContext } from '../../hooks/useDeviceContext';

interface ResponsiveContainerProps {
  children: React.ReactNode;
  style?: ViewStyle;
  tabletStyle?: ViewStyle;
  phoneStyle?: ViewStyle;
}

/**
 * Responsive container that adapts layout based on device type (phone vs tablet)
 * Implements US4-A5: Responsive design for phone vs tablet with breakpoints
 */
export const ResponsiveContainer: React.FC<ResponsiveContainerProps> = ({
  children,
  style,
  tabletStyle,
  phoneStyle,
}) => {
  const { isTablet, isPhone } = useDeviceContext();

  const deviceStyle = isTablet ? tabletStyle : phoneStyle;

  return <View style={[styles.container, style, deviceStyle]}>{children}</View>;
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
});

/**
 * Breakpoint definitions:
 * - Phone: < 600px width (typical mobile devices)
 * - Tablet: >= 600px width (iPad, Android tablets)
 *
 * Based on typical device dimensions:
 * - iPhone SE: 375x667
 * - iPhone 14 Pro Max: 430x932
 * - iPad Mini: 744x1133
 * - iPad Pro 11": 834x1194
 * - iPad Pro 12.9": 1024x1366
 */
