<?php
/**
 * The setup wizard's data (design §13): Hello, Interests, Look, Your people, Ready.
 *
 * The wizard itself is drawn by the app at /app/#setup. This class stores what
 * it asks for, prints the chosen accent colour and reading mode, and opens the
 * wizard right after the plugin is activated.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Setup wizard storage and REST routes.
 */
class Setup {

	/**
	 * Accent colours (DESIGN.md §3.3): light hex, text on it, dark-mode partner.
	 * Dark partners are light enough for ≥ 4.5:1 on #1c1916, with dark text on them.
	 */
	const ACCENTS = array(
		'moss'   => array( 'Moss', '#4f6a4a', '#ffffff', '#a8bf98' ),
		'plum'   => array( 'Plum', '#6b3f5a', '#ffffff', '#d4a9c3' ),
		'sea'    => array( 'Sea', '#3d5468', '#ffffff', '#a7c0d6' ),
		'rust'   => array( 'Rust', '#8a4b2c', '#ffffff', '#e0a887' ),
		'gold'   => array( 'Gold', '#e0b65a', '#1d2433', '#e0b65a' ),
	);

	const MODES = array( 'auto', 'light', 'paper', 'dark' );

	/** Interest ideas offered as chips. */
	const IDEAS = array( 'Hikes', 'Food', 'Coffee', 'City', 'Travel', 'Friends', 'Family', 'Pets', 'Art', 'Nature', 'Books', 'Music' );

	const MAX_INTERESTS = 8;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'admin_init', array( self::class, 'maybe_open' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_accent' ), 100 );
	}

	/**
	 * Called on activation: open the wizard on the next admin page load.
	 */
	public static function flag_open() {
		if ( ! get_option( 'stubli_setup_done' ) ) {
			update_option( 'stubli_setup_open', 1, false );
		}
	}

