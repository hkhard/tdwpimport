# TD3 Integration Quick Start Guide

**Feature**: Tournament Director 3 Integration for WordPress
**Version**: 1.0.0
**Date**: 2025-11-03
**Status**: Phase 1 Planning Complete

## Overview

This guide helps you get started with TD3 (Tournament Director 3) integration features for the Poker Tournament Import WordPress plugin. The current plugin already provides ~85% of TD3 functionality, and this integration adds the remaining professional features.

## Current Status

### ✅ Already Available (No Setup Required)
- **Tournament Creation & Configuration**: Complete setup wizard with financial policies
- **Live Tournament Operations**: Real-time clock, table balancing, player management
- **Player Registration**: Admin interface, frontend forms, CSV import
- **Export Capabilities**: TDT import, CSV/Excel export, email results
- **Transaction Logging**: Complete audit trail for all tournament actions

### 🚀 Coming Soon (TD3 Integration Features)
- **Display System**: Token-based templates and multi-screen support
- **Event Notifications**: Automated sounds, emails, and alerts
- **Player Engagement**: Photos, achievements, leagues, QR codes
- **Mobile App Support**: REST API for remote control

## Quick Start: What You Can Do Today

### 1. Basic Tournament Setup
The core tournament functionality is already available:

```php
// Create a new tournament
$tournament = array(
    'post_title' => 'Weekly $50 Freezeout',
    'post_type' => 'tournament',
    'post_status' => 'publish'
);

$tournament_id = wp_insert_post($tournament);

// Configure tournament settings
update_post_meta($tournament_id, '_buy_in_amount', 50);
update_post_meta($tournament_id, '_rebuy_amount', 50);
update_post_meta($tournament_id, '_rebuy_max_count', 1);
update_post_meta($tournament_id, '_rake_percentage', 10);
```

### 2. Live Tournament Management
Run tournaments with real-time control:

```php
// Start tournament clock
$clock = TDWP_Tournament_Clock::get_instance($tournament_id);
$clock->start_tournament();

// Register players
$player_manager = TDWP_Tournament_Player_Manager::get_instance();
$player_manager->register_player($tournament_id, $player_id, 50, true);

// Process bust-outs
$player_ops = TDWP_Player_Operations::get_instance();
$player_ops->process_bustout($tournament_id, $player_id, 47);
```

### 3. Export Results
Generate tournament results in multiple formats:

```php
// Export to TDT format (TD3 compatible)
$tdt_exporter = TDWP_TDT_Exporter::get_instance();
$tdt_file = $tdt_exporter->export_tournament($tournament_id);

// Export to CSV
$export_manager = TDWP_Export_Manager::get_instance();
$csv_file = $export_manager->export_to_csv($tournament_id, 'results');

// Email to all players
$export_manager->email_results_to_players($tournament_id);
```

## Phase 1: Display System (Coming Soon)

### Token-Based Templates
Create custom display screens with dynamic content:

```php
// Create display template with tokens
$template = array(
    'post_title' => 'Main Clock Display',
    'post_content' => '
        <div class="tournament-clock">
            <h1>{{tournament_name}}</h1>
            <div class="current-blind">
                Blinds: {{current_blind}}
            </div>
            <div class="time-remaining">
                {{time_remaining}}
            </div>
            <div class="next-blind">
                Next: {{next_blind}}
            </div>
        </div>
    ',
    'template_type' => 'clock'
);

$template_id = $display_manager->create_template($tournament_id, $template);
```

### Multi-Screen Support
Manage multiple display endpoints:

```php
// Configure display screens
$screens = array(
    array(
        'screen_name' => 'Main Floor Display',
        'endpoint_url' => '/display/main-floor',
        'template_id' => $clock_template_id,
        'screen_type' => 'clock'
    ),
    array(
        'screen_name' => 'Bar Area Display',
        'endpoint_url' => '/display/bar-area',
        'template_id' => $rankings_template_id,
        'screen_type' => 'rankings'
    )
);

foreach ($screens as $screen) {
    $display_manager->create_screen($tournament_id, $screen);
}
```

## Phase 2: Event Notifications (Coming Soon)

### Sound Library
Configure tournament event sounds:

```php
// Upload sound effects
$sound_manager = TDWP_Sound_Manager::get_instance();
$sound_manager->upload_sound(
    'level-up.mp3',
    'Level Change',
    ['level_change', 'next_level'],
    0.8 // volume level
);
```

### Event Queue
Set up automated notifications:

```php
// Queue tournament events
$event_queue = TDWP_Event_Queue::get_instance();
$event_queue->queue_event($tournament_id, array(
    'event_type' => 'level_change',
    'event_data' => array(
        'old_level' => 3,
        'new_level' => 4,
        'blinds' => '200/400'
    ),
    'notification_types' => ['sound', 'display'],
    'priority' => 'medium'
));
```

## Phase 3: Player Features (Coming Soon)

### Player Photos
Upload and manage player photos:

```php
// Upload player photo
$photo_manager = TDWP_Player_Photo_Manager::get_instance();
$photo_id = $photo_manager->upload_photo(
    $player_id,
    $_FILES['player_photo'],
    'profile',
    'Tournament photo'
);
```

### Achievement System
Create player achievements:

