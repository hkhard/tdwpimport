# Feature Specification: Cross-Platform Tournament Director Platform

**Feature Branch**: `002-expo-rewrite`
**Created**: 2025-12-26
**Status**: Draft
**Input**: User description: "A complete rewrite of all functionality developed into typescript based on expo for cross platform capabilities. We do a web based central controller that houses all timekeeping and central database functionality in a ultra lightweight database that has built in replication. We remove eveyrthing that is wordpress specific and we move that into the API integration layer at alater stage."

## Executive Summary

Rewrite the existing WordPress-based poker tournament management system into a modern, cross-platform TypeScript application using Expo for mobile apps and a web-based central controller for timekeeping and data management. The new architecture separates concerns: mobile apps provide tournament director and player experiences, the web controller manages real-time timing and data coordination, and future API integration layers will connect to external systems (including WordPress).

## User Scenarios & Testing *(mandatory)*

### User Story 0 - User Authentication (Priority: P2)

Tournament directors need to authenticate to access tournament management features, with secure credential storage and session management.

**Why this priority**: Authentication enables data ownership and multi-user support. For MVP, tournaments can run without authentication (local-only mode), but auth is required for cloud sync and multi-device scenarios.

**Independent Test**: Can be fully tested by registering a new account, logging in, verifying JWT token storage, refreshing the app and confirming session persists, then logging out and confirming token is cleared.

**Acceptance Scenarios**:

1. **Given** a new director, **When** they register with email and password, **Then** an account is created, a JWT token is generated, and the token is stored in SecureStore
2. **Given** a returning director, **When** they log in with valid credentials, **Then** a JWT token is generated and stored, and the app navigates to the tournament list screen
3. **Given** an authenticated director, **When** the app restarts, **Then** the JWT token is retrieved from SecureStore and the session is automatically restored without requiring re-login
4. **Given** an authenticated director, **When** they log out, **Then** the JWT token is cleared from SecureStore and the app returns to the login screen
5. **Given** an expired JWT token (7 days old), **When** the director opens the app, **Then** the app detects expiration, clears the token, and prompts for re-login

**Authentication Architecture** (per research.md §5.3):
- **Method**: Email/Password + JWT tokens
- **JWT Payload**: `{ userId, role, exp }` with 7-day expiration
- **Token Storage**: Expo SecureStore (encrypted keychain/keystore)
- **Password Security**: Bcrypt hashing (10 rounds) on server
- **Session Management**: Automatic token refresh on app launch
- **OAuth2**: Deferred to post-MVP (FR-049 allows future addition)

**Roles** (per FR-050):
- **admin**: Full system access, user management
- **director**: Create/edit/delete tournaments, full control
- **scorekeeper**: Limited to player management and bustout recording
- **viewer**: Read-only access to tournaments

**Security Requirements**:
- All API requests MUST include JWT token in Authorization header: `Bearer {token}`
- Invalid/expired tokens MUST return 401 Unauthorized
- Passwords MUST be minimum 8 characters with complexity requirements
- Failed login attempts MUST trigger rate limiting (5 attempts per 15 minutes)

---

### User Story 1 - Precision Tournament Timer (Priority: P1)

Tournament directors need a highly accurate, reliable timer that maintains precision even when devices lose network connectivity or the app is backgrounded.

**Why this priority**: Timer accuracy is the foundation of tournament integrity. Without precise timing, blind escalations, breaks, and tournament conclusions become disputed. This is the core non-negotiable requirement.

**Independent Test**: Can be fully tested by running the timer for 8+ hours across app backgrounding, device restarts, and network disconnection, then verifying elapsed time matches wall-clock time within 1 second.

**Acceptance Scenarios**:

1. **Given** a tournament timer is running at 1:23:45.7, **When** the user backgrounds the app for 30 minutes, **Then** the timer displays 1:53:45.7 upon return (within 0.2s drift)
2. **Given** a timer is counting down a break, **When** the device loses all network connectivity, **Then** the timer continues accurately and alerts when break ends
3. **Given** a timer shows 00:05:00.0 remaining, **When** the device restarts (battery pull), **Then** the app restores timer state within 2 seconds of launch with correct elapsed time
4. **Given** a tournament with multiple blind levels, **When** each level expires, **Then** the next level starts automatically within 0.5 seconds with proper blind amounts displayed
5. **Given** a timer displaying tenths of seconds, **When** observed continuously, **Then** the display updates smoothly at 10Hz without stuttering or skipped tenths

---

### User Story 1a - Pause/Resume Timer (Priority: P2)

Tournament directors need to pause and resume tournament timers, with the master device maintaining control authority.

**Independent Test**: Can be fully tested by starting a timer, pausing it, backgrounding the app, verifying paused state persists, resuming, and confirming blind countdown resumes correctly.

