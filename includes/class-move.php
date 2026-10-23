<?php
/**
 * "Move to another site": copy a whole Stubli to a new WordPress, then let
 * everyone who follows it switch to the new address by themselves.
 *
 * Old site: the owner makes a move link (valid 7 days). It unlocks a few
 * read-only endpoints that hand out the profile, settings, people, guest
 * book and every post, including "Only me" journal entries.
 *
 * New site: the owner pastes the link (in the setup or in You). The new site
 * copies everything in small steps, photos and videos at full size. At the
 * end it tells the old site where it went; the old site then says
 * "moved_to" in its profile, and followers' sites switch over on their next
 * check, once the new site confirms "moved_from".
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Moving a Stubli.
 */
class Move {

	const OUT     = 'stubli_move_out';
	const IN      = 'stubli_move_in';
	const DAYS    = 7;
	const HEADER  = 'X-Stubli-Move';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'template_redirect', array( self::class, 'link_page' ) );
	}

	/* ── Old site ── */

	/**
	 * Make a new move link (the previous one stops working).
	 *
	 * @return array
	 */
	public static function create_link() {
		$token = wp_generate_password( 32, false );
		$out   = (array) get_option( self::OUT, array() );
		$out['hash']    = wp_hash( $token, 'nonce' );
		$out['expires'] = time() + self::DAYS * DAY_IN_SECONDS;
		update_option( self::OUT, $out, false );
		return array(
			'link'    => add_query_arg( 'stubli_move', $token, home_url( '/' ) ),
			'expires' => wp_date( 'j F', $out['expires'] ),
		) + self::out_status();
	}

	/**
	 * State of the old-site side, for the app.
	 *
	 * @return array
	 */
	public static function out_status() {
		$out = (array) get_option( self::OUT, array() );
		return array(
			'has_link' => ! empty( $out['hash'] ) && (int) ( $out['expires'] ?? 0 ) > time(),
			'moved_to' => (string) get_option( 'stubli_moved_to', '' ),
		);
	}

	/**
	 * Stop the move link.
	 */
	public static function cancel_link() {
		$out = (array) get_option( self::OUT, array() );
		unset( $out['hash'], $out['expires'] );
		update_option( self::OUT, $out, false );
	}

	/**
	 * Does this request carry the current move link?
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return bool|\WP_Error
	 */
	public static function token_ok( $r ) {
		$token = (string) $r->get_header( 'x_stubli_move' );
		if ( '' === $token ) {
			$token = (string) $r['token'];
		}
		$out = (array) get_option( self::OUT, array() );
		if ( '' === $token || empty( $out['hash'] ) || (int) ( $out['expires'] ?? 0 ) < time() || ! hash_equals( (string) $out['hash'], wp_hash( $token, 'nonce' ) ) ) {
			return new \WP_Error( 'stubli_move_link', __( 'That move link isn\'t valid any more. Make a new one on your old Stubli: You → Settings and more → Move to another site.', 'stubli' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Remove local file paths from data that leaves this site.
	 *
	 * @param mixed $v Data.
	 * @return mixed
	 */
	private static function public_data( $v ) {
		if ( is_array( $v ) ) {
			unset( $v['path'] );
			return array_map( array( self::class, 'public_data' ), $v );
		}
		return $v;
	}

	/**
	 * Everything except the posts themselves.
	 *
	 * @return array
	 */
	public static function manifest() {
		$posts = array();
		foreach ( Bundle::post_ids() as $id ) {
			$f       = get_post_format( $id );
			$posts[] = array(
				'id'   => $id,
				'type' => 'status' === $f ? 'journal' : 'photo',
			);
		}
		return self::public_data(
			array(
				'format'    => Bundle::FORMAT,
				'generator' => 'stubli/' . VERSION,
				'site'      => home_url( '/' ),
				'profile'   => Bundle::profile(),
				'settings'  => Bundle::settings(),
				'following' => Bundle::follows(),
				'guestbook' => Bundle::guestbook(),
				'posts'     => $posts,
			)
		);
	}

	/**
	 * The new site says it's done. Check it really took this Stubli, then
	 * point everyone there.
	 *
	 * @param string $site New site's address.
	 * @return array|\WP_Error
	 */
	public static function done( $site ) {
		$site = esc_url_raw( (string) $site );
		if ( ! $site || Follows::origin( $site ) === Follows::origin( home_url() ) ) {
			return new \WP_Error( 'stubli_move_bad_site', __( 'That isn\'t a different site.', 'stubli' ), array( 'status' => 400 ) );
		}
		$res  = Rest::remote( $site, '/profile', array( 'timeout' => 15 ) );
		$data = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) || Follows::origin( (string) ( $data['moved_from'] ?? '' ) ) !== Follows::origin( home_url() ) ) {
			return new \WP_Error( 'stubli_move_unproven', __( 'The new site couldn\'t be confirmed.', 'stubli' ), array( 'status' => 400 ) );
		}
		update_option( 'stubli_moved_to', trailingslashit( Follows::origin( (string) $data['url'] ) ) );
		return array( 'moved_to' => (string) get_option( 'stubli_moved_to' ) );
	}

	/**
	 * Someone opened a move link in a browser: tell them what it's for.
	 */
	public static function link_page() {
		if ( ! isset( $_GET['stubli_move'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wp_die(
			esc_html__( 'This is a move link for this Stubli. Open the Stubli app on your new site and paste it under "Moving here from another Stubli?" (in the setup, or under You → Settings and more → Move to another site). Keep it private: it lets the new site copy your photos, journal, guest book and people, including entries only you can see.', 'stubli' ),
			esc_html__( 'Move link', 'stubli' ),
			array( 'response' => 200 )
		);
	}

	/* ── New site ── */

	/**
	 * Current import (or null).
	 *
	 * @return array|null
	 */
	public static function job() {
		$j = get_option( self::IN );
		return is_array( $j ) ? $j : null;
	}

	/**
	 * Import state for the app.
	 *
	 * @return array
	 */
	public static function in_status() {
		$j = self::job();
		if ( ! $j ) {
			return array( 'state' => 'none' );
		}
		return array(
			'state'   => $j['queue'] ? 'moving' : 'done',
			'from'    => $j['source'],
			'done'    => (int) $j['total'] - count( $j['queue'] ),
			'total'   => (int) $j['total'],
			'skipped' => (int) $j['skipped'],
			'told'    => ! empty( $j['told'] ),
		);
	}

	/**
	 * Call the old site.
	 *
	 * @param array  $j      Job (source, token).
	 * @param string $route  Route.
	 * @param string $method GET or POST.
	 * @param array  $body   POST body.
	 * @return array|\WP_Error Decoded JSON.
	 */
	private static function call( array $j, $route, $method = 'GET', array $body = array() ) {
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array( self::HEADER => $j['token'] ),
		);
		if ( $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}
		$res = Rest::remote( $j['source'], $route, $args );
		if ( is_wp_error( $res ) ) {
			return new \WP_Error( 'stubli_move_offline', __( 'Your old Stubli couldn\'t be reached. Check that it\'s still online, then try again.', 'stubli' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error( 'stubli_move_link', __( 'That move link isn\'t valid any more. Make a new one on your old Stubli: You → Settings and more → Move to another site.', 'stubli' ), array( 'status' => 401 ) );
		}
		if ( 200 !== $code || ! is_array( $data ) ) {
			return new \WP_Error( 'stubli_move_old', __( 'Your old site didn\'t answer like a Stubli. Update Stubli there to the newest version, then try again.', 'stubli' ), array( 'status' => 502 ) );
		}
		return $data;
	}

	/**
	 * Start copying from a move link.
	 *
	 * @param string $link Move link.
	 * @return array|\WP_Error
	 */
	public static function start( $link ) {
		$link  = trim( (string) $link );
		$parts = wp_parse_url( $link );
		parse_str( (string) ( $parts['query'] ?? '' ), $q );
		if ( empty( $parts['host'] ) || empty( $q['stubli_move'] ) ) {
			return new \WP_Error( 'stubli_move_paste', __( 'That doesn\'t look like a move link. On your old Stubli, open You → Settings and more → Move to another site, then copy the link it shows.', 'stubli' ), array( 'status' => 400 ) );
		}
		$source = ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . ( $parts['path'] ?? '/' );
		if ( Follows::origin( $source ) === Follows::origin( home_url() ) ) {
			return new \WP_Error( 'stubli_move_same', __( 'That\'s this site\'s own move link. Paste it into your new Stubli instead.', 'stubli' ), array( 'status' => 400 ) );
		}
		$j = array(
			'source'  => trailingslashit( $source ),
			'token'   => preg_replace( '/[^A-Za-z0-9]/', '', (string) $q['stubli_move'] ),
			'queue'   => array(),
			'total'   => 0,
			'map'     => array(),
			'skipped' => 0,
			'started' => time(),
		);
		$m = self::call( $j, '/move/manifest' );
		if ( is_wp_error( $m ) ) {
			return $m;
		}
		// So the old site can check this site really took over.
		update_option( 'stubli_moved_from', trailingslashit( Follows::origin( $j['source'] ) ) );

		self::apply_profile( (array) ( $m['profile'] ?? array() ), (array) ( $m['settings'] ?? array() ) );
		self::import_comments( (int) Guestbook::ensure_page(), (array) ( $m['guestbook'] ?? array() ), 'gb' );

		foreach ( (array) ( $m['following'] ?? array() ) as $f ) {
			$j['queue'][] = array( 'follow', $f );
		}
		foreach ( (array) ( $m['posts'] ?? array() ) as $p ) {
			$j['queue'][] = array( 'post', (int) $p['id'] );
		}
		$j['total'] = count( $j['queue'] );
		update_option( self::IN, $j, false );
		return self::in_status();
	}

	/**
	 * Name, bio, photo, colour, interests and settings.
	 *
	 * @param array $p Profile.
	 * @param array $s Settings.
	 */
	private static function apply_profile( array $p, array $s ) {
		if ( '' !== (string) ( $p['name'] ?? '' ) ) {
			update_option( 'blogname', sanitize_text_field( $p['name'] ) );
		}
		if ( isset( $p['tagline'] ) ) {
			update_option( 'blogdescription', sanitize_text_field( $p['tagline'] ) );
		}
		update_option( 'stubli_bio', mb_substr( sanitize_text_field( (string) ( $p['bio'] ?? '' ) ), 0, 300 ) );
		Settings::update( array( 'pronouns' => (string) ( $p['pronouns'] ?? '' ) ) );
		if ( isset( $p['prompts'] ) && is_array( $p['prompts'] ) ) {
			Prompts::save( $p['prompts'] );
		}
		update_option( 'stubli_links', sanitize_textarea_field( (string) ( $p['links'] ?? '' ) ) );
		Setup::save(
			array(
				'accent' => (string) ( $p['accent'] ?? '' ),
				'mode'   => (string) ( $p['mode'] ?? '' ),
			)
		);
		if ( ! empty( $p['interests'] ) && is_array( $p['interests'] ) ) {
			Setup::set_interests( array_map( 'strval', $p['interests'] ) );
		}
		if ( isset( $p['smiley']['c'] ) ) {
			update_option( 'stubli_avatar', Avatar::clean( $p['smiley']['c'], $p['smiley']['x'] ?? '' ), false );
		}
		if ( ! empty( $p['avatar']['url'] ) ) {
			$id = self::fetch( (string) $p['avatar']['url'], 0, (string) ( $p['avatar']['name'] ?? 'profile.jpg' ) );
			if ( ! is_wp_error( $id ) ) {
				update_option( 'site_icon', (int) $id );
			}
		}
		$keep = array_intersect_key( $s, array_flip( array( 'who_can_comment', 'on_break', 'block_ai', 'credit', 'digest_time', 'digest_email' ) ) );
		if ( $keep ) {
			Settings::update( $keep );
		}
		if ( isset( $s['blocked'] ) && is_array( $s['blocked'] ) ) {
			update_option( 'stubli_blocked', array_values( array_filter( array_map( 'esc_url_raw', $s['blocked'] ) ) ) );
		}
	}

	/**
	 * Download one file from the old site into the media library.
	 *
	 * @param string $url     File URL.
	 * @param int    $post_id Attach to this post.
	 * @param string $name    File name.
	 * @return int|\WP_Error Attachment ID.
	 */
	private static function fetch( $url, $post_id, $name ) {
		$file = self::download( $url, $name );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_sideload( $file, (int) $post_id );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $file['tmp_name'] );
		}
		return $id;
	}

	/**
	 * Download to a temporary file.
	 *
	 * @param string $url  URL.
	 * @param string $name File name.
	 * @return array|\WP_Error { name, tmp_name }
	 */
	private static function download( $url, $name ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = download_url( esc_url_raw( $url ), 300 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		return array(
			'name'     => sanitize_file_name( $name ? $name : wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ),
			'tmp_name' => $tmp,
		);
	}

	/**
	 * Copy approved comments onto a post (or the guest book).
	 *
	 * @param int    $post_id  Post ID.
	 * @param array  $comments Comments from the old site.
	 * @param string $scope    Key for duplicates.
	 */
	private static function import_comments( $post_id, array $comments, $scope ) {
		if ( ! $post_id ) {
			return;
		}
		$owner = get_current_user_id();
		$map   = array();
		foreach ( $comments as $c ) {
			$key = $scope . ':' . (int) $c['id'];
			if ( get_comments(
				array(
					'post_id'    => (int) $post_id,
					'meta_key'   => '_stubli_moved_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'count'      => true,
				)
			) ) {
				continue;
			}
			$time = (int) strtotime( (string) $c['date'] );
			$id   = wp_insert_comment(
				array(
					'comment_post_ID'      => (int) $post_id,
					'comment_author'       => sanitize_text_field( (string) $c['name'] ),
					'comment_author_url'   => esc_url_raw( (string) $c['url'] ),
					'comment_content'      => sanitize_textarea_field( (string) $c['text'] ),
					'comment_type'         => in_array( $c['type'] ?? '', array( 'comment', 'webmention', '' ), true ) ? (string) ( $c['type'] ?: 'comment' ) : 'comment',
					'comment_parent'       => (int) ( $map[ (int) $c['parent'] ] ?? 0 ),
					'comment_approved'     => 1,
					'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $time ),
					'comment_date'         => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $time ) ),
					'user_id'              => ! empty( $c['mine'] ) ? $owner : 0,
				)
			);
			if ( ! $id ) {
				continue;
			}
			$map[ (int) $c['id'] ] = (int) $id;
			update_comment_meta( $id, '_stubli_moved_id', $key );
			foreach ( (array) ( $c['meta'] ?? array() ) as $k => $v ) {
				if ( is_string( $k ) && preg_match( '/^[a-z0-9_]+$/', $k ) ) {
					update_comment_meta( $id, $k, is_scalar( $v ) ? sanitize_text_field( (string) $v ) : map_deep( $v, 'sanitize_text_field' ) );
				}
			}
		}
	}

	/**
	 * Copy the next item.
	 *
	 * @return array|\WP_Error
	 */
	public static function step() {
		$j = self::job();
		if ( ! $j ) {
			return new \WP_Error( 'stubli_move_none', __( 'Nothing is being moved.', 'stubli' ), array( 'status' => 404 ) );
		}
		$until = time() + 20;
		while ( $j['queue'] && time() < $until ) {
			list( $kind, $what ) = $j['queue'][0];
			$res                 = 'follow' === $kind ? self::copy_follow( (array) $what ) : self::copy_post( $j, (int) $what );
			if ( is_wp_error( $res ) && in_array( $res->get_error_code(), array( 'stubli_move_link', 'stubli_move_offline' ), true ) ) {
				update_option( self::IN, $j, false );
				return $res;
			}
			if ( is_wp_error( $res ) || false === $res ) {
				++$j['skipped'];
			}
			array_shift( $j['queue'] );
			update_option( self::IN, $j, false );
			if ( 'post' === $kind ) {
				break; // One post (with its photos and videos) per request.
			}
		}
		if ( ! $j['queue'] && empty( $j['told'] ) ) {
			$told      = self::call( $j, '/move/done', 'POST', array( 'site' => home_url( '/' ) ) );
			$j['told'] = ! is_wp_error( $told );
			update_option( self::IN, $j, false );
			update_option( 'stubli_setup_done', 1 );
		}
		return self::in_status();
	}

	/**
	 * Follow someone the old site followed.
	 *
	 * @param array $f Follow.
	 * @return bool|\WP_Error
	 */
	private static function copy_follow( array $f ) {
		$url = esc_url_raw( (string) ( $f['url'] ?? '' ) );
		if ( ! $url || Follows::origin( $url ) === Follows::origin( home_url() ) ) {
			return false;
		}
		if ( Follows::find_by_site( $url ) ) {
			return true;
		}
		$res = Follows::add( $url, (string) ( $f['interest'] ?? '' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$id = (int) ( $res['id'] ?? 0 );
		if ( $id ) {
			Follows::update(
				$id,
				array(
					'is_public' => ! empty( $f['is_public'] ),
					'muted'     => ! empty( $f['muted'] ),
				)
			);
		}
		return true;
	}

	/**
	 * Copy one post with its photos, videos and comments.
	 *
	 * @param array $j  Job.
	 * @param int   $id Post ID on the old site.
	 * @return int|\WP_Error|false New post ID.
	 */
	private static function copy_post( array $j, $id ) {
		$key      = 'move:' . md5( $j['source'] ) . ':' . $id;
		$existing = get_posts(
			array(
				'post_type'   => 'post',
				'post_status' => 'any',
				'meta_key'    => '_stubli_import_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		if ( $existing ) {
			return (int) $existing[0];
		}
		$p = self::call( $j, '/move/post/' . $id );
		if ( is_wp_error( $p ) ) {
			return $p;
		}
		$time = (int) strtotime( (string) $p['date'] );
		if ( 'photo' === $p['type'] ) {
			$files   = array();
			$alts    = array();
			$posters = array();
			$temp    = array();
			foreach ( (array) $p['photos'] as $i => $ph ) {
				$photo = self::download( (string) $ph['url'], (string) $ph['name'] );
				if ( is_wp_error( $photo ) ) {
					continue;
				}
				$temp[] = $photo['tmp_name'];
				if ( ! empty( $ph['video']['url'] ) ) {
					$video = self::download( (string) $ph['video']['url'], (string) $ph['video']['name'] );
					if ( ! is_wp_error( $video ) ) {
						$temp[]                          = $video['tmp_name'];
						$posters[ count( $files ) ]      = $photo;
						$files[]                         = $video;
						$alts[]                          = (string) $ph['alt'];
						continue;
					}
				}
				$files[] = $photo;
				$alts[]  = (string) $ph['alt'];
			}
			if ( ! $files ) {
				return false;
			}
			$res = Posts::create_photo_post(
				$files,
				$alts,
				(string) $p['caption'],
				(string) $p['collection'],
				(string) $p['note'],
				array(
					'date'       => $time,
					'import_key' => $key,
					'posters'    => $posters,
				)
			);
			foreach ( $temp as $t ) {
				if ( file_exists( $t ) ) {
					wp_delete_file( $t );
				}
			}
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$new = (int) $res['id'];
			if ( 'friends' === ( $p['audience'] ?? '' ) ) {
				Friends::set_audience( $new, 'friends' );
			} elseif ( 'private' === $p['status'] ) {
				wp_update_post(
					array(
						'ID'          => $new,
						'post_status' => 'private',
					)
				);
			}
		} else {
			$new = wp_insert_post(
				array(
					'post_status'  => 'private' === $p['status'] ? 'private' : 'publish',
					'post_title'   => sanitize_text_field( (string) $p['title'] ),
					'post_content' => '',
					'post_author'  => get_current_user_id(),
				) + Posts::date_fields( $time ),
				true
			);
			if ( is_wp_error( $new ) ) {
				return $new;
			}
			set_post_format( $new, 'journal' === $p['type'] ? 'status' : false );
			$html = (string) $p['html'];
			foreach ( (array) $p['photos'] as $ph ) {
				$aid = self::fetch( (string) $ph['url'], $new, (string) $ph['name'] );
				if ( is_wp_error( $aid ) ) {
					continue;
				}
				if ( '' !== (string) $ph['alt'] ) {
					update_post_meta( $aid, '_wp_attachment_image_alt', sanitize_text_field( (string) $ph['alt'] ) );
				}
				$img  = wp_get_attachment_image(
					$aid,
					'large',
					false,
					array(
						'alt'     => (string) $ph['alt'],
						'loading' => 'lazy',
						'class'   => 'og-journal-img',
					)
				);
				$html = Bundle::replace_img( $html, $ph, $img );
			}
			wp_update_post(
				array(
					'ID'           => $new,
					'post_content' => wp_kses_post( $html ),
				)
			);
			update_post_meta( $new, '_stubli_import_key', $key );
		}
		self::import_comments( $new, (array) ( $p['comments'] ?? array() ), 'p' . $id );
		return $new;
	}

	/**
	 * Forget a finished (or abandoned) import.
	 */
	public static function clear() {
		delete_option( self::IN );
	}

	/* ── Followers ── */

	/**
	 * A site we follow says it moved: follow the new address instead, once
	 * the new site confirms it came from there.
	 *
	 * @param array $follow  Follow row.
	 * @param array $profile Its profile.
	 * @return bool Switched.
	 */
	public static function follow_moved( array $follow, array $profile ) {
		$to = esc_url_raw( (string) ( $profile['moved_to'] ?? '' ) );
		if ( ! $to || Follows::origin( $to ) === Follows::origin( $follow['site_url'] ) ) {
			return false;
		}
		$res  = Rest::remote( $to, '/profile', array( 'timeout' => 15 ) );
		$data = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) || Follows::origin( (string) ( $data['moved_from'] ?? '' ) ) !== Follows::origin( $follow['site_url'] ) ) {
			return false;
		}
		global $wpdb;
		$new_site = trailingslashit( Follows::origin( (string) $data['url'] ) );
		if ( Follows::find_by_site( $new_site ) ) {
			Follows::remove( (int) $follow['id'] );
			return true;
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- this plugin's own tables.
		$wpdb->update(
			Install::follows_table(),
			array(
				'site_url' => $new_site,
				'feed_url' => esc_url_raw( (string) ( $data['feed'] ?? $new_site . 'feed/' ) ),
				'name'     => sanitize_text_field( (string) ( $data['name'] ?? $follow['name'] ) ),
			),
			array( 'id' => (int) $follow['id'] )
		);
		// The posts arrive again from the new address, with their dates.
		$wpdb->delete( Install::items_table(), array( 'follow_id' => (int) $follow['id'] ) );
		// phpcs:enable
		return true;
	}

	/**
	 * REST routes.
	 */
	public static function routes() {
		$owner = array( Rest::class, 'is_owner' );
		$token = array( self::class, 'token_ok' );
		$wrap  = static fn( $res ) => is_wp_error( $res ) ? $res : rest_ensure_response( $res );
		// Old site, for the owner.
		Rest::register(
			'/move/link',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn() => rest_ensure_response( self::out_status() ),
					'permission_callback' => $owner,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static fn() => rest_ensure_response( self::create_link() ),
					'permission_callback' => $owner,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => static function () {
						self::cancel_link();
						return rest_ensure_response( self::out_status() );
					},
					'permission_callback' => $owner,
				),
			)
		);
		// Old site, for the new site holding the link.
		Rest::register(
			'/move/manifest',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static fn() => rest_ensure_response( self::manifest() ),
				'permission_callback' => $token,
			)
		);
		Rest::register(
			'/move/post/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$p = Bundle::post( (int) $r['id'] );
					return $p ? rest_ensure_response( self::public_data( $p ) ) : new \WP_Error( 'stubli_not_found', 'Not found.', array( 'status' => 404 ) );
				},
				'permission_callback' => $token,
			)
		);
		Rest::register(
			'/move/done',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static fn( \WP_REST_Request $r ) => $wrap( self::done( (string) $r['site'] ) ),
				'permission_callback' => $token,
			)
		);
		// New site, for the owner.
		Rest::register(
			'/move/in',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn() => rest_ensure_response( self::in_status() ),
					'permission_callback' => $owner,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static fn( \WP_REST_Request $r ) => $wrap( self::start( (string) $r['link'] ) ),
					'permission_callback' => $owner,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => static function () {
						self::clear();
						return rest_ensure_response( self::in_status() );
					},
					'permission_callback' => $owner,
				),
			)
		);
		Rest::register(
			'/move/in/step',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static fn() => $wrap( self::step() ),
				'permission_callback' => $owner,
			)
		);
	}
}
