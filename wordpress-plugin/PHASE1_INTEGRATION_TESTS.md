# Phase 1 Integration Test Plan
## Tournament Manager Foundation - Complete Feature Testing

**Version:** 3.0.0
**Test Date:** 2025-01-27
**Status:** Ready for Testing

---

## 1. Component Overview

### Implemented Features (Weeks 1-5)

#### Week 1: Database Schema
- ✅ `wp_poker_tournament_templates` table
- ✅ `wp_poker_blind_schedules` table
- ✅ `wp_poker_blind_levels` table
- ✅ `wp_poker_prize_structures` table
- ✅ Default data seeding (templates, blind schedules, prize structures)

#### Week 2: Tournament Templates
- ✅ Template CRUD operations
- ✅ Template list/add/edit UI
- ✅ Financial settings (buy-in, rake, rebuys, add-ons)
- ✅ Template cloning
- ✅ Admin menu: Tournament Manager → Templates

#### Week 3: Blind Builder
- ✅ Blind schedule CRUD
- ✅ Blind level management (SB/BB/ante/duration)
- ✅ Default templates (Turbo, Standard, Deep Stack)
- ✅ PDF export functionality
- ✅ Admin menu: Tournament Manager → Blind Builder

#### Week 4: Prize Calculator
- ✅ Prize structure CRUD
- ✅ Prize distribution templates (50/30/20, etc.)
- ✅ Chop management
- ✅ Automatic recalculation
- ✅ Admin menu: Tournament Manager → Prize Calculator

#### Week 5: Player Registration
- ✅ Player Manager (WordPress posts CRUD)
- ✅ Player Management UI (list/add/edit/import)
- ✅ CSV import with duplicate handling
- ✅ Frontend registration form shortcode
- ✅ Honeypot spam protection
- ✅ Admin menu: Tournaments → Player Management
- ✅ Shortcode: `[player_registration]`

---

## 2. Database Integration Tests

### Schema Verification
```sql
-- Verify all Phase 1 tables exist
SHOW TABLES LIKE 'wp_poker_tournament_templates';
SHOW TABLES LIKE 'wp_poker_blind_schedules';
SHOW TABLES LIKE 'wp_poker_blind_levels';
SHOW TABLES LIKE 'wp_poker_prize_structures';

-- Verify existing integration table
SHOW TABLES LIKE 'wp_poker_tournament_players';
```

### Foreign Key Relationships
- **Blind Levels** → **Blind Schedules** (`schedule_id`)
- **Player Statistics** → **Tournament Players** (`player_uuid`)
- **Templates** ← Used by tournament creation flow

### Data Integrity Tests

#### Test 1.1: Template Creation
- [ ] Create tournament template
- [ ] Verify record in `wp_poker_tournament_templates`
- [ ] Verify all financial fields populated correctly
- [ ] Check default values applied

#### Test 1.2: Blind Schedule Creation
- [ ] Create blind schedule
- [ ] Add 10 blind levels
- [ ] Verify parent record in `wp_poker_blind_schedules`
- [ ] Verify 10 child records in `wp_poker_blind_levels`
- [ ] Check `schedule_id` foreign key integrity

#### Test 1.3: Prize Structure Creation
- [ ] Create prize structure
- [ ] Verify JSON encoding of prize distribution
- [ ] Check default templates loaded correctly

#### Test 1.4: Player Management
- [ ] Create player via admin
- [ ] Verify WordPress post created (post_type='player')
- [ ] Check post_meta (player_uuid, email, phone)
- [ ] Import 100 players via CSV
- [ ] Verify no duplicate UUIDs created

---

## 3. WordPress Integration Tests

### Admin Menu Structure

**Expected Menu Structure:**
```
Tournament Manager (dashicons-trophy)
├── Templates
├── Blind Builder
└── Prize Calculator

Tournaments
├── All Tournaments
├── Add New
├── Categories
├── Tags
└── Player Management ← NEW
```

