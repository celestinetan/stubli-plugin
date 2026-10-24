<?php
/**
 * Uninstall: remove Stubli's own tables, settings and comment pages. The
 * site's posts, journal, guest book page and photos are standard WordPress
 * content and are deliberately kept. Covers names from before 0.17 (og_) too.
 *
 * @package Stubli
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

foreach ( array( 'stubli_', 'og_' ) as $stubli_prefix ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$stubli_prefix}items" );   // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$stubli_prefix}follows" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	foreach ( array( 'accent', 'bio', 'block_ai', 'blocked', 'break', 'defaults_applied', 'digest_email', 'digest_sent', 'digest_time', 'guestbook_page', 'invites', 'last_cron', 'links', 'look', 'mode', 'pronouns', 'push_subs', 'schema_version', 'setup_done', 'setup_open', 'setup_step', 'vapid', 'who_can_comment', 'migrating', 'flush_rules', 'tables_migrated', 'credit', 'legacy_api', 'archive', 'move_out', 'move_in', 'moved_to', 'moved_from', 'friends', 'friend_keys', 'prompts', 'note_blocked', 'avatar', 'cover', 'coll_order', 'pins', 'firstday', 'invited', 'seasonal', 'demo' ) as $stubli_option ) {
		delete_option( $stubli_prefix . $stubli_option );
	}
	wp_clear_scheduled_hook( $stubli_prefix . 'poll_follows' );
	wp_clear_scheduled_hook( $stubli_prefix . 'purge_removed' );
	$stubli_replies = get_posts(
		array(
			'post_type'   => array( $stubli_prefix . 'reply', $stubli_prefix . 'like', $stubli_prefix . 'note' ),
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $stubli_replies as $stubli_reply ) {
		wp_delete_post( $stubli_reply, true );
	}
}

// Archives made with "Download everything".
$stubli_up = wp_upload_dir( null, false );
foreach ( (array) glob( trailingslashit( $stubli_up['basedir'] ) . 'stubli-export/*' ) as $stubli_file ) {
	wp_delete_file( $stubli_file );
}