	/**
	 * After activation, take the owner straight to the wizard ("the setup opens by itself").
	 */
	public static function maybe_open() {
		if ( ! get_option( 'stubli_setup_open' ) || wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_option( 'stubli_setup_open' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads whether this was a bulk activation.
		if ( isset( $_GET['activate-multi'] ) || get_option( 'stubli_setup_done' ) ) {
			return;
		}
		wp_safe_redirect( home_url( '/app/#setup' ) );
		exit;
	}

	/**
	 * The chosen accent key.
	 *
	 * @return string
	 */
	public static function accent() {
		$a = (string) get_option( 'stubli_accent', 'moss' );
		return isset( self::ACCENTS[ $a ] ) ? $a : 'moss';
	}

	/**
	 * The default reading mode for visitors who haven't picked one.
	 *
	 * @return string
	 */
	public static function mode() {
		$m = (string) get_option( 'stubli_mode', 'auto' );
		return in_array( $m, self::MODES, true ) ? $m : 'auto';
	}

	/**
	 * CSS that swaps the accent, for the app (--accent) and the look (--st-accent).
	 *
	 * @return string Empty for the default.
	 */
	public static function accent_css() {
		$key = self::accent();
		if ( 'moss' === $key ) {
			return '';
		}
		list( , $light, $ink, $dark ) = array_map( 'sanitize_hex_color', self::ACCENTS[ $key ] );
		// Gold is too pale for words on a light page, so text gets a deeper gold.
		$text = 'gold' === $key ? '#7d5f16' : $light;
		return sprintf(
			':root,[data-mode="light"],[data-mode="paper"]{--accent:%1$s;--accent-ink:%2$s;--accent-text:%4$s;--accent-far:%5$s;--st-accent:%1$s;--st-accent-ink:%2$s;--st-accent-text:%4$s;--st-accent-far:%5$s}[data-mode="dark"]{--accent:%3$s;--accent-ink:#1c1916;--accent-text:%3$s;--accent-far:#fff;--st-accent:%3$s;--st-accent-ink:#1c1916;--st-accent-text:%3$s;--st-accent-far:#fff}',
			$light,
			$ink,
			$dark,
			$text,
			// Card shades move away from the text colour, so words stay readable.
			in_array( strtolower( $ink ), array( '#fff', '#ffffff' ), true ) ? '#000' : '#fff'
		);
	}

	/**
	 * Add the accent to public pages (theme or built-in look), as an inline style
	 * on its own handle so it works with any theme.
	 */
	public static function enqueue_accent() {
		$css = self::accent_css();
		if ( '' !== $css ) {
			wp_register_style( 'stubli-accent', false, array(), VERSION );
			wp_enqueue_style( 'stubli-accent' );
			wp_add_inline_style( 'stubli-accent', wp_strip_all_tags( $css ) );
		}
	}

	/**
	 * Interests: categories, minus an empty "Uncategorized".
	 *
	 * @return array List of { name, count }.
	 */
	public static function interests() {
		$out     = array();
		$default = (int) get_option( 'default_category' );
		foreach ( get_categories( array( 'hide_empty' => false, 'orderby' => 'name' ) ) as $t ) {
			if ( $default === (int) $t->term_id ) {
				continue;
			}
			$out[] = array(
				'name'  => $t->name,
				'count' => (int) $t->count,
			);
		}
		return $out;
	}

	/**
	 * Make the categories match the chosen interests. Interests with posts are
	 * never removed; empty ones that were unticked are.
	 *
	 * @param array $names Chosen names.
	 */
	public static function set_interests( array $names ) {
		$clean = array();
		foreach ( $names as $n ) {
			$n = mb_substr( trim( sanitize_text_field( (string) $n ) ), 0, 40 );
			if ( '' !== $n && ! in_array( mb_strtolower( $n ), array_map( 'mb_strtolower', $clean ), true ) ) {
				$clean[] = $n;
			}
		}
		$clean   = array_slice( $clean, 0, self::MAX_INTERESTS );
		$lower   = array_map( 'mb_strtolower', $clean );
		$default = (int) get_option( 'default_category' );
		foreach ( get_categories( array( 'hide_empty' => false ) ) as $t ) {
			if ( $default !== (int) $t->term_id && 0 === (int) $t->count && ! in_array( mb_strtolower( $t->name ), $lower, true ) ) {
				wp_delete_term( $t->term_id, 'category' );
			}
		}
		foreach ( $clean as $n ) {
			if ( ! term_exists( $n, 'category' ) ) {
				wp_insert_term( $n, 'category' );
			}
		}
	}

	/**
	 * Everything the wizard shows.
	 *
	 * @return array
	 */
	public static function state() {
		$accents = array();
		foreach ( self::ACCENTS as $key => $a ) {
			$accents[] = array(
				'key'  => $key,
				'name' => $a[0],
				'hex'  => $a[1],
				'ink'  => $a[2],
				'dark' => $a[3],
			);
		}
		return array(
			'done'      => (bool) get_option( 'stubli_setup_done' ),
			'step'      => (int) get_option( 'stubli_setup_step', 0 ),
			'name'      => get_bloginfo( 'name' ),
			'bio'       => (string) get_option( 'stubli_bio', '' ),
			'pronouns'  => (string) get_option( 'stubli_pronouns', '' ),
			'avatar'    => Avatar::current(),
			'photo'     => '' !== Avatar::photo(),
			'cover'     => self::cover_url(),
			'smiley'    => Avatar::look() + Avatar::choices(),
			'interests' => self::interests(),
			'ideas'     => self::IDEAS,
			'max'       => self::MAX_INTERESTS,
			'accent'    => self::accent(),
			'accents'   => $accents,
			'mode'      => self::mode(),
			'digest'    => (string) get_option( 'stubli_digest_time', '08:00' ),
			'hidden'    => ! get_option( 'blog_public', 1 ) || ! get_option( 'stubli_setup_done' ),
			'block_ai'  => (bool) get_option( 'stubli_block_ai', 1 ),
			'seasonal'  => Seasonal::on(),
			// Asked as a yes/no in setup: null until the owner answers (WordPress.org guideline 10: credits are opt-in).
			'credit'    => false === get_option( 'stubli_credit', false ) ? null : (bool) get_option( 'stubli_credit' ),
			'helper'    => Helper::state(),
			'manual'    => Helper::manual_url(),
			'follows'   => array_map(
				static fn( $f ) => array(
					'id'     => (int) $f['id'],
					'name'   => (string) $f['name'],
					'url'    => (string) $f['site_url'],
					'list'   => (string) $f['list_name'],
					'avatar' => (string) ( $f['avatar_url'] ?? '' ),
				),
				Follows::all()
			),
		);
	}

	/**
	 * Save what a step changed. Unknown keys are ignored.
	 *
	 * @param array $c Changes.
	 * @return array|\WP_Error New state.
	 */
	public static function save( array $c ) {
		if ( array_key_exists( 'name', $c ) ) {
			$name = mb_substr( trim( sanitize_text_field( (string) $c['name'] ) ), 0, 60 );
			if ( '' === $name ) {
				return new \WP_Error( 'stubli_name', __( 'Add the name people should see.', 'stubli' ), array( 'status' => 400 ) );
			}
			update_option( 'blogname', $name );
		}
		if ( array_key_exists( 'bio', $c ) ) {
			update_option( 'stubli_bio', mb_substr( trim( sanitize_text_field( (string) $c['bio'] ) ), 0, 160 ) );
		}
		if ( array_key_exists( 'pronouns', $c ) ) {
			Settings::update( array( 'pronouns' => $c['pronouns'] ) );
		}
		if ( array_key_exists( 'interests', $c ) && is_array( $c['interests'] ) ) {
			self::set_interests( $c['interests'] );
		}
		if ( array_key_exists( 'accent', $c ) && isset( self::ACCENTS[ (string) $c['accent'] ] ) ) {
			update_option( 'stubli_accent', (string) $c['accent'] );
		}
		if ( array_key_exists( 'mode', $c ) && in_array( (string) $c['mode'], self::MODES, true ) ) {
			update_option( 'stubli_mode', (string) $c['mode'] );
		}
		if ( array_key_exists( 'credit', $c ) ) {
			Settings::update( array( 'credit' => $c['credit'] ) );
		}
		if ( array_key_exists( 'seasonal', $c ) ) {
			Seasonal::set( $c['seasonal'] );
		}
		if ( array_key_exists( 'block_ai', $c ) ) {
			Settings::update( array( 'block_ai' => $c['block_ai'] ) );
		}
		if ( array_key_exists( 'hidden', $c ) ) {
			Settings::update( array( 'search_engines' => ! rest_sanitize_boolean( $c['hidden'] ) ) );
		}
		if ( array_key_exists( 'digest', $c ) ) {
			Settings::update( array( 'digest_time' => $c['digest'] ) );
		}
		if ( array_key_exists( 'step', $c ) ) {
			update_option( 'stubli_setup_step', max( 0, min( 4, (int) $c['step'] ) ), false );
		}
		if ( ! empty( $c['done'] ) ) {
			if ( ! get_option( 'stubli_setup_done' ) ) {
				// The owner was told on the last step: replies from other sites need comments open.
				update_option( 'default_comment_status', 'open' );
				update_option( 'comment_registration', 0 );
			}
			update_option( 'stubli_setup_done', 1 );
			delete_option( 'stubli_setup_step' );
			// A small "Your first day" card on Home, only for a Stubli that starts empty.
			if ( false === get_option( 'stubli_firstday', false ) ) {
				update_option( 'stubli_firstday', App::has_photos() ? 'off' : time(), false );
			}
		}
		return self::state();
	}

	/**
	 * Save an uploaded profile photo and use it as the site icon (the avatar
	 * everywhere in Stubli). The app sends a square JPEG, already scaled.
	 *
	 * @return array|\WP_Error
	 */
	public static function photo() {
		if ( empty( $_FILES['photo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST checks the nonce.
			return new \WP_Error( 'stubli_no_photo', __( 'Choose a photo first.', 'stubli' ), array( 'status' => 400 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$file = $_FILES['photo']; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- handed to WordPress's upload checks.
		$type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( empty( $type['type'] ) || 0 !== strpos( (string) $type['type'], 'image/' ) ) {
			return new \WP_Error( 'stubli_not_photo', __( 'That file isn\'t a photo. Try a JPEG or PNG.', 'stubli' ), array( 'status' => 400 ) );
		}
		Posts::$fresh = true;
		$id           = media_handle_upload( 'photo', 0, array( 'post_title' => get_bloginfo( 'name' ) ) );
		Posts::$fresh = false;
		if ( is_wp_error( $id ) ) {
			return new \WP_Error( 'stubli_upload', __( 'That didn\'t upload. Try again when you\'re ready.', 'stubli' ), array( 'status' => 400 ) );
		}
		update_post_meta( $id, '_wp_attachment_context', 'site-icon' );
		update_option( 'site_icon', (int) $id );
		return array(
			'avatar' => (string) get_site_icon_url( 256 ),
			'photo'  => true,
		);
	}

	/**
	 * The cover photo across the top of the profile card, if there is one.
	 *
	 * @return string
	 */
	public static function cover_url() {
		$id = (int) get_option( 'stubli_cover', 0 );
		if ( ! $id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $id, 'full' );
		// A new cover always gets a new address, so no browser or cache shows the old one.
		return $url ? add_query_arg( 'v', $id, (string) $url ) : '';
	}

	/**
	 * Save a new cover photo. The app sends it already cropped to 3:1 and
	 * re-drawn (so location details are gone); this keeps it no bigger than
	 * 1800 × 600 either way.
	 *
	 * @return array|\WP_Error
	 */
	public static function cover() {
		if ( empty( $_FILES['cover'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST checks the nonce.
			return new \WP_Error( 'stubli_no_photo', __( 'Choose a photo first.', 'stubli' ), array( 'status' => 400 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$file = $_FILES['cover']; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- handed to WordPress's upload checks.
		$type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( empty( $type['type'] ) || 0 !== strpos( (string) $type['type'], 'image/' ) ) {
			return new \WP_Error( 'stubli_not_photo', __( 'That file isn\'t a photo. Try a JPEG or PNG.', 'stubli' ), array( 'status' => 400 ) );
		}
		Posts::$fresh = true;
		$id           = media_handle_upload( 'cover', 0, array( 'post_title' => __( 'Cover photo', 'stubli' ) ) );
		Posts::$fresh = false;
		if ( is_wp_error( $id ) ) {
			return new \WP_Error( 'stubli_upload', __( 'That didn\'t upload. Try again when you\'re ready.', 'stubli' ), array( 'status' => 400 ) );
		}
		$path = get_attached_file( $id );
		$ed   = $path ? wp_get_image_editor( $path ) : null;
		if ( $ed && ! is_wp_error( $ed ) ) {
			$size = $ed->get_size();
			if ( $size['width'] > 1800 || $size['height'] > 600 ) {
				$ed->resize( 1800, 600, true );
				$ed->save( $path );
				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
			}
		}
		$old = (int) get_option( 'stubli_cover', 0 );
		update_option( 'stubli_cover', (int) $id );
		if ( $old && $old !== (int) $id ) {
			wp_delete_attachment( $old, true );
		}
		return array( 'cover' => self::cover_url() );
	}

	/**
	 * Take the cover photo away.
	 *
	 * @return array
	 */
	public static function cover_remove() {
		$old = (int) get_option( 'stubli_cover', 0 );
		delete_option( 'stubli_cover' );
		if ( $old ) {
			wp_delete_attachment( $old, true );
		}
		return array( 'cover' => '' );
	}

	/**
	 * Use an invite link pasted in the wizard ("Got an invite link?").
	 *
	 * @param string $link Like https://ana.ch/invite/?t=abc.
	 * @return array|\WP_Error
	 */
	public static function use_invite( $link ) {
		$link  = trim( (string) $link );
		$parts = wp_parse_url( $link );
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}
		$token = isset( $query['t'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $query['t'] ) : '';
		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) || '' === $token ) {
			return new \WP_Error( 'stubli_invite_link', __( 'That doesn\'t look like an invite link. It ends in /invite/?t= and a code.', 'stubli' ), array( 'status' => 400 ) );
		}
		$path = isset( $parts['path'] ) ? preg_replace( '#invite/?$#', '', $parts['path'] ) : '/';
		$home = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . ( '' === $path ? '/' : $path );
		$res  = Invites::accept_here( $home, $token );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array(
			'result'  => $res,
			'follows' => self::state()['follows'],
		);
	}

	/**
	 * REST routes (owner only).
	 */
	public static function routes() {
		$owner = array( Rest::class, 'is_owner' );
		Rest::register(
			'/setup',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn() => rest_ensure_response( self::state() ),
					'permission_callback' => $owner,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$res = self::save( (array) $r->get_json_params() + (array) $r->get_body_params() );
						return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
					},
					'permission_callback' => $owner,
				),
			)
		);
		Rest::register(
			'/setup/webmention',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function () {
					$res = Helper::add();
					return is_wp_error( $res ) ? $res : rest_ensure_response( array( 'helper' => Helper::state() ) );
				},
				'permission_callback' => $owner,
			)
		);
		Rest::register(
			'/setup/photo',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function () {
					$res = self::photo();
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => $owner,
			)
		);
		Rest::register(
			'/setup/cover',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function () {
						$res = self::cover();
						return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
					},
					'permission_callback' => $owner,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => static fn() => rest_ensure_response( self::cover_remove() ),
					'permission_callback' => $owner,
				),
			)
		);
		Rest::register(
			'/setup/smiley',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$l = Avatar::save( $r['c'], (string) $r['x'] );
					return rest_ensure_response(
						array(
							'avatar' => Avatar::url( $l ),
							'photo'  => false,
							'smiley' => $l + Avatar::choices(),
						)
					);
				},
				'permission_callback' => $owner,
			)
		);
		Rest::register(
			'/setup/invite',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$res = self::use_invite( (string) $r['link'] );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => $owner,
			)
		);
	}
}
