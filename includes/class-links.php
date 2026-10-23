<?php
/**
 * Your links (the Links tab): a short list of names and addresses,
 * editable from the app as well as Settings → Stubli.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Stored as "Name | https://…" lines in stubli_links, as before.
 */
class Links {

	const MAX = 20;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Your links.
	 *
	 * @return array[] { label, url }
	 */
	public static function all() {
		$out = array();
		foreach ( preg_split( '/\r?\n/', (string) get_option( 'stubli_links', '' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 === count( $parts ) && '' !== $parts[0] && in_array( wp_parse_url( $parts[1], PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
				$out[] = array(
					'label' => $parts[0],
					'url'   => $parts[1],
				);
			}
		}
		return $out;
	}

	/**
	 * Save the list, in this order. Addresses without http get https://.
	 *
	 * @param array $links Raw { label, url } list.
	 * @return array|\WP_Error
	 */
	public static function save( array $links ) {
		$lines = array();
		foreach ( array_slice( $links, 0, self::MAX ) as $l ) {
			if ( ! is_array( $l ) ) {
				continue;
			}
			$label = trim( str_replace( '|', '/', sanitize_text_field( (string) ( $l['label'] ?? '' ) ) ) );
			$url   = trim( (string) ( $l['url'] ?? '' ) );
			if ( '' === $label && '' === $url ) {
				continue;
			}
			if ( '' !== $url && ! preg_match( '#^https?://#i', $url ) ) {
				$url = 'https://' . $url;
			}
			$url = esc_url_raw( $url, array( 'http', 'https' ) );
			if ( '' === $label || '' === $url || ! wp_parse_url( $url, PHP_URL_HOST ) ) {
				return new \WP_Error( 'stubli_link', __( 'Each link needs a name and a web address, like Shop and shop.example.com.', 'stubli' ), array( 'status' => 400 ) );
			}
			$lines[] = mb_substr( $label, 0, 60 ) . ' | ' . $url;
		}
		update_option( 'stubli_links', implode( "\n", $lines ) );
		return self::all();
	}

	/**
	 * REST routes, owner only.
	 */
	public static function routes() {
		Rest::register(
			'/links',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn() => rest_ensure_response( self::all() ),
					'permission_callback' => array( Rest::class, 'is_owner' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$res = self::save( is_array( $r['links'] ) ? $r['links'] : array() );
						return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
					},
					'permission_callback' => array( Rest::class, 'is_owner' ),
				),
			)
		);
	}
}
