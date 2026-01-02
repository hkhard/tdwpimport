# Tasks: Restore Dashboard Overview and Action Buttons

**Input**: Design documents from `/specs/006-restore-dashboard/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Manual testing only - no automated test tasks required for this feature.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each dashboard section.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3, US4)
- Include exact file paths in descriptions

## Path Conventions

- WordPress plugin structure: `wordpress-plugin/poker-tournament-import/`
- Admin code: `wordpress-plugin/poker-tournament-import/admin/class-admin.php`
- No new files created - all changes in existing `class-admin.php`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Feature branch preparation and backup

- [x] T001 Verify branch 006-restore-dashboard is checked out and clean
- [x] T002 Create backup of wordpress-plugin/poker-tournament-import/admin/class-admin.php to wordpress-plugin/poker-tournament-import/admin/class-admin.php.backup
- [x] T003 [P] Review original implementation from git commit 4ff9552 using command: `git show 4ff9552:wordpress-plugin/poker-tournament-import/admin/class-admin.php | grep -A 300 "function render_dashboard"`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Verify menu registration is in place

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T004 Verify menu registration in wordpress-plugin/poker-tournament-import/admin/class-admin.php add_admin_menu() method points to render_dashboard() callback
- [x] T005 [P] Verify Poker_Tournament_Formula_Validator class exists and get_all_formulas() method is accessible in wordpress-plugin/poker-tournament-import/includes/class-formula-validator.php
- [x] T006 [P] Verify custom post types (tournament, player, tournament_season) are registered in wordpress-plugin/poker-tournament-import/includes/class-post-types.php

**Checkpoint**: Foundation ready - render_dashboard() method implementation can now begin

---

## Phase 3: User Story 1 - View Statistics Overview (Priority: P1) 🎯 MVP

**Goal**: Display 4 stat cards showing tournament count, player count, season count, and formula count with action buttons

**Independent Test**: Navigate to "Poker Import" admin menu and verify 4 stat cards display with correct counts and "View All"/"Manage" links work

### Implementation for User Story 1

- [ ] T007 Add render_dashboard() method skeleton to wordpress-plugin/poker-tournament-import/admin/class-admin.php with PHPDoc header
- [ ] T008 [US1] Implement tournament count retrieval in render_dashboard() using wp_count_posts('tournament') and calculate total (publish + draft + private)
- [ ] T009 [US1] Implement player count retrieval in render_dashboard() using wp_count_posts('player') and calculate total (publish + draft + private)
- [ ] T010 [US1] Implement season count retrieval in render_dashboard() using wp_count_posts('tournament_season') and calculate total (publish + draft + private)
- [ ] T011 [US1] Implement formula count retrieval in render_dashboard() using Poker_Tournament_Formula_Validator::get_all_formulas() and count()
- [ ] T012 [US1] Render stat cards grid HTML in render_dashboard() with inline CSS (grid-template-columns: repeat(4, 1fr))
- [ ] T013 [US1] Render tournaments stat card in render_dashboard() with dashicon (dashicons-list-view), count, number_format(), and "View All" button linking to edit.php?post_type=tournament
- [ ] T014 [US1] Render players stat card in render_dashboard() with dashicon (dashicons-groups), count, number_format(), and "View All" button linking to edit.php?post_type=player
- [ ] T015 [US1] Render seasons stat card in render_dashboard() with dashicon (dashicons-calendar-alt), count, number_format(), and "View All" button linking to edit.php?post_type=tournament_season
- [ ] T016 [US1] Render formulas stat card in render_dashboard() with dashicon (dashicons-calculator), count, number_format(), and "Manage" button linking to admin.php?page=poker-formula-manager
- [ ] T017 [US1] Add output escaping to all stat card text using esc_html() for counts and labels
- [ ] T018 [US1] Add URL escaping to all stat card button links using esc_url() with admin_url()

**Checkpoint**: At this point, User Story 1 should be fully functional - 4 stat cards display with accurate counts and working links

---

## Phase 4: User Story 2 - Access Quick Actions (Priority: P2)

**Goal**: Display Quick Actions section with 4 action buttons for common tasks

**Independent Test**: Verify Quick Actions section displays 4 buttons (Import Tournament, View Tournaments, View Players, Manage Formulas) that navigate correctly

### Implementation for User Story 2

- [ ] T019 [US2] Render Quick Actions section HTML in render_dashboard() after stat cards grid with inline CSS (2-column layout: Data Mart Health left 2fr, Quick Actions right 1fr)
- [ ] T020 [US2] Render section heading in render_dashboard() with dashicon (dashicons-admin-tools) and "Quick Actions" title using esc_html__()
- [ ] T021 [P] [US2] Render "Import Tournament" button in render_dashboard() with button-primary class, dashicon (dashicons-upload), and link to admin.php?page=poker-tournament-import-import
- [ ] T022 [P] [US2] Render "View Tournaments" button in render_dashboard() with dashicon (dashicons-list-view) and link to edit.php?post_type=tournament
- [ ] T023 [P] [US2] Render "View Players" button in render_dashboard() with dashicon (dashicons-groups) and link to edit.php?post_type=player
- [ ] T024 [P] [US2] Render "Manage Formulas" button in render_dashboard() with dashicon (dashicons-calculator) and link to admin.php?page=poker-formula-manager
- [ ] T025 [US2] Add output escaping to all Quick Actions text using esc_html__() for labels and esc_url() for button links

**Checkpoint**: At this point, User Stories 1 AND 2 should both work - stat cards and quick actions displayed

---

## Phase 5: User Story 3 - Monitor Data Mart Health (Priority: P3)

**Goal**: Display Data Mart Health section showing status (Active/Not Created), record count, and last refresh time

**Independent Test**: Verify Data Mart Health section shows correct status, record count, and last refresh time with "Refresh Statistics" button

### Implementation for User Story 3

- [ ] T026 [US3] Implement data mart table existence check in render_dashboard() using $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") where table_name is wp_poker_statistics
- [ ] T027 [US3] Implement data mart row count query in render_dashboard() using $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}") if table exists, else 0
- [ ] T028 [US3] Retrieve last refresh time in render_dashboard() using get_option('tdwp_statistics_last_refresh', null)
- [ ] T029 [US3] Render Data Mart Health section HTML in render_dashboard() with inline CSS, section heading with dashicon (dashicons-database)
- [ ] T030 [US3] Render status table in render_dashboard() with 3 rows: Status (Active/Not Created with colored dot), Records (count), Last Refresh (formatted date or "Never")
- [ ] T031 [US3] Implement status display logic in render_dashboard(): green dot with "Active" if table exists, red dot with "Not Created" if table missing
- [ ] T032 [US3] Format last refresh time in render_dashboard() using date_i18n('M j, Y g:i A', $timestamp) if timestamp exists, else display "Never" using esc_html__()
- [ ] T033 [US3] Render "Refresh Statistics" button in render_dashboard() linking to admin.php?page=poker-tournament-import-settings with esc_url()
- [ ] T034 [US3] Add output escaping to all Data Mart Health text using esc_html() for status and labels, esc_url() for button link

**Checkpoint**: At this point, User Stories 1, 2, AND 3 should all work - stat cards, quick actions, and data mart health displayed

---

## Phase 6: User Story 4 - View Recent Activity (Priority: P4)

**Goal**: Display Recent Activity table showing 5 most recently imported tournaments with action links

**Independent Test**: Verify Recent Activity table shows exactly 5 most recent tournaments (when tournaments exist) with correct columns and working action links

### Implementation for User Story 4

- [ ] T035 [US4] Implement recent tournaments query in render_dashboard() using get_posts() with post_type='tournament', posts_per_page=5, orderby='date', order='DESC', post_status=array('publish', 'draft', 'private')
- [ ] T036 [US4] Add conditional logic in render_dashboard() to only render Recent Activity section if recent_tournaments is not empty
- [ ] T037 [US4] Render Recent Activity section HTML in render_dashboard() with inline CSS, section heading with dashicon (dashicons-clock)
- [ ] T038 [US4] Render table HTML in render_dashboard() with class="widefat striped" and 4 column headers: Tournament, Date Imported, Status, Actions
- [ ] T039 [US4] Implement table body loop in render_dashboard() iterating through recent_tournaments and rendering rows
- [ ] T040 [US4] Render tournament name cell in render_dashboard() as escaped link to post.php?post={ID}&action=edit with esc_url() and post_title with esc_html()
- [ ] T041 [US4] Render date imported cell in render_dashboard() using date_i18n('M j, Y', $post->post_date) with esc_html()
- [ ] T042 [US4] Render status cell in render_dashboard() with post_status (Published/Draft/Private) using esc_html__()
- [ ] T043 [US4] Render actions cell in render_dashboard() with 3 links: Edit (post.php?post={ID}&action=edit), View (get_permalink()), Trash (get_delete_post_link()) all escaped with esc_url()
- [ ] T044 [US4] Add edge case handling in render_dashboard(): display "Untitled Tournament" via esc_html() if post_title is empty

**Checkpoint**: All user stories should now be independently functional - complete dashboard with all 4 sections

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Code quality, validation, and documentation

- [ ] T045 [P] Run PHP syntax check on wordpress-plugin/poker-tournament-import/admin/class-admin.php using `php -l`
- [ ] T046 [P] Verify all text output uses internationalization functions __() or _e() with text domain 'poker-tournament-import'
- [ ] T047 [P] Verify all output is properly escaped: text uses esc_html(), URLs use esc_url(), attributes use esc_attr()
- [ ] T048 [P] Verify no WordPress PHP errors or warnings in debug log when loading dashboard
- [ ] T049 [P] Test dashboard with empty database (0 tournaments, 0 players, 0 seasons) and verify graceful display (counts show "0", Recent Activity hidden)
- [ ] T050 [P] Test dashboard with sample data (5+ tournaments) and verify accurate counts and Recent Activity displays exactly 5 tournaments
- [ ] T051 [P] Visual verification: Compare dashboard appearance with v3.3/v3.4 screenshots (spacing, colors, icons match)
- [ ] T052 Test all navigation links from stat cards and Quick Actions buttons verify each link navigates to correct admin page
- [ ] T053 Test Data Mart Health section with table existing and missing, verify status displays correctly
- [ ] T054 Bump version number in wordpress-plugin/poker-tournament-import/poker-tournament-import.php to 3.5.0-beta37
- [ ] T055 Create distribution ZIP file: poker-tournament-import-v3.5.0-beta37.zip from wordpress-plugin/poker-tournament-import/ directory

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3-6)**: All depend on Foundational phase completion
  - User stories can proceed sequentially in priority order (US1 → US2 → US3 → US4)
  - Each US builds on the previous by adding more sections to the same render_dashboard() method
- **Polish (Phase 7)**: Depends on all user stories being complete

### User Story Dependencies

- **User Story 1 (P1 - Stat Cards)**: Can start after Foundational - No dependencies on other stories (first section in dashboard)
- **User Story 2 (P2 - Quick Actions)**: Can start after US1 complete - Adds section below stat cards in same method
- **User Story 3 (P3 - Data Mart Health)**: Can start after US2 complete - Adds left column section in same method
- **User Story 4 (P4 - Recent Activity)**: Can start after US3 complete - Adds final section in same method

### Within Each User Story

- Data retrieval queries before rendering HTML
- Core rendering before edge case handling
- Story complete before moving to next priority

### Parallel Opportunities

- Setup tasks T002 and T003 can run in parallel
- Foundational tasks T005 and T006 can run in parallel
- Within US2: Tasks T021, T022, T023, T024 (4 buttons) can run in parallel after T019 and T020
- Within US4: No parallel opportunities (sequential table building)
- Polish tasks T045, T046, T047, T048, T049, T050, T051 can run in parallel

---

## Parallel Example: User Story 2 (Quick Actions)

```bash
# After rendering section structure (T019, T020), launch all 4 button tasks together:
Task: "Render 'Import Tournament' button in render_dashboard()..."
Task: "Render 'View Tournaments' button in render_dashboard()..."
Task: "Render 'View Players' button in render_dashboard()..."
Task: "Render 'Manage Formulas' button in render_dashboard()..."
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (backup, review original code)
2. Complete Phase 2: Foundational (verify menu, verify dependencies)
3. Complete Phase 3: User Story 1 (4 stat cards with counts and links)
4. **STOP and VALIDATE**: Test User Story 1 independently - navigate to dashboard, verify stat cards display correctly
5. Deploy/demo if ready (MVP delivers immediate value: see database stats at a glance)

