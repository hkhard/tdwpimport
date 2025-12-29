# Feature Specification: Tournament Director 3 Integration

**Feature Branch**: `001-td3-integration`
**Created**: 2025-10-30
**Status**: Draft
**Input**: User description: "Tournament Director 3 Integration - Complete Feature Set - Transform the Poker Tournament Import plugin from a simple .tdt file importer into a full-featured Tournament Director web-based alternative"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Tournament Setup & Configuration (Priority: P1 - Phase 1 Foundation)

As a tournament organizer, I need to create and configure tournaments directly in WordPress without requiring Tournament Director 3 software, so I can set up buy-ins, rebuys, blinds, and prize structures efficiently.

**Why this priority**: This is the foundation for all tournament management. Without the ability to create tournaments, no other features can function. This enables users to transition from TD3 to the web-based system and provides immediate value.

**Independent Test**: Can create a complete tournament setup (buy-in $50, 1 rebuy allowed, 9-level blind structure, 50/30/20 prize pool) through WordPress admin, save as template, and view the configuration without running the tournament.

**Acceptance Scenarios**:

1. **Given** I'm logged into WordPress admin with tournament management permissions, **When** I navigate to "Create New Tournament" and enter buy-in ($50), rebuy ($50, max 1), and add-on ($25) details with rake (10%), **Then** the system calculates and displays total prize pool and rake amount accurately.

2. **Given** I'm configuring a tournament, **When** I create a blind structure with 9 levels (starting 25/50, doubling each level, 15-minute rounds with ante from level 5), **Then** I can preview the complete blind schedule and save it as a template named "Fast Turbo 9-Level".

3. **Given** I'm setting up prizes, **When** I enter 20 registered players with $50 buy-in, **Then** the system automatically suggests prize distribution (50/30/20 for top 3) totaling the prize pool minus rake.

4. **Given** I've configured a complete tournament, **When** I save it as a template named "Weekly $50 Freezeout", **Then** I can load this template for future tournaments with all settings pre-filled.

---

### User Story 2 - Player Registration Management (Priority: P1 - Phase 1 Foundation)

As a tournament organizer, I need to register players efficiently through both admin interface and public forms, import player lists from spreadsheets, and maintain a player database, so I can reduce registration time and errors.

**Why this priority**: Player registration is essential for running any tournament. This must be in place before live tournament operations can begin. Provides immediate value by streamlining the registration workflow.

**Independent Test**: Register 50 players through admin interface, have 20 players self-register via frontend shortcode, import 30 more from CSV file, verify all 100 players appear in the tournament roster with correct buy-in amounts.

**Acceptance Scenarios**:

1. **Given** I'm in the tournament admin, **When** I search for "John Smith" in the player database and click "Register", **Then** John Smith is added to the tournament roster and marked as paid for the $50 buy-in.

2. **Given** I've embedded `[tournament_registration id="123"]` shortcode on a page, **When** a player visits the page and fills out the form (name, email, phone), **Then** they receive a confirmation email and appear in the pending registration list for admin approval.

3. **Given** I have an Excel file with columns (Name, Email, Phone, Paid Amount), **When** I import the file through the "Import Players" interface, **Then** all valid rows are added to the player database and registered for the tournament, with an error report for invalid rows.

4. **Given** the tournament has started, **When** a late registrant arrives, **Then** I can quick-register them and immediately assign them to a table with correct starting chips.

---

### User Story 3 - Live Tournament Clock & Control (Priority: P2 - Phase 2 Live Operations)

As a tournament director, I need a synchronized tournament clock with start/pause/resume controls and break management, displayed on public screens, so players always know the current blind level and time remaining.

**Why this priority**: This transforms the plugin from a planning tool to an operational system. Essential for running live tournaments. Depends on Phase 1 foundation being complete.

**Independent Test**: Start a tournament with 50 registered players, advance through 3 blind levels (15 minutes each), pause for a scheduled break, resume after break, verify all connected public displays show synchronized time and blind levels.

**Acceptance Scenarios**:

1. **Given** a tournament is configured and players are registered, **When** I click "Start Tournament" at 7:00 PM, **Then** the clock begins at level 1 (25/50 blinds, 15:00 countdown), all public display screens show synchronized clock, and the start time is logged.

2. **Given** the tournament clock is running at level 3 (11:23 remaining), **When** I click "Pause", **Then** the clock freezes at 11:23, pause time is logged, and all displays show "PAUSED" status with frozen time.

3. **Given** a scheduled break is configured after level 4, **When** level 4 countdown reaches 0:00, **Then** the clock automatically transitions to "BREAK - 15:00" countdown, break music plays (if configured), and displays show break countdown.

