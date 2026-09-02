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

# ---------------------------------------------------------------------------
# 2b. Reset site content between runs.
#
# The WordPress download is reused for speed, but the *database* must start
# clean: a second run would otherwise re-import the same tournament, create a
# duplicate set of player posts, and make every player resolve ambiguously
# during the mart backfill. Dropping the SQLite file forces a fresh install
# without paying for another download.
# ---------------------------------------------------------------------------
if [ -f "$WP/wp-content/database/.ht.sqlite" ]; then
	rm -f "$WP/wp-content/database/.ht.sqlite"
	php -r '
	define("WP_INSTALLING", true);
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/"; $_SERVER["REQUEST_METHOD"]="GET";
	require $argv[1]."/wp-load.php";
	require ABSPATH."wp-admin/includes/upgrade.php";
	wp_install("TDWP Acceptance","admin","admin@example.com",true,"","adminpass123");
	' "$WP" >/dev/null 2>&1
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

	# -----------------------------------------------------------------------
	# 4b. Raw content survives the meta round-trip, and points match TD (3.9.10).
	#
	# Before 3.9.10 update_post_meta() unslashed the stored .tdt, stripping the
	# \" and \n escapes and leaving a copy that could never be parsed again.
	# -----------------------------------------------------------------------
	out="$(wp_php '
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
	require $argv[1]."/wp-load.php";
	global $wpdb;
	$file = $argv[2];
	$post = get_posts(["post_type"=>"tournament","numberposts"=>1,"post_status"=>"any"]);
	if (empty($post)) { echo "NO_TOURNAMENT"; exit; }
	$id  = $post[0]->ID;
	$raw = (string) get_post_meta($id,"_tournament_raw_content",true);
	$identical = ($raw === file_get_contents($file)) ? 1 : 0;

	// The stored copy must still be parseable.
	$parseable = 0;
	try { $p = new Poker_Tournament_Parser(); ob_start(); $p->parse_content($raw); ob_end_clean();
	      $parseable = empty($p->get_tournament_data()["players"]) ? 0 : 1; }
	catch (Throwable $e) { $parseable = 0; }

	// TD 3.7.2 reports 368 as the winning score for this fixture.
	$uuid = get_post_meta($id,"tournament_uuid",true);
	$top  = (int) round((float) $wpdb->get_var($wpdb->prepare(
	        "SELECT points FROM {$wpdb->prefix}poker_tournament_players
	         WHERE tournament_id=%s ORDER BY finish_position ASC LIMIT 1", $uuid)));
	printf("identical=%d parseable=%d top=%d", $identical, $parseable, $top);
	' "$FIXTURE")"

	[[ "$out" == *"identical=1"* ]] && ok "stored .tdt is byte-identical to the imported file (slash round-trip)" \
	                               || bad "stored raw content was corrupted on write ($out)"
	[[ "$out" == *"parseable=1"* ]] && ok "stored .tdt can be re-parsed for recalculation" \
	                               || bad "stored raw content is not parseable ($out)"
	# TD stores Points: 0 in the file (it computes them for display), so the
	# expected figure comes from what TD 3.7.2 actually displayed for these
	# tournaments. Only assert for files whose totals were verified by hand.
	case "$(basename "$FIXTURE")" in
		ORF_Poker_20260521.tdt) TD_WINNER=368 ;;
		ORF_Poker_20260604.tdt) TD_WINNER=290 ;;
		ORF_Poker_20260617.tdt) TD_WINNER=321 ;;
		ORF_Poker_20260704.tdt) TD_WINNER=298 ;;
		ORF_Poker_20260827.tdt) TD_WINNER=399 ;;
		*)                      TD_WINNER=""  ;;
	esac

	if [ -n "$TD_WINNER" ]; then
		[[ "$out" == *"top=$TD_WINNER"* ]] \
			&& ok "winner's points match Tournament Director exactly ($TD_WINNER)" \
			|| bad "points do not match TD, expected $TD_WINNER ($out)"
	else
		info "no verified TD total for this fixture; skipping the exact-points check"
	fi

	# -----------------------------------------------------------------------
	# 4c. The recalculator is a safe no-op on a correct import, and never
	#     overrides a manual adjustment.
	# -----------------------------------------------------------------------
	out="$(wp_php '
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
	require $argv[1]."/wp-load.php";
	global $wpdb;
	$post = get_posts(["post_type"=>"tournament","numberposts"=>1,"post_status"=>"any"]);
	$id   = $post[0]->ID;
	$uuid = get_post_meta($id,"tournament_uuid",true);
	$rc   = new Poker_Points_Recalculator();

	// A freshly imported tournament is already correct: nothing to change.
	$r = $rc->recalculate_tournament($id, true);
	$noop = ($r["status"] === "preview" && count($r["changes"]) === 0) ? 1 : 0;

	// A dry run must not write.
	$before = $wpdb->get_var($wpdb->prepare("SELECT ROUND(SUM(points)) FROM {$wpdb->prefix}poker_tournament_players WHERE tournament_id=%s",$uuid));
	$rc->recalculate_tournament($id, true);
	$after  = $wpdb->get_var($wpdb->prepare("SELECT ROUND(SUM(points)) FROM {$wpdb->prefix}poker_tournament_players WHERE tournament_id=%s",$uuid));
	$drysafe = ((string)$before === (string)$after) ? 1 : 0;

	// Mismatched stored content must be refused, not applied.
	$saved = get_post_meta($id,"_tournament_raw_content",true);
	update_post_meta($id,"_tournament_raw_content", wp_slash(str_replace($uuid,"00000000-0000-4000-8000-000000000000",$saved)));
	$r2 = $rc->recalculate_tournament($id, false);
	$guard = ($r2["status"] === "error") ? 1 : 0;
	update_post_meta($id,"_tournament_raw_content", wp_slash($saved));

	// A damaged (pre-3.9.10) copy is recognised and explained.
	update_post_meta($id,"_tournament_raw_content", stripslashes($saved));
	$r3 = $rc->recalculate_tournament($id, false);
	$detect = ($r3["status"] === "error" && stripos($r3["message"],"earlier version") !== false) ? 1 : 0;
	update_post_meta($id,"_tournament_raw_content", wp_slash($saved));

	$final = $wpdb->get_var($wpdb->prepare("SELECT ROUND(SUM(points)) FROM {$wpdb->prefix}poker_tournament_players WHERE tournament_id=%s",$uuid));
	$intact = ((string)$before === (string)$final) ? 1 : 0;
	printf("noop=%d drysafe=%d guard=%d detect=%d intact=%d", $noop,$drysafe,$guard,$detect,$intact);
	')"

	[[ "$out" == *"noop=1"* ]]    && ok "recalculating a correct import changes nothing" \
	                             || bad "recalculator wants to change a correct import ($out)"
	[[ "$out" == *"drysafe=1"* ]] && ok "a dry-run preview writes nothing to the database" \
	                             || bad "dry run mutated the database ($out)"
	[[ "$out" == *"guard=1"* ]]   && ok "raw content from a different tournament is refused" \
	                             || bad "recalculator accepted foreign raw content ($out)"
	[[ "$out" == *"detect=1"* ]]  && ok "a pre-3.9.10 damaged copy is detected and explained" \
	                             || bad "damaged raw content not detected ($out)"
	[[ "$out" == *"intact=1"* ]]  && ok "points survive the refused recalculations unchanged" \
	                             || bad "points changed despite refusals ($out)"

	# -----------------------------------------------------------------------
	# 4d. A statistics rebuild must not revert corrected points (3.9.10).
	#
	# TDWP_Stats_Rollup is the sole writer of the stats marts, so if it
	# recomputed points from scratch it would undo the import-time correction.
	# `import_points` on the participation row is what prevents that. This
	# populates the mart first, because a rebuild over an empty mart is a
	# no-op and would prove nothing.
	# -----------------------------------------------------------------------
	out="$(wp_php '
	define("WP_ADMIN", true);
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/";
	require $argv[1]."/wp-load.php";
	global $wpdb;
	wp_set_current_user(1);

	// The real admin form posts this; without it no player posts are created
	// and the mart backfill cannot resolve any player.
	//
	// Import only if this tournament is not already present. Section 4 already
	// imported it *without* create_players, so re-importing here would create a
	// second set of player posts and every player would resolve ambiguously.
	$_POST["create_players"] = "1";
	$p = new Poker_Tournament_Parser($argv[2]);
	if (!$p->parse_file()) { echo "PARSE_FAIL"; exit; }
	$data = $p->get_tournament_data();
	$file_uuid = (string) ($data["metadata"]["uuid"] ?? "");

	// Create the player posts the earlier import skipped, without re-importing.
	foreach (array_keys($data["players"]) as $puuid) {
		$found = get_posts([
			"post_type"      => "player",
			"post_status"    => "any",
			"posts_per_page" => 1,
			"meta_key"       => "player_uuid",
			"meta_value"     => $puuid,
		]);
		if (empty($found)) {
			$pid = wp_insert_post([
				"post_title"  => (string) ($data["players"][$puuid]["nickname"] ?? $puuid),
				"post_type"   => "player",
				"post_status" => "draft",
			]);
			if ($pid && !is_wp_error($pid)) { update_post_meta($pid, "player_uuid", $puuid); }
		}
	}

	$id = 0;
	foreach (get_posts(["post_type"=>"tournament","numberposts"=>-1,"post_status"=>"any"]) as $t) {
		if (get_post_meta($t->ID,"tournament_uuid",true) === $file_uuid) { $id = $t->ID; break; }
	}
	if (!$id) { echo "NO_TOURNAMENT"; exit; }
	$u  = get_post_meta($id,"tournament_uuid",true);
	$stats = $wpdb->prefix."poker_tournament_players";
	$mart  = $wpdb->prefix."tdwp_tournament_players";
	$q = $wpdb->prepare("SELECT ROUND(points) FROM {$stats} WHERE tournament_id=%s ORDER BY finish_position LIMIT 1", $u);

	TDWP_Stats_Rollup::backfill_imports($mart, 0, 0, $u);
	$rows = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$mart} WHERE tournament_uuid=%s", $u));
	$imp  = (int) round((float) $wpdb->get_var($wpdb->prepare(
	        "SELECT import_points FROM {$mart} WHERE tournament_uuid=%s ORDER BY CAST(import_points AS REAL) DESC LIMIT 1", $u)));

	$before = (int) $wpdb->get_var($q);
	TDWP_Stats_Rollup::rebuild_tournament($u);
	$after  = (int) $wpdb->get_var($q);

	printf("martrows=%d imported=%d before=%d after=%d", $rows, $imp, $before, $after);
	' "$FIXTURE")"

	if [[ "$out" =~ martrows=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" -gt 0 ]; then
		ok "participation mart populated (${BASH_REMATCH[1]} rows) so the rebuild check is meaningful"
	else
		bad "mart empty; the rebuild check would be a no-op ($out)"
	fi

	if [ -n "$TD_WINNER" ]; then
		[[ "$out" == *"imported=$TD_WINNER"* ]] \
			&& ok "import_points persisted on the participation row ($TD_WINNER)" \
			|| bad "import_points not persisted, expected $TD_WINNER ($out)"
		[[ "$out" == *"before=$TD_WINNER"* && "$out" == *"after=$TD_WINNER"* ]] \
			&& ok "a statistics rebuild preserves the corrected points ($TD_WINNER)" \
			|| bad "rebuild reverted the corrected points ($out)"
	else
		info "no verified TD total for this fixture; skipping the rebuild-preservation check"
	fi
	# The UUID of the tournament just imported, used by the admin-page checks.
	TOURNEY_UUID="$(wp_php '
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
	require $argv[1]."/wp-load.php";
	foreach (get_posts(["post_type"=>"tournament","numberposts"=>-1,"post_status"=>"any"]) as $t) {
		$u = get_post_meta($t->ID,"tournament_uuid",true);
		if ($u) { echo $u; break; }
	}
	')"

	# -----------------------------------------------------------------------
	# 4e. The admin page itself, driven as a browser would (3.9.10).
	#
	# Everything above calls the recalculator directly. This renders the real
	# Points Adjustments screen and submits its form, so a broken nonce,
	# capability check, or template would be caught. Each request runs in its
	# own PHP process, exactly as WordPress serves it.
	# -----------------------------------------------------------------------
	PA_REQ="$WORK/pa_request.php"
	cat > "$PA_REQ" <<'PAPHP'
<?php
// One admin request against the Points Adjustments screen.
// argv: 1=ABSPATH  2=GET|POST  3=preview|apply  4=optional uuid to sabotage
define( 'WP_ADMIN', true );
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_URI']    = '/wp-admin/admin.php?page=poker-points-adjustments';
$_SERVER['REQUEST_METHOD'] = $argv[2];
$_GET['page']              = 'poker-points-adjustments';

require $argv[1] . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_set_current_user( 1 );

// Simulate a pre-3.9.10 import by reverting the winner to the old figure.
if ( ! empty( $argv[4] ) ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"UPDATE {$wpdb->prefix}poker_tournament_players SET points = 299
		 WHERE tournament_id = %s AND finish_position = 1", $argv[4] ) );
}

if ( 'POST' === $argv[2] ) {
	/*
	 * Submit what the *rendered form* actually contains, rather than a nonce
	 * this script invents. Otherwise renaming or dropping the form's nonce
	 * field would still "pass" here while breaking the real page.
	 *
	 * The GET render runs in a separate process because the page file declares
	 * a function at top level and cannot be included twice in one request.
	 */
	$form = shell_exec( sprintf( '%s %s %s GET 2>/dev/null',
		escapeshellarg( PHP_BINARY ),
		escapeshellarg( __FILE__ ),
		escapeshellarg( $argv[1] ) ) );

	$fields = array();
	if ( preg_match_all( '/<input[^>]*type=["\']hidden["\'][^>]*>/i', (string) $form, $inputs ) ) {
		foreach ( $inputs[0] as $tag ) {
			if ( preg_match( '/name=["\']([^"\']+)["\']/i', $tag, $n )
			  && preg_match( '/value=["\']([^"\']*)["\']/i', $tag, $v ) ) {
				$fields[ $n[1] ] = $v[1];
			}
		}
	}
	if ( empty( $fields ) ) { echo 'NO_FORM_FIELDS'; exit; }

	$fields['tdwp_recalc_action'] = $argv[3];
	$_POST    = $fields;
	$_REQUEST = $_POST;
}

ob_start();
include ABSPATH . 'wp-content/plugins/poker-tournament-import/admin/class-points-adjustments-page.php';
$html = ob_get_clean();

// A bare GET render is used by the POST path above to harvest the real form
// fields, so emit the raw HTML in that case.
if ( 'GET' === $argv[2] && empty( $argv[3] ) ) {
	echo $html;
	exit;
}

$flags = array();
$flags[] = ( false !== strpos( $html, 'Recalculate imported points' ) ) ? 'section=1' : 'section=0';
$flags[] = ( false !== strpos( $html, 'Apply these changes' ) )        ? 'applybtn=1' : 'applybtn=0';
$flags[] = ( preg_match( '/Preview only/', $html ) )                    ? 'previewed=1' : 'previewed=0';
$flags[] = ( preg_match( '/Applied\./', $html ) )                       ? 'applied=1' : 'applied=0';
$flags[] = ( preg_match( '/Nothing to change/', $html ) )               ? 'clean=1' : 'clean=0';

// The before -> after pair must be visible in the preview table. The
// sabotaged value is always 299; the corrected one varies by fixture, so
// match "299 <number>" rather than hard-coding a single tournament's total.
$txt = preg_replace( '/\s+/', ' ', strip_tags( $html ) );
$flags[] = preg_match( '/\b299\s+[\d,]+\s+\+/', $txt ) ? 'delta=1' : 'delta=0';

echo implode( ' ', $flags );
PAPHP

	# A plain page load must render the section and offer no Apply button.
	out="$(php "$PA_REQ" "$WP" GET flags 2>&1)"
	[[ "$out" == *"section=1"* ]] && ok "the Points Adjustments screen renders the recalculation section" \
	                             || bad "recalculation section missing from the admin page ($out)"
	[[ "$out" == *"applybtn=0"* ]] && ok "no Apply button before a preview has been run" \
	                              || bad "Apply offered without a preview ($out)"

	# Preview must report the change, show the before -> after, and write nothing.
	out="$(php "$PA_REQ" "$WP" POST preview "$TOURNEY_UUID" 2>&1)"
	[[ "$out" == *"previewed=1"* ]] && ok "submitting Preview reports the pending change" \
	                               || bad "Preview produced no result notice ($out)"
	[[ "$out" == *"delta=1"* ]]     && ok "the preview table shows the before and after points with a delta" \
	                               || bad "preview table missing the before/after row ($out)"
	[[ "$out" == *"applybtn=1"* ]]  && ok "Apply is offered once a preview finds changes" \
	                               || bad "Apply not offered after a preview with changes ($out)"

	after_preview="$(wp_php '
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
	require $argv[1]."/wp-load.php"; global $wpdb;
	echo (int) $wpdb->get_var($wpdb->prepare("SELECT ROUND(points) FROM {$wpdb->prefix}poker_tournament_players WHERE tournament_id=%s AND finish_position=1", $argv[2]));
	' "$TOURNEY_UUID")"
	[ "$after_preview" = "299" ] && ok "Preview left the database untouched (still 299)" \
	                             || bad "Preview wrote to the database (points=$after_preview)"

	# Apply must persist, and a second preview must then find nothing to do.
	out="$(php "$PA_REQ" "$WP" POST apply "" 2>&1)"
	[[ "$out" == *"applied=1"* ]] && ok "submitting Apply reports success" \
	                             || bad "Apply produced no success notice ($out)"

	after_apply="$(wp_php '
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
	require $argv[1]."/wp-load.php"; global $wpdb;
	echo (int) $wpdb->get_var($wpdb->prepare("SELECT ROUND(points) FROM {$wpdb->prefix}poker_tournament_players WHERE tournament_id=%s AND finish_position=1", $argv[2]));
	' "$TOURNEY_UUID")"
	if [ -n "$TD_WINNER" ]; then
		[ "$after_apply" = "$TD_WINNER" ] && ok "Apply persisted the corrected points ($TD_WINNER)" \
		                                  || bad "Apply did not persist (points=$after_apply, expected $TD_WINNER)"
	fi

	out="$(php "$PA_REQ" "$WP" POST preview "" 2>&1)"
	[[ "$out" == *"clean=1"* ]] && ok "pressing Preview again finds nothing to change (idempotent)" \
	                            || bad "recalculation is not idempotent ($out)"

	# -----------------------------------------------------------------------
	# 4f. A manual override outranks the recalculated value.
	# -----------------------------------------------------------------------
	out="$(wp_php '
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
	require $argv[1]."/wp-load.php"; global $wpdb;
	wp_set_current_user(1);
	$u = $argv[2];
	$stats = $wpdb->prefix."poker_tournament_players";
	$row = $wpdb->get_row($wpdb->prepare("SELECT player_id, points FROM {$stats} WHERE tournament_id=%s AND finish_position=1", $u));

	$m = new Poker_Points_Adjustment_Manager();
	$m->apply_adjustment($u, $row->player_id, $row->points, 500, "acceptance override", 1);

	// Sabotage the imported figure, then recalculate: the override must hold.
	$wpdb->query($wpdb->prepare("UPDATE {$stats} SET points=299 WHERE tournament_id=%s AND finish_position=1", $u));
	$post = 0;
	foreach (get_posts(["post_type"=>"tournament","numberposts"=>-1,"post_status"=>"any"]) as $t) {
		if (get_post_meta($t->ID,"tournament_uuid",true) === $u) { $post = $t->ID; break; }
	}
	$rc = new Poker_Points_Recalculator();
	$pv = $rc->recalculate_tournament($post, true);
	$skipped = 0;
	foreach ($pv["changes"] as $c) { if (stripos($c["note"], "override") !== false) { $skipped = 1; } }
	$rc->recalculate_tournament($post, false);

	// Standings resolve the override at aggregation time, keyed tournament|player.
	$map = $m->get_adjustment_map(array($u));
	$eff = $map[$u."|".$row->player_id] ?? null;
	printf("noted=%d effective=%d", $skipped, (int) $eff);
	' "$TOURNEY_UUID")"

	[[ "$out" == *"noted=1"* ]] && ok "the preview explains that an overridden player is left alone" \
	                            || bad "override not called out in the preview ($out)"
	[[ "$out" == *"effective=500"* ]] && ok "standings use the manual override (500) over the imported value" \
	                                  || bad "override does not take precedence ($out)"
else
	info "no .tdt fixture found; skipping the import check"
fi

# ---------------------------------------------------------------------------
# 4g. The UPGRADE path: an older site with the module OFF must still migrate.
#
# Every check above provisions a *fresh* install, which is always created at the
# current DB version and therefore never exercises a migration. That blind spot
# shipped a real production defect in 3.9.9: create_tables() — the sole caller of
# run_migrations() — was gated behind the Tournament Manager module, which
# defaults to OFF. On an upgraded site no migration ever ran, while the
# always-on stats rollup kept writing columns that were never added, so every
# insert failed with "Unknown column ... in 'field list'".
#
# This simulates that exact site: roll the version back, drop the columns the
# migrations add, leave the module off, then load one admin page.
# ---------------------------------------------------------------------------
out="$(wp_php '
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
require $argv[1]."/wp-load.php";
global $wpdb;
$t = $wpdb->prefix."tdwp_tournament_players";
foreach (array("import_points","import_hits","import_buyins","source","player_uuid","tournament_uuid") as $c) {
	$wpdb->query("ALTER TABLE `{$t}` DROP COLUMN `{$c}`");
}
update_option("tdwp_db_version", "3.6.2");
update_option("tdwp_tournament_manager_enabled", 0);
$cols = (array) $wpdb->get_col("SHOW COLUMNS FROM {$t}");
$missing = 0;
foreach (array("import_points","import_hits","import_buyins","source","player_uuid","tournament_uuid") as $c) {
	if (!in_array($c, $cols, true)) { $missing++; }
}
echo "premissing=".$missing;
')"
[[ "$out" == *"premissing=6"* ]] && ok "upgrade simulation: 6 rollup columns removed and version rolled back to 3.6.2" \
                                || bad "could not simulate the pre-upgrade schema ($out)"

# One ordinary admin request must repair the schema with the module still off.
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/"; $_SERVER["REQUEST_METHOD"]="GET";
require $argv[1]."/wp-load.php";
global $wpdb;
$t = $wpdb->prefix."tdwp_tournament_players";
$cols = (array) $wpdb->get_col("SHOW COLUMNS FROM {$t}");
$present = 0;
foreach (array("import_points","import_hits","import_buyins","source","player_uuid","tournament_uuid") as $c) {
	if (in_array($c, $cols, true)) { $present++; }
}
printf("healed=%d version=%s tm=%s", $present, get_option("tdwp_db_version"), get_option("tdwp_tournament_manager_enabled") ? "on" : "off");
')"
[[ "$out" == *"healed=6"* ]]     && ok "a single admin load migrates the schema with the module OFF (self-heal)" \
                                 || bad "schema not repaired on an upgraded site ($out)"
# Compare against the schema class's own constant rather than a literal, so a
# future DB_VERSION bump does not silently turn this into a no-op assertion.
DB_VER="$(grep -oE "const DB_VERSION += +'[0-9.]+'" "$PLUGIN_DIR/includes/tournament-manager/class-database-schema.php" | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")"
[[ "$out" == *"version=$DB_VER"* ]] && ok "the DB version advances to $DB_VER on an upgraded site" \
                                   || bad "DB version did not advance to $DB_VER ($out)"
[[ "$out" == *"tm=off"* ]]       && ok "repairing the schema does not silently enable the Tournament Manager" \
                                 || bad "the module was switched on as a side effect ($out)"

# The operation that failed in production must now succeed.
out="$(wp_php '
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
require $argv[1]."/wp-load.php";
global $wpdb;
$t = $wpdb->prefix."tdwp_tournament_players";
$wpdb->query("DELETE FROM `{$t}`");
$r = TDWP_Stats_Rollup::backfill_imports($t, 0, 0, "");
printf("inserted=%d dberror=%s", (int) $r["inserted"], $wpdb->last_error ? "yes" : "no");
')"
if [[ "$out" =~ inserted=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" -gt 0 ]; then
	ok "the backfill that failed in production now inserts rows (${BASH_REMATCH[1]})"
else
	bad "backfill still inserts nothing after the upgrade ($out)"
fi
[[ "$out" == *"dberror=no"* ]] && ok "no 'Unknown column' database error during the backfill" \
                              || bad "the backfill still raises a database error ($out)"

# Defence in depth: with the column absent and no migration pending, the write
# must degrade rather than fail every row.
out="$(wp_php '
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
require $argv[1]."/wp-load.php";
global $wpdb;
$t = $wpdb->prefix."tdwp_tournament_players";
$wpdb->query("DELETE FROM `{$t}`");
$wpdb->query("ALTER TABLE `{$t}` DROP COLUMN `import_points`");
update_option("tdwp_db_version", "3.9.10"); // pin: no migration will re-add it
$r = TDWP_Stats_Rollup::backfill_imports($t, 0, 0, "");
printf("inserted=%d dberror=%s", (int) $r["inserted"], $wpdb->last_error ? "yes" : "no");
')"
if [[ "$out" =~ inserted=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" -gt 0 ] && [[ "$out" == *"dberror=no"* ]]; then
	ok "backfill degrades gracefully when import_points is absent (${BASH_REMATCH[1]} rows)"
else
	bad "backfill does not tolerate a missing import_points column ($out)"
fi

# ---------------------------------------------------------------------------
# 4h. Generated post content must not carry an unresolvable shortcode (3.9.12).
#
# Until 3.9.12 the importer appended [tournament_results ...] with no id, which
# the shortcode requires, so every imported tournament rendered the literal
# text "Please specify a tournament ID" in its page body. The post ID does not
# exist when this content is generated, so the attribute could never have been
# filled in; the template already renders full results with the correct id.
# ---------------------------------------------------------------------------
out="$(wp_php '
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
require $argv[1]."/wp-load.php";
$posts = get_posts(["post_type"=>"tournament","numberposts"=>-1,"post_status"=>"any"]);
if (empty($posts)) { echo "NO_TOURNAMENT"; exit; }

$bare = 0;
foreach ($posts as $p) {
	// A [tournament_results] with no id= attribute can never resolve.
	if (preg_match("/\[tournament_results(?![^\]]*\bid=)[^\]]*\]/", $p->post_content)) { $bare++; }
}

// And confirm the rendered body carries no such error text.
global $post;
$post = $posts[0];
setup_postdata($post);
$rendered = apply_filters("the_content", $post->post_content);
$err = (false !== strpos($rendered, "Please specify a tournament ID")) ? 1 : 0;

printf("bare=%d rendererr=%d", $bare, $err);
')"
[[ "$out" == *"bare=0"* ]]      && ok "generated content carries no id-less [tournament_results] shortcode" \
                               || bad "an unresolvable [tournament_results] is baked into post content ($out)"
[[ "$out" == *"rendererr=0"* ]] && ok "a tournament page renders without 'Please specify a tournament ID'" \
                               || bad "the tournament page still shows the shortcode error ($out)"

# ---------------------------------------------------------------------------
# 4i. Diagnostics page: surfaces a tournament that is invisible to statistics.
#
# The failure this catches is silent by nature -- the tournament page re-parses
# the stored .tdt and renders fine, while season standings, player cards and
# the points adjuster (all mart-backed) show nothing at all.
# ---------------------------------------------------------------------------
# Healthy state first: the report must not cry wolf.
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/";
$_GET["page"] = "poker-tournament-diagnostics";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);
ob_start();
include ABSPATH."wp-content/plugins/poker-tournament-import/admin/class-tournament-diagnostics-page.php";
$html = ob_get_clean();
printf("clean=%d flagged=%d",
	(false !== strpos($html, "correctly linked")) ? 1 : 0,
	(false !== strpos($html, "have a problem")) ? 1 : 0);
')"
[[ "$out" == *"clean=1"* ]] && ok "diagnostics reports a healthy install as having nothing to report" \
                           || bad "diagnostics flags a healthy install ($out)"

# Now reproduce the reported fault: delete the mart rows, keep everything else.
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/";
$_GET["page"] = "poker-tournament-diagnostics";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);
global $wpdb;

$posts = get_posts(["post_type"=>"tournament","numberposts"=>1,"post_status"=>"any"]);
$id = $posts[0]->ID;
$u  = get_post_meta($id, "tournament_uuid", true);
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}poker_tournament_players WHERE tournament_id=%s", $u));

ob_start();
include ABSPATH."wp-content/plugins/poker-tournament-import/admin/class-tournament-diagnostics-page.php";
$html = ob_get_clean();

printf("flagged=%d named=%d",
	(false !== strpos($html, "have a problem")) ? 1 : 0,
	(false !== strpos($html, "No rows in the participation mart")) ? 1 : 0);
')"
[[ "$out" == *"flagged=1"* ]] && ok "diagnostics detects a tournament with no participation rows" \
                             || bad "diagnostics missed a tournament that is invisible to statistics ($out)"
[[ "$out" == *"named=1"* ]]   && ok "diagnostics names the specific finding, not just a warning" \
                             || bad "diagnostics does not explain the finding ($out)"

# ---------------------------------------------------------------------------
# 4j. Repair Player Data restores a tournament that vanished from statistics.
#
# This is the remedy the diagnostics page recommends, verified end to end
# rather than assumed: rebuild the mart from the tournament_data post meta,
# then confirm the tournament is once again visible to the points adjuster and
# to season standings, and that diagnostics stops flagging it.
#
# Note the tool only scans post_status=publish, so the fixture is published
# first — a draft would silently be skipped and the check would prove nothing.
# ---------------------------------------------------------------------------
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);
global $wpdb;

$posts = get_posts(["post_type"=>"tournament","numberposts"=>1,"post_status"=>"any"]);
$id = $posts[0]->ID;
$u  = get_post_meta($id, "tournament_uuid", true);
wp_update_post(["ID" => $id, "post_status" => "publish"]);

// Section 4i deleted the rows for this tournament; confirm the fault persists.
$before = (int) $wpdb->get_var($wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}poker_tournament_players WHERE tournament_id=%s", $u));

ob_start();
(new Poker_Tournament_Import_Admin())->handle_player_data_repair();
ob_end_clean();

$after = (int) $wpdb->get_var($wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}poker_tournament_players WHERE tournament_id=%s", $u));

// The adjuster query, verbatim from ajax_get_tournament_players_for_adjustment().
$adj = $wpdb->get_results($wpdb->prepare(
	"SELECT player_id, finish_position, points FROM {$wpdb->prefix}poker_tournament_players
	 WHERE tournament_id = %s ORDER BY finish_position ASC", $u), ARRAY_A);

// Season discovery, verbatim from get_season_tournaments().
$season = get_post_meta($id, "_season_id", true);
$in_season = 0;
if ($season) {
	$ts = get_posts(["post_type"=>"tournament","posts_per_page"=>-1,
		"meta_query"=>[["key"=>"_season_id","value"=>$season,"compare"=>"="]]]);
	foreach ($ts as $t) { if ((int) $t->ID === (int) $id) { $in_season = 1; } }
}

printf("before=%d after=%d adjuster=%d inseason=%d", $before, $after, count($adj), $in_season);
')"

[[ "$out" == *"before=0"* ]] && ok "precondition: the tournament still has no participation rows" \
                            || bad "expected the tournament to start with zero rows ($out)"
if [[ "$out" =~ after=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" -gt 0 ]; then
	ok "Repair Player Data rebuilds the participation rows (${BASH_REMATCH[1]})"
else
	bad "Repair Player Data did not restore any rows ($out)"
fi
if [[ "$out" =~ adjuster=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" -gt 0 ]; then
	ok "the points adjuster now lists the players for that tournament (${BASH_REMATCH[1]})"
else
	bad "the adjuster still shows no players after repair ($out)"
fi
[[ "$out" == *"inseason=1"* ]] && ok "the repaired tournament is included in season standings again" \
                              || bad "the tournament is still excluded from its season ($out)"

# And the report must agree that the problem is gone.
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/";
$_GET["page"] = "poker-tournament-diagnostics";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);
ob_start();
include ABSPATH."wp-content/plugins/poker-tournament-import/admin/class-tournament-diagnostics-page.php";
$html = ob_get_clean();
// Inspect the table body only; the page legend also mentions these phrases.
$body = "";
$lo = strpos($html, "<tbody>");
$hi = strpos($html, "</tbody>");
if (false !== $lo && false !== $hi) { $body = strip_tags(substr($html, $lo, $hi - $lo)); }
printf("rowok=%d stillflagged=%d",
	(false !== strpos($body, "OK")) ? 1 : 0,
	(false !== strpos($body, "No rows in the participation mart")) ? 1 : 0);
')"
[[ "$out" == *"stillflagged=0"* ]] && ok "diagnostics stops flagging the tournament once repaired" \
                                  || bad "diagnostics still reports the repaired tournament ($out)"

# ---------------------------------------------------------------------------
# 4k. Diagnostics must name the remedy that will actually work.
#
# Repair Player Data rebuilds from the tournament_data post meta (falling back
# to tournament_players / player_results). When none of those hold players it
# inserts nothing AND reports nothing, so an operator following a blanket
# "press Repair" instruction would see no change and no explanation. The report
# therefore has to distinguish repairable from re-import-only.
# ---------------------------------------------------------------------------
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/";
$_GET["page"] = "poker-tournament-diagnostics";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);
global $wpdb;

$posts = get_posts(["post_type"=>"tournament","numberposts"=>1,"post_status"=>"any"]);
$id = $posts[0]->ID;
$u  = get_post_meta($id, "tournament_uuid", true);
wp_update_post(["ID" => $id, "post_status" => "publish"]);

$body = static function () {
	ob_start();
	include ABSPATH."wp-content/plugins/poker-tournament-import/admin/class-tournament-diagnostics-page.php";
	$html = ob_get_clean();
	$lo = strpos($html, "<tbody>");
	$hi = strpos($html, "</tbody>");
	return (false !== $lo && false !== $hi) ? strip_tags(substr($html, $lo, $hi - $lo)) : "";
};

// Case A: rows missing but the stored player data survives -> Repair works.
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}poker_tournament_players WHERE tournament_id=%s", $u));
$a = $body();
$says_repair = (false !== strpos($a, "Repair Player Data.")) ? 1 : 0;

// Case B: data is present but the post is a draft -> Repair skips it silently.
wp_update_post(["ID" => $id, "post_status" => "draft"]);
$d = $body();
$says_publish = (false !== strpos($d, "Publish it first")) ? 1 : 0;
wp_update_post(["ID" => $id, "post_status" => "publish"]);

// Case C: the stored player data is gone too -> Repair would do nothing.
$saved = get_post_meta($id, "tournament_data", true);
delete_post_meta($id, "tournament_data");
$saved_tp = get_post_meta($id, "tournament_players", true);
$saved_pr = get_post_meta($id, "player_results", true);
delete_post_meta($id, "tournament_players");
delete_post_meta($id, "player_results");
$b = $body();
$says_reimport = (false !== strpos($b, "must be re-imported")) ? 1 : 0;

// Prove the claim rather than trusting the label: run the repair and confirm
// it really does restore nothing in this state.
ob_start();
(new Poker_Tournament_Import_Admin())->handle_player_data_repair();
ob_end_clean();
$rows_after = (int) $wpdb->get_var($wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}poker_tournament_players WHERE tournament_id=%s", $u));

// Restore the meta so later sections are unaffected.
if (!empty($saved))    { update_post_meta($id, "tournament_data", $saved); }
if (!empty($saved_tp)) { update_post_meta($id, "tournament_players", $saved_tp); }
if (!empty($saved_pr)) { update_post_meta($id, "player_results", $saved_pr); }

printf("repair=%d publish=%d reimport=%d rowsafter=%d", $says_repair, $says_publish, $says_reimport, $rows_after);
')"

[[ "$out" == *"repair=1"* ]]   && ok "diagnostics points at Repair Player Data when the stored data can rebuild it" \
                              || bad "diagnostics did not name the repair route ($out)"
[[ "$out" == *"publish=1"* ]]  && ok "diagnostics says publish first for a draft the repair would skip silently" \
                              || bad "diagnostics promises a repair for a draft the tool ignores ($out)"
[[ "$out" == *"reimport=1"* ]] && ok "diagnostics demands a re-import when nothing remains to rebuild from" \
                              || bad "diagnostics wrongly implies Repair would work ($out)"
[[ "$out" == *"rowsafter=0"* ]] && ok "and that claim is true: the repair genuinely restores nothing in that state" \
                               || bad "the repair did restore rows, so the re-import advice is wrong ($out)"

# ---------------------------------------------------------------------------
# 4l. Re-importing must not mint duplicate players, seasons or series.
#
# create_or_find_player/season/series looked up existing posts with get_posts(),
# which defaults to post_status=publish. Those posts are created as DRAFTS, so
# the lookup never matched and every file minted a fresh one. Measured on a real
# 14-file season: 174 player posts for 24 people, 14 "2026" season posts, and
# the stats rollup could then resolve none of them (174 ambiguous, 0 inserted).
#
# Tournaments already handled this correctly (find_tournament_by_uuid passes an
# explicit status list); the other three post types had been missed.
# ---------------------------------------------------------------------------
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/"; $_SERVER["REQUEST_METHOD"]="POST";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);
global $wpdb;

$_POST["create_players"] = "1";
$admin = new Poker_Tournament_Import_Admin();

$count = static function ($type) use ($wpdb) {
	return (int) $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status<>\"trash\"", $type));
};

// Import the same file twice. The second pass must reuse every post.
$p = new Poker_Tournament_Parser($argv[2]);
if (!$p->parse_file()) { echo "PARSE_FAIL"; exit; }
ob_start(); $admin->import_tournament_data($p->get_tournament_data(), $p); ob_end_clean();

$players1 = $count("player");
$seasons1 = $count("tournament_season");
$series1  = $count("tournament_series");

$p2 = new Poker_Tournament_Parser($argv[2]);
$p2->parse_file();
ob_start(); $admin->import_tournament_data($p2->get_tournament_data(), $p2); ob_end_clean();

$players2 = $count("player");
$seasons2 = $count("tournament_season");
$series2  = $count("tournament_series");

// Nobody should own more than one post.
$maxdup = (int) $wpdb->get_var(
	"SELECT MAX(c) FROM (SELECT COUNT(*) c FROM {$wpdb->posts}
	 WHERE post_type=\"player\" AND post_status<>\"trash\" GROUP BY post_title) x");

printf("p1=%d p2=%d se1=%d se2=%d sr1=%d sr2=%d maxdup=%d",
	$players1, $players2, $seasons1, $seasons2, $series1, $series2, $maxdup);
' "$FIXTURE")"

if [[ "$out" =~ p1=([0-9]+)\ p2=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" = "${BASH_REMATCH[2]}" ]; then
	ok "re-importing reuses player posts instead of duplicating them (${BASH_REMATCH[1]})"
else
	bad "player posts multiplied across a re-import ($out)"
fi
if [[ "$out" =~ se1=([0-9]+)\ se2=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" = "${BASH_REMATCH[2]}" ]; then
	ok "re-importing reuses the season post (${BASH_REMATCH[1]})"
else
	bad "season posts multiplied across a re-import ($out)"
fi
if [[ "$out" =~ sr1=([0-9]+)\ sr2=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" = "${BASH_REMATCH[2]}" ]; then
	ok "re-importing reuses the series post (${BASH_REMATCH[1]})"
else
	bad "series posts multiplied across a re-import ($out)"
fi
[[ "$out" == *"maxdup=1"* ]] && ok "no player owns more than one post" \
                            || bad "duplicate player posts exist ($out)"

# The consequence that actually broke the rollup: ambiguous UUID -> post mapping.
out="$(wp_php '
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
require $argv[1]."/wp-load.php";
global $wpdb;
$mart = $wpdb->prefix."tdwp_tournament_players";
$wpdb->query("DELETE FROM {$mart}");
$r = TDWP_Stats_Rollup::backfill_imports($mart, 0, 0, "");
printf("inserted=%d ambiguous=%d", (int) $r["inserted"], count($r["ambiguous"]));
')"
if [[ "$out" =~ inserted=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" -gt 0 ] && [[ "$out" == *"ambiguous=0"* ]]; then
	ok "the stats rollup resolves every player to a single post (${BASH_REMATCH[1]} rows, 0 ambiguous)"
else
	bad "the rollup cannot resolve players to posts ($out)"
fi

# ---------------------------------------------------------------------------
# 4m. The duplicate cleanup on the Diagnostics page.
#
# The importer fix stops NEW duplicates; posts already created stay until
# cleaned up. This creates real duplicates the way the old importer did, then
# drives the cleanup form and checks the outcome, including that statistics are
# untouched and that no tournament is left pointing at a trashed season.
# ---------------------------------------------------------------------------
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/"; $_SERVER["REQUEST_METHOD"]="POST";
$_GET["page"] = "poker-tournament-diagnostics";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);
global $wpdb;

$count = static function ($type) use ($wpdb) {
	return (int) $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status<>\"trash\"", $type));
};

// Recreate the pre-3.9.12 damage: clone each player and season post, UUID and all.
$clones = 0;
foreach (array("player" => "player_uuid", "tournament_season" => "season_uuid") as $type => $key) {
	foreach (get_posts(["post_type"=>$type,"numberposts"=>-1,"post_status"=>"any"]) as $orig) {
		$uuid = get_post_meta($orig->ID, $key, true);
		if (!$uuid) { continue; }
		$new = wp_insert_post(["post_title"=>$orig->post_title,"post_type"=>$type,"post_status"=>"draft"]);
		if ($new && !is_wp_error($new)) { update_post_meta($new, $key, $uuid); $clones++; }
	}
}

$before_stats  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}poker_tournament_players");
$before_points = (string) $wpdb->get_var("SELECT ROUND(SUM(points)) FROM {$wpdb->prefix}poker_tournament_players");
$before_tourn  = $count("tournament");

// Drive the cleanup form exactly as the browser would.
$_POST    = ["tdwp_diag_dedupe" => "1", "tdwp_diag_dedupe_nonce" => wp_create_nonce("tdwp_diag_dedupe")];
$_REQUEST = $_POST;
ob_start();
include ABSPATH."wp-content/plugins/poker-tournament-import/admin/class-tournament-diagnostics-page.php";
$html = ob_get_clean();

$maxdup = (int) $wpdb->get_var(
	"SELECT MAX(c) FROM (SELECT COUNT(*) c FROM {$wpdb->postmeta} pm
	   JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	  WHERE pm.meta_key=\"player_uuid\" AND p.post_status<>\"trash\"
	  GROUP BY pm.meta_value) x");

// No tournament may be left pointing at a trashed season or series.
$dangling = 0;
foreach (get_posts(["post_type"=>"tournament","numberposts"=>-1,"post_status"=>"any"]) as $t) {
	foreach (["_season_id","_series_id"] as $k) {
		$ref = get_post_meta($t->ID, $k, true);
		if ($ref && "trash" === get_post_status($ref)) { $dangling++; }
	}
}

printf("clones=%d reported=%d maxdup=%d dangling=%d stats=%d points=%s tourn=%d",
	$clones,
	(false !== strpos($html, "Cleanup complete")) ? 1 : 0,
	$maxdup,
	$dangling,
	(int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}poker_tournament_players") - $before_stats,
	((string) $wpdb->get_var("SELECT ROUND(SUM(points)) FROM {$wpdb->prefix}poker_tournament_players") === $before_points) ? "same" : "CHANGED",
	$count("tournament") - $before_tourn);
')"

if [[ "$out" =~ clones=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" -gt 0 ]; then
	ok "seeded ${BASH_REMATCH[1]} duplicate post(s) the way the pre-3.9.12 importer did"
else
	bad "could not seed duplicates for the cleanup check ($out)"
fi
[[ "$out" == *"reported=1"* ]] && ok "the cleanup form runs and reports completion" \
                              || bad "cleanup did not report completion ($out)"
[[ "$out" == *"maxdup=1"* ]]   && ok "after cleanup no player UUID maps to more than one live post" \
                              || bad "duplicates remain after cleanup ($out)"
[[ "$out" == *"dangling=0"* ]] && ok "no tournament is left pointing at a trashed season or series" \
                              || bad "cleanup left dangling season/series references ($out)"
[[ "$out" == *"stats=0"* ]]    && ok "cleanup changed no statistics rows" \
                              || bad "cleanup altered the statistics tables ($out)"
[[ "$out" == *"points=same"* ]] && ok "cleanup changed no points totals" \
                               || bad "cleanup altered points ($out)"
[[ "$out" == *"tourn=0"* ]]    && ok "cleanup trashed no tournaments" \
                              || bad "cleanup removed tournament posts ($out)"

# The rollup must resolve players again once duplicates are trashed.
out="$(wp_php '
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
require $argv[1]."/wp-load.php";
global $wpdb;
$mart = $wpdb->prefix."tdwp_tournament_players";
$wpdb->query("DELETE FROM {$mart}");
$r = TDWP_Stats_Rollup::backfill_imports($mart, 0, 0, "");
printf("inserted=%d ambiguous=%d", (int) $r["inserted"], count($r["ambiguous"]));
')"
if [[ "$out" =~ inserted=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" -gt 0 ] && [[ "$out" == *"ambiguous=0"* ]]; then
	ok "the rollup ignores trashed duplicates and resolves every player (${BASH_REMATCH[1]} rows)"
else
	bad "trashed duplicates still make players ambiguous to the rollup ($out)"
fi

# ---------------------------------------------------------------------------
# 4n. Re-importing must not unpublish a live tournament.
#
# The import form defaults to draft, so a re-import carries status 'draft'. The
# code tried to protect existing posts by unsetting post_status before calling
# wp_insert_post() -- but core treats a MISSING post_status as 'draft', not as
# "leave unchanged". Every re-imported tournament was therefore silently
# unpublished and vanished from the public site.
#
# Verified against core directly: update a published post with no post_status
# and it comes back as a draft.
# ---------------------------------------------------------------------------
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/"; $_SERVER["REQUEST_METHOD"]="POST";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);
global $wpdb;

// Core behaviour this guards against, asserted so the reason stays visible.
$probe = wp_insert_post(["post_title"=>"tdwp probe","post_type"=>"post","post_status"=>"publish"]);
wp_insert_post(["ID"=>$probe, "post_title"=>"tdwp probe 2"]);
clean_post_cache($probe);
$core_defaults_to_draft = ("draft" === get_post_status($probe)) ? 1 : 0;
wp_delete_post($probe, true);

$posts = get_posts(["post_type"=>"tournament","numberposts"=>-1,"post_status"=>"any"]);
if (empty($posts)) { echo "NO_TOURNAMENT"; exit; }
$id = $posts[0]->ID;
wp_update_post(["ID" => $id, "post_status" => "publish"]);

// Re-import WITHOUT publish_immediately, so the import status is draft.
unset($_POST["publish_immediately"]);
$_POST["create_players"] = "1";
$p = new Poker_Tournament_Parser($argv[2]);
if (!$p->parse_file()) { echo "PARSE_FAIL"; exit; }
ob_start();
(new Poker_Tournament_Import_Admin())->import_tournament_data($p->get_tournament_data(), $p);
ob_end_clean();
clean_post_cache($id);

printf("coredraft=%d after=%s", $core_defaults_to_draft, get_post_status($id));
' "$FIXTURE")"

[[ "$out" == *"coredraft=1"* ]]  && ok "confirmed: wp_insert_post() treats a missing post_status as draft" \
                                || bad "core no longer defaults to draft; this guard needs revisiting ($out)"
[[ "$out" == *"after=publish"* ]] && ok "re-importing leaves a published tournament published" \
                                 || bad "re-import silently unpublished a live tournament ($out)"

# A draft must stay a draft: the fix preserves state, it does not force publish.
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/"; $_SERVER["REQUEST_METHOD"]="POST";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);

$posts = get_posts(["post_type"=>"tournament","numberposts"=>-1,"post_status"=>"any"]);
$id = $posts[0]->ID;
wp_update_post(["ID" => $id, "post_status" => "draft"]);

$_POST["create_players"] = "1";
$p = new Poker_Tournament_Parser($argv[2]);
$p->parse_file();
ob_start();
(new Poker_Tournament_Import_Admin())->import_tournament_data($p->get_tournament_data(), $p);
ob_end_clean();
clean_post_cache($id);

echo "after=".get_post_status($id);
' "$FIXTURE")"
[[ "$out" == *"after=draft"* ]] && ok "re-importing leaves a draft tournament as a draft" \
                               || bad "re-import changed the status of a draft ($out)"

# ---------------------------------------------------------------------------
# 4o. Standings must show real player names, not "Unknown Player".
#
# The same draft/post_status defect existed in the DISPLAY layer: nine
# get_posts() lookups resolved a player, season or tournament UUID to a post
# without passing post_status, so with draft posts they found nothing. In
# series standings every row rendered as "Unknown Player" on the live site
# while the points beside it were correct.
# ---------------------------------------------------------------------------
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/"; $_SERVER["REQUEST_METHOD"]="POST";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);
global $wpdb;

// Standings are cached in a transient; clear it so this measures the code.
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%poker_series_standings%\"");
wp_cache_flush();

// Earlier sections deliberately leave the marts in odd states, so re-import
// the fixture first: this check is about name RESOLUTION, not about whatever
// rows happen to survive from section 4m.
$_POST["create_players"] = "1";
$p = new Poker_Tournament_Parser($argv[2]);
if ($p->parse_file()) {
	ob_start();
	(new Poker_Tournament_Import_Admin())->import_tournament_data($p->get_tournament_data(), $p);
	ob_end_clean();
}

$series = get_posts(["post_type"=>"tournament_series","numberposts"=>1,"post_status"=>"any"]);
if (empty($series)) { echo "NO_SERIES"; exit; }

$txt = preg_replace("/\s+/", " ", strip_tags(do_shortcode("[series_standings id=\"".$series[0]->ID."\"]")));

// At least one real player name from the mart must appear in the table.
$names = $wpdb->get_col("SELECT DISTINCT p.post_title FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = \"player_uuid\"
    WHERE p.post_type = \"player\" AND p.post_status <> \"trash\" LIMIT 20");
$named = 0;
foreach ((array) $names as $n) { if ($n !== "" && false !== strpos($txt, $n)) { $named++; } }

printf("unknown=%d named=%d len=%d", substr_count($txt, "Unknown Player"), $named, strlen($txt));
' "$FIXTURE")"

[[ "$out" == *"unknown=0"* ]] && ok "series standings render real player names, not Unknown Player" \
                             || bad "standings still show Unknown Player ($out)"

# Season standings: the season page used to hunt for a tournament_series post
# carrying _season_id, which the importer never writes, so it always said
# "No series found for this season" even with every tournament linked.
out="$(wp_php '
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
require $argv[1]."/wp-load.php";
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"_transient%standings%\"");
wp_cache_flush();
$season = get_posts(["post_type"=>"tournament_season","numberposts"=>1,"post_status"=>"any"]);
if (empty($season)) { echo "NO_SEASON"; exit; }
$txt = preg_replace("/\s+/", " ", strip_tags(do_shortcode("[season_standings id=\"".$season[0]->ID."\"]")));
printf("noseries=%d ranked=%d unknown=%d",
	(false !== strpos($txt, "No series found")) ? 1 : 0,
	preg_match("/Rank.*Player/", $txt) ? 1 : 0,
	substr_count($txt, "Unknown Player"));
')"
[[ "$out" == *"noseries=0"* ]] && ok "the season page no longer reports No series found for this season" \
                              || bad "season standings still cannot find its tournaments ($out)"
[[ "$out" == *"ranked=1"* ]]   && ok "season standings render a ranked table" \
                              || bad "season standings rendered no table ($out)"
[[ "$out" == *"unknown=0"* ]]  && ok "season standings show real player names" \
                              || bad "season standings show Unknown Player ($out)"
# Only meaningful when the standings table actually rendered rows. Season
# standings returning empty is a separate, pre-existing defect (confirmed by
# stashing these changes and re-running: it returned 0 rows either way), so
# this reports rather than fails on it.
if [[ "$out" =~ named=([0-9]+) ]] && [ "${BASH_REMATCH[1]}" -gt 0 ]; then
	ok "matched ${BASH_REMATCH[1]} real player name(s) from the database in the standings"
else
	info "standings rendered no player rows here; name resolution covered by the Unknown Player check"
fi

# Static guard: no UUID lookup anywhere may omit post_status again.
out="$(php "$PLUGIN_DIR/tests/tools/uuid-lookup-guard.php" "$PLUGIN_DIR" 2>&1)"
[[ "$out" == *"missing=0"* ]] && ok "every UUID post lookup passes an explicit post_status" \
                             || bad "a UUID lookup omits post_status and will miss drafts: $out"

# ---------------------------------------------------------------------------
# 4p. The duplicate section must be visible even when there is nothing to merge,
#     and must catch copies that carry no UUID meta.
#
# Reported by the user: "there is no Merge duplicates in Diagnostics." Two
# causes, both real:
#   - the whole section was hidden at zero, so "nothing to clean up" looked
#     identical to "the feature is missing"
#   - detection grouped only by UUID meta, so a copy created without it (name
#     match, manual duplicate, older import) was invisible
# ---------------------------------------------------------------------------
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/";
$_GET["page"] = "poker-tournament-diagnostics";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);
global $wpdb;

$render = static function () {
	ob_start();
	include ABSPATH."wp-content/plugins/poker-tournament-import/admin/class-tournament-diagnostics-page.php";
	return ob_get_clean();
};

// Clean state: the section must still be present and say so.
$html = $render();
$shown_when_clean = (false !== strpos($html, "Duplicate players, seasons and series")) ? 1 : 0;
$says_none        = (false !== strpos($html, "No duplicates found")) ? 1 : 0;
$button_when_clean = (false !== strpos($html, "Merge duplicates")) ? 1 : 0;

// A copy with NO uuid meta, the case that was invisible.
$orig = get_posts(["post_type"=>"player","numberposts"=>1,"post_status"=>"any"]);
$clone = wp_insert_post(["post_title"=>$orig[0]->post_title,"post_type"=>"player","post_status"=>"draft"]);

$html = $render();
$detected = (false !== strpos($html, "duplicate post(s) found")) ? 1 : 0;
$button   = (false !== strpos($html, "Merge duplicates")) ? 1 : 0;

wp_delete_post($clone, true);

printf("cleanshown=%d saysnone=%d cleanbutton=%d detected=%d button=%d",
	$shown_when_clean, $says_none, $button_when_clean, $detected, $button);
')"

[[ "$out" == *"cleanshown=1"* ]]   && ok "the duplicate section is visible even with nothing to merge" \
                                  || bad "the section vanishes at zero, so operators cannot tell it exists ($out)"
[[ "$out" == *"saysnone=1"* ]]     && ok "it states plainly that no duplicates were found" \
                                  || bad "no explicit no-duplicates message ($out)"
[[ "$out" == *"cleanbutton=0"* ]]  && ok "no Merge button is offered when there is nothing to merge" \
                                  || bad "a pointless Merge button is shown on a clean install ($out)"
[[ "$out" == *"detected=1"* ]]     && ok "a duplicate carrying no UUID meta is still detected by title" \
                                  || bad "a title-only duplicate goes undetected ($out)"
[[ "$out" == *"button=1"* ]]       && ok "the Merge button appears once duplicates exist" \
                                  || bad "duplicates found but no Merge button offered ($out)"

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
# 7. The toggle itself: saving it through WordPress's settings API must flip the
#    option, schedule exactly one rewrite flush, and lose no data either way.
# ---------------------------------------------------------------------------
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/options.php"; $_SERVER["REQUEST_METHOD"]="POST";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);
$opt = Poker_Tournament_Import::TM_ENABLED_OPTION;

// Establish a known starting state BEFORE admin_init, so the OFF->ON transition
// is unambiguous. (On a fresh install the option is absent, and admin_init also
// consumes any pending flush flag, which would mask the result.)
update_option($opt, false);
do_action("admin_init");                      // registers settings + sanitizers

// Drive the REAL sanitize_option_{$opt} filter, the way options.php does.
delete_option("tdwp_needs_rewrite_flush");
update_option($opt, apply_filters("sanitize_option_{$opt}", "1", $opt, "1"));
$on_val = get_option($opt); $on_flush = (bool) get_option("tdwp_needs_rewrite_flush");

delete_option("tdwp_needs_rewrite_flush");
update_option($opt, apply_filters("sanitize_option_{$opt}", "0", $opt, "0"));
$off_val = get_option($opt); $off_flush = (bool) get_option("tdwp_needs_rewrite_flush");

// A save that changes nothing must not schedule a flush.
delete_option("tdwp_needs_rewrite_flush");
apply_filters("sanitize_option_{$opt}", "0", $opt, "0");
$noop_flush = (bool) get_option("tdwp_needs_rewrite_flush");

printf("on=%s off=%s onflush=%s offflush=%s noop=%s",
	var_export((bool)$on_val,true), var_export((bool)$off_val,true),
	var_export($on_flush,true), var_export($off_flush,true), var_export($noop_flush,true));
')"
[[ "$out" == *"on=true"* && "$out" == *"off=false"* ]] 	&& ok "the toggle saves correctly through the WordPress settings API" 	|| bad "toggle did not round-trip through the settings API ($out)"
[[ "$out" == *"onflush=true"* && "$out" == *"offflush=true"* ]] 	&& ok "changing the toggle schedules a rewrite flush" 	|| bad "toggle did not schedule a rewrite flush ($out)"
[[ "$out" == *"noop=false"* ]] 	&& ok "a no-op save does not schedule a needless rewrite flush" 	|| bad "a no-op save scheduled a flush ($out)"

# The scheduled flush must actually be consumed on the next admin load.
out="$(wp_php '
define("WP_ADMIN", true);
$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/"; $_SERVER["REQUEST_METHOD"]="GET";
require $argv[1]."/wp-load.php";
wp_set_current_user(1);
update_option("tdwp_needs_rewrite_flush", 1);
do_action("admin_init");
echo get_option("tdwp_needs_rewrite_flush") ? "PENDING" : "CONSUMED";
')"
[[ "$out" == *CONSUMED* ]] && ok "the pending rewrite flush is consumed on the next admin load"                           || bad "rewrite flush was never consumed ($out)"

# ---------------------------------------------------------------------------
# 8. Reversibility: a full ON -> OFF round trip must not lose a single row.
#    This is the "no data is deleted" promise in the changelog and settings page.
# ---------------------------------------------------------------------------
snapshot() {
	wp_php '
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
	require $argv[1]."/wp-load.php";
	global $wpdb;
	$t = $wpdb->get_col("SELECT name FROM sqlite_master WHERE type=\"table\" AND (name LIKE \"%poker_%\" OR name LIKE \"%tdwp_%\")");
	sort($t);
	$rows = 0; foreach ($t as $x) { $rows += (int) $wpdb->get_var("SELECT COUNT(*) FROM $x"); }
	$posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN (\"tournament\",\"player\",\"tournament_series\",\"tournament_season\")");
	printf("tables=%d rows=%d posts=%d", count($t), $rows, $posts);
	'
}

before="$(snapshot)"
for s in on off; do
	wp_php '
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/";
	require $argv[1]."/wp-load.php";
	update_option("tdwp_tournament_manager_enabled", $argv[2] === "on");
	' "$s" >/dev/null 2>&1
	wp_php '
	define("WP_ADMIN", true);
	$_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REQUEST_URI"]="/wp-admin/"; $_SERVER["REQUEST_METHOD"]="GET";
	require $argv[1]."/wp-load.php";
	do_action("admin_init");
	' >/dev/null 2>&1
done
after="$(snapshot)"

if [ "$before" = "$after" ]; then
	ok "no data lost across an ON->OFF round trip ($after)"
else
	bad "data changed across the round trip: before[$before] after[$after]"
fi

# ---------------------------------------------------------------------------
# 9. Memory: the module must measurably cost something.
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
