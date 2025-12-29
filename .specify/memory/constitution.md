<!--
  Sync Impact Report: Constitution Update v1.0.0
  =================================================
  Version Change: INITIAL → 1.0.0
  Modified Principles: N/A (initial constitution)
  Added Sections: All sections (initial creation)
  Removed Sections: N/A
  Templates Updated:
    ✅ plan-template.md - Constitution Check section remains applicable
    ✅ spec-template.md - Requirements sections align with mobile + WP plugin principles
    ✅ tasks-template.md - Task categorization supports multi-platform projects
  Follow-up TODOs: None
-->

# Poker Tournament Platform Constitution

## Core Principles

### I. Mobile-First Architecture

All mobile application development MUST prioritize mobile UX and performance:

- **Primary Platform**: Mobile app built with TypeScript + Expo (React Native)
- **Cross-Platform Target**: iOS and Android parity in features and functionality
- **Responsive by Default**: All UI components designed for smallest screen first, scaled up for tablets
- **Offline-Capable Core**: Timer and tournament control MUST function without network connectivity
- **Touch-Optimized**: Minimum touch target 44px, gestures preferred over taps

**Rationale**: Tournament directors need portable, in-the-pocket functionality. Mobile-first ensures the critical timer and remote viewing features work reliably in tournament venues where WiFi may be spotty or unavailable.

### II. Precision Timing (NON-NEGOTIABLE)

Tournament timing accuracy is the critical path:

- **Resolution**: ALL timers MUST maintain 1/10th second (100ms) precision or better
- **Display**: Clock displays MUST show tenths of seconds visibly
- **Persistence**: Timer state MUST survive app backgrounding, crashes, and device restarts
- **Synchronization**: Remote view clocks MUST sync to within 200ms across all connected devices
- **Validation**: Every timer implementation MUST include automated precision tests

**Rationale**: Tournament integrity depends on accurate timing. Blinds escalate, breaks end, and tournaments conclude on precise schedules. 100ms precision ensures fair play and eliminates disputes.

### III. CMS Integration Layer

WordPress plugin remains the authoritative data backend:

- **API-First Design**: Mobile app communicates via REST/GraphQL endpoints only - no direct DB access
- **Dual Workflow**: Mobile app creates/manages tournaments, WordPress displays/publishes results
- **Bidirectional Sync**: Tournament data flows: Mobile App → CMS → Public Website
- **Upload Functionality**: Mobile app MUST support .tdt file upload to WordPress CMS API
- **Authentication**: OAuth2 or JWT token-based auth required for all API calls

**Rationale**: Leverages existing WordPress plugin investment while enabling modern mobile UX. CMS remains content authority, mobile app becomes the creation/control surface.

### IV. TypeScript Discipline

TypeScript strict mode enforcement across all code:

- **Strict Mode**: `strict: true` mandatory in tsconfig.json
- **No Any Types**: Explicit typing required; `any` type prohibited in PRs
- **Interface-First**: All data models defined as interfaces/types before implementation
- **Null Safety**: Leverage strictNullChecks for optional state handling
- **ESLint + Prettier**: Automated formatting on save, enforced in CI/CD

**Rationale**: Mobile app runtime errors are catastrophic (crashes in production). TypeScript compilation catches 15-20% of bugs before deployment, critical for distributed apps that cannot be hotfixed instantly.

### V. Expo Managed Workflow (with exceptions)

Prefer Expo managed workflow for development velocity:

- **Managed Default**: Use Expo Go for development, EAS Build for deployment
- **Expo Modules**: Prefer ecosystem packages over native modules when possible
- **Config Plugins**: Use expo-module-scripts for any necessary native code
- **Exception Protocol**: Custom native code allowed ONLY if:
  - No Expo module exists for required capability
  - Documented in plan.md with justification
  - Approved via constitution review

**Rationale**: Expo provides 10x faster iteration, OTA updates for critical bug fixes, and unified build pipeline. Custom native code introduces complexity that defeats the platform's benefits.

### VI. Real-Time Remote Viewing

Tournament state synchronization for remote participants:

- **WebSocket/Streaming**: Use real-time protocols for clock/leaderboard updates
- **Optimized Payloads**: Delta updates only (changed data), not full state refreshes
- **Reconnection Logic**: Auto-reconnect with exponential backoff, state reconciliation on reconnect
- **Bandwidth Awareness**: Detect network quality, throttle updates on poor connections
- **View Permissions**: Public read-only views separate from director controls