**Acceptance Scenarios**:

1. **Given** a tournament timer is running, **When** the director pauses the timer on the master device, **Then** the blind level countdown stops immediately and the timer displays "PAUSED" status
2. **Given** a paused timer, **When** the director backgrounds the app for 30 minutes, **Then** the timer remains paused upon return with correct elapsed time preserved
3. **Given** a paused timer, **When** the director resumes on the master device, **Then** the blind level countdown resumes from the exact point of pause
4. **Given** multiple devices connected, **When** a non-master (slave) device attempts to pause/resume, **Then** the action is ignored or triggers a "not master" notification
5. **Given** a paused timer, **When** observing the timer display, **Then** the paused state is visually indicated (gray color, "PAUSED" badge, stopped clock icon)

**Master Device Model**: The first device to start the timer becomes the master. Master device has exclusive control over pause/resume. Other connected devices are slaves and can only view the timer state.

---

### User Story 1b - Manual Blind Level Override (Priority: P2)

Tournament directors need to manually adjust blind levels during tournaments for flexibility (color-ups, shortened breaks, etc.).

**Independent Test**: Can be fully tested by starting a timer at level 5, manually advancing to level 8, editing blind amounts, and verifying all changes are logged and synced.

**Acceptance Scenarios**:

1. **Given** a tournament timer is running at level 5 (200/400), **When** the director manually advances to level 6 on the master device, **Then** the timer immediately switches to level 6 blinds (300/600) and resets level countdown
2. **Given** a paused timer at level 5, **When** the director edits blind amounts to 250/500 with 50 ante on the master device, **Then** the timer displays updated blinds and logs the change with timestamp and director identifier
3. **Given** a manual blind level change, **When** reviewing the timer event log, **Then** the event is recorded as type "director_override" with previous state and new state
4. **Given** multiple devices connected, **When** a non-master (slave) device attempts manual override, **Then** the action is ignored and a notification indicates "only master device can adjust blinds"
5. **Given** a tournament in progress, **When** the director manually skips a break level, **Then** the timer advances directly to the next playing level with appropriate blinds

**Audit Requirement**: All manual blind level changes MUST be logged with:
- Event type: "director_override"
- Timestamp (server and local)
- Director device ID
- Previous state (level, blinds)
- New state (level, blinds)
- Reason (optional text field)

---

### User Story 1c - Tournament Notifications (Priority: P3)

Tournament directors need configurable alerts for timer events, with notifications delivered even when the app is backgrounded.

**Independent Test**: Can be fully tested by starting a timer, enabling notifications, triggering a break end, and verifying the notification appears on lock screen with correct content.

**Acceptance Scenarios**:

1. **Given** a tournament break is in progress, **When** the break timer reaches 0:00, **Then** the device displays a local push notification "Break Over - Time to Resume" with a sound or vibration
2. **Given** blind level changes from 200/400 to 300/600, **When** the level changes, **Then** the device displays a local push notification "Blinds Up: 300/600" with the new blind amounts
3. **Given** the central controller initiates a level change, **When** the master device adjusts blinds, **Then** all connected devices receive a remote notification with the updated blind information
4. **Given** notification permissions are denied, **When** the timer starts, **Then** the app displays an in-app banner "Enable notifications for alerts" with a link to settings
5. **Given** notifications are enabled, **When** the app is backgrounded and a break ends, **Then** the notification appears on the lock screen and tapping it opens the timer screen

**Platform-Specific Behaviors**:
- **iOS**: Use UNUserNotificationCenter for local and remote notifications; request permissions on first timer start; handle background app refresh restrictions
- **Android**: Use Firebase Cloud Messaging (FCM) for remote notifications; respect Do Not Disturb mode; use notification channels for Android 8+

**Permission Handling**:
- Request notification permissions on first timer start (not app launch)
- Display clear explanation of why notifications are needed
- Gracefully handle permission denial: show in-app alternative (banner, badge, sound)
- Allow user to re-request permissions from settings screen

---

### User Story 2 - Offline Tournament Management (Priority: P1)

Tournament directors must be able to manage tournaments (register players, record bustouts, adjust settings) without network connectivity, with data syncing when connection is restored.

**Why this priority**: Tournament venues often have poor or no WiFi. Directors cannot be dependent on network availability to run events. Offline capability is essential for reliability.

**Independent Test**: Can be fully tested by putting a device in airplane mode for 24+ hours, registering 50 players, recording 30 bustouts, adjusting blind levels, then connecting to WiFi and verifying all data syncs correctly to the central controller.

**Acceptance Scenarios**:

