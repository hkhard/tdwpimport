# TD3 Integration - Complete Database Schema Design

## Executive Summary

This document provides the complete database schema design for integrating TD3 (Tournament Director 3) features into the existing WordPress poker tournament plugin. The design supports 1000+ player tournaments with real-time updates, multi-screen displays, player engagement features, and comprehensive tracking capabilities.

## Architecture Overview

### Design Principles
- **WordPress Compatibility**: Full integration with WordPress database conventions
- **Scalability**: Optimized for large-scale tournaments (1000+ players)
- **Performance**: Strategic indexing and caching strategies
- **Data Integrity**: Proper relationships and constraints
- **Migration Ready**: Safe upgrade path for existing installations

### Integration Strategy
- **Phase 1**: Display system templates and layouts
- **Phase 2**: Event notifications and sound library
- **Phase 3**: Player engagement (photos, achievements, leagues)
- **Phase 4**: QR code generation and tracking
- **Phase 5**: Multi-screen endpoint management

## Database Schema Components

### 1. Display System (4 Tables)

#### Core Display Tables
- **`wp_tdwp_display_templates`**: Reusable display template configurations
- **`wp_tdwp_display_layouts`**: Layout configurations for different screen types
- **`wp_tdwp_display_screens`**: Active screen instances and assignments
- **`wp_tdwp_screen_configurations`**: Runtime configuration for active screens

#### Key Features
- Template-based display system with CSS variables
- Responsive layouts for different screen types
- Real-time configuration updates
- Multi-screen support with endpoint management

### 2. Event Notifications (3 Tables)

#### Notification Tables
- **`wp_tdwp_event_queue`**: Event queue for tournament notifications
- **`wp_tdwp_notification_preferences`**: User notification preferences
- **`wp_tdwp_sound_library`**: Sound files for event notifications

#### Key Features
- Priority-based event processing
- Custom notification preferences per user
- Sound library with categorization
- Retry mechanism for failed notifications

### 3. Player Engagement (6 Tables)

#### Engagement Tables
- **`wp_tdwp_player_photos`**: Player photo references and metadata
- **`wp_tdwp_achievements`**: Achievement definitions and criteria
- **`wp_tdwp_player_achievements`**: Earned achievements by players
- **`wp_tdwp_leagues`**: League definitions and settings
- **`wp_tdwp_seasons`**: Season definitions within leagues
- **`wp_tdwp_league_memberships`**: Player memberships in leagues and seasons

#### Key Features
- Player photo management with approval workflow
- Achievement system with configurable criteria
- League and season management
- Player statistics and rankings

### 4. QR Code System (2 Tables)

#### QR Tables
- **`wp_tdwp_qr_codes`**: Generated QR codes and metadata
- **`wp_tdwp_qr_tracking`**: QR code scan tracking data

#### Key Features
- QR code generation for players, tournaments, registration
- Comprehensive scan tracking with analytics
- Expiry and usage limits
- Geographic and device tracking

### 5. Multi-Screen Endpoints (2 Tables)

#### Endpoint Tables
- **`wp_tdwp_display_endpoints`**: Multi-screen endpoint configurations
- **`wp_tdwp_endpoint_status`**: Real-time status for display endpoints

#### Key Features
- Support for web, mobile, TV, and projector endpoints
- Real-time health monitoring
- API authentication and security
- Performance metrics tracking

## Database Relationships

### Primary Relationships
```
Existing Tables:
├── wp_posts (tournaments, players)
├── wp_users (WordPress users)
└── wp_postmeta (post metadata)

New TD3 Tables:
├── Display System
│   ├── display_templates → display_layouts → display_screens → screen_configurations
│   └── display_screens → display_endpoints → endpoint_status
├── Event Notifications
│   ├── event_queue (linked to tournaments)
│   ├── notification_preferences (linked to users)
│   └── sound_library (used by event_queue)
├── Player Engagement
│   ├── player_photos (linked to player posts)
│   ├── achievements → player_achievements (linked to players/tournaments)
│   └── leagues → seasons → league_memberships (linked to players)
└── QR Codes
    ├── qr_codes (linked to tournaments/players)
    └── qr_tracking (linked to qr_codes)
```

### Foreign Key Relationships
- All `*_id` fields reference appropriate primary keys
- `tournament_id` and `player_id` reference `wp_posts.ID`
- `user_id` and `created_by` reference `wp_users.ID`
- Proper cascade handling for data integrity

## Performance Optimization

### Index Strategy
- **Composite Indexes**: For common query patterns (tournament_id + status, player_id + tournament_id)
- **Time-based Indexes**: All timestamp fields indexed for time-based queries
- **Foreign Key Indexes**: All relationships properly indexed
- **Unique Constraints**: Prevent duplicate data where required

### Scaling Considerations
- **Event Queue**: Batch processing for high-volume events
- **Display Screens**: Optimized for frequent updates
- **QR Tracking**: Efficient storage for high-volume scan data
- **Endpoint Status**: Lightweight updates for real-time monitoring

