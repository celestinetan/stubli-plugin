<?php
/**
 * REST API used by the app (design decision Q6) and by other Stubli sites.
 *
 * @package Stubli
 */

namespace Stubli;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /wp-json/og/v1/.
 *
 * Public:  GET profile, following, journal, guestbook, posts/{id}/comments
 * Owner:   follows, sync, feed, comments, posts, journal (write), queue,
 *          guest book pins, blocks, settings, activity
 */
class Rest {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Only the site owner may use the app endpoints.
	 *
	 * @return bool
	 */
	/**
	 * Register a route at stubli/v1. Sites that ran Stubli before 0.17 also
	 * answer at the older og/v1, so friends' sites that haven't updated yet
	 * can still reach them.
	 *
	 * @param string $path Route.
	 * @param array  $args Route arguments.
	 */
	public static function register( $path, array $args ) {
		register_rest_route( REST_NS, $path, $args );
		if ( get_option( 'stubli_legacy_api' ) ) {
			register_rest_route( LEGACY_NS, $path, $args );
		}
	}

	/**
	 * Address of a route on another Stubli site.
	 *
	 * @param string $site  Site address.
	 * @param string $route Route, starting with a slash.
	 * @param array  $query Extra query arguments.
	 * @param string $ns    Namespace.
	 * @return string
	 */
	public static function remote_url( $site, $route, array $query = array(), $ns = REST_NS ) {
		return add_query_arg( array_merge( array( 'rest_route' => '/' . $ns . $route ), $query ), trailingslashit( $site ) );
	}

	/**
	 * Call another Stubli site's API: stubli/v1 first, then og/v1 for sites
	 * still on Stubli 0.16 or older.
	 *
	 * @param string $site  Site address.
	 * @param string $route Route, starting with a slash.
	 * @param array  $args  Arguments for wp_safe_remote_request().
	 * @param array  $query Extra query arguments.
	 * @return array|\WP_Error
	 */
	public static function remote( $site, $route, array $args = array(), array $query = array() ) {
		$res = null;
		foreach ( array( REST_NS, LEGACY_NS ) as $ns ) {
			$res = wp_safe_remote_request( self::remote_url( $site, $route, $query, $ns ), $args );
			if ( is_wp_error( $res ) || 404 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				break;
			}
			$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( ! is_array( $body ) || 'rest_no_route' !== ( $body['code'] ?? '' ) ) {
				break;
			}
		}
		return $res;
	}

	/**
	 * Owner of this site (can publish).
	 *
	 * @return bool
	 */
	public static function is_owner() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Wrap a result that may be a WP_Error.
	 *
	 * @param mixed $result Result.
	 * @return \WP_REST_Response|WP_Error
	 */
	private static function respond( $result ) {
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Register one route.
	 *
	 * @param string       $path    Route.
	 * @param string       $methods Methods.
	 * @param callable     $cb      Callback.
	 * @param bool         $public  Public route.
	 * @param array        $args    Arguments.
	 */
	private static function route( $path, $methods, $cb, $public = false, $args = array() ) {
		Rest::register(
			$path,
			array(
				'methods'             => $methods,
				'callback'            => $cb,
				'permission_callback' => $public ? '__return_true' : array( self::class, 'is_owner' ),
				'args'                => $args,
			)
		);
	}

	/**
	 * Register routes.
	 */
	public static function routes() {
		$id   = array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
		);
		$text = array(
			'type'     => 'string',
			'required' => true,
		);
		$url  = array(
			'type'     => 'string',
			'format'   => 'uri',
			'required' => true,
		);
		$str  = array(
			'type'    => 'string',
			'default' => '',
		);

		// ——— Public ———
		self::route( '/profile', WP_REST_Server::READABLE, static fn() => rest_ensure_response( Posts::profile() ), true );
		self::route( '/following', WP_REST_Server::READABLE, static fn() => rest_ensure_response( Follows::public_list() ), true );
		self::route(
			'/posts/(?P<id>\d+)/comments',
			WP_REST_Server::READABLE,
			static function ( WP_REST_Request $r ) {
				$post = get_post( (int) $r['id'] );
				if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
					return new WP_Error( 'stubli_not_found', __( 'Post not found.', 'stubli' ), array( 'status' => 404 ) );
				}
				return rest_ensure_response( Moderation::for_post( $post->ID ) );
			},
			true,
			$id
		);
		self::route( '/guestbook', WP_REST_Server::READABLE, static fn() => rest_ensure_response( Guestbook::entries() ), true );

