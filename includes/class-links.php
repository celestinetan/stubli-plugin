<?php
/**
 * Your favourites (the Favourites tab, once called Links): names with an
 * optional link and note, in optional groups, edited in the app.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Favourites (since 0.45.0): up to 100, each a name with an optional link,
 * an optional one-line note and an optional group; up to 12 groups, shown
 * as headed sections on the Favourites tab. Stored in stubli_favourites as
 * { items: [ { label, url, note, group } ], groups: [ names ] }. The old
 * "Name | https://…" lines in stubli_links are read once if there's nothing
 * new yet, and kept up to date for the favourites that have a link, so an
 * older look or a move to an older Stubli still shows them.
 */
class Links {

	const MAX        = 100;
	const MAX_GROUPS = 12;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * The old links, from stubli_links lines.
	 *
	 * @return array[]
	 */
	private static function legacy() {
		$out = array();
		foreach ( preg_split( '/\r?\n/', (string) get_option( 'stubli_links', '' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 === count( $parts ) && '' !== $parts[0] && in_array( wp_parse_url( $parts[1], PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
				$out[] = array(
					'label' => $parts[0],
					'url'   => $parts[1],
					'note'  => '',
					'group' => '',
				);
			}
		}
		return $out;
	}

	/**
	 * Your favourites and groups, in order.
	 *
	 * @return array { items: array[], groups: string[] }
	 */
	public static function data() {
		$d = get_option( 'stubli_favourites', null );
		if ( ! is_array( $d ) || ! isset( $d['items'] ) ) {
			return array(
				'items'  => self::legacy(),
				'groups' => array(),
			);
		}
		$groups = array_values( array_filter( array_map( 'strval', (array) ( $d['groups'] ?? array() ) ) ) );
		$items  = array();
		foreach ( (array) $d['items'] as $i ) {
			if ( ! is_array( $i ) || '' === (string) ( $i['label'] ?? '' ) ) {
				continue;
			}
			$g       = (string) ( $i['group'] ?? '' );
			$items[] = array(
				'label' => (string) $i['label'],
				'url'   => (string) ( $i['url'] ?? '' ),
				'note'  => (string) ( $i['note'] ?? '' ),
				'group' => in_array( $g, $groups, true ) ? $g : '',
			);
		}
		return array(
			'items'  => $items,
			'groups' => $groups,
		);
	}

	/**
	 * Favourites as sections for the page: ungrouped ones first, then each
	 * group in its order. Empty groups are left out.
	 *
	 * @return array[] { group, items }
	 */
	public static function sections() {
		$d   = self::data();
		$out = array(
			array(
				'group' => '',
				'items' => array(),
			),
		);
		foreach ( $d['groups'] as $g ) {
			$out[] = array(
				'group' => $g,
				'items' => array(),
			);
		}
		$at = array_flip( array_merge( array( '' ), $d['groups'] ) );
		foreach ( $d['items'] as $i ) {
			$out[ $at[ $i['group'] ] ]['items'][] = $i;
		}
		return array_values( array_filter( $out, static fn( $s ) => ! empty( $s['items'] ) ) );
	}

	/**
	 * Whether a link opens a map (a pin instead of an arrow on the page).
	 *
	 * @param string $url Address.
	 * @return bool
	 */
	public static function is_map( $url ) {
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		if ( '' === $host ) {
			return false;
		}
		if ( in_array( $host, array( 'maps.app.goo.gl', 'maps.google.com', 'maps.apple.com', 'maps.apple', 'www.openstreetmap.org', 'openstreetmap.org', 'osm.org', 'map.search.ch' ), true ) ) {
			return true;
		}
		return ( preg_match( '#(^|\.)google\.[a-z.]+$#', $host ) && 0 === strpos( $path, '/maps' ) ) || ( 'goo.gl' === $host && 0 === strpos( $path, '/maps' ) );
	}

	/**
	 * Old callers: the favourites as a flat list.
	 *
	 * @return array[] { label, url, note, group }
	 */
	public static function all() {
		return self::data()['items'];
	}

	/**
	 * Save favourites and groups, in this order. Addresses without http get
	 * https://. Groups nobody uses are dropped.
	 *
	 * @param array $items  Raw { label, url, note, group } list.
	 * @param array $groups Group names, in order.
	 * @return array|\WP_Error The saved data.
	 */
	public static function save( array $items, array $groups = array() ) {
		$names = array();
		foreach ( $groups as $g ) {
			$g = trim( mb_substr( sanitize_text_field( (string) $g ), 0, 30 ) );
			if ( '' !== $g && ! in_array( $g, $names, true ) ) {
				$names[] = $g;
			}
		}
		$names = array_slice( $names, 0, self::MAX_GROUPS );
		$out   = array();
		foreach ( $items as $l ) {
			if ( ! is_array( $l ) ) {
				continue;
			}
			$label = trim( sanitize_text_field( (string) ( $l['label'] ?? '' ) ) );
			$url   = trim( (string) ( $l['url'] ?? '' ) );
			$note  = trim( sanitize_text_field( (string) ( $l['note'] ?? '' ) ) );
			$group = trim( sanitize_text_field( (string) ( $l['group'] ?? '' ) ) );
			if ( '' === $label && '' === $url && '' === $note ) {
				continue;
			}
			if ( '' === $label ) {
				return new \WP_Error( 'stubli_link', __( 'Each favourite needs a name, like Café Henrici.', 'stubli' ), array( 'status' => 400 ) );
			}
			if ( '' !== $url ) {
				if ( preg_match( '/\s/u', $url ) ) {
					$url = '';
				} elseif ( ! preg_match( '#^https?://#i', $url ) ) {
					$url = 'https://' . $url;
				}
				$url  = '' === $url ? '' : esc_url_raw( $url, array( 'http', 'https' ) );
				$host = (string) wp_parse_url( $url, PHP_URL_HOST );
				if ( '' === $url || ! preg_match( '/^[^%.\s]+(\.[^%.\s]+)+$/u', $host ) ) {
					return new \WP_Error( 'stubli_link', __( 'That link doesn\'t look like a web address. Try something like cafe-example.ch, or leave it empty.', 'stubli' ), array( 'status' => 400 ) );
				}
			}
			if ( count( $out ) >= self::MAX ) {
				return new \WP_Error( 'stubli_link', __( 'You can keep up to 100 favourites.', 'stubli' ), array( 'status' => 400 ) );
			}
			if ( '' !== $group && ! in_array( $group, $names, true ) ) {
				if ( count( $names ) >= self::MAX_GROUPS ) {
					return new \WP_Error( 'stubli_link', __( 'You can have up to 12 groups.', 'stubli' ), array( 'status' => 400 ) );
				}
				$names[] = mb_substr( $group, 0, 30 );
				$group   = mb_substr( $group, 0, 30 );
			}
			$out[] = array(
				'label' => mb_substr( $label, 0, 60 ),
				'url'   => $url,
				'note'  => mb_substr( $note, 0, 80 ),
				'group' => $group,
			);
		}
		$used  = array_unique( array_column( $out, 'group' ) );
		$names = array_values( array_filter( $names, static fn( $g ) => in_array( $g, $used, true ) ) );
		update_option(
			'stubli_favourites',
			array(
				'items'  => $out,
				'groups' => $names,
			),
			false
		);
		$lines = array();
		foreach ( $out as $o ) {
			if ( '' !== $o['url'] ) {
				$lines[] = str_replace( '|', '/', $o['label'] ) . ' | ' . $o['url'];
			}
		}
		update_option( 'stubli_links', implode( "\n", $lines ) );
		return self::data();
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
					'callback'            => static fn() => rest_ensure_response( self::data() ),
					'permission_callback' => array( Rest::class, 'is_owner' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$items  = is_array( $r['items'] ) ? $r['items'] : ( is_array( $r['links'] ) ? $r['links'] : array() );
						$groups = is_array( $r['groups'] ) ? $r['groups'] : array();
						$res    = self::save( $items, $groups );
						return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
					},
					'permission_callback' => array( Rest::class, 'is_owner' ),
				),
			)
		);
	}
}
