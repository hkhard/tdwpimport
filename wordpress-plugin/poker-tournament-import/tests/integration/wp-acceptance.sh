#!/usr/bin/env bash
#
# End-to-end integration check against a REAL WordPress install.
#
# The PHPUnit suite runs against stubs and a fake $wpdb, which is fast but cannot
# see anything that depends on real WordPress or a real database — plugin
# activation, dbDelta schema creation, actual shortcode registration, or what
# genuinely lands in debug.log. This script closes that gap.
#
# It downloads WordPress, wires up the SQLite database drop-in (so no MySQL
# server is needed), installs the site, activates the built plugin zip, and then
# asserts the behaviour this release is responsible for:
#
#   1. The plugin activates without a fatal.
#   2. With the module OFF, importing a real .tdt still populates the statistics
#      data marts (poker_tournament_players + poker_player_roi).
#   3. Statistics shortcodes still work; live/display shortcodes are gone.
#   4. No diagnostic spam reaches debug.log, in either gate state.
#   5. Turning the module off measurably lowers peak memory.
#
# Usage:  ./tests/integration/wp-acceptance.sh [path-to-plugin-zip]
#
# Requires: php with pdo_sqlite, curl, unzip. No MySQL, no Docker, no network
# after the first run (the WordPress download is cached in the work directory).

set -uo pipefail

PLUGIN_ZIP="${1:-}"
WORK="${TDWP_WP_TEST_DIR:-/tmp/tdwp-wp-acceptance}"
WP="$WORK/wordpress"
FIXTURE="${TDWP_TDT_FIXTURE:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
REPO_ROOT="$(cd "$PLUGIN_DIR/../.." && pwd)"

RED=$'\033[0;31m'; GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'; NC=$'\033[0m'
PASS=0; FAIL=0

ok()   { printf "  ${GREEN}PASS${NC}  %s\n" "$1"; PASS=$((PASS+1)); }
bad()  { printf "  ${RED}FAIL${NC}  %s\n" "$1"; FAIL=$((FAIL+1)); }
info() { printf "  ${YELLOW}--${NC}    %s\n" "$1"; }

# Resolve the plugin zip: newest built artifact unless one was named.
if [ -z "$PLUGIN_ZIP" ]; then
	PLUGIN_ZIP="$(ls -t "$(dirname "$PLUGIN_DIR")"/poker-tournament-import-v*.zip 2>/dev/null | head -1)"
fi

if [ ! -f "$PLUGIN_ZIP" ]; then
	echo "error: no plugin zip found (pass one as \$1)" >&2
	exit 2
fi

# Resolve to an absolute path: provisioning cd's into the work directory, after
# which a relative zip path would no longer resolve.
PLUGIN_ZIP="$(cd "$(dirname "$PLUGIN_ZIP")" && pwd)/$(basename "$PLUGIN_ZIP")"

# Note: no `grep -q` in a pipeline here — under `set -o pipefail` its early exit
# SIGPIPEs `php -m` and the pipeline reports failure even on a match.
php_modules="$(php -m 2>/dev/null)"
if ! printf '%s\n' "$php_modules" | grep -iE '^pdo_sqlite$' >/dev/null; then
	echo "error: php needs the pdo_sqlite extension" >&2
	exit 2
fi

echo "WordPress acceptance check"
echo "  plugin: $(basename "$PLUGIN_ZIP")"
echo "  work:   $WORK"
echo

# ---------------------------------------------------------------------------
# 1. Provision WordPress + SQLite (cached across runs).
# ---------------------------------------------------------------------------
if [ ! -f "$WP/wp-settings.php" ]; then
	mkdir -p "$WORK" && cd "$WORK" || exit 1
	echo "Downloading WordPress..."
	curl -sL --max-time 300 -o wp.tar.gz https://wordpress.org/latest.tar.gz || exit 1
	tar xzf wp.tar.gz && rm -f wp.tar.gz

	echo "Adding the SQLite database drop-in..."
	curl -sL --max-time 300 -o sqlite.zip \
		https://downloads.wordpress.org/plugin/sqlite-database-integration.zip || exit 1
	unzip -oq sqlite.zip && rm -f sqlite.zip
	cp -r sqlite-database-integration "$WP/wp-content/plugins/"
	mkdir -p "$WP/wp-content/database"
	sed -e "s|{SQLITE_IMPLEMENTATION_FOLDER_PATH}|$WP/wp-content/plugins/sqlite-database-integration|" \
	    -e "s|{SQLITE_PLUGIN}|sqlite-database-integration/load.php|" \
	    sqlite-database-integration/db.copy > "$WP/wp-content/db.php"

	cat > "$WP/wp-config.php" <<PHPCONF
<?php
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'AUTH_KEY', 'k1' ); define( 'SECURE_AUTH_KEY', 'k2' );
define( 'LOGGED_IN_KEY', 'k3' ); define( 'NONCE_KEY', 'k4' );
define( 'AUTH_SALT', 's1' ); define( 'SECURE_AUTH_SALT', 's2' );
define( 'LOGGED_IN_SALT', 's3' ); define( 'NONCE_SALT', 's4' );
\$table_prefix = 'wp_';
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', '$WORK/debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_MEMORY_LIMIT', '128M' );
define( 'WP_MAX_MEMORY_LIMIT', '128M' );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
PHPCONF

	echo "Installing the site..."
	php -r '
	define("WP_INSTALLING", true);
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/"; $_SERVER["REQUEST_METHOD"]="GET";
	require $argv[1]."/wp-load.php";
	require ABSPATH."wp-admin/includes/upgrade.php";
	wp_install("TDWP Acceptance","admin","admin@example.com",true,"","adminpass123");
	' "$WP" >/dev/null 2>&1
