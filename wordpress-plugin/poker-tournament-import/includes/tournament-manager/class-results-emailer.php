<?php
/**
 * Tournament Results Emailer (tdwp-871.29)
 *
 * Sends final results to players (all / in-the-money) or to explicit
 * addresses. Sending is always operator-triggered (no automatic sends); the
 * subject/body/recipient logic is pure so it can be unit-tested.
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and sends tournament-results emails.
 */
class TDWP_Results_Emailer {

	/**
	 * Build the email subject for a tournament (pure).
	 *
	 * @param string $tournament_name Tournament name.
	 * @return string
	 */
	public static function build_subject( $tournament_name ) {
		// sanitize_text_field strips tags and CR/LF, blocking header injection.
		$tournament_name = sanitize_text_field( (string) $tournament_name );
		if ( '' === $tournament_name ) {
			$tournament_name = __( 'Tournament', 'poker-tournament-import' );
		}
		/* translators: %s: tournament name */
		return sprintf( __( 'Results: %s', 'poker-tournament-import' ), $tournament_name );
	}

	/**
	 * Keep only in-the-money result rows (prize > 0). Pure.
	 *
	 * @param array $rows Result rows with a `prize` key.
	 * @return array Filtered rows.
	 */
	public static function filter_itm( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( isset( $row['prize'] ) && (float) $row['prize'] > 0 ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Build the plain-text results body (pure).
	 *
	 * @param string $tournament_name Tournament name.
	 * @param array  $rows            Rows with position, name, prize.
	 * @return string
	 */
	public static function build_body( $tournament_name, $rows ) {
		$lines   = array();
		$lines[] = sprintf(
			/* translators: %s: tournament name */
			__( 'Final results for %s', 'poker-tournament-import' ),
			(string) $tournament_name
		);
		$lines[] = '';

		// Order by finish position ascending (1st first); rows without a
		// position sort last.
		$sorted = (array) $rows;
		usort(
			$sorted,
			static function ( $a, $b ) {
				$pa = isset( $a['position'] ) && $a['position'] ? (int) $a['position'] : PHP_INT_MAX;
				$pb = isset( $b['position'] ) && $b['position'] ? (int) $b['position'] : PHP_INT_MAX;
				return $pa <=> $pb;
			}
		);

		foreach ( $sorted as $row ) {
			$position = isset( $row['position'] ) && $row['position'] ? (int) $row['position'] : '-';
			$name     = isset( $row['name'] ) ? (string) $row['name'] : '';
			$prize    = isset( $row['prize'] ) ? (float) $row['prize'] : 0.0;
			if ( $prize > 0 ) {
				$lines[] = sprintf( '%s. %s — %s', $position, $name, number_format( $prize, 2 ) );
			} else {
				$lines[] = sprintf( '%s. %s', $position, $name );
			}
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Parse and validate a free-text list of email addresses (pure).
	 *
	 * @param string $raw Comma/space/newline-separated addresses.
	 * @return string[] Valid, de-duplicated addresses.
	 */
	public static function parse_address_list( $raw ) {
		$parts = preg_split( '/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
		$valid = array();
		foreach ( (array) $parts as $part ) {
			$email = sanitize_email( $part );
			if ( $email && is_email( $email ) ) {
				$key = strtolower( $email );
				if ( ! isset( $valid[ $key ] ) ) {
					$valid[ $key ] = $email;
				}
			}
		}
		return array_values( $valid );
	}

	/**
	 * Gather a tournament's result rows from the data mart.
	 *
	 * @param int $tournament_id Tournament post ID.
	 * @return array Rows with position, name, prize, email.
	 */
	public static function get_results( $tournament_id ) {
		global $wpdb;

		$uuid = get_post_meta( $tournament_id, 'tournament_uuid', true );
		if ( empty( $uuid ) ) {
			return array();
		}

		$table = $wpdb->prefix . 'poker_tournament_players';
		$db    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT player_id, finish_position, winnings FROM {$table} WHERE tournament_id = %s ORDER BY finish_position ASC",
				$uuid
			),
			ARRAY_A
		);

		$rows = array();
		foreach ( (array) $db as $row ) {
			$player = get_posts(
				array(
					'post_type'      => 'player',
					'posts_per_page' => 1,
					'meta_query'     => array(
						array(
							'key'     => 'player_uuid',
							'value'   => $row['player_id'],
							'compare' => '=',
						),
					),
				)
			);
			$name  = ( ! empty( $player ) && ! empty( $player[0]->post_title ) ) ? $player[0]->post_title : $row['player_id'];
			$email = ! empty( $player ) ? get_post_meta( $player[0]->ID, 'player_email', true ) : '';

			$rows[] = array(
				'position' => (int) $row['finish_position'],
				'name'     => $name,
				'prize'    => (float) $row['winnings'],
				'email'    => $email ? sanitize_email( $email ) : '',
			);
		}

		return $rows;
	}

	/**
	 * Resolve recipient email addresses for a send.
	 *
	 * @param array  $rows     Result rows (with email).
	 * @param string $mode     'all' | 'itm' | 'specific'.
	 * @param string $explicit Explicit address list (for 'specific').
	 * @return string[] Addresses to send to.
	 */
	public static function resolve_recipients( $rows, $mode, $explicit = '' ) {
		if ( 'specific' === $mode ) {
			return self::parse_address_list( $explicit );
		}

		$source = ( 'itm' === $mode ) ? self::filter_itm( $rows ) : (array) $rows;
		$emails = array();
		foreach ( $source as $row ) {
			if ( ! empty( $row['email'] ) && is_email( $row['email'] ) ) {
				$key = strtolower( $row['email'] );
				if ( ! isset( $emails[ $key ] ) ) {
					$emails[ $key ] = $row['email'];
				}
			}
		}
		return array_values( $emails );
	}

	/**
	 * Send the results email (operator-triggered).
	 *
	 * @param int    $tournament_id Tournament post ID.
	 * @param string $mode          'all' | 'itm' | 'specific'.
	 * @param string $explicit      Explicit address list (for 'specific').
	 * @return array|WP_Error Summary (sent count) or error.
	 */
	public static function send( $tournament_id, $mode, $explicit = '' ) {
		$tournament_id = absint( $tournament_id );
		$mode          = in_array( $mode, array( 'all', 'itm', 'specific' ), true ) ? $mode : 'all';

		$post = get_post( $tournament_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_tournament', __( 'Tournament not found', 'poker-tournament-import' ) );
		}

		$rows       = self::get_results( $tournament_id );
		$recipients = self::resolve_recipients( $rows, $mode, $explicit );
		if ( empty( $recipients ) ) {
			return new WP_Error( 'no_recipients', __( 'No valid recipient addresses', 'poker-tournament-import' ) );
		}

		$subject = self::build_subject( $post->post_title );
		$body    = self::build_body( $post->post_title, $rows );

		$sent = 0;
		foreach ( $recipients as $recipient ) {
			if ( wp_mail( $recipient, $subject, $body ) ) {
				++$sent;
			}
		}

		return array(
			'success'    => true,
			'sent'       => $sent,
			'recipients' => count( $recipients ),
			'message'    => sprintf(
				/* translators: 1: emails sent, 2: total recipients */
				__( 'Sent %1$d of %2$d results emails', 'poker-tournament-import' ),
				$sent,
				count( $recipients )
			),
		);
	}
}
