<?php
/**
 * The owner's own photo posts in the app: list, edit, delete and bring back.
 *
 * Deleting moves a post to the bin (WordPress keeps it 30 days), so the app
 * can offer Undo. Followers' readers drop it on their next fetch.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * "Your posts".
 */
class Mine {

	const PER_PAGE = 24;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Is this one of the owner's photo posts?
	 *
	 * @param int  $id        Post ID.
	 * @param bool $any_state Also accept posts in the bin.
	 * @return \WP_Post|null
	 */
	private static function photo_post( $id, $any_state = false ) {
		$post = get_post( (int) $id );
		if ( ! $post || 'post' !== $post->post_type || ! in_array( get_post_format( $post ), array( 'image', 'gallery' ), true ) ) {
			return null;
		}
		if ( ! $any_state && 'publish' !== $post->post_status ) {
			return null;
		}
		return $post;
	}

	/**
	 * The caption as the owner typed it.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function caption_text( $post ) {
		$raw = get_post_meta( $post->ID, '_stubli_caption', true );
		if ( '' !== (string) $raw ) {
			return (string) $raw;
		}
		$html = preg_replace( '#</p>\s*<p>#i', "\n\n", (string) $post->post_content );
		$html = preg_replace( '#<br\s*/?>#i', "\n", $html );
		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Photos with their descriptions.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private static function photos( $post_id ) {
		$out = array();
		foreach ( Posts::photo_ids( $post_id ) as $aid ) {
			$src   = wp_get_attachment_image_src( $aid, 'medium_large' );
			$out[] = array(
				'id'  => (int) $aid,
				'src' => $src ? (string) $src[0] : (string) wp_get_attachment_url( $aid ),
				'alt'   => (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ),
				'video' => Posts::video_for( $aid ) > 0,
			);
		}
		return $out;
	}