**Rationale**: Remote players and spectators need live tournament status without requiring app install or login. Web-viewable remote screens maximize accessibility.

## Technology Stack Standards

### Mobile App (Primary Platform)

- **Framework**: Expo SDK 50+ (latest stable)
- **Language**: TypeScript 5.0+ in strict mode
- **State Management**: Zustand or Redux Toolkit (plan.md must decide)
- **Navigation**: React Navigation v6+
- **Networking**: Axios or Fetch API with interceptors
- **Real-Time**: WebSocket or Server-Sent Events (plan.md must decide)
- **Storage**: AsyncStorage for offline cache, SecureStore for auth tokens
- **Testing**: Jest + React Native Testing Library
- **Build**: EAS Build for iOS/Android deployment

### WordPress Plugin (Existing Platform)

- **Backend**: PHP 8.0+, WordPress 6.0+
- **Database**: MySQL 5.7+, custom tables with `tdwp_` prefix
- **API**: REST API endpoints for mobile app consumption
- **Caching**: Transients API for computed statistics
- **Frontend**: Shortcodes for tournament display on public site

### Development Tools

- **Version Control**: Git with feature branch workflow
- **Code Quality**: ESLint, Prettier, TypeScript compiler
- **CI/CD**: GitHub Actions or similar for automated testing/builds
- **Documentation**: Markdown in docs/, JSDoc for code comments

## Quality Gates

### Pre-Commit (Automated)

- TypeScript compilation must pass with zero errors
- ESLint must pass with zero warnings
- Prettier formatting applied automatically
- Unit tests for modified modules must pass

### Pre-Merge (Code Review)

- All PRs must include tests for new functionality
- Timer precision tests MUST pass for any timing code changes
- API contracts validated against WordPress backend
- No TypeScript `any` types in new code
- Mobile app tested on at least one physical device

### Pre-Release (Deployment)

- Full test suite passes (unit + integration + E2E)
- Manual smoke test on iOS and Android physical devices
- Timer stress test: 8+ hour continuous operation verification
- Offline mode validated (core features work without network)
- EAS Build successful for both platforms
- App Store / Play Store submission checklist completed

## Performance Requirements

### Mobile App Performance

- **App Start**: Cold launch <3 seconds on mid-range device
- **Timer Rendering**: 60 FPS for clock display updates
- **API Response**: p95 <500ms for CRUD operations
- **Offline Recovery**: <2 seconds to restore state from local cache
- **Bundle Size**: <50MB initial download (iOS/Android)

### WordPress Plugin Performance

- **Import Processing**: <30 seconds for 1000-player .tdt file
- **API Response**: p95 <200ms for REST endpoints
- **Database Queries**: All queries <100ms with proper indexing
- **Cache Hit Rate**: >90% for computed statistics

## Security Standards

### Mobile App Security

- **API Authentication**: OAuth2 with PKCE or JWT with refresh token rotation
- **Secure Storage**: Tokens stored in SecureStore (Keychain/Keystore)
- **Certificate Pinning**: Implement SSL pinning for production API calls
- **Obfuscation**: Code obfuscation enabled in release builds
- **Data at Rest**: Encrypt sensitive cached data

### WordPress Plugin Security

- **Input Validation**: All .tdt file data sanitized
- **Capability Checks**: Role-based access for all admin functions
- **Nonce Verification**: CSRF protection on all forms
- **Prepared Statements**: All database queries use prepared statements
- **Rate Limiting**: API endpoints throttled to prevent abuse

## Governance

### Amendment Process

1. Propose change via GitHub issue with rationale
2. Document impact on existing features
3. Create migration plan if breaking change
4. Approve via maintainer review
5. Update version per semantic versioning rules
6. Update all dependent templates
7. Communicate changes to contributors

### Versioning

- **MAJOR**: Principle removal, backward-incompatible changes
- **MINOR**: New principle added, material guidance expansion
- **PATCH**: Clarification, wording improvements, non-semantic changes

### Compliance Verification

- All code MUST pass constitution checks before merge
- Complexity beyond principles requires explicit justification in plan.md
- Use `.specify/templates/` for automated compliance checking
- Reference `CLAUDE.md` for runtime development guidance

**Version**: 1.0.0 | **Ratified**: 2025-12-26 | **Last Amended**: 2025-12-26
