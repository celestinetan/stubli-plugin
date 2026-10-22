<?php
/**
 * The list of sites this site follows.
 *
 * @package Stubli
 */

namespace Stubli;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Following works by URL (design decision Q15): we fetch the page, check it
 * is a Stubli site, read its profile and remember its feed.
 */
class Follows {

	/**
	 * Discover a Stubli site from any of its URLs.
	 *
	 * @param string $url Any page on the site.
	 * @return array|WP_Error Profile data with 'feed' and 'url'.
	 */
	public static function discover( $url ) {
		$url = esc_url_raw( trim( $url ) );
		if ( ! $url || ! wp_http_validate_url( $url ) && ! self::allow_local_urls() ) {
			return new WP_Error( 'stubli_bad_url', __( 'That doesn\'t look like a website address.', 'stubli' ), array( 'status' => 400 ) );
		}

		$response = wp_safe_remote_get( $url, array( 'timeout' => 10, 'redirection' => 3 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'stubli_unreachable', __( 'We couldn\'t reach that site. Check the address and try again.', 'stubli' ), array( 'status' => 400 ) );
		}

		$profile_url = '';
		$tags        = new \WP_HTML_Tag_Processor( wp_remote_retrieve_body( $response ) );
		while ( $tags->next_tag( 'link' ) ) {
			if ( 'og-profile' === strtolower( (string) $tags->get_attribute( 'rel' ) ) ) {
				$profile_url = (string) $tags->get_attribute( 'href' );
				break;
			}
		}
		if ( ! $profile_url ) {
			return new WP_Error( 'stubli_not_stubli', __( 'That site isn\'t a Stubli site yet, so it can\'t be followed.', 'stubli' ), array( 'status' => 400 ) );
		}

		$profile = wp_safe_remote_get( esc_url_raw( $profile_url ), array( 'timeout' => 10 ) );
		$data    = is_wp_error( $profile ) ? null : json_decode( wp_remote_retrieve_body( $profile ), true );
		if ( ! is_array( $data ) || empty( $data['feed'] ) || empty( $data['url'] ) ) {
			return new WP_Error( 'stubli_bad_profile', __( 'That site\'s Stubli profile couldn\'t be read.', 'stubli' ), array( 'status' => 400 ) );
		}

		$site   = esc_url_raw( $data['url'] );
		$feed   = esc_url_raw( $data['feed'] );
		$origin = self::origin( $site );
		// The feed and Following list must live on the same site as the profile.
		if ( ! $origin || self::origin( $feed ) !== $origin ) {
			return new WP_Error( 'stubli_bad_profile', __( 'That site\'s Stubli profile couldn\'t be read.', 'stubli' ), array( 'status' => 400 ) );
		}
		$following = esc_url_raw( $data['following'] ?? '' );
		if ( $following && self::origin( $following ) !== $origin ) {
			$following = '';
		}

		return array(
			'url'       => $site,
			'feed'      => $feed,
			'following' => $following,
			'name'      => mb_substr( sanitize_text_field( $data['name'] ?? '' ), 0, 190 ),
			'avatar'    => esc_url_raw( $data['avatar'] ?? '' ),
		);
	}

