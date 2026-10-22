<?php
/**
 * Who may comment and sign, quiet blocking, taking a break, spam limits and
 * the "Waiting for your OK" queue (Q10, Q26, Q28).
 *
 * @package Stubli
 */

namespace Stubli;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Rules for incoming comments and guest book entries (all arrive as
 * Webmentions from the writer's own site):
 *
 * - From a blocked site: quietly dropped. The sender is told "scheduled",
 *   as with any Webmention, and nothing more.
 * - While the owner is taking a break: politely refused, so the writer's
 *   site can say so. Nothing is deleted.
 * - From a site you follow: shown straight away.
 * - From a friend of a friend (followed by someone you follow): shown
 *   straight away, unless it contains a link.
 * - Everyone else, and anything with a link from outside your circle:
 *   waits for your OK.
 * - Likes, reposts and bookmarks from other IndieWeb sites are not stored:
 *   Stubli has no likes.
 *
 * Nobody can comment through the site's own comment form except the
 * owner (replying on their own posts), so there are no anonymous comments
 * and nothing for bots to fill in.
 */
class Moderation {

	const PER_SITE_PER_HOUR = 10;
	const ALL_PER_HOUR      = 60;
	const WEBMENTION_ROUTE  = '/webmention/1.0/endpoint';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'rest_pre_dispatch', array( self::class, 'gate_webmentions' ), 10, 3 );
		add_filter( 'webmention_comment_data', array( self::class, 'drop_unsupported' ), 99 );
		add_filter( 'pre_comment_approved', array( self::class, 'approval' ), 99, 2 );
		add_action( 'pre_comment_on_post', array( self::class, 'owner_only_form' ) );
		add_filter( 'comments_open', array( self::class, 'comments_open' ), 20, 2 );
	}

	/**
	 * Check an incoming Webmention before it's processed.
	 *
	 * @param mixed           $result  Response to short-circuit with, or null.
	 * @param mixed           $server  REST server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function gate_webmentions( $result, $server, $request ) {
		if ( null !== $result || ! $request instanceof WP_REST_Request || self::WEBMENTION_ROUTE !== $request->get_route() || 'POST' !== $request->get_method() ) {
			return $result;
		}
		$source = (string) $request->get_param( 'source' );
		$origin = Follows::origin( $source );

		if ( $origin && self::is_blocked( $source ) ) {
			// Quiet block: look like any accepted Webmention, store nothing.
			return new WP_REST_Response(
				array(
					'source'  => $source,
					'target'  => (string) $request->get_param( 'target' ),
					'code'    => 'scheduled',
					'message' => 'Webmention is scheduled',
				),
				202
			);
		}

		if ( Settings::on_break() ) {
			return new WP_Error(
				'stubli_on_break',
				sprintf( /* translators: %s: site name */ __( '%s is taking a break from comments. Nothing you wrote is lost; try again another time.', 'stubli' ), get_bloginfo( 'name' ) ),
				array( 'status' => 403 )
			);
		}

		if ( $origin && ! Follows::is_followed( $source ) ) {
			$site_key = 'stubli_rate_' . md5( $origin );
			$all_key  = 'stubli_rate_all';
			$site     = (int) get_transient( $site_key );
			$all      = (int) get_transient( $all_key );
			if ( $site >= self::PER_SITE_PER_HOUR || $all >= self::ALL_PER_HOUR ) {
				return new WP_Error( 'stubli_slow_down', __( 'Lots of notes are arriving at once. Please try again in an hour.', 'stubli' ), array( 'status' => 429 ) );
			}
			set_transient( $site_key, $site + 1, HOUR_IN_SECONDS );
			set_transient( $all_key, $all + 1, HOUR_IN_SECONDS );
		}

		return $result;
	}

	/**
	 * Don't store likes, reposts and other reactions; Stubli only has words.
	 *
	 * @param array|WP_Error|null $commentdata Parsed Webmention.
	 * @return array|WP_Error|null
	 */
	public static function drop_unsupported( $commentdata ) {
		if ( ! is_array( $commentdata ) ) {
			return $commentdata;
		}
		$type = $commentdata['comment_type'] ?? '';
		if ( in_array( $type, array( 'like', 'repost', 'favorite', 'bookmark', 'tag', 'read', 'listen', 'watch', 'rsvp' ), true ) ) {
			return new WP_Error( 'stubli_words_only', __( 'Stubli only keeps comments, not reactions.', 'stubli' ), array( 'status' => 200 ) );
		}
		return $commentdata;
	}

	/**
	 * Decide whether a new comment shows straight away.
	 *
	 * @param int|string|WP_Error $approved    Current decision.
	 * @param array               $commentdata Comment data.
	 * @return int|string|WP_Error
	 */
	public static function approval( $approved, $commentdata ) {
		if ( is_wp_error( $approved ) || 'spam' === $approved || 'trash' === $approved ) {
			return $approved;
		}
		$protocol = $commentdata['comment_meta']['protocol'] ?? '';
		if ( 'webmention' !== $protocol ) {
			return $approved; // The owner's own replies on their site.
		}
		$source = (string) ( $commentdata['comment_author_url'] ?? ( $commentdata['source'] ?? '' ) );
		if ( ! empty( $commentdata['comment_meta']['webmention_source_url'] ) ) {
			$source = (string) $commentdata['comment_meta']['webmention_source_url'];
		}
		if ( self::is_blocked( $source ) ) {
			return 'trash';
		}
		if ( Follows::is_followed( $source ) ) {
			return 1;
		}
		$has_link = (bool) preg_match( '#https?://|<a\s|www\.#i', (string) ( $commentdata['comment_content'] ?? '' ) );
		if ( 'friends_of_friends' === get_option( 'stubli_who_can_comment', 'friends_of_friends' ) && ! $has_link && Follows::is_friend_of_friend( $source ) ) {
			return 1;
		}
		return 0;
	}

	/**
	 * The built-in comment form is for the owner only.
	 *
	 * @param int $post_id Post being commented on.
	 */
	public static function owner_only_form( $post_id ) {
		if ( current_user_can( 'moderate_comments' ) ) {
			return;
		}
		wp_die(
			esc_html__( 'Comments on Stubli come from your own Stubli site. Open this post on your Stubli and write your comment there.', 'stubli' ),
			esc_html__( 'Comment from your own Stubli', 'stubli' ),
			array(
				'response'  => 403,
				'back_link' => true,
			)
		);
	}

	/**
	 * Close comments everywhere while the owner takes a break.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public static function comments_open( $open, $post_id ) {
		return Settings::on_break() ? false : $open;
	}

	/**
	 * Blocked site origins.
	 *
	 * @return string[]
	 */
	public static function blocked() {
		return array_values( array_filter( (array) get_option( 'stubli_blocked', array() ), 'is_string' ) );
	}

	/**
	 * @param string $url Any URL.
	 * @return bool
	 */
	public static function is_blocked( $url ) {
		$origin = Follows::origin( $url );
		return $origin && in_array( $origin, self::blocked(), true );
	}

	/**
	 * Quietly block a site: its comments and guest book entries disappear
	 * (kept in the bin in case you change your mind) and nothing new from it
	 * is stored. They are never told.
	 *
	 * @param string $url Any URL on the site.
	 * @return array|WP_Error
	 */
	public static function block( $url ) {
		$origin = Follows::origin( $url );
		if ( ! $origin || Follows::origin( home_url() ) === $origin ) {
			return new WP_Error( 'stubli_bad_url', __( 'That doesn\'t look like another site\'s address.', 'stubli' ), array( 'status' => 400 ) );
		}
		$list = self::blocked();
		if ( ! in_array( $origin, $list, true ) ) {
			$list[] = $origin;
			update_option( 'stubli_blocked', $list, false );
		}
		$hidden = 0;
		foreach ( self::comments_from( $origin, array( 'approve', 'hold' ) ) as $comment ) {
			update_comment_meta( $comment->comment_ID, 'stubli_hidden_by_block', wp_get_comment_status( $comment ) );
			wp_set_comment_status( $comment, 'trash' );
			++$hidden;
		}
		return array(
			'blocked' => $origin,
			'hidden'  => $hidden,
		);
	}

	/**
	 * Undo a block and bring back what it hid.
	 *
	 * @param string $url Any URL on the site.
	 * @return array
	 */
	public static function unblock( $url ) {
		$origin = Follows::origin( $url );
		update_option( 'stubli_blocked', array_values( array_diff( self::blocked(), array( $origin ) ) ), false );
		$restored = 0;
		foreach ( self::comments_from( $origin, array( 'trash' ) ) as $comment ) {
			$before = get_comment_meta( $comment->comment_ID, 'stubli_hidden_by_block', true );
			if ( ! $before ) {
				continue;
			}
			wp_set_comment_status( $comment, 'approved' === $before ? 'approve' : 'hold' );
			delete_comment_meta( $comment->comment_ID, 'stubli_hidden_by_block' );
			++$restored;
		}
		return array(
			'unblocked' => $origin,
			'restored'  => $restored,
		);
	}

	/**
	 * Comments whose author site is the given origin.
	 *
	 * @param string   $origin   scheme://host[:port].
	 * @param string[] $statuses Comment statuses.
	 * @return \WP_Comment[]
	 */
	private static function comments_from( $origin, array $statuses ) {
		$out = array();
		foreach ( $statuses as $status ) {
			foreach ( get_comments( array( 'status' => $status, 'number' => 500 ) ) as $comment ) {
				$url = (string) get_comment_meta( $comment->comment_ID, 'webmention_source_url', true );
				if ( ! $url ) {
					$url = $comment->comment_author_url;
				}
				if ( Follows::origin( $url ) === $origin ) {
					$out[] = $comment;
				}
			}
		}
		return $out;
	}

	/**
	 * A comment as visitors and the app see it.
	 *
	 * @param \WP_Comment $comment Comment.
	 * @return array
	 */
	public static function public_comment( $comment ) {
		return array(
			'id'     => (int) $comment->comment_ID,
			'parent' => (int) $comment->comment_parent,
			'author' => array(
				'name'   => $comment->comment_author,
				'url'    => $comment->comment_author_url ? Follows::origin( $comment->comment_author_url ) : home_url(),
				'avatar' => get_avatar_url( $comment ),
				'owner'  => (int) $comment->user_id > 0 && user_can( (int) $comment->user_id, 'manage_options' ),
			),
			'text'   => wp_strip_all_tags( $comment->comment_content ),
			'date'   => gmdate( 'c', (int) strtotime( $comment->comment_date_gmt . ' UTC' ) ),
			'pinned' => (bool) get_comment_meta( $comment->comment_ID, 'stubli_pinned', true ),
		);
	}

	/**
	 * Approved comments on one of your posts.
	 *
	 * @param int $post_id Post ID.
	 * @return array[]
	 */
	public static function for_post( $post_id ) {
		$comments = get_comments(
			array(
				'post_id' => (int) $post_id,
				'status'  => 'approve',
				'orderby' => 'comment_date_gmt',
				'order'   => 'ASC',
			)
		);
		return array_map( array( self::class, 'public_comment' ), $comments );
	}

	/**
	 * "Waiting for your OK": comments and guest book entries on hold.
	 *
	 * @return array[]
	 */
	public static function queue() {
		$guestbook = Guestbook::page_id();
		$out       = array();
		foreach ( get_comments( array( 'status' => 'hold', 'number' => 100, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ) ) as $comment ) {
			$item          = self::public_comment( $comment );
			$item['where'] = (int) $comment->comment_post_ID === $guestbook ? 'guestbook' : 'post';
			$item['post']  = array(
				'id'    => (int) $comment->comment_post_ID,
				'title' => get_the_title( (int) $comment->comment_post_ID ),
				'url'   => get_permalink( (int) $comment->comment_post_ID ),
			);
			$item['later'] = (bool) get_comment_meta( $comment->comment_ID, 'stubli_later', true );
			$item['via']   = self::introduced_by( $comment->comment_author_url );
			$out[]         = $item;
		}
		return $out;
	}

	/**
	 * Which of the people you follow also follows this site?
	 *
	 * @param string $url Author URL.
	 * @return string Name, or ''.
	 */
	private static function introduced_by( $url ) {
		$origin = Follows::origin( (string) $url );
		foreach ( Follows::all() as $follow ) {
			$theirs = json_decode( (string) $follow['their_follows'], true );
			if ( $origin && is_array( $theirs ) && in_array( $origin, $theirs, true ) ) {
				return $follow['name'];
			}
		}
		return '';
	}

	/**
	 * Act on a waiting comment: "Show it", "Not now" or "Block this site".
	 *
	 * @param int    $comment_id Comment ID.
	 * @param string $action     show | later | block.
	 * @return array|WP_Error
	 */
	public static function act( $comment_id, $action ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return new WP_Error( 'stubli_not_found', __( 'That note is already gone.', 'stubli' ), array( 'status' => 404 ) );
		}
		switch ( $action ) {
			case 'show':
				wp_set_comment_status( $comment, 'approve' );
				delete_comment_meta( $comment->comment_ID, 'stubli_later' );
				return array( 'status' => 'shown' );
			case 'later':
				update_comment_meta( $comment->comment_ID, 'stubli_later', 1 );
				return array( 'status' => 'later' );
			case 'block':
				$url = (string) get_comment_meta( $comment->comment_ID, 'webmention_source_url', true );
				$res = self::block( $url ? $url : $comment->comment_author_url );
				return is_wp_error( $res ) ? $res : array( 'status' => 'blocked' ) + $res;
		}
		return new WP_Error( 'stubli_bad_action', __( 'Choose Show it, Not now or Block this site.', 'stubli' ), array( 'status' => 400 ) );
	}
}
