<?php
/**
 * Smiley avatars: the default face for a Stubli without a photo.
 *
 * Built from the Stubli smile (the ü: two dots and a curve) in one of the
 * poster inks, with one accessory. Every new Stubli gets a random one, and
 * its owner can pick another in setup. The look is part of the address
 * (/?stubli_avatar=3-cowboy), so other Stublis can show and cache it.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Smiley avatars.
 */
class Avatar {

	/**
	 * Background inks, in order: moss, rust, sea, poster red, gold, plum (the
	 * accent colours plus red). The smile is cream on all of them.
	 */
	const COLOURS = array( '#4f6a4a', '#b5643c', '#55696a', '#b33a2c', '#e5b552', '#6b3f5a' );

	/**
	 * Accessories, with the names people see.
	 */
	const EXTRAS = array(
		''          => 'Just me',
		'blush'     => 'Blush',
		'flowers'   => 'Flowers',
		'cowboy'    => 'Cowboy hat',
		'beret'     => 'Beret',
		'moustache' => 'Moustache',
		'bow'       => 'Bow',
		'glasses'   => 'Glasses',
		'beanie'    => 'Beanie',
		'alpine'    => 'Alpine hat',
	);

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'serve' ), 1 );
	}

	/**
	 * This Stubli's look, picking a random one the first time.
	 *
	 * @return array{c:int,x:string}
	 */
	public static function look() {
		$l = get_option( 'stubli_avatar' );
		if ( ! is_array( $l ) || ! isset( $l['c'], $l['x'] ) ) {
			$extras = array_keys( self::EXTRAS );
			array_shift( $extras ); // A first look always has an accessory: more fun.
			$l = array(
				'c' => wp_rand( 0, count( self::COLOURS ) - 1 ),
				'x' => $extras[ wp_rand( 0, count( $extras ) - 1 ) ],
			);
			update_option( 'stubli_avatar', $l, false );
		}
		return self::clean( $l['c'], $l['x'] );
	}

	/**
	 * Keep a look inside the known colours and accessories.
	 *
	 * @param mixed $c Colour index.
	 * @param mixed $x Accessory key.
	 * @return array{c:int,x:string}
	 */
	public static function clean( $c, $x ) {
		$c = max( 0, min( count( self::COLOURS ) - 1, (int) $c ) );
		$x = isset( self::EXTRAS[ (string) $x ] ) ? (string) $x : '';
		return array(
			'c' => $c,
			'x' => $x,
		);
	}

	/**
	 * Save a look and use it instead of any photo.
	 *
	 * @param mixed $c Colour index.
	 * @param mixed $x Accessory key.
	 * @return array{c:int,x:string}
	 */
	public static function save( $c, $x ) {
		$l = self::clean( $c, $x );
		update_option( 'stubli_avatar', $l, false );
		delete_option( 'site_icon' );
		return $l;
	}

	/**
	 * Public address of a smiley.
	 *
	 * @param array|null $l Look; this Stubli's when null.
	 * @return string
	 */
	public static function url( $l = null ) {
		$l = $l ? self::clean( $l['c'] ?? 0, $l['x'] ?? '' ) : self::look();
		return add_query_arg( 'stubli_avatar', $l['c'] . ( '' !== $l['x'] ? '-' . $l['x'] : '' ), home_url( '/' ) );
	}

	/**
	 * The avatar to show: the photo when there is one, otherwise the smiley.
	 *
	 * @return string
	 */
	public static function current() {
		$icon = (string) get_site_icon_url( 256 );
		return '' !== $icon ? $icon : self::url();
	}

	/**
	 * Every choice, for the setup picker.
	 *
	 * @return array
	 */
	public static function choices() {
		$extras = array();
		foreach ( self::EXTRAS as $k => $label ) {
			$extras[] = array(
				'key'   => $k,
				'label' => $label,
			);
		}
		// Drawings for the picker, so it can preview without asking the server:
		// one per accessory with the colour left open, plus the few that differ
		// on a particular colour.
		$tpl  = array();
		$diff = array();
		foreach ( array_keys( self::EXTRAS ) as $x ) {
			$tpl[ $x ] = str_replace( self::COLOURS[0], '__BG__', self::svg( array( 'c' => 0, 'x' => $x ) ) );
			foreach ( self::COLOURS as $c => $hex ) {
				$own = self::svg( array( 'c' => $c, 'x' => $x ) );
				if ( str_replace( '__BG__', $hex, $tpl[ $x ] ) !== $own ) {
					$diff[ $c . '-' . $x ] = $own;
				}
			}
		}
		return array(
			'colours' => self::COLOURS,
			'extras'  => $extras,
			'base'    => add_query_arg( 'stubli_avatar', '', home_url( '/' ) ),
			'tpl'     => $tpl,
			'own'     => (object) $diff,
		);
	}

	/**
	 * Answer /?stubli_avatar=3-cowboy with the SVG. The look is in the address,
	 * so it can be cached for a long time.
	 */
	public static function serve() {
		if ( ! isset( $_GET['stubli_avatar'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public image.
			return;
		}
		$v     = sanitize_key( wp_unslash( $_GET['stubli_avatar'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$parts = explode( '-', $v, 2 );
		$l     = '' === $v ? self::look() : self::clean( $parts[0], $parts[1] ?? '' );
		nocache_headers();
		header_remove( 'Cache-Control' );
		header_remove( 'Expires' );
		header_remove( 'Pragma' );
		header( 'Content-Type: image/svg+xml; charset=utf-8' );
		header( 'Cache-Control: public, max-age=31536000, immutable' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'" );
		echo self::svg( $l ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from known parts only.
		exit;
	}

	/**
	 * The smiley as SVG.
	 *
	 * @param array{c:int,x:string} $l Look.
	 * @return string
	 */
	public static function svg( $l ) {
		$l     = self::clean( $l['c'] ?? 0, $l['x'] ?? '' );
		$bg    = self::COLOURS[ $l['c'] ];
		$ink   = '#fbf5e7';
		$dark  = '#23302a';
		$blush = '<g transform="translate(0 4)"><circle cx="19.5" cy="33" r="3.6" fill="#e98b76" opacity=".85"/><circle cx="44.5" cy="33" r="3.6" fill="#e98b76" opacity=".85"/></g>';
		$acc   = array(
			'blush'     => $blush,
			'flowers'   => $blush . '<g fill="' . $ink . '"><circle cx="20" cy="11" r="3.4"/><circle cx="32" cy="7.5" r="3.8"/><circle cx="44" cy="11" r="3.4"/></g><g fill="#e5b552"><circle cx="20" cy="11" r="1.3"/><circle cx="32" cy="7.5" r="1.5"/><circle cx="44" cy="11" r="1.3"/></g><path d="M14 14q18-9 36 0" fill="none" stroke="#7d9a5d" stroke-width="2"/>',
			'cowboy'    => '<path d="M8 17q24 7 48 0l-3 4q-21 6-42 0z" fill="#8f4a2a"/><path d="M19 16c0-9 4-12 6-12 2 0 4 2 7 2s5-2 7-2c2 0 6 3 6 12q-13 3-26 0z" fill="#b5643c"/><path d="M19.5 13q12.5 3 25 0" stroke="#8f4a2a" stroke-width="2" fill="none"/>',
			'beret'     => '<path d="M15 16c0-7 8-11 18-11s16 4 16 10q-17 4-34 1z" fill="#23302a"/><path d="M32 5l2-4" stroke="#23302a" stroke-width="2" stroke-linecap="round"/>',
			'moustache' => '<path transform="translate(0 4)" d="M32 30c-3-4-9-4-12-1-2 2-4 2-5 1 1 4 5 6 9 5 3-1 6-3 8-5 2 2 5 4 8 5 4 1 8-1 9-5-1 1-3 1-5-1-3-3-9-3-12 1z" fill="' . $dark . '"/>',
			'bow'       => $blush . '<path d="M32 9l-10-6v12zM32 9l10-6v12z" fill="#b33a2c"/><circle cx="32" cy="9" r="2.6" fill="#8f2b21"/>',
			'glasses'   => '<g transform="translate(0 4)" fill="none" stroke="' . $ink . '" stroke-width="2"><circle cx="26.5" cy="21.5" r="6"/><circle cx="37.5" cy="21.5" r="6"/><path d="M32.5 21.5h-1M20.5 21l-5-2M43.5 21l5-2"/></g>',
			'beanie'    => '<path d="M17 18c0-9 7-13 15-13s15 4 15 13z" fill="#e5b552"/><rect x="15" y="15" width="34" height="6" rx="3" fill="#c9953a"/><circle cx="32" cy="4.5" r="3.6" fill="' . $ink . '"/>',
			'alpine'    => '<path d="M10 17q22 5 44 0l-2 3q-20 5-40 0z" fill="#2f4a33"/><path d="M19 16c1-7 5-10 13-10s12 3 13 10q-13 3-26 0z" fill="#3f553b"/><path d="M19.5 14q12.5 3 25 0" stroke="#b33a2c" stroke-width="2" fill="none"/><path d="M41 13c4-6 9-9 13-10-2 4-6 8-11 11z" fill="' . $ink . '"/><path transform="translate(0 4)" d="M32 30c-3-3-8-3-10-1l-3 1c2 3 5 4 8 3 2-1 4-2 5-3 1 1 3 2 5 3 3 1 6 0 8-3l-3-1c-2-2-7-2-10 1z" fill="' . $ink . '" opacity=".9"/>',
		);
		// Yellow is light: the hat and the bow need the darker pair there.
		if ( '#e5b552' === $bg ) {
			$acc['beanie'] = str_replace( array( '#e5b552', '#c9953a' ), array( '#b5643c', '#8f4a2a' ), $acc['beanie'] );
		}
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="256" height="256"><rect width="64" height="64" fill="' . $bg . '"/>'
			. '<g transform="translate(17 18) scale(.5)"><path d="M10.5 35.5V52a19.5 19.5 0 0 0 39 0V35.5" fill="none" stroke="' . $ink . '" stroke-width="11" stroke-linecap="round"/><circle cx="19" cy="15" r="6.2" fill="' . $ink . '"/><circle cx="41" cy="15" r="6.2" fill="' . $ink . '"/></g>'
			. ( $acc[ $l['x'] ] ?? '' ) . '</svg>';
	}
}
