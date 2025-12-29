<?php
/**
 * TD3 Integration: Layout Builder
 *
 * Handles drag-and-drop layout configurations for tournament displays.
 * Provides CSS Grid generation, component positioning, and responsive design.
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
 * Layout Builder Class
 *
 * Manages drag-and-drop layout configurations for tournament displays.
 * Handles CSS Grid generation, component positioning, responsive breakpoints,
 * and layout validation for various screen sizes.
 *
 * @since 3.4.0
 */
class TDWP_Layout_Builder {

	/**
	 * Singleton instance
	 *
	 * @var TDWP_Layout_Builder|null
	 */
	private static $instance = null;

	/**
	 * Default grid configuration
	 *
	 * @var array
	 */
	private $default_grid_config = array(
		'columns' => 12,
		'rows' => 8,
		'gap' => '1rem',
		'padding' => '1rem',
	);

	/**
	 * Available breakpoints
	 *
	 * @var array
	 */
	private $breakpoints = array(
		'small' => array(
			'max_width' => '768px',
			'columns' => 6,
			'rows' => 12,
		),
		'medium' => array(
			'max_width' => '1024px',
			'columns' => 8,
			'rows' => 10,
		),
		'large' => array(
			'max_width' => '1440px',
			'columns' => 12,
			'rows' => 8,
		),
		'xlarge' => array(
			'max_width' => 'none',
			'columns' => 16,
			'rows' => 6,
		),
	);

	/**
	 * Available components
	 *
	 * @var array
	 */
	private $available_components = array(
		'tournament_name' => array(
			'name' => 'Tournament Name',
			'default_size' => array( 'width' => 6, 'height' => 1 ),
			'resizable' => true,
		),
		'clock_display' => array(
			'name' => 'Clock Display',
			'default_size' => array( 'width' => 4, 'height' => 2 ),
			'resizable' => true,
		),
		'current_blinds' => array(
			'name' => 'Current Blinds',
			'default_size' => array( 'width' => 3, 'height' => 1 ),
			'resizable' => true,
		),
		'time_remaining' => array(
			'name' => 'Time Remaining',
			'default_size' => array( 'width' => 3, 'height' => 1 ),
			'resizable' => true,
		),
		'player_count' => array(
			'name' => 'Player Count',
			'default_size' => array( 'width' => 2, 'height' => 1 ),
			'resizable' => true,
		),
		'prize_pool' => array(
			'name' => 'Prize Pool',
			'default_size' => array( 'width' => 4, 'height' => 1 ),
			'resizable' => true,
		),
		'rankings' => array(
			'name' => 'Rankings',
			'default_size' => array( 'width' => 6, 'height' => 4 ),
			'resizable' => true,
		),
		'seating_chart' => array(
			'name' => 'Seating Chart',
			'default_size' => array( 'width' => 8, 'height' => 6 ),
			'resizable' => true,
		),
		'next_blinds' => array(
			'name' => 'Next Blinds',
			'default_size' => array( 'width' => 3, 'height' => 1 ),
			'resizable' => true,
		),
		'custom_content' => array(
			'name' => 'Custom Content',
			'default_size' => array( 'width' => 4, 'height' => 2 ),
			'resizable' => true,
		),
	);

	/**
	 * Get singleton instance
	 *
	 * @since 3.4.0
	 * @return TDWP_Layout_Builder
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
		// Initialize hooks and filters
		add_action( 'wp_ajax_tdwp_save_layout', array( $this, 'ajax_save_layout' ) );
		add_action( 'wp_ajax_tdwp_get_layout', array( $this, 'ajax_get_layout' ) );
		add_action( 'wp_ajax_tdwp_get_components', array( $this, 'ajax_get_components' ) );
		add_action( 'wp_ajax_tdwp_validate_layout', array( $this, 'ajax_validate_layout' ) );

		// Additional AJAX handlers for layout builder functionality
		add_action( 'wp_ajax_tdwp_export_layout', array( $this, 'ajax_export_layout' ) );
		add_action( 'wp_ajax_tdwp_import_layout', array( $this, 'ajax_import_layout' ) );
		add_action( 'wp_ajax_tdwp_create_preview', array( $this, 'ajax_create_preview' ) );
	}

	/**
	 * Apply layout to content
	 *
	 * @since 3.4.0
	 * @param string $content Original content.
	 * @param array  $grid_config Grid configuration.
	 * @param array  $component_positions Component positions.
	 * @return string Layout-applied content.
	 */
	public function apply_layout( $content, $grid_config, $component_positions ) {
		$grid_config = wp_parse_args( $grid_config, $this->default_grid_config );

		// Generate CSS
		$css = $this->generate_css( $grid_config, $component_positions );

		// Wrap content in grid container
		$layout_html = '<div class="tdwp-layout-container" data-layout-id="' . esc_attr( uniqid() ) . '">';
		$layout_html .= '<style>' . $css . '</style>';
		$layout_html .= '<div class="tdwp-grid">' . $content . '</div>';
		$layout_html .= '</div>';

		return $layout_html;
	}

