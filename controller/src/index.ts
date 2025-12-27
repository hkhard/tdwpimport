/**
 * Main entry point for Tournament Director controller
 * Starts the Fastify server with WebSocket support
 */

import { createServer } from './server';

async function main() {
  const server = await createServer();
  const port = parseInt(process.env.PORT || '3000');

  try {
    await server.listen({ port, host: '0.0.0.0' });
    console.log(`Controller listening on http://0.0.0.0:${port}`);
    console.log(`Health check: http://0.0.0.0:${port}/api/health`);
    console.log(`WebSocket: ws://0.0.0.0:${port}/ws`);
  } catch (error) {
    console.error('Failed to start controller:', error);
    process.exit(1);
  }
}

main();
