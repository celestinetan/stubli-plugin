<?php
/**
 * The journal: short written entries, for everyone or only you (Q17, Q21).
 *
 * @package Stubli
 */

namespace Stubli;

use WP_Error;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Journal entries are ordinary posts in the "status" format. They live in
 * the Journal tab only: never on the home page, never in the RSS feed, so
 * they never reach anyone's reader. "Only me" entries are private posts.
 */
class Journal {

	const MAX_LENGTH = 5000;
	const QUERY_VAR  = 'stubli_journal';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'rewrite' ) );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'pre_get_posts', array( self::class, 'keep_out_of_feeds' ) );
	}

	/**
	 * /journal/ lists entries; the theme renders it.
	 */
	public static function rewrite() {
		add_rewrite_rule( '^journal/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		add_rewrite_rule( '^journal/page/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=1&paged=$matches[1]', 'top' );
	}

	/**
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Journal entries never appear on the home page or in any feed; the
	 * journal page shows only journal entries.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function keep_out_of_feeds( $query ) {
		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}
		$tax = array(
			'taxonomy' => 'post_format',
			'field'    => 'slug',
			'terms'    => array( 'post-format-status' ),
		);
		if ( $query->get( self::QUERY_VAR ) ) {
			$query->set( 'tax_query', array( $tax ) );
			$query->set( 'post_type', 'post' );
			$query->is_home = false;
			return;
		}
		if ( $query->is_home() || $query->is_feed() || $query->is_archive() || $query->is_search() ) {
			$tax['operator'] = 'NOT IN';
			$query->set( 'tax_query', array( $tax ) );
		}
	}

	/**
	 * Write a journal entry.
	 *
	 * @param string $text       Entry text; links and video addresses allowed.
	 * @param string $visibility "everyone" or "only_me".
	 * @return array|WP_Error
	 */
	public static function create( $text, $visibility = 'everyone', array $photos = array() ) {
		$text = (string) $text;
		if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
			return new WP_Error( 'stubli_empty', __( 'Write a few words first.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( Posts::visible_length( $text ) > self::MAX_LENGTH ) {
			return new WP_Error( 'stubli_entry_too_long', __( 'That entry is over 5,000 characters. Trim a little and it will fit.', 'stubli' ), array( 'status' => 400 ) );
		}
		$private = 'only_me' === $visibility;

		$post_id = wp_insert_post(
			array(
				'post_status'  => $private ? 'private' : 'publish',
				'post_title'   => wp_date( get_option( 'date_format' ) ),
				'post_content' => self::content_with_photos( $text, $photos ),
				'post_author'  => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		set_post_format( $post_id, 'status' );
		foreach ( self::valid_photos( $photos ) as $ph ) {
			wp_update_post(
				array(
					'ID'          => $ph['id'],
					'post_parent' => $post_id,
				)
			);
			if ( '' !== $ph['alt'] ) {
				update_post_meta( $ph['id'], '_wp_attachment_image_alt', $ph['alt'] );
			}
		}

		return array(
			'id'         => $post_id,
			'url'        => get_permalink( $post_id ),
			'type'       => 'journal',
			'visibility' => $private ? 'only_me' : 'everyone',
		);
	}

	/**
	 * Entries for the Journal tab. Visitors see public ones; the owner also
	 * sees "Only me" entries, marked as such.
	 *
	 * @param bool $include_private Include private entries.
	 * @param int  $page            1-based page.
	 * @return array[]
	 */
	public static function entries( $include_private = false, $page = 1 ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => $include_private ? array( 'publish', 'private' ) : array( 'publish' ),
				'posts_per_page' => 20,
				'paged'          => max( 1, (int) $page ),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'post_format',
						'field'    => 'slug',
						'terms'    => array( 'post-format-status' ),
					),
				),
			)
		);
		return array_map(
			static function ( $post ) {
				return array(
					'id'         => $post->ID,
					'date'       => get_post_time( 'c', true, $post ),
					'html'       => apply_filters( 'the_content', $post->post_content ),
					'visibility' => 'private' === $post->post_status ? 'only_me' : 'everyone',
					'url'        => get_permalink( $post ),
				);
			},
			$query->posts
		);
	}

	/**
	 * Photos the owner attached: their own image uploads, with a caption and
	 * a description each, keyed by the marker number ("[Photo 2]").
	 *
	 * @param array $photos Raw list of { n, id, caption, alt }.
	 * @return array n => { id, caption, alt }
	 */
	public static function valid_photos( array $photos ) {
		$out = array();
		foreach ( array_slice( $photos, 0, 20 ) as $ph ) {
			if ( ! is_array( $ph ) ) {
				continue;
			}
			$id = (int) ( $ph['id'] ?? 0 );
			$n  = (int) ( $ph['n'] ?? 0 );
			if ( $n < 1 || ! $id || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			if ( (int) get_post_field( 'post_author', $id ) !== get_current_user_id() ) {
				continue;
			}
			$out[ $n ] = array(
				'id'      => $id,
				'caption' => mb_substr( sanitize_text_field( (string) ( $ph['caption'] ?? '' ) ), 0, 300 ),
				'alt'     => mb_substr( sanitize_text_field( (string) ( $ph['alt'] ?? '' ) ), 0, 300 ),
			);
		}
		return $out;
	}

	/**
	 * The entry's HTML: text with each "[Photo N]" line turned into the photo
	 * and its caption. Photos without a marker go at the end.
	 *
	 * @param string $text   Entry text.
	 * @param array  $photos Attached photos.
	 * @return string
	 */
	public static function content_with_photos( $text, array $photos ) {
		$valid = self::valid_photos( $photos );
		if ( ! $valid ) {
			return Posts::safe_text_html( $text );
		}
		$used  = array();
		$parts = preg_split( '/^[ \t]*\[Photo (\d{1,2})\][ \t]*$/mi', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		$html  = '';
		foreach ( $parts as $i => $part ) {
			if ( 1 === $i % 2 ) {
				$n = (int) $part;
				if ( isset( $valid[ $n ] ) && ! isset( $used[ $n ] ) ) {
					$html       .= self::figure( $valid[ $n ] );
					$used[ $n ] = true;
				}
				continue;
			}
			if ( '' !== trim( $part ) ) {
				$html .= Posts::safe_text_html( trim( $part ) ) . "\n";
			}
		}
		foreach ( $valid as $n => $ph ) {
			if ( ! isset( $used[ $n ] ) ) {
				$html .= self::figure( $ph );
			}
		}
		return $html;
	}

	/**
	 * One photo with its caption.
	 *
	 * @param array $ph { id, caption, alt }.
	 * @return string
	 */
	private static function figure( array $ph ) {
		$img = wp_get_attachment_image(
			$ph['id'],
			'large',
			false,
			array(
				'alt'     => $ph['alt'],
				'loading' => 'lazy',
				'class'   => 'og-journal-img',
			)
		);
		return "\n<figure class=\"og-journal-photo\">" . $img . ( '' !== $ph['caption'] ? '<figcaption>' . esc_html( $ph['caption'] ) . '</figcaption>' : '' ) . "</figure>\n";
	}

	/**
	 * Upload one photo for a journal entry (owner only).
	 *
	 * @param array $file Uploaded file.
	 * @return array|WP_Error { id, url }
	 */
	public static function upload_photo( $file ) {
		if ( empty( $file ) || ! empty( $file['error'] ) ) {
			return new WP_Error( 'stubli_no_photo', __( 'Choose a photo first.', 'stubli' ), array( 'status' => 400 ) );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'] ?? '', $file['name'] ?? '' );
		if ( ! in_array( $check['type'], Posts::PHOTO_TYPES, true ) ) {
			return new WP_Error( 'stubli_not_a_photo', __( 'That file isn\'t a photo. Try a JPEG, PNG or WebP image.', 'stubli' ), array( 'status' => 400 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_sideload( $file, 0 );
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'stubli_upload_failed', __( 'That photo didn\'t upload. Try again in a moment.', 'stubli' ), array( 'status' => 400 ) );
		}
		$src = wp_get_attachment_image_src( $id, 'medium' );
		return array(
			'id'  => (int) $id,
			'url' => $src ? $src[0] : wp_get_attachment_url( $id ),
		);
	}
}