4. **Given** the tournament is on break with 3:42 remaining, **When** I click "End Break Early", **Then** the clock immediately transitions to level 5 blinds (400/800 with 75 ante), and play resumes.

---

### User Story 4 - Automated Table Management (Priority: P2 - Phase 2 Live Operations)

As a tournament director, I need automatic player seating, table balancing, and table breaking, with the ability to make manual adjustments, so tables remain balanced and tournament flow is efficient without manual calculation.

**Why this priority**: Manual table balancing is error-prone and time-consuming. Automation is a key differentiator from TD3. Requires player registration and clock system from earlier priorities.

**Independent Test**: Start tournament with 83 players on 9 tables (9-10 players per table), run simulation where 30 players bust out, verify system automatically moves players to balance tables, breaks 3 tables, and maintains ±1 player balance across all active tables.

**Acceptance Scenarios**:

1. **Given** 83 players are registered for a tournament, **When** I click "Auto-Seat All Players", **Then** the system randomly assigns players to 9 tables (Table 1-8 have 9 players, Table 9 has 11 players), ensuring no table differs by more than 1 player, and displays seating chart.

2. **Given** tournament is in progress with 67 players on 8 tables, **When** a player at Table 5 (10 players) busts out, **Then** the system immediately moves one player from Table 3 (9 players) to Table 5, maintaining 9 players on Table 5 and 8 players on Table 3, displays movement notification.

3. **Given** a table has 5 players remaining, **When** the table balancing threshold is reached, **Then** the system marks that table for breaking, moves all 5 players to other tables to maintain balance, updates seating chart, and displays "Table 7 Breaking - Move to assigned tables" on the table screen.

4. **Given** auto-balancing is enabled, **When** I need to manually move Player X from Table 2 Seat 4 to Table 6 Seat 8 and click "Lock Move", **Then** the system allows the manual move, locks both seats from auto-balancing, logs the manual adjustment, and updates displays.

---

### User Story 5 - Live Player Operations & Transactions (Priority: P2 - Phase 2 Live Operations)

As a tournament director, I need to process bust-outs with automatic ranking calculation, handle rebuys and add-ons, track chip count adjustments, and maintain a complete transaction log, so all tournament actions are accurately recorded and retrievable.

**Why this priority**: These are core operational capabilities for running live tournaments. Essential for maintaining tournament integrity and providing accurate statistics. Builds on table management system.

**Independent Test**: Process 15 bust-outs (ranks 86-100), perform 12 rebuys during rebuy period, process 8 add-ons at break, make 3 chip count corrections, verify transaction log shows all 38 transactions with timestamps and reasons, confirm final chip counts and prize pool are accurate.

**Acceptance Scenarios**:

1. **Given** Player John Smith at Table 5 Seat 3 busts out when 47 players remain, **When** I click "Bust Out" on his player card, **Then** the system assigns him 47th place, removes him from the seating chart, records bust-out time (8:23 PM), triggers table rebalancing if needed, and logs the transaction.

2. **Given** the rebuy period is active (levels 1-4) and Player Sarah Jones wants a rebuy, **When** I click "Process Rebuy" and enter $50 received, **Then** Sarah receives starting stack (10,000 chips), prize pool increases by $45 (after $5 rake), transaction is logged, and her chip count updates to current stack + 10,000.

3. **Given** the scheduled break after level 4 has started, **When** 23 players purchase add-ons at $25 each, **Then** I can batch-process all add-ons via "Bulk Add-On Processing", each player receives 10,000 additional chips, prize pool increases by $517.50 (after rake), and all transactions are individually logged.

4. **Given** Player Mike's chip count shows 45,000 but actual count is 52,000, **When** I click "Adjust Chips", enter +7,000 with reason "Miscount - dealer error", **Then** Mike's chip count updates to 52,000, the adjustment is logged with timestamp and reason, and adjustment history shows for tournament director review.

---

### User Story 6 - Tournament Statistics & Export (Priority: P2 - Phase 2 Live Operations)

As a tournament organizer, I need real-time tournament statistics, the ability to export results in multiple formats (PDF, Excel, CSV, .TDT), email results to players, and archive completed tournaments, so I can analyze performance and maintain historical records.

**Why this priority**: Reporting and archival complete the live operations phase. Enables users to share results and maintain records. Depends on all Phase 2 operational features being complete.

**Independent Test**: Complete a 100-player tournament, generate live statistics showing chip leader, average stack, bubble position, export final results as PDF with logo and formatting, send email to all 100 players with results, verify tournament is archived with complete history.

**Acceptance Scenarios**:

