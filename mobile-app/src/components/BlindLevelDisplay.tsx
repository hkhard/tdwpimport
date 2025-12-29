/**
 * Blind Level Display Component
 * Shows current blind levels and antes
 *
 * Constitution Requirements:
 * - XP-002: Minimum 44px touch targets
 * - US1-A4: Automatic blind level progression
 */

import React, { useMemo } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import type { BlindLevel } from '@shared/types';

export interface BlindLevelDisplayProps {
  /** Current blind level */
  blindLevel: BlindLevel | null;
  /** Color scheme */
  colorScheme?: 'light' | 'dark';
  /** Size variant */
  size?: 'small' | 'medium' | 'large';
  /** Show level number */
  showLevel?: boolean;
  /** Hide if break */
  hideBreaks?: boolean;
}

/**
 * Format currency/number with commas
 * Handles undefined/null values gracefully
 */
function formatNumber(num: number | undefined): string {
  if (num === undefined || num === null) {
    return '0';
  }
  return num.toLocaleString();
}

/**
 * Blind Level Display Component
 *
 * Shows:
 * - Small blind / Big blind
 * - Ante (if applicable)
 * - Level number
 * - Break indicator
 */
export const BlindLevelDisplay: React.FC<BlindLevelDisplayProps> = ({
  blindLevel,
  colorScheme = 'dark',
  size = 'medium',
  showLevel = true,
  hideBreaks = false,
}) => {
  const styles = useMemo(() => {
    const isDark = colorScheme === 'dark';
    const isBreak = blindLevel?.isBreak;

    return StyleSheet.create({
      container: {
        alignItems: 'center',
        justifyContent: 'center',
        padding: 16,
        backgroundColor: isBreak ? '#FFC107' : isDark ? '#1E1E1E' : '#F5F5F5',
        borderRadius: 12,
        minWidth: 200,
      },
      levelText: {
        color: isDark ? '#CCCCCC' : '#666666',
        fontSize: size === 'small' ? 12 : size === 'large' ? 18 : 14,
        fontWeight: '500',
        marginBottom: 4,
      },
      blindsContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        gap: size === 'small' ? 8 : 12,
      },
      blindText: {
        color: isDark ? '#FFFFFF' : '#000000',
        fontSize: size === 'small' ? 20 : size === 'large' ? 36 : 28,
        fontWeight: '700',
      },
      dividerText: {
        color: isDark ? '#888888' : '#AAAAAA',
        fontSize: size === 'small' ? 16 : size === 'large' ? 28 : 20,
        fontWeight: '400',
      },
      anteContainer: {
        marginTop: 8,
        paddingTop: 8,
        borderTopWidth: 1,
        borderTopColor: isDark ? '#333333' : '#DDDDDD',
      },
      anteText: {
        color: isDark ? '#4CAF50' : '#2E7D32',
        fontSize: size === 'small' ? 14 : size === 'large' ? 20 : 16,
        fontWeight: '600',
      },
      breakText: {
        color: '#000000',
        fontSize: size === 'small' ? 16 : size === 'large' ? 24 : 20,
        fontWeight: '700',
        textTransform: 'uppercase',
      },
      durationText: {
        color: isDark ? '#888888' : '#AAAAAA',
        fontSize: size === 'small' ? 10 : size === 'large' ? 14 : 12,
        marginTop: 4,
      },
    });
  }, [colorScheme, size, blindLevel]);

  if (!blindLevel) {
    return (
      <View style={styles.container}>
        <Text style={[styles.blindText, { color: '#888888' }]}>No blinds set</Text>
      </View>
    );
  }

  if (blindLevel.isBreak) {
    if (hideBreaks) {
      return null;
    }

    return (
      <View style={styles.container}>
        <Text style={styles.breakText}>BREAK</Text>
        {blindLevel.duration > 0 && (
          <Text style={styles.durationText}>
            {blindLevel.duration} min
          </Text>
        )}
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {showLevel && (
        <Text style={styles.levelText}>Level {blindLevel.level}</Text>
      )}

      <View style={styles.blindsContainer}>
        <Text style={styles.blindText}>{formatNumber(blindLevel.smallBlind)}</Text>
        <Text style={styles.dividerText}>/</Text>
        <Text style={styles.blindText}>{formatNumber(blindLevel.bigBlind)}</Text>
      </View>

      {blindLevel.ante && blindLevel.ante > 0 && (
        <View style={styles.anteContainer}>
          <Text style={styles.anteText}>Ante: {formatNumber(blindLevel.ante)}</Text>
        </View>
      )}

      {blindLevel.duration > 0 && (
        <Text style={styles.durationText}>
          {blindLevel.duration} minutes
        </Text>
      )}
    </View>
  );
};

/**
 * Compact blind display for small spaces
 */
export const BlindLevelDisplayCompact: React.FC<BlindLevelDisplayProps> = (props) => {
  return (
    <BlindLevelDisplay
      {...props}
      size="small"
      showLevel={false}
      hideBreaks={true}
    />
  );
};
