<?php
/**
 * Front-end setup shared by the Stubli theme and the Stubli plugin's
 * built-in look: assets, reading mode, the extra pages, titles, privacy.
 * The same file ships in both; stubli_look_dir()/stubli_look_uri() say
 * where the assets live.
 *
 * @package Stubli
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supports and image sizes.
 */
add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'html5', array( 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'post-formats', array( 'image', 'gallery', 'status' ) );
		add_image_size( 'stubli-tile', 640, 800, true );   // 4:5 grid tiles.
		add_image_size( 'stubli-large', 1280, 1600, false );
	},
	20
);

/**
 * Styles, the small script, and fonts. No third-party requests.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		$uri = stubli_look_uri();
		$dir = stubli_look_dir();
		wp_enqueue_style( 'stubli', $uri . '/assets/css/stubli.css', array(), (string) filemtime( $dir . '/assets/css/stubli.css' ) );
		wp_add_inline_style( 'stubli', wp_strip_all_tags( stubli_font_faces() ) );
		wp_enqueue_script( 'stubli', $uri . '/assets/js/stubli.js', array(), (string) filemtime( $dir . '/assets/js/stubli.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
		wp_localize_script(
			'stubli',
			'stubliText',
			array(
				'modes'       => array(
					'auto'  => __( 'Auto', 'stubli' ),
					'light' => __( 'Light', 'stubli' ),
					'paper' => __( 'Paper', 'stubli' ),
					'dark'  => __( 'Dark', 'stubli' ),
				),
				'readingMode' => __( 'Reading mode', 'stubli' ),
				/* translators: 1: current photo, 2: number of photos */
				'of'          => __( '%1$d of %2$d', 'stubli' ),
				'close'       => __( 'Close', 'stubli' ),
				'prevPhoto'   => __( 'Previous photo', 'stubli' ),
				'nextPhoto'   => __( 'Next photo', 'stubli' ),
				/* translators: 1: current photo, 2: number of photos */
				'photoOf'     => __( 'Photo %1$d of %2$d', 'stubli' ),
				'badAddress'  => __( 'That doesn\'t look like a website address. Try something like yourname.com.', 'stubli' ),
				/* translators: %s: name */
				'invitedBy'   => __( '%s invited you', 'stubli' ),
				/* translators: 1: step number, 2: step name */
				'badInvite'   => __( 'That doesn\'t look like an invite link. It looks like theirname.com/invite/?t=… Ask your friend to send it again if you can\'t find it.', 'stubli' ),
				'gs1'         => __( 'Get a WordPress site', 'stubli' ),
				'gs2'         => __( 'Add the Stubli plugin', 'stubli' ),
				'gs3'         => __( 'Answer five quick questions', 'stubli' ),
				'gs4'         => __( 'Invite your people', 'stubli' ),
				/* translators: %1$s: step number, 1 to 4. */
				'stepOf'      => __( 'Step %1$s of 4', 'stubli' ),
				/* translators: 1: step number, 2: step name. */
				'backTo'      => __( 'Back to step %1$s: %2$s', 'stubli' ),
			)
		);
		// Keep WordPress's global block styles from fighting the design.
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'wp-block-library-theme' );
	},
	20
);

/**
 * Theme colours for the phone's browser bar.
 */
add_action(
	'wp_head',
	static function () {
		?>
<meta name="theme-color" content="#fbfaf8" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#1c1916" media="(prefers-color-scheme: dark)">
		<?php
	},
	1
);

/**
 * Set the reading mode before anything paints, so there's no flash: a tiny
 * script with no file, enqueued in the head.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		$pref = get_option( 'stubli_mode', 'auto' );
		$pref = in_array( $pref, array( 'auto', 'light', 'paper', 'dark' ), true ) ? $pref : 'auto';
		wp_register_script( 'stubli-mode', false, array(), (string) filemtime( stubli_look_dir() . '/assets/js/stubli.js' ), false );
		wp_enqueue_script( 'stubli-mode' );
		wp_add_inline_script( 'stubli-mode', "(function(){try{var p=localStorage.getItem('stubli-mode')||" . wp_json_encode( $pref ) . ";var m=p==='auto'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):p;document.documentElement.setAttribute('data-mode',m);document.documentElement.setAttribute('data-mode-pref',p);}catch(e){document.documentElement.setAttribute('data-mode','light');}})();" );
	},
	1
);

add_filter(
	'query_vars',
	static function ( $vars ) {
		$vars[] = 'stubli_links';
		$vars[] = 'stubli_remote';
		$vars[] = 'stubli_invite';
		return $vars;
	}
);

/**
 * /favourites/ (and the old /links/), /from-your-stubli/ and /invite/ without rewrite rules, so they
 * work straight after an update with nothing to flush.
 */
