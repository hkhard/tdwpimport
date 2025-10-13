# Poker Tournament Import Plugin - Comprehensive Project Documentation

## Table of Contents

1. [Project Overview](#project-overview)
2. [Technical Architecture](#technical-architecture)
3. [Data Models](#data-models)
4. [Core Components](#core-components)
5. [Development Guidelines](#development-guidelines)
6. [Security Considerations](#security-considerations)
7. [Performance Optimization](#performance-optimization)
8. [Testing Strategy](#testing-strategy)
9. [Deployment & Release](#deployment--release)
10. [Maintenance & Support](#maintenance--support)

## Project Overview

### Mission Statement

To provide poker tournament organizers with a seamless WordPress plugin solution that automates the import and display of tournament results from Tournament Director software, reducing manual data entry by 90% while maintaining 100% data accuracy.

### Business Objectives

- **Efficiency**: Eliminate manual data entry through automated .tdt file processing
- **Accuracy**: Ensure 100% data integrity through validated parsing algorithms
- **Accessibility**: Provide responsive, mobile-friendly tournament displays
- **Scalability**: Support venues with 10,000+ tournaments per year
- **SEO**: Optimize tournament content for search engine visibility

### Target Audience

- **Primary**: Tournament directors and poker venue managers
- **Secondary**: Poker players seeking tournament results
- **Tertiary**: WordPress developers extending plugin functionality

### Success Metrics

- **Adoption**: 1,000+ active installations within first year
- **Satisfaction**: 4.5+ star rating on WordPress.org
- **Performance**: <2 second page load times for tournament displays
- **Reliability**: 99.9% uptime for import functionality

## Technical Architecture

### System Overview

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   .tdt Files    │───▶│   Import Engine   │───▶│  WordPress DB   │
│   (Upload)      │    │   (Parser)        │    │   (Storage)     │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                                      │
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Frontend      │◀───│  Template Engine │◀───│   Query Layer   │
│   (Display)     │    │   (Shortcodes)   │    │   (WP_Query)    │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### Core Technologies

- **Backend**: PHP 8.0+ with WordPress 6.0+ integration
- **Database**: MySQL 5.7+ with optimized indexing strategy
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **File Processing**: Streaming JSON parser for large files
- **Caching**: WordPress transients and object caching
- **Security**: WordPress nonce system and data sanitization

### Plugin Structure

```
poker-tournament-import/
├── poker-tournament-import.php          # Main plugin bootstrap
├── includes/                            # Core functionality
│   ├── class-parser.php                 # TDT file parsing engine
│   ├── class-post-types.php             # Custom post type registration
│   ├── class-taxonomies.php             # Custom taxonomy registration
│   ├── class-admin.php                  # Administrative interface
│   ├── class-shortcodes.php             # Frontend display system
│   ├── class-templates.php              # Template rendering engine
│   ├── class-import.php                 # Import workflow manager
│   ├── class-statistics.php             # Statistics calculation engine
│   └── class-hooks.php                  # WordPress integration hooks
├── admin/                               # Administrative components
│   ├── pages/
│   │   ├── import-wizard.php            # File import interface
│   │   ├── series-management.php        # Tournament series dashboard
│   │   ├── player-management.php        # Player statistics dashboard
│   │   └── settings.php                 # Plugin configuration
│   └── assets/
│       ├── css/admin.css                # Admin interface styling
│       └── js/admin.js                  # Admin functionality
├── templates/                           # Frontend display templates
│   ├── parts/
│   │   ├── tournament-header.php        # Tournament information display
│   │   ├── tournament-results.php       # Player results table
│   │   ├── tournament-statistics.php    # Tournament statistics
│   │   ├── player-profile.php           # Individual player information
│   │   └── series-overview.php          # Series summary display
│   ├── tournament-single.php            # Single tournament template
│   ├── tournament-archive.php           # Tournament listing template
│   ├── series-single.php                # Series detail template
│   └── player-single.php                # Player profile template
├── assets/                              # Public assets
│   ├── css/
│   │   ├── frontend.css                 # Frontend styling
│   │   ├── tournament.css               # Tournament-specific styles
│   │   └── responsive.css               # Mobile-responsive styles
│   └── js/
│       ├── tournament.js                # Interactive tournament features
│       ├── charts.js                    # Statistics visualization
│       └── search.js                    # Tournament search functionality
├── languages/                           # Internationalization
│   ├── poker-tournament-import.pot      # Translation template
│   └── README.md                        # Translation guidelines
└── tests/                               # Testing suite
    ├── unit/
    │   ├── test-parser.php              # Parser unit tests
    │   ├── test-import.php              # Import process tests
    │   └── test-statistics.php          # Statistics calculation tests
    ├── integration/
    │   ├── test-workflow.php            # End-to-end workflow tests
    │   └── test-shortcodes.php          # Shortcode functionality tests
    └── fixtures/
        ├── sample-tournaments.tdt       # Sample tournament files
        └── expected-outputs.php         # Expected test results
```

## Data Models

### Custom Post Types

#### Tournament (`tournament`)

```php
$tournament_data = [
    'post_title' => 'Weekly $50 No-Limit Hold\'em',
    'post_content' => '', // Auto-generated from template
    'post_type' => 'tournament',
    'post_status' => 'publish',
    'meta_input' => [
        'tournament_date' => '2024-01-15',
        'tournament_time' => '19:00:00',
        'buy_in_amount' => 50.00,
        'entry_fee' => 10.00,
        'total_prize_pool' => 1000.00,
        'number_of_players' => 20,
        'tournament_type' => 'holdem',
        'tournament_format' => 'no-limit',
        'tournament_location' => 'Casino XYZ',
        'tournament_series_id' => 'uuid-string',
        'season_id' => 123,
        'tournament_data' => [ // Serialized TDT data
            'players' => [...],
            'results' => [...],
            'structure' => [...]
        ]
    ]
];
```

#### Tournament Series (`tournament_series`)

```php
$series_data = [
    'post_title' => 'Summer Poker Championship 2024',
    'post_content' => 'Complete summer tournament series...',
    'post_type' => 'tournament_series',
    'meta_input' => [
        'series_start_date' => '2024-06-01',
        'series_end_date' => '2024-08-31',
        'series_uuid' => 'unique-identifier',
        'total_tournaments' => 12,
        'total_prize_pool' => 25000.00,
        'season_id' => 123
    ]
];
```

#### Tournament Season (`tournament_season`)

```php
$season_data = [
    'post_title' => '2024 Season',
    'post_type' => 'tournament_season',
    'meta_input' => [
        'season_year' => 2024,
        'season_start_date' => '2024-01-01',
        'season_end_date' => '2024-12-31',
        'total_tournaments' => 156,
        'total_players' => 2340,
        'total_prize_pool' => 125000.00
    ]
];
```

#### Player (`player`)

```php
$player_data = [
    'post_title' => 'John Doe',
    'post_type' => 'player',
    'meta_input' => [
        'player_name' => 'John Doe',
        'total_tournaments' => 45,
        'total_winnings' => 3500.00,
        'average_finish' => 12.5,
        'best_finish' => 1,
        'player_statistics' => [
            'cashes' => 8,
            'final_tables' => 3,
            'wins' => 1,
            'points_total' => 1250.5
        ]
    ]
];
```

### Custom Taxonomies

#### Tournament Type (`tournament_type`)

```php
$tournament_types = [
    'holdem' => 'Texas Hold\'em',
    'omaha' => 'Omaha',
    'omaha-hl' => 'Omaha Hi-Lo',
    'stud' => 'Seven Card Stud',
    'stud-hl' => 'Seven Card Stud Hi-Lo',
    'razz' => 'Razz',
    'draw' => 'Five Card Draw',
    'mixed' => 'Mixed Games'
];
```

#### Tournament Format (`tournament_format`)

```php
$tournament_formats = [
    'no-limit' => 'No Limit',
    'pot-limit' => 'Pot Limit',
    'fixed-limit' => 'Fixed Limit',
    'spread-limit' => 'Spread Limit'
];
```

#### Tournament Category (`tournament_category`)

```php
$tournament_categories = [
    'live' => 'Live Tournament',
    'online' => 'Online Tournament',
    'charity' => 'Charity Event',
    'league' => 'League Play',
    'satellite' => 'Satellite Tournament',
    'main-event' => 'Main Event'
];
```

### Database Schema

#### Core Tables (WordPress)

```sql
-- Tournament posts
wp_posts WHERE post_type = 'tournament'

-- Series posts
wp_posts WHERE post_type = 'tournament_series'

-- Season posts
wp_posts WHERE post_type = 'tournament_season'

-- Player posts
wp_posts WHERE post_type = 'player'

-- Tournament metadata
wp_postmeta WHERE post_id IN (SELECT ID FROM wp_posts WHERE post_type = 'tournament')

-- Term relationships
wp_term_relationships WHERE object_id IN (SELECT ID FROM wp_posts WHERE post_type = 'tournament')
```

#### Custom Tables (Optional for large datasets)

```sql
-- Tournament results cache
CREATE TABLE wp_tournament_results (
    tournament_id bigint(20) unsigned NOT NULL,
    player_id bigint(20) unsigned NOT NULL,
    finish_position int(11) NOT NULL,
    winnings decimal(10,2) DEFAULT NULL,
    points decimal(10,4) DEFAULT NULL,
    eliminated_by bigint(20) unsigned DEFAULT NULL,
    PRIMARY KEY (tournament_id, player_id),
    KEY idx_player_performance (player_id, finish_position),
    KEY idx_tournament_results (tournament_id, finish_position)
);

-- Player statistics cache
CREATE TABLE wp_player_statistics (
    player_id bigint(20) unsigned NOT NULL PRIMARY KEY,
    total_tournaments int(11) DEFAULT 0,
    total_winnings decimal(10,2) DEFAULT 0.00,
    average_finish decimal(8,2) DEFAULT 0.00,
    best_finish int(11) DEFAULT 999,
    total_points decimal(10,4) DEFAULT 0.00,
    last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_performance (total_winnings, total_points)
);
```

## Core Components

### 1. TDT Parser Engine (`class-parser.php`)

#### Overview
The parser handles Tournament Director's JavaScript serialization format, extracting tournament data, player information, and results.

#### Key Features
- **Streaming Parser**: Memory-efficient processing of large files
- **Error Recovery**: Graceful handling of malformed data
- **Data Validation**: Comprehensive input validation and sanitization
- **Points Calculation**: Exact replication of Tournament Director formulas

#### Core Methods

```php
class TDT_Parser {
    /**
     * Parse .tdt file and extract tournament data
     */
    public function parse_file(string $file_path): array {
        // Implementation for file parsing
    }

    /**
     * Calculate tournament points using Tournament Director formula
     */
    public function calculate_points(array $tournament_data, int $finish_position): float {
        $n = $tournament_data['total_players'];
        $r = $finish_position;
        $avgBC = $tournament_data['average_buy_in'];
        $numberofHits = $tournament_data['eliminations'][$finish_position] ?? 0;

        return 10 * (sqrt($n) / sqrt($r)) * (1 + log($avgBC + 0.25)) + ($numberofHits * 10);
    }

    /**
     * Validate parsed data integrity
     */
    public function validate_data(array $parsed_data): bool {
        // Data validation implementation
    }
}
```

#### Error Handling

```php
try {
    $tournament_data = $parser->parse_file($file_path);

    if (!$parser->validate_data($tournament_data)) {
        throw new Parser_Exception('Data validation failed');
    }

    return $tournament_data;

} catch (Parser_Exception $e) {
    // Log error and provide user feedback
    error_log("TDT Parser Error: " . $e->getMessage());
    return new WP_Error('parser_error', $e->getMessage());
}
```

### 2. Import Workflow Manager (`class-import.php`)

#### Import Process Flow

```php
class Tournament_Import {
    public function process_import(array $file_data): WP_Post|WP_Error {
        // 1. File validation
        if (!$this->validate_file($file_data)) {
            return new WP_Error('invalid_file', 'Invalid .tdt file format');
        }

        // 2. Parse tournament data
        $parser = new TDT_Parser();
        $tournament_data = $parser->parse_file($file_data['tmp_name']);

        // 3. Check for duplicates
        if ($this->is_duplicate_tournament($tournament_data)) {
            return new WP_Error('duplicate', 'Tournament already exists');
        }

        // 4. Create/update series and season
        $series_id = $this->ensure_series_exists($tournament_data);
        $season_id = $this->ensure_season_exists($tournament_data);

        // 5. Create tournament post
        $tournament_id = $this->create_tournament_post($tournament_data, $series_id, $season_id);

        // 6. Create/update player posts
        $this->process_players($tournament_data['players'], $tournament_id);

        // 7. Update statistics
        $this->update_tournament_statistics($tournament_id);
        $this->update_player_statistics($tournament_data['players']);

        return get_post($tournament_id);
    }
}
```

### 3. Shortcode System (`class-shortcodes.php`)

#### Available Shortcodes

```php
class Tournament_Shortcodes {
    /**
     * Display single tournament
     * [tournament_results id="123"]
     */
    public function tournament_results($atts): string {
        $atts = shortcode_atts([
            'id' => 0,
            'show_details' => 'true',
            'show_players' => 'true',
            'show_statistics' => 'true'
        ], $atts);

        return $this->render_tournament_template($atts['id'], $atts);
    }

    /**
     * Display tournament series
     * [tournament_series id="456"]
     */
    public function tournament_series($atts): string {
        $atts = shortcode_atts([
            'id' => 0,
            'show_standings' => 'true',
            'show_schedule' => 'true'
        ], $atts);

        return $this->render_series_template($atts['id'], $atts);
    }

    /**
     * Display player profile
     * [player_profile name="John Doe"]
     */
    public function player_profile($atts): string {
        $atts = shortcode_atts([
            'name' => '',
            'id' => 0,
            'show_statistics' => 'true',
            'show_history' => 'true'
        ], $atts);

        return $this->render_player_template($atts, $atts);
    }
}
```

### 4. Template Engine (`class-templates.php`)

#### Template Rendering System

```php
class Tournament_Templates {
    /**
     * Render tournament results with caching
     */
    public function render_tournament(int $tournament_id, array $options = []): string {
        $cache_key = "tournament_{$tournament_id}_" . md5(serialize($options));

        if ($cached_output = get_transient($cache_key)) {
            return $cached_output;
        }

        $tournament = $this->get_tournament_data($tournament_id);
        $output = $this->load_template('tournament-single', [
            'tournament' => $tournament,
            'options' => $options
        ]);

        set_transient($cache_key, $output, HOUR_IN_SECONDS);
        return $output;
    }

    /**
     * Load and process template file
     */
    private function load_template(string $template_name, array $data): string {
        $template_path = $this->locate_template($template_name);

        if (!$template_path) {
            return "<!-- Template {$template_name} not found -->";
        }

        ob_start();
        extract($data);
        include $template_path;
        return ob_get_clean();
    }
}
```

### 5. Statistics Engine (`class-statistics.php`)

#### Performance Calculations

```php
class Tournament_Statistics {
    /**
     * Calculate comprehensive player statistics
     */
    public function calculate_player_stats(int $player_id): array {
        global $wpdb;

        $tournaments = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, pm.meta_key, pm.meta_value
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'tournament'
            AND p.post_status = 'publish'
            AND pm.meta_value LIKE %s
        ", "%{$player_id}%"));

        $stats = [
            'total_tournaments' => 0,
            'total_winnings' => 0.00,
            'average_finish' => 0.00,
            'best_finish' => 999,
            'cashes' => 0,
            'final_tables' => 0,
            'wins' => 0,
            'total_points' => 0.00
        ];

        foreach ($tournaments as $tournament) {
            $stats['total_tournaments']++;
            // Calculate statistics based on tournament results
        }

        return $stats;
    }

    /**
     * Update player statistics cache
     */
    public function update_player_cache(int $player_id): void {
        $stats = $this->calculate_player_stats($player_id);

        update_post_meta($player_id, 'player_statistics', $stats);
        update_post_meta($player_id, 'statistics_updated', current_time('mysql'));

        // Update custom statistics table if enabled
        if ($this->use_custom_tables()) {
            $this->update_statistics_table($player_id, $stats);
        }
    }
}
```

## Development Guidelines

### Coding Standards

#### PHP Standards (WordPress)

```php
<?php
/**
 * Plugin Name: Poker Tournament Import
 * Plugin URI: https://example.com/poker-tournament-import
 * Description: Import and display poker tournament results from Tournament Director files
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: poker-tournament-import
 * Domain Path: /languages
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Use strict typing for PHP 7.4+
declare(strict_types=1);

// Namespace organization
namespace PokerTournamentImport;

// Class naming convention
class Tournament_Importer {

    // Method naming: snake_case
    public function import_tournament_data(array $tournament_data): WP_Post|WP_Error {
        // Implementation
    }

    // Constant naming: UPPER_CASE
    private const MAX_FILE_SIZE = 10485760; // 10MB

    // Variable naming: snake_case
    private $tournament_settings = [];
}
```

#### JavaScript Standards

```javascript
/**
 * Tournament display functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    // Use const/let instead of var
    const tournamentElements = document.querySelectorAll('.tournament-results');

    // Function naming: camelCase
    function initializeTournamentDisplays() {
        tournamentElements.forEach(element => {
            // Implementation
        });
    }

    // Event listeners with proper error handling
    tournamentElements.forEach(element => {
        element.addEventListener('click', function(event) {
            try {
                handleTournamentClick(event);
            } catch (error) {
                console.error('Tournament click error:', error);
            }
        });
    });
});
```

#### CSS Standards

```css
/* BEM methodology for CSS naming */
.tournament-results {
    /* Block styles */
}

.tournament-results__header {
    /* Element styles */
}

.tournament-results__title--featured {
    /* Modifier styles */
}

.tournament-results--loading {
    /* State modifier */
}

/* Mobile-first responsive design */
.tournament-results {
    width: 100%;
    max-width: none;
}

@media (min-width: 768px) {
    .tournament-results {
        max-width: 1200px;
        margin: 0 auto;
    }
}

/* CSS custom properties for theming */
:root {
    --tournament-primary-color: #2563eb;
    --tournament-secondary-color: #64748b;
    --tournament-border-color: #e5e7eb;
    --tournament-background-color: #ffffff;
}
```

### Security Guidelines

#### Input Validation

```php
class Security_Helper {
    /**
     * Sanitize and validate tournament data
     */
    public function sanitize_tournament_data(array $raw_data): array {
        return [
            'tournament_name' => sanitize_text_field($raw_data['name'] ?? ''),
            'buy_in_amount' => floatval($raw_data['buy_in'] ?? 0),
            'tournament_date' => $this->validate_date($raw_data['date'] ?? ''),
            'player_names' => array_map('sanitize_text_field', $raw_data['players'] ?? []),
            'results_data' => $this->sanitize_results($raw_data['results'] ?? [])
        ];
    }

    /**
     * Validate date format and range
     */
    private function validate_date(string $date): string {
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);

        if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
            return '';
        }

        // Ensure date is within reasonable range
        $min_date = new DateTime('2000-01-01');
        $max_date = new DateTime('+1 year');

        if ($date_obj < $min_date || $date_obj > $max_date) {
            return '';
        }

        return $date;
    }
}
```

#### Capability Checks

```php
class Capability_Checker {
    /**
     * Verify user can import tournaments
     */
    public function can_import_tournaments(): bool {
        return current_user_can('edit_posts') &&
               current_user_can('upload_files') &&
               $this->verify_nonce();
    }

    /**
     * Verify nonce for security
     */
    private function verify_nonce(): bool {
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        $action = 'poker_tournament_import';

        return wp_verify_nonce($nonce, $action) !== false;
    }
}
```

#### Database Security

```php
class Database_Helper {
    /**
     * Safe database query with prepared statements
     */
    public function get_tournament_results(int $tournament_id): array {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT p.post_title as player_name,
                   pm.meta_value as finish_position,
                   pm2.meta_value as winnings
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_type = 'player'
            AND pm.meta_key = %s
            AND pm2.meta_key = %s
            ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC
        ", "tournament_{$tournament_id}_finish", "tournament_{$tournament_id}_winnings"));

        return $results ?: [];
    }
}
```

### Performance Guidelines

#### Database Optimization

```php
class Performance_Optimizer {
    /**
     * Efficient tournament query with proper indexing
     */
    public function get_tournaments_with_performance(array $args): array {
        $args = wp_parse_args($args, [
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => []
        ]);

        $query = new WP_Query([
            'post_type' => 'tournament',
            'post_status' => 'publish',
            'posts_per_page' => $args['posts_per_page'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
            'meta_query' => $args['meta_query'],
            'cache_results' => true,
            'update_post_meta_cache' => false, // Only load if needed
            'update_post_term_cache' => false, // Only load if needed
        ]);

        return $query->posts;
    }

    /**
     * Batch process statistics updates
     */
    public function batch_update_statistics(array $player_ids): void {
        foreach (array_chunk($player_ids, 50) as $batch) {
            foreach ($batch as $player_id) {
                $this->update_player_statistics($player_id);
            }

            // Prevent memory issues
            wp_cache_flush();
        }
    }
}
```

#### Caching Strategy

```php
class Cache_Manager {
    /**
     * Smart caching with invalidation
     */
    public function get_cached_tournament_data(int $tournament_id): array {
        $cache_key = "tournament_data_{$tournament_id}";
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        $tournament_data = $this->fetch_tournament_data($tournament_id);

        // Cache for different durations based on tournament age
        $tournament_date = get_post_meta($tournament_id, 'tournament_date', true);
        $cache_duration = $this->calculate_cache_duration($tournament_date);

        set_transient($cache_key, $tournament_data, $cache_duration);

        return $tournament_data;
    }

    /**
     * Calculate appropriate cache duration
     */
    private function calculate_cache_duration(string $tournament_date): int {
        $tournament_time = strtotime($tournament_date);
        $current_time = current_time('timestamp');
        $age_in_days = ($current_time - $tournament_time) / DAY_IN_SECONDS;

        if ($age_in_days > 365) {
            return WEEK_IN_SECONDS; // Old tournaments: 1 week
        } elseif ($age_in_days > 30) {
            return DAY_IN_SECONDS; // Recent tournaments: 1 day
        } else {
            return HOUR_IN_SECONDS; // Very recent: 1 hour
        }
    }
}
```

## Security Considerations

### File Upload Security

```php
class File_Upload_Security {
    /**
     * Comprehensive file validation
     */
    public function validate_tdt_upload(array $file): bool|WP_Error {
        // 1. File extension validation
        $allowed_extensions = ['tdt'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            return new WP_Error('invalid_extension', 'Only .tdt files are allowed');
        }

        // 2. File size validation
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 'File size exceeds 10MB limit');
        }

        // 3. MIME type validation
        $allowed_mime_types = ['text/plain', 'application/octet-stream'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mime_types)) {
            return new WP_Error('invalid_mime_type', 'Invalid file type detected');
        }

        // 4. Content validation
        if (!$this->validate_tdt_content($file['tmp_name'])) {
            return new WP_Error('invalid_content', 'File does not contain valid TDT data');
        }

        return true;
    }

    /**
     * Validate TDT file content structure
     */
    private function validate_tdt_content(string $file_path): bool {
        $content = file_get_contents($file_path);

        // Check for TDT-specific patterns
        $tdt_patterns = [
            '/new Tournament\s*\(/',
            '/Map\.from\s*\(/',
            '/tournamentData\s*:/',
            '/players\s*:/'
        ];

        foreach ($tdt_patterns as $pattern) {
            if (!preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }
}
```

### Data Sanitization

```php
class Data_Sanitizer {
    /**
     * Comprehensive data sanitization
     */
    public function sanitize_parsed_data(array $raw_data): array {
        return [
            'tournament_name' => sanitize_text_field($raw_data['name'] ?? ''),
            'tournament_date' => $this->sanitize_date($raw_data['date'] ?? ''),
            'buy_in_amount' => $this->sanitize_currency($raw_data['buy_in'] ?? 0),
            'players' => array_map([$this, 'sanitize_player_data'], $raw_data['players'] ?? []),
            'results' => $this->sanitize_results($raw_data['results'] ?? []),
            'structure' => $this->sanitize_structure($raw_data['structure'] ?? [])
        ];
    }

    /**
     * Sanitize player information
     */
    private function sanitize_player_data(array $player_data): array {
        return [
            'name' => sanitize_text_field($player_data['name'] ?? ''),
            'id' => intval($player_data['id'] ?? 0),
            'notes' => sanitize_textarea_field($player_data['notes'] ?? '')
        ];
    }

    /**
     * Sanitize currency values
     */
    private function sanitize_currency($value): float {
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);
        $float_val = floatval($cleaned);

        // Ensure reasonable bounds
        return max(0, min($float_val, 1000000));
    }
}
```

### Access Control

```php
class Access_Control {
    /**
     * Role-based access permissions
     */
    public function get_user_permissions(): array {
        $user = wp_get_current_user();

        $permissions = [
            'can_import' => user_can($user, 'edit_posts'),
            'can_manage_series' => user_can($user, 'manage_categories'),
            'can_view_statistics' => user_can($user, 'read'),
            'can_export_data' => user_can($user, 'export'),
            'can_delete_tournaments' => user_can($user, 'delete_posts')
        ];

        // Additional custom capabilities
        if (user_can($user, 'manage_options')) {
            $permissions['can_manage_settings'] = true;
        }

        return $permissions;
    }

    /**
     * Check specific permission
     */
    public function user_can(string $capability): bool {
        $permissions = $this->get_user_permissions();
        return $permissions[$capability] ?? false;
    }
}
```

## Performance Optimization

### Database Optimization

```php
class Database_Optimizer {
    /**
     * Optimized tournament queries
     */
    public function get_tournament_leaderboard(array $args): array {
        global $wpdb;

        $defaults = [
            'season_id' => 0,
            'limit' => 50,
            'order_by' => 'total_points',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        // Use custom table for better performance
        if ($this->use_custom_statistics_table()) {
            return $this->get_leaderboard_from_custom_table($args);
        }

        // Fallback to post meta queries with optimization
        $leaderboard = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID,
                   p.post_title,
                   (SELECT CAST(pm.meta_value AS DECIMAL(10,4))
                    FROM {$wpdb->postmeta} pm
                    WHERE pm.post_id = p.ID
                    AND pm.meta_key = 'total_points'
                    LIMIT 1) as total_points,
                   (SELECT CAST(pm.meta_value AS DECIMAL(10,2))
                    FROM {$wpdb->postmeta} pm
                    WHERE pm.post_id = p.ID
                    AND pm.meta_key = 'total_winnings'
                    LIMIT 1) as total_winnings
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'player'
            AND p.post_status = 'publish'
            ORDER BY total_points {$args['order']}
            LIMIT %d
        ", $args['limit']));

        return $leaderboard;
    }

    /**
     * Bulk database operations
     */
    public function bulk_update_player_statistics(array $updates): void {
        global $wpdb;

        // Prepare bulk update statement
        $values = [];
        $place_holders = [];

        foreach ($updates as $update) {
            $values[] = $update['player_id'];
            $values[] = $update['total_points'];
            $values[] = $update['total_winnings'];
            $values[] = $update['total_tournaments'];
            $place_holders[] = '(%d, %f, %f, %d)';
        }

        $sql = "
            INSERT INTO {$wpdb->prefix}player_statistics
            (player_id, total_points, total_winnings, total_tournaments)
            VALUES " . implode(',', $place_holders) . "
            ON DUPLICATE KEY UPDATE
            total_points = VALUES(total_points),
            total_winnings = VALUES(total_winnings),
            total_tournaments = VALUES(total_tournaments),
            last_updated = NOW()
        ";

        $wpdb->query($wpdb->prepare($sql, $values));
    }
}
```

### Memory Management

```php
class Memory_Manager {
    /**
     * Process large TDT files with memory efficiency
     */
    public function process_large_tdt_file(string $file_path): array {
        $max_memory = ini_get('memory_limit');
        $file_size = filesize($file_path);

        // Check if we need to use streaming processing
        if ($file_size > 5 * 1024 * 1024) { // 5MB threshold
            return $this->process_file_streaming($file_path);
        }

        // Regular processing for smaller files
        return $this->process_file_regular($file_path);
    }

    /**
     * Streaming file processing
     */
    private function process_file_streaming(string $file_path): array {
        $handle = fopen($file_path, 'r');
        $buffer_size = 8192; // 8KB chunks
        $content = '';

        while (!feof($handle)) {
            $chunk = fread($handle, $buffer_size);
            $content .= $chunk;

            // Check memory usage periodically
            if (memory_get_usage() > 128 * 1024 * 1024) { // 128MB limit
                throw new Exception('Memory limit exceeded during file processing');
            }
        }

        fclose($handle);

        return $this->parse_tournament_data($content);
    }
}
```

### Caching Strategy

```php
class Advanced_Cache {
    /**
     * Multi-level caching strategy
     */
    public function get_tournament_data_with_caching(int $tournament_id): array {
        $cache_levels = [
            'object_cache' => $this->get_from_object_cache($tournament_id),
            'transient_cache' => $this->get_from_transient_cache($tournament_id),
            'database_cache' => $this->get_from_database_cache($tournament_id)
        ];

        foreach ($cache_levels as $level => $data) {
            if ($data !== false) {
                // Promote to higher cache levels
                $this->promote_cache_data($tournament_id, $data, $level);
                return $data;
            }
        }

        // No cache hit, fetch from database
        $data = $this->fetch_from_database($tournament_id);
        $this->store_in_all_cache_levels($tournament_id, $data);

        return $data;
    }

    /**
     * Smart cache invalidation
     */
    public function invalidate_related_cache(int $tournament_id): void {
        // Invalidate tournament cache
        $this->clear_cache("tournament_{$tournament_id}");

        // Invalidate related player caches
        $player_ids = $this->get_tournament_player_ids($tournament_id);
        foreach ($player_ids as $player_id) {
            $this->clear_cache("player_{$player_id}");
        }

        // Invalidate series cache
        $series_id = get_post_meta($tournament_id, 'tournament_series_id', true);
        if ($series_id) {
            $this->clear_cache("series_{$series_id}");
        }

        // Invalidate leaderboard caches
        $this->clear_cache('leaderboard_*');
    }
}
```

## Testing Strategy

### Unit Testing

```php
class Test_TDT_Parser extends WP_UnitTestCase {
    private $parser;

    public function setUp(): void {
        parent::setUp();
        $this->parser = new TDT_Parser();
    }

    /**
     * Test basic tournament parsing
     */
    public function test_parse_basic_tournament(): void {
        $test_file = dirname(__FILE__) . '/fixtures/basic-tournament.tdt';
        $result = $this->parser->parse_file($test_file);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('players', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertEquals('Test Tournament', $result['name']);
        $this->assertCount(10, $result['players']);
    }

    /**
     * Test points calculation
     */
    public function test_points_calculation(): void {
        $tournament_data = [
            'total_players' => 50,
            'average_buy_in' => 100.00,
            'eliminations' => [1 => 1, 2 => 1, 3 => 1] // First 3 eliminated 1 player each
        ];

        $first_place_points = $this->parser->calculate_points($tournament_data, 1);
        $last_place_points = $this->parser->calculate_points($tournament_data, 50);

        $this->assertGreaterThan(0, $first_place_points);
        $this->assertGreaterThan($last_place_points, $first_place_points);
    }

    /**
     * Test error handling for malformed files
     */
    public function test_malformed_file_handling(): void {
        $malformed_file = dirname(__FILE__) . '/fixtures/malformed.tdt';

        $this->expectException(Parser_Exception::class);
        $this->parser->parse_file($malformed_file);
    }
}
```

### Integration Testing

```php
class Test_Import_Workflow extends WP_UnitTestCase {
    /**
     * Test complete tournament import workflow
     */
    public function test_complete_import_workflow(): void {
        // Create test user with appropriate capabilities
        $user = $this->factory->user->create_and_get(['role' => 'editor']);
        wp_set_current_user($user->ID);

        // Simulate file upload
        $test_file = dirname(__FILE__) . '/fixtures/complete-tournament.tdt';
        $_FILES['tournament_file'] = [
            'name' => 'test-tournament.tdt',
            'type' => 'text/plain',
            'tmp_name' => $test_file,
            'error' => 0,
            'size' => filesize($test_file)
        ];

        // Process import
        $importer = new Tournament_Import();
        $result = $importer->process_import($_FILES['tournament_file']);

        // Verify results
        $this->assertInstanceOf(WP_Post::class, $result);
        $this->assertEquals('tournament', $result->post_type);

        // Verify player creation
        $players = get_posts([
            'post_type' => 'player',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ]);
        $this->assertGreaterThan(0, count($players));

        // Verify metadata
        $tournament_data = get_post_meta($result->ID, 'tournament_data', true);
        $this->assertIsArray($tournament_data);
        $this->assertArrayHasKey('players', $tournament_data);
    }
}
```

### Performance Testing

```php
class Test_Performance extends WP_UnitTestCase {
    /**
     * Test memory usage with large tournaments
     */
    public function test_large_tournament_memory_usage(): void {
        $memory_before = memory_get_usage();

        // Create large tournament data
        $large_tournament = $this->create_large_tournament_data(500); // 500 players

        $parser = new TDT_Parser();
        $result = $parser->parse_tournament_data($large_tournament);

        $memory_after = memory_get_usage();
        $memory_used = $memory_after - $memory_before;

        // Memory usage should be reasonable (< 50MB for 500 players)
        $this->assertLessThan(50 * 1024 * 1024, $memory_used);
    }

    /**
     * Test database query performance
     */
    public function test_leaderboard_query_performance(): void {
        // Create test data
        $this->create_test_leaderboard_data(1000); // 1000 players

        $start_time = microtime(true);

        $optimizer = new Database_Optimizer();
        $leaderboard = $optimizer->get_tournament_leaderboard(['limit' => 100]);

        $end_time = microtime(true);
        $query_time = $end_time - $start_time;

        // Query should complete within reasonable time (< 1 second)
        $this->assertLessThan(1.0, $query_time);
        $this->assertCount(100, $leaderboard);
    }
}
```

### Automated Testing Setup

```json
{
    "scripts": {
        "test": "phpunit",
        "test-unit": "phpunit tests/unit",
        "test-integration": "phpunit tests/integration",
        "test-performance": "phpunit tests/performance",
        "test-coverage": "phpunit --coverage-html coverage",
        "test-watch": "phpunit-watcher watch"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.5",
        "brain/monkey": "^2.6",
        "wp-phpunit/wp-phpunit": "^6.1"
    }
}
```

## Deployment & Release

### Build Process

```bash
#!/bin/bash
# build.sh - Plugin build script

set -e

echo "Building Poker Tournament Import Plugin..."

# 1. Validate code quality
echo "Running code quality checks..."
composer run phpcs
composer run phpstan

# 2. Run tests
echo "Running test suite..."
npm test

# 3. Minify assets
echo "Minifying assets..."
npm run build

# 4. Create distribution package
echo "Creating distribution package..."
DIST_DIR="dist/poker-tournament-import"
rm -rf $DIST_DIR
mkdir -p $DIST_DIR

# Copy plugin files
rsync -av --exclude-from=.distignore \
    wordpress-plugin/poker-tournament-import/ \
    $DIST_DIR/

# 5. Generate version info
VERSION=$(grep -o "Version: [0-9.]*" $DIST_DIR/poker-tournament-import.php | cut -d' ' -f2)
echo "Building version $VERSION"

# 6. Create zip file
cd dist
zip -r "poker-tournament-import-v$VERSION.zip" poker-tournament-import/
cd ..

echo "Build complete: dist/poker-tournament-import-v$VERSION.zip"

# 7. Update version in README
sed -i "s/### Version [0-9.]*/### Version $VERSION/" README.md
```

### Version Management

```php
class Version_Manager {
    private const PLUGIN_VERSION = '1.0.0';
    private const DB_VERSION = '1.0.0';

    /**
     * Check if database update is needed
     */
    public function needs_database_update(): bool {
        $installed_version = get_option('poker_tournament_db_version', '0.0.0');
        return version_compare($installed_version, self::DB_VERSION, '<');
    }

    /**
     * Perform database migrations
     */
    public function update_database(): void {
        $current_version = get_option('poker_tournament_db_version', '0.0.0');

        if (version_compare($current_version, '1.0.0', '<')) {
            $this->migrate_to_1_0_0();
        }

        if (version_compare($current_version, '1.1.0', '<')) {
            $this->migrate_to_1_1_0();
        }

        update_option('poker_tournament_db_version', self::DB_VERSION);
    }

    /**
     * Migration to version 1.0.0
     */
    private function migrate_to_1_0_0(): void {
        // Create custom tables
        $this->create_statistics_table();

        // Update existing data structure
        $this->migrate_legacy_data();

        // Flush caches
        wp_cache_flush();
    }
}
```

### Continuous Integration

```yaml
# .github/workflows/ci.yml
name: Continuous Integration

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php-version: ['7.4', '8.0', '8.1']
        wordpress-version: ['5.9', '6.0', '6.1']

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: mbstring, xml, mysql
        coverage: xdebug

    - name: Install dependencies
      run: |
        composer install --no-progress --no-suggest --prefer-dist
        npm ci

    - name: Run code quality checks
      run: |
        composer run phpcs
        composer run phpstan

    - name: Run tests
      run: |
        npm run test-coverage

    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage/coverage.xml

    - name: Build plugin
      run: |
        chmod +x build.sh
        ./build.sh

    - name: Upload build artifacts
      uses: actions/upload-artifact@v3
      with:
        name: plugin-build-${{ matrix.php-version }}
        path: dist/*.zip
```

### Release Process

```bash
#!/bin/bash
# release.sh - Automated release script

set -e

if [ -z "$1" ]; then
    echo "Usage: ./release.sh [version]"
    exit 1
fi

VERSION=$1

echo "Releasing version $VERSION..."

# 1. Update version numbers
echo "Updating version numbers..."
sed -i "s/Version: [0-9.]*/Version: $VERSION/" poker-tournament-import.php
sed -i "s/const PLUGIN_VERSION = '[0-9.]*/const PLUGIN_VERSION = '$VERSION'/" includes/class-version.php

# 2. Update changelog
echo "Updating changelog..."
git log --oneline $(git describe --tags --abbrev=0)..HEAD >> CHANGELOG.md

# 3. Run full test suite
echo "Running tests..."
npm test

# 4. Build distribution
echo "Building distribution..."
./build.sh

# 5. Create git tag
echo "Creating git tag..."
git add .
git commit -m "Release version $VERSION"
git tag -a "v$VERSION" -m "Release version $VERSION"

# 6. Push to repository
echo "Pushing to repository..."
git push origin main
git push origin "v$VERSION"

# 7. Create GitHub release
echo "Creating GitHub release..."
gh release create "v$VERSION" \
    "dist/poker-tournament-import-v$VERSION.zip" \
    --title "Version $VERSION" \
    --notes "$(cat CHANGELOG.md | sed -n "/^## \[$VERSION\]/,/^## \[/p" | head -n -2)"

echo "Release $VERSION completed successfully!"
```

## Maintenance & Support

### Monitoring and Logging

```php
class Plugin_Monitor {
    /**
     * Log import events for monitoring
     */
    public function log_import_event(int $tournament_id, string $event_type, array $details): void {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'tournament_id' => $tournament_id,
            'event_type' => $event_type,
            'user_id' => get_current_user_id(),
            'details' => $details,
            'memory_usage' => memory_get_usage(true),
            'execution_time' => $details['execution_time'] ?? 0
        ];

        // Log to WordPress debug log
        error_log("Poker Tournament Import: " . json_encode($log_entry));

        // Store in custom log table for analysis
        $this->store_log_entry($log_entry);
    }

    /**
     * Get plugin performance metrics
     */
    public function get_performance_metrics(): array {
        global $wpdb;

        $metrics = [
            'total_tournaments' => wp_count_posts('tournament')->publish,
            'total_imports_today' => $this->get_import_count_today(),
            'average_import_time' => $this->get_average_import_time(),
            'error_rate' => $this->get_error_rate(),
            'memory_usage' => memory_get_usage(true),
            'database_queries' => get_num_queries()
        ];

        return $metrics;
    }
}
```

### Health Check Integration

```php
class Health_Check {
    /**
     * WordPress health check integration
     */
    public function add_health_checks(): void {
        add_filter('site_status_tests', [$this, 'register_health_checks']);
    }

    /**
     * Register custom health checks
     */
    public function register_health_checks(array $tests): array {
        $tests['direct']['poker_tournament_plugin'] = [
            'label' => __('Poker Tournament Plugin', 'poker-tournament-import'),
            'test' => [$this, 'check_plugin_health']
        ];

        return $tests;
    }

    /**
     * Perform plugin health check
     */
    public function check_plugin_health(): array {
        $result = [
            'label' => __('Poker Tournament Plugin Health', 'poker-tournament-import'),
            'status' => 'good',
            'badge' => [
                'label' => __('Tournament Import', 'poker-tournament-import'),
                'color' => 'blue'
            ],
            'description' => __('Plugin is functioning correctly.', 'poker-tournament-import'),
            'actions' => '',
            'test' => 'poker_tournament_plugin'
        ];

        // Check file upload capabilities
        if (!ini_get('file_uploads')) {
            $result['status'] = 'critical';
            $result['description'] = __('File uploads are disabled on this server.', 'poker-tournament-import');
            $result['actions'] = sprintf(
                '<a href="%s">%s</a>',
                admin_url('options-general.php'),
                __('Enable file uploads', 'poker-tournament-import')
            );
        }

        // Check memory limits
        $memory_limit = $this->parse_memory_limit(ini_get('memory_limit'));
        if ($memory_limit < 128) {
            $result['status'] = 'recommended';
            $result['description'] = __('Consider increasing PHP memory limit for large tournament imports.', 'poker-tournament-import');
        }

        return $result;
    }
}
```

### Update System

```php
class Plugin_Updater {
    /**
     * Check for plugin updates
     */
    public function check_for_updates(): void {
        if (!class_exists('Plugin_Upgrader')) {
            return;
        }

        $current_version = get_option('poker_tournament_version', '1.0.0');
        $latest_version = $this->get_latest_version();

        if (version_compare($latest_version, $current_version, '>')) {
            $this->schedule_update_notification($latest_version);
        }
    }

    /**
     * Perform automatic updates
     */
    public function perform_auto_update(): bool {
        if (!wp_doing_cron()) {
            return false;
        }

        // Check if auto-updates are enabled
        $auto_updates = get_option('poker_tournament_auto_updates', false);
        if (!$auto_updates) {
            return false;
        }

        // Perform update
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $upgrader = new Plugin_Upgrader();
        $result = $upgrader->upgrade(plugin_basename(__FILE__));

        if (is_wp_error($result)) {
            error_log("Auto-update failed: " . $result->get_error_message());
            return false;
        }

        return true;
    }
}
```

This comprehensive project documentation provides a complete technical foundation for the Poker Tournament Import plugin, covering all aspects from architecture to maintenance. The documentation follows WordPress best practices and includes detailed code examples for implementation.

---

*This documentation is maintained alongside the plugin code and updated with each major release.*