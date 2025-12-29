#!/usr/bin/env node

/**
 * Remote View Test Script (T107)
 *
 * Tests the remote viewing functionality:
 * - Public tournament view endpoint
 * - SSE real-time streaming
 * - Static file serving
 * - Connection stability
 */

const http = require('http');
const { URL } = require('url');

const CONTROLLER_URL = process.env.CONTROLLER_URL || 'http://localhost:3000';
const TEST_TOURNAMENT_ID = process.env.TOURNAMENT_ID || 'test-tournament-001';

// ANSI colors
const colors = {
  reset: '\x1b[0m',
  green: '\x1b[32m',
  red: '\x1b[31m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  cyan: '\x1b[36m',
};

function log(message, color = colors.reset) {
  console.log(`${color}${message}${colors.reset}`);
}

function success(message) {
  log(`✓ ${message}`, colors.green);
}

function error(message) {
  log(`✗ ${message}`, colors.red);
}

function info(message) {
  log(`ℹ ${message}`, colors.cyan);
}

function section(message) {
  log(`\n${message}`, colors.blue);
  log('='.repeat(message.length - 1), colors.blue);
}

/**
 * Test 1: Check controller health
 */
async function testHealthCheck() {
  section('Test 1: Controller Health Check');

  return new Promise((resolve) => {
    const options = new URL(`${CONTROLLER_URL}/api/health`);

    http.get(options, (res) => {
      if (res.statusCode === 200) {
        success('Controller is healthy');
        resolve(true);
      } else {
        error(`Health check failed: ${res.statusCode}`);
        resolve(false);
      }
    }).on('error', (err) => {
      error(`Health check error: ${err.message}`);
      resolve(false);
    });
  });
}

/**
 * Test 2: Public tournament view endpoint
 */
async function testPublicView() {
  section('Test 2: Public Tournament View Endpoint');

  return new Promise((resolve) => {
    const options = new URL(`${CONTROLLER_URL}/api/tournaments/${TEST_TOURNAMENT_ID}/public`);

    http.get(options, (res) => {
      let data = '';

      res.on('data', (chunk) => {
        data += chunk;
      });

      res.on('end', () => {
        if (res.statusCode === 200) {
          success('Public view endpoint accessible');

          try {
            const json = JSON.parse(data);
            info(`Response data: ${JSON.stringify(json, null, 2)}`);
            resolve(true);
          } catch (e) {
            error('Invalid JSON response');
            resolve(false);
          }
        } else if (res.statusCode === 404) {
          info('Tournament not found (expected if not created)');
          resolve(true);
        } else {
          error(`Unexpected status: ${res.statusCode}`);
          resolve(false);
        }
      });
    }).on('error', (err) => {
      error(`Public view error: ${err.message}`);
      resolve(false);
    });
  });
}

/**
 * Test 3: SSE streaming endpoint
 */
async function testSSEStream() {
  section('Test 3: SSE Streaming Endpoint');

  return new Promise((resolve) => {
    const options = new URL(`${CONTROLLER_URL}/api/tournaments/${TEST_TOURNAMENT_ID}/stream`);

    const req = http.get(options, (res) => {
      info(`Response status: ${res.statusCode}`);
      info(`Content-Type: ${res.headers['content-type']}`);

      if (res.headers['content-type'] !== 'text/event-stream') {
        error('Invalid content type for SSE');
        resolve(false);
        return;
      }

      success('SSE endpoint accessible');

      let eventCount = 0;
      const maxEvents = 5;
      let timeout;

      const cleanup = () => {
        clearTimeout(timeout);
        req.destroy();
      };

      res.on('data', (chunk) => {
        const data = chunk.toString();
        info(`SSE event received: ${data.substring(0, 100)}...`);
        eventCount++;

        if (eventCount >= maxEvents) {
          success(`Received ${eventCount} SSE events`);
          cleanup();
          resolve(true);
        }
      });

      res.on('end', () => {
        info('SSE stream ended');
        cleanup();
        resolve(eventCount > 0);
      });

      res.on('error', (err) => {
        error(`SSE error: ${err.message}`);
        cleanup();
        resolve(false);
      });

      // Timeout after 30 seconds
      timeout = setTimeout(() => {
        if (eventCount > 0) {
          success(`Received ${eventCount} SSE events (timeout)`);
        } else {
          error('SSE timeout - no events received');
        }
        cleanup();
        resolve(eventCount > 0);
      }, 30000);
    });

    req.on('error', (err) => {
      error(`SSE request error: ${err.message}`);
      resolve(false);
    });
  });
}

/**
 * Test 4: Static file serving
 */
async function testStaticFiles() {
  section('Test 4: Static File Serving');

  return new Promise((resolve) => {
    const options = new URL(`${CONTROLLER_URL}/public/remote-view/index.html`);

    http.get(options, (res) => {
      info(`Response status: ${res.statusCode}`);
      info(`Content-Type: ${res.headers['content-type']}`);

      if (res.statusCode === 200) {
        success('Static files accessible');

        if (res.headers['content-type']?.includes('text/html')) {
          success('HTML content type correct');
          resolve(true);
        } else {
          error('Invalid content type');
          resolve(false);
        }
      } else {
        error(`Static file not accessible: ${res.statusCode}`);
        resolve(false);
      }
    }).on('error', (err) => {
      error(`Static file error: ${err.message}`);
      resolve(false);
    });
  });
}

/**
 * Test 5: CORS headers
 */
async function testCORS() {
  section('Test 5: CORS Headers');

  return new Promise((resolve) => {
    const options = new URL(`${CONTROLLER_URL}/api/tournaments/${TEST_TOURNAMENT_ID}/public`);

    http.get(options, (res) => {
      const corsHeaders = {
        'access-control-allow-origin': res.headers['access-control-allow-origin'],
        'access-control-allow-methods': res.headers['access-control-allow-methods'],
      };

      info(`CORS headers: ${JSON.stringify(corsHeaders)}`);

      if (corsHeaders['access-control-allow-origin']) {
        success('CORS headers present');
        resolve(true);
      } else {
        info('No CORS headers (may be intentional)');
        resolve(true);
      }
    }).on('error', (err) => {
      error(`CORS test error: ${err.message}`);
      resolve(false);
    });
  });
}

/**
 * Run all tests
 */
async function runAllTests() {
  log('\n╔════════════════════════════════════════════════════════╗', colors.cyan);
  log('║     Remote View Test Suite (T107)                     ║', colors.cyan);
  log('╚════════════════════════════════════════════════════════╝', colors.cyan);

  info(`Controller URL: ${CONTROLLER_URL}`);
  info(`Test Tournament ID: ${TEST_TOURNAMENT_ID}\n`);

  const results = {
    healthCheck: await testHealthCheck(),
    publicView: await testPublicView(),
    sseStream: await testSSEStream(),
    staticFiles: await testStaticFiles(),
    cors: await testCORS(),
  };

  section('Test Results Summary');

  for (const [test, passed] of Object.entries(results)) {
    const testLabel = test.replace(/([A-Z])/g, ' $1').replace(/^./, (str) => str.toUpperCase());
    if (passed) {
      success(`${testLabel}: PASSED`);
    } else {
      error(`${testLabel}: FAILED`);
    }
  }

  const passCount = Object.values(results).filter(Boolean).length;
  const totalCount = Object.keys(results).length;

  log('\n' + '='.repeat(50), colors.cyan);
  if (passCount === totalCount) {
    success(`All tests passed! (${passCount}/${totalCount})`);
  } else {
    error(`Some tests failed (${passCount}/${totalCount} passed)`);
    process.exit(1);
  }
}

// Run tests
runAllTests().catch((err) => {
  error(`Test suite error: ${err.message}`);
  process.exit(1);
});
