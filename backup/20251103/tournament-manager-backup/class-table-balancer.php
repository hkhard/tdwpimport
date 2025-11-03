<?php
/**
 * Tournament Table Balancer
 *
 * Implements ±1 balance algorithm and table breaking logic for Phase 2 Week 2-3
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.1.0
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Table Balancer class
 *
 * @since 3.1.0
 */
class TDWP_Table_Balancer {

	/**
	 * Calculate balance plan for tournament
	 *
	 * Returns array of moves to achieve ±1 balance
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return array Balance plan with moves and status
	 */
	public static function calculate_balance_plan( $tournament_id ) {
		$tables = TDWP_Table_Manager::get_tables( $tournament_id, 'active' );

		if ( count( $tables ) < 2 ) {
			return array(
				'balanced' => true,
				'moves'    => array(),
				'message'  => __( 'Only one table - no balancing needed', 'poker-tournament-import' ),
			);
		}

		// Calculate target size and identify imbalanced tables
		$total_players = 0;
		$table_data    = array();

		foreach ( $tables as $table ) {
			$total_players += intval( $table->player_count );
			$table_data[]   = array(
				'id'           => $table->id,
				'number'       => $table->table_number,
				'player_count' => intval( $table->player_count ),
				'max_seats'    => intval( $table->max_seats ),
				'seats'        => $table->seats,
			);
		}

		$num_tables  = count( $tables );
		$target_size = ceil( $total_players / $num_tables );

		// Identify overloaded and underloaded tables
		$overloaded   = array();
		$underloaded  = array();
		$balanced     = true;

		foreach ( $table_data as $table ) {
			if ( $table['player_count'] > $target_size + 1 ) {
				$overloaded[] = $table;
				$balanced     = false;
			} elseif ( $table['player_count'] < $target_size - 1 ) {
				$underloaded[] = $table;
				$balanced      = false;
			}
		}

		if ( $balanced ) {
			return array(
				'balanced' => true,
				'moves'    => array(),
				'message'  => __( 'Tables are balanced (±1)', 'poker-tournament-import' ),
			);
		}

		// Generate move plan
		$moves = self::generate_move_plan( $overloaded, $underloaded, $table_data );

		return array(
			'balanced'    => false,
			'moves'       => $moves,
			'target_size' => $target_size,
			'message'     => sprintf(
				/* translators: %d: number of moves */
				__( '%d player movements needed to balance tables', 'poker-tournament-import' ),
				count( $moves )
			),
		);
	}

	/**
	 * Generate optimal move plan
	 *
	 * @since 3.1.0
	 * @param array $overloaded Overloaded tables.
	 * @param array $underloaded Underloaded tables.
	 * @param array $all_tables All table data.
	 * @return array Array of move operations
	 */
	private static function generate_move_plan( $overloaded, $underloaded, $all_tables ) {
		$moves = array();

		// Sort tables by deviation from target
		usort( $overloaded, function ( $a, $b ) {
			return $b['player_count'] - $a['player_count'];
		} );

		usort( $underloaded, function ( $a, $b ) {
			return $a['player_count'] - $b['player_count'];
		} );

		// Move players from overloaded to underloaded tables
		foreach ( $overloaded as $source_table ) {
			// Get occupied seats
			$occupied_seats = array_filter(
				$source_table['seats'],
				function ( $seat ) {
					return $seat->player_id !== null;
				}
			);

			// Move players to underloaded tables
			foreach ( $occupied_seats as $seat ) {
				// Find underloaded table with empty seat
				foreach ( $underloaded as &$dest_table ) {
					// Check if destination table has capacity
					if ( $dest_table['player_count'] >= $dest_table['max_seats'] ) {
						continue;
					}

					// Find empty seat in destination table
					$empty_seat = null;
					foreach ( $all_tables as $table ) {
						if ( $table['id'] === $dest_table['id'] ) {
							foreach ( $table['seats'] as $s ) {
								if ( ! $s->player_id ) {
									$empty_seat = $s;
									break 2;
								}
							}
						}
					}

					if ( ! $empty_seat ) {
						continue;
					}

					// Create move
					$moves[] = array(
						'player_id'      => $seat->player_id,
						'player_name'    => $seat->player ? $seat->player->post_title : 'Unknown',
						'from_table_id'  => $source_table['id'],
						'from_table_num' => $source_table['number'],
						'from_seat'      => $seat->seat_number,
						'to_table_id'    => $dest_table['id'],
						'to_table_num'   => $dest_table['number'],
						'to_seat'        => $empty_seat->seat_number,
					);

					// Update virtual counts
					$dest_table['player_count']++;

					// Stop if source table is no longer overloaded
					if ( $source_table['player_count'] - count( $moves ) <= ceil( count( $all_tables ) / count( $all_tables ) ) + 1 ) {
						break 2;
					}

					break;
				}
			}
		}

		return $moves;
	}

