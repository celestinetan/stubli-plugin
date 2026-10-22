<?php
/**
 * Activation, schema, safe defaults and scheduled jobs.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Tables:
 * - og_follows: the sites this site follows (plus their public Following
 *   lists, cached, for friends-of-friends approval).
 * - og_items:   posts fetched from those sites (the reader's local copy).
 */
class Install {

	const CRON_HOOK = 'stubli_poll_follows';

	/**
	 * @return string
	 */
	public static function follows_table() {
		global $wpdb;
		return $wpdb->prefix . 'stubli_follows';
	}

	/**
	 * @return string
	 */
	public static function items_table() {
		global $wpdb;
		return $wpdb->prefix . 'stubli_items';
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		self::apply_safe_defaults();
		Replies::register_post_type();
		Journal::rewrite();
		Guestbook::ensure_page();
		flush_rewrite_rules();
		Setup::flag_open();

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'stubli_fifteen_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Plugin deactivation: stop polling, keep data.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		flush_rewrite_rules();
	}

	/** Stored names from before 0.17 (prefix og_), moved once by migrate_prefix(). Only these: other plugins use og_ too. */
	const LEGACY_OPTIONS = array( 'accent', 'bio', 'block_ai', 'blocked', 'break', 'defaults_applied', 'digest_email', 'digest_sent', 'digest_time', 'guestbook_page', 'invites', 'last_cron', 'links', 'look', 'mode', 'pronouns', 'push_subs', 'schema_version', 'setup_done', 'setup_open', 'setup_step', 'vapid', 'who_can_comment' );
	const LEGACY_POST_META    = array( 'caption', 'content_note', 'device', 'import_key', 'in_reply_to', 'item_id', 'kind', 'photos', 'reply_to', 'screenshot', 'source', 'version', 'video' );
	const LEGACY_COMMENT_META = array( 'hidden_by_block', 'later', 'pinned' );
	const LEGACY_POST_TYPES   = array( 'reply', 'feedback' );
	const LEGACY_TRANSIENTS   = array( 'disc_', 'fb_all', 'host_check_', 'invite_accepts', 'invite_proof_', 'reach_count', 'rate_', 'their_follows_' );

