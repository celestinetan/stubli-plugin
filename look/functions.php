<?php
/**
 * Paths for the look when it runs from the plugin.
 *
 * @package Stubli
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return string
 */
function stubli_look_dir() {
	return untrailingslashit( STUBLI_PLUGIN_DIR . 'look' );
}

/**
 * @return string
 */
function stubli_look_uri() {
	return untrailingslashit( STUBLI_PLUGIN_URL . 'look' );
}