add_filter(
	'request',
	static function ( $vars ) {
		if ( is_admin() ) {
			return $vars;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$base = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $base && 0 === strpos( $uri, $base ) ) {
			$uri = substr( $uri, strlen( $base ) );
		}
		$map = array(
			'links'            => 'stubli_links',
			'favourites'       => 'stubli_links',
			'from-your-stubli' => 'stubli_remote',
			'invite'           => 'stubli_invite',
		);
		$key = trim( $uri, '/' );
		if ( isset( $map[ $key ] ) ) {
			return array( $map[ $key ] => '1' );
		}
		return $vars;
	}
);

add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}
		if ( $query->get( 'stubli_links' ) || $query->get( 'stubli_remote' ) || $query->get( 'stubli_invite' ) ) {
			$query->set( 'posts_per_page', 1 );
			$query->is_home = false;
		}
	}
);

/**
 * These pages aren't 404s.
 */
add_action(
	'template_redirect',
	static function () {
		if ( get_query_var( 'stubli_links' ) || get_query_var( 'stubli_remote' ) || get_query_var( 'stubli_invite' ) || get_query_var( 'stubli_journal' ) ) {
			global $wp_query;
			$wp_query->is_404 = false;
			status_header( 200 );
		}
	},
	0
);

/**
 * Signed in, your own pages are never kept by the browser, so what you just
 * changed in the app (a cover, the order of your tabs) shows at once.
 */
add_action(
	'template_redirect',
	static function () {
		if ( is_user_logged_in() && ! headers_sent() ) {
			nocache_headers();
		}
	}
);

/**
 * Titles for the custom pages.
 */
add_filter(
	'document_title_parts',
	static function ( $parts ) {
		if ( 'all' === get_query_var( 'stubli_journal' ) ) {
			$parts['title'] = __( 'All entries', 'stubli' );
		} elseif ( get_query_var( 'stubli_journal' ) ) {
			$parts['title'] = __( 'Journal', 'stubli' );
		} elseif ( get_query_var( 'stubli_links' ) ) {
			$parts['title'] = __( 'Favourites', 'stubli' );
		} elseif ( get_query_var( 'stubli_remote' ) ) {
			$parts['title'] = __( 'From your own Stubli', 'stubli' );
		} elseif ( get_query_var( 'stubli_invite' ) ) {
			$parts['title'] = __( 'You\'re invited', 'stubli' );
		} elseif ( is_front_page() && class_exists( '\\Stubli\\Feedback' ) && \Stubli\Feedback::is_hub() ) {
			// stubli.app is found by people searching for Stubli, not for its owner's name.
			$parts['title'] = 'Stubli';
		}
		// Every other stubli.app page ends in "– Stubli", not its owner's name.
		if ( ! is_front_page() && class_exists( '\\Stubli\\Feedback' ) && \Stubli\Feedback::is_hub() ) {
			$parts['site'] = 'Stubli';
		}
		return $parts;
	}
);

/**
 * No calls to outside servers when someone visits: keep emoji as text (no
 * wordpress.org emoji script) and never ask Gravatar for avatars.
 */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
add_filter( 'emoji_svg_url', '__return_false' );

add_filter(
	'get_avatar_url',
	static function ( $url ) {
		if ( false === strpos( (string) $url, 'gravatar.com' ) ) {
			return $url;
		}
		return 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" fill="#e6e1d8"/><circle cx="20" cy="16" r="7" fill="#c9c1b4"/><path d="M6 38c2-8 8-12 14-12s12 4 14 12z" fill="#c9c1b4"/></svg>' );
	}
);
