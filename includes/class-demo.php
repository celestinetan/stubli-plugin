<?php
/**
 * Demo mode, for the "See a demo Stubli" in WordPress Playground: a small
 * bar on every page and app screen says it's a demo, that nothing is saved,
 * and how to make a real Stubli. Real Stublis never show it; only the demo's
 * setup switches it on (option stubli_demo).
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * The demo bar.
 */
class Demo {

	const GET_STARTED = 'https://stubli.app/get-started/';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_body_open', array( self::class, 'bar' ) );
	}

	/**
	 * Is this site the Playground demo?
	 *
	 * @return bool
	 */
	public static function on() {
		return (bool) get_option( 'stubli_demo' );
	}

	/**
	 * The words on the bar, for the page and the app.
	 *
	 * @return array { text, link, url }
	 */
	public static function words() {
		return array(
			'text' => __( 'Demo · nothing is saved', 'stubli' ),
			'link' => __( 'Make your own Stubli', 'stubli' ),
			'url'  => self::GET_STARTED,
		);
	}

	/**
	 * The bar at the top of every page of the demo.
	 */
	public static function bar() {
		if ( ! self::on() ) {
			return;
		}
		$w = self::words();
		echo '<div class="stubli-demo-bar" role="note" style="position:sticky;top:0;z-index:1000;display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:4px 12px;padding:8px 16px;background:#22201d;color:#fbfaf8;font:500 14px/1.4 Figtree,system-ui,sans-serif;text-align:center">'
			. '<span>' . esc_html( $w['text'] ) . '</span>'
			. '<a href="' . esc_url( $w['url'] ) . '" target="_blank" rel="noopener" style="color:#fbfaf8;font-weight:650;text-decoration:underline;text-underline-offset:3px">' . esc_html( $w['link'] ) . ' →</a>'
			. '</div>';
	}
}