fi

if [ ! -f "$WP/wp-settings.php" ]; then
	echo "error: WordPress provisioning failed" >&2
	exit 1
fi

# Helper: run PHP inside the WordPress runtime.
wp_php() { php -r "$1" "$WP" "${@:2}" 2>&1; }

# ---------------------------------------------------------------------------
# 2. Install and activate the built plugin.
# ---------------------------------------------------------------------------
rm -rf "$WP/wp-content/plugins/poker-tournament-import"
unzip -oq "$PLUGIN_ZIP" -d "$WP/wp-content/plugins/"
: > "$WORK/debug.log"

out="$(wp_php '
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/"; $_SERVER["REQUEST_METHOD"]="GET";
require $argv[1]."/wp-load.php";
if ( ! function_exists("activate_plugin") ) require_once ABSPATH."wp-admin/includes/plugin.php";
$r = activate_plugin("poker-tournament-import/poker-tournament-import.php");
echo is_wp_error($r) ? "ERR:".$r->get_error_message() : "OK";
')"
case "$out" in
	*OK*) ok "plugin activates in real WordPress without a fatal" ;;
	*)    bad "plugin activation failed: $out" ;;
esac

# ---------------------------------------------------------------------------
# 3. Gate defaults to OFF, and the rollup tables exist.
# ---------------------------------------------------------------------------
out="$(wp_php '
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
require $argv[1]."/wp-load.php";
global $wpdb;
$gate = Poker_Tournament_Import::tm_enabled() ? "on" : "off";
$need = 0;
foreach (["tdwp_tournament_players","tdwp_points_adjustments"] as $t) {
  if ($wpdb->get_var("SELECT COUNT(*) FROM sqlite_master WHERE type=\"table\" AND name=\"{$wpdb->prefix}$t\"")) $need++;
}
echo "gate=$gate tables=$need";
')"
[[ "$out" == *"gate=off"* ]] && ok "Tournament Manager defaults to OFF on a fresh install" \
                             || bad "gate did not default to OFF ($out)"
[[ "$out" == *"tables=2"* ]] && ok "rollup-critical tdwp_* tables created even with the module off" \
                             || bad "rollup tables missing ($out)"

