# Developer Guide - Poker Tournament Import Plugin

## Table of Contents

1. [Getting Started](#getting-started)
2. [Development Environment Setup](#development-environment-setup)
3. [Code Architecture](#code-architecture)
4. [API Reference](#api-reference)
5. [Extending the Plugin](#extending-the-plugin)
6. [Debugging & Testing](#debugging--testing)
7. [Contributing Guidelines](#contributing-guidelines)
8. [Code Standards](#code-standards)
9. [Common Development Tasks](#common-development-tasks)
10. [FAQ & Troubleshooting](#faq--troubleshooting)

## Getting Started

### Prerequisites

Before you start developing with the Poker Tournament Import plugin, ensure you have:

- **PHP 8.0+** with required extensions (mbstring, xml, json)
- **WordPress 6.0+** development installation
- **MySQL 5.7+** or MariaDB 10.2+
- **Node.js 16+** and npm for frontend assets
- **Git** for version control
- **Composer** for PHP dependencies
- **PHPUnit** for testing

### Quick Start

```bash
# 1. Clone the repository
git clone https://github.com/hkhard/tdwpimport.git
cd tdwpimport

# 2. Install dependencies
composer install
npm install

# 3. Set up WordPress development environment
wp scaffold plugin-tests poker-tournament-import

# 4. Run tests to verify setup
npm test

# 5. Start development
npm run dev
```

## Development Environment Setup

### Local WordPress Development

#### Using Local by Flywheel (Recommended)

1. **Install Local by Flywheel** from [localwp.com](https://localwp.com)
2. **Create a new site**:
   - Site name: `poker-tournament-dev`
   - Environment: `Preferred`
   - PHP version: `8.0+`
   - MySQL version: `5.7+`

3. **Configure WordPress**:
   - Navigate to WordPress admin
   - Set WP_DEBUG to true in wp-config.php
   - Install developer plugins if needed

4. **Link plugin**:
   ```bash
   # Create symbolic link to your development folder
   ln -s /path/to/tdwpimport/wordpress-plugin/poker-tournament-import \
       /path/to/local/site/app/public/wp-content/plugins/
   ```

#### Using Docker WordPress

```yaml
# docker-compose.yml
version: '3.8'
services:
  wordpress:
    image: wordpress:latest
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress
      WORDPRESS_DEBUG: 1
    volumes:
      - ./wordpress-plugin/poker-tournament-import:/var/www/html/wp-content/plugins/poker-tournament-import
      - ./wp-config.php:/var/www/html/wp-config.php

  db:
    image: mysql:5.7
    environment:
      MYSQL_ROOT_PASSWORD: wordpress
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```

### Development Tools Setup

#### PHP CodeSniffer

```bash
# Install WordPress coding standards
composer global require wp-coding-standards/wpcs
composer global require dealerdirect/phpcodesniffer-composer-installer

# Configure PHPCS
~/.composer/vendor/bin/phpcs --config-set installed_paths ~/.composer/vendor/wp-coding-standards/wpcs

# Run code checks
composer run phpcs
```

#### PHPStan

```bash
# Install PHPStan for static analysis
composer require --dev phpstan/phpstan

# Configure phpstan.neon
echo "
parameters:
    level: 5
    paths:
        - wordpress-plugin/poker-tournament-import/includes
    bootstrapFiles:
        - wordpress-plugin/poker-tournament-import/poker-tournament-import.php
" > phpstan.neon

# Run analysis
composer run phpstan
```

#### PHPUnit Testing

```bash
# Install WordPress test library
wp scaffold plugin-tests poker-tournament-import

# Run tests
phpunit

# Run with coverage
phpunit --coverage-html coverage
```

## Code Architecture

### Core Classes Overview

#### TDT_Parser (`includes/class-parser.php`)

The main parsing engine for Tournament Director files.

```php
class TDT_Parser {
    /**
     * Parse a TDT file and return structured data
     *
     * @param string $file_path Path to the TDT file
     * @return array Parsed tournament data
     * @throws Parser_Exception When parsing fails
     */
    public function parse_file(string $file_path): array {
        if (!file_exists($file_path)) {
            throw new Parser_Exception('File not found: ' . $file_path);
        }

        $content = file_get_contents($file_path);

        // Validate file format
        if (!$this->validate_tdt_format($content)) {
            throw new Parser_Exception('Invalid TDT file format');
        }

        // Parse JavaScript structure
        $tournament_data = $this->parse_javascript_structure($content);

        // Validate and sanitize data
        return $this->sanitize_parsed_data($tournament_data);
    }

    /**
     * Calculate tournament points using Tournament Director formula
     */
    public function calculate_points(array $tournament, int $finish_position): float {
        $n = $tournament['total_players'];
        $r = $finish_position;
        $avgBC = $tournament['average_buy_in'];
        $hits = $tournament['eliminations'][$r] ?? 0;

        return 10 * (sqrt($n) / sqrt($r)) * (1 + log($avgBC + 0.25)) + ($hits * 10);
    }
}
```

#### Tournament_Import (`includes/class-import.php`)

Handles the complete import workflow.

```php
class Tournament_Import {
    /**
     * Process tournament import with validation and error handling
     */
    public function process_import(array $file_data, array $options = []): WP_Post|WP_Error {
        try {
            // 1. Security checks
            if (!current_user_can('edit_posts')) {
                return new WP_Error('permission_denied', 'Insufficient permissions');
            }

            // 2. File validation
            $validation_result = $this->validate_upload($file_data);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // 3. Parse file
            $parser = new TDT_Parser();
            $tournament_data = $parser->parse_file($file_data['tmp_name']);

            // 4. Check for duplicates
            if ($this->is_duplicate_tournament($tournament_data)) {
                return new WP_Error('duplicate_tournament', 'This tournament has already been imported');
            }

            // 5. Create tournament post
            $tournament_id = $this->create_tournament_post($tournament_data, $options);

            // 6. Process players
            $this->process_players($tournament_data['players'], $tournament_id);

            // 7. Update statistics
            $this->update_statistics($tournament_id);

            do_action('poker_tournament_imported', $tournament_id, $tournament_data);

            return get_post($tournament_id);

        } catch (Exception $e) {
            error_log("Tournament import failed: " . $e->getMessage());
            return new WP_Error('import_failed', $e->getMessage());
        }
    }
}
```

#### Tournament_Shortcodes (`includes/class-shortcodes.php`)

Frontend display functionality.

```php
class Tournament_Shortcodes {
    /**
     * Register all plugin shortcodes
     */
    public function register_shortcodes(): void {
        add_shortcode('tournament_results', [$this, 'render_tournament_results']);
        add_shortcode('tournament_series', [$this, 'render_tournament_series']);
        add_shortcode('player_profile', [$this, 'render_player_profile']);
        add_shortcode('poker_leaderboard', [$this, 'render_leaderboard']);
    }

    /**
     * Render tournament results with caching
     */
    public function render_tournament_results($atts): string {
        $atts = shortcode_atts([
            'id' => 0,
            'show_details' => 'true',
            'show_players' => 'true',
            'show_statistics' => 'true',
            'template' => 'default'
        ], $atts);

        if (empty($atts['id'])) {
            return '<!-- Missing tournament ID -->';
        }

        $cache_key = "tournament_results_{$atts['id']}_{$atts['template']}";
        $cached_output = get_transient($cache_key);

        if ($cached_output) {
            return $cached_output;
        }

        $tournament = $this->get_tournament_data($atts['id']);
        $output = $this->load_template('tournament-results', [
            'tournament' => $tournament,
            'atts' => $atts
        ]);

        set_transient($cache_key, $output, HOUR_IN_SECONDS);
        return $output;
    }
}
```

### Database Schema

#### Custom Post Types

```php
/**
 * Register custom post types
 */
function register_poker_post_types(): void {
    // Tournament post type
    register_post_type('tournament', [
        'labels' => [
            'name' => __('Tournaments', 'poker-tournament-import'),
            'singular_name' => __('Tournament', 'poker-tournament-import'),
            'menu_name' => __('Poker Tournaments', 'poker-tournament-import')
        ],
        'public' => true,
        'has_archive' => true,
        'supports' => ['title', 'editor', 'custom-fields'],
        'rewrite' => ['slug' => 'tournaments'],
        'menu_icon' => 'dashicons-trophy',
        'capability_type' => 'post',
        'map_meta_cap' => true
    ]);

    // Tournament series post type
    register_post_type('tournament_series', [
        'labels' => [
            'name' => __('Tournament Series', 'poker-tournament-import'),
            'singular_name' => __('Tournament Series', 'poker-tournament-import')
        ],
        'public' => true,
        'has_archive' => true,
        'supports' => ['title', 'editor', 'custom-fields'],
        'rewrite' => ['slug' => 'tournament-series'],
        'capability_type' => 'post'
    ]);

    // Player post type
    register_post_type('player', [
        'labels' => [
            'name' => __('Players', 'poker-tournament-import'),
            'singular_name' => __('Player', 'poker-tournament-import')
        ],
        'public' => true,
        'has_archive' => true,
        'supports' => ['title', 'custom-fields'],
        'rewrite' => ['slug' => 'players'],
        'capability_type' => 'post'
    ]);
}
```

#### Custom Taxonomies

```php
/**
 * Register custom taxonomies
 */
function register_poker_taxonomies(): void {
    // Tournament type
    register_taxonomy('tournament_type', ['tournament'], [
        'labels' => [
            'name' => __('Tournament Types', 'poker-tournament-import'),
            'singular_name' => __('Tournament Type', 'poker-tournament-import')
        ],
        'hierarchical' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true
    ]);

    // Tournament format
    register_taxonomy('tournament_format', ['tournament'], [
        'labels' => [
            'name' => __('Tournament Formats', 'poker-tournament-import'),
            'singular_name' => __('Tournament Format', 'poker-tournament-import')
        ],
        'hierarchical' => false,
        'show_ui' => true,
        'show_admin_column' => true
    ]);
}
```

## API Reference

### Hooks (Actions & Filters)

#### Actions

```php
/**
 * Fired after a tournament is successfully imported
 *
 * @param int $tournament_id The imported tournament ID
 * @param array $tournament_data The parsed tournament data
 */
do_action('poker_tournament_imported', $tournament_id, $tournament_data);

/**
 * Fired before tournament data is processed
 *
 * @param array $tournament_data Raw tournament data
 * @param array $options Import options
 */
do_action('poker_tournament_pre_import', $tournament_data, $options);

/**
 * Fired after player statistics are updated
 *
 * @param int $player_id Player post ID
 * @param array $statistics Updated statistics
 */
do_action('poker_player_statistics_updated', $player_id, $statistics);
```

#### Filters

```php
/**
 * Filter tournament data before import
 *
 * @param array $tournament_data Parsed tournament data
 * @param string $file_path Original file path
 * @return array Modified tournament data
 */
apply_filters('poker_tournament_import_data', $tournament_data, $file_path);

/**
 * Filter shortcode attributes before rendering
 *
 * @param array $atts Shortcode attributes
 * @param string $shortcode Shortcode name
 * @return array Modified attributes
 */
apply_filters('poker_tournament_shortcode_atts', $atts, $shortcode);

/**
 * Filter tournament template path
 *
 * @param string $template Template file path
 * @param string $template_name Template name
 * @return string Modified template path
 */
apply_filters('poker_tournament_template_path', $template, $template_name);
```

### Public API Functions

```php
/**
 * Get tournament data with caching
 *
 * @param int $tournament_id Tournament post ID
 * @return array Tournament data or empty array
 */
function get_poker_tournament_data(int $tournament_id): array {
    static $cache = [];

    if (isset($cache[$tournament_id])) {
        return $cache[$tournament_id];
    }

    $tournament_data = [
        'id' => $tournament_id,
        'title' => get_the_title($tournament_id),
        'date' => get_post_meta($tournament_id, 'tournament_date', true),
        'time' => get_post_meta($tournament_id, 'tournament_time', true),
        'buy_in' => get_post_meta($tournament_id, 'buy_in_amount', true),
        'players' => get_post_meta($tournament_id, 'tournament_players', true),
        'results' => get_post_meta($tournament_id, 'tournament_results', true)
    ];

    $cache[$tournament_id] = $tournament_data;
    return $tournament_data;
}

/**
 * Get player statistics
 *
 * @param int $player_id Player post ID
 * @return array Player statistics
 */
function get_poker_player_statistics(int $player_id): array {
    $stats = get_post_meta($player_id, 'player_statistics', true);

    return wp_parse_args($stats, [
        'total_tournaments' => 0,
        'total_winnings' => 0.00,
        'average_finish' => 0.00,
        'best_finish' => 999,
        'cashes' => 0,
        'wins' => 0,
        'total_points' => 0.00
    ]);
}

/**
 * Check if tournament exists
 *
 * @param string $tournament_name Tournament name
 * @param string $tournament_date Tournament date
 * @return bool
 */
function poker_tournament_exists(string $tournament_name, string $tournament_date): bool {
    global $wpdb;

    $exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'tournament'
        AND p.post_title = %s
        AND pm.meta_key = 'tournament_date'
        AND pm.meta_value = %s
    ", $tournament_name, $tournament_date));

    return $exists > 0;
}
```

### Database Helper Functions

```php
/**
 * Get tournament leaderboard
 *
 * @param array $args Query arguments
 * @return array Leaderboard data
 */
function get_poker_leaderboard(array $args = []): array {
    global $wpdb;

    $defaults = [
        'season_id' => 0,
        'limit' => 50,
        'order_by' => 'total_points',
        'order' => 'DESC'
    ];

    $args = wp_parse_args($args, $defaults);

    $sql = $wpdb->prepare("
        SELECT p.ID,
               p.post_title as player_name,
               pm1.meta_value as total_points,
               pm2.meta_value as total_winnings,
               pm3.meta_value as total_tournaments
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'total_points'
        JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'total_winnings'
        JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'total_tournaments'
        WHERE p.post_type = 'player'
        AND p.post_status = 'publish'
        ORDER BY CAST(pm1.meta_value AS DECIMAL) {$args['order']}
        LIMIT %d
    ", $args['limit']);

    return $wpdb->get_results($sql, ARRAY_A);
}
```

## Extending the Plugin

### Creating Custom Templates

#### Template Hierarchy

The plugin follows WordPress template hierarchy with custom templates:

1. `tournament-{slug}.php`
2. `tournament-{id}.php`
3. `tournament.php`
4. `single-tournament.php`
5. `singular.php`
6. `page.php`
7. `index.php`

#### Custom Template Example

```php
<?php
/**
 * Template Name: Enhanced Tournament Results
 * Template Post Type: tournament
 */

get_header(); ?>

<div class="tournament-container">
    <?php while (have_posts()) : the_post(); ?>

        <?php get_template_part('template-parts/tournament/header'); ?>

        <div class="tournament-content">
            <?php the_content(); ?>
        </div>

        <?php
        // Display enhanced results
        $tournament_data = get_poker_tournament_data(get_the_ID());
        echo do_shortcode('[tournament_results id="' . get_the_ID() . '" template="enhanced"]');
        ?>

        <?php get_template_part('template-parts/tournament/statistics'); ?>

    <?php endwhile; ?>
</div>

<?php get_footer();
```

### Adding Custom Shortcodes

```php
/**
 * Custom shortcode for tournament search
 */
add_shortcode('tournament_search_form', function($atts) {
    $atts = shortcode_atts([
        'placeholder' => __('Search tournaments...', 'poker-tournament-import'),
        'show_filters' => 'true'
    ], $atts);

    ob_start();
    ?>
    <form class="tournament-search" method="get">
        <input
            type="text"
            name="tournament_search"
            placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
            class="tournament-search-input"
        >

        <?php if ($atts['show_filters'] === 'true') : ?>
            <div class="tournament-filters">
                <?php
                // Display tournament type filter
                $types = get_terms(['taxonomy' => 'tournament_type']);
                if ($types) :
                ?>
                    <select name="tournament_type">
                        <option value=""><?php _e('All Types', 'poker-tournament-import'); ?></option>
                        <?php foreach ($types as $type) : ?>
                            <option value="<?php echo esc_attr($type->slug); ?>">
                                <?php echo esc_html($type->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <button type="submit" class="tournament-search-button">
            <?php _e('Search', 'poker-tournament-import'); ?>
        </button>
    </form>
    <?php
    return ob_get_clean();
});
```

### Custom Fields Integration

```php
/**
 * Add custom fields to tournament posts
 */
function add_tournament_custom_fields(): void {
    add_meta_box(
        'tournament_custom_data',
        __('Custom Tournament Data', 'poker-tournament-import'),
        'render_tournament_custom_fields',
        'tournament',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes_tournament', 'add_tournament_custom_fields');

/**
 * Render custom fields
 */
function render_tournament_custom_fields(WP_Post $post): void {
    wp_nonce_field('tournament_custom_fields', 'tournament_nonce');

    $custom_notes = get_post_meta($post->ID, 'custom_notes', true);
    $featured_tournament = get_post_meta($post->ID, 'featured_tournament', true);
    ?>

    <table class="form-table">
        <tr>
            <th>
                <label for="custom_notes"><?php _e('Custom Notes', 'poker-tournament-import'); ?></label>
            </th>
            <td>
                <textarea
                    id="custom_notes"
                    name="custom_notes"
                    rows="4"
                    class="large-text"
                ><?php echo esc_textarea($custom_notes); ?></textarea>
            </td>
        </tr>

        <tr>
            <th>
                <label for="featured_tournament">
                    <input
                        type="checkbox"
                        id="featured_tournament"
                        name="featured_tournament"
                        value="1"
                        <?php checked($featured_tournament, '1'); ?>
                    >
                    <?php _e('Featured Tournament', 'poker-tournament-import'); ?>
                </label>
            </th>
        </tr>
    </table>
    <?php
}

/**
 * Save custom fields
 */
function save_tournament_custom_fields(int $post_id): void {
    if (!isset($_POST['tournament_nonce']) || !wp_verify_nonce($_POST['tournament_nonce'], 'tournament_custom_fields')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['custom_notes'])) {
        update_post_meta($post_id, 'custom_notes', sanitize_textarea_field($_POST['custom_notes']));
    }

    $featured = isset($_POST['featured_tournament']) ? '1' : '0';
    update_post_meta($post_id, 'featured_tournament', $featured);
}
add_action('save_post_tournament', 'save_tournament_custom_fields');
```

### Plugin Integration Examples

#### Integration with WooCommerce

```php
/**
 * Add tournament registration to WooCommerce
 */
class WooCommerce_Tournament_Integration {
    public function __construct() {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'add_tournament_registration']);
        add_action('woocommerce_add_to_cart', [$this, 'handle_tournament_registration']);
    }

    public function add_tournament_registration(): void {
        global $product;

        if ($product->get_type() !== 'tournament') {
            return;
        }

        $tournament_id = $product->get_meta('tournament_id');
        if (!$tournament_id) {
            return;
        }

        // Display tournament information
        $tournament_data = get_poker_tournament_data($tournament_id);
        include plugin_dir_path(__FILE__) . 'templates/tournament-registration-form.php';
    }

    public function handle_tournament_registration($cart_item_key): void {
        $cart_item = WC()->cart->get_cart_item($cart_item_key);

        if (isset($cart_item['tournament_registration'])) {
            // Process tournament registration
            $this->register_player_for_tournament(
                $cart_item['tournament_registration']['tournament_id'],
                $cart_item['tournament_registration']['player_data']
            );
        }
    }
}
```

## Debugging & Testing

### Debugging Tools

#### WP_DEBUG Configuration

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);

// Plugin-specific debugging
define('POKER_TOURNAMENT_DEBUG', true);
```

#### Custom Debug Logging

```php
/**
 * Plugin debug logging function
 */
function poker_tournament_debug_log($message, $level = 'info'): void {
    if (!defined('POKER_TOURNAMENT_DEBUG') || !POKER_TOURNAMENT_DEBUG) {
        return;
    }

    $timestamp = current_time('mysql');
    $user_id = get_current_user_id();
    $log_entry = "[{$timestamp}] [{$level}] [User:{$user_id}] {$message}";

    error_log("Poker Tournament: {$log_entry}");
}

// Usage examples
poker_tournament_debug_log('Tournament import started', 'info');
poker_tournament_debug_log('Parser error: Invalid file format', 'error');
poker_tournament_debug_log('Memory usage: ' . memory_get_usage(true), 'debug');
```

#### Debug Bar Integration

```php
/**
 * Add debug panel for plugin
 */
class Poker_Tournament_Debug_Panel {
    public function __construct() {
        add_filter('debug_bar_panels', [$this, 'add_debug_panel']);
    }

    public function add_debug_panel(array $panels): array {
        $panels[] = new Poker_Tournament_Debug_Panel_Class();
        return $panels;
    }
}

class Poker_Tournament_Debug_Panel_Class extends Debug_Bar_Panel {
    public function init(): void {
        $this->title('Poker Tournament');
    }

    public function render(): void {
        echo '<div class="debug-bar-panel">';
        echo '<h3>Plugin Information</h3>';
        echo '<p><strong>Version:</strong> ' . POKER_TOURNAMENT_VERSION . '</p>';

        // Display recent imports
        $recent_imports = get_posts([
            'post_type' => 'tournament',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        echo '<h4>Recent Imports</h4>';
        if ($recent_imports) {
            echo '<ul>';
            foreach ($recent_imports as $tournament) {
                echo '<li>' . esc_html($tournament->post_title) . ' - ' . get_the_date('', $tournament->ID) . '</li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }
}
```

### Unit Testing

#### Parser Tests

```php
class Test_TDT_Parser extends WP_UnitTestCase {
    private $parser;

    public function setUp(): void {
        parent::setUp();
        $this->parser = new TDT_Parser();
    }

    public function test_parse_valid_tournament(): void {
        $test_data = [
            'name' => 'Test Tournament',
            'date' => '2024-01-15',
            'buy_in' => 100.00,
            'players' => [
                ['name' => 'John Doe', 'id' => 1],
                ['name' => 'Jane Smith', 'id' => 2]
            ],
            'results' => [
                ['player_id' => 1, 'finish' => 1, 'winnings' => 500.00],
                ['player_id' => 2, 'finish' => 2, 'winnings' => 300.00]
            ]
        ];

        $result = $this->parser->parse_tournament_data(json_encode($test_data));

        $this->assertIsArray($result);
        $this->assertEquals('Test Tournament', $result['name']);
        $this->assertCount(2, $result['players']);
        $this->assertCount(2, $result['results']);
    }

    public function test_points_calculation(): void {
        $tournament = [
            'total_players' => 50,
            'average_buy_in' => 100.00,
            'eliminations' => [1 => 1, 2 => 1, 3 => 0]
        ];

        $points = $this->parser->calculate_points($tournament, 1);

        $this->assertIsFloat($points);
        $this->assertGreaterThan(0, $points);
    }
}
```

#### Integration Tests

```php
class Test_Tournament_Import extends WP_UnitTestCase {
    public function test_complete_import_workflow(): void {
        // Create test file
        $test_file = $this->create_test_tdt_file();

        // Mock file upload
        $_FILES = [
            'tournament_file' => [
                'name' => 'test.tdt',
                'type' => 'text/plain',
                'tmp_name' => $test_file,
                'error' => 0,
                'size' => filesize($test_file)
            ]
        ];

        // Process import
        $importer = new Tournament_Import();
        $result = $importer->process_import($_FILES['tournament_file']);

        // Verify import
        $this->assertInstanceOf(WP_Post::class, $result);
        $this->assertEquals('tournament', $result->post_type);

        // Verify data
        $tournament_data = get_post_meta($result->ID, 'tournament_data', true);
        $this->assertIsArray($tournament_data);
        $this->assertNotEmpty($tournament_data);
    }

    private function create_test_tdt_file(): string {
        $content = 'new Tournament({
            name: "Test Tournament",
            date: "2024-01-15",
            buyIn: 100.00,
            players: [
                {name: "John Doe", id: 1},
                {name: "Jane Smith", id: 2}
            ]
        })';

        $file = tempnam(sys_get_temp_dir(), 'test_tdt_');
        file_put_contents($file, $content);
        return $file;
    }
}
```

## Contributing Guidelines

### Pull Request Process

1. **Fork the repository** and create a feature branch
2. **Make your changes** following code standards
3. **Write tests** for new functionality
4. **Update documentation** as needed
5. **Submit pull request** with clear description

### Code Review Checklist

- [ ] Code follows WordPress coding standards
- [ ] Includes appropriate error handling
- [ ] Has unit tests for new functionality
- [ ] Documentation is updated
- [ ] Security considerations addressed
- [ ] Performance impact considered
- [ ] Backward compatibility maintained

### Commit Message Format

```
type(scope): brief description

Detailed description of changes:
- What was changed
- Why it was changed
- How it was tested

Closes #123
```

Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation update
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Test additions/updates
- `chore`: Maintenance tasks

### Development Workflow

```bash
# 1. Create feature branch
git checkout -b feature/new-shortcode-functionality

# 2. Make changes
# ... edit files ...

# 3. Run tests
npm test
composer run phpcs
composer run phpstan

# 4. Commit changes
git add .
git commit -m "feat(shortcodes): add tournament search functionality"

# 5. Push branch
git push origin feature/new-shortcode-functionality

# 6. Create pull request
gh pr create --title "Add Tournament Search Shortcode" --body "..."
```

## Code Standards

### PHP Standards

#### Formatting

```php
<?php
/**
 * Brief description of the class
 *
 * Detailed description if needed
 *
 * @package   PokerTournamentImport
 * @author    Your Name <email@example.com>
 * @license   GPL-2.0+
 * @link      https://example.com
 * @copyright 2024 Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

namespace PokerTournamentImport\Includes;

use WP_Post;
use WP_Error;

/**
 * Class description
 *
 * @since 1.0.0
 */
class Tournament_Manager {

    /**
     * Class constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize the class
     *
     * @since 1.0.0
     * @return void
     */
    private function init(): void {
        add_action('init', [$this, 'register_hooks']);
    }

    /**
     * Register WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    public function register_hooks(): void {
        add_action('save_post_tournament', [$this, 'save_tournament_data']);
        add_filter('the_content', [$this, 'filter_tournament_content']);
    }

    /**
     * Save tournament data
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return void
     */
    public function save_tournament_data(int $post_id): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Process and save data
    }
}
```

#### Naming Conventions

- **Classes**: PascalCase (`Tournament_Manager`)
- **Methods**: snake_case (`process_tournament_data`)
- **Variables**: snake_case (`tournament_data`)
- **Constants**: UPPER_CASE (`MAX_FILE_SIZE`)
- **File names**: hyphen-separated (`class-tournament-manager.php`)

### JavaScript Standards

```javascript
/**
 * Tournament JavaScript functionality
 *
 * @since 1.0.0
 */
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const TournamentManager = {
        /**
         * Initialize tournament functionality
         */
        init: function() {
            this.bindEvents();
            this.loadTournamentData();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const tournamentForms = document.querySelectorAll('.tournament-form');

            tournamentForms.forEach(function(form) {
                form.addEventListener('submit', TournamentManager.handleFormSubmit);
            });

            // Use event delegation for dynamic content
            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('tournament-toggle')) {
                    TournamentManager.toggleTournamentDetails(event.target);
                }
            });
        },

        /**
         * Handle form submission
         *
         * @param {Event} event The submit event
         */
        handleFormSubmit: function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);

            TournamentManager.submitTournamentData(formData)
                .then(function(response) {
                    TournamentManager.showSuccessMessage(response.message);
                })
                .catch(function(error) {
                    TournamentManager.showErrorMessage(error.message);
                });
        },

        /**
         * Submit tournament data via AJAX
         *
         * @param {FormData} data Form data
         * @return {Promise} AJAX promise
         */
        submitTournamentData: function(data) {
            return fetch(poker_tournament_import.ajax_url, {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            });
        }
    };

    // Initialize when DOM is ready
    TournamentManager.init();
});
```

### CSS Standards

```css
/**
 * Tournament styles
 *
 * @since 1.0.0
 */

/* BEM Methodology */
.tournament-results {
    /* Block styles */
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.tournament-results__header {
    /* Element styles */
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 20px;
    padding-bottom: 15px;
}

.tournament-results__title {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

.tournament-results__title--featured {
    /* Modifier styles */
    color: #dc2626;
    border-left: 4px solid #dc2626;
    padding-left: 15px;
}

.tournament-results--loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Mobile-first responsive design */
.tournament-results {
    width: 100%;
    padding: 15px;
}

@media (min-width: 768px) {
    .tournament-results {
        padding: 20px;
    }
}

@media (min-width: 1024px) {
    .tournament-results {
        padding: 30px;
    }
}

/* CSS custom properties */
:root {
    --tournament-primary-color: #2563eb;
    --tournament-secondary-color: #64748b;
    --tournament-success-color: #10b981;
    --tournament-error-color: #ef4444;
    --tournament-border-color: #e5e7eb;
    --tournament-background-color: #ffffff;
    --tournament-text-color: #1f2937;
}
```

## Common Development Tasks

### Adding a New Shortcode

```php
// 1. Register the shortcode
add_shortcode('tournament_calendar', 'render_tournament_calendar_shortcode');

// 2. Create the render function
function render_tournament_calendar_shortcode($atts) {
    $atts = shortcode_atts([
        'month' => date('n'),
        'year' => date('Y'),
        'category' => '',
        'limit' => 50
    ], $atts);

    // Get tournaments for the specified period
    $tournaments = get_tournaments_for_calendar($atts);

    // Load template
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/tournament-calendar.php';
    return ob_get_clean();
}

// 3. Create helper functions
function get_tournaments_for_calendar($atts) {
    $args = [
        'post_type' => 'tournament',
        'post_status' => 'publish',
        'posts_per_page' => $atts['limit'],
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'tournament_date',
                'value' => $atts['year'] . '-' . str_pad($atts['month'], 2, '0', STR_PAD_LEFT) . '-01',
                'compare' => '>=',
                'type' => 'DATE'
            ],
            [
                'key' => 'tournament_date',
                'value' => $atts['year'] . '-' . str_pad($atts['month'], 2, '0', STR_PAD_LEFT) . '-31',
                'compare' => '<=',
                'type' => 'DATE'
            ]
        ]
    ];

    if (!empty($atts['category'])) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'tournament_category',
                'field' => 'slug',
                'terms' => $atts['category']
            ]
        ];
    }

    return get_posts($args);
}
```

### Creating Custom Widgets

```php
class Tournament_Leaderboard_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'tournament_leaderboard_widget',
            __('Tournament Leaderboard', 'poker-tournament-import'),
            ['description' => __('Display tournament leaderboard', 'poker-tournament-import')]
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        // Get leaderboard data
        $leaderboard = get_poker_leaderboard([
            'limit' => $instance['limit'] ?? 10,
            'season_id' => $instance['season_id'] ?? 0
        ]);

        // Display leaderboard
        include plugin_dir_path(__FILE__) . 'templates/widget-leaderboard.php';

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Tournament Leaderboard', 'poker-tournament-import');
        $limit = !empty($instance['limit']) ? $instance['limit'] : 10;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php _e('Title:', 'poker-tournament-import'); ?>
            </label>
            <input
                class="widefat"
                id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                type="text"
                value="<?php echo esc_attr($title); ?>"
            >
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>">
                <?php _e('Number of players:', 'poker-tournament-import'); ?>
            </label>
            <input
                class="widefat"
                id="<?php echo esc_attr($this->get_field_id('limit')); ?>"
                name="<?php echo esc_attr($this->get_field_name('limit')); ?>"
                type="number"
                value="<?php echo esc_attr($limit); ?>"
                min="1"
                max="50"
            >
        </p>
        <?php
    }
}

