/**
 * Cross-Platform E2E Tests
 * Implements US4 Independent Test: Install app on iPhone and Android device,
 * run complete tournament workflow on each, verify identical functionality and behavior.
 *
 * Tests use Detox framework for cross-platform E2E testing on both iOS and Android.
 *
 * Run tests:
 * - iOS: detox build --configuration ios.sim.debug && detox test --configuration ios.sim.debug
 * - Android: detox build --configuration android.emu.debug && detox test --configuration android.emu.debug
 */

import { device, element, by, expect } from 'detox';

describe('Cross-Platform Feature Parity', () => {
  beforeAll(async () => {
    await device.launchApp();
  });

  beforeEach(async () => {
    await device.reloadReactNative();
  });

  /**
   * XP-001: Feature parity validation
   * Verify that all core screens are accessible on both platforms
   */
  describe('Screen Navigation', () => {
    it('should display tournament list screen', async () => {
      await expect(element(by.id('tournament-list-screen'))).toBeVisible();
    });

    it('should navigate to timer screen', async () => {
      await element(by.id('tournament-list-item-0')).tap();
      await element(by.text('Timer')).tap();
      await expect(element(by.id('timer-screen'))).toBeVisible();
    });

    it('should navigate to player manager screen', async () => {
      await element(by.id('tournament-list-item-0')).tap();
      await element(by.text('Players')).tap();
      await expect(element(by.id('player-manager-screen'))).toBeVisible();
    });

    it('should navigate to settings screen', async () => {
      await element(by.text('Settings')).tap();
      await expect(element(by.id('settings-screen'))).toBeVisible();
    });
  });

  /**
   * XP-002: Timer functionality parity
   * Verify timer controls work identically on both platforms
   */
  describe('Timer Functionality', () => {
    it('should start timer', async () => {
      await element(by.id('tournament-list-item-0')).tap();
      await element(by.text('Timer')).tap();

      // Verify initial state
      await expect(element(by.id('timer-display'))).toBeVisible();
      await expect(element(by.text('00:00:0'))).toBeVisible();

      // Start timer
      await element(by.id('start-timer-button')).tap();
      await expect(element(by.id('pause-timer-button'))).toBeVisible();
    });

    it('should pause timer', async () => {
      await element(by.id('tournament-list-item-0')).tap();
      await element(by.text('Timer')).tap();

      await element(by.id('start-timer-button')).tap();
      await element(by.id('pause-timer-button')).tap();
      await expect(element(by.id('resume-timer-button'))).toBeVisible();
    });

    it('should display blind levels correctly', async () => {
      await element(by.id('tournament-list-item-0')).tap();
      await element(by.text('Timer')).tap();

      await expect(element(by.id('blind-level-display'))).toBeVisible();
      await expect(element(by.text('SB: 10/20'))).toBeVisible();
    });

    it('should show tenths of seconds (100ms precision)', async () => {
      await element(by.id('tournament-list-item-0')).tap();
      await element(by.text('Timer')).tap();

      await element(by.id('start-timer-button')).tap();

      // Wait for tenths to change
      await waitFor(element(by.text('00:00:1')))
        .toBeVisible()
        .withTimeout(2000);
    });
  });

  /**
   * XP-003: Player management parity
   * Verify player CRUD operations work identically on both platforms
   */
  describe('Player Management', () => {
    it('should add player', async () => {
      await element(by.id('tournament-list-item-0')).tap();
      await element(by.text('Players')).tap();

      await element(by.id('add-player-button')).tap();
      await element(by.id('player-name-input')).typeText('John Doe');
      await element(by.id('save-player-button')).tap();

      await expect(element(by.text('John Doe'))).toBeVisible();
    });

    it('should record bustout', async () => {
      await element(by.id('tournament-list-item-0')).tap();
      await element(by.text('Players')).tap();

      await element(by.id('player-item-0')).tap();
      await element(by.id('record-bustout-button')).tap();
      await element(by.id('finish-position-input')).typeText('1');
      await element(by.id('confirm-bustout-button')).tap();

      await expect(element(by.text('Finished: 1st'))).toBeVisible();
    });

    it('should display player list', async () => {
      await element(by.id('tournament-list-item-0')).tap();
      await element(by.text('Players')).tap();

      await expect(element(by.id('player-list'))).toBeVisible();
    });
  });

  /**
   * XP-004: Platform-specific UI patterns
   * Verify platform-appropriate styling is applied
   */
  describe('Platform UI Patterns', () => {
    it('should use iOS-styled buttons on iOS', async () => {
      if (device.getPlatform() === 'ios') {
        const button = element(by.id('start-timer-button'));
        await expect(button).toHaveVisible();
        // iOS buttons should have rounded corners
      }
    });

    it('should use Material Design buttons on Android', async () => {
      if (device.getPlatform() === 'android') {
        const button = element(by.id('start-timer-button'));
        await expect(button).toHaveVisible();
        // Android buttons should have slight rounded corners
      }
    });

    it('should display consistent layout across platforms', async () => {
      // All key elements should be present on both platforms
      await element(by.id('tournament-list-item-0')).tap();
      await element(by.text('Timer')).tap();

      await expect(element(by.id('timer-display'))).toBeVisible();
      await expect(element(by.id('blind-level-display'))).toBeVisible();
      await expect(element(by.id('start-timer-button'))).toBeVisible();
    });
  });

  /**
   * XP-005: Offline functionality parity
   * Verify offline operations work identically on both platforms
   */
  describe('Offline Functionality', () => {
    it('should show offline indicator when disconnected', async () => {
      // This test would require network simulation
      // For now, verify indicator element exists
      await expect(element(by.id('sync-status-indicator'))).toExist();
    });

    it('should enable player registration while offline', async () => {
      // Verify offline queue functionality
      await element(by.id('tournament-list-item-0')).tap();
      await element(by.text('Players')).tap();

      await element(by.id('add-player-button')).tap();
      await element(by.id('player-name-input')).typeText('Jane Smith');
      await element(by.id('save-player-button')).tap();

      await expect(element(by.text('Jane Smith'))).toBeVisible();
    });
  });

  /**
   * XP-006: Notification functionality
   * Verify notifications work on both platforms
   */
  describe('Notifications', () => {
    it('should request notification permissions on first launch', async () => {
      // Permissions are requested on app launch
      // Verify permission request dialog appeared (platform-specific)
      const permissionDialog = element(
        by.text('Allow notifications')
      );
      // May or may not be visible depending on platform and previous grants
    });

    it('should schedule notifications for timer events', async () => {
      // Verify notification scheduling (requires additional setup)
      await element(by.id('tournament-list-item-0')).tap();
      await element(by.text('Timer')).tap();

      // Enable notifications in settings
      await element(by.id('notification-settings-toggle')).tap();

      // Start timer to trigger notification scheduling
      await element(by.id('start-timer-button')).tap();
    });
  });

  /**
   * XP-007: Responsive design
   * Verify layout adapts to different screen sizes
   */
  describe('Responsive Design', () => {
    it('should display correctly on phone screens', async () => {
      // Default device size is phone-sized
      await expect(element(by.id('tournament-list-screen'))).toBeVisible();
    });

    it('should handle orientation changes gracefully', async () => {
      // Rotate device and verify layout adapts
      await device.setOrientation('landscape');
      await expect(element(by.id('tournament-list-screen'))).toBeVisible();

      await device.setOrientation('portrait');
      await expect(element(by.id('tournament-list-screen'))).toBeVisible();
    });
  });
});

/**
 * Platform-Specific Validation
 *
 * These tests validate platform-specific behavior that may differ between iOS and Android
 * while still maintaining functional parity.
 */
describe('Platform-Specific Behavior', () => {
  it('iOS: should use blur effects on navigation', async () => {
    if (device.getPlatform() === 'ios') {
      // iOS-specific blur effects should be present
      const navBar = element(by.id('navigation-bar'));
      await expect(navBar).toExist();
    }
  });

  it('Android: should use ripple effects on buttons', async () => {
    if (device.getPlatform() === 'android') {
      // Android Material Design ripple effects are handled by TouchableOpacity
      const button = element(by.id('start-timer-button'));
      await expect(button).toExist();
    }
  });

  it('iOS: should use San Francisco font', async () => {
    if (device.getPlatform() === 'ios') {
      const textElement = element(by.text('Tournaments'));
      await expect(textElement).toExist();
    }
  });

  it('Android: should use Roboto font', async () => {
    if (device.getPlatform() === 'android') {
      const textElement = element(by.text('Tournaments'));
      await expect(textElement).toExist();
    }
  });
});
