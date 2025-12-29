<?php
/**
 * TD3 Integration: Display Shortcode
 *
 * Provides shortcodes for embedding tournament displays in public pages
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.4.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display Shortcode Class
 *
 * Handles shortcodes for displaying tournament information
 * and live displays on public-facing pages.
 *
 * @since 3.4.0
 */
class TDWP_Display_Shortcode {

	/**
	 * Singleton instance
	 *
	 * @var TDWP_Display_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Display manager instance
	 *
	 * @var TDWP_Display_Manager|null
	 */
	private $display_manager = null;

	/**
	 * Get singleton instance
	 *
	 * @since 3.4.0
	 * @return TDWP_Display_Shortcode
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 3.4.0
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_shortcode_scripts' ) );
	}

	/**
	 * Register shortcodes
	 *
	 * @since 3.4.0
	 */
	public function register_shortcodes() {
		add_shortcode( 'tdwp_tournament_display', array( $this, 'tournament_display_shortcode' ) );
		add_shortcode( 'tdwp_live_clock', array( $this, 'live_clock_shortcode' ) );
		add_shortcode( 'tdwp_leaderboard', array( $this, 'leaderboard_shortcode' ) );
		add_shortcode( 'tdwp_prize_pool', array( $this, 'prize_pool_shortcode' ) );
		add_shortcode( 'tdwp_player_count', array( $this, 'player_count_shortcode' ) );
		add_shortcode( 'tdwp_current_blinds', array( $this, 'current_blinds_shortcode' ) );
		add_shortcode( 'tdwp_screen_preview', array( $this, 'screen_preview_shortcode' ) );
	}

