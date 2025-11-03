# TD3 Integration Data Model

**Date**: 2025-11-03
**Status**: Complete
**Phase**: Phase 1 Design & Contracts

## Overview

This data model extends the existing Poker Tournament Import plugin database schema to support TD3 (Tournament Director 3) integration features. The design builds upon the existing 12-table foundation with 17 new tables organized into 5 functional areas.

## Existing Foundation (Reference)

### Core Tables Already Implemented
- `wp_posts` (tournament, player, tournament_series post types)
- `wp_poker_tournament_templates` - Tournament configurations
- `wp_poker_blind_schedules` & `wp_poker_blind_levels` - Blind structures
- `wp_poker_prize_structures` - Prize distribution templates
- `wp_poker_tournament_players` - Player registration data
- `wp_poker_tournament_live_state` - Real-time tournament state
- `wp_poker_tournament_tables` - Table management
- `wp_poker_tournament_seats` - Seat assignments
- `wp_poker_tournament_events` - Event logging
- `wp_poker_transactions` - Financial transaction log

## New TD3 Integration Tables

### 1. Display System (4 tables)

#### `wp_poker_display_templates`
Display template definitions with token support.

**Fields:**
- `template_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `tournament_id` (BIGINT, FOREIGN KEY → wp_posts.ID)
- `template_name` (VARCHAR(255)) - Human-readable name
- `template_type` (ENUM('clock','rankings','prizes','seating','rules','custom'))
- `html_template` (LONGTEXT) - HTML with {{token}} placeholders
- `css_styles` (LONGTEXT) - Custom CSS for template
- `tokens_used` (LONGTEXT) - JSON array of tokens in template
- `is_default` (BOOLEAN) - Default template for type
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (template_id)
- INDEX idx_tournament_type (tournament_id, template_type)
- INDEX idx_type_default (template_type, is_default)

#### `wp_poker_display_layouts`
Drag-and-drop layout configurations.

**Fields:**
- `layout_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `tournament_id` (BIGINT, FOREIGN KEY → wp_posts.ID)
- `layout_name` (VARCHAR(255))
- `screen_size` (VARCHAR(50)) - Target screen dimension
- `grid_config` (LONGTEXT) - CSS Grid configuration
- `component_positions` (LONGTEXT) - JSON of component positions
- `breakpoints` (LONGTEXT) - Responsive breakpoint configurations
- `is_active` (BOOLEAN)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (layout_id)
- INDEX idx_tournament_active (tournament_id, is_active)

#### `wp_poker_display_screens`
Physical display endpoint management.

**Fields:**
- `screen_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `screen_name` (VARCHAR(255))
- `endpoint_url` (VARCHAR(500)) - Unique URL for display
- `layout_id` (BIGINT, FOREIGN KEY → wp_poker_display_layouts.layout_id)
- `template_id` (BIGINT, FOREIGN KEY → wp_poker_display_templates.template_id)
- `screen_type` (ENUM('clock','rankings','prizes','seating','custom'))
- `location` (VARCHAR(255)) - Physical location description
- `last_ping` (DATETIME) - Last heartbeat from display
- `is_online` (BOOLEAN)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (screen_id)
- UNIQUE INDEX idx_endpoint_url (endpoint_url)
- INDEX idx_online (is_online, last_ping)

#### `wp_poker_display_tokens`
Token registry for dynamic content.

**Fields:**
- `token_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `token_name` (VARCHAR(100)) - {{token_name}}
- `token_description` (TEXT)
- `token_type` (ENUM('tournament','player','blind','prize','time','custom'))
- `data_source` (VARCHAR(255)) - Method/function to get data
- `default_format` (VARCHAR(255)) - Default formatting
- `is_active` (BOOLEAN)
- `created_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (token_id)
- UNIQUE INDEX idx_token_name (token_name)
- INDEX idx_type_active (token_type, is_active)

### 2. Event Notification System (3 tables)

#### `wp_poker_event_queue`
Event queue for notification processing.

**Fields:**
- `event_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `tournament_id` (BIGINT, FOREIGN KEY → wp_posts.ID)
- `event_type` (VARCHAR(100)) - level_change, break_start, final_table, etc.
- `event_data` (LONGTEXT) - JSON payload with event details
- `priority` (ENUM('low','medium','high','critical'))
- `notification_types` (LONGTEXT) - JSON: [sound,email,sms,display]
- `scheduled_at` (DATETIME) - When to process
- `processed_at` (DATETIME) - When processed
- `status` (ENUM('pending','processing','completed','failed'))
- `retry_count` (INT)
- `error_message` (TEXT)
- `created_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (event_id)
- INDEX idx_tournament_status (tournament_id, status)
- INDEX idx_scheduled_priority (scheduled_at, priority)
- INDEX idx_status_created (status, created_at)

#### `wp_poker_notification_preferences`
User notification preferences.

**Fields:**
- `preference_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `user_id` (BIGINT, FOREIGN KEY → wp_users.ID)
- `tournament_id` (BIGINT, FOREIGN KEY → wp_posts.ID, NULLABLE)
- `event_types` (LONGTEXT) - JSON of subscribed event types
- `sound_enabled` (BOOLEAN)
- `email_enabled` (BOOLEAN)
- `sms_enabled` (BOOLEAN)
- `quiet_hours` (VARCHAR(100)) - Time range for quiet hours
- `custom_settings` (LONGTEXT) - JSON additional settings
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (preference_id)
- INDEX idx_user_tournament (user_id, tournament_id)
- INDEX idx_user_id (user_id)