	/**
	 * Execute balance plan
	 *
	 * @since 3.1.0
	 * @param int   $tournament_id Tournament post ID.
	 * @param array $moves Array of move operations.
	 * @return array Results with success/failure for each move
	 */
	public static function execute_balance( $tournament_id, $moves ) {
		$results = array(
			'success'   => true,
			'completed' => array(),
			'failed'    => array(),
		);

		foreach ( $moves as $move ) {
			$success = TDWP_Seat_Manager::move_player(
				$move['player_id'],
				$move['to_table_id'],
				$move['to_seat']
			);

			if ( $success ) {
				$results['completed'][] = $move;
			} else {
				$results['failed'][] = $move;
				$results['success']  = false;
			}
		}

		if ( $results['success'] ) {
			do_action( 'tdwp_tables_balanced', $tournament_id, count( $results['completed'] ) );
		}

		return $results;
	}

	/**
	 * Suggest table to break
	 *
	 * Returns smallest table and redistribution plan
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return array|false Table break suggestion or false if cannot break
	 */
	public static function suggest_table_break( $tournament_id ) {
		$tables = TDWP_Table_Manager::get_tables( $tournament_id, 'active' );

		if ( count( $tables ) <= 1 ) {
			return false; // Cannot break last table
		}

		// Find table with fewest players
		$smallest_table = null;
		$min_players    = PHP_INT_MAX;

		foreach ( $tables as $table ) {
			if ( intval( $table->player_count ) < $min_players ) {
				$min_players    = intval( $table->player_count );
				$smallest_table = $table;
			}
		}

		// Check if remaining tables can absorb players
		$remaining_tables = array_filter(
			$tables,
			function ( $t ) use ( $smallest_table ) {
				return $t->id !== $smallest_table->id;
			}
		);

		$total_capacity = 0;
		foreach ( $remaining_tables as $table ) {
			$available       = intval( $table->max_seats ) - intval( $table->player_count );
			$total_capacity += $available;
		}

		if ( $total_capacity < $min_players ) {
			return array(
				'can_break' => false,
				'message'   => __( 'Remaining tables cannot absorb all players', 'poker-tournament-import' ),
			);
		}

		// Generate redistribution plan
		$moves       = array();
		$occupied    = array_filter(
			$smallest_table->seats,
			function ( $seat ) {
				return $seat->player_id !== null;
			}
		);

		foreach ( $occupied as $seat ) {
			// Find destination table
			$dest_table = null;
			$dest_seat  = null;

			foreach ( $remaining_tables as $table ) {
				if ( intval( $table->player_count ) >= intval( $table->max_seats ) ) {
					continue; // Table full
				}

				// Find empty seat
				foreach ( $table->seats as $s ) {
					if ( ! $s->player_id ) {
						$dest_table = $table;
						$dest_seat  = $s;
						break 2;
					}
				}
			}

			if ( $dest_table && $dest_seat ) {
				$moves[] = array(
					'player_id'      => $seat->player_id,
					'player_name'    => $seat->player ? $seat->player->post_title : 'Unknown',
					'from_table_id'  => $smallest_table->id,
					'from_table_num' => $smallest_table->table_number,
					'from_seat'      => $seat->seat_number,
					'to_table_id'    => $dest_table->id,
					'to_table_num'   => $dest_table->table_number,
					'to_seat'        => $dest_seat->seat_number,
				);
			}
		}

		return array(
			'can_break'   => true,
			'table_id'    => $smallest_table->id,
			'table_number' => $smallest_table->table_number,
			'player_count' => $min_players,
			'moves'       => $moves,
			'message'     => sprintf(
				/* translators: 1: table number, 2: player count */
				__( 'Table %1$d can be broken (%2$d players to move)', 'poker-tournament-import' ),
				$smallest_table->table_number,
				$min_players
			),
		);
	}

	/**
	 * Execute table break
	 *
	 * @since 3.1.0
	 * @param int   $tournament_id Tournament post ID.
	 * @param int   $table_id Table to break.
	 * @param array $moves Move operations.
	 * @return bool True on success
	 */
	public static function execute_table_break( $tournament_id, $table_id, $moves ) {
		// Mark table as breaking
		TDWP_Table_Manager::update_status( $table_id, 'breaking' );

		// Execute moves
		$all_success = true;
		foreach ( $moves as $move ) {
			$success = TDWP_Seat_Manager::move_player(
				$move['player_id'],
				$move['to_table_id'],
				$move['to_seat']
			);

			if ( ! $success ) {
				$all_success = false;
			}
		}

		if ( $all_success ) {
			// Mark table as broken
			TDWP_Table_Manager::update_status( $table_id, 'broken' );
			do_action( 'tdwp_table_broken', $tournament_id, $table_id );
		}

		return $all_success;
	}

