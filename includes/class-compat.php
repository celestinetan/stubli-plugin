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
	 * True while Stubli itself is publishing (the app, the importer, a move).
	 *
	 * @var bool
	 */
	public static $quiet = false;

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
		// Posts made in Stubli reach followers in their own Stubli, so WordPress.com
		// doesn't also email them to the site's subscribers or share them to the
		// owner's social accounts (Celestine's decision 28 Oct). Posts written in
		// WordPress's own editor are left alone.
		add_filter( 'rest_request_before_callbacks', array( self::class, 'note_request' ), 10, 3 );
		add_action( 'transition_post_status', array( self::class, 'quiet_publish' ), 1, 3 );
		add_filter( 'publicize_should_publicize_published_post', array( self::class, 'no_publicize' ), 99 );
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
	 * Note when a request comes from Stubli's own app.
	 *
	 * @param mixed            $response Response so far.
	 * @param array            $handler  Route handler.
	 * @param \WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function note_request( $response, $handler, $request ) {
		if ( $request instanceof \WP_REST_Request && preg_match( '#^/(stubli|og)/v1/#', (string) $request->get_route() ) ) {
			self::$quiet = true;
		}
		return $response;
	}

	/**
	 * A post Stubli publishes: no subscriber email, no social sharing.
	 *
	 * @param string   $new  New status.
	 * @param string   $old  Old status.
	 * @param \WP_Post $post Post.
	 */
	public static function quiet_publish( $new, $old, $post ) {
		if ( ! self::$quiet || 'publish' !== $new || 'publish' === $old || 'post' !== $post->post_type ) {
			return;
		}
		update_post_meta( $post->ID, '_jetpack_dont_email_post_to_subs', 1 );
		update_post_meta( $post->ID, 'jetpack_publicize_feature_enabled', false );
		update_post_meta( $post->ID, '_wpas_done_all', 1 );
	}

	/**
	 * Jetpack asks whether to share a post that was just published.
	 *
	 * @param bool $should Share it.
	 * @return bool
	 */
	public static function no_publicize( $should ) {
		return self::$quiet ? false : $should;
	}

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