### Caching Strategy
- **Display Templates**: Cache rendered HTML/CSS combinations
- **Player Photos**: Cache resized thumbnails
- **Achievement Data**: Cache calculated progress
- **League Standings**: Cache computed rankings

## Migration Strategy

### Version Control
- **Database Versioning**: Each schema update increments version
- **Migration Scripts**: Separate functions for each version
- **Rollback Support**: Ability to rollback failed migrations
- **Data Preservation**: Migrations maintain existing data integrity

### Upgrade Path
1. **Create New Tables**: Without affecting existing functionality
2. **Migrate Data**: If needed, transform existing data
3. **Update Application Code**: Use new schema features
4. **Remove Deprecated**: Clean up old structures after successful migration

### Safety Features
- **Transaction Support**: Database transactions for atomic updates
- **Error Handling**: Comprehensive error logging and recovery
- **Validation**: Pre-migration validation checks
- **Backup Recommendations**: Automated backup prompts

## Data Validation Rules

### Input Validation
- **Length Limits**: Enforced at database level
- **Data Types**: Proper types with appropriate precision
- **JSON Validation**: Valid JSON format stored as text
- **URL Validation**: Validated URL format for links

### Business Logic Rules
- **Tournament Status**: Prevent invalid state transitions
- **Player Registration**: Enforce capacity limits
- **Achievement Criteria**: Validate rule configurations
- **QR Code Limits**: Enforce scan limits and expiry

## Security Considerations

### Access Control
- **Role-based Permissions**: WordPress user role integration
- **API Security**: Secure endpoint authentication
- **Data Privacy**: Player photo consent management
- **Audit Trail**: Complete logging of TD3 operations

### Data Protection
- **Encryption**: Sensitive data encrypted at rest
- **Secure File Handling**: Proper upload validation
- **SQL Injection Prevention**: Prepared statements throughout
- **XSS Protection**: Proper output escaping

## File Structure

### Core Files
```
wordpress-plugin/poker-tournament-import/includes/tournament-manager/
├── class-td3-database-schema.php     # TD3 table definitions
├── class-td3-migration.php           # TD3 migration handling
└── class-database-schema.php         # Updated with TD3 integration

docs/
├── td3-database-schema.md            # Complete schema documentation
└── td3-integration-summary.md        # This summary document
```

### Implementation Checklist
- [x] Complete database schema design
- [x] Table creation scripts with proper indexes
- [x] Migration system with version control
- [x] Default data insertion scripts
- [x] Integration with existing schema
- [x] Performance optimization strategy
- [x] Security considerations implementation
- [x] Documentation and relationship diagrams

## Usage Examples

### Creating a Display Template
```php
$template_id = wp_insert_post(array(
    'post_type' => 'display_template',
    'post_title' => 'Main Tournament Display',
    'post_content' => json_encode(array(
        'css_variables' => array(
            'primary_color' => '#0073aa',
            'font_family' => 'Arial, sans-serif'
        ),
        'html_template' => '<!-- Template HTML -->'
    ))
));
```

### Processing Event Notifications
```php
// Queue tournament event
$event_data = array(
    'tournament_id' => $tournament_id,
    'event_type' => 'player_bustout',
    'priority' => 8,
    'notification_data' => array(
        'sound_file' => 'bustout.mp3',
        'message' => 'Player eliminated!'
    )
);

TDWP_Event_Manager::queue_event($event_data);
```

### Generating QR Codes
```php
$qr_code = TDWP_QR_Generator::create(array(
    'type' => 'player_registration',
    'target_id' => $player_id,
    'expiry_date' => '+30 days',
    'max_scans' => 100
));
```

## Next Steps

### Immediate Actions
1. **Review Schema**: Validate all table definitions and relationships
2. **Test Migrations**: Run migration scripts on development environment
3. **Performance Testing**: Validate performance with large datasets
4. **Security Review**: Conduct security assessment of new features

### Development Priorities
1. **Display System**: Implement core template and layout functionality
2. **Event Notifications**: Build event queue and notification system
3. **Player Features**: Develop photo and achievement systems
4. **QR Integration**: Implement QR code generation and tracking
5. **Multi-screen**: Deploy endpoint management system

### Testing Strategy
- **Unit Tests**: Individual table and function testing
- **Integration Tests**: Cross-feature interaction testing
- **Load Testing**: Performance testing with 1000+ players
- **Migration Testing**: Upgrade path validation
- **Security Testing**: Penetration testing and vulnerability assessment

## Conclusion

The TD3 integration database schema provides a comprehensive foundation for advanced tournament management features while maintaining compatibility with the existing WordPress poker tournament plugin. The modular design allows for phased implementation and supports the scalability requirements of large-scale poker tournaments.

The schema design prioritizes performance, security, and maintainability while providing the flexibility needed for future feature expansion. With proper implementation and testing, this system will support professional-grade tournament management capabilities.

**Total Tables Created**: 17 new TD3 tables
**Integration Points**: 5 major feature areas
**Performance Target**: Support for 1000+ player tournaments
**Migration Safety**: Zero-downtime upgrade path for existing installations