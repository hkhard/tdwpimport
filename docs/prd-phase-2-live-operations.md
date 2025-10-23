# Phase 2: Live Tournament Operations (NEED TO HAVE)

## Executive Summary
Real-time tournament management features enabling venues to run live tournaments through WordPress. Includes tournament clock, table management, player operations, and automated balancing.

**Timeline:** 10 weeks
**Tier:** Professional ($99/year)
**Priority:** NEED TO HAVE
**Depends On:** Phase 1 Complete

---

## Features

### 1. Tournament Clock & Control

#### 1.1 Tournament Clock Interface
**User Story:** As a tournament director, I need a tournament clock to manage round timing and breaks.

**Requirements:**
- Clock display showing:
  - Current level (SB/BB/Ante)
  - Time remaining in current level
  - Next level preview
  - Players remaining
  - Average chip stack
  - Current pot size
- Clock controls:
  - Start tournament
  - Pause/Resume
  - Skip to next level
  - Skip to specific level
  - Add time to current level
  - End tournament
- Break management:
  - Automatic break start at configured levels
  - Break countdown timer
  - "Players will be back at HH:MM" display
- Sound notifications:
  - 5 minutes remaining in level
  - 1 minute remaining
  - Level change
  - Break start/end

**Acceptance Criteria:**
- [ ] Clock displays all required information accurately
- [ ] Start/pause/resume work without data loss
- [ ] Skip level advances immediately
- [ ] Add time extends current level correctly
- [ ] Break timer counts down accurately
- [ ] Sound notifications play at correct times
- [ ] Clock survives page refresh (state persisted)

#### 1.2 Real-time Clock Synchronization
**User Story:** As a tournament director, I need all screens to show synchronized time.

**Requirements:**
- WordPress Heartbeat API integration
- Clock state synced every 15 seconds
- Optimistic UI updates (no lag in display)
- Server-side time authority (prevents client-side manipulation)
- Multi-tab synchronization
- Connection lost warning
- Automatic reconnection with state recovery

**Acceptance Criteria:**
- [ ] Multiple browser tabs show same time (±1 second)
- [ ] Clock continues if one tab closed
- [ ] Lost connection shows warning overlay
- [ ] Reconnection restores accurate state
- [ ] No clock drift over 4+ hour tournament

#### 1.3 Public Display Mode
**User Story:** As a venue, I need a full-screen display for players to view tournament info.

**Requirements:**
- Full-screen mode (F11 or button)
- Clean interface (no admin controls)
- Customizable display elements:
  - Clock (required)
  - Blind schedule
  - Prize structure
  - Player rankings
  - Venue logo/branding
- Automatic screen wake (prevent sleep)
- URL parameter for screen selection: ?screen=clock
- Responsive design for various display sizes
- Optional dark mode

**Acceptance Criteria:**
- [ ] Full-screen mode hides browser chrome
- [ ] Display updates in real-time
- [ ] Screen doesn't sleep during tournament
- [ ] Multiple screen types available via URL
- [ ] Branding configurable per tournament
- [ ] Readable from 20+ feet away

---

### 2. Table Management System

#### 2.1 Table Creation & Configuration
**User Story:** As a tournament director, I need to create and manage tables for the tournament.

**Requirements:**
- Table properties:
  - Table number/name
  - Seat count (6, 8, 9, or 10)
  - Status (active, breaking, broken)
  - Notes
- Quick table creation:
  - "Add 5 tables" button
  - Default to 9 seats per table
  - Sequential numbering
- Table editing:
  - Change seat count (warns if players seated)
  - Rename table
  - Change status
- Table deletion:
  - Prevents deletion if players seated
  - Option to unseat all players first
- Table capacity calculation based on registrations

**Acceptance Criteria:**
- [ ] Tables created with specified seat counts
- [ ] Bulk create generates correct number
- [ ] Editing validates against current seating
- [ ] Deletion prevented when players seated
- [ ] Total capacity matches table configuration

#### 2.2 Automatic Seating & Balancing
**User Story:** As a tournament director, I want automatic seating so tables stay balanced.

**Requirements:**
- Initial seating:
  - Random assignment to tables
  - Balanced distribution (±1 player per table)
  - Dealer button randomly assigned
- Automatic balancing triggers:
  - Player bust-out creates imbalance
  - Manual trigger button
  - Option for auto-balance on every bust