// Register the widget
add_action('widgets_init', function() {
    register_widget('Tournament_Leaderboard_Widget');
});
```

### Adding AJAX Handlers

```php
// 1. Register AJAX actions
add_action('wp_ajax_poker_tournament_search', 'handle_tournament_search');
add_action('wp_ajax_nopriv_poker_tournament_search', 'handle_tournament_search');

// 2. Create AJAX handler function
function handle_tournament_search() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'poker_tournament_search')) {
        wp_die(__('Security check failed', 'poker-tournament-import'));
    }

    // Get search parameters
    $search_term = sanitize_text_field($_POST['search_term'] ?? '');
    $tournament_type = sanitize_text_field($_POST['tournament_type'] ?? '');

    // Perform search
    $args = [
        'post_type' => 'tournament',
        'post_status' => 'publish',
        's' => $search_term,
        'posts_per_page' => 20
    ];

    if (!empty($tournament_type)) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'tournament_type',
                'field' => 'slug',
                'terms' => $tournament_type
            ]
        ];
    }

    $tournaments = get_posts($args);

    // Prepare response
    $results = [];
    foreach ($tournaments as $tournament) {
        $results[] = [
            'id' => $tournament->ID,
            'title' => get_the_title($tournament),
            'date' => get_post_meta($tournament->ID, 'tournament_date', true),
            'permalink' => get_permalink($tournament)
        ];
    }

    // Send JSON response
    wp_send_json_success([
        'results' => $results,
        'count' => count($results)
    ]);
}