1. **Given** a tournament is in progress with 34 remaining players, **When** I view the "Live Statistics" dashboard, **Then** I see current chip leader (name, chips, BB equivalent), average stack, biggest stack, shortest stack, bubble position (9th place for 8 paid spots), total prize pool, and time elapsed.

2. **Given** a tournament has concluded with 100 players, **When** I click "Export Results" and select PDF format, **Then** the system generates a PDF with tournament details (date, buy-in, entries, prize pool), final standings (1-100 with names and prizes), blind structure used, and optional logo/branding.

3. **Given** the tournament results are finalized, **When** I click "Email Results to All Players" with custom message "Thanks for playing!", **Then** all 100 registered players receive an email with their finishing position, prize amount (if won), tournament summary, and the custom message.

4. **Given** a tournament is complete, **When** I click "Archive Tournament", **Then** the tournament status changes to "Archived", all data is preserved (players, transactions, times, chip counts), tournament is moved to archive list, and cannot be accidentally modified but can be viewed and re-exported.

---

### User Story 7 - Custom Display & Layout System (Priority: P3 - Phase 3 Professional Features)

As a venue operator, I need customizable tournament displays with token-based templates, drag-and-drop layout builder, multiple screen configurations, and branded visuals, so I can create professional-looking displays that match my venue's brand.

**Why this priority**: Branding and customization are premium features that differentiate professional venues. Enhances visual presentation but not essential for tournament operations. Builds on complete operational system.

**Independent Test**: Create 3 screen layouts (Main Clock with logo, Rankings Board with sponsor banner, Prize Pool Display with custom colors), use tokens ({{tournament_name}}, {{current_blind}}, {{time_remaining}}), preview on different screen sizes, deploy to 3 different display endpoints.

**Acceptance Scenarios**:

1. **Given** I'm creating a custom clock display, **When** I drag "Tournament Name", "Current Blind Level", "Time Remaining", and "Next Blind" widgets onto the canvas and insert {{venue_logo}} token, **Then** I can position and resize each element, preview with live tournament data, and see my venue logo displayed.

