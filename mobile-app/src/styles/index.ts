import { Platform } from 'react-native';
import { iOSStyles } from './ios';
import { AndroidStyles } from './android';

/**
 * Unified styling system that selects appropriate styles based on platform
 * Implements US4-A3: Platform-specific UI patterns
 *
 * Usage:
 * ```tsx
 * import { styles, colors, typography } from '../styles';
 *
 * const MyComponent = () => (
 *   <View style={styles.card}>
 *     <Text style={typography.title}>{title}</Text>
 *   </View>
 * );
 * ```
 */

// Platform-specific style collections
const styles = Platform.select({
  ios: iOSStyles,
  android: AndroidStyles,
});

// Export platform-aware styles
export const colors = styles?.colors || iOSStyles.colors;
export const typography = styles?.typography || iOSStyles.typography;
export const spacing = styles?.spacing || iOSStyles.spacing;
export const borders = styles?.borders || iOSStyles.borders;

// Platform-specific shadows/elevation
export const shadows = Platform.select({
  ios: iOSStyles.shadows,
  android: AndroidStyles.elevation,
});

// Common shared styles that work on both platforms
export const commonStyles = {
  container: {
    flex: 1,
    backgroundColor: colors.background.default,
  },
  card: {
    backgroundColor: colors.background.paper,
    borderRadius: borders.borderRadius.md,
    padding: spacing.md,
    ...Platform.select({
      ios: shadows.medium,
      android: shadows.level2,
    }),
  },
  button: {
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.lg,
    borderRadius: borders.borderRadius.md,
    alignItems: 'center' as const,
    justifyContent: 'center' as const,
    minWidth: 44, // WCAG AA touch target
    minHeight: 44,
  },
  input: {
    height: 48,
    borderWidth: 1,
    borderColor: Platform.select({
      ios: colors.border,
      android: colors.gray[300],
    }),
    borderRadius: borders.borderRadius.sm,
    paddingHorizontal: spacing.md,
    fontSize: 16,
  },
  separator: {
    height: 1,
    backgroundColor: colors.divider,
  },
};

// Re-export everything
export { iOSStyles, AndroidStyles };
export default {
  colors,
  typography,
  spacing,
  borders,
  shadows,
  common: commonStyles,
};
