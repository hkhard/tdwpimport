import { TextStyle, ViewStyle } from 'react-native';

/**
 * Android-specific styling following Material Design 3 guidelines
 * Implements US4-A3: Material Design for Android
 *
 * Design Principles:
 * - Material is the metaphor: UI is inspired by paper and ink
 * - Bold, graphic, intentional: Meaningful motion and transition
 * - Motion provides meaning: Focus user attention on key actions
 */
export const AndroidColors = {
  // Primary
  primary: {
    main: '#6200EE',
    light: '#B079FF',
    dark: '#3700B3',
    contrastText: '#FFFFFF',
  },

  // Secondary
  secondary: {
    main: '#03DAC6',
    light: '#66FFF9',
    dark: '#018786',
    contrastText: '#000000',
  },

  // Error
  error: {
    main: '#B00020',
    light: '#EA6B7E',
    dark: '#8C0019',
  },

  // Success
  success: {
    main: '#4CAF50',
    light: '#80E27E',
    dark: '#087F23',
  },

  // Warning
  warning: {
    main: '#FF9800',
    light: '#FFCC80',
    dark: '#F57C00',
  },

  // Background
  background: {
    default: '#FFFFFF',
    paper: '#FFFFFF',
    elevated: '#F5F5F5',
  },

  // Text
  text: {
    primary: 'rgba(0, 0, 0, 0.87)',
    secondary: 'rgba(0, 0, 0, 0.6)',
    disabled: 'rgba(0, 0, 0, 0.38)',
    hint: 'rgba(0, 0, 0, 0.38)',
  },

  // Divider
  divider: 'rgba(0, 0, 0, 0.12)',

  // Gray Scale
  gray: {
    50: '#FAFAFA',
    100: '#F5F5F5',
    200: '#EEEEEE',
    300: '#E0E0E0',
    400: '#BDBDBD',
    500: '#9E9E9E',
    600: '#757575',
    700: '#616161',
    800: '#424242',
    900: '#212121',
  },
};

export const AndroidTypography = {
  // Display Styles
  displayLarge: {
    fontSize: 57,
    fontWeight: '400' as const,
    lineHeight: 64,
    letterSpacing: -0.25,
  },
  displayMedium: {
    fontSize: 45,
    fontWeight: '400' as const,
    lineHeight: 52,
    letterSpacing: 0,
  },
  displaySmall: {
    fontSize: 36,
    fontWeight: '400' as const,
    lineHeight: 44,
    letterSpacing: 0,
  },

  // Headline Styles
  headlineLarge: {
    fontSize: 32,
    fontWeight: '400' as const,
    lineHeight: 40,
    letterSpacing: 0,
  },
  headlineMedium: {
    fontSize: 28,
    fontWeight: '400' as const,
    lineHeight: 36,
    letterSpacing: 0,
  },
  headlineSmall: {
    fontSize: 24,
    fontWeight: '400' as const,
    lineHeight: 32,
    letterSpacing: 0,
  },

  // Title Styles
  titleLarge: {
    fontSize: 22,
    fontWeight: '400' as const,
    lineHeight: 28,
    letterSpacing: 0,
  },
  titleMedium: {
    fontSize: 16,
    fontWeight: '500' as const,
    lineHeight: 24,
    letterSpacing: 0.15,
  },
  titleSmall: {
    fontSize: 14,
    fontWeight: '500' as const,
    lineHeight: 20,
    letterSpacing: 0.1,
  },

  // Body Styles
  bodyLarge: {
    fontSize: 16,
    fontWeight: '400' as const,
    lineHeight: 24,
    letterSpacing: 0.5,
  },
  bodyMedium: {
    fontSize: 14,
    fontWeight: '400' as const,
    lineHeight: 20,
    letterSpacing: 0.25,
  },
  bodySmall: {
    fontSize: 12,
    fontWeight: '400' as const,
    lineHeight: 16,
    letterSpacing: 0.4,
  },

  // Label Styles
  labelLarge: {
    fontSize: 14,
    fontWeight: '500' as const,
    lineHeight: 20,
    letterSpacing: 0.1,
  },
  labelMedium: {
    fontSize: 12,
    fontWeight: '500' as const,
    lineHeight: 16,
    letterSpacing: 0.5,
  },
  labelSmall: {
    fontSize: 11,
    fontWeight: '500' as const,
    lineHeight: 16,
    letterSpacing: 0.5,
  },
};

export const AndroidSpacing = {
  unit: 8, // Base unit
  xs: 4, // 0.5x
  sm: 8, // 1x
  md: 16, // 2x
  lg: 24, // 3x
  xl: 32, // 4x
  xxl: 48, // 6x
};

export const AndroidBorders = {
  borderRadius: {
    none: 0,
    xs: 2,
    sm: 4,
    md: 8,
    lg: 12,
    xl: 16,
    round: 9999,
  },
};

export const AndroidElevation = {
  level0: {
    elevation: 0,
  } as ViewStyle,
  level1: {
    elevation: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 1.5,
  } as ViewStyle,
  level2: {
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 2.5,
  } as ViewStyle,
  level3: {
    elevation: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.27,
    shadowRadius: 4.65,
  } as ViewStyle,
  level4: {
    elevation: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.37,
    shadowRadius: 7.49,
  } as ViewStyle,
  level5: {
    elevation: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.41,
    shadowRadius: 10.32,
  } as ViewStyle,
};

// Combine all Android styles
export const AndroidStyles = {
  colors: AndroidColors,
  typography: AndroidTypography,
  spacing: AndroidSpacing,
  borders: AndroidBorders,
  elevation: AndroidElevation,
};
