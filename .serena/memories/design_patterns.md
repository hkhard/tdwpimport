# Design Patterns & Guidelines

## Architecture Patterns

### Singleton Pattern
Main plugin class uses singleton:
```php
class Poker_Tournament_Import {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

### Hook System
Plugin lifecycle managed via WordPress hooks:
- `init` - Initialize plugin components
- `save_post_tournament` - Async refresh statistics
- `before_delete_post` - Clean up tournament data
- `untrash_post` - Restore tournament data

### PHP 8.2+ Compatibility
Declare dynamic properties to avoid deprecation warnings:
```php
private $taxonomies;
private $formula_validator;
private $statistics_engine;
```

## Key Design Principles

### Formula System
- Formulas stored in WordPress options (`poker_formulas_*`)
- Real-time validation via AJAX
- Supports variables: n, r, hits, monies, avgBC, T33, T80
- Default Tournament Director formula included

### Statistics Engine
- Auto-refresh on plugin version change
- Manual refresh via admin dashboard
- Async refresh on tournament save/delete/restore
- Data mart tables for performance

### Caching Strategy
- Transient caching for computed statistics
- Clear cache on data changes
- Per-shortcode cache keys

### Data Processing Pipeline
```
.tdt Upload → Validation → Parsing → Processing
    → Database Storage → Template Rendering → Display
```

## Important Considerations

### Version Management
Version changes trigger statistics refresh - ensure version updates are intentional

### Database Schema
Custom tables created on plugin activation - handle migrations carefully

### AJAX Handlers
All AJAX handlers registered in main `init()` method:
- Nonce verification required
- Capability checks enforced
- JSON responses with wp_send_json_*

### WordPress Best Practices
- Use WordPress functions for sanitization/validation
- Follow WordPress template hierarchy
- Use wp_localize_script for JS data
- Implement proper capability checks

## Common Patterns

### Post Meta Storage
```php
update_post_meta($post_id, 'tournament_uuid', $uuid);
$uuid = get_post_meta($post_id, 'tournament_uuid', true);
```

### Custom Database Queries
```php
global $wpdb;
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}poker_statistics WHERE player_id = %d",
    $player_id
));
```

### Shortcode Registration
```php
add_shortcode('shortcode_name', [$this, 'render_shortcode']);
```
