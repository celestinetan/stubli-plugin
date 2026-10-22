<?php
/**
 * Hosting compatibility: WordPress.com / Jetpack.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress.com switches on Jetpack features that clash with Stubli's calm
 * design: a Like button, its own comment form, sharing buttons and "related
 * posts". Stubli has comments from your own site instead, and no likes, so
 * these are turned off while Stubli is active. Jetpack itself stays on.
 */
class Compat {

	/**
	 * Register hooks.
	 */
	public static function init() {
		// No Likes (design decision Q10: comments, not likes).
		add_filter( 'wpl_is_enabled_sitewide', '__return_false', 99 );
		add_filter( 'wpl_is_likes_visible', '__return_false', 99 );
		add_filter( 'jetpack_comment_likes_enabled', '__return_false', 99 );
		// Comments arrive from people's own Stubli, not through Jetpack's form.
		add_filter( 'jetpack_comment_form_enabled_for_post', '__return_false', 99 );
		add_filter( 'jetpack_comment_form_enabled', '__return_false', 99 );
		// No sharing buttons, related posts or "subscribe" pop-ups on a quiet profile.
		add_filter( 'sharing_show', '__return_false', 99 );
		add_filter( 'jetpack_relatedposts_filter_enabled_for_request', '__return_false', 99 );
		add_filter( 'jetpack_subscriptions_modal_enabled', '__return_false', 99 );
		add_filter( 'jetpack_is_frontend_subscribe_modal_enabled', '__return_false', 99 );
		add_filter( 'jetpack_subscribe_floating_button_enabled', '__return_false', 99 );
		add_filter( 'jetpack_blaze_enabled', '__return_false', 99 );
		add_action( 'init', array( self::class, 'drop_jetpack_modules' ), 99 );
		// Stubli opens photos itself; Jetpack's lightbox brings its own comment box.
		add_filter( 'jp_carousel_maybe_disable', '__return_true', 99 );
		// No Gravatar profile pop-ups: they load scripts from gravatar.com for every visitor.
		add_filter( 'pre_option_gravatar_disable_hovercards', array( self::class, 'disabled' ), 99 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'drop_third_party_assets' ), 999 );
		add_action( 'wp_footer', array( self::class, 'drop_third_party_assets' ), 1 );
	}

	/**
	 * Remove the Like and sharing output if the modules are already loaded.
	 */
	/**
	 * @return string
	 */
	public static function disabled() {
		return 'disabled';
	}

	/**
	 * Dequeue Gravatar hovercards and Jetpack's tiled-gallery/carousel scripts on the front end.
	 */
	public static function drop_third_party_assets() {
		if ( is_admin() ) {
			return;
		}
		foreach ( array( 'grofiles-cards', 'wpgroho', 'jetpack-carousel', 'jetpack-carousel-swiper-css', 'tiled-gallery' ) as $handle ) {
			wp_dequeue_script( $handle );
			wp_dequeue_style( $handle );
		}
		wp_dequeue_style( 'gravatar-hovercards' );
	}

	/**
	 * Remove the Like and sharing output if the modules are already loaded.
	 */
	public static function drop_jetpack_modules() {
		remove_filter( 'the_content', 'sharing_display', 19 );
		remove_filter( 'the_excerpt', 'sharing_display', 19 );
	}
}