- Balancing algorithm:
  - Move minimum number of players
  - Never leave table with less than 4 players
  - Maintain dealer button positions
  - Log all movements
- Table breaking:
  - Automatically break smallest table when needed
  - Distribute players to remaining tables
  - Mark table as "broken"
  - Option to lock specific tables from breaking

**Acceptance Criteria:**
- [ ] Initial seating distributes evenly
- [ ] Balancing maintains ±1 player variance
- [ ] Minimum players per table enforced
- [ ] Dealer buttons remain valid after moves
- [ ] Table breaking distributes optimally
- [ ] Movement log tracks all changes

#### 2.3 Manual Seating Management
**User Story:** As a tournament director, I need manual control for special seating situations.

**Requirements:**
- Drag-and-drop player movement
- Seat locking (prevents auto-move)
- Make seat unavailable (broken chairs, etc.)
- Swap two players
- Move entire table to different number
- Undo last move operation
- Seat assignment overrides:
  - VIP table assignments
  - Request-based seating
  - Separation requirements
- Visual table diagram:
  - Shows all players
  - Indicates dealer button
  - Shows locked players
  - Highlights empty seats

**Acceptance Criteria:**
- [ ] Players can be moved via drag-drop
- [ ] Locked players stay put during auto-balance
- [ ] Unavailable seats excluded from capacity
- [ ] Swap operation validates seat availability
- [ ] Undo restores previous state
- [ ] Visual diagram updates in real-time
- [ ] Manual moves logged in history

---

### 3. Live Player Operations

#### 3.1 Player Bust-Out Processing
**User Story:** As a tournament director, I need to record player eliminations and assign finish positions.

**Requirements:**
- Bust-out interface:
  - Select player from table
  - Automatic rank assignment
  - Record eliminating player (optional)
  - Prize amount auto-filled if in money
  - Bust-out time stamped
- Bulk bust-out:
  - Multiple players bust simultaneously
  - Tie resolution (same place, split prize)
- Bubble management:
  - Highlight bubble player
  - Special handling for bubble bust
- Undo bust-out:
  - Restore player to active status
  - Adjust rankings below
  - Recalculate prizes if needed
- Notifications:
  - Announce place on displays
  - Optional receipt printing
  - Email summary to player

**Acceptance Criteria:**
- [ ] Bust-outs assign correct sequential rank
- [ ] Simultaneous busts handled correctly
- [ ] Bubble player identified automatically
- [ ] Undo restores state accurately
- [ ] Prize amounts display for ITM finishes
- [ ] Bust-out logged with timestamp

#### 3.2 Rebuy & Add-On Processing
**User Story:** As a tournament director, I need to process rebuys and add-ons during the tournament.

**Requirements:**
- Rebuy interface:
  - Select player
  - Validate rebuy eligibility (rules check)
  - Record payment
  - Update player chip count
  - Increment rebuy counter
  - Update tournament pot
- Rebuy rules enforcement:
  - Check if rebuy period open
  - Verify maximum rebuy limit
  - Chip count threshold check
  - Display eligibility status
- Add-on interface:
  - Available only during add-on period
  - One per player enforcement
  - Bulk add-on processing
  - Payment tracking
- Rebuy/add-on tracking:
  - Per-player count
  - Total rebuys/add-ons
  - Revenue tracking
  - Impact on prize pool

**Acceptance Criteria:**
- [ ] Rebuy rules enforced automatically
- [ ] Ineligible players cannot rebuy
- [ ] Add-ons limited to one per player
- [ ] Pot updates with each transaction
- [ ] Bulk add-on processes entire table
- [ ] Transactions logged with details

#### 3.3 Late Registration
**User Story:** As a tournament director, I need to allow late entries during the late registration period.

**Requirements:**
- Late registration period configuration:
  - End of level X
  - Specific time (minutes after start)
  - Manual close option
- Late entry process:
  - Add player after tournament started
  - Assign starting chips (or current average)
  - Automatic seating
  - Table balancing triggered
  - Buy-in added to pot
- Late entry display:
  - Show late registration deadline on screens
  - Countdown to registration close
  - Available seats remaining
- Late entry restrictions:
  - No late entry after deadline
  - Cannot register once closed
  - Option to reopen (TD override)