#### Test 2.1: Menu Registration
- [ ] Verify "Tournament Manager" top-level menu appears
- [ ] Check all 3 submenus load correctly
- [ ] Verify "Player Management" under Tournaments menu
- [ ] Test capability restrictions (manage_options required)

#### Test 2.2: Post Types
- [ ] Player custom post type registered
- [ ] Tournament custom post type still functional
- [ ] Both post types editable in admin
- [ ] Post meta stored/retrieved correctly

### Shortcode Integration

#### Test 2.3: Player Registration Shortcode
```php
// Test basic shortcode
[player_registration]

// Test with attributes
[player_registration
    title="Join Our League"
    require_email="yes"
    require_phone="yes"
    show_bio="yes"]
```

**Test Checklist:**
- [ ] Shortcode renders form
- [ ] Form fields match attributes
- [ ] Honeypot field hidden
- [ ] AJAX submission works
- [ ] Success/error messages display
- [ ] Nonce validation passes
- [ ] Player created with status='pending'

---

## 4. End-to-End Workflow Tests

### Workflow A: Complete Tournament Setup

**Steps:**
1. Create Tournament Template
   - Name: "Weekly Friday Tournament"
   - Buy-in: $50 + $5 rake
   - 1 rebuy, 1 add-on

2. Create Blind Schedule
   - Name: "Friday Night Turbo"
   - Clone from "Turbo" template
   - Modify levels 5-10

3. Create Prize Structure
   - Name: "Top 3 Payout"
   - 50/30/20 distribution

4. Register Players
   - Import 50 players via CSV
   - Add 10 players manually
   - 5 players register via frontend form

**Expected Results:**
- [ ] Template saved with all settings
- [ ] Blind schedule cloned and customized
- [ ] Prize structure calculated correctly
- [ ] All 65 players in database
- [ ] No duplicate UUIDs
- [ ] All data queryable for tournament creation

### Workflow B: Player Import & Management

**Steps:**
1. Prepare CSV with 100 players
2. Upload via Import tab
3. Preview shows stats (100 total, X valid, Y invalid)
4. Handle 10 duplicates (skip option)
5. Execute import
6. Search for specific player
7. Edit player details
8. Delete player

**Expected Results:**
- [ ] Import preview accurate
- [ ] Duplicate detection works
- [ ] 90 new players created (100 - 10 duplicates)
- [ ] Search finds players instantly
- [ ] Edits save correctly
- [ ] Delete removes post + meta
- [ ] Statistics updated

### Workflow C: Frontend Registration Flow

**Steps:**
1. User visits page with `[player_registration]`
2. Fills form (name, email, phone, bio)
3. Submits form
4. Admin receives notification email
5. Admin reviews in Player Management
6. Admin approves (changes status to publish)

**Expected Results:**
- [ ] Form displays with proper styling
- [ ] Client-side validation works
- [ ] Honeypot catches spam bots
- [ ] AJAX creates player with status='pending'
- [ ] Email sent to admin
- [ ] Player appears in admin list
- [ ] Status change updates WordPress post

---

## 5. Security Audit

### AJAX Nonce Verification

**Files to Audit:**
1. `tournament-templates-page.php` - Template operations
2. `blind-builder-page.php` - Blind schedule operations
3. `prize-calculator-page.php` - Prize structure operations
4. `player-management-page.php` - Player operations (4 handlers)
5. `class-player-registration.php` - Frontend registration

**Test Checklist:**
- [ ] All AJAX handlers call `check_ajax_referer()`
- [ ] All form submissions call `check_admin_referer()`
- [ ] Nonce names unique and consistent
- [ ] Failed nonce returns 403

### Capability Checks

**Required Capabilities:**
- Admin pages: `manage_options`
- Frontend registration: No capability (public)

**Test Checklist:**
- [ ] Non-admin users blocked from admin pages
- [ ] Editor role blocked from Tournament Manager
- [ ] Public can access registration form
- [ ] AJAX capability checks in place

### Data Sanitization