	/**
	 * Move a pre-0.17 site's data from og_ names to stubli_ names, once.
	 * Runs before anything else reads settings. Safe to call on every load.
	 */
	public static function migrate_prefix() {
		global $wpdb;
		if ( false !== get_option( 'stubli_schema_version' ) || false === get_option( 'og_schema_version' ) ) {
			return;
		}
		// One request at a time.
		$lock = (int) get_option( 'stubli_migrating', 0 );
		if ( $lock && time() - $lock < 300 ) {
			return;
		}
		update_option( 'stubli_migrating', time(), false );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders -- one-time rename of this plugin's own rows and tables.
		foreach ( self::LEGACY_OPTIONS as $name ) {
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", 'stubli_' . $name ) ) ) {
				$wpdb->delete( $wpdb->options, array( 'option_name' => 'og_' . $name ) );
			} else {
				$wpdb->update( $wpdb->options, array( 'option_name' => 'stubli_' . $name ), array( 'option_name' => 'og_' . $name ) );
			}
		}
		foreach ( self::LEGACY_POST_META as $key ) {
			$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_stubli_' . $key ), array( 'meta_key' => '_og_' . $key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		foreach ( self::LEGACY_COMMENT_META as $key ) {
			$wpdb->update( $wpdb->commentmeta, array( 'meta_key' => 'stubli_' . $key ), array( 'meta_key' => 'og_' . $key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		foreach ( self::LEGACY_POST_TYPES as $type ) {
			$wpdb->update( $wpdb->posts, array( 'post_type' => 'stubli_' . $type ), array( 'post_type' => 'og_' . $type ) );
		}
		foreach ( self::LEGACY_TRANSIENTS as $t ) {
			$like = $wpdb->esc_like( '_transient_og_' . $t ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $wpdb->esc_like( '_transient_timeout_og_' . $t ) . '%' ) );
		}
		// phpcs:enable
		wp_clear_scheduled_hook( 'og_poll_follows' );
		wp_cache_flush();
		update_option( 'stubli_flush_rules', 1, false );
		delete_option( 'stubli_migrating' );
	}

	/**
	 * Copy rows from pre-0.17 tables (og_follows, og_items) into the new
	 * ones, then remove the old tables once the counts match. Plain SQL that
	 * works on MySQL, MariaDB and SQLite.
	 */
	public static function migrate_tables() {
		global $wpdb;
		if ( get_option( 'stubli_tables_migrated' ) ) {
			return;
		}
		$done = true;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own tables.
		foreach ( array( 'items', 'follows' ) as $t ) {
			$old = $wpdb->prefix . 'og_' . $t;
			$new = $wpdb->prefix . 'stubli_' . $t;
			if ( $old !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old ) ) ) ) {
				continue;
			}
			$from = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$old}`" );
			if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$new}`" ) && $from > 0 ) {
				$wpdb->query( "INSERT INTO `{$new}` SELECT * FROM `{$old}`" );
			}
			if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$new}`" ) >= $from ) {
				$wpdb->query( "DROP TABLE IF EXISTS `{$old}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			} else {
				$done = false; // Keep the old table and try again on the next load.
			}
		}
		// phpcs:enable
		if ( $done ) {
			update_option( 'stubli_tables_migrated', 1, false );
		}
	}

	/**
	 * Run schema upgrades when the stored version is behind.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( 'stubli_schema_version', 0 ) < SCHEMA_VERSION ) {
			self::create_tables();
			Guestbook::ensure_page();
		}
		self::migrate_tables();
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'stubli_fifteen_minutes', self::CRON_HOOK );
		}
		if ( get_option( 'stubli_flush_rules' ) ) {
			delete_option( 'stubli_flush_rules' );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Privacy and bot defaults for a new Stubli site (design §16, Q26).
	 * Applied once, so an owner who later changes them keeps their choice.
	 */
	public static function apply_safe_defaults() {
		if ( get_option( 'stubli_defaults_applied' ) ) {
			return;
		}
		// Site-wide WordPress settings (search engines, comments) are only changed
		// when the owner confirms them at the end of the setup. See Setup::save().
		add_option( 'stubli_block_ai', 1 );
		add_option( 'stubli_break', 0 );
		add_option( 'stubli_blocked', array() );
		add_option( 'stubli_look', 1 );                 // New sites get the Stubli look straight away.
		update_option( 'stubli_defaults_applied', 1, false );
	}

	/**
	 * Create or update tables with dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$follows = self::follows_table();
		$items   = self::items_table();

		dbDelta(
			"CREATE TABLE {$follows} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				site_url varchar(255) NOT NULL,
				feed_url varchar(255) NOT NULL,
				name varchar(190) NOT NULL DEFAULT '',
				avatar_url varchar(255) NOT NULL DEFAULT '',
				list_name varchar(100) NOT NULL DEFAULT '',
				note varchar(200) NOT NULL DEFAULT '',
				is_public tinyint(1) NOT NULL DEFAULT 1,
				muted tinyint(1) NOT NULL DEFAULT 0,
				their_follows longtext NULL,
				created datetime NOT NULL,
				last_fetched datetime NULL,
				last_error text NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY site_url (site_url(191))
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$items} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				follow_id bigint(20) unsigned NOT NULL,
				guid varchar(255) NOT NULL,
				permalink varchar(255) NOT NULL,
				caption text NOT NULL,
				image_url varchar(255) NOT NULL DEFAULT '',
				images longtext NULL,
				content_note varchar(120) NOT NULL DEFAULT '',
				published datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY follow_guid (follow_id,guid(150)),
				KEY published (published)
			) {$charset};"
		);

		$before = get_option( 'stubli_schema_version' );
		if ( false !== $before && (int) $before < 4 ) {
			// This site ran Stubli before 0.17: keep answering at og/v1 for friends who haven't updated.
			update_option( 'stubli_legacy_api', 1 );
		}
		update_option( 'stubli_schema_version', SCHEMA_VERSION, false );
	}
}

add_filter(
	'cron_schedules',
	static function ( $schedules ) {
		$schedules['stubli_fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes', 'stubli' ),
		);
		return $schedules;
	}
);