### Incremental Delivery

1. Complete Setup + Foundational → Dashboard page ready for implementation
2. Add User Story 1 → Test independently → Deploy/Demo (MVP: stat cards showing database overview)
3. Add User Story 2 → Test independently → Deploy/Demo (quick actions added)
4. Add User Story 3 → Test independently → Deploy/Demo (data mart health monitoring)
5. Add User Story 4 → Test independently → Deploy/Demo (recent activity table)
6. Each story adds value without breaking previous sections

### Sequential Within Story Strategy

**Note**: Unlike projects with multiple files, this feature modifies a single method (render_dashboard()) in a single file (class-admin.php).

**Recommended Approach**:
1. Complete each user story's tasks in order (T007-T018, then T019-T025, etc.)
2. Commit after each complete user story phase
3. Test incrementally - don't wait until all stories are complete
4. Each phase adds a new section to the dashboard without modifying previous sections

---

## Notes

- [P] tasks = different files or independent operations (only within US2 and US4, plus polish phase)
- [Story] label maps task to specific user story for traceability
- Each user story adds a new section to the dashboard, building incrementally
- All work is in wordpress-plugin/poker-tournament-import/admin/class-admin.php
- No new files created - restoration of existing functionality
- Manual testing required (no automated tests for this feature)
- Commit after each user story phase (T018, T025, T034, T044)
- Stop at any checkpoint to validate story independently
- Visual appearance must match v3.3/v3.4 exactly
