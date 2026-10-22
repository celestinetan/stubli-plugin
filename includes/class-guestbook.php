<?php
/**
 * The guest book (Q27): a page that receives notes from visitors' own sites.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Visitors sign by writing a reply to the guest book page on their own
 * Stubli; it arrives as a Webmention, like a comment, and follows the same
 * approval rules. The owner can pin entries.
 */
class Guestbook {

	const OPTION = 'stubli_guestbook_page';

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Nothing to hook yet: the page is created on activation and upgrade.
	}

	/**
	 * Create the guest book page if it doesn't exist.
	 *
	 * @return int Page ID.
	 */
	public static function ensure_page() {
		$id = (int) get_option( self::OPTION, 0 );
		if ( $id && get_post( $id ) ) {
			return $id;
		}
		$existing = get_page_by_path( 'guest-book' );
		if ( $existing ) {
			update_option( self::OPTION, (int) $existing->ID );
			return (int) $existing->ID;
		}
		$id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => __( 'Guest book', 'stubli' ),
				'post_name'      => 'guest-book',
				'post_content'   => '<p>' . esc_html__( 'Thanks for stopping by. Say hello, leave a tip, or tell me where you\'re reading from.', 'stubli' ) . '</p>',
				'comment_status' => 'open',
				'ping_status'    => 'open',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( self::OPTION, (int) $id );
			return (int) $id;
		}
		return 0;
	}

	/**
	 * @return int
	 */
	public static function page_id() {
		return (int) get_option( self::OPTION, 0 );
	}

	/**
	 * @return string
	 */
	public static function url() {
		$id = self::page_id();
		return $id ? (string) get_permalink( $id ) : '';
	}

	/**
	 * Approved guest book entries, pinned first, then newest.
	 *
	 * @return array[]
	 */
	public static function entries() {
		$id = self::page_id();
		if ( ! $id ) {
			return array();
		}
		$comments = get_comments(
			array(
				'post_id' => $id,
				'status'  => 'approve',
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			)
		);
		$out = array_map( array( Moderation::class, 'public_comment' ), $comments );
		usort(
			$out,
			static function ( $a, $b ) {
				return (int) $b['pinned'] - (int) $a['pinned'];
			}
		);
		return $out;
	}

	/**
	 * Pin or unpin an entry.
	 *
	 * @param int  $comment_id Comment ID.
	 * @param bool $pinned     Pin state.
	 * @return bool
	 */
	public static function pin( $comment_id, $pinned ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment || (int) $comment->comment_post_ID !== self::page_id() ) {
			return false;
		}
		if ( $pinned ) {
			update_comment_meta( $comment->comment_ID, 'stubli_pinned', 1 );
		} else {
			delete_comment_meta( $comment->comment_ID, 'stubli_pinned' );
		}
		return true;
	}
}
