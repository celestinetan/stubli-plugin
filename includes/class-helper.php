<?php
/**
 * The Webmention plugin, which carries comments and guest book entries
 * between sites. Stubli switches on without it, and the owner adds it with
 * one tap at the end of the setup (or in Settings → Stubli). It always comes
 * from the WordPress.org plugin directory.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * "The comments helper".
 */
class Helper {

	const SLUG = 'webmention';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_stubli_add_webmention', array( self::class, 'handle_admin' ) );
	}

	/**
	 * Is Webmention running?
	 *
	 * @return bool
	 */
	public static function active() {
		return function_exists( 'send_webmention' );
	}

	/**
	 * Installed plugin file (webmention/webmention.php), if it's there.
	 *
	 * @return string
	 */
	public static function file() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( 0 === strpos( $file, self::SLUG . '/' ) ) {
				return $file;
			}
		}
		return '';
	}

	/**
	 * 'active', 'installed' (but switched off) or 'missing'.
	 *
	 * @return string
	 */
	public static function state() {
		if ( self::active() ) {
			return 'active';
		}
		return self::file() ? 'installed' : 'missing';
	}

	/**
	 * Where to add it by hand, if this site doesn't let Stubli do it.
	 *
	 * @return string
	 */
	public static function manual_url() {
		return self::file() ? admin_url( 'plugins.php' ) : admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . self::SLUG );
	}

	/**
	 * Install Webmention from WordPress.org if needed, and switch it on.
	 *
	 * @return true|\WP_Error
	 */
	public static function add() {
		if ( self::active() ) {
			return true;
		}
		$help = array(
			'status' => 500,
			'manual' => self::manual_url(),
		);
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new \WP_Error( 'stubli_helper_cap', __( 'Only the site owner can add plugins.', 'stubli' ), array( 'status' => 403 ) + $help );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$file = self::file();
		if ( ! $file ) {
			if ( ! current_user_can( 'install_plugins' ) ) {
				return new \WP_Error( 'stubli_helper_cap', __( 'This site doesn\'t let you add plugins. Ask your host whether your plan allows it.', 'stubli' ), array( 'status' => 403 ) + $help );
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => self::SLUG,
					'fields' => array( 'sections' => false ),
				)
			);
			if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
				return new \WP_Error( 'stubli_helper_offline', __( 'WordPress.org couldn\'t be reached just now. Try again in a moment.', 'stubli' ), $help );
			}
			if ( ! WP_Filesystem() ) {
				return new \WP_Error( 'stubli_helper_fs', __( 'This host asks for FTP details before adding plugins, so Stubli can\'t do it for you. Add Webmention under Plugins → Add New.', 'stubli' ), $help );
			}
			$skin     = new \WP_Ajax_Upgrader_Skin();
			$upgrader = new \Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $api->download_link );
			if ( is_wp_error( $result ) || ! $result || $skin->get_errors()->has_errors() ) {
				return new \WP_Error( 'stubli_helper_install', __( 'Webmention couldn\'t be added just now. Try again, or add it under Plugins → Add New.', 'stubli' ), $help );
			}
			wp_clean_plugins_cache();
			$file = self::file();
		}
		if ( ! $file ) {
			return new \WP_Error( 'stubli_helper_install', __( 'Webmention couldn\'t be added just now. Try again, or add it under Plugins → Add New.', 'stubli' ), $help );
		}
		$on = activate_plugin( $file );
		if ( is_wp_error( $on ) ) {
			return new \WP_Error( 'stubli_helper_activate', __( 'Webmention is there but couldn\'t be switched on. Switch it on under Plugins.', 'stubli' ), $help );
		}
		return true;
	}

	/**
	 * The button in Settings → Stubli.
	 */
	public static function handle_admin() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'Only the site owner can add plugins.', 'stubli' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'stubli_add_webmention' );
		$res = self::add();
		wp_safe_redirect( add_query_arg( array( 'page' => Admin::PAGE, 'helper' => is_wp_error( $res ) ? $res->get_error_code() : 'ok' ), admin_url( 'options-general.php' ) ) );
		exit;
	}
}
