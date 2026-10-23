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
		add_filter( 'request', array( self::class, 'route' ), 5 );
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
	 * /journal/ works even when the host hasn't refreshed its address rules
	 * (WordPress.com doesn't after a plugin update), like /links/ in the theme.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function route( $vars ) {
		if ( is_admin() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return $vars;
		}
		$uri  = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$base = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $base && 0 === strpos( $uri, $base ) ) {
			$uri = substr( $uri, strlen( $base ) );
		}
		if ( preg_match( '#^journal(?:/page/(\d+))?/?$#', trim( $uri, '/' ), $m ) ) {
			$out = array( self::QUERY_VAR => '1' );
			if ( ! empty( $m[1] ) ) {
				$out['paged'] = (int) $m[1];
			}
			return $out;
		}
		return $vars;
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
	public static function create( $text, $visibility = 'everyone', array $photos = array(), $html = null ) {
		$content = null !== $html ? self::sanitize_html( (string) $html, $photos ) : self::content_with_photos( (string) $text, $photos );
		$check   = self::check( $content );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$private = 'only_me' === $visibility;

		$post_id = wp_insert_post(
			array(
				'post_status'  => $private ? 'private' : 'publish',
				'post_title'   => wp_date( get_option( 'date_format' ) ),
				'post_content' => $content,
				'post_author'  => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		set_post_format( $post_id, 'status' );
		self::attach_photos( $post_id, $photos );

		return array(
			'id'         => $post_id,
			'url'        => get_permalink( $post_id ),
			'type'       => 'journal',
			'visibility' => $private ? 'only_me' : 'everyone',
		);
	}

	/**
	 * An entry needs words, and fits in 5,000 characters.
	 *
	 * @param string $content Entry HTML.
	 * @return true|WP_Error
	 */
	private static function check( $content ) {
		if ( '' === trim( wp_strip_all_tags( (string) $content ) ) ) {
			return new WP_Error( 'stubli_empty', __( 'Write a few words first.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( Posts::visible_length( $content ) > self::MAX_LENGTH ) {
			return new WP_Error( 'stubli_entry_too_long', __( 'That entry is over 5,000 characters. Trim a little and it will fit.', 'stubli' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Hang the entry's photos under it and save their descriptions.
	 *
	 * @param int   $post_id Entry.
	 * @param array $photos  Raw photo list.
	 */
	private static function attach_photos( $post_id, array $photos ) {
		foreach ( self::valid_photos( $photos ) as $ph ) {
			wp_update_post(
				array(
					'ID'          => $ph['id'],
					'post_parent' => $post_id,
				)
			);
			update_post_meta( $ph['id'], '_wp_attachment_image_alt', $ph['alt'] );
		}
	}

	/**
	 * Clean HTML from the journal editor: simple formatting only (headings,
	 * bold, italic, underline, strikethrough, lists, checklists, quotes, links), and each photo
	 * rebuilt from its upload, never from what the browser sent.
	 *
	 * @param string $html   Editor HTML.
	 * @param array  $photos Attached photos.
	 * @return string
	 */
	public static function sanitize_html( $html, array $photos ) {
		$valid = self::valid_photos( $photos );
		$html  = str_replace( "\u{200B}", '', Posts::drop_code( (string) $html ) );
		// Photos become markers first, so nothing inside them survives.
		$html = (string) preg_replace( '#<figure\b[^>]*\bdata-photo="(\d{1,2})"[^>]*>.*?</figure>#is', "\n[[stubli-photo:$1]]\n", $html );
		$html = (string) preg_replace( '#<figure\b[^>]*>.*?</figure>#is', '', $html );
		// Browsers write new lines as <div>; the page wants paragraphs.
		$html = (string) preg_replace( '#<div\b[^>]*>#i', '<p>', $html );
		$html = (string) preg_replace( '#</div\s*>#i', '</p>', $html );
		$html = (string) preg_replace( '#<(/?)b\b[^>]*>#i', '<$1strong>', $html );
		$html = (string) preg_replace( '#<(/?)i\b[^>]*>#i', '<$1em>', $html );
		$html = (string) preg_replace( '#<(/?)(?:strike|del)\b[^>]*>#i', '<$1s>', $html );
		$html = (string) preg_replace( '#<(/?)h[1-6]\b[^>]*>#i', '<$1h3>', $html );
		$html = wp_kses(
			$html,
			array(
				'p'          => array(),
				'br'         => array(),
				'strong'     => array(),
				'em'         => array(),
				'u'          => array(),
				's'          => array(),
				'h3'         => array(),
				'ul'         => array( 'class' => true ),
				'ol'         => array(),
				'li'         => array( 'data-checked' => true ),
				'blockquote' => array(),
				'a'          => array( 'href' => true ),
			),
			array( 'http', 'https' )
		);
		$html = make_clickable( $html );
		$html = wp_kses(
			$html,
			array(
				'p'          => array(),
				'br'         => array(),
				'strong'     => array(),
				'em'         => array(),
				'u'          => array(),
				's'          => array(),
				'h3'         => array(),
				'ul'         => array( 'class' => true ),
				'ol'         => array(),
				'li'         => array( 'data-checked' => true ),
				'blockquote' => array(),
				'a'          => array(
					'href' => true,
					'rel'  => true,
				),
			),
			array( 'http', 'https' )
		);
		// The only class kept is a checklist's, and a ticked item says so.
		$html = (string) preg_replace( '#<ul\s+class="(?![^"]*\bchecklist\b)[^"]*"#i', '<ul', $html );
		$html = (string) preg_replace( '#<ul\s+class="[^"]*"#i', '<ul class="checklist"', $html );
		$html = (string) preg_replace( '#<li\s+data-checked="(?!true")[^"]*"#i', '<li', $html );
		// Lists, headings and quotes never sit inside a paragraph.
		$html = (string) preg_replace( '#<p>\s*(?=<(?:ul|ol|h3|blockquote|p)\b)#i', '', $html );
		$html = (string) preg_replace( '#(</(?:ul|ol|h3|blockquote|p)>)\s*</p>#i', '$1', $html );
		$html = force_balance_tags( $html );
		// Text typed straight into the box, outside any paragraph.
		if ( ! preg_match( '#^\s*<(p|h3|ul|ol|blockquote)\b#i', $html ) && false === strpos( $html, '[[stubli-photo:' ) ) {
			$html = wpautop( $html );
		}
		$used = array();
		$html = (string) preg_replace_callback(
			'#(?:<p>\s*)?\[\[stubli-photo:(\d{1,2})\]\](?:\s*</p>)?#',
			static function ( $m ) use ( $valid, &$used ) {
				$n = (int) $m[1];
				if ( ! isset( $valid[ $n ] ) || isset( $used[ $n ] ) ) {
					return '';
				}
				$used[ $n ] = true;
				return self::figure( $valid[ $n ] );
			},
			$html
		);
		foreach ( $valid as $n => $ph ) {
			if ( ! isset( $used[ $n ] ) ) {
				$html .= self::figure( $ph );
			}
		}
		// Empty lines and empty paragraphs go; spacing comes from the page.
		$html = (string) preg_replace( '#<(p|h3|li|blockquote)>(?:\s|&nbsp;|<br\s*/?>)*</\1>#i', '', $html );
		$html = (string) preg_replace( '#<(ul|ol)>\s*</\1>#i', '', $html );
		return trim( $html );
	}

	/**
	 * The owner's own journal entry, or null.
	 *
	 * @param int  $id       Post ID.
	 * @param bool $trashed  Also find entries in the bin.
	 * @return \WP_Post|null
	 */
	private static function own_entry( $id, $trashed = false ) {
		$post = get_post( (int) $id );
		if ( ! $post || 'post' !== $post->post_type || ! has_post_format( 'status', $post ) ) {
			return null;
		}
		$ok = $trashed ? array( 'publish', 'private', 'trash' ) : array( 'publish', 'private' );
		if ( ! in_array( $post->post_status, $ok, true ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * When the entry was last edited (Unix time), or 0.
	 *
	 * @param int $id Post ID.
	 * @return int
	 */
	public static function edited( $id ) {
		return (int) get_post_meta( (int) $id, '_stubli_edited', true );
	}

	/**
	 * One entry, ready for the editor: its HTML with each photo as a
	 * numbered placeholder, and the photos with their captions.
	 *
	 * @param int $id Post ID.
	 * @return array|WP_Error
	 */
	public static function get( $id ) {
		$post = self::own_entry( $id );
		if ( ! $post ) {
			return new WP_Error( 'stubli_not_found', __( 'That entry isn\'t here any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		// Every size of every photo under the entry, to recognise older entries' photos.
		$by_url = array();
		foreach ( get_attached_media( 'image', $post->ID ) as $att ) {
			foreach ( array( 'large', 'medium', 'full', 'medium_large' ) as $size ) {
				$src = wp_get_attachment_image_src( $att->ID, $size );
				if ( $src ) {
					$by_url[ $src[0] ] = (int) $att->ID;
				}
			}
		}
		$photos = array();
		$html   = (string) preg_replace_callback(
			'#<figure class="og-journal-photo"[^>]*>(.*?)</figure>#is',
			static function ( $m ) use ( &$photos, $by_url ) {
				$id = 0;
				if ( preg_match( '#data-photo-id="(\d+)"#', $m[0], $d ) ) {
					$id = (int) $d[1];
				} elseif ( preg_match( '#<img[^>]+src="([^"]+)"#i', $m[1], $u ) && isset( $by_url[ html_entity_decode( $u[1] ) ] ) ) {
					$id = $by_url[ html_entity_decode( $u[1] ) ];
				}
				if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
					return '';
				}
				$n   = count( $photos ) + 1;
				$src = wp_get_attachment_image_src( $id, 'medium' );
				$cap = preg_match( '#<figcaption>(.*?)</figcaption>#is', $m[1], $c ) ? html_entity_decode( wp_strip_all_tags( $c[1] ), ENT_QUOTES ) : '';
				$photos[] = array(
					'n'       => $n,
					'id'      => $id,
					'url'     => $src ? $src[0] : (string) wp_get_attachment_url( $id ),
					'caption' => $cap,
					'alt'     => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
				);
				return '<figure data-photo="' . $n . '"></figure>';
			},
			(string) $post->post_content
		);
		return array(
			'id'         => (int) $post->ID,
			'html'       => $html,
			'photos'     => $photos,
			'visibility' => 'private' === $post->post_status ? 'only_me' : 'everyone',
			'date'       => get_post_time( 'c', true, $post ),
			'edited'     => self::edited( $post->ID ) ? gmdate( 'c', self::edited( $post->ID ) ) : '',
			'url'        => get_permalink( $post ),
		);
	}

	/**
	 * Change an entry. Its date stays; it gets an "Edited" time, and the
	 * earlier version is kept as a WordPress revision.
	 *
	 * @param int    $id         Post ID.
	 * @param string $html       Editor HTML.
	 * @param string $visibility "everyone" or "only_me".
	 * @param array  $photos     Attached photos.
	 * @return array|WP_Error
	 */
	public static function update( $id, $html, $visibility, array $photos ) {
		$post = self::own_entry( $id );
		if ( ! $post ) {
			return new WP_Error( 'stubli_not_found', __( 'That entry isn\'t here any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		$content = self::sanitize_html( (string) $html, $photos );
		$check   = self::check( $content );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$status = 'only_me' === $visibility ? 'private' : 'publish';
		if ( $content !== $post->post_content || $status !== $post->post_status ) {
			$res = wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_content' => $content,
					'post_status'  => $status,
				),
				true
			);
			if ( is_wp_error( $res ) ) {
				return new WP_Error( 'stubli_not_saved', __( 'That didn\'t save. Your changes are still here, so try again.', 'stubli' ), array( 'status' => 500 ) );
			}
			update_post_meta( $post->ID, '_stubli_edited', time() );
		}
		self::attach_photos( $post->ID, $photos );
		return self::get( $post->ID );
	}

	/**
	 * Move an entry to the bin (it can come back straight after).
	 *
	 * @param int $id Post ID.
	 * @return array|WP_Error
	 */
	public static function delete( $id ) {
		$post = self::own_entry( $id );
		if ( ! $post ) {
			return new WP_Error( 'stubli_not_found', __( 'That entry isn\'t here any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		wp_trash_post( $post->ID );
		return array(
			'deleted' => true,
			'id'      => (int) $post->ID,
		);
	}

	/**
	 * Bring an entry back from the bin, as it was.
	 *
	 * @param int $id Post ID.
	 * @return array|WP_Error
	 */
	public static function restore( $id ) {
		$post = self::own_entry( $id, true );
		if ( ! $post || 'trash' !== $post->post_status ) {
			return new WP_Error( 'stubli_not_found', __( 'That entry can\'t be brought back.', 'stubli' ), array( 'status' => 404 ) );
		}
		add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );
		wp_untrash_post( $post->ID );
		remove_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10 );
		return self::get( $post->ID );
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
					'edited'     => self::edited( $post->ID ) ? gmdate( 'c', self::edited( $post->ID ) ) : '',
					'excerpt'    => wp_html_excerpt( trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( str_replace( '</', ' </', $post->post_content ) ), ENT_QUOTES ) ) ), 140, '…' ),
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
		return "\n<figure class=\"og-journal-photo\" data-photo-id=\"" . (int) $ph['id'] . "\">" . $img . ( '' !== $ph['caption'] ? '<figcaption>' . esc_html( $ph['caption'] ) . '</figcaption>' : '' ) . "</figure>\n";
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
