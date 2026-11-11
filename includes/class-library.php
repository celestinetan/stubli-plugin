<?php
/**
 * Library (0.46.0, Celestine 9–10 Nov 2026): the books you're happy to lend, so people nearby can borrow them.
 * 0.47.0 (10 Nov): games, tools and other things too; Now reading; things for friends only, which your friends see in
 * their own Stubli app.
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
 *
 * 0.47.0: _stubli_kind (book, game, tool or other; empty = book), _stubli_audience ('friends' or empty, like posts),
 * _stubli_reading (date you started, books only; at most READING at once) and _stubli_just (a book you're reading but
 * not lending: it shows only under Now reading and leaves when you finish). Friends-only things never reach the page;
 * GET /library/shelf hands them only to a friend's site that brings the key friends-only posts use (?sf=).
 */
class Library {

	const TYPE     = 'stubli_book';
	const MAX      = 500;
	const NOTE_MAX = 280;
	const READING  = 3;
	const KINDS    = array( 'book', 'game', 'tool', 'other' );

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
			'kind'    => self::kind_of( $p->ID ),
			'friends' => 'friends' === get_post_meta( $p->ID, '_stubli_audience', true ),
			'reading' => (string) get_post_meta( $p->ID, '_stubli_reading', true ),
			'just'    => (bool) get_post_meta( $p->ID, '_stubli_just', true ),
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
	 * What kind of thing this is.
	 *
	 * @param int $id Post ID.
	 * @return string book, game, tool or other.
	 */
	public static function kind_of( $id ) {
		$k = (string) get_post_meta( $id, '_stubli_kind', true );
		return in_array( $k, self::KINDS, true ) ? $k : 'book';
	}