1. **Given** the app is in airplane mode, **When** a director registers a new player, **Then** the player appears in the local player list immediately
2. **Given** no network connectivity, **When** recording a player bustout in 15th place, **Then** the bustout is saved locally and all payouts update correctly based on remaining players
3. **Given** 20 local changes while offline, **When** network connection is restored, **Then** all changes sync to central controller within 10 seconds with conflict resolution
4. **Given** offline mode with local data, **When** the app force-quits and restarts, **Then** all local changes persist and are available immediately
5. **Given** conflicting changes from two devices, **When** sync occurs, **Then** the system uses last-write-wins with timestamps and notifies user of conflicts

---

### User Story 2a - Sync Failure User Interface (Priority: P2)

When offline changes fail to sync to the central controller, directors MUST be clearly informed and able to take corrective action.

**Independent Test**: Can be fully tested by enabling airplane mode, making changes, observing sync status indicators, triggering sync failures, and verifying manual retry functionality.

**Acceptance Scenarios**:

1. **Given** the app is in airplane mode with 5 pending changes, **When** viewing the sync status indicator, **Then** it displays an orange warning badge with "5 changes pending" text
2. **Given** sync fails due to server error (HTTP 500), **When** the sync status indicator updates, **Then** it shows a red badge with "Sync failed" and displays a "Retry Sync" button
3. **Given** 3 failed sync attempts, **When** the director taps the "Retry Sync" button, **Then** the app immediately attempts to sync all pending changes and updates the status indicator
4. **Given** a successful sync after retry, **When** the sync completes, **Then** the status indicator returns to green/normal state and the pending count clears
5. **Given** 10+ pending changes, **When** viewing the sync status, **Then** the indicator shows "10+ changes pending" (abbreviated format)

**UI Components Required**:
- **Sync Status Indicator**: Persistent badge/icon in app header (green=synced, orange=pending, red=failed)
- **Pending Count Badge**: Number display showing unsynced changes ("3 changes" or "10+ changes")
- **Retry Button**: Tappable button that triggers immediate sync attempt (visible only when sync is failing)

**Visual States**:
- **Green (Synced)**: All changes synced, no pending items, solid indicator
- **Orange (Pending)**: Changes waiting to sync, badge shows count, pulsing animation
- **Red (Failed)**: Sync failed, retry button visible, error message available

---

### User Story 3 - Remote Viewing for Players (Priority: P2)

Players and spectators need real-time access to tournament status (clock, blind levels, remaining players, payouts) via any web browser without requiring app installation.

**Why this priority**: Improves player experience and reduces questions. Allows spectators to follow tournaments remotely. Not as critical as timer/offline mode but highly valuable for user adoption.

**Independent Test**: Can be fully tested by opening a web browser on a different device, navigating to the tournament URL, and observing real-time updates as the director makes changes in the mobile app.

**Acceptance Scenarios**:

1. **Given** a tournament is running, **When** a player opens the remote view URL in a browser, **Then** they see current clock, blinds, and player count within 2 seconds
2. **Given** the blind level changes from 200/400 to 300/600, **When** observing the remote view, **Then** the blind update appears within 1 second
3. **Given** 50 remote viewers connected, **When** a player busts out, **Then** all viewers see the updated player count within 2 seconds
4. **Given** no mobile app installed, **When** accessing remote view on a mobile browser, **Then** the view is responsive and fully functional
5. **Given** remote view is loaded, **When** network connection degrades to 3G speeds, **Then** updates continue with graceful degradation (longer sync intervals but no disconnection)

---

### User Story 4 - Cross-Platform Mobile Apps (Priority: P2)

Tournament directors and players need native mobile apps on both iOS and Android that provide full functionality with consistent user experience across platforms.

**Why this priority**: Tournament directors use both iOS and Android devices. A single codebase that targets both platforms reduces development effort and ensures feature parity.

**Independent Test**: Can be fully tested by installing the app on an iPhone and an Android device, running a complete tournament workflow on each, and verifying identical functionality and behavior.

**Acceptance Scenarios**:

1. **Given** an iOS device and an Android device, **When** both run the timer simultaneously, **Then** both display identical times within 0.1 seconds of each other
2. **Given** a tournament created on iOS, **When** opened on Android, **Then** all tournament data is present and editable
3. **Given** the Android app, **When** navigating through screens, **Then** the layout matches iOS with platform-appropriate UI patterns (Material Design vs iOS design)
4. **Given** a notification is scheduled for break end, **When** the app is backgrounded on either platform, **Then** the notification fires at the correct time on both platforms
5. **Given** different screen sizes (phone vs tablet), **When** the app loads, **Then** the layout adapts appropriately to available screen space

---

### User Story 5 - Central Controller Timekeeping (Priority: P1)

