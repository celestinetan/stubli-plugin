<?php
/**
 * Friends only posts (design Q44).
 *
 * The owner ticks some of the people they follow as Friends. Each post is for
 * Everyone or for Friends. A friends-only post is a WordPress *private* post
 * marked _stubli_audience = friends, so WordPress itself keeps it off the
 * public page, the public feed, search, sitemaps and the REST API.
 *
 * Each friend's site gets its own key. Their reader adds it to our feed
 * address (?sf=…), and only then does the feed include friends-only posts,
 * with links that carry the same key so the post and its comments work for
 * them. Unticking someone deletes their key; on their next check their
 * reader drops the friends-only posts.
 *
 * Honest wording in the app: "Friends only: shared with the people you
 * choose, not on your public page". Photos are files on the web like any
 * other; we never call this private or secure.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Friends.
 */
class Friends {

	const OURS   = 'stubli_friends';      // Our friends: origin => { hash, since, sent }.
	const THEIRS = 'stubli_friend_keys';  // Keys others gave us: origin => key.
	const META   = '_stubli_audience';
	const PARAM  = 'sf';

	/**
	 * The friend key on this request, once checked: the friend's origin, or ''.
	 *
	 * @var string|null
	 */
	private static $viewer = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'pre_get_posts', array( self::class, 'open_for_friend' ) );
		add_filter( 'the_posts', array( self::class, 'only_friends_posts' ), 10, 2 );
		add_filter( 'the_permalink_rss', array( self::class, 'keyed_link' ) );
		add_filter( 'post_comments_feed_link', array( self::class, 'keyed_link' ) );
		add_filter( 'webmention_post_id', array( self::class, 'webmention_target' ), 10, 2 );
		add_filter( 'webmention_server_url', array( self::class, 'find_endpoint' ), 10, 2 );
		add_action( 'send_headers', array( self::class, 'no_cache' ) );
		add_action( Install::CRON_HOOK, array( self::class, 'resend' ), 35 );
	}

	/* ── Posts ── */

	/**
	 * Is this a friends-only post?
	 *
	 * @param int|\WP_Post $post Post.
	 * @return bool
	 */
	public static function is_friends_post( $post ) {
		$post = get_post( $post );
		return $post && 'private' === $post->post_status && 'friends' === get_post_meta( $post->ID, self::META, true );
	}

	/**
	 * Set who a post is for.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $audience 'everyone' or 'friends'.
	 */
	public static function set_audience( $post_id, $audience ) {
		$friends = 'friends' === $audience;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		if ( $friends ) {
			update_post_meta( $post->ID, self::META, 'friends' );
			if ( 'private' !== $post->post_status ) {
				wp_update_post(
					array(
						'ID'          => $post->ID,
						'post_status' => 'private',
					)
				);
			}
		} elseif ( 'friends' === get_post_meta( $post->ID, self::META, true ) ) {
			delete_post_meta( $post->ID, self::META );
			wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => 'publish',
				)
			);
		}
	}

	/**
	 * Who a post is for.
	 *
	 * @param int|\WP_Post $post Post.
	 * @return string 'friends' or 'everyone'.
	 */
	public static function audience( $post ) {
		return self::is_friends_post( $post ) ? 'friends' : 'everyone';
	}

	/* ── Who is a friend ── */

	/**
	 * Our friends.
	 *
	 * @return array origin => { hash, since, sent }
	 */
	private static function ours() {
		$o = get_option( self::OURS, array() );
		return is_array( $o ) ? $o : array();
	}

	/**
	 * Is this site one of our friends?
	 *
	 * @param string $url Any URL on that site.
	 * @return bool
	 */
	public static function is_friend( $url ) {
		return isset( self::ours()[ Follows::origin( $url ) ] );
	}

	/**
	 * Tick or untick a friend.
	 *
	 * @param string $url Their site.
	 * @param bool   $on  Friend or not.
	 * @return array|\WP_Error { friend, sent }
	 */
	public static function set( $url, $on ) {
		$origin = Follows::origin( $url );
		if ( ! $origin || ! Follows::find_by_site( trailingslashit( $origin ) ) ) {
			return new \WP_Error( 'stubli_friend_follow', __( 'You can only choose friends among the people you follow.', 'stubli' ), array( 'status' => 400 ) );
		}
		$ours = self::ours();
		if ( ! $on ) {
			unset( $ours[ $origin ] );
			update_option( self::OURS, $ours, false );
			return array(
				'friend' => false,
				'sent'   => false,
			);
		}
		$key             = wp_generate_password( 32, false );
		$ours[ $origin ] = array(
			'hash'  => wp_hash( $key, 'nonce' ),
			'since' => time(),
			'sent'  => false,
			'key'   => $key, // Kept only until their site has it.
		);
		update_option( self::OURS, $ours, false );
		$sent = self::send( $origin );
		return array(
			'friend' => true,
			'sent'   => $sent,
		);
	}

	/**
	 * Give a friend's site its key.
	 *
	 * @param string $origin Their origin.
	 * @return bool Delivered.
	 */
	private static function send( $origin ) {
		$ours = self::ours();
		if ( empty( $ours[ $origin ]['key'] ) ) {
			return ! empty( $ours[ $origin ]['sent'] );
		}
		$res = Demo::person( $origin ) ? array( 'response' => array( 'code' => 200 ) ) : Rest::remote(
			$origin,
			'/friends/key',
			array(
				'method'  => 'POST',
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'site' => home_url( '/' ),
						'key'  => $ours[ $origin ]['key'],
					)
				),
			)
		);
		$ok = ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res );
		if ( $ok ) {
			$ours                       = self::ours();
			$ours[ $origin ]['sent']    = true;
			unset( $ours[ $origin ]['key'] );
			update_option( self::OURS, $ours, false );
		}
		return $ok;
	}

	/**
	 * Try again for friends whose site wasn't reachable (for a week).
	 */
	public static function resend() {
		foreach ( self::ours() as $origin => $f ) {
			if ( empty( $f['sent'] ) && ! empty( $f['key'] ) && time() - (int) $f['since'] < WEEK_IN_SECONDS ) {
				self::send( $origin );
			}
		}
	}

	/**
	 * Which friend does this key belong to?
	 *
	 * @param string $key Key.
	 * @return string Origin, or ''.
	 */
	public static function origin_for_key( $key ) {
		$key = preg_replace( '/[^A-Za-z0-9]/', '', (string) $key );
		if ( strlen( $key ) < 20 ) {
			return '';
		}
		$hash = wp_hash( $key, 'nonce' );
		foreach ( self::ours() as $origin => $f ) {
			if ( hash_equals( (string) $f['hash'], $hash ) ) {
				return (string) $origin;
			}
		}
		return '';
	}

	/**
	 * The friend whose key is on this request ('' when none or not valid).
	 *
	 * @return string
	 */
	public static function viewer() {
		if ( null === self::$viewer ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only key, checked against its hash.
			self::$viewer = isset( $_GET[ self::PARAM ] ) ? self::origin_for_key( sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) ) ) : '';
		}
		return self::$viewer;
	}

	/* ── Their side: keys we were given ── */

	/**
	 * A site says we're their friend. Check with that site, then keep the key.
	 *
	 * @param string $site Their site.
	 * @param string $key  Key.
	 * @return array|\WP_Error
	 */
	public static function receive( $site, $key ) {
		$site = esc_url_raw( (string) $site );
		$key  = preg_replace( '/[^A-Za-z0-9]/', '', (string) $key );
		if ( ! $site || strlen( $key ) < 20 ) {
			return new \WP_Error( 'stubli_friend_bad', 'Bad request.', array( 'status' => 400 ) );
		}
		$res  = Rest::remote(
			$site,
			'/friends/check',
			array( 'timeout' => 15 ),
			array(
				'key' => $key,
				'for' => home_url( '/' ),
			)
		);
		$data = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $data['ok'] ) ) {
			return new \WP_Error( 'stubli_friend_unproven', 'Not confirmed.', array( 'status' => 403 ) );
		}
		$theirs                              = (array) get_option( self::THEIRS, array() );
		$theirs[ Follows::origin( $site ) ] = $key;
		update_option( self::THEIRS, $theirs, false );
		return array( 'ok' => true );
	}

	/**
	 * Feed address for a site we follow, with our key when they made us a friend.
	 *
	 * @param array $follow Follow row.
	 * @return string
	 */
	public static function feed_url( array $follow ) {
		$theirs = (array) get_option( self::THEIRS, array() );
		$key    = $theirs[ Follows::origin( $follow['site_url'] ) ] ?? '';
		return $key ? add_query_arg( self::PARAM, rawurlencode( $key ), $follow['feed_url'] ) : $follow['feed_url'];
	}

	/* ── Serving friends-only posts to a friend ── */

	/**
	 * With a valid key, the feed and single post pages also look at private
	 * posts; the_posts() then keeps only the friends-only ones.
	 *
	 * @param \WP_Query $q Query.
	 */
	public static function open_for_friend( $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! ( $q->is_feed() || $q->is_singular() ) || ! self::viewer() ) {
			return;
		}
		$q->set( 'post_status', array( 'publish', 'private' ) );
	}

	/**
	 * Drop private posts that aren't for friends (for example "Only me"
	 * journal entries) from anything a friend's key opened.
	 *
	 * @param \WP_Post[] $posts Posts.
	 * @param \WP_Query  $q     Query.
	 * @return \WP_Post[]
	 */
	public static function only_friends_posts( $posts, $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! self::viewer() || current_user_can( 'read_private_posts' ) ) {
			return $posts;
		}
		return array_values( array_filter( $posts, static fn( $p ) => 'private' !== $p->post_status || self::is_friends_post( $p ) ) );
	}

	/**
	 * Links in a friend's feed carry their key, so the post opens for them.
	 *
	 * @param string $url Link.
	 * @return string
	 */
	public static function keyed_link( $url ) {
		$post = get_post();
		if ( self::viewer() && $post && self::is_friends_post( $post ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- already checked by viewer().
			return add_query_arg( self::PARAM, rawurlencode( sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ?? '' ) ) ), $url );
		}
		return $url;
	}

	/**
	 * Pages and feeds opened with a friend's key must not be cached for others.
	 */
	public static function no_cache() {
		if ( self::viewer() ) {
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
	}

	/**
	 * A friend's comment arrives as a Webmention to a keyed link: find the post.
	 *
	 * @param int    $id  Post ID found so far.
	 * @param string $url Target URL.
	 * @return int
	 */
	public static function webmention_target( $id, $url ) {
		if ( $id ) {
			return $id;
		}
		// The comment comes from a friend's reply page (no key on it, so the
		// key never becomes public): allow it when the sender is a friend.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Webmention requests carry no nonce; the sender's page is fetched and checked by the Webmention plugin.
		$source = isset( $_POST['source'] ) ? esc_url_raw( wp_unslash( $_POST['source'] ) ) : '';
		$url    = html_entity_decode( (string) $url, ENT_QUOTES );
		parse_str( (string) wp_parse_url( (string) $url, PHP_URL_QUERY ), $q );
		$keyed = ! empty( $q[ self::PARAM ] ) && self::origin_for_key( (string) $q[ self::PARAM ] );
		if ( ! $keyed && ! ( $source && self::is_friend( $source ) ) ) {
			return $id;
		}
		$path = trim( (string) wp_parse_url( (string) $url, PHP_URL_PATH ), '/' );
		if ( ! empty( $q['p'] ) ) {
			$post = get_post( (int) $q['p'] );
		} else {
			$post = $path ? get_page_by_path( basename( $path ), OBJECT, 'post' ) : null;
		}
		return $post && self::is_friends_post( $post ) ? (int) $post->ID : $id;
	}

	/* ── REST ── */

	/**
	 * Routes.
	 */
	public static function routes() {
		Rest::register(
			'/friends/key',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$res = self::receive( (string) $r['site'], (string) $r['key'] );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => '__return_true', // Checked by asking the sending site back.
			)
		);
		Rest::register(
			'/friends/check',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$origin = self::origin_for_key( (string) $r['key'] );
					return rest_ensure_response( array( 'ok' => '' !== $origin && Follows::origin( (string) $r['for'] ) === $origin ) );
				},
				'permission_callback' => '__return_true', // Only says yes or no for a key the caller already holds.
			)
		);
	}

	/**
	 * Comments on a friend's friends-only post are sent to the bare address
	 * (the key never goes on our public reply page), which is "not found" for
	 * everyone else. So look up their Webmention endpoint with our key.
	 *
	 * @param string|false $endpoint Endpoint found so far.
	 * @param string       $target   Page being replied to.
	 * @return string|false
	 */
	public static function find_endpoint( $endpoint, $target ) {
		if ( $endpoint || ! function_exists( 'webmention_discover_endpoint' ) ) {
			return $endpoint;
		}
		$keys = (array) get_option( self::THEIRS, array() );
		$key  = $keys[ Follows::origin( $target ) ] ?? '';
		if ( ! $key ) {
			return $endpoint;
		}
		return webmention_discover_endpoint( add_query_arg( self::PARAM, $key, $target ) );
	}
}
