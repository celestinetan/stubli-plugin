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
				'supports'            => array( 'editor', 'author' ),
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
			$name = get_bloginfo( 'name' );
		}

		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head><meta charset="utf-8"><meta name="robots" content="noindex"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html( sprintf( /* translators: %s: author name */ __( 'A comment by %s', 'stubli' ), $name ) ); ?></title></head>
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
		$text = trim( sanitize_textarea_field( (string) $text ) );
		if ( '' === $text ) {
			return new WP_Error( 'stubli_empty', __( 'Write a few words first.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( mb_strlen( $text ) > self::MAX_LENGTH ) {
			return new WP_Error( 'stubli_comment_too_long', __( 'That comment is over 1,000 characters. Trim a little and it will fit.', 'stubli' ), array( 'status' => 400 ) );
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

		$result = send_webmention( get_permalink( $reply_id ), $target );

		return array(
			'id'        => $reply_id,
			'url'       => get_permalink( $reply_id ),
			'delivered' => self::delivered( $result ),
			// The other site decides whether it shows now or after their OK.
			'status'    => self::delivered_status( $result ),
		);
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
		$result = ( $source && $target ) ? send_webmention( $source, $target ) : null;
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