The web-based central controller serves as the authoritative time source for all tournaments, managing multiple simultaneous tournaments with automatic failover and data replication.

**Why this priority**: Central timekeeping ensures consistency across all connected devices. If a director's phone dies, the tournament continues from the central controller. Critical for reliability.

**Independent Test**: Can be fully tested by running 10 simultaneous tournaments, disconnecting the central controller for 5 minutes, then reconnecting and verifying all timers sync correctly with proper state reconciliation.

**Acceptance Scenarios**:

1. **Given** the central controller is running 5 tournaments, **When** a new tournament is created, **Then** it is allocated a unique ID and appears in the tournament list immediately
2. **Given** a tournament timer at 00:15:30.0, **When** the central controller restarts, **Then** the timer resumes from the correct elapsed time within 1 second
3. **Given** 3 devices connected to a tournament, **When** the central controller becomes unavailable, **Then** devices continue functioning in offline mode and reconnect automatically when controller returns
4. **Given** data replication enabled, **When** the primary controller fails, **Then** a standby controller takes over within 5 seconds with all data intact
5. **Given** the controller processes 100 sync requests per second, **When** observing performance metrics, **Then** average response time is under 100ms

---

### User Story 6 - Data Export & Migration (Priority: P3)

Users need to export tournament data in standard formats for external systems, with a migration path from the old WordPress-based system.

**Why this priority**: Important for data portability and future integrations, but not critical for initial MVP. Can be added after core functionality is stable.

**Independent Test**: Can be fully tested by running a complete tournament, exporting the data, and importing it into a fresh instance to verify all data transfers correctly.

**Acceptance Scenarios**:

1. **Given** a completed tournament with 100 players, **When** exporting to JSON, **Then** all player data, payouts, and timing records are included in a valid JSON structure
2. **Given** exported tournament data, **When** importing into a new system, **Then** all players, results, and timestamps match the original exactly
3. **Given** WordPress plugin data, **When** running the migration tool, **Then** all tournaments transfer to the new system with data validation warnings for any corrupted records
4. **Given** a tournament in progress, **When** exporting a snapshot, **Then** the export includes current timer state and can be restored on another device

---

### User Story 6a - Migration Failure Recovery (Priority: P2)

WordPress plugin migrations MUST protect data integrity and provide recovery options when errors occur.

**Why this priority**: Data loss during migration is unacceptable. Tournaments represent valuable historical data that directors cannot afford to lose. Rollback capabilities prevent data corruption.

**Independent Test**: Can be fully tested by creating a WordPress export file with intentional errors, running validation mode, then attempting import with rollback verification.

**Acceptance Scenarios**:

1. **Given** a WordPress export file with 100 tournaments, **When** running validation mode (dry-run), **Then** the system scans the file, reports any corrupted records without importing, and shows a summary of "X valid, Y errors, Z warnings"
2. **Given** validation passes with no errors, **When** the director confirms import, **Then** the system creates a database snapshot before importing any data
3. **Given** an import fails after 50 tournaments due to a corrupt record, **When** the failure occurs, **Then** the system automatically rolls back to the pre-import snapshot and preserves the original database state
4. **Given** a rollback occurs, **When** viewing the import status, **Then** the system displays "Import failed - rolled back" with error details and the number of records that were successfully imported before failure
5. **Given** partial data after rollback, **When** the director fixes the corrupt data and retries import, **Then** the system creates a new snapshot and attempts the import again

**Migration Modes**:
- **Validation (Dry-run)**: Scan and report errors without importing; always runs first before actual import
- **Import with Snapshot**: Create database backup, import data, rollback on failure
- **Continue on Error**: (Optional P3) Import valid records, log errors for corrupted ones

**Error Reporting**: Migration errors MUST include:
- Record type (tournament, player, result, etc.)
- Record identifier (ID, name, date)
- Error reason (missing field, invalid data type, constraint violation)
- Recommendation for fixing the error

---

### Edge Cases

- **Clock Drift**: What happens when a device's internal clock drifts significantly from the central controller time?
  - System uses NTP synchronization and timestamps from central controller as source of truth
  - Local device clock is for display only; elapsed time calculated from controller timestamps

- **Simultaneous Conflicting Edits**: What happens when two directors edit the same player record offline simultaneously?
  - Last-write-wins based on server timestamp
  - Conflict detection notifies user of overwrites
  - Audit trail retains all versions for manual reconciliation

- **Controller Failover During Critical Moment**: What happens if the central controller fails exactly when a blind level is supposed to change?
  - Edge devices continue locally and sync when controller returns
  - Blind level change is idempotent (can be applied multiple times safely)
  - Controller records "estimated" transition time for audit purposes

