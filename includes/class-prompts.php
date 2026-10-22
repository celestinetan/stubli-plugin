<?php
/**
 * Prompt cards: up to three short answers that sit behind the profile card
 * ("A perfect Sunday…"). Inspired by Hinge's prompts; no matching.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Prompts.
 */
class Prompts {

	const OPTION     = 'stubli_prompts';
	const MAX        = 3;
	const MAX_Q      = 80;
	const MAX_ANSWER = 150;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Prompts to choose from.
	 *
	 * @return string[]
	 */
	public static function choices() {
		return array(
			__( 'A perfect Sunday…', 'stubli' ),
			__( 'Ask me about…', 'stubli' ),
			__( 'Currently obsessed with…', 'stubli' ),
			__( 'The best café in my city…', 'stubli' ),
			__( 'I\'m known for…', 'stubli' ),
			__( 'A photo I\'m proud of…', 'stubli' ),
			__( 'Something I\'m learning…', 'stubli' ),
			__( 'My favourite walk…', 'stubli' ),
			__( 'The last thing that made me laugh…', 'stubli' ),
			__( 'Teach me something about…', 'stubli' ),
		);
	}

	/**
	 * The owner's answered prompts, in order.
	 *
	 * @return array List of { q, a }.
	 */
	public static function get() {
		$out = array();
		foreach ( (array) get_option( self::OPTION, array() ) as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$q = trim( (string) ( $p['q'] ?? '' ) );
			$a = trim( (string) ( $p['a'] ?? '' ) );
			if ( '' !== $q && '' !== $a ) {
				$out[] = array(
					'q' => $q,
					'a' => $a,
				);
			}
		}
		return array_slice( $out, 0, self::MAX );
	}

	/**
	 * One prompt by position.
	 *
	 * @param int $i Position (0-based).
	 * @return array|null
	 */
	public static function at( $i ) {
		$all = self::get();
		return $all[ (int) $i ] ?? null;
	}

	/**
	 * Save the owner's prompts.
	 *
	 * @param mixed $list List of { q, a }.
	 * @return array|\WP_Error Saved list.
	 */
	public static function save( $list ) {
		if ( ! is_array( $list ) ) {
			return new \WP_Error( 'stubli_bad_prompts', __( 'Something about those cards didn\'t look right. Try again.', 'stubli' ), array( 'status' => 400 ) );
		}
		$clean = array();
		foreach ( array_slice( $list, 0, self::MAX ) as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$q = mb_substr( trim( sanitize_text_field( (string) ( $p['q'] ?? '' ) ) ), 0, self::MAX_Q );
			$a = trim( sanitize_textarea_field( (string) ( $p['a'] ?? '' ) ) );
			$a = trim( preg_replace( '/\s+/', ' ', $a ) );
			if ( mb_strlen( $a ) > self::MAX_ANSWER ) {
				/* translators: %d: characters */
				return new \WP_Error( 'stubli_prompt_long', sprintf( __( 'Keep each answer under %d characters, so it fits on its card.', 'stubli' ), self::MAX_ANSWER ), array( 'status' => 400 ) );
			}
			if ( '' === $q || '' === $a ) {
				continue;
			}
			$clean[] = array(
				'q' => $q,
				'a' => $a,
			);
		}
		update_option( self::OPTION, $clean, false );
		return $clean;
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		Rest::register(
			'/prompts',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static function () {
						return rest_ensure_response(
							array(
								'prompts'    => self::get(),
								'choices'    => self::choices(),
								'max'        => self::MAX,
								'max_answer' => self::MAX_ANSWER,
							)
						);
					},
					'permission_callback' => array( Rest::class, 'is_owner' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$res = self::save( $r['prompts'] );
						return is_wp_error( $res ) ? $res : rest_ensure_response( array( 'prompts' => $res ) );
					},
					'permission_callback' => array( Rest::class, 'is_owner' ),
				),
			)
		);
	}
}