	/**
	 * Enqueue shortcode-specific scripts and styles
	 *
	 * @since 3.4.0
	 */
	public function enqueue_shortcode_scripts() {
		global $post;

		// Only load on pages that contain our shortcodes
		if ( $post && ( has_shortcode( $post->post_content, 'tdwp_tournament_display' ) ||
		                  has_shortcode( $post->post_content, 'tdwp_live_clock' ) ||
		                  has_shortcode( $post->post_content, 'tdwp_leaderboard' ) ||
		                  has_shortcode( $post->post_content, 'tdwp_prize_pool' ) ||
		                  has_shortcode( $post->post_content, 'tdwp_player_count' ) ||
		                  has_shortcode( $post->post_content, 'tdwp_current_blinds' ) ||
		                  has_shortcode( $post->post_content, 'tdwp_screen_preview' ) ) ) {

			// Enqueue display styles
			wp_enqueue_style(
				'tdwp-display-shortcode',
				plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . '/assets/css/display-shortcode.css',
				array(),
				'3.4.0'
			);

			// Enqueue display scripts
			wp_enqueue_script(
				'tdwp-display-shortcode',
				plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . '/assets/js/display-shortcode.js',
				array( 'jquery' ),
				'3.4.0',
				true
			);

			wp_localize_script( 'tdwp-display-shortcode', 'tdwp_shortcode', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'tdwp_shortcode_nonce' ),
				'auto_refresh' => get_option( 'tdwp_auto_refresh_enabled', true ),
				'refresh_interval' => get_option( 'tdwp_refresh_interval', 30000 ),
			) );
		}
	}

	/**
	 * Tournament display shortcode
	 *
	 * @since 3.4.0
	 * @param array $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Rendered HTML.
	 */
	public function tournament_display_shortcode( $atts, $content = '' ) {
		$atts = shortcode_atts( array(
			'tournament_id' => 0,
			'screen_name' => '',
			'display_type' => 'full', // full, compact, minimal
			'show_clock' => 'true',
			'show_blinds' => 'true',
			'show_players' => 'true',
			'show_prizes' => 'true',
			'auto_refresh' => 'true',
			'css_class' => '',
		), $atts, 'tdwp_tournament_display' );

		$tournament_id = intval( $atts['tournament_id'] );

		if ( ! $tournament_id ) {
			return '<p class="tdwp-error">Please specify a tournament ID.</p>';
		}

		$tournament = get_post( $tournament_id );
		if ( ! $tournament || $tournament->post_type !== 'tournament' ) {
			return '<p class="tdwp-error">Tournament not found.</p>';
		}

		// Get display manager
		if ( ! $this->display_manager ) {
			$this->display_manager = TDWP_Display_Manager::get_instance();
		}

		// Get live tournament data
		$tournament_data = $this->get_tournament_data( $tournament_id );

		// Build display HTML
		$html = '<div class="tdwp-tournament-display tdwp-display-' . esc_attr( $atts['display_type'] ) . ' ' . esc_attr( $atts['css_class'] ) . '" data-tournament-id="' . esc_attr( $tournament_id ) . '" data-auto-refresh="' . esc_attr( $atts['auto_refresh'] ) . '">';

		// Add tournament title
		if ( $atts['display_type'] === 'full' ) {
			$html .= '<header class="tdwp-display-header">';
			$html .= '<h1 class="tdwp-tournament-title">' . esc_html( $tournament->post_title ) . '</h1>';
			$html .= '</header>';
		}

		// Add clock display
		if ( $atts['show_clock'] === 'true' ) {
			$html .= $this->render_clock_display( $tournament_data, $atts['display_type'] );
		}

		// Add blinds display
		if ( $atts['show_blinds'] === 'true' ) {
			$html .= $this->render_blinds_display( $tournament_data, $atts['display_type'] );
		}

		// Add player count
		if ( $atts['show_players'] === 'true' ) {
			$html .= $this->render_player_count_display( $tournament_data, $atts['display_type'] );
		}

		// Add prize pool
		if ( $atts['show_prizes'] === 'true' ) {
			$html .= $this->render_prize_pool_display( $tournament_data, $atts['display_type'] );
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Live clock shortcode
	 *
	 * @since 3.4.0
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function live_clock_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'tournament_id' => 0,
			'show_level' => 'true',
			'show_time' => 'true',
			'show_next_blinds' => 'false',
			'css_class' => '',
		), $atts, 'tdwp_live_clock' );

		$tournament_id = intval( $atts['tournament_id'] );
		if ( ! $tournament_id ) {
			return '<p class="tdwp-error">Please specify a tournament ID.</p>';
		}

		$tournament_data = $this->get_tournament_data( $tournament_id );

		$tournament_status = $tournament_data['tournament_status'] ?? 'pending';
		$clock_running = $tournament_data['clock_running'] ?? false;

		// Add status class for styling
		$status_class = ' status-' . $tournament_status . ( $clock_running && $tournament_status === 'running' ? ' running' : '' );

		$html = '<div class="tdwp-live-clock ' . esc_attr( $atts['css_class'] ) . $status_class . '" data-tournament-id="' . esc_attr( $tournament_id ) . '" data-tournament-status="' . esc_attr( $tournament_status ) . '">';

		if ( $atts['show_time'] === 'true' ) {
			$time_display = $tournament_data['time_remaining'] ?? '00:00:00';

			// Override time display based on tournament status
			if ( $tournament_status === 'pending' ) {
				$time_display = 'Not Started';
			} elseif ( $tournament_status === 'completed' ) {
				$time_display = 'Finished';
			} elseif ( $tournament_status === 'break' ) {
				$time_display = 'On Break';
			}

			$html .= '<div class="clock-display">' . esc_html( $time_display ) . '</div>';
		}

		if ( $atts['show_level'] === 'true' ) {
			$level_display = 'Level ' . esc_html( $tournament_data['current_level'] ?? 1 );

			// Override level display based on tournament status
			if ( $tournament_status === 'pending' ) {
				$level_display = 'Scheduled';
			} elseif ( $tournament_status === 'completed' ) {
				$level_display = 'Completed';
			} elseif ( $tournament_status === 'paused' ) {
				$level_display = 'Level ' . esc_html( $tournament_data['current_level'] ?? 1 ) . ' (Paused)';
			} elseif ( $tournament_status === 'break' ) {
				$level_display = 'On Break';
			}

			$html .= '<div class="level-display">' . esc_html( $level_display ) . '</div>';
		}

		if ( $atts['show_next_blinds'] === 'true' && $tournament_status === 'running' ) {
			// Only show next blinds for running tournaments
			$html .= '<div class="next-blinds">Next: ' . esc_html( $tournament_data['next_blinds'] ?? '10/20' ) . '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Leaderboard shortcode
	 *
	 * @since 3.4.0
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function leaderboard_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'tournament_id' => 0,
			'limit' => 10,
			'show_chips' => 'true',
			'show_eliminated' => 'false',
			'css_class' => '',
		), $atts, 'tdwp_leaderboard' );

		$tournament_id = intval( $atts['tournament_id'] );
		if ( ! $tournament_id ) {
			return '<p class="tdwp-error">Please specify a tournament ID.</p>';
		}

		$leaderboard = $this->get_leaderboard( $tournament_id, $atts['limit'], $atts['show_eliminated'] === 'true' );

		$html = '<div class="tdwp-leaderboard ' . esc_attr( $atts['css_class'] ) . '" data-tournament-id="' . esc_attr( $tournament_id ) . '">';

		if ( ! empty( $leaderboard ) ) {
			$html .= '<table class="tdwp-leaderboard-table">';
			$html .= '<thead><tr>';
			$html .= '<th>Rank</th>';
			$html .= '<th>Player</th>';
			if ( $atts['show_chips'] === 'true' ) {
				$html .= '<th>Chips</th>';
			}
			$html .= '</tr></thead>';
			$html .= '<tbody>';

			foreach ( $leaderboard as $player ) {
				$html .= '<tr>';
				$html .= '<td class="rank">' . esc_html( $player['rank'] ) . '</td>';
				$html .= '<td class="player-name">' . esc_html( $player['name'] ) . '</td>';
				if ( $atts['show_chips'] === 'true' ) {
					$html .= '<td class="chips">' . esc_html( number_format( $player['chips'] ) ) . '</td>';
				}
				$html .= '</tr>';
			}

			$html .= '</tbody></table>';
		} else {
			$html .= '<p class="tdwp-no-data">No leaderboard data available.</p>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Prize pool shortcode
	 *
	 * @since 3.4.0
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function prize_pool_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'tournament_id' => 0,
			'show_breakdown' => 'false',
			'css_class' => '',
		), $atts, 'tdwp_prize_pool' );

		$tournament_id = intval( $atts['tournament_id'] );
		if ( ! $tournament_id ) {
			return '<p class="tdwp-error">Please specify a tournament ID.</p>';
		}

		$tournament_data = $this->get_tournament_data( $tournament_id );

		$html = '<div class="tdwp-prize-pool ' . esc_attr( $atts['css_class'] ) . '" data-tournament-id="' . esc_attr( $tournament_id ) . '">';

		if ( ! empty( $tournament_data['prize_pool'] ) ) {
			$html .= '<div class="prize-amount">' . esc_html( $tournament_data['prize_pool'] ) . '</div>';
			$html .= '<div class="prize-label">Prize Pool</div>';

			if ( $atts['show_breakdown'] === 'true' && ! empty( $tournament_data['prize_breakdown'] ) ) {
				$html .= '<div class="prize-breakdown">';
				$html .= '<h4>Prize Breakdown</h4>';
				$html .= '<ul>';
				foreach ( $tournament_data['prize_breakdown'] as $position => $amount ) {
					$html .= '<li><strong>' . esc_html( $position ) . ':</strong> ' . esc_html( $amount ) . '</li>';
				}
				$html .= '</ul>';
				$html .= '</div>';
			}
		} else {
			$html .= '<div class="prize-amount">$0</div>';
			$html .= '<div class="prize-label">Prize Pool</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Player count shortcode
	 *
	 * @since 3.4.0
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function player_count_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'tournament_id' => 0,
			'show_label' => 'true',
			'css_class' => '',
		), $atts, 'tdwp_player_count' );

		$tournament_id = intval( $atts['tournament_id'] );
		if ( ! $tournament_id ) {
			return '<p class="tdwp-error">Please specify a tournament ID.</p>';
		}

		$tournament_data = $this->get_tournament_data( $tournament_id );

		$html = '<div class="tdwp-player-count ' . esc_attr( $atts['css_class'] ) . '" data-tournament-id="' . esc_attr( $tournament_id ) . '">';

		$html .= '<div class="players-remaining">' . esc_html( $tournament_data['players_remaining'] ?? 0 ) . '</div>';

		if ( $atts['show_label'] === 'true' ) {
			$html .= '<div class="players-label">Players</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Current blinds shortcode
	 *
	 * @since 3.4.0
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function current_blinds_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'tournament_id' => 0,
			'show_next' => 'true',
			'css_class' => '',
		), $atts, 'tdwp_current_blinds' );

		$tournament_id = intval( $atts['tournament_id'] );
		if ( ! $tournament_id ) {
			return '<p class="tdwp-error">Please specify a tournament ID.</p>';
		}

		$tournament_data = $this->get_tournament_data( $tournament_id );

		$html = '<div class="tdwp-current-blinds ' . esc_attr( $atts['css_class'] ) . '" data-tournament-id="' . esc_attr( $tournament_id ) . '">';

		$html .= '<div class="current-blinds">' . esc_html( $tournament_data['current_blinds'] ?? '10/20' ) . '</div>';

		if ( $atts['show_next'] === 'true' ) {
			$html .= '<div class="next-blinds">Next: ' . esc_html( $tournament_data['next_blinds'] ?? '15/30' ) . '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Screen preview shortcode
	 *
	 * @since 3.4.0
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function screen_preview_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'screen_id' => 0,
			'tournament_id' => 0,
			'width' => '100%',
			'height' => '600px',
			'css_class' => '',
		), $atts, 'tdwp_screen_preview' );

		$screen_id = intval( $atts['screen_id'] );
		$tournament_id = intval( $atts['tournament_id'] );

		if ( ! $screen_id || ! $tournament_id ) {
			return '<p class="tdwp-error">Please specify both screen_id and tournament_id.</p>';
		}

		$preview_url = home_url( "/tdwp-preview/{$screen_id}-{$tournament_id}/" );

		$html = '<div class="tdwp-screen-preview ' . esc_attr( $atts['css_class'] ) . '">';
		$html .= '<iframe src="' . esc_url( $preview_url ) . '" ';
		$html .= 'width="' . esc_attr( $atts['width'] ) . '" ';
		$html .= 'height="' . esc_attr( $atts['height'] ) . '" ';
		$html .= 'frameborder="0" ';
		$html .= 'class="tdwp-preview-frame">';
		$html .= '</iframe>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Get tournament data
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return array Tournament data.
	 */
	private function get_tournament_data( $tournament_id ) {
		// Try to get live data from display manager
		if ( $this->display_manager ) {
			$live_data = $this->display_manager->get_tournament_live_data( $tournament_id );
			if ( $live_data ) {
				return $live_data;
			}
		}

		// Fall back to cached or default data
		$cached_data = get_transient( "tdwp_tournament_data_{$tournament_id}" );
		if ( $cached_data ) {
			return $cached_data;
		}

		// Default data
		return array(
			'tournament_name' => get_the_title( $tournament_id ),
			'current_level' => 1,
			'current_blinds' => '10/20',
			'next_blinds' => '15/30',
			'time_remaining' => '20:00',
			'players_remaining' => 0,
			'prize_pool' => '$0',
			'clock_running' => false,
		);
	}

	/**
	 * Get leaderboard data
	 *
	 * @since 3.4.0
	 * @param int    $tournament_id Tournament ID.
	 * @param int    $limit Number of players to return.
	 * @param bool   $include_eliminated Include eliminated players.
	 * @return array Leaderboard data.
	 */
	private function get_leaderboard( $tournament_id, $limit = 10, $include_eliminated = false ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'poker_tournament_players';
		$limit = intval( $limit );

		$sql = "SELECT
			p.post_title as name,
			tp.rank,
			tp.chips,
			tp.eliminated,
			tp.elimination_order
			FROM {$table_name} tp
			INNER JOIN {$wpdb->posts} p ON tp.player_id = p.ID
			WHERE tp.tournament_id = %d";

		if ( ! $include_eliminated ) {
			$sql .= " AND tp.eliminated = 0";
		}

		$sql .= " ORDER BY tp.rank ASC, tp.elimination_order ASC LIMIT %d";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $tournament_id, $limit ) );

		$leaderboard = array();
		foreach ( $results as $result ) {
			$leaderboard[] = array(
				'name' => $result->name,
				'rank' => $result->rank,
				'chips' => $result->chips,
				'eliminated' => $result->eliminated,
			);
		}

		return $leaderboard;
	}

	/**
	 * Render clock display component
	 *
	 * @since 3.4.0
	 * @param array  $tournament_data Tournament data.
	 * @param string $display_type Display type.
	 * @return string HTML.
	 */
	private function render_clock_display( $tournament_data, $display_type ) {
		$html = '<div class="tdwp-clock-display">';

		$tournament_status = $tournament_data['tournament_status'] ?? 'pending';
		$clock_running = $tournament_data['clock_running'] ?? false;

		// Handle different tournament states
		if ( $tournament_status === 'pending' ) {
			$time_display = 'Not Started';
			$level_display = 'Scheduled';
		} elseif ( $tournament_status === 'completed' ) {
			$time_display = 'Finished';
			$level_display = 'Completed';
		} elseif ( $tournament_status === 'paused' ) {
			$time_display = $tournament_data['time_remaining'] ?? '--:--';
			$level_display = 'Level ' . esc_html( $tournament_data['current_level'] ?? 1 ) . ' (Paused)';
		} elseif ( $tournament_status === 'break' ) {
			$time_display = $tournament_data['time_remaining'] ?? '--:--';
			$level_display = 'On Break';
		} else {
			// Running tournament
			$time_display = $tournament_data['time_remaining'] ?? '--:--';
			$level_display = 'Level ' . esc_html( $tournament_data['current_level'] ?? 1 );
		}

		if ( $display_type === 'minimal' ) {
			$html .= '<div class="clock-time">' . esc_html( $time_display ) . '</div>';
		} else {
			$html .= '<div class="clock-time' . ( $clock_running && $tournament_status === 'running' ? ' running' : '' ) . '">' . esc_html( $time_display ) . '</div>';
			$html .= '<div class="clock-level">' . esc_html( $level_display ) . '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render blinds display component
	 *
	 * @since 3.4.0
	 * @param array  $tournament_data Tournament data.
	 * @param string $display_type Display type.
	 * @return string HTML.
	 */
	private function render_blinds_display( $tournament_data, $display_type ) {
		$html = '<div class="tdwp-blinds-display">';

		if ( $display_type === 'minimal' ) {
			$html .= '<div class="current-blinds">' . esc_html( $tournament_data['current_blinds'] ?? '10/20' ) . '</div>';
		} else {
			$html .= '<div class="current-blinds">' . esc_html( $tournament_data['current_blinds'] ?? '10/20' ) . '</div>';
			$html .= '<div class="next-blinds">Next: ' . esc_html( $tournament_data['next_blinds'] ?? '15/30' ) . '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render player count display component
	 *
	 * @since 3.4.0
	 * @param array  $tournament_data Tournament data.
	 * @param string $display_type Display type.
	 * @return string HTML.
	 */
	private function render_player_count_display( $tournament_data, $display_type ) {
		$html = '<div class="tdwp-player-count-display">';
		$html .= '<div class="players-number">' . esc_html( $tournament_data['players_remaining'] ?? 0 ) . '</div>';

		if ( $display_type !== 'minimal' ) {
			$html .= '<div class="players-label">Players</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render prize pool display component
	 *
	 * @since 3.4.0
	 * @param array  $tournament_data Tournament data.
	 * @param string $display_type Display type.
	 * @return string HTML.
	 */
	private function render_prize_pool_display( $tournament_data, $display_type ) {
		$html = '<div class="tdwp-prize-pool-display">';
		$html .= '<div class="prize-amount">' . esc_html( $tournament_data['prize_pool'] ?? '$0' ) . '</div>';

		if ( $display_type !== 'minimal' ) {
			$html .= '<div class="prize-label">Prize Pool</div>';
		}

		$html .= '</div>';

		return $html;
	}
}