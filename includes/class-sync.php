<?php
/**
 * Background fetching of followed sites' feeds, and the reader feed.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Scheduled polling (design decision Q9): the server fetches followed feeds
 * every 15 minutes and stores new posts locally, so opening the app never
 * waits on other sites.
 */
class Sync {

	const MAX_ITEMS_PER_FETCH = 30;
	const MAX_CAPTION_LENGTH  = 2200;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( Install::CRON_HOOK, array( self::class, 'poll_all' ) );
	}

	/**
	 * Poll every followed site.
	 *
	 * @return int Number of new items stored.
	 */
	public static function poll_all() {
		$new = 0;
		foreach ( Follows::all() as $follow ) {
			$new += self::poll_one( $follow );
		}
		return $new;
	}

	/**
	 * Poll one followed site.
	 *
	 * @param array $follow Follow row.
	 * @return int Number of new items stored.
	 */
	public static function poll_one( array $follow ) {
		global $wpdb;

		if ( Demo::on() ) {
			return Demo::poll( $follow );
		}

		require_once ABSPATH . WPINC . '/feed.php';

		// WordGram stores what it fetches, so WordPress's own feed cache would
		// only hide new posts (and keep showing deleted ones) until it expires.
		$no_cache = static function ( $feed ) {
			$feed->enable_cache( false );
		};
		add_action( 'wp_feed_options', $no_cache );
		$feed = fetch_feed( Friends::feed_url( $follow ) );
		remove_action( 'wp_feed_options', $no_cache );

		$now = current_time( 'mysql', true );
		if ( is_wp_error( $feed ) ) {
			$wpdb->update(
				Install::follows_table(),
				array(
					'last_fetched' => $now,
					'last_error'   => $feed->get_error_message(),
				),
				array( 'id' => (int) $follow['id'] )
			);
			return 0;
		}

		$new    = 0;
		$table  = Install::items_table();
		$origin = Follows::origin( $follow['site_url'] );
		$seen   = array();
		$oldest = null;
		foreach ( $feed->get_items( 0, self::MAX_ITEMS_PER_FETCH ) as $item ) {
			$guid      = (string) ( $item->get_id() ? $item->get_id() : $item->get_permalink() );
			$permalink = (string) $item->get_permalink();
			if ( '' === $guid || '' === $permalink ) {
				continue;
			}
			// Only accept posts that live on the followed site itself, so a feed
			// can't point your likes and comments at someone else's posts.
			if ( Follows::origin( $permalink ) !== $origin ) {
				continue;
			}

			$seen[]    = substr( $guid, 0, 255 );
			$published = (int) $item->get_date( 'U' );
			$oldest    = null === $oldest ? $published : min( $oldest, $published );

			$images = self::with_videos( self::images_from_item( $item ), $item );
			$note   = '';
			$tags   = $item->get_item_tags( Posts::NS, 'contentNote' );
			if ( ! empty( $tags[0]['data'] ) ) {
				$note = mb_substr( sanitize_text_field( (string) $tags[0]['data'] ), 0, Posts::MAX_NOTE_LENGTH );
			}

			$aud      = $item->get_item_tags( Posts::NS, 'audience' );
			$audience = ! empty( $aud[0]['data'] ) && 'friends' === trim( (string) $aud[0]['data'] ) ? 'friends' : '';

			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE follow_id = %d AND guid = %s", $follow['id'], $guid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $exists ) {
				// The owner may have edited the caption, descriptions or content note.
				$wpdb->update(
					$table,
					array(
						'caption'      => self::safe_caption( (string) $item->get_content() ),
						'image_url'    => $images ? $images[0]['url'] : '',
						'images'       => wp_json_encode( $images ),
						'content_note' => $note,
						'audience'     => $audience,
						'permalink'    => esc_url_raw( $permalink ),
					),
					array( 'id' => (int) $exists ),
					array( '%s', '%s', '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'follow_id'    => (int) $follow['id'],
					'guid'         => substr( $guid, 0, 255 ),
					'permalink'    => esc_url_raw( $permalink ),
					'caption'      => self::safe_caption( (string) $item->get_content() ),
					'image_url'    => $images ? $images[0]['url'] : '',
					'images'       => wp_json_encode( $images ),
					'content_note' => $note,
					'audience'     => $audience,
					'published'    => gmdate( 'Y-m-d H:i:s', (int) $item->get_date( 'U' ) ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			++$new;
		}

		// A post that is missing from the feed but newer than its oldest item was
		// deleted by its owner, so it leaves the reader too.
		if ( $seen && null !== $oldest ) {
			$marks = implode( ',', array_fill( 0, count( $seen ), '%s' ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE follow_id = %d AND published >= %s AND guid NOT IN ({$marks})", array_merge( array( (int) $follow['id'], gmdate( 'Y-m-d H:i:s', $oldest ) ), $seen ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		$wpdb->update(
			Install::follows_table(),
			array(
				'last_fetched' => $now,
				'last_error'   => null,
			),
			array( 'id' => (int) $follow['id'] )
		);

		// Their public Following list changes slowly; refresh it a few times a day.
		$key = 'stubli_their_follows_' . (int) $follow['id'];
		if ( ! get_transient( $key ) ) {
			Follows::refresh_their_follows( $follow );
			set_transient( $key, 1, 6 * HOUR_IN_SECONDS );
		}

		return $new;
	}

	/**
	 * Every photo of a feed item (a carousel has up to ten), with its
	 * description for people who can't see it.
	 *
	 * @param \SimplePie\Item|\SimplePie_Item $item Feed item.
	 * @return array[] Each { url, alt }.
	 */
	private static function images_from_item( $item ) {
		$out  = array();
		$seen = array();
		foreach ( (array) $item->get_enclosures() as $enclosure ) {
			$link   = esc_url_raw( (string) $enclosure->get_link() );
			$medium = (string) $enclosure->get_medium();
			$type   = (string) $enclosure->get_type();
			if ( ! $link || isset( $seen[ $link ] ) || ! ( 'image' === $medium || 0 === strpos( $type, 'image/' ) ) ) {
				continue;
			}
			if ( ! in_array( wp_parse_url( $link, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
				continue;
			}
			$seen[ $link ] = true;
			$out[]         = array(
				'url' => $link,
				'alt' => mb_substr( sanitize_text_field( (string) $enclosure->get_description() ), 0, 500 ),
			);
			if ( count( $out ) >= Posts::MAX_PHOTOS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Attach each video to its poster image (stubli:video, plugin 0.15+).
	 *
	 * @param array  $images Images from the item.
	 * @param object $item   SimplePie item.
	 * @return array
	 */
	private static function with_videos( array $images, $item ) {
		$tags = (array) $item->get_item_tags( Posts::NS, 'video' );
		foreach ( $tags as $tag ) {
			$url    = esc_url_raw( trim( (string) ( $tag['data'] ?? '' ) ) );
			$poster = esc_url_raw( (string) ( $tag['attribs']['']['poster'] ?? '' ) );
			$type   = (string) ( $tag['attribs']['']['type'] ?? '' );
			if ( ! $url || ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) || ! in_array( $type, Posts::VIDEO_TYPES, true ) ) {
				continue;
			}
			foreach ( $images as $i => $im ) {
				if ( $im['url'] === $poster ) {
					$images[ $i ]['video'] = $url;
					$images[ $i ]['type']  = $type;
				}
			}
		}
		return $images;
	}

	/**
	 * A followed post's caption, keeping its links clickable (Q25) and
	 * nothing else. Links get rel="nofollow ugc" because they come from
	 * someone else's site.
	 *
	 * @param string $html Description from the feed.
	 * @return string Safe HTML.
	 */
	public static function safe_caption( $html ) {
		$html = Posts::drop_code( $html );
		$html = wp_kses( $html, array( 'a' => array( 'href' => true ), 'p' => array(), 'br' => array() ), array( 'http', 'https' ) );
		$html = trim( preg_replace( '#</p>\s*<p>#', '<br><br>', preg_replace( '#^<p>|</p>$#', '', trim( $html ) ) ) );
		$html = str_replace( array( '<p>', '</p>' ), '', $html );
		if ( Posts::visible_length( $html ) > self::MAX_CAPTION_LENGTH ) {
			$html = esc_html( mb_substr( trim( wp_strip_all_tags( $html ) ), 0, self::MAX_CAPTION_LENGTH ) );
		}
		return str_replace( '<a href=', '<a rel="nofollow ugc" href=', $html );
	}

	/**
	 * The reader's home feed, newest first (design decision Q14).
	 *
	 * @param string $list_name Optional tab; '' for All.
	 * @param int    $page      1-based page.
	 * @param int    $per_page  Items per page.
	 * @return array[]
	 */
	public static function reader_feed( $list_name = '', $page = 1, $per_page = 20 ) {
		global $wpdb;
		$items   = Install::items_table();
		$follows = Install::follows_table();
		$offset  = max( 0, ( (int) $page - 1 ) * (int) $per_page );

		$where  = 'WHERE f.muted = 0';
		$params = array();
		if ( '' !== $list_name ) {
			$where   .= ' AND f.list_name = %s';
			$params[] = $list_name;
		}
		$params[] = (int) $per_page;
		$params[] = $offset;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.*, f.name AS author_name, f.avatar_url AS author_avatar, f.site_url AS author_url, f.list_name AS list_name
				FROM {$items} i INNER JOIN {$follows} f ON f.id = i.follow_id
				{$where}
				ORDER BY i.published DESC
				LIMIT %d OFFSET %d",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable

		return array_map(
			static function ( $row ) {
				$images = json_decode( (string) $row['images'], true );
				if ( ! is_array( $images ) ) {
					$images = $row['image_url'] ? array( array( 'url' => $row['image_url'], 'alt' => '' ) ) : array();
				}
				return array(
					'id'           => (int) $row['id'],
					'permalink'    => $row['permalink'],
					'caption_html' => $row['caption'],
					'caption'      => html_entity_decode( wp_strip_all_tags( $row['caption'] ), ENT_QUOTES, 'UTF-8' ),
					'image'        => $images ? $images[0]['url'] : '',
					'images'       => $images,
					'content_note' => $row['content_note'],
					'audience'     => (string) ( $row['audience'] ?? '' ),
					'published'    => gmdate( 'c', (int) strtotime( $row['published'] . ' UTC' ) ),
					'my_comments'  => Replies::count_for_item( (int) $row['id'] ),
					'list'         => (string) $row['list_name'],
					'author'       => array(
						'name'   => $row['author_name'],
						'url'    => $row['author_url'],
						'avatar' => $row['author_avatar'],
					),
				);
			},
			$rows
		);
	}

	/**
	 * @param int $id Item ID.
	 * @return array|null
	 */
	public static function get_item( $id ) {
		global $wpdb;
		$table = Install::items_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
