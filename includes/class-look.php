<?php
/**
 * The Stubli look, built into the plugin: one install gives a finished
 * Stubli whatever theme the site has. When the Stubli theme is active it
 * does the drawing instead, and this stays out of the way.
 *
 * The files in /look are copied from the theme (tests/sync-look.sh), so
 * the theme and the plugin always draw the same pages.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the look and takes over the front-end template.
 */
class Look {

	/**
	 * Is the look on for this site?
	 *
	 * @return bool
	 */
	public static function active() {
		if ( 'stubli' === get_option( 'template' ) || 'stubli' === get_option( 'stylesheet' ) ) {
			return false; // The theme draws the pages.
		}
		return (bool) get_option( 'stubli_look', 0 );
	}

	/**
	 * Load it (runs on plugins_loaded, before the theme).
	 */
	public static function init() {
		if ( ! self::active() || function_exists( 'stubli_render_view' ) ) {
			return;
		}
		require_once STUBLI_PLUGIN_DIR . 'look/functions.php';
		require_once STUBLI_PLUGIN_DIR . 'look/inc/helpers.php';
		require_once STUBLI_PLUGIN_DIR . 'look/inc/views.php';
		require_once STUBLI_PLUGIN_DIR . 'look/inc/setup.php';
		add_filter( 'template_include', array( self::class, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'drop_theme_styles' ), 999 );
		// The theme's own font declarations aren't needed.
		add_action(
			'init',
			static function () {
				remove_action( 'wp_head', 'wp_print_font_faces', 50 );
				remove_action( 'wp_head', 'wp_print_font_faces_from_style_variations', 50 );
			}
		);
	}

	/**
	 * Our own page for everything on the front end.
	 *
	 * @param string $template Template the theme chose.
	 * @return string
	 */
	public static function template( $template ) {
		if ( is_feed() || is_embed() || is_robots() || is_trackback() ) {
			return $template;
		}
		return STUBLI_PLUGIN_DIR . 'look/page.php';
	}

	/**
	 * Which view to draw.
	 *
	 * @return string
	 */
	public static function view() {
		if ( is_404() ) {
			return 'notfound';
		}
		if ( is_single() ) {
			return 'single';
		}
		if ( is_page() ) {
			return 'page';
		}
		if ( is_attachment() ) {
			return 'notfound';
		}
		return 'profile';
	}

	/**
	 * The theme's own styles would fight the design.
	 */
	public static function drop_theme_styles() {
		$theme = array( get_stylesheet_directory_uri(), get_template_directory_uri() );
		foreach ( wp_styles()->queue as $handle ) {
			$src = (string) ( wp_styles()->registered[ $handle ]->src ?? '' );
			foreach ( $theme as $base ) {
				if ( '' !== $base && 0 === strpos( $src, $base ) ) {
					wp_dequeue_style( $handle );
				}
			}
		}
		foreach ( wp_scripts()->queue as $handle ) {
			$src = (string) ( wp_scripts()->registered[ $handle ]->src ?? '' );
			foreach ( $theme as $base ) {
				if ( '' !== $base && 0 === strpos( $src, $base ) ) {
					wp_dequeue_script( $handle );
				}
			}
		}
	}
}