		// ——— Invites (follow each other) ———
		self::route( '/invites', WP_REST_Server::CREATABLE, static fn() => rest_ensure_response( Invites::create() ) );
		self::route(
			'/invites/accept',
			WP_REST_Server::CREATABLE,
			static function ( WP_REST_Request $r ) {
				$n = (int) get_transient( 'stubli_invite_accepts' );
				if ( $n >= 20 ) {
					return new \WP_Error( 'stubli_rate_limited', __( 'Too many tries. Please wait a little.', 'stubli' ), array( 'status' => 429 ) );
				}
				set_transient( 'stubli_invite_accepts', $n + 1, HOUR_IN_SECONDS );
				return self::respond( Invites::accept_remote( (string) $r['token'], (string) $r['site'] ) );
			},
			true,
			array(
				'token' => array(
					'type'     => 'string',
					'required' => true,
				),
				'site'  => array(
					'type'     => 'string',
					'required' => true,
				),
			)
		);
		self::route(
			'/invites/proof',
			WP_REST_Server::READABLE,
			static fn( WP_REST_Request $r ) => self::respond( Invites::proof( (string) $r['token'] ) ),
			true,
			array(
				'token' => array(
					'type'     => 'string',
					'required' => true,
				),
			)
		);
		Rest::register(
			'/journal',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( Journal::entries( self::is_owner(), (int) $r['page'] ) ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'page' => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => static fn( WP_REST_Request $r ) => self::respond( Journal::create( (string) $r['text'], (string) $r['visibility'], is_array( $r['photos'] ) ? $r['photos'] : array() ) ),
					'permission_callback' => static fn() => current_user_can( 'publish_posts' ),
					'args'                => array(
						'text'       => $text,
						'visibility' => array(
							'type'    => 'string',
							'enum'    => array( 'everyone', 'only_me' ),
							'default' => 'everyone',
						),
						'photos'     => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'object' ),
						),
					),
				),
			)
		);

		Rest::register(
			'/journal/photos',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static fn( WP_REST_Request $r ) => self::respond( Journal::upload_photo( $r->get_file_params()['photo'] ?? array() ) ),
				'permission_callback' => static fn() => current_user_can( 'upload_files' ),
			)
		);

		// ——— Following and the reader ———
		Rest::register(
			'/follows',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static fn() => rest_ensure_response( array_map( static fn( $f ) => $f + array( 'friend' => Friends::is_friend( $f['site_url'] ) ), Follows::all() ) ),
					'permission_callback' => array( self::class, 'is_owner' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => static fn( WP_REST_Request $r ) => self::respond( Follows::add( (string) $r['url'], sanitize_text_field( (string) $r['list'] ) ) ),
					'permission_callback' => array( self::class, 'is_owner' ),
					'args'                => array(
						'url'  => $url,
						'list' => $str,
					),
				),
			)
		);
		Rest::register(
			'/follows/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => static function ( WP_REST_Request $r ) {
						$changes = array_intersect_key( $r->get_params(), array_flip( array( 'list_name', 'note', 'is_public', 'muted' ) ) );
						$row     = $changes ? Follows::update( (int) $r['id'], $changes ) : Follows::get( (int) $r['id'] );
						if ( ! $row ) {
							return new WP_Error( 'stubli_not_found', __( 'You don\'t follow that site.', 'stubli' ), array( 'status' => 404 ) );
						}
						$sent = null;
						if ( null !== $r['friend'] ) {
							$res = Friends::set( $row['site_url'], rest_sanitize_boolean( $r['friend'] ) );
							if ( is_wp_error( $res ) ) {
								return $res;
							}
							$sent = $res['sent'];
						}
						return rest_ensure_response( $row + array( 'friend' => Friends::is_friend( $row['site_url'] ), 'sent' => $sent ) );
					},
					'permission_callback' => array( self::class, 'is_owner' ),
					'args'                => $id,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( array( 'deleted' => Follows::remove( (int) $r['id'] ) ) ),
					'permission_callback' => array( self::class, 'is_owner' ),
					'args'                => $id,
				),
			)
		);
		self::route( '/sync', WP_REST_Server::CREATABLE, static fn() => rest_ensure_response( array( 'new_items' => Sync::poll_all() ) ) );
		self::route(
			'/feed',
			WP_REST_Server::READABLE,
			static fn( WP_REST_Request $r ) => rest_ensure_response( Sync::reader_feed( sanitize_text_field( (string) $r['list'] ), (int) $r['page'] ) ),
			false,
			array(
				'list' => $str,
				'page' => array(
					'type'    => 'integer',
					'default' => 1,
					'minimum' => 1,
				),
			)
		);

		// ——— Comments you write ———
		self::route(
			'/items/(?P<id>\d+)/comments',
			WP_REST_Server::CREATABLE,
			static fn( WP_REST_Request $r ) => self::respond( Replies::comment_on_item( (int) $r['id'], (string) $r['text'] ) ),
			false,
			$id + array( 'text' => $text )
		);
		self::route(
			'/comments',
			WP_REST_Server::CREATABLE,
			static fn( WP_REST_Request $r ) => self::respond( Replies::comment_on_url( (string) $r['url'], (string) $r['text'] ) ),
			false,
			array(
				'url'  => $url,
				'text' => $text,
			)
		);
		self::route( '/replies/(?P<id>\d+)', WP_REST_Server::DELETABLE, static fn( WP_REST_Request $r ) => self::respond( Replies::delete( (int) $r['id'] ) ), false, $id );

		// ——— Comments you receive ———
		self::route( '/queue', WP_REST_Server::READABLE, static fn() => rest_ensure_response( Moderation::queue() ) );
		self::route(
			'/queue/(?P<id>\d+)',
			WP_REST_Server::CREATABLE,
			static fn( WP_REST_Request $r ) => self::respond( Moderation::act( (int) $r['id'], (string) $r['action'] ) ),
			false,
			$id + array(
				'action' => array(
					'type'     => 'string',
					'enum'     => array( 'show', 'later', 'block' ),
					'required' => true,
				),
			)
		);
		self::route(
			'/guestbook/(?P<id>\d+)/pin',
			'POST, DELETE',
			static fn( WP_REST_Request $r ) => rest_ensure_response( array( 'pinned' => Guestbook::pin( (int) $r['id'], 'DELETE' !== $r->get_method() ) && 'DELETE' !== $r->get_method() ) ),
			false,
			$id
		);
		self::route( '/activity', WP_REST_Server::READABLE, array( self::class, 'activity' ) );

		// ——— Blocks and settings ———
		Rest::register(
			'/blocks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static fn() => rest_ensure_response( Moderation::blocked() ),
					'permission_callback' => array( self::class, 'is_owner' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => static fn( WP_REST_Request $r ) => self::respond( Moderation::block( (string) $r['url'] ) ),
					'permission_callback' => array( self::class, 'is_owner' ),
					'args'                => array( 'url' => $url ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( Moderation::unblock( (string) $r['url'] ) ),
					'permission_callback' => array( self::class, 'is_owner' ),
					'args'                => array( 'url' => $url ),
				),
			)
		);
		Rest::register(
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static fn() => rest_ensure_response( Settings::get() ),
					'permission_callback' => array( self::class, 'is_owner' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( Settings::update( $r->get_params() ) ),
					'permission_callback' => array( self::class, 'is_owner' ),
				),
			)
		);

		// ——— Posting photos ———
		self::route(
			'/posts',
			WP_REST_Server::CREATABLE,
			array( self::class, 'create_post' ),
			false,
			array(
				'caption'      => $str,
				'collection'   => $str,
				'content_note' => $str,
				'alt'          => $str,
				'date'         => array( 'type' => 'integer', 'default' => 0 ),
				'import_key'   => $str,
			)
		);
	}

	/**
	 * Publish a photo or carousel. Files arrive as "photo" (one) or
	 * "photos[]" (up to ten), with "alts[]" in the same order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function create_post( WP_REST_Request $request ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'stubli_forbidden', __( 'You can\'t upload photos here.', 'stubli' ), array( 'status' => 403 ) );
		}
		$params = $request->get_file_params();
		$files  = array();
		if ( ! empty( $params['photos'] ) && is_array( $params['photos']['name'] ?? null ) ) {
			foreach ( array_keys( $params['photos']['name'] ) as $i ) {
				$files[] = array(
					'name'     => $params['photos']['name'][ $i ],
					'type'     => $params['photos']['type'][ $i ],
					'tmp_name' => $params['photos']['tmp_name'][ $i ],
					'error'    => $params['photos']['error'][ $i ],
					'size'     => $params['photos']['size'][ $i ],
				);
			}
		} elseif ( ! empty( $params['photo'] ) ) {
			$files[] = $params['photo'];
		}
		$posters = array();
		if ( ! empty( $params['posters'] ) && is_array( $params['posters']['name'] ?? null ) ) {
			foreach ( array_keys( $params['posters']['name'] ) as $i ) {
				$posters[ (int) $i ] = array(
					'name'     => $params['posters']['name'][ $i ],
					'type'     => $params['posters']['type'][ $i ],
					'tmp_name' => $params['posters']['tmp_name'][ $i ],
					'error'    => $params['posters']['error'][ $i ],
					'size'     => $params['posters']['size'][ $i ],
				);
			}
		}
		$alts = $request->get_param( 'alts' );
		$alts = is_array( $alts ) ? array_values( $alts ) : array( (string) $request['alt'] );

		return self::respond(
			Posts::create_photo_post(
				$files,
				$alts,
				(string) $request['caption'],
				(string) $request['collection'],
				(string) $request['content_note'],
				array(
					'date'       => (int) $request['date'],
					'import_key' => (string) $request['import_key'],
					'posters'    => $posters,
					'audience'   => 'friends' === (string) $request['audience'] ? 'friends' : 'everyone',
				)
			)
		);
	}

	/**
	 * Recent comments and guest book entries, with their status.
	 *
	 * @return \WP_REST_Response
	 */
	public static function activity() {
		$guestbook = Guestbook::page_id();
		$out       = array();
		$owner     = get_current_user_id();
		foreach ( get_comments( array( 'status' => 'all', 'number' => 50, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ) ) as $comment ) {
			if ( $owner && (int) $comment->user_id === $owner ) {
				continue; // Your own replies aren't news to you.
			}
			$item           = Moderation::public_comment( $comment );
			$item['post_title'] = html_entity_decode( get_the_title( (int) $comment->comment_post_ID ), ENT_QUOTES, 'UTF-8' );
			$item['post_url']   = get_permalink( (int) $comment->comment_post_ID );
			$item['status'] = wp_get_comment_status( $comment );
			$item['where']  = (int) $comment->comment_post_ID === $guestbook ? 'guestbook' : 'post';
			$item['post']   = (int) $comment->comment_post_ID;
			$out[]          = $item;
		}
		return rest_ensure_response( $out );
	}
}
