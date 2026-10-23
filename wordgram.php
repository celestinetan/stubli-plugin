<?php
/**
 * Plugin Name:       Stubli
 * Description:       Turns a WordPress site into a cosy, familiar photo-sharing home: a public profile with collections, a journal and a guest book, a private reader for the Stubli sites you follow, and comments between sites via Webmention.
 * Version:           0.31.3
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Celestine Tan
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       stubli
 * Domain Path:       /languages
 *
 * Code prefix: namespace Stubli, stubli_ for stored names. Sites before 0.17
 * used "og_"; Install::migrate_prefix() moves that data once.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

const VERSION        = '0.31.3';
const SCHEMA_VERSION = 5;
const REST_NS        = 'stubli/v1';
/** Every route also answers here, and calls to other sites use it, so sites on older versions keep understanding each other. */
const LEGACY_NS      = 'og/v1';

define( 'STUBLI_PLUGIN_FILE', __FILE__ );
define( 'STUBLI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STUBLI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once STUBLI_PLUGIN_DIR . 'includes/class-install.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-settings.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-posts.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-journal.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-guestbook.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-follows.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-sync.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-replies.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-moderation.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-privacy.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-rest.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-compat.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-admin.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-act.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-app.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-invites.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-look.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-feedback.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-hostcheck.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-discover.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-setup.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-mine.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-digest.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-helper.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-bundle.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-archive.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-move.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-friends.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-prompts.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-notes.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-avatar.php';
require_once STUBLI_PLUGIN_DIR . 'includes/class-arrange.php';

register_activation_hook( __FILE__, array( Install::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Install::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		Posts::init();
		Journal::init();
		Guestbook::init();
		Sync::init();
		Replies::init();
		Moderation::init();
		Privacy::init();
		Rest::init();
		Compat::init();
		Admin::init();
		Act::init();
		App::init();
		Look::init();
		Feedback::init();
		HostCheck::init();
		Discover::init();
		Setup::init();
		Helper::init();
		Archive::init();
		Move::init();
		Friends::init();
		Prompts::init();
		Notes::init();
		Mine::init();
		Digest::init();
		Avatar::init();
		Arrange::init();
	}
);

// Upgrades may create the guest book page, which needs rewrite rules ready.
add_action( 'plugins_loaded', array( Install::class, 'migrate_prefix' ), 1 );
add_action( 'init', array( Install::class, 'maybe_upgrade' ), 20 );
