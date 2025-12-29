import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

/**
 * Notification Scheduler Service
 * Implements US1c: Timer alerts for configurable intervals (blind changes, breaks, tournament end)
 * Implements US4-A4: Platform-specific notification behaviors and background execution limits
 *
 * Features:
 * - Schedule local notifications for timer events
 * - Platform-specific notification handling (iOS vs Android)
 * - Background execution awareness
 * - Notification categories for different timer events
 */

export type NotificationType = 'level_change' | 'break_start' | 'break_end' | 'tournament_end';

export interface ScheduledNotification {
  id: string;
  trigger: Notifications.NotificationTriggerInput;
  content: Notifications.NotificationContentInput;
  type: NotificationType;
}

class NotificationScheduler {
  private scheduledNotifications: Map<string, ScheduledNotification> = new Map();

  constructor() {
    this.setupNotificationHandler();
  }

  /**
   * Configure notification handler for foreground/background behavior
   * Implements US4-A4: Platform-specific notification behaviors
   */
  private setupNotificationHandler() {
    Notifications.setNotificationHandler({
      handleNotification: async () => ({
        shouldShowAlert: true,
        shouldPlaySound: true,
        shouldSetBadge: true,
      }),
    });
  }

  /**
   * Initialize notifications and request permissions
   * Must be called at app startup
   */
  async initialize(): Promise<boolean> {
    try {
      // Android: Set up notification channel
      if (Platform.OS === 'android') {
        await Notifications.setNotificationChannelAsync('timer-events', {
          name: 'Timer Events',
          importance: Notifications.AndroidImportance.HIGH,
          vibrationPattern: [0, 250, 250, 250],
          lightColor: '#007AFF',
        });
      }

      // Request permissions
      const { status: existingStatus } = await Notifications.getPermissionsAsync();
      let finalStatus = existingStatus;

      if (existingStatus !== 'granted') {
        const { status } = await Notifications.requestPermissionsAsync();
        finalStatus = status;
      }

      return finalStatus === 'granted';
    } catch (error) {
      console.error('Failed to initialize notifications:', error);
      return false;
    }
  }

  /**
   * Schedule notification for blind level change
   * Implements FR-005: Configurable alert intervals for blind changes
   */
  async scheduleLevelChange(tournamentId: string, level: number, scheduledTime: Date): Promise<string> {
    const identifier = `${tournamentId}-level-${level}`;

    const content: Notifications.NotificationContentInput = {
      title: 'Blind Level Changing',
      body: `Level ${level} is about to begin`,
      sound: 'default',
      categoryIdentifier: 'timer-events',
      data: { tournamentId, type: 'level_change', level },
    };

    const trigger: Notifications.NotificationTriggerInput = {
      type: Notifications.SchedulableTriggerInputTypes.DATE,
      date: scheduledTime,
    };

    return await this.scheduleNotification(identifier, content, trigger, 'level_change');
  }

  /**
   * Schedule notification for break start
   */
  async scheduleBreakStart(tournamentId: string, scheduledTime: Date): Promise<string> {
    const identifier = `${tournamentId}-break-start`;

    const content: Notifications.NotificationContentInput = {
      title: 'Break Time',
      body: 'Break is starting now',
      sound: 'default',
      categoryIdentifier: 'timer-events',
      data: { tournamentId, type: 'break_start' },
    };

    const trigger: Notifications.NotificationTriggerInput = {
      type: Notifications.SchedulableTriggerInputTypes.DATE,
      date: scheduledTime,
    };

    return await this.scheduleNotification(identifier, content, trigger, 'break_start');
  }

  /**
   * Schedule notification for break end
   */
  async scheduleBreakEnd(tournamentId: string, scheduledTime: Date): Promise<string> {
    const identifier = `${tournamentId}-break-end`;

    const content: Notifications.NotificationContentInput = {
      title: 'Break Over',
      body: 'Break is ending, play will resume',
      sound: 'default',
      categoryIdentifier: 'timer-events',
      data: { tournamentId, type: 'break_end' },
    };

    const trigger: Notifications.NotificationTriggerInput = {
      type: Notifications.SchedulableTriggerInputTypes.DATE,
      date: scheduledTime,
    };

    return await this.scheduleNotification(identifier, content, trigger, 'break_end');
  }

  /**
   * Schedule notification for tournament end
   */
  async scheduleTournamentEnd(tournamentId: string, scheduledTime: Date): Promise<string> {
    const identifier = `${tournamentId}-end`;

    const content: Notifications.NotificationContentInput = {
      title: 'Tournament Ended',
      body: 'The tournament has concluded',
      sound: 'default',
      categoryIdentifier: 'timer-events',
      data: { tournamentId, type: 'tournament_end' },
    };

    const trigger: Notifications.NotificationTriggerInput = {
      type: Notifications.SchedulableTriggerInputTypes.DATE,
      date: scheduledTime,
    };

    return await this.scheduleNotification(identifier, content, trigger, 'tournament_end');
  }

  /**
   * Internal method to schedule notification
   */
  private async scheduleNotification(
    identifier: string,
    content: Notifications.NotificationContentInput,
    trigger: Notifications.NotificationTriggerInput,
    type: NotificationType
  ): Promise<string> {
    try {
      const notificationId = await Notifications.scheduleNotificationAsync(identifier, {
        content,
        trigger,
      });

      this.scheduledNotifications.set(notificationId, {
        id: notificationId,
        trigger,
        content,
        type,
      });

      return notificationId;
    } catch (error) {
      console.error(`Failed to schedule notification ${identifier}:`, error);
      throw error;
    }
  }

  /**
   * Cancel a specific scheduled notification
   */
  async cancelNotification(notificationId: string): Promise<void> {
    try {
      await Notifications.cancelScheduledNotificationAsync(notificationId);
      this.scheduledNotifications.delete(notificationId);
    } catch (error) {
      console.error(`Failed to cancel notification ${notificationId}:`, error);
    }
  }

  /**
   * Cancel all notifications for a tournament
   */
  async cancelTournamentNotifications(tournamentId: string): Promise<void> {
    const scheduled = await Notifications.getAllScheduledNotificationsAsync();

    for (const notification of scheduled) {
      const data = notification.content.data as { tournamentId?: string };
      if (data.tournamentId === tournamentId) {
        await this.cancelNotification(notification.identifier);
      }
    }
  }

  /**
   * Cancel all scheduled notifications
   */
  async cancelAllNotifications(): Promise<void> {
    try {
      await Notifications.cancelAllScheduledNotificationsAsync();
      this.scheduledNotifications.clear();
    } catch (error) {
      console.error('Failed to cancel all notifications:', error);
    }
  }

  /**
   * Get all scheduled notifications
   */
  async getScheduledNotifications(): Promise<ScheduledNotification[]> {
    const scheduled = await Notifications.getAllScheduledNotificationsAsync();
    return scheduled
      .filter((n) => this.scheduledNotifications.has(n.identifier))
      .map((n) => this.scheduledNotifications.get(n.identifier)!);
  }
}

// Singleton instance
export const notificationScheduler = new NotificationScheduler();
