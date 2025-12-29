<?php
/**
 * TD3 Integration: Template Engine
 *
 * Extends existing formula tokenizer for {{token}} pattern recognition and rendering.
 * Handles dynamic content replacement in tournament display templates.
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
 * Template Engine Class
 *
 * Extends the existing formula tokenizer to support {{token}} patterns for
 * dynamic tournament display content. Integrates with WordPress template system
 * and provides caching for performance optimization.
 *
 * @since 3.4.0
 */
class TDWP_Template_Engine {

	/**
	 * Singleton instance
	 *
	 * @var TDWP_Template_Engine|null
	 */
	private static $instance = null;

	/**
	 * Token registry
	 *
	 * @var array
	 */
	private $token_registry = array();

	/**
	 * Cache expiration time in seconds
	 *
	 * @var int
	 */
	private $cache_expiry = 300; // 5 minutes

	/**
	 * Get singleton instance
	 *
	 * @since 3.4.0
	 * @return TDWP_Template_Engine
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
		$this->init_token_registry();
	}

	/**
	 * Initialize token registry
	 *
	 * @since 3.4.0
	 */
	private function init_token_registry() {
		global $wpdb;

		// DEBUG: Log detailed information about table creation process
		error_log( 'TDWP Template Engine: Starting token registry initialization' );
		error_log( 'TDWP Template Engine: Database prefix = "' . $wpdb->prefix . '"' );

		$table_name = $wpdb->prefix . 'tdwp_display_tokens';
		error_log( 'TDWP Template Engine: Looking for table = "' . $table_name . '"' );

		// Check if table exists before querying
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
		error_log( 'TDWP Template Engine: Table exists = ' . ( $table_exists ? 'YES' : 'NO' ) );

		if ( ! $table_exists ) {
			error_log( 'TDWP Template Engine: ERROR - Table does not exist, attempting to create it' );
			$this->create_tokens_table_fallback();
		}

		// Load tokens from database
		error_log( 'TDWP Template Engine: Executing query: SELECT * FROM ' . $table_name . ' WHERE is_active = 1 ORDER BY token_name' );
		$db_tokens = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}tdwp_display_tokens WHERE is_active = 1 ORDER BY token_name"
		);

		error_log( 'TDWP Template Engine: Query returned ' . ( is_array( $db_tokens ) ? count( $db_tokens ) : 0 ) . ' tokens' );

		if ( $wpdb->last_error ) {
			error_log( 'TDWP Template Engine: Database error = ' . $wpdb->last_error );
		}

		foreach ( $db_tokens as $token ) {
			$this->token_registry[ $token->token_name ] = array(
				'description' => $token->token_description,
				'type' => $token->token_type,
				'data_source' => $token->data_source,
				'default_format' => $token->default_format,
			);
		}

		// Add built-in tokens if not in database
		$this->add_builtin_tokens();
	}

	/**
	 * Add built-in tokens
	 *
	 * @since 3.4.0
	 */
	private function add_builtin_tokens() {
		$builtin_tokens = array(
			'tournament_name' => array(
				'description' => 'Tournament name',
				'type' => 'tournament',
				'data_source' => 'get_tournament_name',
				'default_format' => '%s',
			),
			'current_blind' => array(
				'description' => 'Current blind level',
				'type' => 'blind',
				'data_source' => 'get_current_blind',
				'default_format' => '%s',
			),
			'next_blind' => array(
				'description' => 'Next blind level',
				'type' => 'blind',
				'data_source' => 'get_next_blind',
				'default_format' => '%s',
			),
			'time_remaining' => array(
				'description' => 'Time remaining in current level',
				'type' => 'time',
				'data_source' => 'get_time_remaining',
				'default_format' => '%s',
			),
			'players_remaining' => array(
				'description' => 'Number of players still in tournament',
				'type' => 'player',
				'data_source' => 'get_players_remaining',
				'default_format' => '%d',
			),
			'prize_pool' => array(
				'description' => 'Total prize pool',
				'type' => 'prize',
				'data_source' => 'get_prize_pool',
				'default_format' => '$%.2f',
			),
			'current_level' => array(
				'description' => 'Current blind level number',
				'type' => 'blind',
				'data_source' => 'get_current_level',
				'default_format' => '%d',
			),
			'total_levels' => array(
				'description' => 'Total number of blind levels',
				'type' => 'blind',
				'data_source' => 'get_total_levels',
				'default_format' => '%d',
			),
			'entries_count' => array(
				'description' => 'Total number of entries',
				'type' => 'player',
				'data_source' => 'get_entries_count',
				'default_format' => '%d',
			),
			'clock_status' => array(
				'description' => 'Clock status (running/paused/stopped)',
				'type' => 'time',
				'data_source' => 'get_clock_status',
				'default_format' => '%s',
			),
			'average_stack' => array(
				'description' => 'Average chip stack size',
				'type' => 'player',
				'data_source' => 'get_average_stack',
				'default_format' => '%.0f',
			),
			'big_blind_percentage' => array(
				'description' => 'Big blind as percentage of average stack',
				'type' => 'blind',
				'data_source' => 'get_big_blind_percentage',
				'default_format' => '%.1f%%',
			),
			'big_blind_amount' => array(
				'description' => 'Current big blind amount',
				'type' => 'blind',
				'data_source' => 'get_big_blind_amount',
				'default_format' => '%d',
			),
			'small_blind_amount' => array(
				'description' => 'Current small blind amount',
				'type' => 'blind',
				'data_source' => 'get_small_blind_amount',
				'default_format' => '%d',
			),
			'ante_amount' => array(
				'description' => 'Current ante amount',
				'type' => 'blind',
				'data_source' => 'get_ante_amount',
				'default_format' => '%d',
			),
			'prize_pool_formatted' => array(
				'description' => 'Total prize pool with currency formatting',
				'type' => 'prize',
				'data_source' => 'get_prize_pool_formatted',
				'default_format' => '%s',
			),
			'start_time' => array(
				'description' => 'Tournament start time',
				'type' => 'time',
				'data_source' => 'get_start_time',
				'default_format' => '%s',
			),
			'tournament_duration' => array(
				'description' => 'How long tournament has been running',
				'type' => 'time',
				'data_source' => 'get_tournament_duration',
				'default_format' => '%s',
			),
			'players_eliminated' => array(
				'description' => 'Number of players eliminated',
				'type' => 'player',
				'data_source' => 'get_players_eliminated',
				'default_format' => '%d',
			),
			'elimination_percentage' => array(
				'description' => 'Percentage of players eliminated',
				'type' => 'player',
				'data_source' => 'get_elimination_percentage',
				'default_format' => '%.1f%%',
			),
			'current_round_info' => array(
				'description' => 'Current round with time remaining',
				'type' => 'blind',
				'data_source' => 'get_current_round_info',
				'default_format' => '%s',
			),
			'tournament_status' => array(
				'description' => 'Current tournament status',
				'type' => 'tournament',
				'data_source' => 'get_tournament_status',
				'default_format' => '%s',
			),
		);

		foreach ( $builtin_tokens as $token_name => $token_data ) {
			if ( ! isset( $this->token_registry[ $token_name ] ) ) {
				$this->token_registry[ $token_name ] = $token_data;
			}
		}
	}

	/**
	 * Render template with token replacement
	 *
	 * @since 3.4.0
	 * @param string $template Template content with {{tokens}}.
	 * @param int    $tournament_id Tournament ID.
	 * @param array  $options Rendering options.
	 * @return string Rendered content.
	 */
	public function render_template( $template, $tournament_id, $options = array() ) {
		// Default options
		$defaults = array(
			'use_cache' => true,
			'apply_filters' => true,
			'sanitize_output' => true,
			'wrap_in_wpautop' => false
		);
		$options = wp_parse_args( $options, $defaults );

		// Check cache first
		if ( $options['use_cache'] ) {
			$cache_key = "tdwp_template_{$tournament_id}_" . md5( $template );
			$cached_content = get_transient( $cache_key );

			if ( false !== $cached_content ) {
				return $this->apply_template_filters( $cached_content, $options, $tournament_id );
			}
		}

		// Apply pre-render filters
		if ( $options['apply_filters'] ) {
			$template = apply_filters( 'tdwp_template_pre_render', $template, $tournament_id, $options );
		}

		// Find all tokens in template
		$tokens = $this->find_tokens( $template );

		if ( empty( $tokens ) ) {
			$rendered_content = $template;
		} else {
			// Get token values with error handling
			$token_values = $this->get_token_values( $tokens, $tournament_id );

			// Replace tokens with actual values using pattern matching
			$rendered_content = $template;
			foreach ( $token_values as $pattern => $value ) {
				$rendered_content = str_replace( $pattern, $value, $rendered_content );
			}
		}

		// Handle WordPress template functions
		$rendered_content = $this->process_wp_template_functions( $rendered_content, $tournament_id );

		// Apply post-render filters
		if ( $options['apply_filters'] ) {
			$rendered_content = apply_filters( 'tdwp_template_post_render', $rendered_content, $tournament_id, $options );
		}

		// Sanitize output if requested
		if ( $options['sanitize_output'] ) {
			$rendered_content = wp_kses_post( $rendered_content );
		}

		// Wrap in wpautop if requested
		if ( $options['wrap_in_wpautop'] ) {
			$rendered_content = wpautop( $rendered_content );
		}

		// Cache the result
		if ( $options['use_cache'] ) {
			$cache_key = "tdwp_template_{$tournament_id}_" . md5( $template );
			set_transient( $cache_key, $rendered_content, $this->cache_expiry );
		}

		return $rendered_content;
	}

	/**
	 * Apply template filters
	 *
	 * @since 3.4.0
	 * @param string $content Template content.
	 * @param array  $options Rendering options.
	 * @param int    $tournament_id Tournament ID.
	 * @return string Filtered content.
	 */
	private function apply_template_filters( $content, $options, $tournament_id ) {
		if ( $options['apply_filters'] ) {
			$content = apply_filters( 'tdwp_template_cached_content', $content, $tournament_id, $options );
		}

		if ( $options['sanitize_output'] ) {
			$content = wp_kses_post( $content );
		}

		return $content;
	}

	/**
	 * Process WordPress template functions
	 *
	 * @since 3.4.0
	 * @param string $content Template content.
	 * @param int    $tournament_id Tournament ID.
	 * @return string Processed content.
	 */
	private function process_wp_template_functions( $content, $tournament_id ) {
		// Process do_action() calls
		$content = preg_replace_callback(
			'/\{\{\s*do_action:\s*([a-zA-Z0-9_-]+)(?:\s*\|\s*([^\}]+))?\s*\}\}/',
			function( $matches ) use ( $tournament_id ) {
				$action_name = $matches[1];
				$args = isset( $matches[2] ) ? explode( '|', $matches[2] ) : array();
				array_unshift( $args, $tournament_id );

				ob_start();
				do_action_ref_array( $action_name, $args );
				return ob_get_clean();
			},
			$content
		);

		// Process apply_filters() calls
		$content = preg_replace_callback(
			'/\{\{\s*apply_filters:\s*([a-zA-Z0-9_-]+)(?:\s*\|\s*([^\}]+))?\s*\}\}/',
			function( $matches ) use ( $tournament_id ) {
				$filter_name = $matches[1];
				$value = isset( $matches[2] ) ? $matches[2] : '';

				return apply_filters( $filter_name, $value, $tournament_id );
			},
			$content
		);

		// Process WordPress function calls
		$content = preg_replace_callback(
			'/\{\{\s*wp_func:\s*([a-zA-Z0-9_]+)(?:\s*\|\s*([^\}]+))?\s*\}\}/',
			function( $matches ) use ( $tournament_id ) {
				$function_name = $matches[1];
				$args = isset( $matches[2] ) ? explode( '|', $matches[2] ) : array();

				if ( function_exists( $function_name ) ) {
					// Add tournament ID to arguments if needed
					if ( in_array( $function_name, array( 'get_permalink', 'get_edit_post_link' ) ) ) {
						array_unshift( $args, $tournament_id );
					}

					return call_user_func_array( $function_name, $args );
				}

				return '[Unknown function: ' . $function_name . ']';
			},
			$content
		);

		return $content;
	}

	/**
	 * Find tokens in template
	 *
	 * @since 3.4.0
	 * @param string $template Template content.
	 * @return array Found tokens with metadata.
	 */
	private function find_tokens( $template ) {
		$tokens = array();

		// Match {{token_name}} pattern (standard tokens)
		if ( preg_match_all( '/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/', $template, $matches ) ) {
			foreach ( array_unique( $matches[1] ) as $token ) {
				$tokens[ $token ] = array(
					'type' => 'standard',
					'pattern' => '{{' . $token . '}}',
					'source' => 'registry'
				);
			}
		}

		// Match {{formula:expression}} pattern (formula-based tokens)
		if ( preg_match_all( '/\{\{formula:([^}]+)\}\}/', $template, $matches ) ) {
			foreach ( array_unique( $matches[1] ) as $formula ) {
				$token_id = 'formula_' . md5( $formula );
				$tokens[ $token_id ] = array(
					'type' => 'formula',
					'pattern' => '{{formula:' . $formula . '}}',
					'source' => 'formula',
					'expression' => trim( $formula )
				);
			}
		}

		// Match {{td_variable}} pattern (Tournament Director variables)
		if ( preg_match_all( '/\{\{td:([a-zA-Z_][a-zA-Z0-9_]*)\}\}/', $template, $matches ) ) {
			foreach ( array_unique( $matches[1] ) as $td_var ) {
				$tokens[ $td_var ] = array(
					'type' => 'td_variable',
					'pattern' => '{{td:' . $td_var . '}}',
					'source' => 'td_variables'
				);
			}
		}

		return $tokens;
	}

	/**
	 * Get token values
	 *
	 * @since 3.4.0
	 * @param array $tokens Token metadata array.
	 * @param int   $tournament_id Tournament ID.
	 * @return array Token values with patterns for replacement.
	 */
	private function get_token_values( $tokens, $tournament_id ) {
		$values = array();

		foreach ( $tokens as $token_key => $token_meta ) {
			$value = '';
			$error = '';

			try {
				switch ( $token_meta['type'] ) {
					case 'standard':
						$value = $this->get_standard_token_value( $token_key, $tournament_id );
						break;

					case 'formula':
						$value = $this->evaluate_formula_token( $token_meta['expression'], $tournament_id );
						break;

					case 'td_variable':
						$value = $this->get_td_variable_value( $token_key, $tournament_id );
						break;

					default:
						$error = 'Unknown token type: ' . $token_meta['type'];
						break;
				}
			} catch ( Exception $e ) {
				$error = 'Token evaluation error: ' . $e->getMessage();
			}

			// Store with pattern as key for direct replacement
			if ( ! empty( $error ) ) {
				$values[ $token_meta['pattern'] ] = '[ERROR: ' . $error . ']';
			} else {
				$values[ $token_meta['pattern'] ] = $value;
			}
		}

		return $values;
	}

	/**
	 * Format token value
	 *
	 * @since 3.4.0
	 * @param mixed  $value Raw value.
	 * @param string $format Format string.
	 * @return string Formatted value.
	 */
	private function format_token_value( $value, $format ) {
		if ( null === $value ) {
			return 'N/A';
		}

		if ( false === $value ) {
			return 'No';
		}

		if ( true === $value ) {
			return 'Yes';
		}

		// Use sprintf for formatting
		if ( ! empty( $format ) && is_string( $value ) || is_numeric( $value ) ) {
			return sprintf( $format, $value );
		}

		return (string) $value;
	}

	/**
	 * Get tournament name
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return string Tournament name.
	 */
	private function get_tournament_name( $tournament_id ) {
		$post = get_post( $tournament_id );
		return $post ? $post->post_title : 'Unknown Tournament';
	}

	/**
	 * Get current blind level
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return string Current blind level.
	 */
	private function get_current_blind( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Live' ) ) {
			$live_state = TDWP_Tournament_Live::get_instance( $tournament_id );
			$current_level = $live_state->get_current_level();
			return $current_level ? $current_level->get_blind_string() : 'Not started';
		}
		return 'Not available';
	}

	/**
	 * Get next blind level
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return string Next blind level.
	 */
	private function get_next_blind( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Live' ) ) {
			$live_state = TDWP_Tournament_Live::get_instance( $tournament_id );
			$next_level = $live_state->get_next_level();
			return $next_level ? $next_level->get_blind_string() : 'Final level';
		}
		return 'Not available';
	}

	/**
	 * Get time remaining in current level
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return string Time remaining.
	 */
	private function get_time_remaining( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Live' ) ) {
			$live_state = TDWP_Tournament_Live::get_instance( $tournament_id );
			$time_remaining = $live_state->get_time_remaining();
			return $time_remaining ? $this->format_time( $time_remaining ) : 'N/A';
		}
		return 'Not available';
	}

	/**
	 * Get players remaining
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return int Number of players remaining.
	 */
	private function get_players_remaining( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Player_Manager' ) ) {
			$player_manager = TDWP_Tournament_Player_Manager::get_instance();
			return $player_manager->get_active_players_count( $tournament_id );
		}
		return 0;
	}

	/**
	 * Get prize pool
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return float Prize pool amount.
	 */
	private function get_prize_pool( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Player_Manager' ) ) {
			$player_manager = TDWP_Tournament_Player_Manager::get_instance();
			return $player_manager->calculate_prize_pool( $tournament_id );
		}
		return 0.0;
	}

	/**
	 * Get current level number
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return int Current level number.
	 */
	private function get_current_level( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Live' ) ) {
			$live_state = TDWP_Tournament_Live::get_instance( $tournament_id );
			return $live_state->get_current_level_number();
		}
		return 0;
	}

	/**
	 * Get total levels
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return int Total number of levels.
	 */
	private function get_total_levels( $tournament_id ) {
		if ( class_exists( 'TDWP_Blind_Schedule' ) ) {
			$schedule = TDWP_Blind_Schedule::get_tournament_schedule( $tournament_id );
			return $schedule ? $schedule->get_level_count() : 0;
		}
		return 0;
	}

	/**
	 * Get entries count
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return int Total entries count.
	 */
	private function get_entries_count( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Player_Manager' ) ) {
			$player_manager = TDWP_Tournament_Player_Manager::get_instance();
			return $player_manager->get_total_entries_count( $tournament_id );
		}
		return 0;
	}

	/**
	 * Get clock status
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return string Clock status.
	 */
	private function get_clock_status( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Live' ) ) {
			$live_state = TDWP_Tournament_Live::get_instance( $tournament_id );
			return $live_state->get_clock_status();
		}
		return 'Not available';
	}

	/**
	 * Format time display
	 *
	 * @since 3.4.0
	 * @param int $seconds Time in seconds.
	 * @return string Formatted time (MM:SS).
	 */
	private function format_time( $seconds ) {
		if ( $seconds < 0 ) {
			return '00:00';
		}

		$minutes = floor( $seconds / 60 );
		$remaining_seconds = $seconds % 60;

		return sprintf( '%02d:%02d', $minutes, $remaining_seconds );
	}

	/**
	 * Get average stack size
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return float Average stack size.
	 */
	private function get_average_stack( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Player_Manager' ) ) {
			$player_manager = TDWP_Tournament_Player_Manager::get_instance();
			$total_chips = $player_manager->get_total_chips_in_play( $tournament_id );
			$players_remaining = $player_manager->get_active_players_count( $tournament_id );

			return $players_remaining > 0 ? $total_chips / $players_remaining : 0;
		}
		return 0;
	}

	/**
	 * Get big blind percentage of average stack
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return float Big blind as percentage of average stack.
	 */
	private function get_big_blind_percentage( $tournament_id ) {
		$avg_stack = $this->get_average_stack( $tournament_id );
		$big_blind = $this->get_big_blind_amount( $tournament_id );

		return $avg_stack > 0 ? ($big_blind / $avg_stack) * 100 : 0;
	}

	/**
	 * Get big blind amount
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return int Big blind amount.
	 */
	private function get_big_blind_amount( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Live' ) ) {
			$live_state = TDWP_Tournament_Live::get_instance( $tournament_id );
			$current_level = $live_state->get_current_level();
			return $current_level ? $current_level->get_big_blind() : 0;
		}
		return 0;
	}

	/**
	 * Get small blind amount
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return int Small blind amount.
	 */
	private function get_small_blind_amount( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Live' ) ) {
			$live_state = TDWP_Tournament_Live::get_instance( $tournament_id );
			$current_level = $live_state->get_current_level();
			return $current_level ? $current_level->get_small_blind() : 0;
		}
		return 0;
	}

	/**
	 * Get ante amount
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return int Ante amount.
	 */
	private function get_ante_amount( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Live' ) ) {
			$live_state = TDWP_Tournament_Live::get_instance( $tournament_id );
			$current_level = $live_state->get_current_level();
			return $current_level ? $current_level->get_ante() : 0;
		}
		return 0;
	}

	/**
	 * Get total prize pool with currency
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return string Formatted prize pool with currency.
	 */
	private function get_prize_pool_formatted( $tournament_id ) {
		$prize_pool = $this->get_prize_pool( $tournament_id );
		return '$' . number_format( $prize_pool, 2 );
	}

	/**
	 * Get tournament start time
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return string Formatted start time.
	 */
	private function get_start_time( $tournament_id ) {
		$post = get_post( $tournament_id );
		if ( $post ) {
			$start_time = get_post_meta( $tournament_id, '_tournament_start_time', true );
			return $start_time ? date( 'g:i A', strtotime( $start_time ) ) : 'Not set';
		}
		return 'Not available';
	}

	/**
	 * Get tournament duration
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return string Tournament duration.
	 */
	private function get_tournament_duration( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Live' ) ) {
			$live_state = TDWP_Tournament_Live::get_instance( $tournament_id );
			$start_time = $live_state->get_start_time();

			if ( $start_time ) {
				$duration = time() - $start_time;
				$hours = floor( $duration / 3600 );
				$minutes = floor( ( $duration % 3600 ) / 60 );

				if ( $hours > 0 ) {
					return sprintf( '%dh %dm', $hours, $minutes );
				} else {
					return sprintf( '%dm', $minutes );
				}
			}
		}
		return 'Not started';
	}

	/**
	 * Get players eliminated
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return int Number of eliminated players.
	 */
	private function get_players_eliminated( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Player_Manager' ) ) {
			$player_manager = TDWP_Tournament_Player_Manager::get_instance();
			$total_entries = $player_manager->get_total_entries_count( $tournament_id );
			$players_remaining = $player_manager->get_active_players_count( $tournament_id );
			return $total_entries - $players_remaining;
		}
		return 0;
	}

	/**
	 * Get elimination percentage
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return float Percentage of players eliminated.
	 */
	private function get_elimination_percentage( $tournament_id ) {
		if ( class_exists( 'TDWP_Tournament_Player_Manager' ) ) {
			$player_manager = TDWP_Tournament_Player_Manager::get_instance();
			$total_entries = $player_manager->get_total_entries_count( $tournament_id );
			$players_eliminated = $this->get_players_eliminated( $tournament_id );

			return $total_entries > 0 ? ( $players_eliminated / $total_entries ) * 100 : 0;
		}
		return 0;
	}

	/**
	 * Get current round info
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return string Current round information.
	 */
	private function get_current_round_info( $tournament_id ) {
		$current_level = $this->get_current_level( $tournament_id );
		$total_levels = $this->get_total_levels( $tournament_id );
		$time_remaining = $this->get_time_remaining( $tournament_id );

		if ( $current_level > 0 ) {
			$info = "Round {$current_level}";
			if ( $total_levels > 0 ) {
				$info .= " of {$total_levels}";
			}
			if ( $time_remaining && $time_remaining !== 'N/A' ) {
				$info .= " - {$time_remaining} remaining";
			}
			return $info;
		}
		return 'Not started';
	}

	/**
	 * Get tournament status
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return string Tournament status.
	 */
	private function get_tournament_status( $tournament_id ) {
		$post = get_post( $tournament_id );
		if ( $post ) {
			$status = get_post_meta( $tournament_id, '_tournament_status', true );
			$status_map = array(
				'upcoming' => 'Upcoming',
				'registration' => 'Registration Open',
				'running' => 'In Progress',
				'break' => 'On Break',
				'completed' => 'Completed',
				'cancelled' => 'Cancelled'
			);
			return $status_map[ $status ] ?? 'Unknown';
		}
		return 'Not available';
	}

	/**
	 * Validate template
	 *
	 * @since 3.4.0
	 * @param string $template Template content.
	 * @return array Validation result.
	 */
	public function validate_template( $template ) {
		$tokens = $this->find_tokens( $template );
		$invalid_tokens = array();
		$valid_tokens = array();

		foreach ( $tokens as $token ) {
			if ( isset( $this->token_registry[ $token ] ) ) {
				$valid_tokens[] = $token;
			} else {
				$invalid_tokens[] = $token;
			}
		}

		return array(
			'is_valid' => empty( $invalid_tokens ),
			'valid_tokens' => $valid_tokens,
			'invalid_tokens' => $invalid_tokens,
			'token_count' => count( $tokens ),
		);
	}

	/**
	 * Get token registry
	 *
	 * @since 3.4.0
	 * @return array Available tokens.
	 */
	public function get_token_registry() {
		return $this->token_registry;
	}

	/**
	 * Clear template cache
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID (optional).
	 */
	public function clear_cache( $tournament_id = null ) {
		global $wpdb;

		if ( $tournament_id ) {
			// Clear cache for specific tournament
			$cache_key_pattern = "tdwp_template_{$tournament_id}_";
			wp_cache_flush(); // Simple approach - clear all cache
		} else {
			// Clear all template cache
			wp_cache_flush();
		}
	}

	/**
	 * Get standard token value from registry
	 *
	 * @since 3.4.0
	 * @param string $token_name Token name.
	 * @param int    $tournament_id Tournament ID.
	 * @return string Token value.
	 */
	private function get_standard_token_value( $token_name, $tournament_id ) {
		if ( ! isset( $this->token_registry[ $token_name ] ) ) {
			return '[Unknown token: ' . $token_name . ']';
		}

		$token_config = $this->token_registry[ $token_name ];
		$method_name = $token_config['data_source'];

		if ( method_exists( $this, $method_name ) ) {
			$value = $this->$method_name( $tournament_id );
			return $this->format_token_value( $value, $token_config['default_format'] );
		} else {
			return '[Unknown token: ' . $token_name . ']';
		}
	}

	/**
	 * Evaluate formula token using formula tokenizer
	 *
	 * @since 3.4.0
	 * @param string $expression Formula expression.
	 * @param int    $tournament_id Tournament ID.
	 * @return string Calculated value.
	 */
	private function evaluate_formula_token( $expression, $tournament_id ) {
		// Get formula validator instance if available
		if ( class_exists( 'Poker_Tournament_Formula_Validator' ) ) {
			$validator = new Poker_Tournament_Formula_Validator();

			// Get tournament data for formula context
			$context = $this->get_formula_context( $tournament_id );

			try {
				$result = $validator->validate_formula( $expression, $context );
				return $this->format_token_value( $result, '%.2f' );
			} catch ( Exception $e ) {
				return '[Formula error: ' . $e->getMessage() . ']';
			}
		}

		return '[Formula engine not available]';
	}

	/**
	 * Get Tournament Director variable value
	 *
	 * @since 3.4.0
	 * @param string $variable_name Variable name.
	 * @param int    $tournament_id Tournament ID.
	 * @return string Variable value.
	 */
	private function get_td_variable_value( $variable_name, $tournament_id ) {
		// Map common TD variables to our existing methods
		$td_mapping = array(
			'n' => 'get_players_remaining',
			'playersRemaining' => 'get_players_remaining',
			'currentLevel' => 'get_current_level',
			'totalLevels' => 'get_total_levels',
			'entriesCount' => 'get_entries_count',
			'prizePool' => 'get_prize_pool',
			'clockStatus' => 'get_clock_status',
			'timeRemaining' => 'get_time_remaining',
			'currentBlind' => 'get_current_blind',
			'nextBlind' => 'get_next_blind',
		);

		$method_name = $td_mapping[ $variable_name ] ?? null;

		if ( $method_name && method_exists( $this, $method_name ) ) {
			$value = $this->$method_name( $tournament_id );
			return $this->format_token_value( $value, '%s' );
		}

		return '[Unknown TD variable: ' . $variable_name . ']';
	}

	/**
	 * Get formula context data for tournament
	 *
	 * @since 3.4.0
	 * @param int $tournament_id Tournament ID.
	 * @return array Context data for formula evaluation.
	 */
	private function get_formula_context( $tournament_id ) {
		return array(
			'tournament_id' => $tournament_id,
			'players_remaining' => $this->get_players_remaining( $tournament_id ),
			'current_level' => $this->get_current_level( $tournament_id ),
			'total_levels' => $this->get_total_levels( $tournament_id ),
			'entries_count' => $this->get_entries_count( $tournament_id ),
			'prize_pool' => $this->get_prize_pool( $tournament_id ),
			'clock_status' => $this->get_clock_status( $tournament_id ),
			'time_remaining' => $this->get_time_remaining( $tournament_id ),
			'current_blind' => $this->get_current_blind( $tournament_id ),
			'next_blind' => $this->get_next_blind( $tournament_id ),
		);
	}

	/**
	 * Register custom token
	 *
	 * @since 3.4.0
	 * @param string $token_name Token name.
	 * @param array  $token_config Token configuration.
	 * @return bool Success status.
	 */
	public function register_token( $token_name, $token_config ) {
		global $wpdb;

		// Validate token name
		if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $token_name ) ) {
			return false;
		}

		// Prepare token data
		$token_data = array(
			'token_name' => $token_name,
			'token_description' => sanitize_text_field( $token_config['description'] ?? '' ),
			'token_type' => in_array( $token_config['type'], array( 'tournament', 'player', 'blind', 'prize', 'time', 'custom' ) ) ? $token_config['type'] : 'custom',
			'data_source' => sanitize_text_field( $token_config['data_source'] ?? '' ),
			'default_format' => sanitize_text_field( $token_config['default_format'] ?? '%s' ),
			'is_active' => 1,
		);

		// Insert into database
		$result = $wpdb->insert(
			$wpdb->prefix . 'tdwp_display_tokens',
			$token_data,
			array( '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( $result ) {
			// Update registry
			$this->token_registry[ $token_name ] = array(
				'description' => $token_data['token_description'],
				'type' => $token_data['token_type'],
				'data_source' => $token_data['data_source'],
				'default_format' => $token_data['default_format'],
			);

			return true;
		}

		return false;
	}

	/**
	 * Validate token configuration
	 *
	 * @since 3.4.0
	 * @param string $token_name Token name.
	 * @param array  $token_config Token configuration.
	 * @return array Validation result with errors and warnings.
	 */
	public function validate_token_config( $token_name, $token_config ) {
		$errors = array();
		$warnings = array();

		// Validate token name
		if ( empty( $token_name ) ) {
			$errors[] = 'Token name cannot be empty';
		} elseif ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $token_name ) ) {
			$errors[] = 'Token name must start with a letter or underscore and contain only letters, numbers, and underscores';
		} elseif ( strlen( $token_name ) > 50 ) {
			$errors[] = 'Token name cannot exceed 50 characters';
		}

		// Check for reserved tokens
		$reserved_tokens = array( 'formula', 'td', 'token', 'template', 'layout' );
		if ( in_array( strtolower( $token_name ), $reserved_tokens ) ) {
			$errors[] = 'Token name "' . $token_name . '" is reserved';
		}

		// Validate required fields
		$required_fields = array( 'description', 'type', 'data_source' );
		foreach ( $required_fields as $field ) {
			if ( empty( $token_config[ $field ] ) ) {
				$errors[] = 'Required field "' . $field . '" cannot be empty';
			}
		}

		// Validate token type
		$allowed_types = array( 'tournament', 'player', 'blind', 'prize', 'time', 'custom' );
		if ( ! empty( $token_config['type'] ) && ! in_array( $token_config['type'], $allowed_types ) ) {
			$warnings[] = 'Unknown token type "' . $token_config['type'] . '", will be set to "custom"';
		}

		// Validate data source
		if ( ! empty( $token_config['data_source'] ) ) {
			if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $token_config['data_source'] ) ) {
				$errors[] = 'Data source must be a valid method name';
			}

			// Check if method exists for built-in tokens
			if ( method_exists( $this, $token_config['data_source'] ) ) {
				$warnings[] = 'Data source method "' . $token_config['data_source'] . '" exists and will be used';
			}
		}

		// Validate default format
		if ( ! empty( $token_config['default_format'] ) ) {
			if ( ! $this->is_valid_format_string( $token_config['default_format'] ) ) {
				$warnings[] = 'Default format may be invalid, should use printf-style format strings';
			}
		}

		return array(
			'is_valid' => empty( $errors ),
			'errors' => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Validate format string
	 *
	 * @since 3.4.0
	 * @param string $format Format string to validate.
	 * @return bool True if format appears valid.
	 */
	private function is_valid_format_string( $format ) {
		// Basic validation for printf-style format strings
		$format_specifiers = array( '%s', '%d', '%f', '%.2f', '%.0f', '%u', '%c', '%x', '%o' );

		// Check if format contains at least one valid specifier
		foreach ( $format_specifiers as $specifier ) {
			if ( strpos( $format, $specifier ) !== false ) {
				return true;
			}
		}

		// Allow plain format strings (no specifiers)
		return ! preg_match( '/%[^a-zA-Z%]/', $format );
	}

	/**
	 * Get validation errors for template
	 *
	 * @since 3.4.0
	 * @param string $template Template content.
	 * @param int    $tournament_id Tournament ID.
	 * @return array Validation result.
	 */
	public function validate_template_tokens( $template, $tournament_id = null ) {
		$tokens = $this->find_tokens( $template );
		$invalid_tokens = array();
		$deprecated_tokens = array();
		$security_warnings = array();

		foreach ( $tokens as $token_key => $token_meta ) {
			switch ( $token_meta['type'] ) {
				case 'standard':
					if ( ! isset( $this->token_registry[ $token_key ] ) ) {
						$invalid_tokens[] = $token_key;
					}
					break;

				case 'formula':
					// Validate formula syntax
					if ( class_exists( 'Poker_Tournament_Formula_Validator' ) ) {
						$validator = new Poker_Tournament_Formula_Validator();
						try {
							$context = $tournament_id ? $this->get_formula_context( $tournament_id ) : array();
							$validator->validate_formula( $token_meta['expression'], $context );
						} catch ( Exception $e ) {
							$invalid_tokens[] = $token_meta['pattern'] . ' (formula error: ' . $e->getMessage() . ')';
						}
					} else {
						$security_warnings[] = 'Formula engine not available for: ' . $token_meta['pattern'];
					}
					break;

				case 'td_variable':
					// Check if TD variable is supported
					$td_mapping = array(
						'n', 'playersRemaining', 'currentLevel', 'totalLevels', 'entriesCount',
						'prizePool', 'clockStatus', 'timeRemaining', 'currentBlind', 'nextBlind'
					);
					if ( ! in_array( $token_key, $td_mapping ) ) {
						$invalid_tokens[] = $token_meta['pattern'] . ' (unsupported TD variable)';
					}
					break;
			}
		}

		return array(
			'is_valid' => empty( $invalid_tokens ) && empty( $security_warnings ),
			'invalid_tokens' => $invalid_tokens,
			'deprecated_tokens' => $deprecated_tokens,
			'security_warnings' => $security_warnings,
			'token_count' => count( $tokens ),
		);
	}

	/**
	 * Bulk register tokens with validation
	 *
	 * @since 3.4.0
	 * @param array $tokens Array of token configurations.
	 * @return array Registration results.
	 */
	public function bulk_register_tokens( $tokens ) {
		$results = array();
		$success_count = 0;
		$error_count = 0;

		foreach ( $tokens as $token_name => $token_config ) {
			$validation = $this->validate_token_config( $token_name, $token_config );

			if ( $validation['is_valid'] ) {
				$success = $this->register_token( $token_name, $token_config );
				if ( $success ) {
					$results[ $token_name ] = array(
						'status' => 'success',
						'warnings' => $validation['warnings']
					);
					$success_count++;
				} else {
					$results[ $token_name ] = array(
						'status' => 'error',
						'errors' => array( 'Failed to register token in database' )
					);
					$error_count++;
				}
			} else {
				$results[ $token_name ] = array(
					'status' => 'error',
					'errors' => $validation['errors'],
					'warnings' => $validation['warnings']
				);
				$error_count++;
			}
		}

		return array(
			'total_processed' => count( $tokens ),
			'success_count' => $success_count,
			'error_count' => $error_count,
			'results' => $results
		);
	}

	/**
	 * Fallback method to create tokens table if it doesn't exist
	 *
	 * @since 3.4.0
	 * @return bool True on success, false on failure
	 */
	private function create_tokens_table_fallback() {
		global $wpdb;

		error_log( 'TDWP Template Engine: Creating fallback tokens table' );

		$table_name = $wpdb->prefix . 'tdwp_display_tokens';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			token_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			token_name VARCHAR(100) NOT NULL,
			token_description TEXT NULL,
			token_type ENUM('tournament','player','blind','prize','time','custom') NOT NULL DEFAULT 'custom',
			data_source VARCHAR(255) NULL,
			default_format VARCHAR(255) NULL,
			is_active BOOLEAN NOT NULL DEFAULT TRUE,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (token_id),
			UNIQUE KEY idx_token_name (token_name),
			KEY idx_type_active (token_type, is_active)
		) ENGINE=InnoDB {$charset_collate};";

		error_log( 'TDWP Template Engine: Executing fallback SQL: ' . $sql );

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		// Check if table was created
		$table_exists_after = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
		error_log( 'TDWP Template Engine: Table exists after fallback creation = ' . ( $table_exists_after ? 'YES' : 'NO' ) );

		if ( $table_exists_after ) {
			error_log( 'TDWP Template Engine: Fallback table creation SUCCESS' );

			// Insert some basic tokens
			$this->insert_basic_tokens();

			return true;
		} else {
			error_log( 'TDWP Template Engine: Fallback table creation FAILED' );
			error_log( 'TDWP Template Engine: dbDelta result: ' . print_r( $result, true ) );
			error_log( 'TDWP Template Engine: Database error: ' . $wpdb->last_error );
			return false;
		}
	}

	/**
	 * Insert basic built-in tokens for fallback functionality
	 *
	 * @since 3.4.0
	 * @return void
	 */
	private function insert_basic_tokens() {
		global $wpdb;

		error_log( 'TDWP Template Engine: Inserting basic tokens' );

		$basic_tokens = array(
			'tournament_name' => array(
				'description' => 'Tournament name',
				'type' => 'tournament',
				'data_source' => 'get_tournament_name',
				'default_format' => '%s'
			),
			'current_blind' => array(
				'description' => 'Current blind level',
				'type' => 'blind',
				'data_source' => 'get_current_blind',
				'default_format' => '%s'
			),
			'time_remaining' => array(
				'description' => 'Time remaining in current level',
				'type' => 'time',
				'data_source' => 'get_time_remaining',
				'default_format' => '%s'
			),
			'players_remaining' => array(
				'description' => 'Number of players remaining',
				'type' => 'player',
				'data_source' => 'get_players_remaining',
				'default_format' => '%d'
			),
			'prize_pool' => array(
				'description' => 'Total prize pool',
				'type' => 'prize',
				'data_source' => 'get_prize_pool',
				'default_format' => '%.2f'
			)
		);

		foreach ( $basic_tokens as $token_name => $token_data ) {
			// Check if token already exists
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT token_id FROM {$wpdb->prefix}tdwp_display_tokens WHERE token_name = %s",
				$token_name
			) );

			if ( ! $existing ) {
				$insert_data = array(
					'token_name' => $token_name,
					'token_description' => $token_data['description'],
					'token_type' => $token_data['type'],
					'data_source' => $token_data['data_source'],
					'default_format' => $token_data['default_format'],
					'is_active' => 1,
				);

				$result = $wpdb->insert(
					$wpdb->prefix . 'tdwp_display_tokens',
					$insert_data,
					array( '%s', '%s', '%s', '%s', '%s', '%d' )
				);

				if ( $result ) {
					error_log( "TDWP Template Engine: Created basic token: $token_name" );
				} else {
					error_log( "TDWP Template Engine: Failed to create basic token: $token_name" );
				}
			}
		}
	}
}