#### `wp_poker_sound_library`
Sound effect management.

**Fields:**
- `sound_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `sound_name` (VARCHAR(255))
- `sound_file` (VARCHAR(500)) - URL to sound file
- `event_types` (LONGTEXT) - JSON of associated event types
- `duration_ms` (INT) - Sound duration in milliseconds
- `volume_level` (DECIMAL(3,2)) - Default volume (0.00-1.00)
- `is_default` (BOOLEAN) - Default sound for event type
- `is_active` (BOOLEAN)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (sound_id)
- INDEX idx_event_types (event_types(100))
- INDEX idx_active_default (is_active, is_default)

### 3. Player Engagement Features (6 tables)

#### `wp_poker_player_photos`
Player photo management.

**Fields:**
- `photo_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `player_id` (BIGINT, FOREIGN KEY → wp_posts.ID)
- `attachment_id` (BIGINT, FOREIGN KEY → wp_posts.ID) - Media library attachment
- `photo_type` (ENUM('profile','badge','action'))
- `caption` (VARCHAR(500))
- `display_order` (INT)
- `is_approved` (BOOLEAN)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (photo_id)
- INDEX idx_player_type (player_id, photo_type)
- INDEX idx_approved (is_approved)

#### `wp_poker_achievements`
Achievement system definitions.

**Fields:**
- `achievement_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `achievement_key` (VARCHAR(100)) - Unique identifier
- `achievement_name` (VARCHAR(255))
- `description` (TEXT)
- `icon_url` (VARCHAR(500))
- `points_bonus` (INT)
- `achievement_type` (ENUM('milestone','streak','performance','special'))
- `criteria` (LONGTEXT) - JSON achievement criteria
- `is_active` (BOOLEAN)
- `created_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (achievement_id)
- UNIQUE INDEX idx_achievement_key (achievement_key)
- INDEX idx_type_active (achievement_type, is_active)

#### `wp_poker_player_achievements`
Player earned achievements.

**Fields:**
- `player_achievement_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `player_id` (BIGINT, FOREIGN KEY → wp_posts.ID)
- `achievement_id` (BIGINT, FOREIGN KEY → wp_poker_achievements.achievement_id)
- `tournament_id` (BIGINT, FOREIGN KEY → wp_posts.ID, NULLABLE)
- `earned_at` (DATETIME)
- `points_awarded` (INT)
- `metadata` (LONGTEXT) - JSON additional data

**Indexes:**
- PRIMARY KEY (player_achievement_id)
- INDEX idx_player_achievement (player_id, achievement_id)
- INDEX idx_tournament_earned (tournament_id, earned_at)

#### `wp_poker_leagues`
League/season management.

**Fields:**
- `league_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `league_name` (VARCHAR(255))
- `league_description` (TEXT)
- `start_date` (DATE)
- `end_date` (DATE)
- `scoring_formula` (LONGTEXT) - Points calculation formula
- `tournament_weights` (LONGTEXT) - JSON tournament weightings
- `best_x_count` (INT) - Best X tournaments count
- `player_cap` (INT) - Maximum players
- `is_active` (BOOLEAN)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (league_id)
- INDEX idx_dates (start_date, end_date)
- INDEX idx_active (is_active)

