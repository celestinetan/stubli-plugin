<?php
/**
 * Comments you write on other people's posts and guest books (Q10).
 *
 * @package Stubli
 */

namespace Stubli;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stubli has comments, not likes. Commenting on someone else's post creates a
 * small public page on your own site (an h-entry with u-in-reply-to) and sends
 * a Webmention to their post. Their site fetches your page, checks it links
 * to their post, and shows your words as a comment, either straight away or
 * after their OK. Deleting the comment deletes the page and tells their site.
 */
class Replies {

	const POST_TYPE  = 'stubli_reply';
	const MAX_LENGTH = 1000;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register_post_type' ) );
		add_action( 'template_redirect', array( self::class, 'render_reply_page' ) );
		add_action( Install::CRON_HOOK, array( self::class, 'retry' ), 36 );
		// Replies you write in the WordPress dashboard travel too (0.45.9).
		add_action( 'comment_post', array( self::class, 'dashboard_reply' ), 20, 2 );
	}

	/**
	 * Comments that didn't reach the other site are sent again in the
	 * background: for a week when their site didn't answer, and for up to a
	 * month when they were taking a break. Gentle spacing: after 15 minutes,
	 * an hour, six hours, then once a day.
	 */
	public static function retry() {
		if ( ! Helper::active() ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'meta_key'       => '_stubli_retry_at', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => time(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_compare'   => '<=',
				'meta_type'      => 'NUMERIC',
			)
		);
		foreach ( $ids as $id ) {
			$target = (string) get_post_meta( $id, '_stubli_in_reply_to', true );
			$result = $target ? send_webmention( get_permalink( $id ), $target ) : null;
			self::schedule_retry( $id, self::delivered_status( $result ) );
		}
	}

	/**
	 * Remember when to try a comment again, or stop trying.
	 *
	 * @param int    $id     Reply page ID.
	 * @param string $status sent, paused or not_sent.
	 */
	private static function schedule_retry( $id, $status ) {
		$tries = (int) get_post_meta( $id, '_stubli_retries', true );
		$age   = time() - (int) get_post_time( 'U', true, $id );
		$limit = 'paused' === $status ? 30 * DAY_IN_SECONDS : WEEK_IN_SECONDS;
		if ( 'sent' === $status || $age > $limit ) {
			delete_post_meta( $id, '_stubli_retry_at' );
			delete_post_meta( $id, '_stubli_retries' );
			update_post_meta( $id, '_stubli_delivery', 'sent' === $status ? 'sent' : 'gave_up' );
			return;
		}
		$gaps = array( 15 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS, 6 * HOUR_IN_SECONDS );
		update_post_meta( $id, '_stubli_retries', $tries + 1 );
		update_post_meta( $id, '_stubli_retry_at', time() + ( $gaps[ $tries ] ?? DAY_IN_SECONDS ) );
		update_post_meta( $id, '_stubli_delivery', $status );
	}

	/**
	 * Reply pages: public so other sites can verify them, hidden from search,
	 * menus, archives and the site's own feed.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'Comments I wrote', 'stubli' ),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => array(
					'slug'       => 'replies',
					'with_front' => false,
				),
				// Webmentions: so someone can answer your comment from their own Stubli (0.45.9).
				'supports'            => array( 'editor', 'author', 'webmentions' ),
			)
		);
	}

	/**
	 * Render a reply page as a small, readable h-entry.
	 */
	public static function render_reply_page() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return;
		}
		$post   = get_queried_object();
		$target = (string) get_post_meta( $post->ID, '_stubli_in_reply_to', true );
		$name   = get_the_author_meta( 'display_name', (int) $post->post_author );
		if ( ! $name ) {
			$name = Posts::site_name();
		}

		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		// Where an answer to this comment can be delivered (0.45.9).
		$endpoint = function_exists( 'get_webmention_endpoint' ) ? (string) get_webmention_endpoint() : '';
		if ( $endpoint ) {
			header( 'Link: <' . esc_url_raw( $endpoint ) . '>; rel="webmention"', false );
		}
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head><meta charset="utf-8"><?php if ( $endpoint ) : ?><link rel="webmention" href="<?php echo esc_url( $endpoint ); ?>"><?php endif; ?><meta name="robots" content="noindex"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html( sprintf( /* translators: %s: author name */ __( 'A comment by %s', 'stubli' ), $name ) ); ?></title></head>
<body>
<article class="h-entry">
	<p class="p-author h-card"><img class="u-photo" src="<?php echo esc_url( Posts::profile()['avatar'] ); ?>" alt="" width="48" height="48"> <a class="u-url p-name" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $name ); ?></a></p>
	<p><?php esc_html_e( 'In reply to', 'stubli' ); ?> <a class="u-in-reply-to" href="<?php echo esc_url( $target ); ?>"><?php echo esc_html( $target ); ?></a></p>
	<div class="e-content p-name"><?php echo wp_kses( wpautop( $post->post_content ), array( 'p' => array(), 'br' => array(), 'a' => array( 'href' => true, 'rel' => true ) ) ); ?></div>
	<time class="dt-published" datetime="<?php echo esc_attr( get_post_time( 'c', true, $post ) ); ?>"><?php echo esc_html( get_the_date( '', $post ) ); ?></time>
	<a class="u-url" href="<?php echo esc_url( get_permalink( $post ) ); ?>"></a>
