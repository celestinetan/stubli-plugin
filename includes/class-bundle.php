<?php
/**
 * Everything that makes up someone's Stubli, gathered in one plain shape.
 * Used by "Download everything" (Archive) and "Move to another site" (Move).
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * The owner's Stubli as data.
 */
class Bundle {

	const FORMAT = 'stubli-bundle/1';

	/**
	 * Profile and look.
	 *
	 * @return array
	 */
	public static function profile() {
		$icon = (int) get_option( 'site_icon' );
		return array(
			'name'      => Posts::site_name(),
			'bio'       => (string) get_option( 'stubli_bio', '' ),
			'pronouns'  => (string) get_option( 'stubli_pronouns', '' ),
			'tagline'   => Posts::site_tagline(),
			'url'       => home_url( '/' ),
			'avatar'    => $icon ? self::media( $icon ) : null,
			'smiley'    => Avatar::look(),
			'accent'    => Setup::accent(),
			'mode'      => Setup::mode(),
			'links'     => (string) get_option( 'stubli_links', '' ),
			'favourites' => Links::data(),
			'interests' => array_column( Setup::interests(), 'name' ),
			'prompts'   => Prompts::get(),
		);
	}

	/**
	 * Settings worth keeping when moving.
	 *
	 * @return array
	 */
	public static function settings() {
		$s = Settings::get();
		return array(
			'who_can_comment' => $s['who_can_comment'],
			'on_break'        => $s['on_break'],
			'search_engines'  => $s['search_engines'],
			'block_ai'        => $s['block_ai'],
			'credit'          => $s['credit'],
			'digest_time'     => $s['digest_time'],
			'digest_email'    => $s['digest_email'],
			'blocked'         => array_values( (array) get_option( 'stubli_blocked', array() ) ),
		);
	}

	/**
	 * The people you follow.
	 *
	 * @return array
	 */
	public static function follows() {
		return array_map(
			static fn( $f ) => array(
				'url'       => (string) $f['site_url'],
				'name'      => (string) $f['name'],
				'interest'  => (string) $f['list_name'],
				'is_public' => (bool) $f['is_public'],
				'muted'     => (bool) $f['muted'],
				'since'     => (string) $f['created'],
			),
			Follows::all()
		);
	}

	/**
	 * IDs of all photo posts and journal entries, oldest first.
	 *
	 * @return int[]
	 */
	public static function post_ids() {
		$q = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		return array_map( 'intval', $q->posts );
	}