	/**
	 * What a visitor may see: everything for Everyone, plus friends-only things for a friend's site, and never a name.
	 *
	 * @param bool $friend The request carries a friend's key.
	 * @return array[]
	 */
	public static function shown( $friend = false ) {
		return array_values( array_filter( self::books( false ), static fn( $b ) => $friend || ! $b['friends'] ) );
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
	 * Covers for a book found on Open Library by title (and author), for the owner to pick from (0.47.1, Celestine 10 Nov:
	 * for books whose barcode finds no cover). Your site asks; the title and author are all it sends.
	 *
	 * @param string $title  Title.
	 * @param string $author Author, optional.
	 * @return array|\WP_Error List of { url, title, author, year }.
	 */
	public static function covers( $title, $author = '' ) {
		$title  = trim( mb_substr( sanitize_text_field( $title ), 0, 200 ) );
		$author = trim( mb_substr( sanitize_text_field( $author ), 0, 200 ) );
		if ( '' === $title ) {
			return new \WP_Error( 'stubli_covers_title', __( 'Type the title first.', 'stubli' ), array( 'status' => 400 ) );
		}
		$key    = 'stubli_covers_' . md5( strtolower( $title . '|' . $author ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$q   = array(
			'title'  => $title,
			'fields' => 'title,author_name,first_publish_year,cover_i',
			'limit'  => 24,
		);
		if ( '' !== $author ) {
			$q['author'] = $author;
		}
		$res = wp_remote_get(
			add_query_arg( array_map( 'rawurlencode', $q ), 'https://openlibrary.org/search.json' ),
			array(
				'timeout'    => 10,
				'user-agent' => 'Stubli/' . VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			return new \WP_Error( 'stubli_lookup', __( 'Couldn\'t reach Open Library just now. Try again in a moment, or take a photo of the cover.', 'stubli' ), array( 'status' => 502 ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		$out  = array();
		$seen = array();
		foreach ( (array) ( $data['docs'] ?? array() ) as $d ) {
			$c = (int) ( $d['cover_i'] ?? 0 );
			if ( $c <= 0 || isset( $seen[ $c ] ) ) {
				continue;
			}
			$seen[ $c ] = true;
			$out[]      = array(
				'url'    => 'https://covers.openlibrary.org/b/id/' . $c . '-M.jpg',
				'title'  => mb_substr( sanitize_text_field( (string) ( $d['title'] ?? '' ) ), 0, 200 ),
				'author' => mb_substr( sanitize_text_field( implode( ', ', array_slice( (array) ( $d['author_name'] ?? array() ), 0, 2 ) ) ), 0, 200 ),
				'year'   => (string) (int) ( $d['first_publish_year'] ?? 0 ),
			);
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		set_transient( $key, $out, DAY_IN_SECONDS );
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
			return new \WP_Error( 'stubli_book_title', __( 'Give it a name.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( ! $id && count( get_posts( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'posts_per_page' => self::MAX, 'fields' => 'ids' ) ) ) >= self::MAX ) {
			/* translators: %s: the most books a library can hold */
			return new \WP_Error( 'stubli_library_full', sprintf( __( 'Your Library holds up to %s things.', 'stubli' ), self::MAX ), array( 'status' => 400 ) );
		}
		$is_new = ! $id;
		if ( $is_new && ! empty( $f['just'] ) && self::reading_count() >= self::READING ) {
			/* translators: %s: how many books can be under Now reading */
			return new \WP_Error( 'stubli_reading_full', sprintf( __( 'Now reading holds up to %s books. Tap Finished on one first.', 'stubli' ), self::READING ), array( 'status' => 400 ) );
		}
		$args   = array(
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
		$id   = (int) $res;
		$kind = isset( $f['kind'] ) ? (string) $f['kind'] : ( $is_new ? 'book' : self::kind_of( $id ) );
		$kind = in_array( $kind, self::KINDS, true ) ? $kind : 'book';
		update_post_meta( $id, '_stubli_kind', $kind );
		if ( array_key_exists( 'friends', $f ) ) {
			if ( ! empty( $f['friends'] ) ) {
				update_post_meta( $id, '_stubli_audience', 'friends' );
			} else {
				delete_post_meta( $id, '_stubli_audience' );
			}
		}
		if ( 'book' !== $kind ) {
			delete_post_meta( $id, '_stubli_reading' );
			delete_post_meta( $id, '_stubli_just' );
		} elseif ( ! empty( $f['just'] ) ) {
			// Just reading it: on Now reading straight away, never offered to borrow.
			update_post_meta( $id, '_stubli_just', 1 );
			if ( ! get_post_meta( $id, '_stubli_reading', true ) ) {
				update_post_meta( $id, '_stubli_reading', gmdate( 'Y-m-d' ) );
			}
		}
		update_post_meta( $id, '_stubli_author', mb_substr( sanitize_text_field( (string) ( $f['author'] ?? '' ) ), 0, 200 ) );
		update_post_meta( $id, '_stubli_year', preg_replace( '/\D/', '', substr( (string) ( $f['year'] ?? '' ), 0, 4 ) ) );
		update_post_meta( $id, '_stubli_isbn', self::isbn( (string) ( $f['isbn'] ?? '' ) ) );
		update_post_meta( $id, '_stubli_book_note', mb_substr( sanitize_textarea_field( (string) ( $f['note'] ?? '' ) ), 0, self::NOTE_MAX ) );
		if ( ! empty( $f['coverId'] ) && current_user_can( 'edit_post', (int) $f['coverId'] ) ) {
			update_post_meta( $id, '_stubli_cover', (int) $f['coverId'] );
		} elseif ( ! empty( $f['coverUrl'] ) && ( ! empty( $f['coverPick'] ) || ! get_post_meta( $id, '_stubli_cover', true ) ) ) {
			// A cover picked from Open Library replaces the one there was (0.47.1).
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
			$old = get_post_meta( $id, '_stubli_loan', true );
			delete_post_meta( $id, '_stubli_loan' );
			// Lent through Stubli: their Library lets it go too (0.48.0).
			if ( is_array( $old ) ) {
				Borrow::ended( (int) $id, $old );
			}
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
	 * How many books are under Now reading.
	 *
	 * @return int
	 */
	public static function reading_count() {
		return count( array_filter( self::books( false ), static fn( $b ) => '' !== $b['reading'] ) );
	}

	/**
	 * Start reading a book from your shelf, or finish one. Finishing a book you were just reading takes it out of your
	 * Library (to the bin); a book from your shelf goes back to being lent.
	 *
	 * @param int  $id Book.
	 * @param bool $on Reading it now.
	 * @return array|\WP_Error The book, or { removed } when it left.
	 */
	public static function reading( $id, $on ) {
		if ( 'book' !== self::kind_of( $id ) ) {
			return new \WP_Error( 'stubli_reading_kind', __( 'Only books go under Now reading.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( ! $on ) {
			if ( get_post_meta( $id, '_stubli_just', true ) ) {
				wp_trash_post( $id );
				return array( 'removed' => (int) $id );
			}
			delete_post_meta( $id, '_stubli_reading' );
			return self::book( get_post( $id ), true );
		}
		if ( get_post_meta( $id, '_stubli_reading', true ) ) {
			return self::book( get_post( $id ), true );
		}
		$loan = get_post_meta( $id, '_stubli_loan', true );
		if ( is_array( $loan ) && ! empty( $loan['name'] ) ) {
			return new \WP_Error( 'stubli_reading_lent', __( 'It\'s on loan just now. Tap It\'s back first.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( self::reading_count() >= self::READING ) {
			/* translators: %s: how many books can be under Now reading */
			return new \WP_Error( 'stubli_reading_full', sprintf( __( 'Now reading holds up to %s books. Tap Finished on one first.', 'stubli' ), self::READING ), array( 'status' => 400 ) );
		}
		update_post_meta( $id, '_stubli_reading', gmdate( 'Y-m-d' ) );
		return self::book( get_post( $id ), true );
	}

	/**
	 * What a visitor's site gets from GET /library/shelf: the settings visitors see and the things for them. A friend's
	 * site brings its key (?sf=) and also gets the friends-only things, marked.
	 *
	 * @return array
	 */
	public static function shelf_data() {
		$s = self::settings();
		if ( ! $s['on'] ) {
			return array( 'on' => false );
		}
		$friend = '' !== Friends::viewer();
		$items  = array_map(
			static function ( $b ) {
				unset( $b['loan'], $b['isbn'] );
				return $b;
			},
			self::shown( $friend )
		);
		return array(
			'on'       => true,
			'name'     => Posts::site_name(),
			'area'     => $s['area'],
			'handover' => $s['handover'],
			'friend'   => $friend,
			'items'    => $items,
		);
	}

	/**
	 * Someone you follow: their Library, asked from their site by yours, with the key they gave you if they ticked you as
	 * a friend.
	 *
	 * @param int $follow_id Follow row.
	 * @return array|\WP_Error
	 */
	public static function of( $follow_id ) {
		$f = Follows::get( (int) $follow_id );
		if ( ! $f ) {
			return new \WP_Error( 'stubli_no_follow', __( 'You don\'t follow this site any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		$site = (string) $f['site_url'];
		$key  = Friends::key_from( $site );
		$res  = Rest::remote( $site, '/library/shelf', array( 'timeout' => 15 ), $key ? array( Friends::PARAM => $key ) : array() );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$data = 200 === $code ? json_decode( (string) wp_remote_retrieve_body( $res ), true ) : null;
		$base = array(
			'id'   => (int) $f['id'],
			'site' => $site,
			'name' => (string) ( $f['name'] ?? '' ),
		);
		if ( 404 === $code ) {
			return $base + array( 'on' => false );
		}
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'stubli_lib_unreachable', __( 'Their site couldn\'t be reached just now. Try again in a little while.', 'stubli' ), array( 'status' => 502 ) );
		}
		if ( empty( $data['on'] ) ) {
			return $base + array( 'on' => false );
		}
		$items = array();
		foreach ( array_slice( (array) ( $data['items'] ?? array() ), 0, self::MAX ) as $b ) {
			if ( ! is_array( $b ) ) {
				continue;
			}
			$kind    = (string) ( $b['kind'] ?? 'book' );
			$items[] = array(
				'id'      => (int) ( $b['id'] ?? 0 ),
				'title'   => mb_substr( sanitize_text_field( (string) ( $b['title'] ?? '' ) ), 0, 200 ),
				'author'  => mb_substr( sanitize_text_field( (string) ( $b['author'] ?? '' ) ), 0, 200 ),
				'year'    => preg_replace( '/\D/', '', substr( (string) ( $b['year'] ?? '' ), 0, 4 ) ),
				'note'    => mb_substr( sanitize_textarea_field( (string) ( $b['note'] ?? '' ) ), 0, self::NOTE_MAX ),
				'cover'   => esc_url_raw( (string) ( $b['cover'] ?? '' ), array( 'https', 'http' ) ),
				'onLoan'  => ! empty( $b['onLoan'] ),
				'kind'    => in_array( $kind, self::KINDS, true ) ? $kind : 'book',
				'friends' => ! empty( $b['friends'] ),
				'reading' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $b['reading'] ?? '' ) ) ? (string) $b['reading'] : '',
				'just'    => ! empty( $b['just'] ),
			);
		}
		return $base + array(
			'on'       => true,
			'name'     => sanitize_text_field( (string) ( $data['name'] ?? $base['name'] ) ),
			'area'     => mb_substr( sanitize_text_field( (string) ( $data['area'] ?? '' ) ), 0, 80 ),
			'handover' => mb_substr( sanitize_text_field( (string) ( $data['handover'] ?? '' ) ), 0, 140 ),
			'friend'   => ! empty( $data['friend'] ),
			'items'    => array_values( array_filter( $items, static fn( $b ) => '' !== $b['title'] ) ),
		);
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
		// Public: what visitors' sites (and your friends' sites, with their key) may see. Never names or ISBNs.
		Rest::register(
			'/library/shelf',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function () {
					$res = rest_ensure_response( self::shelf_data() );
					if ( '' !== Friends::viewer() ) {
						$res->header( 'Cache-Control', 'no-store, private' );
					}
					return $res;
				},
				'permission_callback' => '__return_true',
			)
		);
		Rest::register(
			'/library/of/(?P<follow>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$res = self::of( (int) $r['follow'] );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => array( Rest::class, 'is_owner' ),
			)
		);
		Rest::register(
			'/library/photo',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$files = $r->get_file_params();
					$res   = Journal::upload_photo( $files['photo'] ?? array() );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => array( Rest::class, 'is_owner' ),
			)
		);
		Rest::register(
			'/library/covers',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$res = self::covers( (string) $r->get_param( 'title' ), (string) $r->get_param( 'author' ) );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
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
						} elseif ( array_key_exists( 'reading', $body ) ) {
							$res = self::reading( $p->ID, ! empty( $body['reading'] ) );
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