- **Data Corruption After Extended Offline**: What happens if a device is offline for weeks with changes that conflict with server state?
  - System presents diff view and asks user to resolve conflicts
  - Option to force local changes or accept server state
  - Automated conflict resolution rules can be configured per organization

- **Massive Tournament (1000+ Players)**: How does the system handle performance with very large player counts?
  - Pagination for player lists (50 players per page)
  - Delta sync only transmits changed records
  - Timer updates are independent of player count (always efficient)
  - Remote viewing aggregates updates (bulk sends every 1 second rather than per-change)

## Requirements *(mandatory)*

### Functional Requirements

#### Timer & Timekeeping

- **FR-001**: System MUST maintain timer precision of 1/10th second (100ms) or better at all times
- **FR-002**: Timer MUST display tenths of seconds visibly to users
- **FR-003**: Timer state MUST persist across app backgrounding, crashes, and device restarts
- **FR-004**: System MUST support multiple concurrent tournaments (up to 100) with independent timers and automatic failover
- **FR-005**: Timer MUST alert users at configurable intervals (blind changes, breaks, tournament end)
- **FR-006**: System MUST record all timer events with timestamps for audit and replay
- **FR-007**: Central controller MUST serve as authoritative time source for all connected devices
- **FR-008**: System MUST synchronize timer state across devices within 200ms

#### Offline Capability

- **FR-009**: Core tournament functions (timer, player management, bustouts) MUST work without network connectivity
- **FR-010**: System MUST queue all changes while offline and sync when connection restores
- **FR-011**: System MUST detect and merge conflicting changes using last-write-wins with timestamps
- **FR-012**: Local data MUST persist across app restarts without network connectivity
- **FR-013**: System MUST display current sync status (connected, syncing, offline) to users

#### Data Management

- **FR-014**: System MUST store all tournament data in a lightweight embedded database with replication support
- **FR-015**: Database MUST support automatic replication between central controller and backup instances
- **FR-016**: System MUST support player registration, bustout recording, and payout calculations
- **FR-017**: System MUST calculate payouts automatically based on finish position and prize pool
- **FR-018**: System MUST support tournament templates (blind structures, starting stacks, break intervals)
- **FR-019**: All data changes MUST be logged with timestamp, user, and change type for audit trail

#### Cross-Platform Mobile Apps

- **FR-020**: System MUST provide native mobile applications for iOS and Android
- **FR-021**: Mobile apps MUST provide identical functionality on both platforms
- **FR-022**: Apps MUST adapt layout for different screen sizes (phones and tablets)
- **FR-023**: Apps MUST support push notifications for time-sensitive alerts
- **FR-024**: Apps MUST allow users to create, edit, and delete tournaments
- **FR-025**: Apps MUST allow users to register players, record bustouts, and adjust settings
- **FR-026**: Apps MUST display current timer, blind levels, and player counts

#### Remote Viewing

- **FR-027**: System MUST provide web-based remote viewing accessible via standard browsers
- **FR-028**: Remote views MUST NOT require authentication or app installation for public tournaments
- **FR-029**: Remote views MUST display current timer, blind levels, remaining players, and payouts in real-time
- **FR-030**: System MUST support at least 100 concurrent remote viewers per tournament without performance degradation
- **FR-031**: Remote view updates MUST use efficient protocols (WebSockets or Server-Sent Events)
- **FR-032**: Remote views MUST be responsive and work on mobile browsers

#### Central Controller

- **FR-033**: System MUST provide a web-based central controller for timekeeping and data coordination
- **FR-034**: Central controller MUST manage tournament state synchronization and failover coordination across instances
- **FR-035**: Central controller MUST support automatic failover to standby instances
- **FR-036**: Central controller MUST replicate data to backup instances in real-time
- **FR-037**: System MUST support multiple central controller instances for load balancing
- **FR-038**: Central controller MUST provide health monitoring and alerting

#### API Integration Layer (Future)

- **FR-039**: System MUST provide a well-documented API for future integrations
- **FR-040**: API MUST support CRUD operations for tournaments, players, and results
- **FR-041**: API MUST support authentication via OAuth2 or JWT tokens
- **FR-042**: API MUST support real-time event streaming for timer updates
- **FR-043**: API MUST be versioned to support backward compatibility

#### Data Export & Migration

- **FR-044**: System MUST support exporting tournament data in JSON format
- **FR-045**: System MUST support importing tournament data from JSON exports
- **FR-046**: System MUST provide migration tools from the WordPress plugin data format
- **FR-047**: Exports MUST include all player data, results, payouts, and timer events
- **FR-048**: System MUST validate imported data and report any errors or warnings

#### Security & Access Control