// 3. JavaScript for AJAX requests
jQuery(document).ready(function($) {
    $('.tournament-search-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $results = $('.tournament-search-results');

        $.ajax({
            url: poker_tournament_import.ajax_url,
            type: 'POST',
            data: {
                action: 'poker_tournament_search',
                search_term: $form.find('input[name="search_term"]').val(),
                tournament_type: $form.find('select[name="tournament_type"]').val(),
                nonce: poker_tournament_import.nonce
            },
            beforeSend: function() {
                $results.addClass('loading');
                $results.html('<div class="loading-spinner"><?php _e('Searching...', 'poker-tournament-import'); ?></div>');
            },
            success: function(response) {
                if (response.success) {
                    $results.removeClass('loading');
                    displaySearchResults(response.data.results);
                } else {
                    $results.html('<div class="error">' + response.data + '</div>');
                }
            },
            error: function() {
                $results.html('<div class="error"><?php _e('Search failed. Please try again.', 'poker-tournament-import'); ?></div>');
            }
        });
    });

    function displaySearchResults(results) {
        var $results = $('.tournament-search-results');

        if (results.length === 0) {
            $results.html('<p><?php _e('No tournaments found.', 'poker-tournament-import'); ?></p>');
            return;
        }

        var html = '<ul class="tournament-search-list">';
        results.forEach(function(tournament) {
            html += '<li>';
            html += '<a href="' + tournament.permalink + '">' + tournament.title + '</a>';
            html += '<span class="tournament-date">' + tournament.date + '</span>';
            html += '</li>';
        });
        html += '</ul>';

        $results.html(html);
    }
});
```

## FAQ & Troubleshooting

### Common Issues

#### Q: Import fails with "Invalid file format" error

**A:** Check the following:
- Ensure the file is a valid .tdt file from Tournament Director
- Verify the file isn't corrupted (try opening in a text editor)
- Check PHP file upload limits in php.ini
- Ensure proper file permissions on upload directory

#### Q: Tournament statistics are not updating

**A:** Try these solutions:
- Check that wp-cron is running properly
- Clear plugin caches via admin panel
- Run statistics update manually: `wp eval 'update_all_tournament_statistics();'`
- Check for database errors in debug log

#### Q: Shortcodes are not working

**A:** Verify:
- Plugin is activated
- Shortcode syntax is correct
- No theme conflicts (try with default theme)
- Check for JavaScript errors in browser console

#### Q: Memory exhaustion during import

**A:** Increase PHP memory:
```php
// In wp-config.php
define('WP_MEMORY_LIMIT', '256M');