# ---------------------------------------------------------------------------
# 4. THE acceptance behaviour: import a real .tdt with the module OFF and
#    confirm the statistics data marts are populated.
# ---------------------------------------------------------------------------
if [ -z "$FIXTURE" ]; then
	# Prefer a real tournament file; fall back to the committed minimal fixture.
	for cand in "$REPO_ROOT"/tdtfiles/*.tdt "$PLUGIN_DIR/tests/fixtures/minimal.tdt"; do
		if [ -f "$cand" ]; then FIXTURE="$cand"; break; fi
	done
fi

if [ -n "$FIXTURE" ] && [ -f "$FIXTURE" ]; then
	out="$(wp_php '
	define("WP_ADMIN", true);
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/"; $_SERVER["REQUEST_METHOD"]="GET";
	require $argv[1]."/wp-load.php";
	wp_set_current_user(1);
	global $wpdb;
	$parser = new Poker_Tournament_Parser($argv[2]);
	if ( ! $parser->parse_file() ) { echo "PARSE_FAIL"; exit; }
	$res = (new Poker_Tournament_Import_Admin())->import_tournament_data($parser->get_tournament_data(), $parser);
	$mart = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}poker_tournament_players");
	$roi  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}poker_player_roi");
	$neg  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}poker_tournament_players WHERE points < 0");
	printf("import=%s mart=%d roi=%d negative=%d",
		!empty($res["success"]) ? "ok" : "fail", $mart, $roi, $neg);
	' "$FIXTURE")"

	info "fixture: $(basename "$FIXTURE")"
	[[ "$out" == *"import=ok"* ]] && ok "a real .tdt imports with the module OFF" \
	                             || bad "import failed ($out)"
	if [[ "$out" =~ mart=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" -gt 0 ]; then
		ok "poker_tournament_players populated (${BASH_REMATCH[1]} rows) with the module OFF"
	else
		bad "participation mart empty after import ($out)"
	fi
	if [[ "$out" =~ roi=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" -gt 0 ]; then
		ok "poker_player_roi populated (${BASH_REMATCH[1]} rows) with the module OFF"
	else
		bad "ROI mart empty after import ($out)"
	fi
	[[ "$out" == *"negative=0"* ]] && ok "no negative points written (tdwp-brj regression)" \
	                              || bad "negative points found ($out)"
else
	info "no .tdt fixture found; skipping the import check"
fi

# ---------------------------------------------------------------------------
# 5. Shortcode surface.
# ---------------------------------------------------------------------------
out="$(wp_php '
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
require $argv[1]."/wp-load.php";
$stats = ["tournament_results","player_profile","season_standings","series_standings"];
$live  = ["tournament_clock","tdwp_leaderboard","tdwp_tournament_display","tdwp_live_clock"];
$s = 0; foreach ($stats as $x) if (shortcode_exists($x)) $s++;
$l = 0; foreach ($live  as $x) if (shortcode_exists($x)) $l++;
echo "stats=$s/",count($stats)," live=$l";
')"
[[ "$out" == *"stats=4/4"* ]] && ok "statistics shortcodes still registered with the module OFF" \
                             || bad "statistics shortcodes missing ($out)"
[[ "$out" == *"live=0"* ]]    && ok "live/display shortcodes not registered with the module OFF" \
                             || bad "live shortcodes leaked ($out)"

# ---------------------------------------------------------------------------
# 6. The reported symptom: diagnostic spam in debug.log.
# ---------------------------------------------------------------------------
for state in off on; do
	wp_php '
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
	require $argv[1]."/wp-load.php";
	update_option("tdwp_tournament_manager_enabled", $argv[2] === "on");
	' "$state" >/dev/null 2>&1

	: > "$WORK/debug.log"
	loaded=""
	for _ in 1 2 3; do
		loaded="$(wp_php '
		define("WP_ADMIN", true);
		$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/"; $_SERVER["REQUEST_METHOD"]="GET";
		require $argv[1]."/wp-load.php";
		// Core admin helpers a real wp-admin request would already have loaded;
		// without them WordPress own auth-check handler fatals under CLI.
		foreach (["screen.php","template.php","misc.php"] as $f) {
			$inc = ABSPATH."wp-admin/includes/".$f;
			if (file_exists($inc)) { require_once $inc; }
		}
		do_action("admin_enqueue_scripts","index.php");
		echo class_exists("Poker_Tournament_Import") ? "LOADED" : "NOT_LOADED";
		' 2>/dev/null | tail -c 32)"
	done

	spam="$(grep -E 'Display Manager|Template Engine|Dependency Manager|Admin Scripts Hook' "$WORK/debug.log" 2>/dev/null | wc -l | tr -d ' ')"
	spam="${spam:-0}"

	# A "no spam" result only means something if the plugin actually ran.
	if [[ "$loaded" != *LOADED* ]]; then
		bad "plugin did not load during the spam check (module $state) — result would be meaningless"
	elif [ "$spam" -eq 0 ]; then
		ok "no diagnostic spam in debug.log over 3 admin requests (module $state)"
	else
		bad "$spam diagnostic lines written over 3 admin requests (module $state)"
	fi
done

# ---------------------------------------------------------------------------
# 7. Memory: the module must measurably cost something.
# ---------------------------------------------------------------------------
peak_for() {
	wp_php '
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
	require $argv[1]."/wp-load.php";
	update_option("tdwp_tournament_manager_enabled", $argv[2] === "on");
	' "$1" >/dev/null 2>&1
	wp_php '
	define("WP_ADMIN", true);
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/"; $_SERVER["REQUEST_METHOD"]="GET";
	require $argv[1]."/wp-load.php";
	echo (int) round(memory_get_peak_usage(true)/1024);
	'
}

off_kb="$(peak_for off)"; on_kb="$(peak_for on)"
peak_for off >/dev/null   # leave the install in the shipped default state

if [[ "$off_kb" =~ ^[0-9]+$ ]] && [[ "$on_kb" =~ ^[0-9]+$ ]] && [ "$off_kb" -lt "$on_kb" ]; then
	ok "module OFF lowers peak memory: ${off_kb} KB vs ${on_kb} KB (saved $((on_kb-off_kb)) KB)"
else
	bad "expected a lower peak with the module off (off=${off_kb} on=${on_kb})"
fi

echo
printf "%d passed, %d failed\n" "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ] || exit 1
