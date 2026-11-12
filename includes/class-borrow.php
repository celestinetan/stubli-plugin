<?php
/**
 * Ask to borrow between Stublis (0.48.0, Celestine 9–11 Nov 2026).
 *
 * Ben asks from his app (People → Library next to Zoë) → his site sends Zoë's site a short message → Zoë's site fetches
 * it back from Ben's site to prove who sent it (the way private notes are checked) → it shows in Zoë's What's new with
 * Lend it / Not now. Her answer travels back the same way. While Ben has it, it shows in his Library, and on his page as
 * "borrowed from a neighbour", with no cover and no link, so Zoë's name and address stay private. He taps I've given it
 * back; Zoë confirms, or taps It's back herself, and it leaves his Library.
 *
 * Only people Zoë follows back can ask; friends-only things only by people she ticked as Friend. No due dates.
 *
 * Lender side: option stubli_lib_in (the requests that came in). Borrower side: option stubli_lib_borrowed (what you
 * asked for, and have). Messages waiting to be fetched: transients stubli_lmsg_<id>, for 15 minutes.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Asking, lending and giving back between two Stublis.
 */
class Borrow {

	const IN       = 'stubli_lib_in';
	const OUT      = 'stubli_lib_borrowed';
	const MAX      = 200;
	const NOTE_MAX = 280;
	const TYPES    = array( 'ask', 'yes', 'no', 'given', 'back' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/* ── Storage ── */

	/**
	 * Requests that came in (you lend).
	 *
	 * @return array[]
	 */
	public static function incoming() {
		$v = get_option( self::IN, array() );
		return is_array( $v ) ? array_values( $v ) : array();
	}

	/**
	 * What you asked for or have borrowed.
	 *
	 * @return array[]
	 */
	public static function borrowed() {
		$v = get_option( self::OUT, array() );
		return is_array( $v ) ? array_values( $v ) : array();
	}

	/**
	 * Save a list, newest first, never longer than MAX.
	 *
	 * @param string $key  Option.
	 * @param array  $list Entries.
	 */
	private static function keep( $key, array $list ) {
		update_option( $key, array_slice( array_values( $list ), 0, self::MAX ), false );
	}

	/**
	 * The key for something borrowed: their site and their item.
	 *
	 * @param string $site Lender's site.
	 * @param int    $item Their item.
	 * @return string
	 */
	public static function key( $site, $item ) {
		return md5( Follows::origin( $site ) . '|' . (int) $item );
	}

	/* ── Messages between sites ── */

	/**
	 * Tell another Stubli something. Their site fetches the message back from ours to prove it came from us.
	 *
	 * @param string $to      Their site.
	 * @param array  $payload { type, item, … }.
	 * @return array|\WP_Error Their answer.
	 */
	public static function send( $to, array $payload ) {
		$id    = strtolower( wp_generate_password( 16, false ) );
		$token = strtolower( wp_generate_password( 32, false ) );
		set_transient(
			'stubli_lmsg_' . $id,
			array(
				'hash'    => wp_hash( $token ),
				'to'      => Follows::origin( $to ),
				'payload' => $payload,
			),
			15 * MINUTE_IN_SECONDS
		);
		$res = Rest::remote(
			$to,
			'/library/msg',
			array(
				'method'  => 'POST',
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'site'  => home_url( '/' ),
						'id'    => $id,
						'token' => $token,
					)
				),
			)
		);
		delete_transient( 'stubli_lmsg_' . $id );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$body = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code >= 200 && $code < 300 && ! empty( $body['ok'] ) ) {
			return $body;
		}
		if ( is_array( $body ) && ! empty( $body['code'] ) && 0 === strpos( (string) $body['code'], 'stubli_' ) ) {
			return new \WP_Error( (string) $body['code'], (string) ( $body['message'] ?? '' ), array( 'status' => $code ?: 502 ) );
		}
		return new \WP_Error( 'stubli_borrow_unreachable', __( 'Their site couldn\'t be reached just now, or doesn\'t have the latest Stubli yet. Try again later.', 'stubli' ), array( 'status' => 502 ) );
	}

	/**
	 * The other site asks us for a message we said we sent.
	 *
	 * @param string $id    Message.
	 * @param string $token Proof.
	 * @param string $for   The site asking.
	 * @return array
	 */
	public static function check( $id, $token, $for ) {
		$m = get_transient( 'stubli_lmsg_' . preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $id ) ) );
		if ( ! is_array( $m ) || ! hash_equals( (string) $m['hash'], wp_hash( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $token ) ) ) ) || $m['to'] !== Follows::origin( (string) $for ) ) {
			return array( 'ok' => false );
		}
		$profile = Posts::profile();
		return array(
			'ok'      => true,
			'payload' => $m['payload'],
			'name'    => $profile['name'],
			'avatar'  => $profile['avatar'],
			'site'    => home_url( '/' ),
		);
	}

	/**
	 * A message arrived from another Stubli: fetch it back from them, then act on it.
	 *
	 * @param string $site  Sender.
	 * @param string $id    Their message.
	 * @param string $token Proof.
	 * @return array|\WP_Error
	 */
	public static function receive( $site, $id, $token ) {
		$site = esc_url_raw( (string) $site );
		if ( ! $site || ! in_array( wp_parse_url( $site, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			return new \WP_Error( 'stubli_borrow_bad', 'Bad request.', array( 'status' => 400 ) );
		}
		if ( Settings::on_break() ) {
			return new \WP_Error( 'stubli_on_break', __( 'They\'re taking a break just now. Try again another time.', 'stubli' ), array( 'status' => 403 ) );
		}
		$origin = Follows::origin( $site );
		// Limits count only messages that passed the check (security review 11 Nov, finding 7).
		if ( Notes::too_many_fails( 'lib' ) ) {
			return new \WP_Error( 'stubli_borrow_limit', __( 'That\'s a lot for one day. Try again tomorrow.', 'stubli' ), array( 'status' => 429 ) );
		}
		$res  = Rest::remote(
			$site,
			'/library/msg/check',
			array( 'timeout' => 15 ),
			array(
				'id'    => (string) $id,
				'token' => (string) $token,
				'for'   => home_url( '/' ),
			)
		);
		$data = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $data['ok'] ) || ! is_array( $data['payload'] ?? null ) ) {
			Notes::failed( 'lib' );
			return new \WP_Error( 'stubli_borrow_unproven', 'Not confirmed.', array( 'status' => 403 ) );
		}
		// Blocked sites get the same answer as everyone else, and nothing is kept.
		if ( Moderation::is_blocked( $site ) ) {
			return array( 'ok' => true );
		}
		if ( ! Notes::under_limit( 'lib_' . md5( $origin ), 30, DAY_IN_SECONDS ) ) {
			return new \WP_Error( 'stubli_borrow_limit', __( 'That\'s a lot for one day. Try again tomorrow.', 'stubli' ), array( 'status' => 429 ) );
		}
		$p    = $data['payload'];
		$type = (string) ( $p['type'] ?? '' );
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return new \WP_Error( 'stubli_borrow_bad', 'Bad request.', array( 'status' => 400 ) );
		}
		$who = array(
			'site'   => $origin . '/',
			'name'   => mb_substr( sanitize_text_field( (string) ( $data['name'] ?? '' ) ), 0, 80 ),
			'avatar' => esc_url_raw( (string) ( $data['avatar'] ?? '' ) ),
		);
		if ( '' === $who['name'] ) {
			$who['name'] = (string) wp_parse_url( $origin, PHP_URL_HOST );
		}
		$item = (int) ( $p['item'] ?? 0 );
		switch ( $type ) {
			case 'ask':
				return self::asked( $who, $item, (string) ( $p['note'] ?? '' ) );
			case 'given':
				return self::given_back( $who, $item );
			default:
				return self::answered( $who, $item, $type, $p );
		}
	}

	/* ── Lender side ── */

	/**
	 * Someone asks to borrow one of your things.
	 *
	 * @param array  $who  { site, name, avatar }.
	 * @param int    $item Your thing.
	 * @param string $note Their note.
	 * @return array|\WP_Error
	 */
	private static function asked( array $who, $item, $note ) {
		$first = self::first( (string) ( Posts::profile()['name'] ?? '' ) );
		if ( ! Library::is_on() ) {
			/* translators: %s: the owner's first name */
			return new \WP_Error( 'stubli_borrow_off', sprintf( __( '%s isn\'t lending things just now.', 'stubli' ), $first ), array( 'status' => 404 ) );
		}
		if ( ! Follows::find_by_site( $who['site'] ) ) {
			/* translators: %s: the owner's first name */
			return new \WP_Error( 'stubli_not_followed', sprintf( __( '%s lends to people they follow back. You can still write to them about it.', 'stubli' ), $first ), array( 'status' => 403 ) );
		}
		$p = get_post( $item );
		if ( ! $p || Library::TYPE !== $p->post_type || 'private' !== $p->post_status ) {
			return new \WP_Error( 'stubli_borrow_gone', __( 'That isn\'t on their shelf any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		$b = Library::book( $p, false );
		if ( $b['friends'] && ! Friends::is_friend( $who['site'] ) ) {
			return new \WP_Error( 'stubli_borrow_gone', __( 'That isn\'t on their shelf any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		if ( $b['onLoan'] || '' !== $b['reading'] || $b['just'] ) {
			return new \WP_Error( 'stubli_borrow_busy', __( 'It\'s not free just now: it\'s on loan or being read. Try again another time.', 'stubli' ), array( 'status' => 409 ) );
		}
		$list = self::incoming();
		foreach ( $list as $r ) {
			if ( $r['item'] === (int) $item && $r['site'] === $who['site'] && 'open' === $r['state'] ) {
				return array( 'ok' => true ); // Already asked: once is enough.
			}
		}
		array_unshift(
			$list,
			array(
				'id'     => strtolower( wp_generate_password( 12, false ) ),
				'item'   => (int) $item,
				'title'  => $b['title'],
				'cover'  => $b['cover'],
				'kind'   => $b['kind'],
				'site'   => $who['site'],
				'name'   => $who['name'],
				'avatar' => $who['avatar'],
				'note'   => mb_substr( sanitize_textarea_field( $note ), 0, self::NOTE_MAX ),
				'at'     => gmdate( 'c' ),
				'state'  => 'open',
			)
		);
		self::keep( self::IN, $list );
		self::push(
			/* translators: 1: a name, 2: what they'd like to borrow */
			sprintf( __( '%1$s asks to borrow %2$s', 'stubli' ), $who['name'], $b['title'] ),
			'#whatsnew'
		);
		return array( 'ok' => true );
	}

	/**
	 * The borrower says they've given it back.
	 *
	 * @param array $who  { site, name }.
	 * @param int   $item Your thing.
	 * @return array
	 */
	private static function given_back( array $who, $item ) {
		$list = self::incoming();
		foreach ( $list as &$r ) {
			if ( $r['item'] === (int) $item && $r['site'] === $who['site'] && 'lent' === $r['state'] ) {
				$r['returning'] = gmdate( 'c' );
				self::keep( self::IN, $list );
				self::push(
					/* translators: 1: a name, 2: the thing */
					sprintf( __( '%1$s says they\'ve given back %2$s', 'stubli' ), $who['name'], $r['title'] ),
					'#whatsnew'
				);
				break;
			}
		}
		return array( 'ok' => true );
	}

	/**
	 * You answer a request: Lend it, Not now, Yes it's back, Not yet.
	 *
	 * @param string $id     Request.
	 * @param string $action lend, decline, back or notyet.
	 * @return array|\WP_Error The request as it is now.
	 */
	public static function act( $id, $action ) {
		$list = self::incoming();
		$at   = null;
		foreach ( $list as $k => $r ) {
			if ( $r['id'] === (string) $id ) {
				$at = $k;
			}
		}
		if ( null === $at ) {
			return new \WP_Error( 'stubli_borrow_none', __( 'That request isn\'t here any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		$r    = $list[ $at ];
		$book = get_post( $r['item'] );
		if ( 'lend' === $action && 'open' === $r['state'] ) {
			if ( ! $book || Library::TYPE !== $book->post_type ) {
				return new \WP_Error( 'stubli_borrow_gone', __( 'That isn\'t on your shelf any more.', 'stubli' ), array( 'status' => 404 ) );
			}
			$b = Library::book( $book, true );
			if ( $b['loan'] ) {
				/* translators: %s: a name */
				return new \WP_Error( 'stubli_borrow_busy', sprintf( __( 'It\'s with %s just now. Tap It\'s back on it first.', 'stubli' ), $b['loan']['name'] ), array( 'status' => 409 ) );
			}
			$s   = Library::settings();
			$res = self::send(
				$r['site'],
				array(
					'type'     => 'yes',
					'item'     => $r['item'],
					'title'    => $b['title'],
					'author'   => $b['author'],
					'cover'    => $b['cover'],
					'kind'     => $b['kind'],
					'handover' => $s['handover'],
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			Library::lend(
				$r['item'],
				array(
					'name' => self::first( $r['name'] ),
					'site' => $r['site'],
				)
			);
			if ( '' !== $b['reading'] ) {
				delete_post_meta( $r['item'], '_stubli_reading' );
			}
			$list[ $at ]['state'] = 'lent';
			// Anyone else waiting for the same thing hears "not right now".
			foreach ( $list as $k => $o ) {
				if ( $k !== $at && $o['item'] === $r['item'] && 'open' === $o['state'] ) {
					self::send(
						$o['site'],
						array(
							'type'  => 'no',
							'item'  => $o['item'],
							'title' => $o['title'],
						)
					);
					$list[ $k ]['state'] = 'declined';
				}
			}
		} elseif ( 'decline' === $action && 'open' === $r['state'] ) {
			self::send(
				$r['site'],
				array(
					'type'  => 'no',
					'item'  => $r['item'],
					'title' => $r['title'],
				)
			);
			$list[ $at ]['state'] = 'declined'; // Even if their site didn't answer: it's off your list.
		} elseif ( 'back' === $action && 'lent' === $r['state'] ) {
			if ( $book && Library::TYPE === $book->post_type ) {
				delete_post_meta( $r['item'], '_stubli_loan' );
			}
			self::send(
				$r['site'],
				array(
					'type' => 'back',
					'item' => $r['item'],
				)
			);
			$list[ $at ]['state'] = 'back';
			unset( $list[ $at ]['returning'] );
		} elseif ( 'notyet' === $action && 'lent' === $r['state'] ) {
			unset( $list[ $at ]['returning'] );
		} else {
			return new \WP_Error( 'stubli_borrow_done', __( 'That\'s already been answered.', 'stubli' ), array( 'status' => 409 ) );
		}
		self::keep( self::IN, $list );
		return self::shape_in( $list[ $at ] );
	}

	/**
	 * You tapped It's back on a thing someone borrowed through Stubli: tell their site, so it leaves their Library.
	 *
	 * @param int   $item Your thing.
	 * @param array $loan The loan that ended.
	 */
	public static function ended( $item, array $loan ) {
		if ( empty( $loan['site'] ) ) {
			return;
		}
		$site = Follows::origin( (string) $loan['site'] ) . '/';
		$list = self::incoming();
		foreach ( $list as $k => $r ) {
			if ( $r['item'] === (int) $item && $r['site'] === $site && 'lent' === $r['state'] ) {
				$list[ $k ]['state'] = 'back';
				unset( $list[ $k ]['returning'] );
				self::keep( self::IN, $list );
				self::send(
					$site,
					array(
						'type' => 'back',
						'item' => (int) $item,
					)
				);
				return;
			}
		}
	}

	/* ── Borrower side ── */

	/**
	 * Ask to borrow something from someone you follow.
	 *
	 * @param int    $follow Follow row.
	 * @param int    $item   Their thing.
	 * @param string $note   A note for them.
	 * @param array  $shown  What your app showed: { title, author, cover, kind }.
	 * @return array|\WP_Error
	 */
	public static function ask( $follow, $item, $note, array $shown ) {
		$f = Follows::get( (int) $follow );
		if ( ! $f ) {
			return new \WP_Error( 'stubli_no_follow', __( 'You don\'t follow this site any more.', 'stubli' ), array( 'status' => 404 ) );
		}
		$site = Follows::origin( (string) $f['site_url'] ) . '/';
		$note = mb_substr( sanitize_textarea_field( (string) $note ), 0, self::NOTE_MAX );
		$res  = self::send(
			$site,
			array(
				'type' => 'ask',
				'item' => (int) $item,
				'note' => $note,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$key  = self::key( $site, $item );
		$list = array_values( array_filter( self::borrowed(), static fn( $e ) => $e['key'] !== $key ) );
		array_unshift(
			$list,
			array(
				'key'    => $key,
				'site'   => $site,
				'name'   => (string) ( $f['name'] ?? '' ),
				'item'   => (int) $item,
				'title'  => mb_substr( sanitize_text_field( (string) ( $shown['title'] ?? '' ) ), 0, 200 ),
				'author' => mb_substr( sanitize_text_field( (string) ( $shown['author'] ?? '' ) ), 0, 200 ),
				'cover'  => esc_url_raw( (string) ( $shown['cover'] ?? '' ), array( 'https', 'http' ) ),
				'kind'   => in_array( (string) ( $shown['kind'] ?? '' ), Library::KINDS, true ) ? (string) $shown['kind'] : 'book',
				'state'  => 'asked',
				'at'     => gmdate( 'c' ),
				'seen'   => true,
			)
		);
		self::keep( self::OUT, $list );
		return self::shape_out( $list[0] );
	}

	/**
	 * The lender answered: yes, not now, or it's back.
	 *
	 * @param array  $who  { site, name }.
	 * @param int    $item Their thing.
	 * @param string $type yes, no or back.
	 * @param array  $p    The message.
	 * @return array
	 */
	private static function answered( array $who, $item, $type, array $p ) {
		$key  = self::key( $who['site'], $item );
		$list = self::borrowed();
		foreach ( $list as $k => $e ) {
			if ( $e['key'] !== $key ) {
				continue;
			}
			if ( 'back' === $type ) {
				unset( $list[ $k ] );
				self::keep( self::OUT, $list );
				return array( 'ok' => true );
			}
			if ( 'asked' !== $e['state'] ) {
				return array( 'ok' => true );
			}
			$list[ $k ]['state'] = 'yes' === $type ? 'borrowed' : 'declined';
			$list[ $k ]['name']  = $who['name'];
			$list[ $k ]['seen']  = false;
			$list[ $k ]['since'] = gmdate( 'Y-m-d' );
			if ( 'yes' === $type ) {
				$list[ $k ]['title']    = mb_substr( sanitize_text_field( (string) ( $p['title'] ?? $e['title'] ) ), 0, 200 );
				$list[ $k ]['author']   = mb_substr( sanitize_text_field( (string) ( $p['author'] ?? $e['author'] ) ), 0, 200 );
				$list[ $k ]['cover']    = esc_url_raw( (string) ( $p['cover'] ?? $e['cover'] ), array( 'https', 'http' ) );
				$list[ $k ]['handover'] = mb_substr( sanitize_text_field( (string) ( $p['handover'] ?? '' ) ), 0, 140 );
			}
			self::keep( self::OUT, $list );
			self::push(
				'yes' === $type
					/* translators: 1: a name, 2: the thing */
					? sprintf( __( '%1$s said yes: %2$s', 'stubli' ), $who['name'], $list[ $k ]['title'] )
					/* translators: 1: a name, 2: the thing */
					: sprintf( __( '%1$s can\'t lend %2$s right now', 'stubli' ), $who['name'], $list[ $k ]['title'] ),
				'#whatsnew'
			);
			return array( 'ok' => true );
		}
		return array( 'ok' => true );
	}

	/**
	 * You tap I've given it back.
	 *
	 * @param string $key Entry.
	 * @return array|\WP_Error
	 */
	public static function give_back( $key ) {
		$list = self::borrowed();
		foreach ( $list as $k => $e ) {
			if ( $e['key'] === (string) $key && 'borrowed' === $e['state'] ) {
				$res = self::send(
					$e['site'],
					array(
						'type' => 'given',
						'item' => $e['item'],
					)
				);
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$list[ $k ]['state'] = 'returning';
				self::keep( self::OUT, $list );
				return self::shape_out( $list[ $k ] );
			}
		}
		return new \WP_Error( 'stubli_borrow_none', __( 'That isn\'t in your Library any more.', 'stubli' ), array( 'status' => 404 ) );
	}

	/**
	 * Forget an answer you've read ("can't lend it right now"), or mark answers seen.
	 *
	 * @param string $key Entry, or '' for all answers.
	 */
	public static function seen( $key = '' ) {
		$list = self::borrowed();
		foreach ( $list as $k => $e ) {
			if ( '' !== $key && $e['key'] !== $key ) {
				continue;
			}
			if ( 'declined' === $e['state'] ) {
				unset( $list[ $k ] );
			} else {
				$list[ $k ]['seen'] = true;
			}
		}
		self::keep( self::OUT, $list );
	}

	/* ── What the app and the page get ── */

	/**
	 * A request as the app shows it.
	 *
	 * @param array $r Request.
	 * @return array
	 */
	private static function shape_in( array $r ) {
		return array(
			'id'        => (string) $r['id'],
			'item'      => (int) $r['item'],
			'title'     => (string) $r['title'],
			'cover'     => (string) $r['cover'],
			'kind'      => (string) $r['kind'],
			'site'      => (string) $r['site'],
			'name'      => (string) $r['name'],
			'avatar'    => (string) $r['avatar'],
			'note'      => (string) $r['note'],
			'at'        => (string) $r['at'],
			'state'     => (string) $r['state'],
			'returning' => ! empty( $r['returning'] ),
		);
	}

	/**
	 * Something you asked for or have, as the app shows it.
	 *
	 * @param array $e Entry.
	 * @return array
	 */
	private static function shape_out( array $e ) {
		return array(
			'key'      => (string) $e['key'],
			'site'     => (string) $e['site'],
			'name'     => (string) $e['name'],
			'item'     => (int) $e['item'],
			'title'    => (string) $e['title'],
			'author'   => (string) ( $e['author'] ?? '' ),
			'cover'    => (string) ( $e['cover'] ?? '' ),
			'kind'     => (string) ( $e['kind'] ?? 'book' ),
			'state'    => (string) $e['state'],
			'since'    => (string) ( $e['since'] ?? '' ),
			'handover' => (string) ( $e['handover'] ?? '' ),
			'seen'     => ! empty( $e['seen'] ),
		);
	}

	/**
	 * Everything the owner's app needs: requests waiting, things given back, answers, and what you've borrowed.
	 *
	 * @return array
	 */
	public static function news() {
		return array(
			'incoming' => array_values(
				array_map(
					array( self::class, 'shape_in' ),
					array_filter( self::incoming(), static fn( $r ) => 'open' === $r['state'] || ( 'lent' === $r['state'] && ! empty( $r['returning'] ) ) )
				)
			),
			'borrowed' => array_values( array_map( array( self::class, 'shape_out' ), self::borrowed() ) ),
		);
	}

	/**
	 * What visitors see on your page: what you've borrowed, with no cover, no name and no link.
	 *
	 * @return array[] { title, kind }
	 */
	public static function public_borrowed() {
		$out = array();
		foreach ( self::borrowed() as $e ) {
			if ( in_array( $e['state'], array( 'borrowed', 'returning' ), true ) ) {
				$out[] = array(
					'title' => (string) $e['title'],
					'kind'  => (string) ( $e['kind'] ?? 'book' ),
				);
			}
		}
		return $out;
	}

	/**
	 * First name only, for "With Ben".
	 *
	 * @param string $name Full name.
	 * @return string
	 */
	private static function first( $name ) {
		$parts = preg_split( '/\s+/', trim( (string) $name ) );
		return (string) ( $parts[0] ?? $name );
	}

	/**
	 * Tell the owner's phone, if they turned notifications on.
	 *
	 * @param string $title What happened.
	 * @param string $hash  Where in the app.
	 */
	private static function push( $title, $hash ) {
		if ( class_exists( __NAMESPACE__ . '\\Digest' ) && method_exists( __NAMESPACE__ . '\\Digest', 'push_all' ) ) {
			Digest::push_all(
				array(
					'title' => $title,
					'body'  => '',
					'url'   => home_url( '/app/' . $hash ),
				)
			);
		}
	}

	/* ── Routes ── */

	/**
	 * REST routes.
	 */
	public static function routes() {
		// From other Stublis.
		Rest::register(
			'/library/msg',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$res = self::receive( (string) $r->get_param( 'site' ), (string) $r->get_param( 'id' ), (string) $r->get_param( 'token' ) );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => '__return_true',
			)
		);
		Rest::register(
			'/library/msg/check',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					return rest_ensure_response( self::check( (string) $r->get_param( 'id' ), (string) $r->get_param( 'token' ), (string) $r->get_param( 'for' ) ) );
				},
				'permission_callback' => '__return_true',
			)
		);
		// The owner's app.
		$owner = array( Rest::class, 'is_owner' );
		Rest::register(
			'/library/news',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static fn() => rest_ensure_response( self::news() ),
				'permission_callback' => $owner,
			)
		);
		Rest::register(
			'/library/ask',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$res = self::ask( (int) $r->get_param( 'follow' ), (int) $r->get_param( 'item' ), (string) $r->get_param( 'note' ), (array) $r->get_param( 'shown' ) );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => $owner,
			)
		);
		Rest::register(
			'/library/asks/(?P<id>[a-z0-9]+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$res = self::act( (string) $r['id'], sanitize_key( (string) $r->get_param( 'action' ) ) );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => $owner,
			)
		);
		Rest::register(
			'/library/borrowed/(?P<key>[a-f0-9]{32})',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					if ( 'given' === $r->get_param( 'action' ) ) {
						$res = self::give_back( (string) $r['key'] );
						return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
					}
					self::seen( (string) $r['key'] );
					return rest_ensure_response( array( 'ok' => true ) );
				},
				'permission_callback' => $owner,
			)
		);
	}
}
