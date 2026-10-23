<?php
/**
 * Arrange your page: the order of your collection tabs, and up to three
 * posts pinned to the top of each collection. Everything else stays newest
 * first, and followers' feeds are never reordered.
 *
 * Stored as two options the page reads: stubli_coll_order (category IDs in
 * the owner's order) and stubli_pins (category ID => post IDs).
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
		return array(
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => array( 'post-format-image', 'post-format-gallery' ),
			),
		);
	}

	/**
	 * A collection's name ("Photos" for an untouched default category).
	 *
	 * @param \WP_Term $t Category.
	 * @return string
	 */
	private static function name( $t ) {
		if ( (int) $t->term_id === (int) get_option( 'default_category' ) && in_array( strtolower( $t->slug ), array( 'uncategorized', 'uncategorised' ), true ) ) {
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
					'id'    => (int) $t->term_id,
					'name'  => self::name( $t ),
					'count' => (int) $q->found_posts,
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
	 * The newest posts in a collection, for choosing pins.
	 *
	 * @param int $cat Category ID.
	 * @return array[]
	 */
	public static function posts( $cat ) {
		$pins = self::all_pins()[ (int) $cat ] ?? array();
		$q    = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'cat'            => (int) $cat,
				'posts_per_page' => 60,
				'tax_query'      => self::photo_tax(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
		$out  = array();
		foreach ( $q->posts as $p ) {
			$ids   = Posts::photo_ids( $p->ID );
			$out[] = array(
				'id'     => (int) $p->ID,
				'title'  => html_entity_decode( get_the_title( $p ), ENT_QUOTES ),
				'thumb'  => $ids ? (string) wp_get_attachment_image_url( $ids[0], 'thumbnail' ) : '',
				'pinned' => array_search( (int) $p->ID, $pins, true ),
			);
		}
		return array(
			'posts' => $out,
			'pins'  => $pins,
			'max'   => self::MAX_PINS,
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
						$cat  = (int) $r['cat'];
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
