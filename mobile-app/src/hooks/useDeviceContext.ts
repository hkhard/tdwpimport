import { useMemo } from 'react';
import { useWindowDimensions, Platform } from 'react-native';

/**
 * Device context hook for responsive design
 * Implements US4-A5: Responsive layout adaptation for phone vs tablet
 */
export function useDeviceContext() {
  const { width, height } = useWindowDimensions();

  const isTablet = useMemo(() => {
    // Tablet breakpoint: 600px width (standard responsive design breakpoint)
    // This covers iPad Mini (744px), iPad Pro (834-1024px), and similar Android tablets
    return width >= 600;
  }, [width]);

  const isPhone = useMemo(() => {
    return !isTablet;
  }, [isTablet]);

  const isLandscape = useMemo(() => {
    return width > height;
  }, [width, height]);

  const isPortrait = useMemo(() => {
    return width <= height;
  }, [width, height]);

  const platform = useMemo(() => Platform.OS, []);

  return {
    isTablet,
    isPhone,
    isLandscape,
    isPortrait,
    platform,
    dimensions: { width, height },
  };
}

/**
 * Breakpoint Rationale:
 *
 * **Phone (< 600px)**: Compact layout, single column, bottom navigation
 * - iPhone SE: 375x667
 * - iPhone 14: 393x852
 * - iPhone 14 Pro Max: 430x932
 *
 * **Tablet (>= 600px)**: Expanded layout, multi-column, side navigation possible
 * - iPad Mini: 744x1133
 * - iPad Pro 11": 834x1194
 * - iPad Pro 12.9": 1024x1366
 *
 * This follows Material Design and Apple HIG responsive design guidelines.
 */
