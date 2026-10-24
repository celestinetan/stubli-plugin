<?php
/**
 * The site's own photo posts: creation, feed media, discovery and privacy.
 *
 * @package Stubli
 */

namespace Stubli;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stubli content is standard WordPress posts (design decision Q7):
 * one photo = "image" format, carousel = "gallery", journal entry = "status".
 * Collections are categories.
 */
class Posts {

	const MAX_PHOTOS         = 10;
	const MAX_CAPTION_LENGTH = 2200;
	const MAX_NOTE_LENGTH    = 80;
	const NS                 = 'urn:stubli:1';
	const PHOTO_TYPES        = array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic' );
	const VIDEO_TYPES        = array( 'video/mp4', 'video/quicktime' );
	const MAX_VIDEO_SECONDS  = 60;
	/** Atoms that hold where a video was filmed (QuickTime, 3GPP, some Android phones). */
	const LOCATION_ATOMS     = array( "\xA9xyz", 'loci', 'gps ' );

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'after_setup_theme', array( self::class, 'post_formats' ), 20 );
		add_action( 'wp_head', array( self::class, 'discovery_link' ) );
		add_action( 'rss2_ns', array( self::class, 'rss_namespace' ) );
		add_action( 'rss2_item', array( self::class, 'rss_item_extras' ) );
		add_filter( 'wp_handle_upload', array( self::class, 'strip_photo_metadata' ) );
		add_filter( 'wp_handle_upload', array( self::class, 'strip_video_upload' ) );
	}

	/**
	 * Enable the three Stubli post formats.
	 */
	public static function post_formats() {
		add_theme_support( 'post-formats', array( 'image', 'gallery', 'status' ) );
	}

	/**
	 * Advertise that this is a Stubli site. The reader only follows sites
	 * that expose this link (design decision Q4).
	 */
	public static function discovery_link() {
		printf(
			'<link rel="og-profile" href="%s" />' . "\n",
			esc_url( rest_url( REST_NS . '/profile' ) )
		);
	}

	/**
	 * Public profile data, served at /og/v1/profile.
	 *
	 * @return array
	 */
	public static function profile() {
		$avatar = Avatar::current();

		return array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'pronouns'    => (string) get_option( 'stubli_pronouns', '' ),
			'url'         => home_url( '/' ),
			'feed'        => get_feed_link( 'rss2' ),
			'following'   => rest_url( REST_NS . '/following' ),
			'guestbook'   => Guestbook::url(),
			'on_break'    => Settings::on_break(),
			'avatar'      => (string) $avatar,
			'cover'       => Setup::cover_url(),
			'bio'         => '' !== (string) get_option( 'stubli_bio', '' ) ? (string) get_option( 'stubli_bio', '' ) : get_bloginfo( 'description' ),
			'recent'      => self::recent_photos( 3 ),
			'prompts'     => Prompts::get(),
			'generator'   => 'stubli/' . VERSION,
			'moved_to'    => (string) get_option( 'stubli_moved_to', '' ),
			'moved_from'  => (string) get_option( 'stubli_moved_from', '' ),
		);
	}

	/**
	 * The newest public photo posts, for Discover cards on other sites.
	 *
	 * @param int $n How many.
	 * @return array List of { url, alt, link, note, date }.
	 */
	public static function recent_photos( $n = 3 ) {
		$out   = array();
		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => (int) $n,
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'post_format',
						'field'    => 'slug',
						'terms'    => array( 'post-format-status' ),
						'operator' => 'NOT IN',
					),
				),
			)
		);
		foreach ( $query->posts as $post ) {
			$ids = self::photo_ids( $post->ID );
			if ( ! $ids ) {
				continue;
			}
			$src   = wp_get_attachment_image_src( $ids[0], 'medium' );
			$out[] = array(
				'url'  => $src ? $src[0] : wp_get_attachment_url( $ids[0] ),
				'alt'  => (string) get_post_meta( $ids[0], '_wp_attachment_image_alt', true ),
				'link' => get_permalink( $post ),
				'note' => (string) get_post_meta( $post->ID, '_stubli_content_note', true ),
				'date' => get_post_time( 'c', true, $post ),
			);
		}
		return $out;
	}

	/**
	 * Caption and journal text keep plain text and links only (design Q25).
	 * Bare addresses become links; only http(s) links survive.
	 *
	 * @param string $text Raw text from the owner.
	 * @return string Safe HTML.
	 */
	public static function safe_text_html( $text ) {
		$text = self::drop_code( $text );
		$text = wp_kses( (string) $text, array( 'a' => array( 'href' => true ) ), array( 'http', 'https' ) );
		$text = make_clickable( $text );
		$text = wp_kses( $text, array( 'a' => array( 'href' => true, 'rel' => true ) ), array( 'http', 'https' ) );
		return wpautop( $text );
	}

	/**
	 * Remove script and style blocks entirely, contents included, so no
	 * leftover code text ends up in a caption.
	 *
	 * @param string $html Text or HTML.
	 * @return string
	 */
	public static function drop_code( $html ) {
		return (string) preg_replace( '#<(script|style|iframe|noscript)\b[^>]*>.*?</\1\s*>#is', '', (string) $html );
	}

	/**
	 * Length of what a reader sees, ignoring markup.
	 *
	 * @param string $text Text that may contain links.
	 * @return int
	 */
	public static function visible_length( $text ) {
		return mb_strlen( trim( wp_strip_all_tags( (string) $text ) ) );
	}

	/**
	 * Post date fields for an original (imported) time.
	 *
	 * @param int $timestamp Unix time.
	 * @return array
	 */
	public static function date_fields( $timestamp ) {
		$ts = (int) $timestamp;
		if ( $ts < 946684800 || $ts > time() + DAY_IN_SECONDS ) { // Only real dates from 2000 until now.
			return array();
		}
		return array(
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $ts ),
			'post_date'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ts ) ),
		);
	}

	/**
	 * Create a photo post (one photo, or a carousel of up to ten).
	 *
	 * Extra: 'date' (Unix time, for imports) and 'import_key' (makes the
	 * same import safe to run twice).
	 *
	 * @param array  $files      Uploaded files.
	 * @param array  $alts       Descriptions, same order.
	 * @param string $caption    Caption.
	 * @param string $collection Collection name.
	 * @param string $note       Content note.
	 * @param array  $extra      Extra options.
	 * @return array|WP_Error
	 */
	public static function create_photo_post( array $files, array $alts, $caption, $collection = '', $note = '', array $extra = array() ) {
		$import_key = isset( $extra['import_key'] ) ? substr( preg_replace( '/[^a-zA-Z0-9_:.-]/', '', (string) $extra['import_key'] ), 0, 120 ) : '';
		if ( '' !== $import_key ) {
			$existing = get_posts(
				array(
					'post_type'   => 'post',
					'post_status' => 'any',
					'meta_key'    => '_stubli_import_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'  => $import_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			if ( $existing ) {
				return array(
					'id'        => (int) $existing[0],
					'url'       => get_permalink( $existing[0] ),
					'duplicate' => true,
				);
			}
		}
		if ( ! $files ) {
			return new WP_Error( 'stubli_no_photo', __( 'Choose at least one photo.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( count( $files ) > self::MAX_PHOTOS ) {
			return new WP_Error( 'stubli_too_many_photos', __( 'A post can hold up to 10 photos.', 'stubli' ), array( 'status' => 400 ) );
		}
		$posters = isset( $extra['posters'] ) && is_array( $extra['posters'] ) ? $extra['posters'] : array();
		$videos  = self::check_media( $files, $posters );
		if ( is_wp_error( $videos ) ) {
			return $videos;
		}
		if ( self::visible_length( $caption ) > self::MAX_CAPTION_LENGTH ) {
			return new WP_Error( 'stubli_caption_too_long', __( 'That caption is a little long. Trim it to 2,200 characters and it will fit.', 'stubli' ), array( 'status' => 400 ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_status'   => 'publish',
				'post_title'    => wp_trim_words( wp_strip_all_tags( $caption ), 8, '…' ),
				'post_content'  => '' !== trim( $caption ) ? self::safe_text_html( $caption ) : '',
				'post_author'   => get_current_user_id(),
				'post_category' => $collection ? array( self::collection_id( $collection ) ) : array(),
			) + self::date_fields( $extra['date'] ?? 0 ),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$ids = self::sideload_media( $files, $alts, $posters, $videos, $post_id );
		if ( is_wp_error( $ids ) ) {
			wp_delete_post( $post_id, true );
			return $ids;
		}

		if ( '' !== $import_key ) {
			update_post_meta( $post_id, '_stubli_import_key', $import_key );
		}
		if ( '' !== trim( (string) $caption ) ) {
			update_post_meta( $post_id, '_stubli_caption', sanitize_textarea_field( (string) $caption ) );
		}
		set_post_thumbnail( $post_id, $ids[0] );
		update_post_meta( $post_id, '_stubli_photos', $ids );
		set_post_format( $post_id, count( $ids ) > 1 ? 'gallery' : 'image' );

		$note = mb_substr( sanitize_text_field( (string) $note ), 0, self::MAX_NOTE_LENGTH );
		if ( '' !== $note ) {
			update_post_meta( $post_id, '_stubli_content_note', $note );
		}
		if ( 'friends' === ( $extra['audience'] ?? '' ) ) {
			Friends::set_audience( $post_id, 'friends' );
		}

		return array(
			'id'     => $post_id,
			'url'    => get_permalink( $post_id ),
			'type'   => count( $ids ) > 1 ? 'carousel' : ( $videos ? 'video' : 'photo' ),
			'photos' => count( $ids ),
			'videos' => count( $videos ),
		);
	}

	/**
	 * Check uploaded photos and videos before anything is saved.
	 *
	 * @param array $files   Uploaded files.
	 * @param array $posters Cover pictures for videos, keyed by position.
	 * @return array|WP_Error Positions that are videos (position => true).
	 */
	public static function check_media( array $files, array $posters ) {
		$videos = array();
		foreach ( array_values( $files ) as $i => $file ) {
			$check = wp_check_filetype_and_ext( $file['tmp_name'] ?? '', $file['name'] ?? '' );
			if ( empty( $check['type'] ) && self::looks_like_video( $file ) ) {
				// The host doesn't accept video files (WordPress.com Personal, for one): say so plainly.
				return new WP_Error( 'stubli_video_not_allowed', self::no_video_message(), array( 'status' => 400 ) );
			}
			if ( in_array( $check['type'], self::VIDEO_TYPES, true ) ) {
				if ( empty( $posters[ $i ]['tmp_name'] ) ) {
					return new WP_Error( 'stubli_no_poster', __( 'That video arrived without its cover picture. Try sharing it again.', 'stubli' ), array( 'status' => 400 ) );
				}
				$pcheck = wp_check_filetype_and_ext( $posters[ $i ]['tmp_name'], $posters[ $i ]['name'] ?? 'poster.jpg' );
				if ( ! in_array( $pcheck['type'], self::PHOTO_TYPES, true ) ) {
					return new WP_Error( 'stubli_bad_poster', __( 'That video\'s cover picture couldn\'t be read. Try sharing it again.', 'stubli' ), array( 'status' => 400 ) );
				}
				$seconds = self::video_seconds( $file['tmp_name'] );
				if ( $seconds > self::MAX_VIDEO_SECONDS + 1 ) {
					return new WP_Error( 'stubli_video_too_long', __( 'Videos can be up to a minute long. Trim it on your phone, then share it again.', 'stubli' ), array( 'status' => 400 ) );
				}
				$videos[ $i ] = true;
				continue;
			}
			if ( ! in_array( $check['type'], self::PHOTO_TYPES, true ) ) {
				return new WP_Error( 'stubli_not_a_photo', __( 'That file isn\'t a photo or video. Try a JPEG, PNG or HEIC photo, or an MP4 or MOV video.', 'stubli' ), array( 'status' => 400 ) );
			}
		}
		return $videos;
	}

	/**
	 * Save checked photos and videos to a post's media. If one fails, the
	 * ones already saved from this batch are removed again.
	 *
	 * @param array $files   Uploaded files.
	 * @param array $alts    Descriptions, same order.
	 * @param array $posters Cover pictures for videos, keyed by position.
	 * @param array $videos  From check_media().
	 * @param int   $post_id Post ID.
	 * @return int[]|WP_Error Attachment IDs of the photos (video covers), in order.
	 */
	public static function sideload_media( array $files, array $alts, array $posters, array $videos, $post_id ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$ids       = array();
		$extra_ids = array();
		foreach ( array_values( $files ) as $i => $file ) {
			$video_id = 0;
			if ( isset( $videos[ $i ] ) ) {
				// The poster stands in for the video everywhere a photo is expected
				// (grid, Discover, older readers); the video hangs off it.
				$video_id = media_handle_sideload( $file, $post_id );
				$file     = $posters[ $i ];
				if ( ! is_wp_error( $video_id ) ) {
					$extra_ids[] = (int) $video_id;
				}
			}
			$attachment_id = is_wp_error( $video_id ) ? $video_id : media_handle_sideload( $file, $post_id );
			if ( is_wp_error( $attachment_id ) ) {
				foreach ( array_merge( $ids, $extra_ids ) as $done ) {
					wp_delete_attachment( $done, true );
				}
				return new WP_Error( 'stubli_upload_failed', $video_id ? __( 'That video didn\'t upload. It may be too large for your host; try a shorter clip.', 'stubli' ) : __( 'That photo didn\'t upload. Your caption is saved; try again when you\'re ready.', 'stubli' ), array( 'status' => 400 ) );
			}
			if ( $video_id ) {
				update_post_meta( $attachment_id, '_stubli_video', (int) $video_id );
			}
			$alt = sanitize_text_field( (string) ( $alts[ $i ] ?? '' ) );
			if ( '' !== $alt ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
			}
			$ids[] = (int) $attachment_id;
		}
		return $ids;
	}

	/**
	 * Category ID for a collection name, creating it if needed.
	 *
	 * @param string $name Collection name.
	 * @return int
	 */
	public static function collection_id( $name ) {
		$name = mb_substr( sanitize_text_field( $name ), 0, 40 );
		// "Photos" is how the untouched default category is shown; posting there means that one.
		$default = get_term( (int) get_option( 'default_category' ), 'category' );
		if ( $default && ! is_wp_error( $default ) && __( 'Photos', 'stubli' ) === $name && in_array( strtolower( $default->name ), array( 'uncategorized', 'uncategorised' ), true ) ) {
			return (int) $default->term_id;
		}
		$term = term_exists( $name, 'category' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'category' );
		}
		return is_wp_error( $term ) ? (int) get_option( 'default_category' ) : (int) $term['term_id'];
	}

	/**
	 * The photos of a post, in order.
	 *
	 * @param int $post_id Post ID.
	 * @return int[] Attachment IDs.
	 */
	public static function photo_ids( $post_id ) {
		$ids = array_filter( array_map( 'intval', (array) get_post_meta( $post_id, '_stubli_photos', true ) ) );
		if ( ! $ids && get_post_thumbnail_id( $post_id ) ) {
			$ids = array( (int) get_post_thumbnail_id( $post_id ) );
		}
		return array_values( $ids );
	}

	/**
	 * The video behind a poster, if this photo is a video's cover.
	 *
	 * @param int $photo_id Attachment ID of the photo (poster).
	 * @return int Video attachment ID, or 0.
	 */
	public static function video_for( $photo_id ) {
		$vid = (int) get_post_meta( (int) $photo_id, '_stubli_video', true );
		return ( $vid && 'attachment' === get_post_type( $vid ) ) ? $vid : 0;
	}

	/**
	 * The video types this site accepts, as WordPress and the host allow them.
	 * Empty when the host takes no video files (WordPress.com's Personal plan,
	 * for example, keeps video uploads for Premium and above).
	 *
	 * @return string[] MIME types from VIDEO_TYPES.
	 */
	public static function video_types_allowed() {
		return array_values( array_intersect( self::VIDEO_TYPES, array_values( get_allowed_mime_types() ) ) );
	}

	/**
	 * Whether this site runs on WordPress.com.
	 *
	 * @return bool
	 */
	public static function on_wpcom() {
		return ( defined( 'IS_ATOMIC' ) && IS_ATOMIC ) || ( defined( 'IS_WPCOM' ) && IS_WPCOM ) || defined( 'WPCOMSH_VERSION' );
	}

	/**
	 * What to tell the owner when their host takes no video files.
	 *
	 * @return string
	 */
	public static function no_video_message() {
		return self::on_wpcom()
			? __( 'Videos need WordPress.com Premium or above. Photos work on your plan.', 'stubli' )
			: __( 'Your host doesn\'t accept video files, so you can share photos here but not videos.', 'stubli' );
	}

	/**
	 * Whether an uploaded file is meant as a video (by its name or the type the phone gave it).
	 *
	 * @param array $file Uploaded file.
	 * @return bool
	 */
	private static function looks_like_video( $file ) {
		return (bool) preg_match( '/\.(mp4|m4v|mov|qt)$/i', (string) ( $file['name'] ?? '' ) ) || 0 === strpos( (string) ( $file['type'] ?? '' ), 'video/' );
	}

	/**
	 * A video's length in seconds (0 when it can't be read).
	 *
	 * @param string $path File path.
	 * @return float
	 */
	public static function video_seconds( $path ) {
		if ( ! function_exists( 'wp_read_video_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		$meta = wp_read_video_metadata( $path );
		return is_array( $meta ) && ! empty( $meta['length'] ) ? (float) $meta['length'] : 0.0;
	}

	/**
	 * Remove where a video was filmed, after upload and before anything
	 * else reads it. The app does this too; this catches other uploads.
	 *
	 * @param array $upload Upload result.
	 * @return array
	 */
	public static function strip_video_upload( $upload ) {
		if ( ! empty( $upload['file'] ) && ! empty( $upload['type'] ) && in_array( $upload['type'], self::VIDEO_TYPES, true ) ) {
			self::strip_video_location( $upload['file'] );
		}
		return $upload;
	}

	/**
	 * Blank the location in an MP4 or MOV file, in place. Location atoms are
	 * renamed to "free" (which every player skips), so nothing is re-encoded
	 * and the file keeps its size.
	 *
	 * @param string $path File path.
	 * @return int How many boxes were changed, or -1 if the file couldn't be opened.
	 */
	public static function strip_video_location( $path ) {
		$fh = @fopen( $path, 'r+b' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fh ) {
			return -1;
		}
		$size    = (int) filesize( $path );
		$off     = 0;
		$changed = 0;
		while ( $off + 8 <= $size ) {
			fseek( $fh, $off );
			$h = (string) fread( $fh, 16 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( strlen( $h ) < 8 ) {
				break;
			}
			$len  = unpack( 'N', substr( $h, 0, 4 ) )[1];
			$type = substr( $h, 4, 4 );
			$hl   = 8;
			if ( 1 === $len && strlen( $h ) >= 16 ) {
				$len = (int) unpack( 'J', substr( $h, 8, 8 ) )[1];
				$hl  = 16;
			} elseif ( 0 === $len ) {
				$len = $size - $off;
			}
			if ( $len < $hl ) {
				break;
			}
			if ( in_array( $type, array( 'moov', 'udta', 'meta' ), true ) && $len <= 64 * MB_IN_BYTES ) {
				fseek( $fh, $off );
				$buf  = (string) fread( $fh, $len ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				$orig = $buf;
				if ( 'moov' === $type || 'udta' === $type ) {
					$changed += self::scrub_boxes( $buf, $hl, strlen( $buf ) );
				} else {
					$changed += self::scrub_meta( $buf, $hl, strlen( $buf ) );
				}
				$buf = self::blank_coordinates( $buf );
				if ( $buf !== $orig && strlen( $buf ) === strlen( $orig ) ) {
					fseek( $fh, $off );
					fwrite( $fh, $buf ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
					++$changed;
				}
			}
			$off += $len;
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $changed;
	}

	/**
	 * Read one box header inside a buffer.
	 *
	 * @param string $b   Buffer.
	 * @param int    $p   Offset.
	 * @param int    $end End of the parent.
	 * @return array|null { len, type, hl }.
	 */
	private static function box_at( $b, $p, $end ) {
		if ( $p + 8 > $end ) {
			return null;
		}
		$len  = unpack( 'N', substr( $b, $p, 4 ) )[1];
		$type = substr( $b, $p + 4, 4 );
		$hl   = 8;
		if ( 1 === $len ) {
			if ( $p + 16 > $end ) {
				return null;
			}
			$len = (int) unpack( 'J', substr( $b, $p + 8, 8 ) )[1];
			$hl  = 16;
		} elseif ( 0 === $len ) {
			$len = $end - $p;
		}
		if ( $len < $hl || $p + $len > $end ) {
			return null;
		}
		return array(
			'len'  => $len,
			'type' => $type,
			'hl'   => $hl,
		);
	}

	/**
	 * Walk boxes and rename location atoms.
	 *
	 * @param string $b     Buffer (changed in place).
	 * @param int    $start Start of children.
	 * @param int    $end   End of children.
	 * @return int Changes.
	 */
	private static function scrub_boxes( &$b, $start, $end ) {
		$n = 0;
		$p = $start;
		while ( $box = self::box_at( $b, $p, $end ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( in_array( $box['type'], self::LOCATION_ATOMS, true ) ) {
				$b = substr_replace( $b, 'free', $p + 4, 4 );
				++$n;
			} elseif ( in_array( $box['type'], array( 'moov', 'trak', 'udta', 'mdia', 'minf', 'edts' ), true ) ) {
				$n += self::scrub_boxes( $b, $p + $box['hl'], $p + $box['len'] );
			} elseif ( 'meta' === $box['type'] ) {
				$n += self::scrub_meta( $b, $p + $box['hl'], $p + $box['len'] );
			}
			$p += $box['len'];
		}
		return $n;
	}

	/**
	 * A "meta" box: QuickTime keys (com.apple.quicktime.location.ISO6709)
	 * and iTunes-style items.
	 *
	 * @param string $b     Buffer (changed in place).
	 * @param int    $start Start of the meta payload.
	 * @param int    $end   End of the meta box.
	 * @return int Changes.
	 */
	private static function scrub_meta( &$b, $start, $end ) {
		// ISO meta boxes carry 4 bytes of version and flags; QuickTime ones don't.
		if ( 'hdlr' !== substr( $b, $start + 4, 4 ) ) {
			$start += 4;
		}
		$n   = 0;
		$loc = array();
		$p   = $start;
		while ( $box = self::box_at( $b, $p, $end ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( 'keys' === $box['type'] ) {
				$q     = $p + $box['hl'] + 4;
				$count = $q + 4 <= $end ? unpack( 'N', substr( $b, $q, 4 ) )[1] : 0;
				$q    += 4;
				for ( $i = 1; $i <= $count && $q + 8 <= $p + $box['len']; $i++ ) {
					$ksz = unpack( 'N', substr( $b, $q, 4 ) )[1];
					if ( $ksz < 8 ) {
						break;
					}
					if ( false !== stripos( substr( $b, $q + 8, $ksz - 8 ), 'location' ) ) {
						$loc[ $i ] = true;
					}
					$q += $ksz;
				}
			}
			$p += $box['len'];
		}
		$p = $start;
		while ( $box = self::box_at( $b, $p, $end ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( 'ilst' === $box['type'] ) {
				$q  = $p + $box['hl'];
				$qe = $p + $box['len'];
				while ( $item = self::box_at( $b, $q, $qe ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
					$idx = unpack( 'N', $item['type'] )[1];
					if ( isset( $loc[ $idx ] ) || in_array( $item['type'], self::LOCATION_ATOMS, true ) ) {
						$b = substr_replace( $b, 'free', $q + 4, 4 );
						++$n;
					}
					$q += $item['len'];
				}
			} elseif ( in_array( $box['type'], self::LOCATION_ATOMS, true ) ) {
				$b = substr_replace( $b, 'free', $p + 4, 4 );
				++$n;
			}
			$p += $box['len'];
		}
		return $n;
	}

	/**
	 * Last safety net: any ISO 6709 coordinates left in the metadata become zeros.
	 *
	 * @param string $b Buffer.
	 * @return string
	 */
	private static function blank_coordinates( $b ) {
		return (string) preg_replace_callback(
			'#[+-]\d{2}\.\d{2,}[+-]\d{3}\.\d{2,}(?:[+-]\d+(?:\.\d+)?)?(?:CRS[^/]*)?/#',
			static fn( $m ) => preg_replace( '/\d/', '0', $m[0] ),
			$b
		);
	}

	/**
	 * Declare the feed namespaces.
	 */
	public static function rss_namespace() {
		echo 'xmlns:media="http://search.yahoo.com/mrss/"' . "\n";
		echo 'xmlns:stubli="' . esc_attr( self::NS ) . '"' . "\n";
	}

	/**
	 * Add every photo (with its description) and the content note to the
	 * post's feed item, so readers can show the carousel faithfully.
	 */
	public static function rss_item_extras() {
		$post_id = get_the_ID();
		foreach ( self::photo_ids( $post_id ) as $id ) {
			$src = wp_get_attachment_image_src( $id, 'large' );
			if ( ! $src ) {
				continue;
			}
			$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			printf(
				"\t\t<media:content url=\"%s\" medium=\"image\" type=\"%s\" width=\"%d\" height=\"%d\">%s</media:content>\n",
				esc_url( $src[0] ),
				esc_attr( get_post_mime_type( $id ) ),
				(int) $src[1],
				(int) $src[2],
				$alt ? '<media:description>' . esc_html( $alt ) . '</media:description>' : ''
			);
		}
		foreach ( self::photo_ids( $post_id ) as $id ) {
			$vid = self::video_for( $id );
			$src = $vid ? wp_get_attachment_image_src( $id, 'large' ) : false;
			if ( $vid && $src ) {
				printf(
					"\t\t<stubli:video poster=\"%s\" type=\"%s\">%s</stubli:video>\n",
					esc_url( $src[0] ),
					esc_attr( get_post_mime_type( $vid ) ),
					esc_url( wp_get_attachment_url( $vid ) )
				);
			}
		}
		$note = (string) get_post_meta( $post_id, '_stubli_content_note', true );
		if ( '' !== $note ) {
			echo "\t\t<stubli:contentNote>" . esc_html( $note ) . "</stubli:contentNote>\n";
		}
		if ( Friends::is_friends_post( $post_id ) ) {
			echo "\t\t<stubli:audience>friends</stubli:audience>\n";
		}
	}

	/**
	 * Privacy by default: re-encode uploaded JPEGs so EXIF data, including
	 * GPS location, is removed. Orientation is applied first so the photo
	 * doesn't end up sideways.
	 *
	 * @param array $upload Upload result from wp_handle_upload.
	 * @return array
	 */
	public static function strip_photo_metadata( $upload ) {
		if ( empty( $upload['file'] ) || empty( $upload['type'] ) || 'image/jpeg' !== $upload['type'] ) {
			return $upload;
		}
		if ( ! apply_filters( 'stubli_strip_photo_metadata', true, $upload ) ) {
			return $upload;
		}

		$editor = wp_get_image_editor( $upload['file'] );
		if ( is_wp_error( $editor ) ) {
			return $upload;
		}
		if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$editor->maybe_exif_rotate();
		}
		$editor->set_quality( 90 );
		$editor->save( $upload['file'], 'image/jpeg' );

		return $upload;
	}
}
