<?php
/**
 * Notes: private messages between people (design Q48).
 *
 * - From another Stubli: the sender's site tells ours a note is waiting; we
 *   fetch it back from the sender's site, so a note can only claim to come
 *   from a site that really sent it.
 * - From a guest without a Stubli: a short form with their name and how to
 *   reach them. Only the owner sees it; it's deleted after 90 days.
 *
 * Notes from people the owner follows arrive straight away; everyone else
 * waits under Requests. Nothing here is ever public.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Notes.
 */
class Notes {

	const POST_TYPE     = 'stubli_note';
	const MAX_LENGTH    = 1000;
	const GUEST_DAYS    = 90;
	const BLOCKED_GUEST = 'stubli_note_blocked';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( Install::CRON_HOOK, array( self::class, 'clean_up' ), 40 );
		add_action( 'admin_init', array( self::class, 'privacy_text' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'add_eraser' ) );
		add_action( 'init', array( self::class, 'mark_old_read' ), 20 );
		add_action( self::PUSH_HOOK, array( self::class, 'push' ) );
	}

	const PUSH_HOOK = 'stubli_letter_push';
	const READ      = '_stubli_read';

	/**
	 * Letters that arrived before the inbox existed (0.40.0) count as read,
	 * so nobody opens the new inbox to a wall of "unread".
	 */
	public static function mark_old_read() {
		if ( get_option( 'stubli_letters_read_marked' ) ) {
			return;
		}
		update_option( 'stubli_letters_read_marked', 1, false );
		foreach ( get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'posts_per_page' => 500, 'fields' => 'ids' ) ) as $id ) {
			update_post_meta( $id, self::READ, 1 );
		}
	}

	/**
	 * Who a letter is with: their site's origin, or one guest letter on its own.
	 *
	 * @param array $n Shaped note.
	 * @return string
	 */
	public static function with_key( array $n ) {
		if ( 'guest' === $n['kind'] ) {
			return 'guest-' . (int) $n['id'];
		}
		$o = Follows::origin( (string) $n['site'] );
		return $o ? $o : 'note-' . (int) $n['id'];
	}

	/**
	 * The inbox: requests, then one conversation per person, newest first.
	 *
	 * @return array { requests: [], conversations: [] }
	 */
	public static function inbox() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 500,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$requests = array();
		$convs    = array();
		foreach ( $posts as $p ) {
			$n = self::shape( $p );
			if ( 'in' === $n['dir'] && 'request' === $n['state'] ) {
				$requests[] = $n;
				continue;
			}
			$k = self::with_key( $n );
			if ( ! isset( $convs[ $k ] ) ) {
				$convs[ $k ] = array(
					'with'   => $k,
					'kind'   => 'out' === $n['dir'] ? 'stubli' : $n['kind'],
					'site'   => $n['site'],
					'name'   => '',
					'avatar' => '',
					'last'   => array(
						'text' => $n['text'],
						'date' => $n['date'],
						'dir'  => $n['dir'],
					),
					'unread' => false,
				);
			}
			if ( 'in' === $n['dir'] ) {
				if ( '' === $convs[ $k ]['name'] ) {
					$convs[ $k ]['name']   = $n['name'];
					$convs[ $k ]['avatar'] = $n['avatar'];
				}
				if ( ! get_post_meta( $p->ID, self::READ, true ) ) {
					$convs[ $k ]['unread'] = true;
				}
			}
		}
		foreach ( $convs as $k => $c ) {
			if ( '' === $c['name'] ) {
				$f                     = $c['site'] ? Follows::find_by_site( trailingslashit( Follows::origin( $c['site'] ) ) ) : null;
				$convs[ $k ]['name']   = $f && $f['name'] ? $f['name'] : (string) wp_parse_url( (string) $c['site'], PHP_URL_HOST );
				$convs[ $k ]['avatar'] = $f ? $f['avatar_url'] : '';
			}
		}
		return array(
			'requests'      => $requests,
			'conversations' => array_values( $convs ),
		);
	}

	/**
	 * One conversation, oldest letter first.
	 *
	 * @param string $with Key from inbox().
	 * @return array[]
	 */
	public static function thread( $with ) {
		$out = array();
		foreach ( get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'posts_per_page' => 500, 'orderby' => 'date', 'order' => 'ASC' ) ) as $p ) {
			$n = self::shape( $p );
			if ( self::with_key( $n ) === (string) $with && ! ( 'in' === $n['dir'] && 'request' === $n['state'] ) ) {
				$out[] = $n;
			}
		}
		return $out;
	}

	/**
	 * Mark a conversation's letters as read.
	 *
	 * @param string $with Key.
	 * @return int How many were unread.
	 */
	public static function mark_read( $with ) {
		$n = 0;
		foreach ( self::thread( $with ) as $l ) {
			if ( 'in' === $l['dir'] && ! get_post_meta( $l['id'], self::READ, true ) ) {
				update_post_meta( $l['id'], self::READ, 1 );
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Unread letters and waiting requests, for the envelope's dot.
	 *
	 * @return int
	 */
	public static function unread() {
		$q = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_stubli_dir',
						'value' => 'in',
					),
					array(
						'key'     => self::READ,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * A letter arrived: tell the owner's phone, if they turned notifications on.
	 *
	 * @param int $id Letter.
	 */
	public static function push( $id ) {
		$p = get_post( (int) $id );
		if ( ! $p || self::POST_TYPE !== $p->post_type || ! class_exists( __NAMESPACE__ . '\\Digest' ) ) {
			return;
		}
		$n = self::shape( $p );
		Digest::push_all(
			array(
				/* translators: %s: name */
				'title' => sprintf( __( 'A letter from %s', 'stubli' ), $n['name'] ),
				'body'  => wp_trim_words( $n['text'], 16, '…' ),
				'url'   => home_url( '/app/#letters' ),
			)
		);
	}

	/**
	 * Suggested words for the site's privacy policy (Settings → Privacy).
	 */
	public static function privacy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'Stubli',
			'<p>' . esc_html__( 'Notes: if you leave a note on this site without a Stubli of your own, we keep your name, the way to reach you that you give us, and your message, so the owner can read it and write back. Only the owner sees it; it never appears on the site. Notes from visitors are deleted after 90 days, or sooner if you ask. To stop spam, a one-way code made from your network address is kept with the note; the address itself is not stored.', 'stubli' ) . '</p>' .
			'<p>' . esc_html__( 'If you send a note from your own Stubli, this site receives your site\'s address, name and photo along with the note.', 'stubli' ) . '</p>'
		);
	}

	/**
	 * Register the exporter for guest notes (Tools → Export Personal Data).
	 *
	 * @param array $list Exporters.
	 * @return array
	 */
	public static function add_exporter( $list ) {
		$list['stubli-notes'] = array(
			'exporter_friendly_name' => __( 'Stubli notes', 'stubli' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $list;
	}

	/**
	 * Register the eraser for guest notes (Tools → Erase Personal Data).
	 *
	 * @param array $list Erasers.
	 * @return array
	 */
	public static function add_eraser( $list ) {
		$list['stubli-notes'] = array(
			'eraser_friendly_name' => __( 'Stubli notes', 'stubli' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $list;
	}

	/**
	 * Guest notes left with this email address.
	 *
	 * @param string $email Email.
	 * @return int[]
	 */
	private static function by_contact( $email ) {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_key'       => '_stubli_contact', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (string) $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * Export.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function export( $email ) {
		$data = array();
		foreach ( self::by_contact( $email ) as $id ) {
			$p      = get_post( $id );
			$data[] = array(
				'group_id'    => 'stubli-notes',
				'group_label' => __( 'Notes', 'stubli' ),
				'item_id'     => 'stubli-note-' . $id,
				'data'        => array(
					array(
						'name'  => __( 'Name', 'stubli' ),
						'value' => (string) get_post_meta( $id, '_stubli_name', true ),
					),
					array(
						'name'  => __( 'Note', 'stubli' ),
						'value' => $p ? (string) $p->post_content : '',
					),
					array(
						'name'  => __( 'Date', 'stubli' ),
						'value' => $p ? (string) $p->post_date : '',
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function erase( $email ) {
		$ids = self::by_contact( $email );
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
		return array(
			'items_removed'  => count( $ids ) > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * The post type that stores notes (never public, never in feeds).
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'editor' ),
				'labels'              => array( 'name' => __( 'Notes', 'stubli' ) ),
			)
		);
	}

	/* ── Sending ── */

	/**
	 * Send a note to another Stubli.
	 *
	 * @param string $to     Their address.
	 * @param string $text   The note.
	 * @param int    $prompt Their prompt card it replies to, or -1.
	 * @param int    $reply  Our note it answers, if any.
	 * @return array|\WP_Error
	 */
	public static function send( $to, $text, $prompt = -1, $reply = 0 ) {
		$to   = esc_url_raw( trim( (string) $to ) );
		$text = self::clean_text( $text );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		if ( ! $to || ! in_array( wp_parse_url( $to, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			return new \WP_Error( 'stubli_bad_url', __( 'That doesn\'t look like a Stubli address.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( Follows::origin( $to ) === Follows::origin( home_url() ) ) {
			return new \WP_Error( 'stubli_self', __( 'That\'s your own site.', 'stubli' ), array( 'status' => 400 ) );
		}
		$quote = (int) $prompt >= 0 ? self::their_prompt( $to, (int) $prompt ) : null;
		$token = strtolower( wp_generate_password( 32, false ) );
		$id    = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => self::POST_TYPE,
					'post_status'  => 'private',
					'post_title'   => wp_trim_words( $text, 8, '…' ),
					'post_content' => $text,
					'meta_input'   => array(
						'_stubli_dir'    => 'out',
						'_stubli_site'   => $to,
						'_stubli_token'  => wp_hash( $token ),
						'_stubli_prompt' => $quote ? wp_json_encode( $quote ) : '',
						'_stubli_pidx'   => (int) $prompt,
						'_stubli_reply'  => (int) $reply,
					),
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$res  = Demo::person( $to ) ? array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"ok":true}',
		) : Rest::remote(
			$to,
			'/notes/in',
			array(
				'method'  => 'POST',
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'site'  => home_url( '/' ),
						'id'    => (int) $id,
						'token' => $token,
					)
				),
			)
		);
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$body = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
		// The proof token has done its job once the other site fetched the note.
		delete_post_meta( $id, '_stubli_token' );
		$ok = $code >= 200 && $code < 300 && ! empty( $body['ok'] );
		update_post_meta( $id, '_stubli_state', $ok ? 'sent' : 'failed' );
		if ( ! $ok ) {
			$msg = is_array( $body ) && ! empty( $body['message'] ) ? (string) $body['message'] : __( 'Their site didn\'t take the note just now. It may not have the latest Stubli yet. Try again later.', 'stubli' );
			return new \WP_Error( 'stubli_note_not_sent', $msg, array( 'status' => 502 ) );
		}
		if ( $reply ) {
			update_post_meta( (int) $reply, '_stubli_replied', current_time( 'mysql', true ) );
		}
		update_post_meta( $id, self::READ, 1 );
		return array(
			'id'   => (int) $id,
			'sent' => true,
		);
	}

	/**
	 * Their prompt card, for our own copy of the note.
	 *
	 * @param string $site Their address.
	 * @param int    $i    Position.
	 * @return array|null { q, a }
	 */
	public static function their_prompt( $site, $i ) {
		$res  = Rest::remote( $site, '/profile', array( 'timeout' => 10 ) );
		$data = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$p    = is_array( $data ) && isset( $data['prompts'][ $i ] ) && is_array( $data['prompts'][ $i ] ) ? $data['prompts'][ $i ] : null;
		return $p ? array(
			'q' => mb_substr( sanitize_text_field( (string) ( $p['q'] ?? '' ) ), 0, Prompts::MAX_Q ),
			'a' => mb_substr( sanitize_text_field( (string) ( $p['a'] ?? '' ) ), 0, Prompts::MAX_ANSWER ),
		) : null;
	}

	/* ── Receiving ── */

	/**
	 * Another Stubli says a note is waiting: fetch it from them and keep it.
	 *
	 * @param string $site  Sender's address.
	 * @param int    $id    Their note ID.
	 * @param string $token Proof token.
	 * @return array|\WP_Error
	 */
	public static function receive( $site, $id, $token ) {
		$site  = esc_url_raw( (string) $site );
		$token = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $token ) );
		if ( ! $site || (int) $id < 1 || strlen( $token ) < 20 ) {
			return new \WP_Error( 'stubli_note_bad', 'Bad request.', array( 'status' => 400 ) );
		}
		if ( Settings::on_break() ) {
			return new \WP_Error( 'stubli_on_break', __( 'They\'re taking a break, so notes are paused for now.', 'stubli' ), array( 'status' => 403 ) );
		}
		$origin = Follows::origin( $site );
		if ( ! self::under_limit( 'site_' . md5( $origin ), 20, DAY_IN_SECONDS ) ) {
			return new \WP_Error( 'stubli_note_limit', __( 'That\'s a lot of notes for one day. Try again tomorrow.', 'stubli' ), array( 'status' => 429 ) );
		}
		$res  = Rest::remote(
			$site,
			'/notes/check',
			array( 'timeout' => 15 ),
			array(
				'id'    => (int) $id,
				'token' => $token,
				'for'   => home_url( '/' ),
			)
		);
		$data = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $data['ok'] ) ) {
			return new \WP_Error( 'stubli_note_unproven', 'Not confirmed.', array( 'status' => 403 ) );
		}
		// Blocked sites get the same answer as everyone else, and nothing is kept.
		if ( Moderation::is_blocked( $site ) ) {
			return array( 'ok' => true );
		}
		$text = self::clean_text( $data['text'] ?? '' );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$pidx   = isset( $data['prompt'] ) ? (int) $data['prompt'] : -1;
		$quote  = $pidx >= 0 ? Prompts::at( $pidx ) : null;
		$follow = Follows::find_by_site( $origin . '/' );
		$state  = $follow ? 'inbox' : 'request';
		$new    = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => self::POST_TYPE,
					'post_status'  => 'private',
					'post_title'   => wp_trim_words( $text, 8, '…' ),
					'post_content' => $text,
					'meta_input'   => array(
						'_stubli_dir'    => 'in',
						'_stubli_kind'   => 'stubli',
						'_stubli_site'   => esc_url_raw( (string) ( $data['site'] ?? $site ) ),
						'_stubli_name'   => mb_substr( sanitize_text_field( (string) ( $data['name'] ?? '' ) ), 0, 80 ),
						'_stubli_avatar' => esc_url_raw( (string) ( $data['avatar'] ?? '' ) ),
						'_stubli_state'  => $state,
						'_stubli_prompt' => $quote ? wp_json_encode( $quote ) : '',
					),
				)
			),
			true
		);
		if ( is_wp_error( $new ) ) {
			return $new;
		}
		wp_schedule_single_event( time(), self::PUSH_HOOK, array( (int) $new ) );
		return array(
			'ok'    => true,
			'state' => $state,
		);
	}

	/**
	 * A guest without a Stubli leaves a note.
	 *
	 * @param array $in name, contact, text, prompt, website (a trap field).
	 * @return array|\WP_Error
	 */
	public static function guest( array $in ) {
		// A filled-in trap field means a bot: say thanks, keep nothing.
		if ( '' !== trim( (string) ( $in['website'] ?? '' ) ) ) {
			return array( 'ok' => true );
		}
		if ( Settings::on_break() ) {
			return new \WP_Error( 'stubli_on_break', __( 'They\'re taking a break, so notes are paused for now.', 'stubli' ), array( 'status' => 403 ) );
		}
		$name    = mb_substr( trim( sanitize_text_field( (string) ( $in['name'] ?? '' ) ) ), 0, 60 );
		$contact = mb_substr( trim( sanitize_text_field( (string) ( $in['contact'] ?? '' ) ) ), 0, 120 );
		$text    = self::clean_text( $in['text'] ?? '' );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		if ( '' === $name ) {
			return new \WP_Error( 'stubli_note_name', __( 'Add your name, so they know who it\'s from.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( '' === $contact ) {
			return new \WP_Error( 'stubli_note_contact', __( 'Add an email, phone number or other way to reach you, so they can write back.', 'stubli' ), array( 'status' => 400 ) );
		}
		$who = self::guest_hash();
		if ( in_array( $who, (array) get_option( self::BLOCKED_GUEST, array() ), true ) ) {
			return array( 'ok' => true );
		}
		if ( ! self::under_limit( 'guest_' . $who, 3, HOUR_IN_SECONDS ) || ! self::under_limit( 'guests', 30, DAY_IN_SECONDS ) ) {
			return new \WP_Error( 'stubli_note_limit', __( 'Lots of notes have arrived here recently. Please try again later.', 'stubli' ), array( 'status' => 429 ) );
		}
		$pidx  = isset( $in['prompt'] ) && '' !== (string) $in['prompt'] ? (int) $in['prompt'] : -1;
		$quote = $pidx >= 0 ? Prompts::at( $pidx ) : null;
		$id    = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => self::POST_TYPE,
					'post_status'  => 'private',
					'post_title'   => wp_trim_words( $text, 8, '…' ),
					'post_content' => $text,
					'meta_input'   => array(
						'_stubli_dir'     => 'in',
						'_stubli_kind'    => 'guest',
						'_stubli_name'    => $name,
						'_stubli_contact' => $contact,
						'_stubli_guest'   => $who,
						'_stubli_state'   => 'request',
						'_stubli_prompt'  => $quote ? wp_json_encode( $quote ) : '',
					),
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		wp_schedule_single_event( time(), self::PUSH_HOOK, array( (int) $id ) );
		return array( 'ok' => true );
	}

	/**
	 * Hash standing in for a guest (from their network address), so they can
	 * be blocked without keeping the address itself.
	 *
	 * @return string
	 */
	private static function guest_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return substr( wp_hash( 'stubli-note|' . $ip ), 0, 20 );
	}

	/**
	 * Count an event and say whether it's still under the limit.
	 *
	 * @param string $key    What's counted.
	 * @param int    $max    Allowed per window.
	 * @param int    $window Seconds.
	 * @return bool
	 */
	private static function under_limit( $key, $max, $window ) {
		$t = 'stubli_nl_' . md5( $key );
		$n = (int) get_transient( $t );
		if ( $n >= $max ) {
			return false;
		}
		set_transient( $t, $n + 1, $window );
		return true;
	}

	/**
	 * Trim and check a note's text.
	 *
	 * @param mixed $text Text.
	 * @return string|\WP_Error
	 */
	private static function clean_text( $text ) {
		$text = trim( sanitize_textarea_field( (string) $text ) );
		if ( '' === $text ) {
			return new \WP_Error( 'stubli_empty', __( 'Write a few words first.', 'stubli' ), array( 'status' => 400 ) );
		}
		if ( mb_strlen( $text ) > self::MAX_LENGTH ) {
			return new \WP_Error( 'stubli_note_long', __( 'That note is over 1,000 characters. Trim a little and it will fit.', 'stubli' ), array( 'status' => 400 ) );
		}
		return $text;
	}

	/* ── The owner's view ── */

	/**
	 * One note, shaped for the app.
	 *
	 * @param \WP_Post $p Note.
	 * @return array
	 */
	public static function shape( \WP_Post $p ) {
		$quote = json_decode( (string) get_post_meta( $p->ID, '_stubli_prompt', true ), true );
		$site  = (string) get_post_meta( $p->ID, '_stubli_site', true );
		$name  = (string) get_post_meta( $p->ID, '_stubli_name', true );
		return array(
			'id'      => (int) $p->ID,
			'dir'     => (string) get_post_meta( $p->ID, '_stubli_dir', true ),
			'kind'    => (string) get_post_meta( $p->ID, '_stubli_kind', true ),
			'state'   => (string) get_post_meta( $p->ID, '_stubli_state', true ),
			'name'    => '' !== $name ? $name : ( $site ? (string) wp_parse_url( $site, PHP_URL_HOST ) : '' ),
			'site'    => $site,
			'avatar'  => (string) get_post_meta( $p->ID, '_stubli_avatar', true ),
			'contact' => (string) get_post_meta( $p->ID, '_stubli_contact', true ),
			'text'    => (string) $p->post_content,
			'prompt'  => is_array( $quote ) ? $quote : null,
			'replied' => (bool) get_post_meta( $p->ID, '_stubli_replied', true ),
			'date'    => gmdate( 'c', (int) strtotime( $p->post_date_gmt . ' UTC' ) ),
		);
	}

	/**
	 * Notes for the app: arrived, waiting as requests, and sent.
	 *
	 * @return array
	 */
	public static function all() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$out = array(
			'inbox'    => array(),
			'requests' => array(),
			'sent'     => array(),
		);
		foreach ( $posts as $p ) {
			$n = self::shape( $p );
			if ( 'out' === $n['dir'] ) {
				$out['sent'][] = $n;
			} elseif ( 'request' === $n['state'] ) {
				$out['requests'][] = $n;
			} else {
				$out['inbox'][] = $n;
			}
		}
		return $out;
	}

	/**
	 * How many requests are waiting (for the What's new dot).
	 *
	 * @return int
	 */
	public static function waiting() {
		$q = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_stubli_state', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'request', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Accept, delete or block.
	 *
	 * @param int    $id     Note.
	 * @param string $action accept | delete | block.
	 * @return array|\WP_Error
	 */
	public static function act( $id, $action ) {
		$p = get_post( (int) $id );
		if ( ! $p || self::POST_TYPE !== $p->post_type ) {
			return new \WP_Error( 'stubli_not_found', __( 'That note is already gone.', 'stubli' ), array( 'status' => 404 ) );
		}
		if ( 'accept' === $action ) {
			update_post_meta( $p->ID, '_stubli_state', 'inbox' );
			return array( 'state' => 'inbox' );
		}
		if ( 'block' === $action ) {
			if ( 'guest' === get_post_meta( $p->ID, '_stubli_kind', true ) ) {
				$list   = (array) get_option( self::BLOCKED_GUEST, array() );
				$list[] = (string) get_post_meta( $p->ID, '_stubli_guest', true );
				update_option( self::BLOCKED_GUEST, array_values( array_unique( array_filter( $list ) ) ), false );
			} else {
				Moderation::block( (string) get_post_meta( $p->ID, '_stubli_site', true ) );
			}
		}
		if ( 'block' === $action || 'delete' === $action ) {
			wp_delete_post( $p->ID, true );
			return array( 'deleted' => true );
		}
		return new \WP_Error( 'stubli_bad_action', 'Unknown action.', array( 'status' => 400 ) );
	}

	/**
	 * Daily: guest notes go after 90 days.
	 */
	public static function clean_up() {
		$old = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'date_query'     => array( array( 'before' => self::GUEST_DAYS . ' days ago' ) ),
				'meta_key'       => '_stubli_kind', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'guest', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $old as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	/* ── REST ── */

	/**
	 * Routes.
	 */
	public static function routes() {
		Rest::register(
			'/notes',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function () {
					return rest_ensure_response( self::all() );
				},
				'permission_callback' => array( Rest::class, 'is_owner' ),
			)
		);
		Rest::register(
			'/notes/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$res = self::act( (int) $r['id'], (string) $r['action'] );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => array( Rest::class, 'is_owner' ),
			)
		);
		Rest::register(
			'/letters',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function () {
					return rest_ensure_response( self::inbox() );
				},
				'permission_callback' => array( Rest::class, 'is_owner' ),
			)
		);
		Rest::register(
			'/letters/thread',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						return rest_ensure_response( self::thread( (string) $r['with'] ) );
					},
					'permission_callback' => array( Rest::class, 'is_owner' ),
					'args'                => array( 'with' => array( 'type' => 'string', 'required' => true ) ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						return rest_ensure_response( array( 'read' => self::mark_read( (string) $r['with'] ), 'unread' => self::unread() ) );
					},
					'permission_callback' => array( Rest::class, 'is_owner' ),
					'args'                => array( 'with' => array( 'type' => 'string', 'required' => true ) ),
				),
			)
		);
		Rest::register(
			'/notes/send',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$res = self::send( (string) $r['to'], (string) $r['text'], isset( $r['prompt'] ) ? (int) $r['prompt'] : -1, (int) $r['reply'] );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => array( Rest::class, 'is_owner' ),
			)
		);
		Rest::register(
			'/notes/in',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$res = self::receive( (string) $r['site'], (int) $r['id'], (string) $r['token'] );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => '__return_true', // Proven by fetching the note back from the sender.
			)
		);
		Rest::register(
			'/notes/check',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					return rest_ensure_response( self::check( (int) $r['id'], (string) $r['token'], (string) $r['for'] ) );
				},
				'permission_callback' => '__return_true', // Answers only for the one site holding the note's token.
			)
		);
		Rest::register(
			'/notes/guest',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$res = self::guest( (array) $r->get_params() );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
				'permission_callback' => '__return_true', // Guests have no account; limits and a trap field keep spam out.
			)
		);
	}

	/**
	 * The receiving site asks us for a note we said we sent.
	 *
	 * @param int    $id    Our note.
	 * @param string $token Proof token.
	 * @param string $for   The receiving site.
	 * @return array
	 */
	public static function check( $id, $token, $for ) {
		$p = get_post( (int) $id );
		if ( ! $p || self::POST_TYPE !== $p->post_type || 'out' !== get_post_meta( $p->ID, '_stubli_dir', true ) ) {
			return array( 'ok' => false );
		}
		$hash = (string) get_post_meta( $p->ID, '_stubli_token', true );
		$to   = (string) get_post_meta( $p->ID, '_stubli_site', true );
		if ( '' === $hash || ! hash_equals( $hash, wp_hash( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $token ) ) ) ) || Follows::origin( $to ) !== Follows::origin( (string) $for ) ) {
			return array( 'ok' => false );
		}
		$profile = Posts::profile();
		return array(
			'ok'     => true,
			'text'   => (string) $p->post_content,
			'prompt' => (int) get_post_meta( $p->ID, '_stubli_pidx', true ),
			'name'   => $profile['name'],
			'avatar' => $profile['avatar'],
			'site'   => home_url( '/' ),
		);
	}
}
