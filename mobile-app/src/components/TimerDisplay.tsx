/**
 * Timer Display Component
 * Shows tournament timer with 60 FPS updates and tenths-of-second precision
 *
 * Constitution Requirements:
 * - TP-002: 10Hz (tenths-of-second) display update
 * - US1-A5: Smooth display with no stuttering
 * - XP-002: Minimum 44px touch targets
 */

import React, { useMemo } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import type { TimerState } from '@shared/types';

export interface TimerDisplayProps {
  /** Current timer state */
  state: TimerState | null;
  /** Show tenths of second */
  showTenths?: boolean;
  /** Color scheme */
  colorScheme?: 'light' | 'dark';
  /** Size variant */
  size?: 'small' | 'medium' | 'large' | 'xlarge';
}

/**
 * Format milliseconds to MM:SS or HH:MM:SS
 */
function formatTime(ms: number, includeHours = false): string {
  const totalSeconds = Math.floor(ms / 1000);
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  if (includeHours && hours > 0) {
    return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
  }

  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

/**
 * Timer Display Component
 *
 * Renders the timer with:
 * - Main time display (MM:SS or HH:MM:SS)
 * - Optional tenths-of-second digit
 * - Status indicators (running/paused)
 * - Visual urgency based on remaining time
 */
export const TimerDisplay: React.FC<TimerDisplayProps> = ({
  state,
  showTenths = true,
  colorScheme = 'dark',
  size = 'large',
}) => {
  // Determine text colors based on color scheme and status
  const styles = useMemo(() => {
    const isDark = colorScheme === 'dark';
    const isRunning = state?.isRunning && !state?.isPaused;
    const isPaused = state?.isPaused;

    // Calculate urgency based on remaining time
    const remainingMs = state?.remainingTime ?? 0;
    const remainingMinutes = remainingMs / (60 * 1000);
    const isUrgent = remainingMinutes < 5 && remainingMinutes > 0;
    const isCritical = remainingMinutes <= 2 && remainingMs > 0;

    let timeColor = isDark ? '#FFFFFF' : '#000000';
    if (isUrgent) timeColor = '#FFA500'; // Orange
    if (isCritical) timeColor = '#FF0000'; // Red
    if (isPaused) timeColor = '#888888'; // Gray

    return StyleSheet.create({
      container: {
        alignItems: 'center',
        justifyContent: 'center',
        padding: 16,
      },
      timeContainer: {
        flexDirection: 'row',
        alignItems: 'flex-end',
        justifyContent: 'center',
      },
      timeText: {
        color: timeColor,
        fontWeight: '700',
        lineHeight: 1,
      },
      timeTextSmall: {
        fontSize: 48,
      },
      timeTextMedium: {
        fontSize: 72,
      },
      timeTextLarge: {
        fontSize: 96,
      },
      timeTextXLarge: {
        fontSize: 128,
      },
      tenthsText: {
        color: timeColor,
        fontSize: size === 'xlarge' ? 64 : size === 'large' ? 48 : size === 'medium' ? 36 : 24,
        fontWeight: '400',
        marginBottom: size === 'xlarge' ? 20 : size === 'large' ? 16 : size === 'medium' ? 12 : 8,
        marginLeft: 4,
      },
      statusBadge: {
        marginTop: 8,
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 16,
        backgroundColor: isRunning
          ? '#4CAF50'
          : isPaused
          ? '#FFC107'
          : '#9E9E9E',
      },
      statusText: {
        color: isDark ? '#FFFFFF' : '#FFFFFF',
        fontSize: 14,
        fontWeight: '600',
        textTransform: 'uppercase',
      },
      levelIndicator: {
        marginTop: 12,
      },
      levelText: {
        color: isDark ? '#CCCCCC' : '#666666',
        fontSize: 18,
        fontWeight: '500',
      },
    });
  }, [colorScheme, size, state]);

  if (!state) {
    return (
      <View style={styles.container}>
        <Text style={[styles.timeText, styles.timeTextLarge]}>--:--</Text>
      </View>
    );
  }

  // Use elapsed time if no remaining time, otherwise show countdown
  const displayTime = state.remainingTime !== undefined
    ? Math.max(0, state.remainingTime)
    : state.elapsedTime;

  const includeHours = displayTime >= 60 * 60 * 1000;
  const timeString = formatTime(displayTime, includeHours);

  const getStatusText = () => {
    if (!state.isRunning) return 'Stopped';
    if (state.isPaused) return 'Paused';
    return 'Running';
  };

  const timeSizeStyle =
    size === 'small'
      ? styles.timeTextSmall
      : size === 'medium'
      ? styles.timeTextMedium
      : size === 'large'
      ? styles.timeTextLarge
      : styles.timeTextXLarge;

  return (
    <View style={styles.container}>
      <View style={styles.timeContainer}>
        <Text style={[styles.timeText, timeSizeStyle]}>{timeString}</Text>
        {showTenths && (
          <Text style={styles.tenthsText}>.{state.tenths}</Text>
        )}
      </View>

      <View style={styles.statusBadge}>
        <Text style={styles.statusText}>{getStatusText()}</Text>
      </View>

      <View style={styles.levelIndicator}>
        <Text style={styles.levelText}>Level {state.level}</Text>
      </View>
    </View>
  );
};

/**
 * Small timer display for compact spaces
 */
export const TimerDisplaySmall: React.FC<TimerDisplayProps> = (props) => {
  return <TimerDisplay {...props} size="small" showTenths={false} />;
};

/**
 * Extra-large timer for main display
 */
export const TimerDisplayXL: React.FC<TimerDisplayProps> = (props) => {
  return <TimerDisplay {...props} size="xlarge" showTenths={true} />;
};
