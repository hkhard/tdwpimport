# Phase 1: Tournament Management Foundation (MUST HAVE)

## Executive Summary
Core tournament setup and management features that transform the plugin from import-only to full tournament creation and management. Enables users to create tournaments from scratch without requiring Tournament Director software.

**Timeline:** 12 weeks
**Tier:** Starter ($49/year)
**Priority:** MUST HAVE

---

## Features

### 1. Tournament Setup & Configuration

#### 1.1 Financial Configuration
**User Story:** As a tournament organizer, I need to configure buy-ins, fees, rebuys, and add-ons so that I can track tournament financials accurately.

**Requirements:**
- Buy-in amount configuration (separate entry fee and prize pool)
- Rebuy settings:
  - Allowed (yes/no)
  - Maximum count (unlimited or specific number)
  - Timing restrictions (first X rounds, below Y chips, etc.)
  - Cost per rebuy
- Add-on settings:
  - Allowed (yes/no)
  - Timing (specific round/break)
  - Cost
  - Chips awarded
- Rake calculation (percentage or flat fee)
- Estimated pot calculation in real-time
- Financial summary display

**Acceptance Criteria:**
- [ ] Admin can configure buy-in with separate fee structure
- [ ] Rebuy rules can be customized with restrictions
- [ ] Add-on configuration saves and loads correctly
- [ ] Pot calculation updates automatically based on registered players
- [ ] Financial summary displays: Total pot, Rake, Net prize pool

#### 1.2 Tournament Templates/Profiles
**User Story:** As a tournament organizer, I want to save tournament configurations as templates so I can quickly set up recurring tournaments.

**Requirements:**
- Save complete tournament configuration as named template
- Load template to populate new tournament
- Template includes: buy-in structure, rebuy/add-on rules, financial settings
- Template management: edit, delete, duplicate
- Built-in templates: "Standard Freezeout", "Deep Stack", "Turbo Rebuy"

**Acceptance Criteria:**
- [ ] Templates save all tournament settings
- [ ] Loading template populates all fields correctly
- [ ] Templates can be edited without affecting existing tournaments
- [ ] 3+ built-in templates provided out-of-box
- [ ] Template library accessible from tournament creation screen

#### 1.3 Auto-save & Tournament History
**User Story:** As a tournament organizer, I need tournaments to auto-save so I don't lose work if browser crashes.

**Requirements:**
- Auto-save every 30 seconds when tournament tab active
- Save indicator shows last save time
- Tournament history log (created, modified, started, completed dates)
- Draft tournaments saved separately from active/completed
- Restore from auto-save on browser/server crash

**Acceptance Criteria:**
- [ ] Changes auto-save within 30 seconds
- [ ] Visual indicator shows "Saved" or "Saving..."
- [ ] Draft tournaments appear in "Drafts" section
- [ ] Can restore unsaved changes after crash
- [ ] History log accessible from tournament details

---

### 2. Blind Structure Management

#### 2.1 Blind Schedule Builder
**User Story:** As a tournament organizer, I need to create custom blind schedules so tournaments progress at the right pace.

**Requirements:**
- Level editor with fields:
  - Small blind amount
  - Big blind amount
  - Ante amount (optional)
  - Duration (minutes)
  - Break flag (yes/no)
- Add new level at any position
- Edit existing levels
- Delete levels
- Reorder levels (drag-drop)
- Visual timeline preview showing full schedule
- Total tournament duration calculation
- Average stack calculation by level

**Acceptance Criteria:**
- [ ] Can add/edit/delete blind levels
- [ ] Levels can be reordered via drag-drop
- [ ] Timeline shows all levels with durations
- [ ] Total time calculates correctly including breaks
- [ ] Break levels clearly marked visually
- [ ] Ante field optional (defaults to 0)

#### 2.2 Schedule Templates
**User Story:** As a tournament organizer, I want pre-built blind schedules so I don't have to create from scratch.

**Requirements:**
- Template library with:
  - Turbo (15-min levels)
  - Standard (20-min levels)
  - Deep Stack (30-min levels)
  - Hyper Turbo (5-min levels)
  - Custom (user-created)
- Load template into tournament
- Save current schedule as template
- Edit templates without affecting tournaments
- Export schedule to PDF/printable format
- Import schedule from CSV

**Acceptance Criteria:**
- [ ] 4+ built-in schedule templates
- [ ] Templates load correctly into new tournaments
- [ ] Custom templates can be created and saved
- [ ] Templates editable in template library
- [ ] Schedule exports to clean PDF format
- [ ] CSV import validates blind structure

#### 2.3 Schedule Suggestions
**User Story:** As a tournament organizer, I want the system to suggest blind schedules based on tournament parameters.

**Requirements:**
- Suggest schedule based on:
  - Starting chips
  - Desired tournament duration
  - Number of players
  - Tournament style (turbo, standard, deep)
- Algorithm calculates appropriate level durations
- Blinds double approximately every 4-6 levels
- Suggests break placements
- User can accept, modify, or reject suggestion

