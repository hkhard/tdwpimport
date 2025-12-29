#!/bin/bash
# Phase 4: Apply tdwp_ prefix to all poker_ prefixed items
# WordPress.org Compliance Script

set -e

echo "Starting tdwp_ prefix migration..."

# Change to plugin directory
cd wordpress-plugin/poker-tournament-import

# Backup first
echo "Creating backup..."
tar -czf ../../tdwp-prefix-backup-$(date +%Y%m%d-%H%M%S).tar.gz .

# Replace all poker_ options with tdwp_ options
echo "Updating option names..."
find . -name "*.php" -type f -exec sed -i '.tdwp' \
  -e "s/get_option('poker_/get_option('tdwp_/g" \
  -e "s/update_option('poker_/update_option('tdwp_/g" \
  -e "s/add_option('poker_/add_option('tdwp_/g" \
  -e "s/delete_option('poker_/delete_option('tdwp_/g" \
  {} \;

# Replace AJAX action names
echo "Updating AJAX actions..."
find . -name "*.php" -type f -exec sed -i '.tdwp' \
  -e "s/wp_ajax_poker_/wp_ajax_tdwp_/g" \
  -e "s/wp_ajax_nopriv_poker_/wp_ajax_nopriv_tdwp_/g" \
  {} \;

# Update AJAX calls in JavaScript files
echo "Updating JavaScript AJAX calls..."
find ../assets/js -name "*.js" -type f -exec sed -i '.tdwp' \
  -e "s/action: 'poker_/action: 'tdwp_/g" \
  -e "s/action:'poker_/action:'tdwp_/g" \
  {} \;

# Add shortcode backward compatibility - update shortcode registrations
echo "Updating shortcode names (maintaining backward compat)..."
find . -name "*.php" -type f -exec sed -i '.tdwp' \
  -e "s/add_shortcode('player_profile'/add_shortcode('tdwp_player_profile'/g" \
  -e "s/add_shortcode('poker_dashboard'/add_shortcode('tdwp_dashboard'/g" \
  -e "s/add_shortcode('season_overview'/add_shortcode('tdwp_season_overview'/g" \
  -e "s/add_shortcode('season_players'/add_shortcode('tdwp_season_players'/g" \
  -e "s/add_shortcode('season_results'/add_shortcode('tdwp_season_results'/g" \
  -e "s/add_shortcode('season_standings'/add_shortcode('tdwp_season_standings'/g" \
  -e "s/add_shortcode('season_statistics'/add_shortcode('tdwp_season_statistics'/g" \
  -e "s/add_shortcode('season_tabs'/add_shortcode('tdwp_season_tabs'/g" \
  -e "s/add_shortcode('series_leaderboard'/add_shortcode('tdwp_series_leaderboard'/g" \
  -e "s/add_shortcode('series_overview'/add_shortcode('tdwp_series_overview'/g" \
  -e "s/add_shortcode('series_players'/add_shortcode('tdwp_series_players'/g" \
  -e "s/add_shortcode('series_results'/add_shortcode('tdwp_series_results'/g" \
  -e "s/add_shortcode('series_standings'/add_shortcode('tdwp_series_standings'/g" \
  -e "s/add_shortcode('series_statistics'/add_shortcode('tdwp_series_statistics'/g" \
  -e "s/add_shortcode('series_tabs'/add_shortcode('tdwp_series_tabs'/g" \
  -e "s/add_shortcode('tournament_results'/add_shortcode('tdwp_tournament_results'/g" \
  -e "s/add_shortcode('tournament_series'/add_shortcode('tdwp_tournament_series'/g" \
  {} \;

# Clean up backup files
echo "Cleaning up backup files..."
find . -name "*.tdwp" -delete
find ../assets -name "*.tdwp" -delete

echo "Testing PHP syntax..."
find . -name "*.php" -type f | while read file; do
  php -l "$file" > /dev/null || echo "Syntax error in: $file"
done

echo ""
echo "✅ TDWP prefix migration complete!"
echo ""
echo "Next steps:"
echo "1. Add backward compatibility for old shortcode names in includes/class-shortcodes.php"
echo "2. Test plugin functionality"
echo "3. Commit changes"