- **FR-049**: System MUST support user authentication with email/password or OAuth providers
- **FR-050**: System MUST support role-based access control (director, scorekeeper, viewer)
- **FR-051**: System MUST encrypt sensitive data at rest (auth tokens, personal information)
- **FR-052**: System MUST use HTTPS/TLS for all network communication
- **FR-053**: System MUST support session management with automatic expiration

#### Performance & Reliability

- **FR-054**: Mobile apps MUST cold launch in under 3 seconds on mid-range devices
- **FR-055**: Timer display MUST update smoothly at 60 FPS
- **FR-056**: API responses MUST complete in under 200ms (p95)
- **FR-057**: ~~System MUST support 100 concurrent tournaments~~ *(consolidated into FR-004)*
- **FR-058**: System MUST support 1000 concurrent users across all tournaments
- **FR-059**: Timer MUST run accurately for 24+ hours without drift exceeding 1 second

### Key Entities

- **Tournament**: Represents a poker tournament event with timer, blind structure, registered players, and results. Key attributes: unique ID, name, start time, current blind level, timer state, prize pool, payout structure, status (upcoming, active, completed).

- **Player**: An individual participant in tournaments. Key attributes: unique ID, name, email (optional), contact info, statistics (tournaments played, wins, total winnings). Players can be registered in multiple tournaments.

- **TournamentPlayer**: Represents a player's participation in a specific tournament. Key attributes: tournament ID, player ID, registration timestamp, starting stack, finish position, winnings, bustout time, number of eliminations (bounties). Links Tournament and Player as a many-to-many relationship with tournament-specific data.

- **BlindLevel**: Defines a blind level in the tournament structure. Key attributes: level number, small blind amount, big blind amount, ante amount (if any), duration in minutes. Blind levels are ordered sequentially within a tournament.

- **BlindSchedule**: Template of blind levels for a tournament. Key attributes: schedule ID, name, list of BlindLevels, break intervals between levels. Tournaments can share BlindSchedules for consistency.

- **TimerEvent**: Audit log of timer state changes. Key attributes: event ID, tournament ID, timestamp, event type (level_start, level_end, break_start, break_end, tournament_start, tournament_end), previous state, new state. Used for reconciliation and dispute resolution.

- **SyncRecord**: Represents a data synchronization event between devices and central controller. Key attributes: sync ID, device ID, timestamp, list of changed entities, conflict resolution outcome, sync status (pending, success, failed). Enables offline sync and conflict detection.

- **User**: Represents a system user with authentication and permissions. Key attributes: user ID, email, password hash, role (admin, director, scorekeeper, viewer), created timestamp, last login timestamp. Users can be associated with multiple tournaments.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Tournament directors can run a complete 50-player tournament from start to finish using only the mobile app, including player registration, timer management, bustout recording, and payout calculation, in under 30 minutes total hands-on time.

- **SC-002**: Timer maintains accuracy within 1 second over an 8-hour continuous operation, including app backgrounding, device restarts, and temporary network disconnections.

- **SC-003**: Tournament directors can manage tournaments offline (no network connectivity) for 24+ hours, with all data syncing correctly when connectivity is restored.

- **SC-004**: 100 remote viewers can observe a tournament simultaneously with updates appearing within 2 seconds of changes being made by the tournament director.

- **SC-005**: Central controller can manage 50 concurrent tournaments with 1000 total connected devices without performance degradation or timer accuracy loss.

- **SC-006**: Mobile apps are functionally identical on iOS and Android, with 100% feature parity and consistent user experience across platforms.

- **SC-007**: Users can migrate all data from the WordPress plugin to the new system with zero data loss and automated validation of all migrated records.

- **SC-008**: System uptime exceeds 99.5% with automatic failover completing in under 10 seconds when the primary controller fails.

- **SC-009**: New users can install the app, create their first tournament, and start the timer in under 5 minutes without documentation or training.

- **SC-010**: Tournament data can be exported and imported between instances with 100% data fidelity and automated integrity validation.

## Assumptions

1. **Tournament Director Expertise**: Users have basic familiarity with running poker tournaments (blinds, bustouts, payouts). The app does not need to teach poker tournament mechanics.

2. **Network Availability**: While offline capability is critical, most venues will have some form of WiFi or cellular connectivity available for the central controller.

3. **Device Ownership**: Tournament directors own or control the mobile devices used for timer management. Shared kiosk mode is not a primary use case.

4. **Moderate Tournament Sizes**: Primary target is tournaments with 10-200 players. System should support larger tournaments (1000+) but this is not the common case.

5. **Single Tournament Per Device**: Most tournament directors will manage one tournament at a time per device. Multi-tournament management is supported but not the primary workflow.