**Acceptance Criteria:**
- [ ] Late registration open until configured time
- [ ] Late entries receive correct chips
- [ ] Seating happens automatically
- [ ] Tables rebalance with new player
- [ ] Deadline displays on public screens
- [ ] Registration cannot reopen after manual close

#### 3.4 Chip Count Management
**User Story:** As a tournament director, I need to adjust chip counts for corrections or penalties.

**Requirements:**
- Chip count editor:
  - Select player
  - Enter new chip count
  - Reason for adjustment (required)
  - Logs old and new counts
- Adjustment types:
  - Penalty (subtract chips)
  - Correction (add/subtract)
  - Reset to average
  - Set specific amount
- Validation:
  - Cannot go negative
  - Warn on large changes
  - Require confirmation
- Adjustment history:
  - All adjustments logged
  - Filterable by player/type
  - Export to CSV

**Acceptance Criteria:**
- [ ] Chip counts editable per player
- [ ] Reason required for all adjustments
- [ ] Negative counts prevented
- [ ] Large adjustments require confirmation
- [ ] Complete audit trail maintained
- [ ] History exportable

---

### 4. Statistics & Reporting

#### 4.1 Live Tournament Statistics
**User Story:** As a tournament director, I want to see real-time tournament statistics.

**Requirements:**
- Statistics dashboard:
  - Players remaining
  - Average chip stack
  - Total chips in play
  - Largest/smallest stack
  - Total pot (with rebuys/add-ons)
  - Players eliminated per level
  - Rebuys/add-ons count and revenue
  - Tournament duration (elapsed)
  - Estimated time to completion
- Per-player statistics:
  - Current chips
  - Starting chips
  - Peak chips
  - Rebuys taken
  - Add-ons taken
  - Table assignments
- Table statistics:
  - Players per table
  - Average chips per table
  - Short stack at table
  - Big stack at table

**Acceptance Criteria:**
- [ ] All statistics calculate accurately
- [ ] Real-time updates (no page refresh needed)
- [ ] Player stats accessible from multiple screens
- [ ] Table stats show on table management
- [ ] Estimated completion within 30 minutes

#### 4.2 Tournament Summary & Export
**User Story:** As a tournament director, I need to export final results for records and reporting.

**Requirements:**
- Summary includes:
  - Tournament details (name, date, buy-in)
  - Player list with finish positions
  - Prize payouts
  - Rebuy/add-on summary
  - Financial summary (pot, rake, payouts)
  - Duration and key events
  - Blind structure used
- Export formats:
  - PDF (formatted report)
  - CSV (data only)
  - Excel (.xlsx)
  - JSON (API integration)
  - .TDT (Tournament Director format)
- Email results:
  - Send to all players
  - Send to specific addresses
  - Include receipts for ITM
- Tournament archive:
  - Mark tournament as complete
  - Move to archived list
  - Read-only mode
  - Restore if needed

**Acceptance Criteria:**
- [ ] Summary includes all required data
- [ ] PDF export formatted professionally
- [ ] CSV export imports into Excel cleanly
- [ ] .TDT export compatible with TD3
- [ ] Email delivery tracked
- [ ] Archived tournaments preserved accurately

---

## Technical Specifications

### Database Schema