	/**
	 * One post, for the editor.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	public static function shape( $post ) {
		$cats = get_the_category( $post->ID );
		$def  = (int) get_option( 'default_category' );
		$coll = '';
		foreach ( $cats as $c ) {
			if ( (int) $c->term_id !== $def ) {
				$coll = $c->name;
				break;
			}
		}
		return array(
			'id'         => (int) $post->ID,
			'url'        => (string) get_permalink( $post ),
			'date'       => get_post_time( 'c', true, $post ),
			'caption'    => self::caption_text( $post ),
			'collection' => $coll,
			'note'       => (string) get_post_meta( $post->ID, '_stubli_content_note', true ),
			'photos'     => self::photos( $post->ID ),
		);
	}

	/**
	 * A page of the owner's photo posts, newest first.
	 *
	 * @param int $page Page number.
	 * @return array { items, more, missing }.
	 */
	public static function page( $page = 1 ) {
		$q     = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => self::PER_PAGE,
				'paged'          => max( 1, (int) $page ),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'post_format',
						'field'    => 'slug',
						'terms'    => array( 'post-format-image', 'post-format-gallery' ),
					),
				),
			)
		);
		$items = array();
		foreach ( $q->posts as $p ) {
			$photos  = self::photos( $p->ID );
			$missing = count( array_filter( $photos, static fn( $ph ) => '' === trim( $ph['alt'] ) ) );
			$items[] = array(
				'id'      => (int) $p->ID,
				'thumb'   => $photos ? $photos[0]['src'] : '',
				'count'   => count( $photos ),
				'missing' => $missing,
				'note'    => '' !== (string) get_post_meta( $p->ID, '_stubli_content_note', true ),
				'video'   => $photos && $photos[0]['video'],
				'title'   => html_entity_decode( wp_strip_all_tags( get_the_title( $p ) ), ENT_QUOTES, 'UTF-8' ),
			);
		}
		return array(
			'items'   => $items,
			'more'    => $q->max_num_pages > max( 1, (int) $page ),
			'total'   => (int) $q->found_posts,
			'missing' => self::missing_count(),
		);
	}

	/**
	 * How many published photos have no description, across all posts.
	 *
	 * @return int
	 */
	public static function missing_count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one cheap count for a single owner.
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} a
			 JOIN {$wpdb->posts} p ON p.ID = a.post_parent AND p.post_type = 'post' AND p.post_status = 'publish'
			 JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'post_format'
			 JOIN {$wpdb->terms} t ON t.term_id = tt.term_id AND t.slug IN ( 'post-format-image', 'post-format-gallery' )
			 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = a.ID AND m.meta_key = '_wp_attachment_image_alt'
			 WHERE a.post_type = 'attachment' AND a.post_mime_type LIKE 'image/%' AND ( m.meta_value IS NULL OR TRIM( m.meta_value ) = '' )"
		);
	}

	/**
	 * Change a post's caption, collection, content note or descriptions.
	 *
	 * @param int   $id      Post ID.
	 * @param array $changes Changes.
	 * @return array|\WP_Error
	 */
	public static function update( $id, array $changes ) {
		$post = self::photo_post( $id );
		if ( ! $post ) {
			return new \WP_Error( 'stubli_not_found', __( 'That post isn\'t here any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		$fields = array( 'ID' => $post->ID );
		if ( array_key_exists( 'caption', $changes ) ) {
			$caption = sanitize_textarea_field( (string) $changes['caption'] );
			if ( Posts::visible_length( $caption ) > Posts::MAX_CAPTION_LENGTH ) {
				return new \WP_Error( 'stubli_caption_too_long', __( 'That caption is a little long. Trim it to 2,200 characters and it will fit.', 'stubli' ), array( 'status' => 400 ) );
			}
			$fields['post_content'] = '' !== trim( $caption ) ? Posts::safe_text_html( $caption ) : '';
			$fields['post_title']   = wp_trim_words( wp_strip_all_tags( $caption ), 8, '…' );
			update_post_meta( $post->ID, '_stubli_caption', $caption );
		}
		if ( count( $fields ) > 1 ) {
			$res = wp_update_post( wp_slash( $fields ), true );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
		}
		if ( array_key_exists( 'collection', $changes ) ) {
			$name = trim( (string) $changes['collection'] );
			wp_set_post_categories( $post->ID, '' === $name ? array() : array( Posts::collection_id( $name ) ) );
		}
		if ( array_key_exists( 'note', $changes ) ) {
			$note = mb_substr( sanitize_text_field( (string) $changes['note'] ), 0, Posts::MAX_NOTE_LENGTH );
			if ( '' === $note ) {
				delete_post_meta( $post->ID, '_stubli_content_note' );
			} else {
				update_post_meta( $post->ID, '_stubli_content_note', $note );
			}
		}
		if ( isset( $changes['alts'] ) && is_array( $changes['alts'] ) ) {
			$mine = Posts::photo_ids( $post->ID );
			foreach ( $changes['alts'] as $aid => $alt ) {
				if ( in_array( (int) $aid, $mine, true ) ) {
					update_post_meta( (int) $aid, '_wp_attachment_image_alt', mb_substr( sanitize_text_field( (string) $alt ), 0, 1000 ) );
				}
			}
		}
		clean_post_cache( $post->ID );
		return self::shape( get_post( $post->ID ) );
	}

	/**
	 * Move a post to the bin.
	 *
	 * @param int $id Post ID.
	 * @return array|\WP_Error
	 */
	public static function delete( $id ) {
		$post = self::photo_post( $id );
		if ( ! $post ) {
			return new \WP_Error( 'stubli_not_found', __( 'That post isn\'t here any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		wp_trash_post( $post->ID );
		return array(
			'deleted' => true,
			'id'      => (int) $post->ID,
		);
	}

	/**
	 * Bring a post back from the bin, published as before.
	 *
	 * @param int $id Post ID.
	 * @return array|\WP_Error
	 */
	public static function restore( $id ) {
		$post = self::photo_post( $id, true );
		if ( ! $post || 'trash' !== $post->post_status ) {
			return new \WP_Error( 'stubli_not_found', __( 'That post can\'t be brought back.', 'stubli' ), array( 'status' => 404 ) );
		}
		add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );
		wp_untrash_post( $post->ID );
		remove_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10 );
		return self::shape( get_post( $post->ID ) );
	}

	/**
	 * REST routes (owner only).
	 */
	public static function routes() {
		$owner = array( Rest::class, 'is_owner' );
		$id    = array(
			'id' => array(
				'validate_callback' => static fn( $v ) => is_numeric( $v ),
			),
		);
		$wrap  = static fn( $res ) => is_wp_error( $res ) ? $res : rest_ensure_response( $res );
		Rest::register(
			'/mine',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static fn( \WP_REST_Request $r ) => rest_ensure_response( self::page( (int) ( $r['page'] ? $r['page'] : 1 ) ) ),
				'permission_callback' => $owner,
			)
		);
		Rest::register(
			'/mine/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$post = self::photo_post( (int) $r['id'] );
						return $post ? rest_ensure_response( self::shape( $post ) ) : new \WP_Error( 'stubli_not_found', __( 'That post isn\'t here any more.', 'stubli' ), array( 'status' => 404 ) );
					},
					'permission_callback' => $owner,
					'args'                => $id,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => static fn( \WP_REST_Request $r ) => $wrap( self::update( (int) $r['id'], (array) $r->get_json_params() ) ),
					'permission_callback' => $owner,
					'args'                => $id,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => static fn( \WP_REST_Request $r ) => $wrap( self::delete( (int) $r['id'] ) ),
					'permission_callback' => $owner,
					'args'                => $id,
				),
			)
		);
		Rest::register(
			'/mine/(?P<id>\d+)/restore',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static fn( \WP_REST_Request $r ) => $wrap( self::restore( (int) $r['id'] ) ),
				'permission_callback' => $owner,
				'args'                => $id,
			)
		);
	}
}