```php
// Define achievements
$achievement_system = TDWP_Achievement_System::get_instance();
$achievement_system->create_achievement(array(
    'achievement_key' => 'first_place',
    'achievement_name' => 'First Victory',
    'description' => 'Win your first tournament',
    'points_bonus' => 100,
    'criteria' => array(
        'finish_position' => 1,
        'min_players' => 10
    )
));
```

### QR Code Generation
Generate QR codes for check-ins:

```php
// Generate check-in QR code
$qr_manager = TDWP_QR_Code_Manager::get_instance();
$qr_code = $qr_manager->generate_qr_code(array(
    'qr_type' => 'player_checkin',
    'entity_id' => $player_id,
    'qr_data' => array(
        'player_id' => $player_id,
        'tournament_id' => $tournament_id,
        'checkin_time' => current_time('mysql')
    ),
    'expires_hours' => 4
));
```

## Phase 4: Mobile App Support (Coming Soon)

### REST API
Control tournaments via mobile app:

```bash
# Start tournament
POST /wp-json/poker-tournament/v1/tournaments/123/clock/start
Authorization: Bearer <jwt_token>

# Process bust-out
POST /wp-json/poker-tournament/v1/tournaments/123/players/456/bustout
Content-Type: application/json
{
    "finish_position": 47,
    "timestamp": "2025-11-03T20:30:00Z"
}

# Get live status
GET /wp-json/poker-tournament/v1/tournaments/123/status
```

## Development Setup

### 1. Prerequisites
- WordPress 6.0+
- PHP 8.0+
- MySQL 5.7+
- Existing Poker Tournament Import plugin v3.3.0+

### 2. Database Schema
The TD3 integration adds 17 new tables to the existing 12-table foundation:

```sql
-- Display System Tables
wp_poker_display_templates
wp_poker_display_layouts
wp_poker_display_screens
wp_poker_display_tokens

-- Event Notification Tables
wp_poker_event_queue
wp_poker_notification_preferences
wp_poker_sound_library

-- Player Feature Tables
wp_poker_player_photos
wp_poker_achievements
wp_poker_player_achievements
wp_poker_leagues
wp_poker_league_standings
wp_poker_league_tournaments

-- QR Code Tables
wp_poker_qr_codes
wp_poker_qr_scans

-- Multi-Screen Tables
wp_poker_screen_status
```

### 3. API Endpoints
New REST API endpoints for TD3 features:

```yaml
# Display System
GET /displays/templates
POST /displays/templates
GET /displays/screens
POST /displays/screens

# Event Notifications
GET /events/queue
POST /events/queue
GET /events/preferences

# Player Features
GET /players/photos
POST /players/photos
GET /leagues
POST /leagues

# QR Codes
POST /qr-codes
POST /qr-codes/{id}/scan
```

## Architecture Overview

### Existing Foundation
- **Tournament Management**: Complete CRUD operations with templates
- **Live Operations**: Real-time clock, table management, player operations
- **Database Schema**: 12 tables with proper indexing and relationships
- **Security**: Nonces, capabilities, prepared statements
- **Performance**: Caching, async processing, memory efficiency

### TD3 Integration Additions
- **Display System**: Token templates, multi-screen endpoints, layout builder
- **Event System**: Queue processing, sound library, notification preferences
- **Player Features**: Photo management, achievements, leagues, QR codes
- **Mobile API**: REST endpoints for external app control
- **Extended Database**: 17 new tables maintaining WordPress conventions

## Implementation Timeline

### Phase 1: Display System (3-4 weeks)
- Token template engine extending formula system
- Basic layout builder with drag-and-drop
- Multi-screen endpoint URLs
- Integration with existing tournament data

### Phase 2: Event Notifications (2-3 weeks)
- Event trigger system building on existing event logging
- Sound library with browser optimization
- Enhanced email notification templates
- Priority event queue management

### Phase 3: Player Features (3-4 weeks)
- Player photo upload and management
- Email receipt templates and badge generation
- QR code system for check-ins
- Enhanced league management

### Phase 4: Advanced Features (2-3 weeks)
- REST API for mobile app control
- Hand timer and pace analytics
- Advanced reporting dashboards
- Performance optimizations for 1000+ players

## Migration Strategy

### For Existing Installations
1. **Zero Downtime**: All new features are additive
2. **Backward Compatibility**: Existing tournaments and data remain functional
3. **Database Migration**: New tables added without modifying existing structure
4. **API Compatibility**: Existing endpoints continue to work

### For New Installations
1. **Complete TD3 Features**: All phases available from day one
2. **Professional Templates**: Pre-built display layouts included
3. **Sample Data**: Demo tournaments with all features enabled
4. **Documentation**: Comprehensive setup and usage guides

## Getting Help

### Documentation
- **Research Report**: `/specs/001-td3-integration/research.md`
- **Data Model**: `/specs/001-td3-integration/data-model.md`
- **API Contracts**: `/specs/001-td3-integration/contracts/api.yaml`

### Support Channels
- **GitHub Issues**: Report bugs and request features
- **Documentation**: Check existing knowledge base
- **Community**: Join discussions about TD3 integration

## Next Steps

1. **Stay Updated**: Follow development progress on the `001-td3-integration` branch
2. **Test Current Features**: Explore the existing tournament management capabilities
3. **Plan Migration**: Prepare your tournament data for enhanced features
4. **Provide Feedback**: Share your TD3 feature requirements and use cases

The TD3 integration will transform your WordPress site into a full-featured tournament management system while maintaining the reliability and ease of use you expect from WordPress plugins.