**Acceptance Criteria:**
- [ ] Suggestion algorithm produces playable schedules
- [ ] Suggested duration ±15 minutes of target
- [ ] Blinds progression is reasonable (no huge jumps)
- [ ] Breaks suggested every 60-90 minutes
- [ ] User can modify suggestion before accepting

---

### 3. Player Registration System

#### 3.1 Player Database Integration
**User Story:** As a tournament organizer, I need to manage a player database so I can track player history across tournaments.

**Requirements:**
- Player records with fields:
  - Name (required)
  - Email (optional)
  - Phone (optional)
  - Notes
  - Photo/avatar
- Add new players to database
- Search players by name/email
- Merge duplicate player records
- Player appears in autocomplete when registering
- Link tournament participation to database player

**Acceptance Criteria:**
- [ ] Players can be added to database from any screen
- [ ] Search finds players by partial name match
- [ ] Duplicate detection suggests merges
- [ ] Player autocomplete works in registration forms
- [ ] Player history shows all past tournaments

#### 3.2 Tournament Registration
**User Story:** As a tournament organizer, I need to register players for tournaments before they start.

**Requirements:**
- Admin registration interface:
  - Add player by name (autocomplete from database)
  - Add new player (creates database entry)
  - Set buy-in status (paid/unpaid)
  - Add multiple players at once
  - Remove registered players (before tournament starts)
- Frontend registration form (shortcode):
  - Self-registration with name/email
  - Displays available seats
  - Confirmation message/email
  - Waiting list when full
- Registration limits based on table configuration
- Late registration period configuration

**Acceptance Criteria:**
- [ ] Admin can add players from existing database
- [ ] Admin can create new players during registration
- [ ] Frontend shortcode displays registration form
- [ ] Registration limits enforced
- [ ] Confirmation email sent on registration
- [ ] Waiting list manages overflow registrations

#### 3.3 Import/Export Players
**User Story:** As a tournament organizer, I want to import/export player lists so I can use external tools or share rosters.

**Requirements:**
- Import formats: CSV, Excel (.xlsx)
- Export formats: CSV, Excel, PDF
- CSV format: Name, Email, Phone, Buy-in Status, Seat Number
- Import validation with error reporting
- Bulk import from previous tournament
- Export includes custom fields if configured

**Acceptance Criteria:**
- [ ] CSV import handles standard format
- [ ] Excel import works with .xlsx files
- [ ] Import errors reported with line numbers
- [ ] Export includes all player data
- [ ] Can copy player list from previous tournament
- [ ] PDF export formatted for printing

---

### 4. Prize Structure Calculator

#### 4.1 Prize Creation & Management
**User Story:** As a tournament organizer, I need to define prize structures so players know payouts.

**Requirements:**
- Prize editor with fields:
  - Place (1st, 2nd, 3rd, etc.)
  - Amount (dollar value or percentage)
  - Recipient (can override to specific player)
  - Lock (prevents auto-recalculation)
  - Display flag (show on screens)
- Add/edit/delete prizes
- Automatic recalculation when pot changes
- Rounding options (nearest $1, $5, $10)
- Sort prizes by place or amount
- Clear all prizes

**Acceptance Criteria:**
- [ ] Prizes can be added with $ or % values
- [ ] Locked prizes don't recalculate
- [ ] Rounding applies consistently
- [ ] Prize total matches available pot (or shows warning)
- [ ] Can hide specific prizes from public display

#### 4.2 Prize Suggestions
**User Story:** As a tournament organizer, I want suggested prize structures so I don't have to calculate distributions manually.

**Requirements:**
- Suggestion algorithms:
  - Standard (50/30/20 for 3 places)
  - Top-heavy (65/25/10)
  - Flat (more even distribution)
  - Custom percentage-based
- Suggest number of places based on player count
  - 1-20 players: 3 places
  - 21-50 players: 5 places
  - 51-100 players: 9 places
  - 100+ players: 15% of field
- Apply suggestion with one click
- Suggestions respect minimum payout rules

**Acceptance Criteria:**
- [ ] 3+ prize suggestion algorithms available
- [ ] Suggested place count scales with players
- [ ] Suggestions calculate to exact pot amount
- [ ] Can modify suggestion before applying
- [ ] Minimum payout configurable (e.g., $50)

#### 4.3 Prize Templates
**User Story:** As a tournament organizer, I want to save prize structures as templates for reuse.

**Requirements:**
- Save prize structure as named template
- Template stores percentage-based prizes
- Load template and apply to current pot
- Built-in templates:
  - "Standard 3-way" (50/30/20)
  - "Standard 5-way" (40/25/18/11/6)
  - "Flat 9-way"
  - "Top Heavy"
- Edit templates without affecting tournaments
- Delete custom templates

**Acceptance Criteria:**
- [ ] Templates save percentage-based structures
- [ ] Loading template recalculates for current pot
- [ ] 4+ built-in templates provided
- [ ] Custom templates can be created/edited
- [ ] Template changes don't affect existing tournaments