	/**
	 * Generate CSS for layout
	 *
	 * @since 3.4.0
	 * @param array $grid_config Grid configuration.
	 * @param array $component_positions Component positions.
	 * @return string Generated CSS.
	 */
	private function generate_css( $grid_config, $component_positions ) {
		$css = '.tdwp-grid {';
		$css .= 'display: grid;';
		$css .= 'grid-template-columns: repeat(' . $grid_config['columns'] . ', 1fr);';
		$css .= 'grid-template-rows: repeat(' . $grid_config['rows'] . ', minmax(60px, 1fr));';
		$css .= 'gap: ' . $grid_config['gap'] . ';';
		$css .= 'padding: ' . $grid_config['padding'] . ';';
		$css .= 'width: 100%;';
		$css .= 'height: 100vh;';
		$css .= '}';

		// Generate responsive styles
		$css .= $this->generate_responsive_css( $grid_config );

		// Generate component positioning styles
		if ( ! empty( $component_positions ) ) {
			$css .= $this->generate_component_css( $component_positions );
		}

		return $css;
	}

	/**
	 * Generate responsive CSS
	 *
	 * @since 3.4.0
	 * @param array $grid_config Grid configuration.
	 * @return string Responsive CSS.
	 */
	private function generate_responsive_css( $grid_config ) {
		$css = '';

		foreach ( $this->breakpoints as $breakpoint_name => $breakpoint_config ) {
			if ( $breakpoint_config['max_width'] !== 'none' ) {
				$css .= '@media (max-width: ' . $breakpoint_config['max_width'] . ') {';
				$css .= '.tdwp-grid {';
				$css .= 'grid-template-columns: repeat(' . $breakpoint_config['columns'] . ', 1fr);';
				$css .= 'grid-template-rows: repeat(' . $breakpoint_config['rows'] . ', minmax(60px, 1fr));';
				$css .= '}';
				$css .= '}';
			}
		}

		return $css;
	}

	/**
	 * Generate component positioning CSS
	 *
	 * @since 3.4.0
	 * @param array $component_positions Component positions.
	 * @return string Component CSS.
	 */
	private function generate_component_css( $component_positions ) {
		$css = '';

		foreach ( $component_positions as $component_id => $position ) {
			$css .= '.tdwp-component-' . $component_id . ' {';
			$css .= 'grid-column: ' . $position['column_start'] . ' / ' . ($position['column_start'] + $position['width']) . ';';
			$css .= 'grid-row: ' . $position['row_start'] . ' / ' . ($position['row_start'] + $position['height']) . ';';

			if ( isset( $position['z_index'] ) ) {
				$css .= 'z-index: ' . $position['z_index'] . ';';
			}

			$css .= '}';
		}

		return $css;
	}

