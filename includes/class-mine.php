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
	 * How long a photo taken out of a post is kept, so Undo can bring it back
	 * (seconds). After that it's deleted from the site.
	 */
	const KEEP_REMOVED = 600;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'stubli_purge_removed', array( self::class, 'purge_removed' ) );
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
		if ( ! $any_state && 'publish' !== $post->post_status && ! Friends::is_friends_post( $post ) ) {
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
		$coll = self::collection_of( $post->ID );
		return array(
			'id'         => (int) $post->ID,
			'url'        => (string) get_permalink( $post ),
			'date'       => get_post_time( 'c', true, $post ),
			'caption'    => self::caption_text( $post ),
			'collection' => $coll,
			'note'       => (string) get_post_meta( $post->ID, '_stubli_content_note', true ),
			'audience'   => Friends::audience( $post ),
			'photos'     => self::photos( $post->ID ),
			'ratio'      => self::ratio( $post->ID ),
			'edited'     => self::edited( $post->ID ) ? gmdate( 'c', self::edited( $post->ID ) ) : '',
		);
	}

	/**
	 * The post's shape, from its first photo: portrait, square or landscape.
	 * New photos added in the editor are cropped to it unless the owner picks another.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function ratio( $post_id ) {
		foreach ( Posts::photo_ids( $post_id ) as $aid ) {
			if ( Posts::video_for( $aid ) ) {
				continue;
			}
			$meta = wp_get_attachment_metadata( $aid );
			if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				$r = $meta['width'] / $meta['height'];
				return $r > 1.2 ? 'landscape' : ( $r > 0.9 ? 'square' : 'portrait' );
			}
		}
		return 'portrait';
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
				'post_status'    => array( 'publish', 'private' ),
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
				'friends' => Friends::is_friends_post( $p ),
				'title'   => html_entity_decode( wp_strip_all_tags( get_the_title( $p ) ), ENT_QUOTES, 'UTF-8' ),
				'url'     => (string) get_permalink( $p ),
				'date'    => get_post_time( 'c', true, $p ),
				'coll'    => self::collection_of( $p->ID ),
			);
		}
		return array(
			'items'   => $items,
			'more'    => $q->max_num_pages > max( 1, (int) $page ),
			'total'   => (int) $q->found_posts,
			'missing' => self::missing_count(),
			'shapes'  => 1 === max( 1, (int) $page ) ? Arrange::shapes() : null,
		);
	}

	/**
	 * The collection a post is in ('' for none).
	 *
	 * @param int $id Post ID.
	 * @return string
	 */
	public static function collection_of( $id ) {
		$def = (int) get_option( 'default_category' );
		foreach ( get_the_category( $id ) as $c ) {
			// The default category counts once it has a real name (renamed under Arrange).
			if ( (int) $c->term_id !== $def || ! in_array( strtolower( $c->name ), array( 'uncategorized', 'uncategorised' ), true ) ) {
				return html_entity_decode( $c->name, ENT_QUOTES, 'UTF-8' );
			}
		}
		return '';
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
	 * When a post's photos were last changed (Unix time), or 0.
	 *
	 * @param int $id Post ID.
	 * @return int
	 */
	public static function edited( $id ) {
		return (int) get_post_meta( (int) $id, '_stubli_edited', true );
	}

	/**
	 * Photos taken out of this post that Undo can still bring back.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private static function removed_photos( $post_id ) {
		$ids = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_parent' => (int) $post_id,
				'numberposts' => 50,
				'fields'      => 'ids',
				'meta_key'    => '_stubli_removed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * Delete photos (and their videos) that were taken out of a post more
	 * than KEEP_REMOVED seconds ago. Runs from a scheduled event.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function purge_removed( $post_id ) {
		$keep = Posts::photo_ids( (int) $post_id );
		$left = false;
		foreach ( self::removed_photos( (int) $post_id ) as $aid ) {
			if ( in_array( $aid, $keep, true ) ) {
				delete_post_meta( $aid, '_stubli_removed' );
				continue;
			}
			if ( time() - (int) get_post_meta( $aid, '_stubli_removed', true ) < self::KEEP_REMOVED ) {
				$left = true;
				continue;
			}
			$video = Posts::video_for( $aid );
			if ( $video ) {
				wp_delete_attachment( $video, true );
			}
			wp_delete_attachment( $aid, true );
		}
		if ( $left && ! wp_next_scheduled( 'stubli_purge_removed', array( (int) $post_id ) ) ) {
			wp_schedule_single_event( time() + self::KEEP_REMOVED, 'stubli_purge_removed', array( (int) $post_id ) );
		}
	}

	/**
	 * Work out a post's new photos from the app's list, uploading new ones.
	 * Entries are photo IDs, or 'new0', 'new1'… for the uploaded files in order.
	 * Returns null when the photos stay as they are.
	 *
	 * @param \WP_Post $post    Post.
	 * @param array    $order   The app's list.
	 * @param array    $uploads { files, posters, alts } for new photos.
	 * @return array|null|\WP_Error { order, added } or null.
	 */
	private static function new_photos( $post, array $order, array $uploads ) {
		$files = array_values( (array) ( $uploads['files'] ?? array() ) );
		if ( $files && ! current_user_can( 'upload_files' ) ) {
			return new \WP_Error( 'stubli_forbidden', __( 'You can\'t upload photos here.', 'stubli' ), array( 'status' => 403 ) );
		}
		$mine    = Posts::photo_ids( $post->ID );
		$allowed = array_merge( $mine, self::removed_photos( $post->ID ) );
		$wanted  = array();
		foreach ( $order as $entry ) {
			if ( is_string( $entry ) && preg_match( '/^new(\d+)$/', $entry, $m ) ) {
				if ( (int) $m[1] < count( $files ) ) {
					$wanted[] = $entry;
				}
			} elseif ( in_array( (int) $entry, $allowed, true ) ) {
				$wanted[] = (int) $entry;
			}
		}
		$wanted = array_values( array_unique( $wanted, SORT_REGULAR ) );
		if ( ! $wanted ) {
			return new \WP_Error( 'stubli_no_photo', __( 'A post keeps at least one photo. To remove them all, delete the post instead.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( count( $wanted ) > Posts::MAX_PHOTOS ) {
			return new \WP_Error( 'stubli_too_many_photos', __( 'A post can hold up to 10 photos.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( ! $files && $wanted === $mine ) {
			return null;
		}
		$added = array();
		if ( $files ) {
			$posters = (array) ( $uploads['posters'] ?? array() );
			$videos  = Posts::check_media( $files, $posters );
			if ( is_wp_error( $videos ) ) {
				return $videos;
			}
			$ids = Posts::sideload_media( $files, (array) ( $uploads['alts'] ?? array() ), $posters, $videos, $post->ID );
			if ( is_wp_error( $ids ) ) {
				return 'stubli_upload_failed' === $ids->get_error_code() && ! $videos ? new \WP_Error( 'stubli_upload_failed', __( 'That photo didn\'t upload, so nothing has changed. Try again when you\'re ready.', 'stubli' ), array( 'status' => 400 ) ) : $ids;
			}
			foreach ( $wanted as $k => $entry ) {
				if ( is_string( $entry ) ) {
					$wanted[ $k ] = $ids[ (int) substr( $entry, 3 ) ];
					$added[]      = $wanted[ $k ];
				}
			}
			// Uploaded files the list didn't use aren't kept.
			foreach ( array_diff( $ids, $added ) as $unused ) {
				$video = Posts::video_for( $unused );
				if ( $video ) {
					wp_delete_attachment( $video, true );
				}
				wp_delete_attachment( $unused, true );
			}
		}
		return array(
			'order' => array_map( 'intval', $wanted ),
			'added' => $added,
		);
	}

	/**
	 * Change a post's photos, caption, collection, content note or descriptions.
	 *
	 * Photos can be added, removed, replaced and reordered. Taken-out photos
	 * stay on the site for KEEP_REMOVED seconds so Undo works, then are
	 * deleted. Changing the photos gives the post an "Edited" time.
	 *
	 * @param int   $id      Post ID.
	 * @param array $changes Changes.
	 * @param array $uploads New photos: { files, posters, alts }.
	 * @return array|\WP_Error
	 */
	public static function update( $id, array $changes, array $uploads = array() ) {
		$post = self::photo_post( $id );
		if ( ! $post ) {
			return new \WP_Error( 'stubli_not_found', __( 'That post isn\'t here any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		if ( array_key_exists( 'caption', $changes ) && Posts::visible_length( sanitize_textarea_field( (string) $changes['caption'] ) ) > Posts::MAX_CAPTION_LENGTH ) {
			return new \WP_Error( 'stubli_caption_too_long', __( 'That caption is a little long. Trim it to 2,200 characters and it will fit.', 'stubli' ), array( 'status' => 400 ) );
		}
		// Photos first: if an upload fails, nothing else changes either.
		$photos = isset( $changes['order'] ) && is_array( $changes['order'] ) ? self::new_photos( $post, $changes['order'], $uploads ) : null;
		if ( is_wp_error( $photos ) ) {
			return $photos;
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
		if ( array_key_exists( 'audience', $changes ) ) {
			Friends::set_audience( $post->ID, 'friends' === $changes['audience'] ? 'friends' : 'everyone' );
		}
		if ( $photos ) {
			$mine    = Posts::photo_ids( $post->ID );
			$order   = $photos['order'];
			$removed = array_values( array_diff( $mine, $order ) );
			$added   = array_values( array_diff( $order, $mine ) );
			foreach ( $removed as $aid ) {
				update_post_meta( $aid, '_stubli_removed', time() );
			}
			foreach ( $added as $aid ) {
				delete_post_meta( $aid, '_stubli_removed' );
			}
			update_post_meta( $post->ID, '_stubli_photos', $order );
			set_post_thumbnail( $post->ID, $order[0] );
			set_post_format( $post->ID, count( $order ) > 1 ? 'gallery' : 'image' );
			if ( $removed || $added ) {
				$prev = array_map( 'intval', (array) get_post_meta( $post->ID, '_stubli_prev_photos', true ) );
				if ( $prev && $order === $prev && ! $photos['added'] ) {
					// Undo: back to the photos from before, and to the "Edited" time from before.
					$was = (int) get_post_meta( $post->ID, '_stubli_prev_edited', true );
					if ( $was ) {
						update_post_meta( $post->ID, '_stubli_edited', $was );
					} else {
						delete_post_meta( $post->ID, '_stubli_edited' );
					}
					delete_post_meta( $post->ID, '_stubli_prev_photos' );
				} else {
					update_post_meta( $post->ID, '_stubli_prev_photos', $mine );
					update_post_meta( $post->ID, '_stubli_prev_edited', self::edited( $post->ID ) );
					update_post_meta( $post->ID, '_stubli_edited', time() );
				}
				if ( $removed && ! wp_next_scheduled( 'stubli_purge_removed', array( (int) $post->ID ) ) ) {
					wp_schedule_single_event( time() + self::KEEP_REMOVED + 60, 'stubli_purge_removed', array( (int) $post->ID ) );
				}
			}
			// Readers notice the change at their next check.
			wp_update_post( array( 'ID' => $post->ID, 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', true ) ) );
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
					'callback'            => static function ( \WP_REST_Request $r ) use ( $wrap ) {
						// Plain changes come as JSON; with new photos, as a form whose 'data' field holds the JSON.
						$changes = (array) $r->get_json_params();
						$files   = $r->get_file_params();
						if ( ! $changes && is_string( $r->get_param( 'data' ) ) ) {
							$changes = (array) json_decode( $r->get_param( 'data' ), true );
						}
						$alts = $r->get_param( 'new_alts' );
						return $wrap(
							self::update(
								(int) $r['id'],
								$changes,
								array(
									'files'   => array_values( Rest::file_list( $files, 'photos' ) ),
									'posters' => Rest::file_list( $files, 'posters' ),
									'alts'    => is_array( $alts ) ? array_values( $alts ) : array(),
								)
							)
						);
					},
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