#### 4.4 Chop Management
**User Story:** As a tournament organizer, I need to handle chops (deal-making) when final players agree to split prizes.

**Requirements:**
- Chop interface appears when 2+ players remain
- Chop options:
  - Even split (divide remaining prizes equally)
  - ICM (Independent Chip Model) calculation
  - Custom amounts per player
- Lock prize structure after chop agreed
- Record chop in tournament history
- Display "Chopped" status on prizes

**Acceptance Criteria:**
- [ ] Chop interface accessible from prizes tab
- [ ] Even split divides prizes correctly
- [ ] ICM calculation based on chip stacks
- [ ] Custom chop amounts validated to equal pot
- [ ] Chopped prizes marked distinctly
- [ ] Chop recorded in tournament log

---

## Technical Specifications

### Database Schema

```sql
-- Tournament Templates
CREATE TABLE wp_poker_tournament_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    config_json LONGTEXT NOT NULL,
    created_by BIGINT UNSIGNED,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_created_by (created_by)
);

-- Blind Schedules
CREATE TABLE wp_poker_blind_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id BIGINT UNSIGNED NOT NULL,
    level_number INT NOT NULL,
    small_blind INT NOT NULL,
    big_blind INT NOT NULL,
    ante INT DEFAULT 0,
    duration_minutes INT NOT NULL,
    is_break BOOLEAN DEFAULT FALSE,
    break_duration_minutes INT DEFAULT 0,
    INDEX idx_tournament (tournament_id),
    INDEX idx_level (tournament_id, level_number)
);

-- Prize Structures
CREATE TABLE wp_poker_prize_structures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id BIGINT UNSIGNED NOT NULL,
    place INT NOT NULL,
    amount DECIMAL(10,2),
    percentage DECIMAL(5,2),
    recipient_player_id BIGINT UNSIGNED,
    locked BOOLEAN DEFAULT FALSE,
    display BOOLEAN DEFAULT TRUE,
    INDEX idx_tournament (tournament_id),
    INDEX idx_place (tournament_id, place)
);

-- Extend existing poker_tournament_players table
ALTER TABLE wp_poker_tournament_players ADD COLUMN status VARCHAR(20) DEFAULT 'registered';
ALTER TABLE wp_poker_tournament_players ADD COLUMN buy_in_amount DECIMAL(10,2);
ALTER TABLE wp_poker_tournament_players ADD COLUMN buy_in_paid BOOLEAN DEFAULT FALSE;
ALTER TABLE wp_poker_tournament_players ADD COLUMN rebuy_count INT DEFAULT 0;
ALTER TABLE wp_poker_tournament_players ADD COLUMN addon_count INT DEFAULT 0;
ALTER TABLE wp_poker_tournament_players ADD COLUMN registered_at DATETIME;
```

### REST API Endpoints

```
POST   /wp-json/poker-tournament/v1/tournaments          Create tournament
GET    /wp-json/poker-tournament/v1/tournaments/:id      Get tournament
PATCH  /wp-json/poker-tournament/v1/tournaments/:id      Update tournament
DELETE /wp-json/poker-tournament/v1/tournaments/:id      Delete tournament

GET    /wp-json/poker-tournament/v1/templates            List templates
POST   /wp-json/poker-tournament/v1/templates            Create template
GET    /wp-json/poker-tournament/v1/templates/:id        Get template
DELETE /wp-json/poker-tournament/v1/templates/:id        Delete template

POST   /wp-json/poker-tournament/v1/tournaments/:id/players      Register player
DELETE /wp-json/poker-tournament/v1/tournaments/:id/players/:pid  Unregister player
POST   /wp-json/poker-tournament/v1/tournaments/:id/import       Import players

GET    /wp-json/poker-tournament/v1/tournaments/:id/blinds       Get blind schedule
POST   /wp-json/poker-tournament/v1/tournaments/:id/blinds       Update blinds
POST   /wp-json/poker-tournament/v1/tournaments/:id/blinds/suggest  Suggest schedule

GET    /wp-json/poker-tournament/v1/tournaments/:id/prizes       Get prizes
POST   /wp-json/poker-tournament/v1/tournaments/:id/prizes       Update prizes
POST   /wp-json/poker-tournament/v1/tournaments/:id/prizes/suggest  Suggest prizes
POST   /wp-json/poker-tournament/v1/tournaments/:id/prizes/chop   Record chop
```

---

## Dependencies & Prerequisites

- WordPress 6.0+
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.2+
- Existing Poker Tournament Import plugin v2.8+

---

## Success Metrics

- 500+ active installs within 3 months
- 25+ paid conversions to Starter tier
- 4.5+ star average rating
- <5% bug report rate
- 80%+ template usage (users use templates vs manual setup)

---

## Out of Scope (Future Phases)

- Live tournament control (Phase 2)
- Table management (Phase 2)
- Custom display layouts (Phase 3)
- Mobile app (Phase 4)
