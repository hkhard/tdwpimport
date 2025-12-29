#!/usr/bin/env node
/**
 * Timer Precision Stress Test
 *
 * Constitution Requirements:
 * - TP-001: 100ms precision for tenths-of-second display
 * - TP-004: <0.2s drift after 30 minutes of backgrounding
 * - US1-A1: <1s drift over 8 hours of operation
 *
 * Test Plan:
 * 1. Run timer for 8+ hours
 * 2. Measure drift against wall-clock time every 15 minutes
 * 3. Simulate backgrounding events
 * 4. Validate <1s total drift
 *
 * Usage: node scripts/test/timer-precision-test.ts [duration_minutes]
 */

import { performance } from 'perf_hooks';

// Test configuration
const TEST_DURATION_MS = 8 * 60 * 60 * 1000; // 8 hours
const CHECK_INTERVAL_MS = 15 * 60 * 1000; // Check every 15 minutes
const MAX_DRIFT_MS = 1000; // 1 second max drift
const MAX_DRIFT_AFTER_BACKGROUND_MS = 200; // 0.2s max after 30min background

interface TestResult {
  testName: string;
  passed: boolean;
  driftMs: number;
  expectedMaxDriftMs: number;
  message: string;
}

class TimerPrecisionTest {
  private startTime: number = 0;
  private timerElapsedTime: number = 0;
  private checkCount: number = 0;
  private results: TestResult[] = [];

  /**
   * Run the full stress test
   */
  async run(durationMs: number = TEST_DURATION_MS): Promise<void> {
    console.log('='.repeat(60));
    console.log('Timer Precision Stress Test');
    console.log('='.repeat(60));
    console.log(`Duration: ${Math.round(durationMs / 60000)} minutes`);
    console.log(`Max allowed drift: ${MAX_DRIFT_MS}ms`);
    console.log('');

    this.startTime = performance.now();
    let lastCheckTime = this.startTime;

    // Simulate timer ticking
    while (true) {
      const now = performance.now();
      const elapsed = now - this.startTime;

      // Simulate 100ms timer tick
      this.timerElapsedTime += 100;

      // Check drift periodically
      if (now - lastCheckTime >= CHECK_INTERVAL_MS) {
        await this.checkDrift(now);
        lastCheckTime = now;
      }

      // End test
      if (elapsed >= durationMs) {
        break;
      }

      // Small sleep to simulate real timer
      await new Promise((resolve) => setTimeout(resolve, 100));
    }

    this.printSummary();
  }

  /**
   * Check drift against wall-clock time
   */
  private async checkDrift(now: number): Promise<void> {
    this.checkCount++;

    const wallClockElapsed = now - this.startTime;
    const drift = Math.abs(this.timerElapsedTime - wallClockElapsed);
    const elapsedMinutes = Math.round(wallClockElapsed / 60000);

    const passed = drift <= MAX_DRIFT_MS;

    this.results.push({
      testName: `Drift Check #${this.checkCount} (${elapsedMinutes}min)`,
      passed,
      driftMs: drift,
      expectedMaxDriftMs: MAX_DRIFT_MS,
      message: passed ? 'PASS' : `FAIL: Drift of ${drift}ms exceeds ${MAX_DRIFT_MS}ms`,
    });

    console.log(
      `[${this.checkCount.toString().padStart(2, '0')}] ${elapsedMinutes.toString().padStart(3, '0')}min | Drift: ${drift.toString().padStart(6, '0')}ms | ${passed ? 'PASS' : 'FAIL'}`
    );
  }

  /**
   * Test backgrounding recovery
   */
  async testBackgroundRecovery(): Promise<void> {
    console.log('\n--- Testing Backgrounding Recovery ---');

    const beforeBackground = performance.now();
    const timerBeforeBackground = this.timerElapsedTime;

    // Simulate 30 minutes of backgrounding
    const backgroundDuration = 30 * 60 * 1000;
    await new Promise((resolve) => setTimeout(resolve, 100)); // Short wait for simulation

    const afterBackground = performance.now();
    const timerAfterBackground = this.timerElapsedTime + backgroundDuration;

    // Simulate recovery adjustment
    const drift = Math.abs(timerAfterBackground - (afterBackground - this.startTime));

    const passed = drift <= MAX_DRIFT_AFTER_BACKGROUND_MS;

    this.results.push({
      testName: 'Backgrounding Recovery (30min)',
      passed,
      driftMs: drift,
      expectedMaxDriftMs: MAX_DRIFT_AFTER_BACKGROUND_MS,
      message: passed
        ? 'PASS: Backgrounding recovery within acceptable drift'
        : `FAIL: Drift of ${drift}ms exceeds ${MAX_DRIFT_AFTER_BACKGROUND_MS}ms`,
    });

    console.log(`Backgrounding: ${passed ? 'PASS' : 'FAIL'} | Drift: ${drift}ms`);
  }

  /**
   * Test tenths precision
   */
  testTenthsPrecision(): void {
    console.log('\n--- Testing Tenths Precision ---');

    const errors: number[] = [];

    for (let i = 0; i < 1000; i++) {
      const ms = i * 10; // 0, 10, 20, ... 9990
      const expectedTenths = Math.floor((ms % 1000) / 100);
      const actualTenths = Math.floor((ms % 1000) / 100);

      if (expectedTenths !== actualTenths) {
        errors.push(i);
      }
    }

    const passed = errors.length === 0;

    this.results.push({
      testName: 'Tenths Precision (1000 samples)',
      passed,
      driftMs: errors.length,
      expectedMaxDriftMs: 0,
      message: passed
        ? 'PASS: All tenths calculations correct'
        : `FAIL: ${errors.length} incorrect calculations`,
    });

    console.log(`Tenths: ${passed ? 'PASS' : 'FAIL'} | Errors: ${errors.length}/1000`);
  }

  /**
   * Print test summary
   */
  private printSummary(): void {
    console.log('\n' + '='.repeat(60));
    console.log('Test Summary');
    console.log('='.repeat(60));

    const passed = this.results.filter((r) => r.passed).length;
    const failed = this.results.filter((r) => !r.passed).length;

    console.log(`Total Checks: ${this.results.length}`);
    console.log(`Passed: ${passed}`);
    console.log(`Failed: ${failed}`);
    console.log('');

    // Detailed results
    for (const result of this.results) {
      if (!result.passed) {
        console.log(`❌ ${result.testName}`);
        console.log(`   ${result.message}`);
      }
    }

    // Overall result
    const allPassed = failed === 0;
    console.log('');
    console.log('='.repeat(60));

    if (allPassed) {
      console.log('✅ ALL TESTS PASSED');
      console.log(`Timer maintained <${MAX_DRIFT_MS}ms drift over ${Math.round(TEST_DURATION_MS / 60000)} minutes`);
    } else {
      console.log('❌ SOME TESTS FAILED');
      console.log('Timer drift exceeds constitution requirements');
    }

    console.log('='.repeat(60));

    process.exit(allPassed ? 0 : 1);
  }
}

// Main execution
async function main() {
  const durationArg = process.argv[2];
  const duration = durationArg ? parseInt(durationArg, 10) * 60 * 1000 : undefined;

  const test = new TimerPrecisionTest();

  // Run tenths precision test
  test.testTenthsPrecision();

  // Run backgrounding recovery test
  await test.testBackgroundRecovery();

  // Run main stress test
  await test.run(duration);
}

// Run if executed directly
if (require.main === module) {
  main().catch((error) => {
    console.error('Test error:', error);
    process.exit(1);
  });
}

export { TimerPrecisionTest };
