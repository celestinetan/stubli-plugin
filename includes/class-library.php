<?php
/**
 * Library (0.46.0, Celestine 9–10 Nov 2026): the books you're happy to lend, so people nearby can borrow them.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Library, stage 1: you switch it on in the app, add books by scanning the barcode, typing the ISBN (details and cover
 * from Open Library, fetched by your own site) or by hand, and mark a book on loan (to anyone, name kept private) and
 * returned. Visitors see the shelf on your page with "On loan", never who has a book. Asking to borrow between Stublis
 * comes in stage 2.
 *
 * Books are private posts of type stubli_book (title = the book's title), with meta: _stubli_author, _stubli_year,
 * _stubli_isbn, _stubli_cover (attachment ID), _stubli_book_note (a note for borrowers), _stubli_loan
 * ( { name, site, since } , private). Settings in stubli_library: { on, area, handover }.
 */
class Library {

	const TYPE     = 'stubli_book';
	const MAX      = 500;
	const NOTE_MAX = 280;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register_type' ) );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * The book post type: never public on its own, shown only through the Library tab.
	 */
	public static function register_type() {
		register_post_type(
			self::TYPE,
			array(
				'label'           => __( 'Library books', 'stubli' ),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Library settings.
	 *
	 * @return array { on: bool, area: string, handover: string }
	 */
	public static function settings() {
		$s = (array) get_option( 'stubli_library', array() );
		return array(
			'on'       => ! empty( $s['on'] ),
			'area'     => (string) ( $s['area'] ?? '' ),
			'handover' => (string) ( $s['handover'] ?? '' ),
		);
	}

	/**
	 * Whether the Library tab shows on your page.
	 *
	 * @return bool
	 */
	public static function is_on() {
		return self::settings()['on'];
	}

	/**
	 * One book as data.
	 *
	 * @param \WP_Post $p     Book.
	 * @param bool     $owner Include who has it (only ever for the owner).
	 * @return array
	 */
	public static function book( $p, $owner = false ) {
		$cover = (int) get_post_meta( $p->ID, '_stubli_cover', true );
		$loan  = get_post_meta( $p->ID, '_stubli_loan', true );
		$out   = array(
			'id'     => (int) $p->ID,
			'title'  => html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' ),
			'author' => (string) get_post_meta( $p->ID, '_stubli_author', true ),
			'year'   => (string) get_post_meta( $p->ID, '_stubli_year', true ),
			'isbn'   => (string) get_post_meta( $p->ID, '_stubli_isbn', true ),
			'note'   => (string) get_post_meta( $p->ID, '_stubli_book_note', true ),
			'cover'  => $cover ? (string) wp_get_attachment_image_url( $cover, 'medium' ) : '',
			'onLoan' => is_array( $loan ) && ! empty( $loan['name'] ),
		);
		if ( $owner && $out['onLoan'] ) {
			$out['loan'] = array(
				'name'  => (string) $loan['name'],
				'site'  => (string) ( $loan['site'] ?? '' ),
				'since' => (string) ( $loan['since'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * All books, newest first.
	 *
	 * @param bool $owner Include who has each book.
	 * @return array[]
	 */
	public static function books( $owner = false ) {
		$posts = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'private',
				'posts_per_page' => self::MAX,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		return array_map( static fn( $p ) => self::book( $p, $owner ), $posts );
	}

	/**
	 * An ISBN as 13 digits (an ISBN-10 is converted), or ''.
	 *
	 * @param string $raw What was scanned or typed.
	 * @return string
	 */
	public static function isbn( $raw ) {
		$s = strtoupper( preg_replace( '/[^0-9Xx]/', '', (string) $raw ) );
		if ( 10 === strlen( $s ) && preg_match( '/^\d{9}[\dX]$/', $s ) ) {
			$sum = 0;
			for ( $i = 0; $i < 10; $i++ ) {
				$sum += ( 10 - $i ) * ( 'X' === $s[ $i ] ? 10 : (int) $s[ $i ] );
			}
			if ( 0 !== $sum % 11 ) {
				return '';
			}
			$s = '978' . substr( $s, 0, 9 );
			$t = 0;
			for ( $i = 0; $i < 12; $i++ ) {
				$t += (int) $s[ $i ] * ( $i % 2 ? 3 : 1 );
			}
			return $s . ( ( 10 - $t % 10 ) % 10 );
		}
		if ( 13 === strlen( $s ) && ctype_digit( $s ) && in_array( substr( $s, 0, 3 ), array( '978', '979' ), true ) ) {
			$t = 0;
			for ( $i = 0; $i < 12; $i++ ) {
				$t += (int) $s[ $i ] * ( $i % 2 ? 3 : 1 );
			}
			return ( ( 10 - $t % 10 ) % 10 ) === (int) $s[12] ? $s : '';
		}
		return '';
	}

	/**
	 * Look a book up on Open Library by ISBN. Your site asks; nothing goes from your visitors.
	 *
	 * @param string $isbn 13 digits.
	 * @return array|\WP_Error { isbn, title, author, year, coverUrl }
	 */
	public static function lookup( $isbn ) {
		$cached = get_transient( 'stubli_isbn_' . $isbn );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$res = wp_remote_get(
			'https://openlibrary.org/api/books?format=json&jscmd=data&bibkeys=ISBN:' . rawurlencode( $isbn ),
			array(
				'timeout'    => 10,
				'user-agent' => 'Stubli/' . VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			return new \WP_Error( 'stubli_lookup', __( 'Couldn\'t reach Open Library just now. You can type the details in yourself.', 'stubli' ), array( 'status' => 502 ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		$b    = is_array( $data ) ? ( $data[ 'ISBN:' . $isbn ] ?? null ) : null;
		if ( ! is_array( $b ) ) {
			return new \WP_Error( 'stubli_not_found', __( 'Open Library doesn\'t know this book. You can type the details in yourself.', 'stubli' ), array( 'status' => 404 ) );
		}
		$authors = array();
		foreach ( (array) ( $b['authors'] ?? array() ) as $a ) {
			if ( ! empty( $a['name'] ) ) {
				$authors[] = (string) $a['name'];
			}
		}
		preg_match( '/\d{4}/', (string) ( $b['publish_date'] ?? '' ), $y );
		$out = array(
			'isbn'     => $isbn,
			'title'    => trim( (string) ( $b['title'] ?? '' ) . ( ! empty( $b['subtitle'] ) ? ': ' . $b['subtitle'] : '' ) ),
			'author'   => implode( ', ', array_slice( $authors, 0, 3 ) ),
			'year'     => $y[0] ?? '',
			'coverUrl' => (string) ( $b['cover']['medium'] ?? ( $b['cover']['large'] ?? '' ) ),
		);
		set_transient( 'stubli_isbn_' . $isbn, $out, WEEK_IN_SECONDS );
		return $out;
	}

	/**
	 * Copy a cover from Open Library into your media, so visitors load it from your site.
	 *
	 * @param string $url     Cover address (covers.openlibrary.org only).
	 * @param int    $book_id Book.
	 * @return int Attachment ID or 0.
	 */
	private static function sideload_cover( $url, $book_id ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! in_array( $host, array( 'covers.openlibrary.org' ), true ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_sideload_image( $url, $book_id, null, 'id' );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Add or change a book.
	 *
	 * @param array $f  Fields: title, author, year, isbn, note, coverUrl, coverId.
	 * @param int   $id Book to change, or 0 to add.
	 * @return array|\WP_Error The book.
	 */
	public static function save( array $f, $id = 0 ) {
		$title = trim( sanitize_text_field( (string) ( $f['title'] ?? '' ) ) );
		if ( '' === $title ) {
			return new \WP_Error( 'stubli_book_title', __( 'Give the book a title.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( ! $id && count( get_posts( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'posts_per_page' => self::MAX, 'fields' => 'ids' ) ) ) >= self::MAX ) {
			/* translators: %s: the most books a library can hold */
			return new \WP_Error( 'stubli_library_full', sprintf( __( 'Your library holds up to %s books.', 'stubli' ), self::MAX ), array( 'status' => 400 ) );
		}
		$args = array(
			'post_type'   => self::TYPE,
			'post_status' => 'private',
			'post_title'  => mb_substr( $title, 0, 200 ),
		);
		if ( $id ) {
			$args['ID'] = $id;
			$res        = wp_update_post( $args, true );
		} else {
			$res = wp_insert_post( $args, true );
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$id = (int) $res;
		update_post_meta( $id, '_stubli_author', mb_substr( sanitize_text_field( (string) ( $f['author'] ?? '' ) ), 0, 200 ) );
		update_post_meta( $id, '_stubli_year', preg_replace( '/\D/', '', substr( (string) ( $f['year'] ?? '' ), 0, 4 ) ) );
		update_post_meta( $id, '_stubli_isbn', self::isbn( (string) ( $f['isbn'] ?? '' ) ) );
		update_post_meta( $id, '_stubli_book_note', mb_substr( sanitize_textarea_field( (string) ( $f['note'] ?? '' ) ), 0, self::NOTE_MAX ) );
		if ( ! empty( $f['coverId'] ) && current_user_can( 'edit_post', (int) $f['coverId'] ) ) {
			update_post_meta( $id, '_stubli_cover', (int) $f['coverId'] );
		} elseif ( ! empty( $f['coverUrl'] ) && ! get_post_meta( $id, '_stubli_cover', true ) ) {
			$c = self::sideload_cover( (string) $f['coverUrl'], $id );
			if ( $c ) {
				update_post_meta( $id, '_stubli_cover', $c );
			}
		}
		return self::book( get_post( $id ), true );
	}

	/**
	 * Mark a book on loan (to a name, and a Stubli address if they have one) or back home.
	 *
	 * @param int        $id   Book.
	 * @param array|null $loan { name, site } or null when it's back.
	 * @return array|\WP_Error The book.
	 */
	public static function lend( $id, $loan ) {
		if ( null === $loan ) {
			delete_post_meta( $id, '_stubli_loan' );
		} else {
			$name = trim( sanitize_text_field( (string) ( $loan['name'] ?? '' ) ) );
			if ( '' === $name ) {
				return new \WP_Error( 'stubli_loan_name', __( 'Who has it? A first name is enough.', 'stubli' ), array( 'status' => 400 ) );
			}
			update_post_meta(
				$id,
				'_stubli_loan',
				array(
					'name'  => mb_substr( $name, 0, 80 ),
					'site'  => esc_url_raw( (string) ( $loan['site'] ?? '' ) ),
					'since' => gmdate( 'Y-m-d' ),
				)
			);
		}
		return self::book( get_post( $id ), true );
	}

	/**
	 * A book of this site, or an error.
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|\WP_Error
	 */
	private static function find( $id ) {
		$p = get_post( (int) $id );
		return ( $p && self::TYPE === $p->post_type && 'trash' !== $p->post_status ) ? $p : new \WP_Error( 'stubli_no_book', __( 'That book isn\'t here any more.', 'stubli' ), array( 'status' => 404 ) );
	}

	/**
	 * Everything the app needs: settings, books (with who has them) and people you follow to lend to.
	 *
	 * @return array
	 */
	public static function owner_data() {
		$people = array();
		foreach ( Follows::all() as $f ) {
			$people[] = array(
				'name' => (string) ( $f['name'] ?? '' ),
				'site' => (string) ( $f['site_url'] ?? '' ),
			);
		}
		return array(
			'settings' => self::settings(),
			'books'    => self::books( true ),
			'people'   => array_values( array_filter( $people, static fn( $p ) => '' !== $p['name'] ) ),
		);
	}

	/**
	 * REST routes, owner only (visitors see the shelf on the page itself).
	 */
	public static function routes() {
		Rest::register(
			'/library',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn() => rest_ensure_response( self::owner_data() ),
					'permission_callback' => array( Rest::class, 'is_owner' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$res = self::save( (array) $r->get_json_params() );
						return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
					},
					'permission_callback' => array( Rest::class, 'is_owner' ),
				),
			)
		);
		Rest::register(
			'/library/settings',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$s = self::settings();
					if ( null !== $r['on'] ) {
						$s['on'] = (bool) $r['on'];
					}
					if ( null !== $r['area'] ) {
						$s['area'] = mb_substr( sanitize_text_field( (string) $r['area'] ), 0, 80 );
					}
					if ( null !== $r['handover'] ) {
						$s['handover'] = mb_substr( sanitize_text_field( (string) $r['handover'] ), 0, 140 );
					}
					update_option( 'stubli_library', $s, false );
					return rest_ensure_response( self::settings() );
				},
				'permission_callback' => array( Rest::class, 'is_owner' ),
			)
		);
		Rest::register(
			'/library/isbn/(?P<isbn>[0-9Xx-]{10,17})',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$isbn = self::isbn( (string) $r['isbn'] );
					if ( '' === $isbn ) {
						return new \WP_Error( 'stubli_isbn', __( 'That doesn\'t look like a book\'s barcode (ISBN). Check the numbers, or type the details in yourself.', 'stubli' ), array( 'status' => 400 ) );
					}
					$res = self::lookup( $isbn );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => array( Rest::class, 'is_owner' ),
			)
		);
		Rest::register(
			'/library/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$p = self::find( (int) $r['id'] );
						if ( is_wp_error( $p ) ) {
							return $p;
						}
						$body = (array) $r->get_json_params();
						if ( array_key_exists( 'loan', $body ) ) {
							$res = self::lend( $p->ID, is_array( $body['loan'] ) ? $body['loan'] : null );
						} else {
							$res = self::save( $body, $p->ID );
						}
						return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
					},
					'permission_callback' => array( Rest::class, 'is_owner' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$p = self::find( (int) $r['id'] );
						if ( is_wp_error( $p ) ) {
							return $p;
						}
						// To the bin, not gone: WordPress empties it after 30 days.
						wp_trash_post( $p->ID );
						return rest_ensure_response( array( 'removed' => (int) $p->ID ) );
					},
					'permission_callback' => array( Rest::class, 'is_owner' ),
				),
			)
		);
	}
}
