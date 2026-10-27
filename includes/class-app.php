<?php
/**
 * The Stubli app at /app: the owner's private side (Q6 C, Q8 B).
 * v0.4 ships the shell and the composer (photos and journal); the feed,
 * Discover and What's new follow.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Serves /app and /app/manifest.webmanifest without rewrite rules, so it
 * works right after the plugin is updated.
 */
class App {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'parse_request', array( self::class, 'route' ), 0 );
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar' ), 70 );
		// No WordPress toolbar over a Stubli page: the app is the owner's way in.
		add_filter( 'show_admin_bar', array( self::class, 'hide_toolbar' ), 99 );
	}

	/**
	 * Hide the toolbar on the front end of Stubli sites (theme or built-in look).
	 *
	 * @param bool $show Show it.
	 * @return bool
	 */
	public static function hide_toolbar( $show ) {
		if ( is_admin() ) {
			return $show;
		}
		if ( 'stubli' === get_option( 'stylesheet' ) || Look::active() ) {
			return false;
		}
		return $show;
	}

	/**
	 * The request path relative to the site, e.g. "app" or "app/manifest.webmanifest".
	 *
	 * @return string
	 */
	private static function path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$base = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $base && 0 === strpos( $uri, $base ) ) {
			$uri = substr( $uri, strlen( $base ) );
		}
		return trim( $uri, '/' );
	}

	/**
	 * Is this request for the app?
	 *
	 * @return bool
	 */
	public static function is_app_request() {
		$p = self::path();
		return 'app' === $p || 0 === strpos( $p, 'app/' );
	}

	/**
	 * Route /app requests.
	 */
	public static function route() {
		$path = self::path();
		if ( 'app/manifest.webmanifest' === $path ) {
			self::manifest();
		}
		// No ".js" in the address: some hosts (WordPress.com) answer those
		// themselves and never pass them to WordPress.
		if ( 'app/notifications' === $path || 'app/sw.js' === $path ) {
			Digest::service_worker();
		}
		if ( 'app' !== $path && 0 !== strpos( $path, 'app/' ) ) {
			return;
		}
		nocache_headers();
		if ( ! is_user_logged_in() ) {
			$back = home_url( '/' . $path . '/' );
			if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
				$back .= '?' . wp_unslash( $_SERVER['QUERY_STRING'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed through esc_url_raw by wp_login_url.
			}
			wp_safe_redirect( wp_login_url( esc_url_raw( $back ) ) );
			exit;
		}
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_die( esc_html__( 'The Stubli app is for the owner of this site.', 'stubli' ), esc_html__( 'Stubli', 'stubli' ), array( 'response' => 403 ) );
		}
		self::render( isset( $_GET['act'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		exit;
	}

	/**
	 * "Stubli app" link in the toolbar.
	 *
	 * @param \WP_Admin_Bar $bar Toolbar.
	 */
	public static function admin_bar( $bar ) {
		if ( current_user_can( 'publish_posts' ) ) {
			$bar->add_node(
				array(
					'id'    => 'stubli-app',
					'title' => __( 'Stubli app', 'stubli' ),
					'href'  => home_url( '/app/' ),
				)
			);
		}
	}

	/**
	 * The web app manifest, for Add to Home Screen.
	 */
	private static function manifest() {
		$icons = STUBLI_PLUGIN_URL . 'assets/app/';
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode(
			array(
				'name'             => 'Stubli',
				'short_name'       => 'Stubli',
				'start_url'        => home_url( '/app/' ),
				'scope'            => home_url( '/app/' ),
				'display'          => 'standalone',
				'background_color' => '#ffffff',
				'theme_color'      => '#fbfaf8',
				'icons'            => array(
					array(
						'src'     => $icons . 'icon-192.png',
						'sizes'   => '192x192',
						'type'    => 'image/png',
						'purpose' => 'any maskable',
					),
					array(
						'src'     => $icons . 'icon-512.png',
						'sizes'   => '512x512',
						'type'    => 'image/png',
						'purpose' => 'any maskable',
					),
				),
			)
		);
		exit;
	}

	/**
	 * Data the app starts with.
	 *
	 * @return array
	 */
	private static function boot() {
		$user        = wp_get_current_user();
		$collections = array();
		$default     = (int) get_option( 'default_category' );
		foreach ( get_categories( array( 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC' ) ) as $term ) {
			if ( $default === (int) $term->term_id && 0 === (int) $term->count ) {
				continue; // An empty "Uncategorized" isn't a collection yet.
			}
			$name = ( $default === (int) $term->term_id && in_array( strtolower( $term->name ), array( 'uncategorized', 'uncategorised' ), true ) ) ? __( 'Photos', 'stubli' ) : $term->name;
			$collections[] = $name;
		}
		return array(
			'rest'        => esc_url_raw( rest_url( REST_NS . '/' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'version'     => VERSION,
			'demo'        => Demo::on() ? Demo::words() : null,
			'ajax'        => admin_url( 'admin-ajax.php' ),
			'home'        => home_url( '/' ),
			'journal'     => home_url( '/journal/' ),
			'name'        => self::first_name( $user ),
			'settings'    => admin_url( 'options-general.php?page=stubli' ),
			'dashboard'   => admin_url(),
			'collections' => array_values( array_unique( $collections ) ),
			'collOrder'   => array_column( Arrange::collections(), 'name' ),
			'maxPhotos'   => Posts::MAX_PHOTOS,
			'maxCaption'  => Posts::MAX_CAPTION_LENGTH,
			'maxNote'     => Posts::MAX_NOTE_LENGTH,
			'friends'     => count( (array) get_option( 'stubli_friends', array() ) ),
			'requests'    => Notes::waiting(),
			'maxJournal'  => 5000,
			'today'       => wp_date( 'l, j F' ),
			'waiting'     => self::waiting(),
			'setupDone'   => (bool) get_option( 'stubli_setup_done' ),
			'firstDay'    => self::first_day(),
			'logo'        => Seasonal::logo(),
			'maxUpload'   => (int) wp_max_upload_size(),
			'videoTypes'  => Posts::video_types_allowed(),
			'noVideo'     => Posts::no_video_message(),
			'sw'          => home_url( '/app/notifications' ),
			'swScope'     => home_url( '/app/' ),
			'maxPost'     => (int) wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) ),
			'mode'        => Setup::mode(),
			'hour'        => (int) wp_date( 'G' ),
		);
	}

	/**
	 * How many comments and guest book entries wait for an OK (not set aside).
	 *
	 * @return int
	 */
	private static function waiting() {
		$n = 0;
		foreach ( get_comments( array( 'status' => 'hold', 'number' => 100, 'fields' => 'ids' ) ) as $id ) {
			if ( ! get_comment_meta( $id, 'stubli_later', true ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * A name to greet the owner with: their first name, or a display name
	 * that looks like a real name (not a username like "celestinetan").
	 *
	 * @param \WP_User $user User.
	 * @return string
	 */
	private static function first_name( $user ) {
		if ( '' !== trim( (string) $user->first_name ) ) {
			return trim( (string) $user->first_name );
		}
		$d = trim( (string) $user->display_name );
		if ( '' === $d || strtolower( $d ) === strtolower( (string) $user->user_login ) || strtolower( $d ) === strtolower( (string) $user->user_nicename ) ) {
			// Fall back to the name on your page ("Ana Keller" → "Ana").
			$d = trim( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
			if ( '' === $d || preg_match( '/^(your|my|the)\b/i', $d ) || preg_match( '/^(stubli|wordpress|my blog|site title)$/i', $d ) ) {
				return '';
			}
		}
		if ( preg_match( '/\s/', $d ) ) {
			return (string) strtok( $d, ' ' );
		}
		return preg_match( '/^\p{Lu}/u', $d ) ? $d : '';
	}

	/**
	 * Font faces from the Stubli theme, when it's active.
	 *
	 * @return string
	 */
	private static function fonts() {
		return function_exists( 'stubli_font_faces' ) ? stubli_font_faces() : '';
	}

	/**
	 * The app page.
	 */
	private static function render( $act = false ) {
		$assets = STUBLI_PLUGIN_URL . 'assets/app/';
		// The app is its own page, outside the theme, but its files still go
		// through WordPress's script and style registry.
		wp_register_style( 'stubli-app', $assets . 'app.css', array(), VERSION );
		wp_add_inline_style( 'stubli-app', wp_strip_all_tags( self::fonts() . Setup::accent_css() ) );
		wp_register_script( 'stubli-app', $assets . 'app.js', array( 'wp-i18n' ), VERSION, true );
		// The app's words in the site's language: from WordPress.org's language
		// packs, or from the plugin's own languages folder.
		wp_set_script_translations( 'stubli-app', 'stubli', STUBLI_PLUGIN_DIR . 'languages' );
		wp_add_inline_script( 'stubli-app', 'window.STUBLI = ' . wp_json_encode( self::boot(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>" data-mode="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#fbfaf8" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#1c1916" media="(prefers-color-scheme: dark)">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Stubli">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title><?php esc_html_e( 'Stubli', 'stubli' ); ?></title>
<link rel="manifest" href="<?php echo esc_url( home_url( '/app/manifest.webmanifest' ) ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( $assets . 'icon-180.png' ); ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?php echo esc_url( $assets . 'icon-192.png' ); ?>">
<?php wp_print_inline_script_tag( "(function(){try{var p=localStorage.getItem('stubli-mode')||" . wp_json_encode( Setup::mode() ) . ";var m=p==='auto'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):p;document.documentElement.setAttribute('data-mode',m);}catch(e){}})();" ); ?>
<?php wp_print_styles( array( 'stubli-app' ) ); ?>
</head>
<body>
<?php if ( $act ) : ?>
<div class="a-app"><header class="a-head"><a class="a-text-btn" href="<?php echo esc_url( home_url( '/app/' ) ); ?>"><?php esc_html_e( 'Close', 'stubli' ); ?></a><span></span><span></span></header><main id="main"><?php Act::body( true ); ?></main></div>
<?php else : ?>
<div id="app" class="a-app"><noscript><p class="a-pad"><?php esc_html_e( 'The Stubli app needs JavaScript.', 'stubli' ); ?></p></noscript></div>
<?php endif; ?>
<?php
if ( ! $act ) {
	wp_print_scripts( array( 'stubli-app' ) );
}
?>
</body>
</html>
		<?php
	}

	/**
	 * Has the owner posted any photos yet?
	 *
	 * @return bool
	 */
	public static function has_photos() {
		$q = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'post_format',
						'field'    => 'slug',
						'terms'    => array( 'post-format-image', 'post-format-gallery' ),
					),
				),
			)
		);
		return (bool) $q->posts;
	}

	/**
	 * The "Your first day" card: null when it's off, hidden or a month old.
	 *
	 * @return array|null
	 */
	public static function first_day() {
		$since = get_option( 'stubli_firstday', false );
		if ( ! is_numeric( $since ) || time() - (int) $since > 30 * DAY_IN_SECONDS ) {
			return null;
		}
		return array(
			'photo'   => self::has_photos(),
			'invited' => (bool) get_option( 'stubli_invited' ),
		);
	}
}
