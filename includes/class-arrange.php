<?php
/**
 * Arrange your page: the order of your collection tabs, and up to three
 * posts pinned to the top of each collection. Everything else stays newest
 * first, and followers' feeds are never reordered.
 *
 * Stored as options the page reads: stubli_coll_order (category IDs in the
 * owner's order), stubli_pins (category ID => post IDs) and stubli_hand
 * (category ID => post IDs, for collections arranged by hand: then the
 * whole collection follows that order, new posts first, and pins rest).
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Tab order and pins.
 */
class Arrange {

	const MAX_PINS = 3;

	const MAX_HAND = 500;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Photo post query arguments (image and gallery formats).
	 *
	 * @return array
	 */
	private static function photo_tax() {
		return Posts::tile_tax();
	}

	/**
	 * A collection's name ("Photos" for an untouched default category).
	 *
	 * @param \WP_Term $t Category.
	 * @return string
	 */
	private static function name( $t ) {
		if ( (int) $t->term_id === (int) get_option( 'default_category' ) && in_array( strtolower( $t->name ), array( 'uncategorized', 'uncategorised' ), true ) ) {
			return __( 'Photos', 'stubli' );
		}
		return html_entity_decode( $t->name, ENT_QUOTES );
	}

	/**
	 * Collections with at least one photo post, in the owner's order: arranged
	 * ones first, the rest biggest first (as before).
	 *
	 * @return array[]
	 */
	public static function collections() {
		$out = array();
		foreach ( get_categories( array( 'hide_empty' => true ) ) as $t ) {
			$q = new \WP_Query(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'cat'            => $t->term_id,
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'tax_query'      => self::photo_tax(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				)
			);
			if ( $q->found_posts > 0 ) {
				$out[] = array(
					'id'          => (int) $t->term_id,
					'name'        => self::name( $t ),
					'count'       => (int) $q->found_posts,
					'description' => wp_strip_all_tags( (string) $t->description ),
				);
			}
		}
		$order = array_map( 'intval', (array) get_option( 'stubli_coll_order', array() ) );
		usort(
			$out,
			static function ( $a, $b ) use ( $order ) {
				$ia = array_search( $a['id'], $order, true );
				$ib = array_search( $b['id'], $order, true );
				if ( false !== $ia && false !== $ib ) {
					return $ia - $ib;
				}
				if ( false !== $ia || false !== $ib ) {
					return false !== $ia ? -1 : 1;
				}
				return $b['count'] - $a['count'];
			}
		);
		$pins = self::all_pins();
		foreach ( $out as &$c ) {
			$c['pins'] = count( $pins[ $c['id'] ] ?? array() );
			$c['hand'] = self::is_hand( $c['id'] );
		}
		return $out;
	}

	/**
	 * The shape a collection's tiles take, from its first photo (the first
	 * pinned post, or else the newest): landscape, square or portrait.
	 * Matches what the page shows.
	 *
	 * @param int $cat Category ID.
	 * @return string
	 */
	public static function shape( $cat ) {
		if ( self::is_hand( $cat ) ) {
			$ord = self::ordered_ids( $cat );
			$id  = $ord ? $ord[0] : 0;
		} else {
			$pins = self::all_pins()[ (int) $cat ] ?? array();
			$id   = $pins ? $pins[0] : 0;
		}
		if ( ! $id ) {
			$q  = new \WP_Query(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'cat'            => (int) $cat,
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'tax_query'      => self::photo_tax(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				)
			);
			$id = $q->posts ? (int) $q->posts[0] : 0;
		}
		$ids = $id ? Posts::tile_ids( $id ) : array();
		if ( $ids ) {
			$m = wp_get_attachment_metadata( $ids[0] );
			if ( ! empty( $m['width'] ) && ! empty( $m['height'] ) ) {
				$r = (float) $m['width'] / (float) $m['height'];
				return $r > 1.15 ? 'landscape' : ( $r >= 0.9 ? 'square' : 'portrait' );
			}
		}
		return 'portrait';
	}

	/**
	 * Shapes by collection name, for the app's You tabs.
	 *
	 * @return array
	 */
	public static function shapes() {
		$out = array();
		foreach ( self::collections() as $c ) {
			$out[ $c['name'] ] = self::shape( $c['id'] );
		}
		return $out;
	}