	/**
	 * Follow a site.
	 *
	 * @param string $url       Any URL on the site.
	 * @param string $list_name Optional reader tab (design decision Q14).
	 * @return array|WP_Error The follow row.
	 */
	public static function add( $url, $list_name = '' ) {
		global $wpdb;

		if ( Moderation::is_blocked( $url ) ) {
			return new WP_Error( 'stubli_blocked', __( 'You\'ve blocked this site. Unblock it in Settings to follow it.', 'stubli' ), array( 'status' => 400 ) );
		}
		$profile = self::discover( $url );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		if ( self::origin( $profile['url'] ) === self::origin( home_url() ) ) {
			return new WP_Error( 'stubli_self', __( 'You can\'t follow your own site.', 'stubli' ), array( 'status' => 400 ) );
		}

		$existing = self::find_by_site( $profile['url'] );
		if ( $existing ) {
			return $existing;
		}

		$wpdb->insert(
			Install::follows_table(),
			array(
				'site_url'   => $profile['url'],
				'feed_url'   => $profile['feed'],
				'name'       => $profile['name'],
				'avatar_url' => $profile['avatar'],
				'list_name'  => sanitize_text_field( $list_name ),
				'created'    => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$follow = self::get( (int) $wpdb->insert_id );
		if ( $follow ) {
			Sync::poll_one( $follow );
			$follow = self::get( (int) $follow['id'] );
		}
		return $follow;
	}

	/**
	 * Change how a followed site appears: its interest (one per person,
	 * Q14), your note on your public list, whether it's on that list, and
	 * whether it's muted in your reader.
	 *
	 * @param int   $id      Follow ID.
	 * @param array $changes list_name, note, is_public, muted.
	 * @return array|null Updated row.
	 */
	public static function update( $id, array $changes ) {
		global $wpdb;
		$data = array();
		if ( array_key_exists( 'list_name', $changes ) ) {
			$data['list_name'] = mb_substr( sanitize_text_field( (string) $changes['list_name'] ), 0, 100 );
		}
		if ( array_key_exists( 'note', $changes ) ) {
			$data['note'] = mb_substr( sanitize_text_field( (string) $changes['note'] ), 0, 200 );
		}
		if ( array_key_exists( 'is_public', $changes ) ) {
			$data['is_public'] = rest_sanitize_boolean( $changes['is_public'] ) ? 1 : 0;
		}
		if ( array_key_exists( 'muted', $changes ) ) {
			$data['muted'] = rest_sanitize_boolean( $changes['muted'] ) ? 1 : 0;
		}
		if ( $data ) {
			$wpdb->update( Install::follows_table(), $data, array( 'id' => (int) $id ) );
		}
		return self::get( $id );
	}

	/**
	 * The public "Sites I follow" list, for visitors and for other Stubli
	 * sites working out friends of friends.
	 *
	 * @return array[]
	 */
	public static function public_list() {
		$out = array();
		foreach ( self::all() as $follow ) {
			if ( ! (int) $follow['is_public'] ) {
				continue;
			}
			$out[] = array(
				'url'    => $follow['site_url'],
				'name'   => $follow['name'],
				'avatar' => $follow['avatar_url'],
				'note'   => $follow['note'],
			);
		}
		return $out;
	}

	/**
	 * Remember a followed site's own public Following list.
	 *
	 * @param array $follow Follow row.
	 */
	public static function refresh_their_follows( array $follow ) {
		global $wpdb;
		$origin   = self::origin( $follow['site_url'] );
		$endpoint = '';
		$profile  = wp_safe_remote_get( esc_url_raw( trailingslashit( $follow['site_url'] ) . '?rest_route=/' . LEGACY_NS . '/profile' ), array( 'timeout' => 10 ) );
		$data     = is_wp_error( $profile ) ? null : json_decode( wp_remote_retrieve_body( $profile ), true );
		if ( is_array( $data ) && ! empty( $data['following'] ) && self::origin( $data['following'] ) === $origin ) {
			$endpoint = esc_url_raw( $data['following'] );
		}
		if ( ! $endpoint ) {
			return;
		}
		$response = wp_safe_remote_get( $endpoint, array( 'timeout' => 10 ) );
		$list     = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $list ) ) {
			return;
		}
		$origins = array();
		foreach ( array_slice( $list, 0, 500 ) as $entry ) {
			$o = is_array( $entry ) && ! empty( $entry['url'] ) ? self::origin( (string) $entry['url'] ) : '';
			if ( $o ) {
				$origins[] = $o;
			}
		}
		$wpdb->update( Install::follows_table(), array( 'their_follows' => wp_json_encode( array_values( array_unique( $origins ) ) ) ), array( 'id' => (int) $follow['id'] ) );
	}

	/**
	 * Is this URL on a site followed by someone we follow (but not by us)?
	 * Design Q15/Q26: friends of friends may comment without waiting.
	 *
	 * @param string $url Any URL.
	 * @return bool
	 */
	public static function is_friend_of_friend( $url ) {
		$origin = self::origin( $url );
		if ( ! $origin ) {
			return false;
		}
		foreach ( self::all() as $follow ) {
			$theirs = json_decode( (string) $follow['their_follows'], true );
			if ( is_array( $theirs ) && in_array( $origin, $theirs, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Unfollow a site and drop its cached posts.
	 *
	 * @param int $id Follow ID.
	 * @return bool
	 */
	public static function remove( $id ) {
		global $wpdb;
		$wpdb->delete( Install::items_table(), array( 'follow_id' => (int) $id ), array( '%d' ) );
		return (bool) $wpdb->delete( Install::follows_table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * @param int $id Follow ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Install::follows_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param string $site_url Site home URL.
	 * @return array|null
	 */
	public static function find_by_site( $site_url ) {
		global $wpdb;
		$table = Install::follows_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE site_url = %s", $site_url ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @return array[]
	 */
	public static function all() {
		global $wpdb;
		$table = Install::follows_table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Is this URL on a site we follow? Compares scheme, host and port, so two
	 * sites on one domain with different ports are told apart.
	 *
	 * @param string $url Any URL.
	 * @return bool
	 */
	public static function is_followed( $url ) {
		$origin = self::origin( $url );
		if ( ! $origin ) {
			return false;
		}
		foreach ( self::all() as $follow ) {
			if ( self::origin( $follow['site_url'] ) === $origin ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * scheme://host[:port] of a URL, lowercased.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function origin( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return '';
		}
		$origin = strtolower( ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'] );
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}
		return $origin;
	}

	/**
	 * Local development only: allow following sites on localhost.
	 *
	 * @return bool
	 */
	private static function allow_local_urls() {
		return 'local' === wp_get_environment_type();
	}
}