// Or in php.ini
memory_limit = 256M
```

#### Q: Database performance issues

**A:** Optimize database:
- Add proper indexes to custom tables
- Use database caching
- Consider using custom statistics tables
- Run `wp db optimize` regularly

### Debug Information

#### WordPress Debug Info

```php
// Add to functions.php for debugging
function debug_plugin_info() {
    if (!current_user_can('administrator')) {
        return;
    }

    echo '<details><pre>';
    echo 'Plugin Version: ' . POKER_TOURNAMENT_VERSION . "\n";
    echo 'PHP Version: ' . PHP_VERSION . "\n";
    echo 'WordPress Version: ' . get_bloginfo('version') . "\n";
    echo 'Memory Limit: ' . ini_get('memory_limit') . "\n";
    echo 'Upload Max Filesize: ' . ini_get('upload_max_filesize') . "\n";
    echo 'Post Max Size: ' . ini_get('post_max_size') . "\n";

    // Check plugin files
    $plugin_files = glob(plugin_dir_path(__FILE__) . '*.php');
    echo 'Plugin Files: ' . count($plugin_files) . "\n";

    // Database tables
    global $wpdb;
    $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}poker_%'");
    echo 'Custom Tables: ' . count($tables) . "\n";

    echo '</pre></details>';
}
add_action('admin_footer', 'debug_plugin_info');
```

### Performance Monitoring

```php
// Performance monitoring hook
add_action('poker_tournament_imported', function($tournament_id, $tournament_data) {
    $memory_usage = memory_get_peak_usage(true);
    $execution_time = timer_stop();

    error_log("Tournament Import Performance - ID: {$tournament_id}, Memory: {$memory_usage}, Time: {$execution_time}s");

    // Alert if performance is poor
    if ($memory_usage > 128 * 1024 * 1024) { // 128MB
        wp_mail(get_option('admin_email'), 'High Memory Usage Alert',
            "Tournament import {$tournament_id} used " . size_format($memory_usage) . " memory");
    }
}, 10, 2);
```

---

This developer guide provides comprehensive documentation for extending and maintaining the Poker Tournament Import plugin. Follow these guidelines to ensure consistent, secure, and performant code contributions.