2. **Given** I want branded displays, **When** I upload venue logo (PNG, 1920x200px), set brand colors (primary: #FF0000, secondary: #000000), and apply to all displays, **Then** all tournament screens use these brand elements consistently, and I can preview across clock, rankings, and prize displays.

3. **Given** I have multiple screens in my venue (2 main floor displays, 1 bar area display, 1 lobby display), **When** I create 4 different screen configurations and assign them to physical display endpoints, **Then** each endpoint can load its assigned configuration via URL parameter, auto-refresh every 5 seconds, and show synchronized tournament data.

4. **Given** I'm editing a rankings display, **When** I use tokens {{player_rank}}, {{player_name}}, {{chip_count}}, {{big_blinds}}, **Then** the template populates with real tournament data for preview, I can apply custom CSS for fonts/spacing, save as "Venue Rankings Template v2", and deploy to production displays.

---

### User Story 8 - Events & Notifications System (Priority: P3 - Phase 3 Professional Features)

As a tournament director, I need automated event triggers (level changes, breaks, final table, eliminations) with sounds, email/SMS notifications, and custom alerts, so players are informed of important tournament events without manual announcements.

**Why this priority**: Professional venues need sophisticated alerting. Improves player experience and reduces TD workload. Requires complete display system and operational features.

**Independent Test**: Configure 8 event triggers (level up sound, break alert, final table notification, bubble warning), set email notification for final 9 players, run tournament simulation, verify all events fire at correct times with configured sounds and notifications delivered.

**Acceptance Scenarios**:

1. **Given** I'm configuring tournament events, **When** I set "Level Change" event to play "level-up.mp3" at 10 seconds before each level change, **Then** at 0:10 remaining in each level, the sound plays on all displays, visual countdown appears, and next blind level is highlighted.

2. **Given** I want to notify players of break time, **When** I configure "Break Start" event to send email notification to all active players with subject "15-Minute Break", **Then** when break begins, all active players receive the email immediately with break duration and resume time.

3. **Given** I want special notification for final table, **When** I configure "Final Table" event for 9 players remaining with email notification and "final-table-fanfare.mp3" sound, **Then** when 10th player busts, the sound plays, email is sent to final 9 players, and displays show "FINAL TABLE" announcement.

4. **Given** I have custom events, **When** I create "Bubble Warning" event for when players = paid positions + 1 (bubble), play "bubble-tension.mp3", display "HAND-FOR-HAND PLAY" on all screens, **Then** when bubble is reached, all triggers fire, and I can manually end hand-for-hand mode when bubble bursts.

---

### User Story 9 - Advanced Chip & League Management (Priority: P3 - Phase 3 Professional Features)

As a venue operator running multiple tournaments, I need chipset management, league tracking, player photos, receipt generation, and badge printing, so I can manage tournament series professionally and provide enhanced player experience.

**Why this priority**: These are professional venue features that enhance but aren't essential for basic tournament operations. Appeals to serious operators running regular tournaments and leagues.

**Independent Test**: Create custom chipset (13 denominations with colors), calculate capacity for 200-player tournament, run 4-week league with points tracking, upload photos for 50 players, generate 20 receipts, print 20 name badges with QR codes.

**Acceptance Scenarios**:

1. **Given** I'm setting up venue chipsets, **When** I configure chipset "Venue Standard" with 13 denominations (25, 100, 500, 1K, 5K, 10K, 25K, 50K, 100K, 250K, 500K, 1M, 5M) and chip counts per denomination, **Then** the system calculates total bank value, suggests starting stacks for various buy-in levels, and warns if chipset is insufficient for tournament size.

2. **Given** I'm running "Spring Poker League" with 6 weekly tournaments, **When** I configure league settings (points formula, drop worst result, top 10 paid), **Then** after each tournament, league standings update automatically, players can view standings via shortcode `[league_standings id="spring-2025"]`, and final payouts calculate from points at league end.

3. **Given** I want professional player experience, **When** I upload player photo for "John Smith" (JPG, 200x200px), **Then** John's photo appears on seating displays, tournament results exports, and his player profile page, with automatic resizing and optimization.

4. **Given** a player requests receipt for $250 tournament buy-in, **When** I click "Generate Receipt" for John Smith, **Then** a PDF receipt generates with venue details, player name, tournament name, date, amount paid ($250), items (1x Buy-in, 1x Rebuy, 1x Add-on), payment method, and unique receipt number for tax purposes.

---

### User Story 10 - Enterprise Features & API (Priority: P4 - Phase 4 Premium Features)

As an enterprise venue operator, I need advanced features like multi-monitor support, hand timing analytics, mobile app control via REST API, custom formula engine with TD3 import, and comprehensive financial reporting, so I can achieve complete TD3 feature parity with web-based advantages.

**Why this priority**: These are premium enterprise features targeting the highest-tier customers. Nice-to-have enhancements that provide TD3 parity and unique web advantages. Represents final feature set for complete product.

**Independent Test**: Configure 4 monitors (main clock, seating chart, rankings, prizes), enable hand timer, import TD3 config file with custom formulas, control tournament via mobile app API calls, generate monthly profitability report showing ROI across 20 tournaments.

**Acceptance Scenarios**:

1. **Given** I have 4 display monitors in the venue, **When** I configure display endpoints (Monitor1=clock, Monitor2=seating, Monitor3=rankings, Monitor4=prizes), **Then** each monitor loads its assigned view via unique URL, all sync with tournament state in real-time, and I can switch configurations on-the-fly from admin.

2. **Given** I want to track game pace, **When** I enable "Hand Timer" feature and mark each deal, **Then** the system tracks time per hand, calculates average hand duration (3:45), displays slow-play warnings when hand exceeds 5 minutes, and provides per-level pace analytics in reports.

3. **Given** I have Tournament Director 3 with custom configurations, **When** I import TD3 config file (.tdc format) with custom formulas, chipsets, and structures, **Then** the system parses and imports blind structures, prize formulas with variables, chipset definitions, and displays conversion report with any unsupported features.

4. **Given** I want mobile control, **When** I authenticate via REST API (/api/v1/auth) and send POST request to /api/v1/tournament/123/clock/pause, **Then** the tournament clock pauses, API returns success response with current state, and mobile app reflects paused status, enabling full tournament control from mobile device.

5. **Given** I operate 20 tournaments per month, **When** I generate "Monthly Profitability Report" for March 2025, **Then** the report shows total entries (843), total buy-ins ($42,150), total rake ($4,215), expenses breakdown, net profit ($31,220), per-tournament ROI, player retention rate (67%), and year-over-year comparison.

---

### Edge Cases

- What happens when internet connection drops during live tournament (offline mode capability needed)?
- How does system handle clock desync between multiple displays (heartbeat/polling strategy)?
- What happens when two admins simultaneously try to modify tournament state (locking/conflict resolution)?
- How does system handle player re-entry after bust-out if re-entry is allowed (track elimination count)?
- What happens when prize pool doesn't divide evenly (rounding rules, penny allocation)?
- How does system handle players with identical names (unique IDs, display disambiguation)?
- What happens when tournament runs past midnight into next day (date handling in reports)?
- How does system handle negative chip adjustments causing player to bust (validation rules)?
- What happens when late registration closes with players still in queue (pending registration handling)?
- How does system handle table breaking when all other tables are at maximum capacity (overflow handling)?

## Requirements *(mandatory)*

### Functional Requirements

#### Phase 1 - Foundation

- **FR-001**: System MUST allow admin to create tournament with buy-in amount, rebuy settings (count, amount, period), add-on settings (amount, timing), and rake percentage
- **FR-002**: System MUST calculate total prize pool from buy-ins, rebuys, add-ons minus rake automatically
- **FR-003**: System MUST provide blind structure builder with configurable levels (small blind, big blind, ante, duration in minutes)
- **FR-004**: System MUST include pre-configured blind structure templates (Turbo, Standard, Deep Stack)
- **FR-005**: System MUST allow saving custom blind structures as named templates
- **FR-006**: System MUST provide prize structure calculator suggesting distributions based on player count (50/30/20, 40/25/20/15, etc.)
- **FR-007**: System MUST allow manual prize structure customization and chop management
- **FR-008**: System MUST validate prize structure totals match prize pool
- **FR-009**: System MUST provide player database with fields (name, email, phone, notes, photo, stats)
- **FR-010**: System MUST allow admin to register players for specific tournament with payment status tracking
- **FR-011**: System MUST provide frontend registration shortcode `[tournament_registration id="X"]` for player self-registration
- **FR-012**: System MUST support player import from CSV/Excel files with validation
- **FR-013**: System MUST support player export to CSV/Excel formats
- **FR-014**: System MUST save complete tournament configuration as reusable template

#### Phase 2 - Live Operations

- **FR-015**: System MUST provide tournament clock displaying current blind level, time remaining, next blind level, ante
- **FR-016**: System MUST support clock controls (Start, Pause, Resume, Skip Level, Add Time)
- **FR-017**: System MUST synchronize clock across all connected displays using polling mechanism (default 5-second interval)
- **FR-018**: System MUST automatically advance to next blind level when countdown reaches 0:00
- **FR-019**: System MUST support scheduled breaks with countdown timer
- **FR-020**: System MUST allow manual break start/end override
- **FR-021**: System MUST provide public display mode (fullscreen clock view) accessible via URL parameter
- **FR-022**: System MUST automatically seat registered players across tables with random assignment
- **FR-023**: System MUST maintain table balance within ±1 player difference
- **FR-024**: System MUST automatically move players between tables when balance threshold exceeded
- **FR-025**: System MUST identify tables for breaking when player count falls below threshold (default: 50% capacity)
- **FR-026**: System MUST allow manual player movement with seat locking to prevent auto-rebalancing
- **FR-027**: System MUST display visual seating chart showing all tables, seats, and player assignments
- **FR-028**: System MUST process player bust-outs with automatic ranking calculation (finish position = remaining players)
- **FR-029**: System MUST process rebuys during configured rebuy period, updating chip count and prize pool
- **FR-030**: System MUST process add-ons during configured add-on period (typically break)
- **FR-031**: System MUST support late registration during configured period with automatic chip penalty calculation
- **FR-032**: System MUST allow chip count adjustments with mandatory reason field
- **FR-033**: System MUST maintain complete transaction log (timestamp, type, player, amount, reason)
- **FR-034**: System MUST provide live statistics dashboard (chip leader, average stack, bubble position, remaining players)
- **FR-035**: System MUST export tournament results to PDF format with configurable branding
- **FR-036**: System MUST export tournament results to CSV/Excel formats
- **FR-037**: System MUST export tournament results to .TDT format for Tournament Director 3 compatibility
- **FR-038**: System MUST send email notifications with results to all registered players
- **FR-039**: System MUST archive completed tournaments with complete history preservation
- **FR-040**: System MUST prevent modification of archived tournaments while allowing viewing and re-export

#### Phase 3 - Professional Features

- **FR-041**: System MUST provide token system for dynamic content ({{tournament_name}}, {{current_blind}}, {{time_remaining}}, {{chip_leader}}, etc.)
- **FR-042**: System MUST provide drag-and-drop layout builder for custom display screens
- **FR-043**: System MUST support multiple screen templates (Clock, Rankings, Prizes, Seating Chart, Rules)
- **FR-044**: System MUST allow custom CSS styling for display templates
- **FR-045**: System MUST support logo/banner image uploads for branded displays
- **FR-046**: System MUST support multiple screen configurations assignable to different display endpoints
- **FR-047**: System MUST provide event trigger system (level change, break, final table, bubble, etc.)
- **FR-048**: System MUST support sound library with custom sound uploads (MP3, WAV formats)
- **FR-049**: System MUST send email notifications for configurable events
- **FR-050**: System MUST support SMS notifications for configurable events (requires third-party service integration)
- **FR-051**: System MUST maintain event priority queue for simultaneous events
- **FR-052**: System MUST provide chipset designer with denomination configuration (value, color, quantity)
- **FR-053**: System MUST calculate tournament capacity based on chipset and starting stacks
- **FR-054**: System MUST provide chip-up automation when denominations become obsolete
- **FR-055**: System MUST support league management with multiple tournaments
- **FR-056**: System MUST calculate league standings with configurable points formula
- **FR-057**: System MUST support player photos (upload, crop, optimize)
- **FR-058**: System MUST display player photos on seating charts and results
- **FR-059**: System MUST generate PDF receipts for player transactions with venue branding
- **FR-060**: System MUST generate printable name badges with QR codes for player identification
- **FR-061**: System MUST provide rules display editor with token support
- **FR-062**: System MUST include rules templates (Roberts Rules, TDA Tournament Rules)
- **FR-063**: System MUST support multi-language rules display

#### Phase 4 - Premium Features

- **FR-064**: System MUST support multiple simultaneous display endpoints with different views
- **FR-065**: System MUST provide seating chart display with custom table blueprint uploads
- **FR-066**: System MUST display player movement notifications on affected table screens
- **FR-067**: System MUST provide hand timer tracking time per hand dealt
- **FR-068**: System MUST calculate and display hand duration analytics (average, per level)
- **FR-069**: System MUST display slow-play warnings when hand exceeds configured threshold
- **FR-070**: System MUST provide REST API for external control (authentication, clock control, player operations)
- **FR-071**: System MUST support mobile app integration via REST API
- **FR-072**: System MUST provide customizable hotkey configuration for common operations
- **FR-073**: System MUST support screen/keyboard locking for security
- **FR-074**: System MUST import Tournament Director 3 config files (.tdc format)
- **FR-075**: System MUST parse TD3 custom formulas with variables (n, r, avgBC, etc.)
- **FR-076**: System MUST support bounty tournament configurations
- **FR-077**: System MUST calculate advanced rake with progressive structures
- **FR-078**: System MUST track per-player ROI metrics across multiple tournaments
- **FR-079**: System MUST generate profitability analytics (per tournament, monthly, yearly)
- **FR-080**: System MUST generate tax reporting exports with configurable tax year

### Security Requirements (per Constitution)

- **SEC-001**: Input sanitization using WordPress functions (`sanitize_text_field()`, `wp_kses_post()`, `absint()` for IDs, `sanitize_email()` for emails)
- **SEC-002**: AJAX nonce verification via `check_ajax_referer()` for all tournament control actions (clock control, player operations, table management)
- **SEC-003**: Capability checks for admin operations (`current_user_can('manage_options')` or custom capability `manage_tournaments`)
- **SEC-004**: Prepared statements for database operations (`$wpdb->prepare()`) for all tournament data queries
- **SEC-005**: File upload validation for logos/sounds (MIME type checking, file size limits, extension whitelist)
- **SEC-006**: Rate limiting on public registration forms to prevent spam/abuse
- **SEC-007**: REST API authentication using WordPress nonces or JWT tokens
- **SEC-008**: Player data privacy controls (consent for photo display, email opt-in, GDPR compliance)
- **SEC-009**: Transaction log immutability (append-only, no deletion, audit trail preservation)
- **SEC-010**: Session validation for long-running tournament operations (prevent session hijacking)

### Performance Requirements (per Constitution)

- **PERF-001**: Tournament clock synchronization polling interval 3-5 seconds (configurable, default 5s)
- **PERF-002**: Public display pages load in <500ms (cached HTML with dynamic updates via AJAX)
- **PERF-003**: Admin tournament control operations respond in <1 second (bust-out, rebuy, clock control)
- **PERF-004**: Auto-balancing calculations complete in <2 seconds for tournaments up to 500 players
- **PERF-005**: Transient caching for live statistics (15-second TTL), blind structures (60-minute TTL), player lists (30-second TTL)
- **PERF-006**: Background processing for email notifications (wp_schedule_single_event) to avoid blocking user operations
- **PERF-007**: Lazy loading for player photos on displays (load as needed, cache in browser)
- **PERF-008**: Database indexing on tournament_id, player_id, table_id, timestamp columns for fast queries
- **PERF-009**: Memory efficiency: Tournament operations remain under 256MB memory usage for up to 500 players
- **PERF-010**: Export operations (PDF, Excel) use streaming for large datasets (>1000 players) to avoid memory exhaustion

### User Experience Requirements (per Constitution)

- **UX-001**: WordPress admin UI patterns compliance (metaboxes, notices, settings API, list tables for player/tournament management)
- **UX-002**: Clear feedback/loading states for all operations (spinner on clock control, confirmation messages for bust-outs, progress bar for imports)
- **UX-003**: Frontend template compatibility (namespaced CSS classes `.poker-tournament-`, no theme conflicts, responsive design)
- **UX-004**: Accessibility compliance (WCAG 2.1 AA: keyboard navigation, screen reader support, sufficient color contrast 4.5:1, semantic HTML)
- **UX-005**: Public displays fullscreen optimized (no admin chrome, high contrast, readable from distance 10+ feet)
- **UX-006**: Visual countdown indicators (progress bars for blind levels, color changes in final 60 seconds)
- **UX-007**: Responsive design for admin on tablets (tournament control on iPad, registration on mobile)
- **UX-008**: Real-time updates without page refresh (AJAX polling for displays, instant feedback on admin actions)
- **UX-009**: Confirmation dialogs for destructive actions (bust-out, delete tournament, cancel registration)
- **UX-010**: Contextual help tooltips for complex features (table balancing rules, formula variables, rake calculations)

### Key Entities

- **Tournament**: Represents a poker tournament with configuration (buy-in, blinds, prizes), state (running/paused/completed), timestamps (start, end), and relationships to players, tables, transactions
- **Tournament Template**: Saved tournament configuration (blind structure, prize structure, buy-in settings) for reuse
- **Blind Structure**: Ordered sequence of blind levels with small blind, big blind, ante, duration
- **Blind Level**: Single level in structure with specific blind amounts and duration
- **Prize Structure**: Distribution of prize pool across finishing positions (1st place: 50%, 2nd: 30%, etc.)
- **Player**: Individual player with profile data (name, email, phone, photo, statistics) and registration status
- **Tournament Registration**: Links player to specific tournament with entry type (buy-in, rebuy, add-on), payment status, timestamps
- **Table**: Physical table in tournament with capacity (max seats), current player count, status (active/breaking)
- **Table Assignment**: Links player to specific table and seat with timestamps for tracking movements
- **Player State**: Current tournament status for player (active, busted, chips, position) with real-time chip count
- **Transaction**: Immutable log entry for all tournament actions (bust-out, rebuy, add-on, chip adjustment) with timestamp, actor, amount, reason
- **Display Screen**: Configured display layout with template, tokens, styling, assigned to endpoint URL
- **League**: Series of related tournaments with points formula, standings calculation, date range
- **Event Trigger**: Configured event (level change, break, final table) with actions (sound, notification, display message)
- **Chipset**: Denomination configuration with chip values, colors, quantities for venue
- **Tournament Event**: Logged event occurrence (level changed to 400/800, break started at 8:30 PM) with timestamp

## Success Criteria *(mandatory)*

### Measurable Outcomes

#### Phase 1 Success Metrics (Foundation)

- **SC-001**: Tournament organizers can create complete tournament configuration (buy-in, blinds, prizes, players) in under 10 minutes
- **SC-002**: Player registration through frontend form takes under 2 minutes from landing to confirmation
- **SC-003**: Importing 100 players from CSV completes in under 30 seconds with validation report
- **SC-004**: Prize pool calculations automatically adjust within 1 second when player count or buy-in changes
- **SC-005**: System achieves 500+ active installations within 3 months of Phase 1 release
- **SC-006**: System achieves 25+ paid (Starter tier) subscriptions within 3 months
- **SC-007**: System maintains 4.5+ star rating on WordPress.org plugin directory
- **SC-008**: User support tickets related to tournament setup features remain below 5% of user base

#### Phase 2 Success Metrics (Live Operations)

- **SC-009**: Tournament clock remains synchronized across all displays within ±2 seconds
- **SC-010**: Automatic table balancing completes within 5 seconds of player bust-out
- **SC-011**: Tournament directors can process bust-out in under 15 seconds (click player, confirm, done)
- **SC-012**: System successfully runs tournaments with up to 500 players without performance degradation
- **SC-013**: Zero data loss incidents during live tournament operations (<1% target)
- **SC-014**: Export to PDF completes in under 10 seconds for tournaments up to 200 players
- **SC-015**: Email delivery to all players completes within 5 minutes of tournament end
- **SC-016**: System achieves 2,000+ active installations within 6 months of Phase 2 release
- **SC-017**: System achieves 100+ paid (Professional tier) subscriptions within 6 months
- **SC-018**: 80% of users transitioning from free tier to Phase 2 report time savings of 60+ minutes per tournament

#### Phase 3 Success Metrics (Professional Features)

- **SC-019**: Venue operators can create custom branded display in under 20 minutes using layout builder
- **SC-020**: Display templates remain responsive and readable on screens from 24" to 85" diagonal
- **SC-021**: Event notifications (email/SMS) deliver within 30 seconds of trigger
- **SC-022**: League standings calculations update within 10 seconds of tournament completion
- **SC-023**: Player photo uploads and optimization complete in under 5 seconds
- **SC-024**: Receipt generation completes in under 3 seconds per player
- **SC-025**: System achieves 5,000+ active installations within 12 months of Phase 3 release
- **SC-026**: System achieves 300+ paid subscriptions (Professional + Enterprise) within 12 months
- **SC-027**: System achieves 10+ enterprise tier subscriptions within 12 months
- **SC-028**: Venue operators report 40% reduction in player complaints about display visibility/branding

#### Phase 4 Success Metrics (Premium Features)

- **SC-029**: REST API handles 100 requests per minute with <200ms response time
- **SC-030**: Mobile app control via API enables full tournament management with 100% feature parity
- **SC-031**: TD3 config file imports complete in under 60 seconds with 95%+ feature compatibility
- **SC-032**: Hand timer tracks 1000+ hands per tournament with <1% timing errors
- **SC-033**: Profitability reports generate in under 15 seconds for 20-tournament analysis
- **SC-034**: Multi-monitor setups support 4+ simultaneous displays with synchronized state
- **SC-035**: System achieves 10,000+ active installations within 18 months of Phase 4 release
- **SC-036**: System achieves 750+ paid subscriptions across all tiers within 18 months
- **SC-037**: Monthly recurring revenue reaches $65,000 within 18 months
- **SC-038**: Enterprise customers report TD3 feature parity at 90%+ with improved web-based advantages
- **SC-039**: Customer churn rate remains below 10% annually
- **SC-040**: Net Promoter Score (NPS) reaches 50+ indicating strong customer advocacy

### Business Success Criteria

- **SC-041**: Revenue progression follows projections (Month 3: $1.2K MRR, Month 6: $7.5K MRR, Month 12: $25K MRR, Month 18: $65K MRR)
- **SC-042**: Free-to-paid conversion rate reaches 5% for Starter tier within 6 months
- **SC-043**: Starter-to-Professional upgrade rate reaches 30% within 12 months
- **SC-044**: Customer lifetime value (LTV) exceeds $400 per paying customer
- **SC-045**: Support cost per customer remains under $50 annually
- **SC-046**: Product development cost recovered within 18 months of Phase 1 launch

## Assumptions

- WordPress Heartbeat API provides sufficient real-time capabilities for clock synchronization (no WebSocket requirement)
- Tournament organizers have basic WordPress admin knowledge (can install plugins, create pages)
- Venues have reliable internet connectivity during tournaments (4G/5G backup recommended but not required for basic operations)
- Players have email addresses for registration and notifications (SMS is optional premium feature)
- Tournaments follow standard poker formats (Texas Hold'em primarily, other variants use same blind structure model)
- Maximum tournament size is 500 players for table management calculations (larger events would segment into flights)
- Display screens support modern web browsers with JavaScript enabled
- React 18 can be integrated into WordPress admin without conflicts (isolated app mounting)
- Tournament Director 3 .tdc file format is parseable (JSON-like structure documented by TD3)
- Payment processing for buy-ins/rebuys happens outside the system (cash/external POS, system tracks amounts only)
- Tax reporting requirements vary by jurisdiction (US 1099 format as default, customizable)
- Photo storage uses WordPress media library with image optimization
- Email deliverability assumes properly configured WordPress mail (SMTP plugin recommended)
- Mobile app would be separate native/hybrid application consuming REST API (not included in core plugin)

## Notes

This is a comprehensive multi-phase feature specification covering 18 months of development. Each phase builds on the previous and represents an independently valuable product increment:

- **Phase 1 (12 weeks)**: Tournament planning and setup tool - targets organizers who want to prepare tournaments digitally
- **Phase 2 (10 weeks)**: Live tournament operations - transforms plugin into operational system replacing TD3 for live events
- **Phase 3 (12 weeks)**: Professional venue features - adds branding, customization, and league management for serious operators
- **Phase 4 (10 weeks)**: Enterprise and API features - achieves TD3 feature parity plus web-based advantages

This specification intentionally avoids implementation details (database schema, API endpoints, React component structure) focusing on WHAT users need and WHY. The `/speckit.plan` command will translate this into technical implementation planning.

**Pricing tiers map to feature phases**:
- Free: Current .tdt import (unchanged)
- Starter ($49/year): Phase 1 features, 100-player limit
- Professional ($99/year): Phases 1+2+3, 500-player limit
- Enterprise ($199/year): All phases, unlimited players, priority support

Development should proceed phase by phase with each phase being production-ready and monetizable before starting the next.