6. **Data Privacy Requirements**: Player email addresses and contact info are considered moderately sensitive and should be encrypted at rest, but full GDPR compliance is not an immediate requirement.

7. **Hosted Central Controller**: The central controller will be hosted on a cloud platform (AWS, GCP, Azure) rather than on-premise hardware. This ensures reliability and reduces deployment complexity.

8. **Migration from WordPress**: The existing WordPress plugin contains valuable historical data that users will want to migrate. Migration is a one-time bulk operation, not ongoing sync.

9. **Authentication Provider**: User authentication will use a standard provider (Auth0, Firebase Auth, or custom OAuth2) rather than building auth from scratch.

10. **Real-Time Technology**: Real-time updates for remote viewing will use standard web technologies (WebSockets or Server-Sent Events) rather than custom protocols.

## Out of Scope

The following features are explicitly excluded from this rewrite and can be considered for future phases:

1. **WordPress Plugin Integration**: The API integration layer for WordPress connection is deferred to a later phase. This phase focuses on standalone functionality.

2. **Advanced Analytics**: Detailed player statistics, ROI tracking, and historical analytics are out of scope. Basic tournament results and payouts are included.

3. **Social Features**: Player profiles, avatars, chat, or social networking features are not included in this phase.

4. **Payment Processing**: Handling actual payments, buy-ins, or financial transactions is out of scope. The system tracks payouts for informational purposes only.

5. **Multi-Tenant SaaS**: This system is designed for single-organization deployment. Multi-tenant hosting for multiple organizations is a future consideration.

6. **Live Streaming Integration**: Video streaming or integration with platforms like Twitch/YouTube is not included.

7. **Seating Chart Management**: Visual seating charts, table assignments, and seat movement management are out of scope.

8. **Hand History Tracking**: Recording individual poker hands, pot sizes, or detailed hand-for-hand play is not included.

9. **League Management**: Season-long scoring, leaderboards across tournaments, and league standings are deferred to future phases.

10. **White-Label Customization**: Custom branding, logos, and theming for different organizations are not included in the initial release.

---

## Testing & Validation Requirements

### Automated Precision Testing (Constitution II)

All timer implementations MUST include automated precision validation tests:

- **TP-001**: 100ms precision test across all scenarios (backgrounding, restart, disconnect)
- **TP-002**: 10Hz display smoothness test (no stuttering, no skipped frames)
- **TP-003**: 24-hour timer accuracy test (drift < 1 second over 24 hours)
- **TP-004**: 30-minute backgrounding drift test (within 0.2s per US1-A1)
- **TP-005**: Cross-platform timer sync test (0.1 second tolerance per US4-A1)

**Testing Methodology**:
- Use automated E2E tests with Detox (iOS) and Espresso (Android)
- Measure actual elapsed time vs displayed elapsed time
- Test on minimum device specifications: iPhone 8 (iOS 13) and Pixel 3 (Android 8)
- Run tests for 8 continuous hours covering backgrounding, force-quit, and restart scenarios

### Performance Testing

- **PT-001**: Cold launch test - measure time from app tap to first render on mid-range devices (iPhone 12, Pixel 6)
  - Target: <3 seconds per FR-054
  - Method: Average of 10 cold launches after device reboot
- **PT-002**: Timer display FPS test - measure frame rate during timer updates
  - Target: 60 FPS per FR-055
  - Method: Monitor display refresh rate while timer runs for 30 minutes
- **PT-003**: API response time test - measure p95 latency for sync endpoints
  - Target: <200ms per FR-056
  - Method: Load test with 100 concurrent devices making sync requests
- **PT-004**: Bundle size test - verify mobile app binary size
  - Target: <50MB per constitution performance requirements
  - Method: Measure production build size for both iOS and Android

### Synchronization Testing

- **ST-001**: 200ms cross-device sync test
  - Target: Timer state syncs within 200ms across all devices per FR-008
  - Method: Start timer on device A, measure time until device B shows update
  - Conditions: 3 devices connected, good network (WiFi), tournament active
- **ST-002**: 10-second offline sync test
  - Target: 20 local changes sync within 10 seconds per US2-A3
  - Method: Make 20 changes offline, reconnect, measure sync completion time
  - Data volume: ~50KB of player registration and bustout data
- **ST-003**: 24-hour offline stress test
  - Target: Core functions work offline for 24+ hours per constitution requirement
  - Method: Run tournament for 24 hours in airplane mode, verify all functions (timer, player registration, bustouts, settings adjustments) work correctly
  - Validation: On reconnection, all data syncs with no conflicts or data loss

### Failover Testing

- **FT-001**: Controller heartbeat detection test
  - Target: Detect primary controller failure within 2 seconds per US5-A3
  - Method: Send heartbeat every 500ms; if 4 consecutive heartbeats missed (2 seconds), mark as failed
  - Validation: Standby controller initiates takeover within 2 seconds of detection