#### `wp_poker_league_standings`
League standings calculations.

**Fields:**
- `standing_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `league_id` (BIGINT, FOREIGN KEY → wp_poker_leagues.league_id)
- `player_id` (BIGINT, FOREIGN KEY → wp_posts.ID)
- `total_points` (DECIMAL(10,2))
- `tournaments_played` (INT)
- `tournaments_won` (INT)
- `best_finish` (INT)
- `average_finish` (DECIMAL(5,2))
- `last_calculated` (DATETIME)
- `rank_position` (INT)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (standing_id)
- UNIQUE INDEX idx_league_player (league_id, player_id)
- INDEX idx_league_rank (league_id, rank_position)

#### `wp_poker_league_tournaments`
Tournaments in leagues.

**Fields:**
- `league_tournament_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `league_id` (BIGINT, FOREIGN KEY → wp_poker_leagues.league_id)
- `tournament_id` (BIGINT, FOREIGN KEY → wp_posts.ID)
- `weight_multiplier` (DECIMAL(3,2)) - Weight for scoring (1.0 = normal)
- `included_in_standings` (BOOLEAN)
- `added_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (league_tournament_id)
- UNIQUE INDEX idx_league_tournament (league_id, tournament_id)
- INDEX idx_tournament_included (tournament_id, included_in_standings)

### 4. QR Code System (2 tables)

#### `wp_poker_qr_codes`
QR code generation tracking.

**Fields:**
- `qr_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `qr_code_data` (LONGTEXT) - JSON data encoded in QR
- `qr_type` (ENUM('player_checkin','receipt','badge','table'))
- `entity_id` (BIGINT) - Related entity ID (player, tournament, etc.)
- `qr_url` (VARCHAR(500)) - URL to QR image
- `expires_at` (DATETIME)
- `is_active` (BOOLEAN)
- `usage_count` (INT)
- `last_used` (DATETIME)
- `created_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (qr_id)
- INDEX idx_type_entity (qr_type, entity_id)
- INDEX idx_expires (expires_at, is_active)

#### `wp_poker_qr_scans`
QR code scan logging.

**Fields:**
- `scan_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `qr_id` (BIGINT, FOREIGN KEY → wp_poker_qr_codes.qr_id)
- `scanned_at` (DATETIME)
- `scan_result` (ENUM('success','expired','invalid','duplicate'))
- `user_agent` (VARCHAR(500))
- `ip_address` (VARCHAR(45))
- `location_data` (LONGTEXT) - JSON location if available

**Indexes:**
- PRIMARY KEY (scan_id)
- INDEX idx_qr_scanned (qr_id, scanned_at)
- INDEX idx_scanned_date (scanned_at)

### 5. Multi-Screen Endpoints (2 tables)

#### `wp_poker_screen_endpoints` (Alias for wp_poker_display_screens)
Physical display endpoints already defined in display system.

#### `wp_poker_screen_status`
Real-time screen status tracking.

**Fields:**
- `status_id` (BIGINT, AUTO_INCREMENT, PRIMARY)
- `screen_id` (BIGINT, FOREIGN KEY → wp_poker_display_screens.screen_id)
- `tournament_id` (BIGINT, FOREIGN KEY → wp_posts.ID)
- `current_state` (LONGTEXT) - JSON current display state
- `last_update` (DATETIME)
- `connection_quality` (ENUM('excellent','good','poor','offline'))
- `bandwidth_usage` (DECIMAL(8,2)) - KB/s
- `error_count` (INT)
- `last_error` (TEXT)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Indexes:**
- PRIMARY KEY (status_id)
- UNIQUE INDEX idx_screen_tournament (screen_id, tournament_id)
- INDEX idx_last_update (last_update)
- INDEX idx_quality (connection_quality)

## Data Relationships

### Key Relationships

1. **Tournament-Centric Relationships**
   - All feature tables link to `wp_posts.ID` where post_type = 'tournament'
   - Enables per-tournament configuration and data isolation

