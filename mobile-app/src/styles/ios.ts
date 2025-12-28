import { TextStyle, ViewStyle } from 'react-native';

/**
 * iOS-specific styling following Apple Human Interface Guidelines
 * Implements US4-A3: iOS design patterns
 *
 * Design Principles:
 * - Clarity: Content is king, UI elements fade to background
 * - Deference to Content: UI frames content, doesn't compete
 * - Depth: Visual layers communicate hierarchy
 */
export const iOSColors = {
  // System Colors
  blue: '#007AFF',
  green: '#34C759',
  red: '#FF3B30',
  yellow: '#FFCC00',
  orange: '#FF9500',
  purple: '#AF52DE',
  pink: '#FF2D55',
  teal: '#5AC8FA',
  indigo: '#5856D6',

  // Gray Scale
  gray: {
    50: '#FAFAFA',
    100: '#F5F5F5',
    200: '#E5E5EA',
    300: '#D1D1D6',
    400: '#C7C7CC',
    500: '#AEAEB2',
    600: '#8E8E93',
    700: '#636366',
    800: '#48484A',
    900: '#3A3A3C',
  },

  // Semantic
  background: '#FFFFFF',
  secondaryBackground: '#F2F2F7',
  primaryText: '#000000',
  secondaryText: '#3C3C43',
  tertiaryText: '#8E8E93',
  separator: '#C6C6C8',
  border: '#D1D1D6',
};

export const iOSTypography = {
  // Large Titles (34pt)
  largeTitle: {
    fontSize: 34,
    fontWeight: 'bold' as const,
    letterSpacing: 0.37,
  },

  // Titles (28pt)
  title1: {
    fontSize: 28,
    fontWeight: 'bold' as const,
    letterSpacing: 0.36,
  },
  title2: {
    fontSize: 22,
    fontWeight: 'bold' as const,
    letterSpacing: 0.35,
  },
  title3: {
    fontSize: 20,
    fontWeight: 'semibold' as const,
    letterSpacing: 0.38,
  },

  // Body (17pt)
  body: {
    fontSize: 17,
    fontWeight: 'normal' as const,
    letterSpacing: -0.43,
    lineHeight: 22,
  },
  bodyEmphasized: {
    fontSize: 17,
    fontWeight: 'semibold' as const,
    letterSpacing: -0.43,
    lineHeight: 22,
  },

  // Callout (16pt)
  callout: {
    fontSize: 16,
    fontWeight: 'normal' as const,
    letterSpacing: -0.32,
    lineHeight: 21,
  },

  // Subhead (15pt)
  subhead: {
    fontSize: 15,
    fontWeight: 'normal' as const,
    letterSpacing: -0.24,
    lineHeight: 20,
  },
  subheadEmphasized: {
    fontSize: 15,
    fontWeight: 'semibold' as const,
    letterSpacing: -0.24,
    lineHeight: 20,
  },

  // Footnote (13pt)
  footnote: {
    fontSize: 13,
    fontWeight: 'normal' as const,
    letterSpacing: -0.08,
    lineHeight: 18,
  },

  // Caption 1 (12pt)
  caption1: {
    fontSize: 12,
    fontWeight: 'normal' as const,
    letterSpacing: 0,
    lineHeight: 16,
  },

  // Caption 2 (11pt)
  caption2: {
    fontSize: 11,
    fontWeight: 'normal' as const,
    letterSpacing: 0.06,
    lineHeight: 13,
  },
};

export const iOSSpacing = {
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
  xxl: 48,
};

export const iOSBorders = {
  borderRadius: {
    sm: 8,
    md: 12,
    lg: 16,
    xl: 24,
    round: 9999,
  },
};

export const iOSShadows = {
  small: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  } as ViewStyle,
  medium: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  } as ViewStyle,
  large: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 8,
    elevation: 4,
  } as ViewStyle,
};

// Combine all iOS styles
export const iOSStyles = {
  colors: iOSColors,
  typography: iOSTypography,
  spacing: iOSSpacing,
  borders: iOSBorders,
  shadows: iOSShadows,
};
