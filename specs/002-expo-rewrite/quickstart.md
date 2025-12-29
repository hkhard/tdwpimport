# Quick Start Guide

**Feature**: Cross-Platform Tournament Director Platform (002-expo-rewrite)
**Date**: 2025-12-26

This guide provides step-by-step instructions for setting up the development environment and running the application locally.

---

## Prerequisites

### Required Software

| Software | Version | Purpose |
|----------|---------|---------|
| Node.js | 20+ LTS | Runtime for controller and shared code |
| npm | 10+ | Package manager |
| Git | Latest | Version control |
| Expo CLI | Latest | Mobile app development |
| Expo Go App | Latest | Mobile testing (iOS/Android) |
| Docker | Latest (optional) | Containerized controller |

### Optional Software

| Software | Purpose |
|----------|---------|
| Android Studio | Android development |
| Xcode | iOS development (macOS only) |
| Postman | API testing |
| DB Browser for SQLite | Local database inspection |

---

## Project Setup

### 1. Clone Repository

```bash
git clone https://github.com/hkhard/tdwpimport.git
cd tdwpimport
git checkout 002-expo-rewrite
```

### 2. Install Dependencies

```bash
# Install shared code dependencies
cd shared
npm install
cd ..

# Install controller dependencies
cd controller
npm install
cd ..

# Install mobile app dependencies
cd mobile-app
npm install
cd ..
```

### 3. Environment Configuration

**Controller (controller/.env)**:
```bash
# Server
PORT=3000
NODE_ENV=development

# Database
DB_PATH=./data/tournaments.db

# JWT
JWT_SECRET=your-secret-key-change-in-production
JWT_EXPIRES_IN=7d

# CORS
CORS_ORIGIN=http://localhost:19006,exp://localhost:19006

# WebSocket
WS_PORT=3001
```

**Mobile App (mobile-app/.env)**:
```bash
# API
API_URL=http://localhost:3000/v1
WS_URL=ws://localhost:3001

# App
APP_ENV=development
```

---

## Development Workflow

### Option A: Full Stack (Controller + Mobile)

#### Step 1: Start Controller

```bash
cd controller
npm run dev
```

Controller runs on:
- HTTP API: http://localhost:3000/v1
- WebSocket: ws://localhost:3001
- Health check: http://localhost:3000/health

#### Step 2: Start Mobile App

```bash
cd mobile-app
npx expo start
```

#### Step 3: Run on Device

1. Install Expo Go on your phone:
   - iOS: App Store
   - Android: Google Play

2. Scan QR code from terminal

3. App opens with tournament list screen

### Option B: Controller Only (API Development)

```bash
cd controller
npm run dev
```

Test API with Postman or curl:
```bash
# Health check
curl http://localhost:3000/health

# Register user
curl -X POST http://localhost:3000/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"SecurePass123!","name":"Test Director"}'

# Login
curl -X POST http://localhost:3000/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"SecurePass123!"}'
```

### Option C: Mobile Only (UI Development)

Use mock data for offline-first development:
```bash
cd mobile-app
npx expo start --offline
```

---

## Running Tests

### Controller Tests

```bash
cd controller

# Unit tests
npm test

# Integration tests
npm run test:integration

# Load tests
npm run test:load

# All tests with coverage
npm run test:all
```

### Mobile App Tests

```bash
cd mobile-app

# Unit tests
npm test

# Component tests
npm run test:component

# E2E tests (requires Detox setup)
npm run test:e2e
```

---

## Database Management

### Inspect Database

```bash
# Using SQLite CLI
cd controller
sqlite3 data/tournaments.db

# List tables
.tables

# Query tournaments
SELECT * FROM tournaments;

# Exit
.quit
```

### Seed Database

```bash
cd controller
npm run seed
```

Creates:
- 1 admin user (admin@example.com / admin123)
- 5 demo blind schedules
- 3 demo tournaments (various statuses)

### Reset Database

```bash
cd controller
rm data/tournaments.db
npm run migrate
npm run seed
```

---

## WebSocket Testing

### Using wscat

```bash
# Install wscat
npm install -g wscat

# Connect to WebSocket
wscat -c ws://localhost:3001

# Subscribe to tournament updates
{"action":"subscribe","tournamentId":"<tournament-uuid>"}

# Start timer (after authentication)
{"action":"start","tournamentId":"<tournament-uuid>","token":"<jwt-token>"}
```

### Using Browser Console

```javascript
// Connect to WebSocket
const ws = new WebSocket('ws://localhost:3001');

// Subscribe to tournament
ws.send(JSON.stringify({
  action: 'subscribe',
  tournamentId: '<tournament-uuid>'
}));

// Listen for updates
ws.onmessage = (event) => {
  console.log('Update:', JSON.parse(event.data));
};
```

---

## Troubleshooting

### Port Already in Use

```bash
# Find process using port 3000
lsof -i :3000

# Kill process
kill -9 <PID>

# Or use different port
PORT=3001 npm run dev
```

### Expo Connection Issues

1. Ensure phone and computer on same network
2. Check firewall settings (port 19006)
3. Try tunnel mode: `npx expo start --tunnel`

### Database Lock Error

```bash
# Remove WAL files
cd controller
rm data/tournaments.db-*
rm data/tournaments.db-shm
rm data/tournaments.db-wal
```

### TypeScript Errors

```bash
# Clean and rebuild
cd shared
npm run clean
npm run build

cd controller
npm run clean
npm run build

cd mobile-app
npm run clean
npm run build
```

---

## Production Build

### Mobile App

```bash
cd mobile-app

# Build for iOS
eas build --platform ios

# Build for Android
eas build --platform android

# Build both
eas build --platform all
```

### Controller

```bash
cd controller

# Build TypeScript
npm run build

# Build Docker image
docker build -t tournament-controller .

# Run container
docker run -p 3000:3000 -p 3001:3001 tournament-controller
```

---

## Useful Commands

```bash
# Format code
npm run format

# Lint code
npm run lint

# Type check
npm run type-check

# Run all quality checks
npm run check

# Clean all artifacts
npm run clean

# Generate API docs
npm run docs:api
```

---

## Development Tips

1. **Hot Reload**: Both controller and mobile app support hot reload. Save files and changes appear immediately.

2. **Debugging**:
   - Controller: Use Chrome DevTools (`--inspect` flag)
   - Mobile: Use React Native Debugger (shake device → Debug)

3. **Offline Testing**:
   - Put phone in airplane mode
   - Create tournament, register players
   - Disable airplane mode → sync occurs automatically

4. **Timer Precision Testing**:
   - Run timer for 8+ hours
   - Background app, restart device
   - Verify elapsed time accuracy (<1 second drift)

5. **Database Inspection**:
   - Use DB Browser for SQLite GUI
   - Or use Prisma Studio (if using Prisma): `npx prisma studio`

---

## Next Steps

1. Create a test tournament via mobile app
2. Register some players
3. Start the timer and observe precision
4. Test offline mode (airplane mode)
5. Open remote view in browser
6. Record bustouts and verify payouts
7. Export tournament data

For detailed API documentation, see [contracts/openapi.yaml](./contracts/openapi.yaml).