2. **Player Integration**
   - Player-related tables link to `wp_posts.ID` where post_type = 'player'
   - Maintains existing player post type architecture

3. **User Management**
   - Notification preferences link to `wp_users.ID`
   - Integrates with WordPress user system

4. **Media Library Integration**
   - Player photos link to `wp_posts.ID` (attachments)
   - Leverages WordPress media handling

### Foreign Key Constraints

```sql
-- Display System
FOREIGN KEY (tournament_id) REFERENCES wp_posts(ID) ON DELETE CASCADE
FOREIGN KEY (layout_id) REFERENCES wp_poker_display_layouts(layout_id) ON DELETE SET NULL
FOREIGN KEY (template_id) REFERENCES wp_poker_display_templates(template_id) ON DELETE SET NULL

-- Event System
FOREIGN KEY (tournament_id) REFERENCES wp_posts(ID) ON DELETE CASCADE
FOREIGN KEY (user_id) REFERENCES wp_users(ID) ON DELETE CASCADE

-- Player Features
FOREIGN KEY (player_id) REFERENCES wp_posts(ID) ON DELETE CASCADE
FOREIGN KEY (achievement_id) REFERENCES wp_poker_achievements(achievement_id) ON DELETE CASCADE
FOREIGN KEY (league_id) REFERENCES wp_poker_leagues(league_id) ON DELETE CASCADE

-- QR System
FOREIGN KEY (qr_id) REFERENCES wp_poker_qr_codes(qr_id) ON DELETE CASCADE
```

## Data Validation Rules

### Field Validation

1. **URL Fields**: Must be valid URLs with http/https protocol
2. **Email Fields**: Must be valid email addresses
3. **Date/Time Fields**: Must be valid MySQL datetime formats
4. **JSON Fields**: Must be valid JSON structure
5. **Numeric Fields**: Must be within reasonable ranges
6. **Enum Fields**: Must match predefined values

### Business Logic Validation

1. **Tournament Status**: Only active tournaments can have live displays
2. **Player Registration**: Players must be registered to appear in displays
3. **League Standings**: Only completed tournaments count toward standings
4. **QR Code Expiry**: Expired QR codes cannot be used for check-ins
5. **Event Queue**: Cannot queue events for completed tournaments

## Performance Considerations

### Indexing Strategy

1. **Primary Keys**: All tables have auto-increment primary keys
2. **Foreign Keys**: All foreign key relationships are indexed
3. **Query Optimization**: Composite indexes for common query patterns
4. **Time-based Queries**: Indexes on timestamp fields for filtering

### Caching Strategy

1. **Display Templates**: Cache rendered templates for 5 minutes
2. **Player Photos**: Cache photo URLs and metadata for 1 hour
3. **League Standings**: Cache calculations for 15 minutes
4. **QR Codes**: Cache generated QR codes indefinitely until expiry

### Scaling Considerations

1. **Tournament Size**: Schema optimized for 1000+ player tournaments
2. **Multi-Screen**: Support for 10+ simultaneous displays
3. **Event Processing**: Queue system handles high-frequency events
4. **Photo Storage**: Leverages WordPress media library scaling

## Migration Strategy

### Phase 1: Display System
- Create display template and layout tables
- Migrate existing display configurations
- Update admin interface to use new system

### Phase 2: Event Notifications
- Create event queue and notification tables
- Implement event processing system
- Add notification preference management

### Phase 3: Player Features
- Create player photo and achievement tables
- Implement league management system
- Add QR code generation

### Phase 4: Multi-Screen Support
- Create screen endpoint and status tables
- Implement real-time synchronization
- Add screen management interface

## Data Model Summary

The TD3 integration data model adds **17 new tables** to the existing foundation, providing:

- **Display System**: Flexible template and layout management
- **Event Notifications**: Queue-based notification processing
- **Player Engagement**: Photo management and achievement system
- **League Management**: Complete season/league tracking
- **QR Code System**: Generation and tracking capabilities
- **Multi-Screen Support**: Real-time display synchronization

All tables follow WordPress conventions, include proper indexing, and are designed to support the phased implementation approach while maintaining backward compatibility with existing functionality.