	/**
	 * Validate layout configuration
	 *
	 * @since 3.4.0
	 * @param array $layout_config Layout configuration.
	 * @return array Validation result.
	 */
	public function validate_layout( $layout_config ) {
		$errors = array();
		$warnings = array();

		// Validate grid config
		if ( isset( $layout_config['grid_config'] ) ) {
			$grid_config = $layout_config['grid_config'];

			if ( ! isset( $grid_config['columns'] ) || $grid_config['columns'] < 1 || $grid_config['columns'] > 24 ) {
				$errors[] = 'Invalid column count. Must be between 1 and 24.';
			}

			if ( ! isset( $grid_config['rows'] ) || $grid_config['rows'] < 1 || $grid_config['rows'] > 20 ) {
				$errors[] = 'Invalid row count. Must be between 1 and 20.';
			}

			if ( isset( $grid_config['gap'] ) && ! $this->is_valid_css_size( $grid_config['gap'] ) ) {
				$errors[] = 'Invalid gap value. Must be a valid CSS size value.';
			}
		}

		// Validate component positions
		if ( isset( $layout_config['component_positions'] ) ) {
			$component_positions = $layout_config['component_positions'];
			$grid_columns = $layout_config['grid_config']['columns'] ?? 12;
			$grid_rows = $layout_config['grid_config']['rows'] ?? 8;

			foreach ( $component_positions as $component_id => $position ) {
				$component_errors = $this->validate_component_position( $position, $grid_columns, $grid_rows );

				if ( ! empty( $component_errors ) ) {
					$errors = array_merge( $errors, $component_errors );
				}
			}
		}

		// Check for overlapping components
		if ( ! empty( $layout_config['component_positions'] ) ) {
			$overlaps = $this->check_component_overlaps( $layout_config['component_positions'] );

			if ( ! empty( $overlaps ) ) {
				$warnings[] = 'Some components overlap: ' . implode( ', ', $overlaps );
			}
		}

		return array(
			'is_valid' => empty( $errors ),
			'errors' => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Validate component position
	 *
	 * @since 3.4.0
	 * @param array $position Component position.
	 * @param int   $max_columns Maximum columns.
	 * @param int   $max_rows Maximum rows.
	 * @return array Validation errors.
	 */
	private function validate_component_position( $position, $max_columns, $max_rows ) {
		$errors = array();

		// Check required fields
		$required_fields = array( 'column_start', 'row_start', 'width', 'height' );

		foreach ( $required_fields as $field ) {
			if ( ! isset( $position[ $field ] ) || ! is_numeric( $position[ $field ] ) ) {
				$errors[] = "Missing or invalid field: {$field}";
			}
		}

		if ( ! empty( $errors ) ) {
			return $errors;
		}

		// Check bounds
		if ( $position['column_start'] < 1 || $position['column_start'] > $max_columns ) {
			$errors[] = 'Column start out of bounds.';
		}

		if ( $position['row_start'] < 1 || $position['row_start'] > $max_rows ) {
			$errors[] = 'Row start out of bounds.';
		}

		if ( $position['width'] < 1 || ($position['column_start'] + $position['width'] - 1) > $max_columns ) {
			$errors[] = 'Component width out of bounds.';
		}

		if ( $position['height'] < 1 || ($position['row_start'] + $position['height'] - 1) > $max_rows ) {
			$errors[] = 'Component height out of bounds.';
		}

		return $errors;
	}

	/**
	 * Check for overlapping components
	 *
	 * @since 3.4.0
	 * @param array $component_positions Component positions.
	 * @return array Overlapping component IDs.
	 */
	private function check_component_overlaps( $component_positions ) {
		$overlaps = array();
		$occupied = array();

		foreach ( $component_positions as $component_id => $position ) {
			for ( $row = $position['row_start']; $row < $position['row_start'] + $position['height']; $row++ ) {
				for ( $col = $position['column_start']; $col < $position['column_start'] + $position['width']; $col++ ) {
					$cell_key = $row . '-' . $col;

					if ( isset( $occupied[ $cell_key ] ) ) {
						$overlaps[] = $component_id . ' overlaps with ' . $occupied[ $cell_key ];
					} else {
						$occupied[ $cell_key ] = $component_id;
					}
				}
			}
		}

		return $overlaps;
	}

	/**
	 * Validate CSS size value
	 *
	 * @since 3.4.0
	 * @param string $value CSS value.
	 * @return bool Valid status.
	 */
	private function is_valid_css_size( $value ) {
		// Basic validation for common CSS size values
		return preg_match( '/^(0|auto|inherit|initial|unset|\d+(\.\d+)?(px|em|rem|%|vh|vw|cm|mm|in|pt|pc|ex|ch|vmin|vmax)|calc\(.*\))$/', $value );
	}

	/**
	 * Get available components
	 *
	 * @since 3.4.0
	 * @return array Available components.
	 */
	public function get_available_components() {
		return $this->available_components;
	}

	/**
	 * Get breakpoints
	 *
	 * @since 3.4.0
	 * @return array Available breakpoints.
	 */
	public function get_breakpoints() {
		return $this->breakpoints;
	}

	/**
	 * Get default grid configuration
	 *
	 * @since 3.4.0
	 * @return array Default grid configuration.
	 */
	public function get_default_grid_config() {
		return $this->default_grid_config;
	}

	/**
	 * AJAX handler for saving layout
	 *
	 * @since 3.4.0
	 */
	public function ajax_save_layout() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_layout_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$layout_data = json_decode( stripslashes( $_POST['layout_data'] ), true );

		if ( ! $layout_data ) {
			wp_send_json_error( 'Invalid layout data' );
		}

		// Validate layout
		$validation = $this->validate_layout( $layout_data );

		if ( ! $validation['is_valid'] ) {
			wp_send_json_error( array( 'validation_errors' => $validation['errors'] ) );
		}

		// Save layout
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'poker_display_layouts',
			array(
				'tournament_id' => intval( $layout_data['tournament_id'] ),
				'layout_name' => sanitize_text_field( $layout_data['layout_name'] ),
				'screen_size' => sanitize_text_field( $layout_data['screen_size'] ?? '' ),
				'grid_config' => json_encode( $layout_data['grid_config'] ),
				'component_positions' => json_encode( $layout_data['component_positions'] ),
				'breakpoints' => json_encode( $layout_data['breakpoints'] ?? array() ),
				'is_active' => isset( $layout_data['is_active'] ) ? (bool) $layout_data['is_active'] : true,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( $result ) {
			wp_send_json_success( array(
				'layout_id' => $wpdb->insert_id,
				'validation_warnings' => $validation['warnings'],
			) );
		} else {
			wp_send_json_error( 'Failed to save layout' );
		}
	}

	/**
	 * AJAX handler for getting layout
	 *
	 * @since 3.4.0
	 */
	public function ajax_get_layout() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_layout_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$layout_id = isset( $_GET['layout_id'] ) ? intval( $_GET['layout_id'] ) : 0;

		if ( $layout_id <= 0 ) {
			wp_send_json_error( 'Invalid layout ID' );
		}

		global $wpdb;

		$layout = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}poker_display_layouts WHERE layout_id = %d",
			$layout_id
		) );

		if ( ! $layout ) {
			wp_send_json_error( 'Layout not found' );
		}

		// Decode JSON fields
		$layout->grid_config = json_decode( $layout->grid_config, true );
		$layout->component_positions = json_decode( $layout->component_positions, true );
		$layout->breakpoints = json_decode( $layout->breakpoints, true );

		wp_send_json_success( $layout );
	}

	/**
	 * AJAX handler for getting components
	 *
	 * @since 3.4.0
	 */
	public function ajax_get_components() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_layout_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		wp_send_json_success( $this->get_available_components() );
	}

	/**
	 * AJAX handler for validating layout
	 *
	 * @since 3.4.0
	 */
	public function ajax_validate_layout() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_layout_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$layout_data = json_decode( stripslashes( $_POST['layout_data'] ), true );

		if ( ! $layout_data ) {
			wp_send_json_error( 'Invalid layout data' );
		}

		$validation = $this->validate_layout( $layout_data );

		wp_send_json_success( $validation );
	}

	/**
	 * Enhanced component position tracking with metadata
	 *
	 * @since 3.4.0
	 * @param array $layout_data Layout configuration.
	 * @return array Enhanced position data with metadata.
	 */
	public function track_component_positions( $layout_data ) {
		if ( empty( $layout_data['components'] ) ) {
			return array();
		}

		$positions = array();
		$grid_size = $layout_data['grid_size'] ?? array( 'columns' => 12, 'rows' => 8 );

		foreach ( $layout_data['components'] as $component_id => $component ) {
			$position_data = array(
				'id' => $component_id,
				'type' => $component['type'] ?? 'unknown',
				'grid_position' => array(
					'start_col' => $component['gridPosition']['x'] ?? 1,
					'start_row' => $component['gridPosition']['y'] ?? 1,
					'span_cols' => $component['width'] ?? 2,
					'span_rows' => $component['height'] ?? 2,
				),
				'breakpoints' => array(),
				'overlaps' => array(),
				'metadata' => array(
					'last_modified' => current_time( 'mysql' ),
					'created_by' => get_current_user_id(),
					'display_order' => 0,
				),
			);

			// Calculate responsive positions for each breakpoint
			foreach ( $this->get_breakpoints() as $breakpoint => $config ) {
				$responsive_pos = $this->calculate_responsive_position(
					$position_data['grid_position'],
					$grid_size,
					$config['columns']
				);
				$position_data['breakpoints'][ $breakpoint ] = $responsive_pos;
			}

			// Calculate display order based on vertical position
			$position_data['metadata']['display_order'] = ( $position_data['grid_position']['start_row'] * $grid_size['columns'] ) + $position_data['grid_position']['start_col'];

			$positions[ $component_id ] = $position_data;
		}

		// Check for overlapping components
		$positions = $this->detect_component_overlaps( $positions );

		return $positions;
	}

	/**
	 * Calculate responsive position for different screen sizes
	 *
	 * @since 3.4.0
	 * @param array $grid_pos Original grid position.
	 * @param array $base_grid Base grid size.
	 * @param int   $target_cols Target number of columns.
	 * @return array Responsive position data.
	 */
	private function calculate_responsive_position( $grid_pos, $base_grid, $target_cols ) {
		$scale_factor = $target_cols / $base_grid['columns'];

		$responsive = array(
			'start_col' => max( 1, round( $grid_pos['start_col'] * $scale_factor ) ),
			'start_row' => $grid_pos['start_row'], // Maintain row positions
			'span_cols' => max( 1, round( $grid_pos['span_cols'] * $scale_factor ) ),
			'span_rows' => $grid_pos['span_rows'],
		);

		// Ensure component fits within grid bounds
		if ( $responsive['start_col'] + $responsive['span_cols'] - 1 > $target_cols ) {
			$responsive['start_col'] = max( 1, $target_cols - $responsive['span_cols'] + 1 );
		}

		return $responsive;
	}

	/**
	 * Detect overlapping components in layout
	 *
	 * @since 3.4.0
	 * @param array $positions Component positions array.
	 * @return array Updated positions with overlap data.
	 */
	private function detect_component_overlaps( $positions ) {
		foreach ( $positions as $id => $position ) {
			$rect1 = array(
				'left' => $position['grid_position']['start_col'],
				'right' => $position['grid_position']['start_col'] + $position['grid_position']['span_cols'] - 1,
				'top' => $position['grid_position']['start_row'],
				'bottom' => $position['grid_position']['start_row'] + $position['grid_position']['span_rows'] - 1,
			);

			foreach ( $positions as $other_id => $other_position ) {
				if ( $id === $other_id ) {
					continue;
				}

				$rect2 = array(
					'left' => $other_position['grid_position']['start_col'],
					'right' => $other_position['grid_position']['start_col'] + $other_position['grid_position']['span_cols'] - 1,
					'top' => $other_position['grid_position']['start_row'],
					'bottom' => $other_position['grid_position']['start_row'] + $other_position['grid_position']['span_rows'] - 1,
				);

				if ( $this->rectangles_overlap( $rect1, $rect2 ) ) {
					$positions[ $id ]['overlaps'][] = $other_id;
				}
			}
		}

		return $positions;
	}

	/**
	 * Check if two rectangles overlap
	 *
	 * @since 3.4.0
	 * @param array $rect1 First rectangle.
	 * @param array $rect2 Second rectangle.
	 * @return bool True if rectangles overlap.
	 */
	private function rectangles_overlap( $rect1, $rect2 ) {
		return ! ( $rect1['right'] < $rect2['left'] ||
				  $rect1['left'] > $rect2['right'] ||
				  $rect1['bottom'] < $rect2['top'] ||
				  $rect1['top'] > $rect2['bottom'] );
	}

	/**
	 * Export layout configuration to JSON
	 *
	 * @since 3.4.0
	 * @param int $layout_id Layout ID to export.
	 * @return array|false Export data or false on failure.
	 */
	public function export_layout( $layout_id ) {
		global $wpdb;

		$layout = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}poker_display_layouts WHERE layout_id = %d",
			$layout_id
		) );

		if ( ! $layout ) {
			return false;
		}

		$export_data = array(
			'layout_info' => array(
				'name' => $layout->layout_name,
				'screen_size' => $layout->screen_size,
				'tournament_id' => $layout->tournament_id,
				'is_active' => $layout->is_active,
				'created_at' => $layout->created_at,
				'updated_at' => $layout->updated_at,
				'version' => '3.4.0',
			),
			'layout_config' => json_decode( $layout->layout_data, true ),
			'components' => $this->track_component_positions( json_decode( $layout->layout_data, true ) ),
			'breakpoints' => $this->get_breakpoints(),
			'available_components' => $this->get_available_components(),
		);

		return $export_data;
	}

	/**
	 * Import layout configuration from JSON
	 *
	 * @since 3.4.0
	 * @param array $import_data Import data.
	 * @param int   $tournament_id Target tournament ID.
	 * @return int|false New layout ID or false on failure.
	 */
	public function import_layout( $import_data, $tournament_id ) {
		global $wpdb;

		// Validate import data structure
		if ( empty( $import_data['layout_info'] ) || empty( $import_data['layout_config'] ) ) {
			return false;
		}

		$layout_info = $import_data['layout_info'];
		$layout_config = $import_data['layout_config'];

		// Validate layout configuration
		$validation = $this->validate_layout( $layout_config );
		if ( ! $validation['valid'] ) {
			return false;
		}

		// Generate unique layout name if conflict exists
		$layout_name = $layout_info['name'] ?? 'Imported Layout';
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}poker_display_layouts
			 WHERE tournament_id = %d AND layout_name = %s",
			$tournament_id, $layout_name
		) );

		if ( $existing ) {
			$layout_name .= ' - Imported ' . date( 'Y-m-d H:i:s' );
		}

		// Insert new layout
		$result = $wpdb->insert(
			$wpdb->prefix . 'poker_display_layouts',
			array(
				'tournament_id' => $tournament_id,
				'layout_name' => $layout_name,
				'screen_size' => $layout_info['screen_size'] ?? 'custom',
				'layout_data' => wp_json_encode( $layout_config ),
				'is_active' => 0, // Don't activate imported layouts by default
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Create live preview with actual tournament data
	 *
	 * @since 3.4.0
	 * @param int   $layout_id Layout ID.
	 * @param int   $tournament_id Tournament ID.
	 * @param array $preview_options Preview options.
	 * @return array Preview data with rendered HTML and CSS.
	 */
	public function create_preview( $layout_id, $tournament_id, $preview_options = array() ) {
		$layout_data = $this->load_layout( $layout_id, $tournament_id );

		if ( ! $layout_data ) {
			return false;
		}

		// Get live tournament data
		$tournament_data = $this->get_tournament_preview_data( $tournament_id );

		// Merge preview options with defaults
		$options = wp_parse_args( $preview_options, array(
			'screen_size' => 'desktop',
			'show_grid' => false,
			'live_data' => true,
			'auto_refresh' => false,
		) );

		// Generate preview HTML
		$preview_html = $this->render_layout_preview( $layout_data, $tournament_data, $options );

		// Generate preview CSS
		$preview_css = $this->generate_layout_css( $layout_data, $options['screen_size'] );

		return array(
			'html' => $preview_html,
			'css' => $preview_css,
			'screen_size' => $options['screen_size'],
			'tournament_data' => $tournament_data,
			'layout_info' => array(
				'name' => $layout_data['layout_name'],
				'component_count' => count( $layout_data['components'] ?? array() ),
				'grid_size' => $layout_data['grid_size'],
			),
			'preview_options' => $options,
		);
	}

	/**
	 * Get tournament data for preview rendering
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return array Tournament preview data.
	 */
	private function get_tournament_preview_data( $tournament_id ) {
		// Check if tournament is currently active and get live state
		$live_data = array();

		// Try to get live tournament data from transient cache
		$live_state = get_transient( "tdwp_tournament_live_state_{$tournament_id}" );

		if ( $live_state ) {
			$live_data = $live_state;
		} else {
			// Get tournament basic info
			$tournament = get_post( $tournament_id );
			if ( $tournament && $tournament->post_type === 'tournament' ) {
				$live_data = array(
					'tournament_name' => $tournament->post_title,
					'status' => 'scheduled', // Default status
					'current_level' => 1,
					'current_blinds' => '10/20',
					'next_blinds' => '15/30',
					'time_remaining' => '20:00',
					'players_remaining' => 0,
					'prize_pool' => '$0',
					'clock_running' => false,
				);
			}
		}

		// Add mock data for demonstration if no live data
		if ( empty( $live_data ) ) {
			$live_data = array(
				'tournament_name' => 'Demo Tournament',
				'status' => 'active',
				'current_level' => 3,
				'current_blinds' => '50/100',
				'next_blinds' => '75/150',
				'time_remaining' => '15:30',
				'players_remaining' => 27,
				'prize_pool' => '$2,700',
				'clock_running' => true,
			);
		}

		return apply_filters( 'tdwp_preview_tournament_data', $live_data, $tournament_id );
	}

	/**
	 * Render layout preview HTML with tournament data
	 *
	 * @since 3.4.0
	 * @param array $layout_data Layout configuration.
	 * @param array $tournament_data Tournament data.
	 * @param array $options Preview options.
	 * @return string Rendered HTML.
	 */
	private function render_layout_preview( $layout_data, $tournament_data, $options ) {
		$html = '<div class="tdwp-preview-container" data-screen-size="' . esc_attr( $options['screen_size'] ) . '">';

		if ( $options['show_grid'] ) {
			$html .= '<div class="tdwp-grid-overlay"></div>';
		}

		if ( ! empty( $layout_data['components'] ) ) {
			foreach ( $layout_data['components'] as $component_id => $component ) {
				$component_html = $this->render_component_preview( $component, $tournament_data, $options );
				$html .= $component_html;
			}
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Render individual component in preview
	 *
	 * @since 3.4.0
	 * @param array $component Component data.
	 * @param array $tournament_data Tournament data.
	 * @param array $options Preview options.
	 * @return string Component HTML.
	 */
	private function render_component_preview( $component, $tournament_data, $options ) {
		$style = $this->generate_component_style( $component, $options['screen_size'] );
		$content = $this->render_component_content( $component, $tournament_data );

		$html = sprintf(
			'<div class="tdwp-component tdwp-component-%1$s" id="%2$s" style="%3$s">',
			esc_attr( $component['type'] ),
			esc_attr( $component['id'] ),
			esc_attr( $style )
		);

		$html .= '<div class="tdwp-component-inner">';
		$html .= $content;
		$html .= '</div>';

		// Add auto-refresh script if enabled
		if ( $options['auto_refresh'] && $options['live_data'] ) {
			$html .= '<script class="tdwp-refresh-script" data-component="' . esc_attr( $component['id'] ) . '">';
			$html .= 'setInterval(function() { TDWP_Layout_Builder.refreshComponent("' . esc_js( $component['id'] ) . '"); }, 5000);';
			$html .= '</script>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Render component content with tournament data
	 *
	 * @since 3.4.0
	 * @param array $component Component configuration.
	 * @param array $tournament_data Tournament data.
	 * @return string Rendered content.
	 */
	private function render_component_content( $component, $tournament_data ) {
		$content = '';
		$component_type = $component['type'] ?? 'unknown';

		switch ( $component_type ) {
			case 'tournament_name':
				$content = '<h1 class="tournament-name">' . esc_html( $tournament_data['tournament_name'] ?? 'Tournament Name' ) . '</h1>';
				break;

			case 'clock_display':
				$content = '<div class="clock-display">';
				$content .= '<div class="time-remaining">' . esc_html( $tournament_data['time_remaining'] ?? '00:00' ) . '</div>';
				$content .= '<div class="level-info">Level ' . esc_html( $tournament_data['current_level'] ?? 1 ) . '</div>';
				$content .= '</div>';
				break;

			case 'current_blinds':
				$content = '<div class="blinds-display">';
				$content .= '<div class="current-blinds">' . esc_html( $tournament_data['current_blinds'] ?? '10/20' ) . '</div>';
				$content .= '<div class="next-blinds">Next: ' . esc_html( $tournament_data['next_blinds'] ?? '15/30' ) . '</div>';
				$content .= '</div>';
				break;

			case 'player_count':
				$content = '<div class="player-count">';
				$content .= '<div class="players-remaining">' . esc_html( $tournament_data['players_remaining'] ?? 0 ) . '</div>';
				$content .= '<div class="players-label">Players</div>';
				$content .= '</div>';
				break;

			case 'prize_pool':
				$content = '<div class="prize-pool">';
				$content .= '<div class="prize-amount">' . esc_html( $tournament_data['prize_pool'] ?? '$0' ) . '</div>';
				$content .= '<div class="prize-label">Prize Pool</div>';
				$content .= '</div>';
				break;

			default:
				$content = '<div class="component-placeholder">';
				$content .= '<h4>' . esc_html( ucfirst( str_replace( '_', ' ', $component_type ) ) ) . '</h4>';
				$content .= '<p>Component content will appear here</p>';
				$content .= '</div>';
				break;
		}

		// Apply template overrides if available
		$template_override = apply_filters( 'tdwp_component_template_' . $component_type, '', $component, $tournament_data );
		if ( $template_override ) {
			$content = $template_override;
		}

		return $content;
	}

	/**
	 * AJAX handler for layout export
	 *
	 * @since 3.4.0
	 */
	public function ajax_export_layout() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_layout_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$layout_id = intval( $_POST['layout_id'] );
		$export_data = $this->export_layout( $layout_id );

		if ( ! $export_data ) {
			wp_send_json_error( 'Failed to export layout' );
		}

		// Generate filename
		$filename = 'layout_export_' . $export_data['layout_info']['name'] . '_' . date( 'Y-m-d' ) . '.json';
		$filename = sanitize_file_name( $filename );

		wp_send_json_success( array(
			'filename' => $filename,
			'data' => $export_data,
		) );
	}

	/**
	 * AJAX handler for layout import
	 *
	 * @since 3.4.0
	 */
	public function ajax_import_layout() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_layout_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$tournament_id = intval( $_POST['tournament_id'] );
		$import_data = json_decode( stripslashes( $_POST['import_data'] ), true );

		if ( ! $import_data ) {
			wp_send_json_error( 'Invalid import data' );
		}

		$new_layout_id = $this->import_layout( $import_data, $tournament_id );

		if ( ! $new_layout_id ) {
			wp_send_json_error( 'Failed to import layout' );
		}

		wp_send_json_success( array(
			'layout_id' => $new_layout_id,
			'message' => 'Layout imported successfully',
		) );
	}

	/**
	 * AJAX handler for layout preview
	 *
	 * @since 3.4.0
	 */
	public function ajax_create_preview() {
		// Verify nonce and capabilities
		if ( ! check_ajax_referer( 'tdwp_layout_nonce', 'nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$layout_id = intval( $_POST['layout_id'] );
		$tournament_id = intval( $_POST['tournament_id'] );
		$preview_options = json_decode( stripslashes( $_POST['preview_options'] ?? '{}' ), true );

		$preview = $this->create_preview( $layout_id, $tournament_id, $preview_options );

		if ( ! $preview ) {
			wp_send_json_error( 'Failed to create preview' );
		}

		wp_send_json_success( $preview );
	}

	/**
	 * Get all layouts from database
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Optional tournament ID to filter layouts.
	 * @return array Array of layout objects.
	 */
	public function get_all_layouts( $tournament_id = 0 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'poker_display_layouts';

		$sql = "SELECT * FROM {$table_name}";
		$where_sql = '';
		$sql_params = array();

		if ( $tournament_id > 0 ) {
			$where_sql = " WHERE tournament_id = %d";
			$sql_params[] = $tournament_id;
		}

		$sql .= $where_sql . " ORDER BY created_at DESC";

		if ( ! empty( $sql_params ) ) {
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $sql_params ) );
		} else {
			$results = $wpdb->get_results( $sql );
		}

		$layouts = array();

		foreach ( $results as $row ) {
			$layout = array(
				'layout_id' => intval( $row->layout_id ),
				'tournament_id' => intval( $row->tournament_id ),
				'layout_name' => $row->layout_name,
				'screen_size' => $row->screen_size,
				'layout_type' => $row->layout_type ?? 'custom',
				'grid_config' => json_decode( $row->grid_config, true ),
				'component_positions' => json_decode( $row->component_positions, true ),
				'breakpoints' => json_decode( $row->breakpoints, true ),
				'is_active' => (bool) $row->is_active,
				'created_at' => $row->created_at,
				'updated_at' => $row->updated_at,
			);

			// Ensure JSON fields are arrays
			$layout['grid_config'] = is_array( $layout['grid_config'] ) ? $layout['grid_config'] : array();
			$layout['component_positions'] = is_array( $layout['component_positions'] ) ? $layout['component_positions'] : array();
			$layout['breakpoints'] = is_array( $layout['breakpoints'] ) ? $layout['breakpoints'] : array();

			// Count components
			$layout['components'] = $layout['component_positions'];

			$layouts[] = $layout;
		}

		return $layouts;
	}

	/**
	 * Load a single layout by ID
	 *
	 * @since 3.4.0
	 * @param int $layout_id Layout ID to load.
	 * @return array|false Layout data array or false if not found.
	 */
	public function load_layout( $layout_id ) {
		global $wpdb;

		$layout_id = intval( $layout_id );
		if ( $layout_id <= 0 ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'poker_display_layouts';

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE layout_id = %d",
			$layout_id
		) );

		if ( ! $row ) {
			return false;
		}

		$layout = array(
			'layout_id' => intval( $row->layout_id ),
			'tournament_id' => intval( $row->tournament_id ),
			'layout_name' => $row->layout_name,
			'screen_size' => $row->screen_size,
			'layout_type' => $row->layout_type ?? 'custom',
			'grid_config' => json_decode( $row->grid_config, true ),
			'component_positions' => json_decode( $row->component_positions, true ),
			'breakpoints' => json_decode( $row->breakpoints, true ),
			'is_active' => (bool) $row->is_active,
			'created_at' => $row->created_at,
			'updated_at' => $row->updated_at,
		);

		// Ensure JSON fields are arrays
		$layout['grid_config'] = is_array( $layout['grid_config'] ) ? $layout['grid_config'] : array();
		$layout['component_positions'] = is_array( $layout['component_positions'] ) ? $layout['component_positions'] : array();
		$layout['breakpoints'] = is_array( $layout['breakpoints'] ) ? $layout['breakpoints'] : array();

		return $layout;
	}
}