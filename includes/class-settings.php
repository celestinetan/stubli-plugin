<?php
/**
 * The owner's Stubli settings (design: I · Settings).
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * One place to read and change settings, so REST and the rest of the plugin
 * agree on names, types and limits.
 */
class Settings {

	/**
	 * Current settings.
	 *
	 * @return array
	 */
	public static function get() {
		return array(
			'pronouns'      => (string) get_option( 'stubli_pronouns', '' ),
			'on_break'      => (bool) get_option( 'stubli_break', 0 ),
			'search_engines' => (bool) get_option( 'blog_public', 0 ),
			'block_ai'      => (bool) get_option( 'stubli_block_ai', 1 ),
			'digest_time'   => (string) get_option( 'stubli_digest_time', '08:00' ),
			'digest_email'  => (bool) get_option( 'stubli_digest_email', 0 ),
			'who_can_comment' => (string) get_option( 'stubli_who_can_comment', 'friends_of_friends' ),
			'look'          => (bool) get_option( 'stubli_look', 0 ),
			'credit'        => (bool) get_option( 'stubli_credit', 0 ),
		);
	}

	/**
	 * Update some settings. Unknown keys are ignored.
	 *
	 * @param array $changes New values.
	 * @return array Settings after the change.
	 */
	public static function update( array $changes ) {
		if ( array_key_exists( 'pronouns', $changes ) ) {
			update_option( 'stubli_pronouns', mb_substr( sanitize_text_field( (string) $changes['pronouns'] ), 0, 30 ) );
		}
		if ( array_key_exists( 'on_break', $changes ) ) {
			update_option( 'stubli_break', rest_sanitize_boolean( $changes['on_break'] ) ? 1 : 0 );
		}
		if ( array_key_exists( 'search_engines', $changes ) ) {
			update_option( 'blog_public', rest_sanitize_boolean( $changes['search_engines'] ) ? 1 : 0 );
		}
		if ( array_key_exists( 'block_ai', $changes ) ) {
			update_option( 'stubli_block_ai', rest_sanitize_boolean( $changes['block_ai'] ) ? 1 : 0 );
		}
		if ( array_key_exists( 'digest_time', $changes ) && preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $changes['digest_time'] ) ) {
			update_option( 'stubli_digest_time', (string) $changes['digest_time'] );
		}
		if ( array_key_exists( 'digest_email', $changes ) ) {
			update_option( 'stubli_digest_email', rest_sanitize_boolean( $changes['digest_email'] ) ? 1 : 0 );
		}
		if ( array_key_exists( 'who_can_comment', $changes ) && in_array( $changes['who_can_comment'], array( 'friends_of_friends', 'following' ), true ) ) {
			update_option( 'stubli_who_can_comment', $changes['who_can_comment'] );
		}
		if ( array_key_exists( 'credit', $changes ) ) {
			update_option( 'stubli_credit', rest_sanitize_boolean( $changes['credit'] ) ? 1 : 0 );
		}
		if ( array_key_exists( 'look', $changes ) ) {
			update_option( 'stubli_look', rest_sanitize_boolean( $changes['look'] ) ? 1 : 0 );
		}
		return self::get();
	}

	/**
	 * Is the owner taking a break from comments and the guest book?
	 *
	 * @return bool
	 */
	public static function on_break() {
		return (bool) get_option( 'stubli_break', 0 );
	}
}
