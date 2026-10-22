<?php
/**
 * Discover: friends of friends (Q15). The sites that the people you follow
 * list publicly, with who introduced them, their bio and newest photos.
 * No ranking: sites with the newest photos come first.
 *
 * @package Stubli
 */

namespace Stubli;

use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Build the Discover list.
 */
class Discover {

	const MAX = 15;

	/**
	 * Register the route.
	 */
	public static function init() {
		add_action(
			'rest_api_init',
			static function () {
				Rest::register(
			'/discover',
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => static fn() => rest_ensure_response( self::suggestions() ),
						'permission_callback' => static fn() => current_user_can( 'manage_options' ),
					)
				);
			}
		);
	}

	/**
	 * Suggestions for this site's owner.
	 *
	 * @return array
	 */
	public static function suggestions() {
		$mine    = array( Follows::origin( home_url() ) => true );
		$via     = array();
		$follows = Follows::all();
		foreach ( $follows as $f ) {
			$mine[ Follows::origin( $f['site_url'] ) ] = true;
		}
		foreach ( $follows as $f ) {
			$theirs = json_decode( (string) $f['their_follows'], true );
			foreach ( is_array( $theirs ) ? $theirs : array() as $o ) {
				if ( isset( $mine[ $o ] ) || Moderation::is_blocked( $o ) ) {
					continue;
				}
				$first          = strtok( (string) $f['name'], ' ' );
				$via[ $o ][]    = '' !== (string) $first ? $first : (string) $f['name'];
			}
		}
		$origins = array_slice( array_keys( $via ), 0, self::MAX * 2 );
		$profiles = self::profiles( $origins );
		$out      = array();
		foreach ( $origins as $o ) {
			$p = $profiles[ $o ] ?? null;
			if ( ! $p ) {
				continue;
			}
			$out[] = array(
				'url'    => $p['url'],
				'name'   => $p['name'],
				'avatar' => $p['avatar'],
				'bio'    => $p['bio'],
				'recent' => $p['recent'],
				'latest' => $p['recent'] ? $p['recent'][0]['date'] : '',
				'via'    => array_values( array_unique( $via[ $o ] ) ),
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( (string) $b['latest'], (string) $a['latest'] );
			}
		);
		return array_slice( $out, 0, self::MAX );
	}

	/**
	 * Fetch Stubli profiles, in parallel, cached for 12 hours.
	 *
	 * @param string[] $origins Site origins.
	 * @return array origin => profile|null
	 */
	private static function profiles( array $origins ) {
		$out  = array();
		$todo = array();
		foreach ( $origins as $o ) {
			$cached = get_transient( 'stubli_disc_' . md5( $o ) );
			if ( false !== $cached ) {
				$out[ $o ] = $cached ? $cached : null;
			} else {
				$todo[ $o ] = add_query_arg( 'rest_route', '/' . LEGACY_NS . '/profile', trailingslashit( $o ) );
			}
		}
		$responses = array();
		if ( $todo && class_exists( '\WpOrg\Requests\Requests' ) ) {
			$reqs = array();
			foreach ( $todo as $o => $url ) {
				if ( wp_http_validate_url( $url ) || apply_filters( 'http_request_host_is_external', false, (string) wp_parse_url( $url, PHP_URL_HOST ), $url ) ) {
					$reqs[ $o ] = array(
						'url'     => $url,
						'type'    => 'GET',
						'options' => array( 'timeout' => 8 ),
					);
				}
			}
			try {
				$responses = $reqs ? \WpOrg\Requests\Requests::request_multiple( $reqs ) : array();
			} catch ( \Throwable $e ) {
				$responses = array();
			}
		}
		foreach ( $todo as $o => $url ) {
			$r    = $responses[ $o ] ?? null;
			$body = ( $r instanceof \WpOrg\Requests\Response && 200 === (int) $r->status_code ) ? json_decode( $r->body, true ) : null;
			$p    = self::clean( $o, $body );
			set_transient( 'stubli_disc_' . md5( $o ), $p ? $p : 0, $p ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
			$out[ $o ] = $p;
		}
		return $out;
	}

	/**
	 * Keep only what Discover shows, and only from real Stubli sites.
	 *
	 * @param string     $origin Expected origin.
	 * @param array|null $p      Raw profile.
	 * @return array|null
	 */
	private static function clean( $origin, $p ) {
		if ( ! is_array( $p ) || 0 !== strpos( (string) ( $p['generator'] ?? '' ), 'stubli/' ) || Follows::origin( (string) ( $p['url'] ?? '' ) ) !== $origin ) {
			return null;
		}
		$recent = array();
		foreach ( array_slice( is_array( $p['recent'] ?? null ) ? $p['recent'] : array(), 0, 3 ) as $r ) {
			$url  = esc_url_raw( (string) ( $r['url'] ?? '' ) );
			$link = esc_url_raw( (string) ( $r['link'] ?? '' ) );
			if ( $url && $link && Follows::origin( $link ) === $origin ) {
				$recent[] = array(
					'url'  => $url,
					'alt'  => sanitize_text_field( (string) ( $r['alt'] ?? '' ) ),
					'link' => $link,
					'note' => sanitize_text_field( (string) ( $r['note'] ?? '' ) ),
					'date' => sanitize_text_field( (string) ( $r['date'] ?? '' ) ),
				);
			}
		}
		return array(
			'url'    => esc_url_raw( (string) $p['url'] ),
			'name'   => sanitize_text_field( (string) ( $p['name'] ?? '' ) ),
			'avatar' => esc_url_raw( (string) ( $p['avatar'] ?? '' ) ),
			'bio'    => mb_substr( sanitize_text_field( (string) ( $p['bio'] ?? ( $p['description'] ?? '' ) ) ), 0, 200 ),
			'recent' => $recent,
		);
	}
}