**Input Sanitization:**
```php
// Tournament Template
$name = sanitize_text_field($_POST['template_name']);
$buyin = floatval($_POST['buyin']);
$rake_percentage = floatval($_POST['rake_percentage']);

// Player Registration
$name = sanitize_text_field($_POST['player_name']);
$email = sanitize_email($_POST['player_email']);
$bio = wp_kses_post($_POST['player_bio']);
```

**Test Checklist:**
- [ ] All $_POST data sanitized
- [ ] All $_GET data sanitized
- [ ] Email validation works
- [ ] HTML stripped from text fields
- [ ] wp_kses_post allows safe HTML in bio

### Output Escaping

**Escaping Functions:**
```php
echo esc_html($template_name);
echo esc_attr($buyin);
echo esc_url($edit_link);
```

**Test Checklist:**
- [ ] All dynamic output escaped
- [ ] Proper escape function for context
- [ ] No raw `echo` of user input

### SQL Injection Prevention

**Prepared Statements:**
```php
$wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}poker_tournament_templates WHERE id = %d",
    $template_id
);
```

**Test Checklist:**
- [ ] All database queries use `$wpdb->prepare()`
- [ ] No direct SQL concatenation
- [ ] Placeholders (%d, %s, %f) used correctly

---

## 6. Performance Testing

### Large Dataset Tests

#### Test 6.1: 500 Players
- [ ] Import 500 players via CSV
- [ ] Page load time < 2 seconds
- [ ] Search returns results < 500ms
- [ ] Pagination works smoothly

#### Test 6.2: 100 Blind Levels
- [ ] Create schedule with 100 levels
- [ ] Save time < 3 seconds
- [ ] Edit page loads in < 2 seconds
- [ ] No browser memory issues

#### Test 6.3: 50 Templates
- [ ] Create 50 tournament templates
- [ ] List page loads < 2 seconds
- [ ] Dropdown selection instant
- [ ] No N+1 query issues

### Database Query Optimization

**Queries to Review:**
1. Player list query (paginated)
2. Blind level fetch for schedule
3. Prize structure calculation
4. Player statistics aggregation

**Optimization Checklist:**
- [ ] Indexes on foreign keys
- [ ] Indexes on UUID columns
- [ ] No SELECT * queries
- [ ] Proper JOIN usage
- [ ] Query caching where appropriate

### Asset Loading

**CSS Files:**
- `player-management-admin.css` (400 lines)
- `player-registration-frontend.css` (250 lines)
- Existing admin.css

**JS Files:**
- `player-management-admin.js` (350 lines)
- `player-registration-frontend.js` (200 lines)

**Test Checklist:**
- [ ] Assets load only on relevant pages
- [ ] No JavaScript errors in console
- [ ] CSS doesn't conflict with WordPress core
- [ ] Minification possible for production

---

## 7. Cross-Feature Integration

### Integration Point 1: Templates → Tournaments

**Test:**
1. Create tournament template
2. Use template in tournament creation
3. Verify settings applied

**Expected:**
- [ ] Template data populates tournament fields
- [ ] Financial calculations use template settings

### Integration Point 2: Blind Schedules → Tournaments

**Test:**
1. Create custom blind schedule
2. Assign to tournament
3. Display during tournament

**Expected:**
- [ ] Blind levels loaded correctly
- [ ] Duration calculations accurate
- [ ] Break levels identified

### Integration Point 3: Players → Tournaments

**Test:**
1. Create 50 players
2. Assign to tournament via admin
3. View tournament results
4. Check player statistics

**Expected:**
- [ ] Players selectable in tournament
- [ ] Results stored in `poker_tournament_players`
- [ ] Statistics calculated correctly
- [ ] Player profiles show tournament history

### Integration Point 4: Prize Structures → Payouts

**Test:**
1. Create prize structure (50/30/20)
2. Calculate prizes for $1000 prize pool
3. Verify math

**Expected:**
- [ ] $500, $300, $200 distribution
- [ ] Rounding handled correctly
- [ ] Chop calculations accurate

---

