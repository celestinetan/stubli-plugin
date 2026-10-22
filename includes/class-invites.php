<?php
/**
 * Invite links that follow both ways (Q24).
 *
 * 1. The owner creates a link: /invite/?t=TOKEN (single use, 30 days).
 * 2. The friend opens it and enters their own Stubli address; they land on
 *    their own dashboard (Act, do=accept) and confirm.
 * 3. The friend's site follows the inviter, keeps a short-lived proof, and
 *    calls the inviter's /invites/accept with the token and its address.
 * 4. The inviter's site checks the token, asks the friend's site for the
 *    proof (so nobody can claim to be someone else's site), and follows back.
 *
 * Only a hash of each token is stored.
 *
 * @package Stubli
 */

namespace Stubli;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Create, check and accept invites.
 */
class Invites {

	const OPTION   = 'stubli_invites';
	const LIFETIME = 30 * DAY_IN_SECONDS;

	/**
	 * @return array
	 */
	private static function all() {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) ? $all : array();
	}

	/**
	 * @param string $token Token.
	 * @return string
	 */
	private static function key( $token ) {
		return hash( 'sha256', (string) $token );
	}

	/**
	 * Make a new invite.
	 *
	 * @return array { url, expires }
	 */
	public static function create() {
		$token = wp_generate_password( 24, false, false );
		$all   = self::all();
		// Forget invites that ended more than 30 days ago.
		foreach ( $all as $k => $inv ) {
			if ( (int) $inv['expires'] < time() - self::LIFETIME ) {
				unset( $all[ $k ] );
			}
		}
		$all[ self::key( $token ) ] = array(
			'created' => time(),
			'expires' => time() + self::LIFETIME,
			'used_by' => '',
		);
		update_option( self::OPTION, $all, false );
		return array(
			'url'     => add_query_arg( 't', $token, home_url( '/invite/' ) ),
			'expires' => gmdate( 'c', time() + self::LIFETIME ),
		);
	}

	/**
	 * @param string $token Token.
	 * @return string ok | used | expired | unknown
	 */
	public static function check( $token ) {
		if ( '' === (string) $token ) {
			return 'unknown';
		}
		$all = self::all();
		$inv = $all[ self::key( $token ) ] ?? null;
		if ( ! $inv ) {
			return 'unknown';
		}
		if ( '' !== $inv['used_by'] ) {
			return 'used';
		}
		return (int) $inv['expires'] < time() ? 'expired' : 'ok';
	}

	/**
	 * @param string $token Token.
	 * @return int Timestamp.
	 */
	public static function expires( $token ) {
		$inv = self::all()[ self::key( $token ) ] ?? null;
		return $inv ? (int) $inv['expires'] : time();
	}

	/**
	 * On the friend's site: follow the inviter, then ask them to follow back.
	 *
	 * @param string $inviter Inviter's site URL.
	 * @param string $token   Token.
	 * @return array|WP_Error { following: bool, followed_back: bool, name }
	 */
	public static function accept_here( $inviter, $token ) {
		$follow = Follows::add( $inviter );
		if ( is_wp_error( $follow ) ) {
			return $follow;
		}
		set_transient( 'stubli_invite_proof_' . self::key( $token ), Follows::origin( $follow['site_url'] ), 10 * MINUTE_IN_SECONDS );
		$res      = Rest::remote(
			$follow['site_url'],
			'/invites/accept',
			array(
				'method'  => 'POST',
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'token' => $token,
						'site'  => home_url( '/' ),
					)
				),
			)
		);
		delete_transient( 'stubli_invite_proof_' . self::key( $token ) );
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return array(
			'following'     => true,
			'followed_back' => 200 === $code && ! empty( $body['followed'] ),
			'reason'        => is_array( $body ) && isset( $body['message'] ) ? (string) $body['message'] : '',
			'name'          => $follow['name'],
		);
	}

	/**
	 * On the inviter's site: check the token and the proof, then follow back.
	 *
	 * @param string $token Token.
	 * @param string $site  The friend's site.
	 * @return array|WP_Error
	 */
	public static function accept_remote( $token, $site ) {
		$state = self::check( $token );
		if ( 'ok' !== $state ) {
			return new WP_Error( 'stubli_invite_' . $state, 'used' === $state ? __( 'This invite has already been used.', 'stubli' ) : __( 'This invite isn\'t valid any more.', 'stubli' ), array( 'status' => 400 ) );
		}
		$site = esc_url_raw( (string) $site );
		if ( ! $site || ! in_array( wp_parse_url( $site, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'stubli_bad_url', __( 'That doesn\'t look like a site address.', 'stubli' ), array( 'status' => 400 ) );
		}
		// Ask the friend's site whether it really is accepting this invite.
		$proof = Rest::remote( $site, '/invites/proof', array( 'timeout' => 10 ), array( 'token' => $token ) );
		$body  = json_decode( (string) wp_remote_retrieve_body( $proof ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $proof ) || empty( $body['inviter'] ) || Follows::origin( $body['inviter'] ) !== Follows::origin( home_url() ) ) {
			return new WP_Error( 'stubli_invite_unproven', __( 'Your site couldn\'t confirm the invite. Try the link again.', 'stubli' ), array( 'status' => 403 ) );
		}
		$follow = Follows::add( $site );
		if ( is_wp_error( $follow ) ) {
			return $follow;
		}
		$all                                     = self::all();
		$all[ self::key( $token ) ]['used_by']   = Follows::origin( $site );
		$all[ self::key( $token ) ]['used_at']   = time();
		update_option( self::OPTION, $all, false );
		return array(
			'followed' => true,
			'name'     => get_bloginfo( 'name' ),
		);
	}

	/**
	 * On the friend's site: answer the inviter's proof question.
	 *
	 * @param string $token Token.
	 * @return array|WP_Error
	 */
	public static function proof( $token ) {
		$inviter = get_transient( 'stubli_invite_proof_' . self::key( $token ) );
		if ( ! $inviter ) {
			return new WP_Error( 'stubli_no_proof', 'Not accepting this invite.', array( 'status' => 404 ) );
		}
		return array( 'inviter' => $inviter );
	}
}