	/**
	 * One photo, video or image file: where it is and what it is.
	 *
	 * @param int $id Attachment ID.
	 * @return array|null
	 */
	public static function media( $id ) {
		$id = (int) $id;
		if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
			return null;
		}
		$path = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $id ) : false;
		if ( ! $path || ! file_exists( $path ) ) {
			$path = get_attached_file( $id );
		}
		$url = function_exists( 'wp_get_original_image_url' ) ? wp_get_original_image_url( $id ) : false;
		return array(
			'id'   => $id,
			'url'  => $url ? (string) $url : (string) wp_get_attachment_url( $id ),
			'path' => $path ? (string) $path : '',
			'mime' => (string) get_post_mime_type( $id ),
			'alt'  => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'name' => $path ? wp_basename( $path ) : '',
		);
	}

	/**
	 * Comments on a post or the guest book, oldest first.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function comments( $post_id ) {
		$out = array();
		foreach ( get_comments(
			array(
				'post_id' => (int) $post_id,
				'status'  => 'approve',
				'orderby' => 'comment_date_gmt',
				'order'   => 'ASC',
			)
		) as $c ) {
			$meta = array();
			foreach ( (array) get_comment_meta( $c->comment_ID ) as $k => $v ) {
				if ( '_' !== substr( (string) $k, 0, 1 ) ) {
					$meta[ $k ] = maybe_unserialize( $v[0] );
				}
			}
			$out[] = array(
				'id'     => (int) $c->comment_ID,
				'parent' => (int) $c->comment_parent,
				'name'   => (string) $c->comment_author,
				'url'    => (string) $c->comment_author_url,
				'mine'   => (int) $c->user_id > 0 && user_can( (int) $c->user_id, 'manage_options' ),
				'avatar' => (string) get_avatar_url( $c ),
				'text'   => wp_strip_all_tags( $c->comment_content ),
				'date'   => gmdate( 'c', (int) strtotime( $c->comment_date_gmt . ' UTC' ) ),
				'type'   => (string) $c->comment_type,
				'meta'   => $meta,
			);
		}
		return $out;
	}

	/**
	 * One photo post or journal entry.
	 *
	 * @param int $id Post ID.
	 * @return array|null
	 */
	public static function post( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || 'post' !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			return null;
		}
		$format = get_post_format( $post );
		$type   = 'status' === $format ? 'journal' : ( in_array( $format, array( 'image', 'gallery' ), true ) ? 'photo' : 'other' );
		$out    = array(
			'id'         => (int) $post->ID,
			'type'       => $type,
			'format'     => $format ? $format : 'standard',
			'date'       => get_post_time( 'c', true, $post ),
			'status'     => $post->post_status,
			'audience'   => Friends::audience( $post ),
			'title'      => $post->post_title,
			'own_title'  => 'journal' === $type ? Journal::title( $post->ID ) : '',
			'url'        => (string) get_permalink( $post ),
			'collection' => '',
			'note'       => (string) get_post_meta( $post->ID, '_stubli_content_note', true ),
			'caption'    => 'photo' === $type ? Mine::caption_text( $post ) : '',
			'html'       => 'photo' === $type ? '' : (string) $post->post_content,
			'photos'     => array(),
			'comments'   => self::comments( $post->ID ),
		);
		$def = (int) get_option( 'default_category' );
		foreach ( get_the_category( $post->ID ) as $c ) {
			if ( (int) $c->term_id !== $def ) {
				$out['collection'] = $c->name;
				break;
			}
		}
		if ( 'photo' === $type ) {
			foreach ( Posts::photo_ids( $post->ID ) as $aid ) {
				$m = self::media( $aid );
				if ( $m ) {
					$v          = Posts::video_for( $aid );
					$m['video'] = $v ? self::media( $v ) : null;
					$out['photos'][] = $m;
				}
			}
		} else {
			foreach ( get_children(
				array(
					'post_parent'    => $post->ID,
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'orderby'        => 'menu_order ID',
					'order'          => 'ASC',
				)
			) as $att ) {
				$m = self::media( $att->ID );
				if ( $m ) {
					$m['video']      = null;
					$out['photos'][] = $m;
				}
			}
		}
		return $out;
	}

	/**
	 * Swap the <img> of one photo inside journal HTML for something else.
	 * Matches by file name, including WordPress's resized copies
	 * (photo-1024x768.jpg, photo-scaled.jpg).
	 *
	 * @param string $html Journal HTML.
	 * @param array  $m    Media (with name).
	 * @param string $with Replacement HTML.
	 * @return string
	 */
	public static function replace_img( $html, array $m, $with ) {
		$name = (string) ( $m['name'] ?? '' );
		if ( '' === $name ) {
			return $html;
		}
		$dot  = strrpos( $name, '.' );
		$stem = preg_quote( false === $dot ? $name : substr( $name, 0, $dot ), '#' );
		$ext  = preg_quote( false === $dot ? '' : substr( $name, $dot ), '#' );
		$stem = preg_replace( '#\\-scaled$#', '', $stem );
		return (string) preg_replace_callback( '#<img\b[^>]*\bsrc="[^"]*/' . $stem . '(?:-scaled|-\d+x\d+)*' . $ext . '"[^>]*>#i', static fn() => $with, $html );
	}

	/**
	 * The guest book.
	 *
	 * @return array
	 */
	public static function guestbook() {
		$id = Guestbook::page_id();
		if ( ! $id ) {
			return array();
		}
		$out = self::comments( $id );
		foreach ( $out as &$e ) {
			$e['pinned'] = ! empty( $e['meta']['stubli_pinned'] );
		}
		return $out;
	}
}
