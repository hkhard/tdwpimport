# TDWP Import - Continuity Ledger

Updated: 2026-01-01T22:33:07.865Z

## Goal
Multi-platform poker tournament management system: WordPress plugin + Node.js controller + Mobile app

## Constraints
- WordPress plugin: PHP 8.0+ / MySQL 5.7+ (tdwp_ prefix)
- Controller: TypeScript 5.0+ / Fastify / SQLite (tdwp_ prefix)
- Mobile: Expo SDK 54 / React Native / SQLite / Zustand
- Security: nonce verification, data sanitization, prepared statements
- WordPress coding standards, internationalization

## Key Decisions
- Multi-platform architecture for separate concerns
- SQLite for controller/mobile (speed), MySQL for WordPress (existing)
- Zustand for mobile state management (simpler than Redux)
- WebSocket support in controller for real-time updates
- Database prefix: tdwp_ (all tables)

## State
- Done:
  - [x] WordPress plugin core with .tdt parser
  - [x] Mobile app (Expo 54, React Native)
  - [x] Node.js controller (Fastify)
  - [x] Blind level CRUD (002-blind-level-crud)
  - [x] Fix: Tournament level display "Level 0" → "Level 1"
  - [x] Fix: Migration 006 NULL handling with COALESCE
  - [x] Fix: Keyboard covering input fields on Create Tournament
  - [x] Fix: Blind schedule validation UI
  - [x] Beta26: Remove legacy database results warning section
- Now: [→] WordPress plugin bug fixes (feature 003-fix-tournament-import)
- Next: Deploy Beta26 and test
- Remaining:
  - [ ] Commit/push 002-blind-level-crud changes
  - [ ] Merge to main or continue branch work

## Open Questions
- UNCONFIRMED: User goals for next development phase
- UNCONFIRMED: Should current changes be committed/pushed?

## Working Set
- Branch: 002-blind-level-crud
- Modified files:
  - controller/src/api/routes/tournaments.ts
  - controller/src/services/tournament/TournamentService.ts
  - mobile-app/src/screens/TournamentDetailScreen.tsx
  - controller/src/db/migrations/007_fix_tournaments_without_schedule.sql (untracked)
- Untracked changes: .gitignore, .mcp.json (deleted)
- Build commands:
  - Controller: `cd controller && npm run build`
  - Mobile: `cd mobile-app && npm start`

## Recent Context (from mem-search)
Recent sessions (Dec 29, 2:17-2:30 AM):
- #S171-S177: Blind level CRUD implementation
- #S178: Fix "Level 0" display
- #S179: Database NULL handling + corruption cleanup
- #S180: Blind level display and level increment fixes
- #5172-#5222: Schedule CRUD, migration history, schema analysis, UI enhancements

---

Created: 2025-12-29
Session: Onboarding + State Save
Status: Ready for /clear