```sql
-- Live Tournament State
CREATE TABLE wp_poker_tournament_live_state (
    tournament_id BIGINT UNSIGNED PRIMARY KEY,
    status ENUM('scheduled','registration','active','paused','break','completed') NOT NULL,
    current_level INT DEFAULT 1,
    level_start_time DATETIME,
    time_remaining_seconds INT,
    break_end_time DATETIME,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
);

-- Tables
CREATE TABLE wp_poker_tables (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id BIGINT UNSIGNED NOT NULL,
    table_number INT NOT NULL,
    seat_count INT NOT NULL DEFAULT 9,
    status ENUM('active','breaking','broken') DEFAULT 'active',
    notes TEXT,
    UNIQUE KEY idx_tournament_number (tournament_id, table_number),
    INDEX idx_tournament (tournament_id)
);

-- Table Assignments
CREATE TABLE wp_poker_table_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id BIGINT UNSIGNED NOT NULL,
    table_id BIGINT UNSIGNED NOT NULL,
    seat_number INT NOT NULL,
    player_id BIGINT UNSIGNED,
    dealer_button BOOLEAN DEFAULT FALSE,
    locked BOOLEAN DEFAULT FALSE,
    seat_available BOOLEAN DEFAULT TRUE,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_seat (table_id, seat_number),
    INDEX idx_tournament (tournament_id),
    INDEX idx_player (player_id)
);

-- Player State
CREATE TABLE wp_poker_player_state (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    chips INT NOT NULL,
    status ENUM('active','busted','late_reg','waiting') DEFAULT 'active',
    finish_place INT,
    bust_time DATETIME,
    UNIQUE KEY idx_tournament_player (tournament_id, player_id),
    INDEX idx_tournament (tournament_id),
    INDEX idx_status (tournament_id, status)
);

-- Transactions
CREATE TABLE wp_poker_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    transaction_type ENUM('buy_in','rebuy','addon','chip_adjustment','prize') NOT NULL,
    amount DECIMAL(10,2),
    chips INT,
    reason TEXT,
    created_by BIGINT UNSIGNED,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tournament (tournament_id),
    INDEX idx_player (player_id),
    INDEX idx_type (transaction_type)
);

-- Player Movements
CREATE TABLE wp_poker_player_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    from_table_id BIGINT UNSIGNED,
    from_seat INT,
    to_table_id BIGINT UNSIGNED,
    to_seat INT,
    move_reason ENUM('balance','break_table','manual','initial') NOT NULL,
    moved_by BIGINT UNSIGNED,
    moved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tournament (tournament_id),
    INDEX idx_player (player_id)
);

-- Event Log
CREATE TABLE wp_poker_tournament_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_data JSON,
    created_by BIGINT UNSIGNED,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tournament_time (tournament_id, created_at),
    INDEX idx_type (event_type)
);
```

### REST API Endpoints

```
POST   /wp-json/poker-tournament/v1/tournaments/:id/start        Start tournament
POST   /wp-json/poker-tournament/v1/tournaments/:id/pause        Pause tournament
POST   /wp-json/poker-tournament/v1/tournaments/:id/resume       Resume tournament
POST   /wp-json/poker-tournament/v1/tournaments/:id/next-level   Advance level
POST   /wp-json/poker-tournament/v1/tournaments/:id/end          End tournament
GET    /wp-json/poker-tournament/v1/tournaments/:id/clock        Get clock state

POST   /wp-json/poker-tournament/v1/tournaments/:id/tables       Create table
GET    /wp-json/poker-tournament/v1/tournaments/:id/tables       List tables
PATCH  /wp-json/poker-tournament/v1/tournaments/:id/tables/:tid  Update table
DELETE /wp-json/poker-tournament/v1/tournaments/:id/tables/:tid  Delete table

POST   /wp-json/poker-tournament/v1/tournaments/:id/seat         Seat players
POST   /wp-json/poker-tournament/v1/tournaments/:id/balance      Balance tables
POST   /wp-json/poker-tournament/v1/tournaments/:id/move         Move player
POST   /wp-json/poker-tournament/v1/tournaments/:id/swap         Swap players

POST   /wp-json/poker-tournament/v1/tournaments/:id/bustout      Record bust-out
POST   /wp-json/poker-tournament/v1/tournaments/:id/rebuy        Process rebuy
POST   /wp-json/poker-tournament/v1/tournaments/:id/addon        Process add-on
PATCH  /wp-json/poker-tournament/v1/tournaments/:id/chips        Adjust chips

GET    /wp-json/poker-tournament/v1/tournaments/:id/stats        Get statistics
GET    /wp-json/poker-tournament/v1/tournaments/:id/export       Export results
POST   /wp-json/poker-tournament/v1/tournaments/:id/email        Email results
```

### WordPress Heartbeat Integration

```javascript
jQuery(document).on('heartbeat-send', function(e, data) {
    data['poker_tournament_clock'] = {
        tournament_id: poker_tournament.id,
        action: 'sync_clock'
    };
});

jQuery(document).on('heartbeat-tick', function(e, data) {
    if (data['poker_tournament_clock']) {
        updateClockDisplay(data['poker_tournament_clock']);
    }
});
```

---

## Success Metrics

- 100+ Professional tier conversions
- <1% data loss incidents
- 95%+ accuracy in table balancing
- 50+ concurrent live tournaments weekly
- <2 second response time for player operations

---

## Out of Scope (Future Phases)

- Custom display layouts (Phase 3)
- Advanced chip management (Phase 3)
- Mobile app control (Phase 4)
- Multi-monitor support (Phase 4)