- **FT-002**: 5-second standby takeover test
  - Target: Standby controller takeover completes within 5 seconds per US5-A4
  - Method: Kill primary controller, measure time until standby accepts requests
  - Data integrity validation: Compare database state before/after takeover; verify all timer states, player records, and sync queues match
- **FT-003**: Split-brain prevention test
  - Target: No split-brain when controller fails during blind level transition
  - Method: Trigger controller failure exactly at blind level change (during transition)
  - Validation: Only one controller serves as primary; use lock file or database mutex; verify idempotent transition logic
- **FT-004**: Load shedding test
  - Target: System degrades gracefully at 100 sync requests/sec per US5-A5
  - Method: Send 150 sync requests/sec; verify system prioritizes timer updates over player sync
  - Behavior: Timer updates process immediately; player sync queues with exponential backoff; return 503 Service Unavailable with Retry-After header

### Cross-Platform Testing

- **XP-001**: Feature parity test - verify 100% functionality match between iOS and Android
  - Method: Create test matrix of all features; verify each works identically on both platforms
- **XP-002**: Touch target test - verify all tappable elements meet 44px minimum per constitution
  - Method: Automated UI test that measures all interactive element dimensions
- **XP-003**: Screen orientation test - verify both portrait and landscape layouts work
  - Method: Rotate device through all orientations; verify layout adapts correctly
  - Timer display must remain visible and readable in all orientations
- **XP-004**: Mobile browser compatibility test - verify remote view works on Chrome Mobile and Safari Mobile
  - Method: Test remote view on iOS Safari and Android Chrome; verify full functionality including real-time updates
- **XP-005**: WebSocket fallback test - verify graceful degradation when WebSocket unavailable
  - Method: Block WebSocket connections; verify system falls back to polling (5-second intervals)
  - Validation: Remote view continues to update with longer latency but no data loss

### Exception Flow Testing

- **ET-001**: Simultaneous conflicting edits test - verify last-write-wins conflict resolution
  - Method: Two devices edit same player offline simultaneously; reconnect; verify winner based on timestamp
- **ET-002**: Clock drift correction test - verify system handles significant clock drift
  - Method: Set device clock 10 minutes ahead; verify system uses server timestamps for display
- **ET-003**: Database corruption recovery test - verify validation and repair procedures
  - Method: Inject corruption into local database; verify app detects corruption on launch and offers repair (re-sync from server)
- **ET-004**: WebSocket disconnection test - verify reconnection behavior and state reconciliation
  - Method: Trigger WebSocket disconnect during active tournament; verify automatic reconnection with exponential backoff
  - State reconciliation: On reconnect, fetch latest state and reconcile with local changes
- **ET-005**: Insufficient storage test - verify behavior when device runs out of space
  - Method: Fill device storage to 95%; verify app displays warning and prevents new offline changes
  - User notification: "Storage nearly full. Sync pending changes or free up space."
- **ET-006**: Tournament duplication test - verify ability to create tournament from template
  - Method: Select existing tournament, choose "Duplicate as Template"; verify new tournament created with same blind structure and settings

### Device & Tournament Binding

- **Device-to-Tournament Binding**: When a director starts a timer on a device, that device becomes the "master" for that tournament
  - Master device has exclusive control over pause/resume and manual blind adjustments
  - If master device dies (battery, crash, loss), any connected device can claim master status after 30-second timeout
  - Claim process: Long-press timer, confirm "Take control of tournament" dialog
  - Central controller records master deviceId; fails over to next active device on timeout

### Minimum Font Sizes & Readability

- **Timer Display**: Minimum 48px font for time display on phones, 64px on tablets
- **Blind Amounts**: Minimum 32px font on phones, 48px on tablets
- **Player Count**: Minimum 24px font on phones, 32px on tablets
- **Readability Guarantee**: All timer-critical information must be readable at arm's length (approximately 50cm) in normal lighting conditions
- **Contrast**: Minimum WCAG AA contrast ratio (4.5:1 for normal text, 3:1 for large text)

### E2E Test Requirements

- **E2E-001**: Complete tournament workflow test - verify full tournament lifecycle on both iOS and Android
  - Scenarios: Create tournament, register 50 players, start timer, record 30 bustouts, verify payouts
- **E2E-002**: Offline-first workflow test - verify tournament runs completely offline
  - Scenarios: Enable airplane mode, run full tournament, reconnect, verify sync
- **E2E-003**: Multi-device sync test - verify 3 devices stay synchronized throughout tournament
  - Scenarios: Start tournament on device A, add devices B and C, make changes on each, verify all sync within 200ms