	/**
	 * Check if final table consolidation is possible
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return array Status with can_consolidate flag and details
	 */
	public static function check_final_table( $tournament_id ) {
		$tables        = TDWP_Table_Manager::get_tables( $tournament_id, 'active' );
		$total_players = TDWP_Table_Manager::get_seated_player_count( $tournament_id );

		if ( count( $tables ) <= 1 ) {
			return array(
				'can_consolidate' => false,
				'message'         => __( 'Already at final table', 'poker-tournament-import' ),
			);
		}

		// Check if players fit on largest single table
		$max_capacity = 0;
		foreach ( $tables as $table ) {
			$max_capacity = max( $max_capacity, intval( $table->max_seats ) );
		}

		if ( $total_players <= $max_capacity ) {
			return array(
				'can_consolidate' => true,
				'message'         => sprintf(
					/* translators: %d: player count */
					__( '%d players can fit on final table', 'poker-tournament-import' ),
					$total_players
				),
			);
		}

		return array(
			'can_consolidate' => false,
			'message'         => sprintf(
				/* translators: 1: player count, 2: max capacity */
				__( '%1$d players exceed largest table capacity (%2$d)', 'poker-tournament-import' ),
				$total_players,
				$max_capacity
			),
		);
	}

	/**
	 * Get balance status for tournament
	 *
	 * @since 3.1.0
	 * @param int $tournament_id Tournament post ID.
	 * @return string Balance status: 'balanced', 'unbalanced', or 'single_table'
	 */
	public static function get_balance_status( $tournament_id ) {
		$plan = self::calculate_balance_plan( $tournament_id );

		if ( count( TDWP_Table_Manager::get_tables( $tournament_id, 'active' ) ) <= 1 ) {
			return 'single_table';
		}

		return $plan['balanced'] ? 'balanced' : 'unbalanced';
	}

	/**
	 * Trigger automatic table rebalancing
	 *
	 * Automatically calculates and executes balance plan if needed.
	 * Used after player eliminations to maintain optimal table distribution.
	 *
	 * @since 3.2.0
	 * @param int $tournament_id Tournament post ID.
	 * @return array|WP_Error Rebalancing results or error
	 */
	public static function trigger_rebalance( $tournament_id ) {
		// Validate tournament ID
		if ( ! $tournament_id ) {
			return new WP_Error(
				'invalid_tournament',
				__( 'Invalid tournament ID', 'poker-tournament-import' )
			);
		}

		try {
			// Calculate current balance plan
			$plan = self::calculate_balance_plan( $tournament_id );

			// Check if rebalancing is needed
			$active_tables = TDWP_Table_Manager::get_tables( $tournament_id, 'active' );

			if ( count( $active_tables ) <= 1 ) {
				return array(
					'rebalanced' => false,
					'message'   => __( 'Single table - no rebalancing needed', 'poker-tournament-import' ),
					'tables'    => count( $active_tables ),
				);
			}

			if ( $plan['balanced'] ) {
				return array(
					'rebalanced' => false,
					'message'   => __( 'Tables already balanced', 'poker-tournament-import' ),
					'balance'   => $plan['average'],
				);
			}

			// Execute automatic rebalancing if moves are available
			if ( ! empty( $plan['moves'] ) ) {
				$results = self::execute_balance( $tournament_id, $plan['moves'] );

				if ( is_wp_error( $results ) ) {
					return $results;
				}

				return array(
					'rebalanced' => true,
					'message'   => sprintf(
						/* translators: %d: number of moves executed */
						_n(
							'Executed %d rebalancing move',
							'Executed %d rebalancing moves',
							count( $results['moves'] ),
							'poker-tournament-import'
						),
						count( $results['moves'] )
					),
					'moves'     => $results['moves'],
					'tables'    => count( $active_tables ),
				);
			}

			// No moves available despite being unbalanced
			return array(
				'rebalanced' => false,
				'message'   => __( 'No rebalancing moves available', 'poker-tournament-import' ),
				'reason'    => __( 'Cannot balance with current player distribution', 'poker-tournament-import' ),
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'rebalance_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Table rebalancing failed: %s', 'poker-tournament-import' ),
					$e->getMessage()
				)
			);
		}
	}
}