## 8. Bug Tracking

### Known Issues
_To be populated during testing_

| ID | Severity | Component | Description | Status |
|----|----------|-----------|-------------|--------|
| - | - | - | - | - |

### Fixed Bugs
_To be populated during testing_

| ID | Component | Description | Fix Commit |
|----|-----------|-------------|------------|
| - | - | - | - |

---

## 9. User Documentation Checklist

### Admin Documentation

- [ ] Tournament Templates guide
  - [ ] Creating templates
  - [ ] Financial settings
  - [ ] Cloning templates

- [ ] Blind Builder guide
  - [ ] Creating schedules
  - [ ] Adding levels
  - [ ] Exporting to PDF

- [ ] Prize Calculator guide
  - [ ] Setting up structures
  - [ ] Using templates
  - [ ] Handling chops

- [ ] Player Management guide
  - [ ] Adding players manually
  - [ ] Importing via CSV
  - [ ] Managing player status

### Frontend Documentation

- [ ] Player Registration shortcode
  - [ ] Shortcode attributes
  - [ ] Customization options
  - [ ] Styling guide

### Developer Documentation

- [ ] Database schema reference
- [ ] Hook/filter reference
- [ ] Class structure overview
- [ ] Integration examples

---

## 10. Release Checklist

### Pre-Release

- [ ] All integration tests pass
- [ ] Security audit complete
- [ ] Performance benchmarks met
- [ ] Documentation complete
- [ ] No critical bugs

### Code Quality

- [ ] PHP syntax verified on all files
- [ ] WordPress Coding Standards compliant
- [ ] No PHP warnings/notices
- [ ] JavaScript console clean
- [ ] CSS validated

### Version Update

- [ ] Update version in `poker-tournament-import.php` header
- [ ] Update `POKER_TOURNAMENT_IMPORT_VERSION` constant
- [ ] Write release notes for v3.0.0
- [ ] Create distribution ZIP

### Testing Environment

- [ ] PHP 8.0+ tested
- [ ] PHP 8.1 tested
- [ ] PHP 8.2 tested
- [ ] WordPress 6.0+ tested
- [ ] WordPress 6.4 tested
- [ ] MySQL 5.7+ tested
- [ ] MariaDB 10.2+ tested

---

## 11. Beta Testing Plan

### Beta Tester Recruitment
- Target: 10 active poker tournament organizers
- Duration: 2 weeks
- Feedback form provided

### Test Scenarios for Beta
1. Create full tournament from scratch
2. Import player database
3. Run simulated tournament
4. Generate reports
5. Test on mobile devices

### Feedback Collection
- [ ] Usability issues
- [ ] Feature requests
- [ ] Bug reports
- [ ] Performance concerns
- [ ] Documentation gaps

---

## 12. Success Metrics

### Technical Metrics
- [ ] 0 critical bugs
- [ ] < 5 minor bugs
- [ ] 100% security tests pass
- [ ] Page load < 2 seconds
- [ ] Database queries < 100ms avg

### User Experience Metrics
- [ ] Setup time < 10 minutes for first tournament
- [ ] CSV import < 30 seconds for 100 players
- [ ] Intuitive UI (no training needed)
- [ ] Mobile responsive

### Business Metrics (Phase 1 Goals)
- Target: 500+ installs
- Target: 25+ paid subscriptions
- Target: 4.5+ star rating

---

## Test Execution Log

### Test Session 1: [DATE]
**Tester:** [NAME]
**Environment:** PHP [VERSION] / WordPress [VERSION]

#### Tests Executed:
- [ ] Database Integration (Tests 1.1-1.4)
- [ ] WordPress Integration (Tests 2.1-2.3)
- [ ] Workflow A
- [ ] Workflow B
- [ ] Workflow C
- [ ] Security Audit
- [ ] Performance Tests

#### Results:
_To be completed_

#### Issues Found:
_To be listed_

---

**Document Version:** 1.0
**Last Updated:** 2025-01-27
**Next Review:** After test execution