</article>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Comment on a post in your reader.
	 *
	 * @param int    $item_id Reader item ID.
	 * @param string $text    Comment text.
	 * @return array|WP_Error
	 */
	public static function comment_on_item( $item_id, $text ) {
		$item = Sync::get_item( $item_id );
		if ( ! $item ) {
			return new WP_Error( 'stubli_not_found', __( 'That post isn\'t in your feed.', 'stubli' ), array( 'status' => 404 ) );
		}
		return self::send( $item['permalink'], $text, (int) $item['id'] );
	}

	/**
	 * Comment on, or sign the guest book of, any Stubli page by address
	 * (the "Comment from your own Stubli" flow, H · Remote).
	 *
	 * @param string $url  The page you're replying to.
	 * @param string $text Comment text.
	 * @return array|WP_Error
	 */
	public static function comment_on_url( $url, $text ) {
		$url = esc_url_raw( trim( (string) $url ) );
		if ( ! $url || ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'stubli_bad_url', __( 'That doesn\'t look like a page address.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( Follows::origin( $url ) === Follows::origin( home_url() ) ) {
			return new WP_Error( 'stubli_self', __( 'That\'s your own site. Reply there directly.', 'stubli' ), array( 'status' => 400 ) );
		}
		return self::send( $url, $text, 0 );
	}

	/**
	 * Create the reply page and send the Webmention.
	 *
	 * @param string $target  Page being replied to.
	 * @param string $text    Comment text.
	 * @param int    $item_id Reader item, if any.
	 * @return array|WP_Error
	 */
	private static function send( $target, $text, $item_id ) {
		// A friend's key never goes on a public reply page (design Q44).
		$target = remove_query_arg( Friends::PARAM, html_entity_decode( (string) $target, ENT_QUOTES ) );
		$text = self::clean( $text );
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		$reply_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => wp_trim_words( $text, 8, '…' ),
				'post_content' => $text,
				// Each comment gets its own unguessable address.
				'post_name'    => 'c-' . strtolower( wp_generate_password( 12, false ) ),
				'post_author'  => get_current_user_id(),
				'meta_input'   => array(
					'_stubli_in_reply_to' => esc_url_raw( $target ),
					'_stubli_item_id'     => (int) $item_id,
				),
			),
			true
		);
		if ( is_wp_error( $reply_id ) ) {
			return $reply_id;
		}

		// The demo's friends are made up: nothing to send to, so it counts as sent.
		$result = Demo::person( $target ) ? array( 'response' => array( 'code' => 202 ) ) : ( Helper::active() ? send_webmention( get_permalink( $reply_id ), $target ) : null );
		$status = self::delivered_status( $result );
		if ( 'sent' !== $status ) {
			self::schedule_retry( $reply_id, $status );
		}

		return array(
			'id'        => $reply_id,
			'url'       => get_permalink( $reply_id ),
			'delivered' => self::delivered( $result ),
			// The other site decides whether it shows now or after their OK.
			'status'    => $status,
		);
	}

	/**
	 * Answer a comment on your own post or guest book (0.45.9). The answer
	 * shows under their comment on your page, and goes to their Stubli the way
	 * your comments on their posts do, so they see it there too.
	 *
	 * @param int    $comment_id The comment you're answering.
	 * @param string $text       Your answer.
	 * @return array|WP_Error
	 */
	public static function reply_to_comment( $comment_id, $text ) {
		$parent = get_comment( (int) $comment_id );
		if ( ! $parent || '1' !== (string) $parent->comment_approved || self::POST_TYPE === get_post_type( (int) $parent->comment_post_ID ) ) {
			return new WP_Error( 'stubli_not_found', __( 'That comment isn\'t on your page any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		$text = self::clean( $text );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$id = wp_insert_comment(
			array(
				'comment_post_ID'      => (int) $parent->comment_post_ID,
				'comment_parent'       => (int) $parent->comment_ID,
				'comment_content'      => $text,
				'comment_author'       => Posts::site_name(),
				'comment_author_url'   => home_url( '/' ),
				'comment_author_email' => (string) get_option( 'admin_email' ),
				'user_id'              => get_current_user_id(),
				'comment_approved'     => 1,
				'comment_type'         => 'comment',
			)
		);
		if ( ! $id ) {
			return new WP_Error( 'stubli_reply_failed', __( 'That didn\'t work. Try again.', 'stubli' ), array( 'status' => 500 ) );
		}
		$sent = self::pass_on( get_comment( $id ) );
		return array(
			'id'      => (int) $id,
			'comment' => Moderation::public_comment( get_comment( $id ) ),
			// "local": they wrote it without a Stubli, so it only shows on your page.
			'status'  => is_array( $sent ) ? $sent['status'] : 'local',
		);
	}

	/**
	 * Comments on one of your posts, oldest first, for the app's Comments
	 * screen (0.45.9). Each says whether you've answered it.
	 *
	 * @param int $post_id Your post.
	 * @return array|WP_Error
	 */
	public static function for_own_post( $post_id ) {
		$post = self::own_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$owner = get_current_user_id();
		$out   = array();
		foreach ( Moderation::for_post( $post->ID ) as $c ) {
			$c['replied'] = $owner && ! $c['author']['owner'] && (bool) get_comments(
				array(
					'parent'  => (int) $c['id'],
					'user_id' => $owner,
					'count'   => true,
				)
			);
			$out[] = $c;
		}
		return array(
			'post'     => array(
				'id'    => (int) $post->ID,
				'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'url'   => get_permalink( $post ),
				'thumb' => self::thumb( $post ),
			),
			'comments' => $out,
		);
	}

	/**
	 * Write the first comment (or any comment) on your own post (Celestine,
	 * 1 Nov: "I want the option to add my comment anyway"). It shows under the
	 * post on your page; there's nobody else to send it to.
	 *
	 * @param int    $post_id Your post.
	 * @param string $text    Your comment.
	 * @return array|WP_Error
	 */
	public static function comment_on_own_post( $post_id, $text ) {
		$post = self::own_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$text = self::clean( $text );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$id = wp_insert_comment(
			array(
				'comment_post_ID'      => (int) $post->ID,
				'comment_content'      => $text,
				'comment_author'       => Posts::site_name(),
				'comment_author_url'   => home_url( '/' ),
				'comment_author_email' => (string) get_option( 'admin_email' ),
				'user_id'              => get_current_user_id(),
				'comment_approved'     => 1,
				'comment_type'         => 'comment',
			)
		);
		if ( ! $id ) {
			return new WP_Error( 'stubli_reply_failed', __( 'That didn\'t work. Try again.', 'stubli' ), array( 'status' => 500 ) );
		}
		return array(
			'id'      => (int) $id,
			'comment' => Moderation::public_comment( get_comment( $id ) ),
		);
	}

	/**
	 * A small picture for a post: its first photo, as in the You tab.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function thumb( $post ) {
		$photos = Mine::photos( $post->ID );
		if ( $photos ) {
			return (string) $photos[0]['src'];
		}
		return (string) get_the_post_thumbnail_url( $post, 'thumbnail' );
	}

	/**
	 * One of your published posts.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|WP_Error
	 */
	private static function own_post( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'stubli_not_found', __( 'That post isn\'t here any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		return $post;
	}

	/**
	 * A reply typed in the WordPress dashboard: pass it on like one from the app.
	 *
	 * @param int        $id       Comment ID.
	 * @param int|string $approved 1 when approved.
	 */
	public static function dashboard_reply( $id, $approved ) {
		$c = get_comment( $id );
		if ( 1 === (int) $approved && $c && $c->comment_parent && (int) $c->user_id > 0 && user_can( (int) $c->user_id, 'manage_options' ) ) {
			self::pass_on( $c );
		}
	}

	/**
	 * Send your answer to the Stubli the original comment came from.
	 *
	 * @param \WP_Comment|null $c Your answer.
	 * @return array|WP_Error|null Null when there's nowhere to send it.
	 */
	private static function pass_on( $c ) {
		if ( ! $c || ! $c->comment_parent || get_comment_meta( $c->comment_ID, 'stubli_reply_page', true ) ) {
			return null;
		}
		$target = (string) get_comment_meta( (int) $c->comment_parent, 'webmention_source_url', true );
		if ( ! $target || Follows::origin( $target ) === Follows::origin( home_url() ) ) {
			return null;
		}
		$sent = self::send( $target, $c->comment_content, 0 );
		if ( is_array( $sent ) ) {
			update_comment_meta( $c->comment_ID, 'stubli_reply_page', (int) $sent['id'] );
		}
		return $sent;
	}

	/**
	 * Comment text, tidied and checked.
	 *
	 * @param string $text Text.
	 * @return string|WP_Error
	 */
	private static function clean( $text ) {
		$text = trim( sanitize_textarea_field( (string) $text ) );
		if ( '' === $text ) {
			return new WP_Error( 'stubli_empty', __( 'Write a few words first.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( mb_strlen( $text ) > self::MAX_LENGTH ) {
			return new WP_Error( 'stubli_comment_too_long', __( 'That comment is over 1,000 characters. Trim a little and it will fit.', 'stubli' ), array( 'status' => 400 ) );
		}
		return $text;
	}

	/**
	 * Take back a comment you wrote.
	 *
	 * @param int $reply_id Reply page ID.
	 * @return array|WP_Error
	 */
	public static function delete( $reply_id ) {
		$post = get_post( $reply_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'stubli_not_found', __( 'That comment is already gone.', 'stubli' ), array( 'status' => 404 ) );
		}
		$source = get_permalink( $post );
		$target = (string) get_post_meta( $post->ID, '_stubli_in_reply_to', true );
		wp_delete_post( $post->ID, true );
		$result = ( $source && $target && Helper::active() ) ? send_webmention( $source, $target ) : null;
		return array(
			'deleted'   => true,
			'delivered' => self::delivered( $result, true ),
		);
	}

	/**
	 * Comments you've written on one reader item.
	 *
	 * @param int $item_id Reader item ID.
	 * @return int
	 */
	public static function count_for_item( $item_id ) {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_key'       => '_stubli_item_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (int) $item_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return count( $ids );
	}

	/**
	 * Did the other site accept the Webmention?
	 *
	 * @param mixed $result   send_webmention() result.
	 * @param bool  $removing True when taking a comment back: the receiver
	 *                        finds our page gone (404/410) and removes it.
	 * @return bool
	 */
	private static function delivered( $result, $removing = false ) {
		if ( ! is_array( $result ) || is_wp_error( $result ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $result );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}
		return $removing && in_array( $code, array( 404, 410 ), true );
	}

	/**
	 * Plain status for the app: sent, paused (they're on a break) or not sent.
	 *
	 * @param mixed $result send_webmention() result.
	 * @return string
	 */
	private static function delivered_status( $result ) {
		if ( self::delivered( $result ) ) {
			return 'sent';
		}
		if ( is_array( $result ) && ! is_wp_error( $result ) && 403 === (int) wp_remote_retrieve_response_code( $result ) && false !== strpos( (string) wp_remote_retrieve_body( $result ), 'stubli_on_break' ) ) {
			return 'paused';
		}
		return 'not_sent';
	}
}
