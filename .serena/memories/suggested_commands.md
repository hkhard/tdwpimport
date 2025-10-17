# Suggested Commands

## Git Commands (macOS/Darwin)
```bash
git status                    # Check repository status
git add .                     # Stage all changes
git commit -m "message"       # Commit changes
git push                      # Push to remote
git log --oneline -10         # View recent commits
```

## File Operations (macOS/Darwin)
```bash
ls -la                        # List files with details
find . -name "*.php"          # Find PHP files
grep -r "pattern" .           # Search for pattern
cd wordpress-plugin/poker-tournament-import  # Navigate to plugin
```

## WordPress CLI (if available)
```bash
wp plugin list                # List plugins
wp plugin activate poker-tournament-import  # Activate plugin
wp cache flush                # Clear cache
wp db optimize                # Optimize database
```

## Development Workflow
```bash
# Navigate to plugin directory
cd wordpress-plugin/poker-tournament-import

# Make code changes
# ... edit files ...

# No build/compile step needed - direct PHP/JS/CSS development
```

## Testing (if configured)
```bash
phpunit                       # Run PHP tests (if available)
npm test                      # Run tests (if configured)
```

## Database Operations
```bash
# Connect to MySQL (adjust credentials)
mysql -u wordpress -p wordpress

# Show tables
SHOW TABLES LIKE 'wp_poker_%';

# Check statistics table
SELECT COUNT(*) FROM wp_poker_statistics;
```

## File Search Shortcuts
```bash
# Find class files
find . -name "class-*.php"

# Find shortcode definitions
grep -r "add_shortcode" wordpress-plugin/

# Find AJAX handlers
grep -r "wp_ajax_" wordpress-plugin/
```
