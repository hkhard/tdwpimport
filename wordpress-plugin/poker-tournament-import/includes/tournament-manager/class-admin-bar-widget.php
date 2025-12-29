<?php
/**
 * Admin Bar Widget
 *
 * Displays active tournament in WordPress admin bar for quick access.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.1.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Bar Widget class
 *
 * @since 3.1.0
 */
class TDWP_Admin_Bar_Widget {

	/**
	 * Constructor
	 *
	 * @since 3.1.0
	 */
	public function __construct() {
		// Add to admin bar (high priority to appear after WP items).
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_item' ), 999 );

		// Enqueue styles for admin bar.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Add active tournament to admin bar
	 *
	 * @since 3.1.0
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_admin_bar_item( $wp_admin_bar ) {
		// Only for users who can manage tournaments.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get active tournament data.
		$data = TDWP_Active_Tournament_Manager::get_tournament_data_for_admin_bar( get_current_user_id() );

		if ( ! $data ) {
			return;
		}

		// Prepare status text.
		$status_text = $this->get_status_text( $data['status'], $data['current_level'] );

		// Add parent item.
		$wp_admin_bar->add_node(
			array(
				'id'    => 'tdwp-active-tournament',
				'title' => $this->get_admin_bar_title( $data['title'], $status_text, isset( $data['is_practice'] ) ? $data['is_practice'] : 0 ),
				'href'  => esc_url( $data['url'] ),
				'meta'  => array(
					'class' => 'tdwp-admin-bar-tournament',
					'title' => sprintf(
						/* translators: %s: Tournament name */
						__( 'Manage: %s', 'poker-tournament-import' ),
						esc_attr( $data['title'] )
					),
				),
			)
		);

		// Add submenu items.
		$this->add_submenu_items( $wp_admin_bar, $data );
	}

	/**
	 * Get admin bar title HTML
	 *
	 * @since 3.1.0
	 * @param string $tournament_name Tournament name.
	 * @param string $status_text Status text.
	 * @param int    $is_practice Whether tournament is practice mode.
	 * @return string HTML for admin bar title.
	 */
	private function get_admin_bar_title( $tournament_name, $status_text, $is_practice = 0 ) {
		$practice_badge = $is_practice ? '<span class="tdwp-practice-badge">PRACTICE</span>' : '';
		return sprintf(
			'<span class="ab-icon dashicons dashicons-games"></span><span class="ab-label">%s</span>%s<span class="tdwp-status">%s</span>',
			esc_html( wp_trim_words( $tournament_name, 5, '...' ) ),
			$practice_badge,
			esc_html( $status_text )
		);
	}

	/**
	 * Get status text for display
	 *
	 * @since 3.1.0
	 * @param string $status Tournament status.
	 * @param int    $level Current level.
	 * @return string Status text.
	 */
	private function get_status_text( $status, $level ) {
		switch ( $status ) {
			case 'running':
				return sprintf(
					/* translators: %d: Level number */
					__( 'Level %d', 'poker-tournament-import' ),
					$level
				);

			case 'paused':
				return sprintf(
					/* translators: %d: Level number */
					__( 'Paused (L%d)', 'poker-tournament-import' ),
					$level
				);

			case 'break':
				return __( 'On Break', 'poker-tournament-import' );

			case 'setup':
				return __( 'Setup', 'poker-tournament-import' );

			default:
				return ucfirst( $status );
		}
	}

	/**
	 * Add submenu items to admin bar
	 *
	 * @since 3.1.0
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @param array        $data Tournament data.
	 */
	private function add_submenu_items( $wp_admin_bar, $data ) {
		// Quick link to manage tournament.
		$wp_admin_bar->add_node(
			array(
				'parent' => 'tdwp-active-tournament',
				'id'     => 'tdwp-manage-tournament',
				'title'  => __( 'Manage Tournament', 'poker-tournament-import' ),
				'href'   => esc_url( $data['url'] ),
				'meta'   => array(
					'class' => 'tdwp-manage-link',
				),
			)
		);

		// Link to switch tournament.
		$wp_admin_bar->add_node(
			array(
				'parent' => 'tdwp-active-tournament',
				'id'     => 'tdwp-switch-tournament',
				'title'  => __( 'Switch Tournament', 'poker-tournament-import' ),
				'href'   => esc_url( admin_url( 'admin.php?page=tdwp-live-control' ) ),
				'meta'   => array(
					'class' => 'tdwp-switch-link',
				),
			)
		);

		// Show other running tournaments (if any).
		$running = TDWP_Active_Tournament_Manager::get_running_tournaments();
		if ( count( $running ) > 1 ) {
			$wp_admin_bar->add_node(
				array(
					'parent' => 'tdwp-active-tournament',
					'id'     => 'tdwp-running-tournaments',
					'title'  => __( 'Other Running Tournaments', 'poker-tournament-import' ),
					'meta'   => array(
						'class' => 'tdwp-submenu-header',
					),
				)
			);

			foreach ( $running as $tournament ) {
				// Skip current tournament.
				if ( $tournament->ID === $data['id'] ) {
					continue;
				}

				$wp_admin_bar->add_node(
					array(
						'parent' => 'tdwp-active-tournament',
						'id'     => 'tdwp-tournament-' . $tournament->ID,
						'title'  => esc_html( $tournament->post_title ),
						'href'   => esc_url( admin_url( 'admin.php?page=tdwp-live-control&tournament_id=' . $tournament->ID ) ),
					)
				);
			}
		}
	}

	/**
	 * Enqueue styles for admin bar
	 *
	 * @since 3.1.0
	 */
	public function enqueue_styles() {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		// Inline styles for admin bar widget.
		$css = '
			#wpadminbar #wp-admin-bar-tdwp-active-tournament .ab-item {
				padding-right: 15px;
			}
			#wpadminbar #wp-admin-bar-tdwp-active-tournament .ab-icon {
				font-size: 20px;
				margin-top: 2px;
				float: left;
				margin-right: 5px;
			}
			#wpadminbar #wp-admin-bar-tdwp-active-tournament .tdwp-status {
				display: inline-block;
				margin-left: 8px;
				padding: 2px 8px;
				background: rgba(255,255,255,0.2);
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
			}
			#wpadminbar #wp-admin-bar-tdwp-active-tournament .tdwp-practice-badge {
				display: inline-block;
				margin-left: 6px;
				padding: 2px 6px;
				background: #f0ad4e;
				color: #fff;
				border-radius: 3px;
				font-size: 10px;
				font-weight: 700;
				text-transform: uppercase;
			}
			#wpadminbar #wp-admin-bar-tdwp-active-tournament .tdwp-submenu-header {
				font-weight: 600;
				opacity: 0.7;
				cursor: default;
			}
			#wpadminbar #wp-admin-bar-tdwp-active-tournament .tdwp-submenu-header:hover {
				background: none;
			}
		';

		wp_add_inline_style( 'admin-bar', $css );
	}
}