	/**
	 * Pinned post IDs per collection, only ones still published there.
	 *
	 * @return array
	 */
	public static function all_pins() {
		$raw = (array) get_option( 'stubli_pins', array() );
		$out = array();
		foreach ( $raw as $cat => $ids ) {
			$keep = array();
			foreach ( (array) $ids as $id ) {
				$p = get_post( (int) $id );
				if ( $p && 'publish' === $p->post_status && has_category( (int) $cat, $p ) ) {
					$keep[] = (int) $id;
				}
			}
			if ( $keep ) {
				$out[ (int) $cat ] = array_slice( $keep, 0, self::MAX_PINS );
			}
		}
		return $out;
	}

	/**
	 * Is this collection arranged by hand?
	 *
	 * @param int $cat Category ID.
	 * @return bool
	 */
	public static function is_hand( $cat ) {
		$all = (array) get_option( 'stubli_hand', array() );
		return array_key_exists( (int) $cat, $all );
	}

	/**
	 * Every post in a collection, in the order the page shows them: by hand
	 * (posts added since come first, newest first) or else newest first.
	 * Pins are left to the caller.
	 *
	 * @param int $cat Category ID.
	 * @return int[]
	 */
	public static function ordered_ids( $cat ) {
		$q   = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'cat'            => (int) $cat,
				'posts_per_page' => self::MAX_HAND,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => self::photo_tax(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
		$ids = array_map( 'intval', $q->posts );
		if ( ! self::is_hand( $cat ) ) {
			return $ids;
		}
		$hand = array_map( 'intval', (array) ( ( (array) get_option( 'stubli_hand', array() ) )[ (int) $cat ] ?? array() ) );
		$kept = array_values( array_intersect( $hand, $ids ) );
		return array_merge( array_values( array_diff( $ids, $kept ) ), $kept );
	}

	/**
	 * Hand orders by collection name, for the app's You tab.
	 *
	 * @return array name => post IDs
	 */
	public static function hand_orders() {
		$out = array();
		foreach ( self::collections() as $c ) {
			if ( $c['hand'] ) {
				$out[ $c['name'] ] = self::ordered_ids( $c['id'] );
			}
		}
		return $out;
	}

	/**
	 * The newest posts in a collection, for choosing pins.
	 *
	 * @param int $cat Category ID.
	 * @return array[]
	 */
	public static function posts( $cat ) {
		$pins = self::all_pins()[ (int) $cat ] ?? array();
		$hand = self::is_hand( $cat );
		$ids  = self::ordered_ids( $cat );
		$list = $ids ? get_posts(
			array(
				'post__in'       => $hand ? $ids : array_slice( $ids, 0, 60 ),
				'orderby'        => 'post__in',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		) : array();
		$out  = array();
		foreach ( $list as $p ) {
			$ids   = Posts::tile_ids( $p->ID );
			$out[] = array(
				'id'     => (int) $p->ID,
				'title'  => Journal::is_filed( $p->ID ) ? ( Journal::title( $p->ID ) ? Journal::title( $p->ID ) : Journal::first_words( $p ) ) : html_entity_decode( get_the_title( $p ), ENT_QUOTES ),
				'thumb'  => $ids ? (string) wp_get_attachment_image_url( $ids[0], 'thumbnail' ) : '',
				'pinned' => array_search( (int) $p->ID, $pins, true ),
			);
		}
		$term = get_term( (int) $cat, 'category' );
		return array(
			'posts'       => $out,
			'pins'        => $pins,
			'max'         => self::MAX_PINS,
			'hand'        => $hand,
			'name'        => $term && ! is_wp_error( $term ) ? self::name( $term ) : '',
			'description' => $term && ! is_wp_error( $term ) ? wp_strip_all_tags( (string) $term->description ) : '',
		);
	}

	/**
	 * REST routes, owner only.
	 */
	public static function routes() {
		$owner = array( Rest::class, 'is_owner' );
		Rest::register(
			'/arrange',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn() => rest_ensure_response( array( 'collections' => self::collections() ) ),
					'permission_callback' => $owner,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$ids = array_values( array_unique( array_map( 'intval', (array) $r['order'] ) ) );
						update_option( 'stubli_coll_order', array_slice( $ids, 0, 100 ), false );
						return rest_ensure_response( array( 'collections' => self::collections() ) );
					},
					'permission_callback' => $owner,
				),
			)
		);
		Rest::register(
			'/arrange/(?P<cat>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn( \WP_REST_Request $r ) => rest_ensure_response( self::posts( (int) $r['cat'] ) ),
					'permission_callback' => $owner,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$cat = (int) $r['cat'];
						// The collection's name and the short line shown under its tab.
						if ( null !== $r['name'] || null !== $r['description'] ) {
							$term = get_term( $cat, 'category' );
							if ( ! $term || is_wp_error( $term ) ) {
								return new \WP_Error( 'stubli_no_collection', __( 'That collection isn\'t here any more.', 'stubli' ), array( 'status' => 404 ) );
							}
							$args = array();
							if ( null !== $r['name'] ) {
								$name = trim( sanitize_text_field( (string) $r['name'] ) );
								if ( '' === $name ) {
									return new \WP_Error( 'stubli_collection_name', __( 'Give the collection a name.', 'stubli' ), array( 'status' => 400 ) );
								}
								$taken = false;
								foreach ( get_categories( array( 'hide_empty' => false ) ) as $o ) {
									// Compare with what each tab shows, so "Photos" counts too.
									if ( (int) $o->term_id !== $cat && 0 === strcasecmp( self::name( $o ), $name ) ) {
										$taken = true;
									}
								}
								if ( $taken ) {
									return new \WP_Error( 'stubli_collection_name', __( 'You already have a collection with that name.', 'stubli' ), array( 'status' => 400 ) );
								}
								$args['name'] = mb_substr( $name, 0, 40 );
							}
							if ( null !== $r['description'] ) {
								$args['description'] = mb_substr( sanitize_text_field( (string) $r['description'] ), 0, 200 );
							}
							$res = wp_update_term( $cat, 'category', $args );
							if ( is_wp_error( $res ) ) {
								return new \WP_Error( 'stubli_collection_name', __( 'That name didn\'t save. Try another one.', 'stubli' ), array( 'status' => 400 ) );
							}
							if ( null === $r['pins'] && null === $r['hand'] ) {
								return rest_ensure_response( self::posts( $cat ) );
							}
						}
						// Arrange by hand: switch it on or off, and save the order.
						if ( null !== $r['hand'] ) {
							$all = (array) get_option( 'stubli_hand', array() );
							if ( rest_sanitize_boolean( $r['hand'] ) ) {
								$valid = self::ordered_ids( $cat ); // Before switching on: newest first, pins kept on top.
								if ( ! self::is_hand( $cat ) && null === $r['order'] ) {
									$pins  = self::all_pins()[ $cat ] ?? array();
									$valid = array_merge( $pins, array_values( array_diff( $valid, $pins ) ) );
								}
								$want = null !== $r['order'] ? array_values( array_unique( array_map( 'intval', (array) $r['order'] ) ) ) : $valid;
								$all[ $cat ] = array_slice( array_values( array_intersect( $want, $valid ) ), 0, self::MAX_HAND );
							} else {
								unset( $all[ $cat ] );
							}
							update_option( 'stubli_hand', $all, false );
							if ( null === $r['pins'] ) {
								return rest_ensure_response( self::posts( $cat ) );
							}
						}
						$keep = array();
						foreach ( array_map( 'intval', (array) $r['pins'] ) as $id ) {
							$p = get_post( $id );
							if ( $p && 'post' === $p->post_type && 'publish' === $p->post_status && has_category( $cat, $p ) && ! in_array( $id, $keep, true ) ) {
								$keep[] = $id;
							}
						}
						if ( count( $keep ) > self::MAX_PINS ) {
							/* translators: %d: how many */
							return new \WP_Error( 'stubli_pins', sprintf( __( 'Up to %d pinned posts per collection.', 'stubli' ), self::MAX_PINS ), array( 'status' => 400 ) );
						}
						$all = (array) get_option( 'stubli_pins', array() );
						if ( $keep ) {
							$all[ $cat ] = $keep;
						} else {
							unset( $all[ $cat ] );
						}
						update_option( 'stubli_pins', $all, false );
						return rest_ensure_response( self::posts( $cat ) );
					},
					'permission_callback' => $owner,
				),
			)
		);
	}
}
