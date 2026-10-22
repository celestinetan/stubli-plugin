<?php
/**
 * The daily summary (DESIGN §12): at most one a day, at the time the owner
 * picks, and only when something is new. Sent to the installed app as a
 * phone notification (Web Push), with email as the fallback.
 *
 * Web Push is implemented here without outside libraries: VAPID (RFC 8292)
 * for the sender's identity and aes128gcm (RFC 8291) for the message, using
 * PHP's OpenSSL. Push services only ever see encrypted bytes.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Daily summary and phone notifications.
 */
class Digest {

	const SUBS   = 'stubli_push_subs';
	const VAPID  = 'stubli_vapid';
	const SENT   = 'stubli_digest_sent';
	const MAX_SUBS = 10;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( Install::CRON_HOOK, array( self::class, 'maybe_send' ), 30 );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/* ── What's new since the last summary ── */

	/**
	 * Counts for the summary.
	 *
	 * @param int $since Unix time.
	 * @return array { waiting, comments, guestbook, posts, people[] }.
	 */
	public static function summary( $since ) {
		global $wpdb;
		$after     = gmdate( 'Y-m-d H:i:s', (int) $since );
		$guestbook = Guestbook::page_id();
		$owner_ids = get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ), 'fields' => 'ID' ) );
		$out       = array(
			'waiting'   => 0,
			'comments'  => 0,
			'guestbook' => 0,
			'posts'     => 0,
			'people'    => array(),
		);
		$comments  = get_comments(
			array(
				'status'     => 'all',
				'number'     => 500,
				'date_query' => array( array( 'after' => $after, 'column' => 'comment_date_gmt' ) ),
			)
		);
		foreach ( $comments as $c ) {
			if ( in_array( (int) $c->user_id, array_map( 'intval', $owner_ids ), true ) || 'spam' === $c->comment_approved || 'trash' === $c->comment_approved ) {
				continue;
			}
			if ( '0' === (string) $c->comment_approved ) {
				if ( ! get_comment_meta( $c->comment_ID, 'stubli_later', true ) ) {
					++$out['waiting'];
				}
			} elseif ( (int) $c->comment_post_ID === (int) $guestbook ) {
				++$out['guestbook'];
			} else {
				++$out['comments'];
			}
		}
		$items = Install::items_table();
		$fols  = Install::follows_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin tables.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT f.name, f.site_url, COUNT(*) AS n FROM {$items} i JOIN {$fols} f ON f.id = i.follow_id WHERE i.published > %s AND f.muted = 0 GROUP BY f.id ORDER BY MAX(i.published) DESC", $after ) );
		foreach ( (array) $rows as $r ) {
			$out['posts'] += (int) $r->n;
			$out['people'][] = '' !== (string) $r->name ? (string) $r->name : (string) wp_parse_url( $r->site_url, PHP_URL_HOST );
		}
		return $out;
	}

	/**
	 * The summary in words. Empty when nothing is new.
	 *
	 * @param array $s Summary.
	 * @return array { title, body, url } or empty array.
	 */
	public static function message( array $s ) {
		$lines = array();
		if ( $s['waiting'] ) {
			/* translators: %d: number */
			$lines[] = sprintf( _n( '%d comment is waiting for your OK', '%d comments are waiting for your OK', $s['waiting'], 'stubli' ), $s['waiting'] );
		}
		if ( $s['comments'] ) {
			/* translators: %d: number */
			$lines[] = sprintf( _n( '%d new comment', '%d new comments', $s['comments'], 'stubli' ), $s['comments'] );
		}
		if ( $s['guestbook'] ) {
			/* translators: %d: number */
			$lines[] = sprintf( _n( '%d new guest book entry', '%d new guest book entries', $s['guestbook'], 'stubli' ), $s['guestbook'] );
		}
		if ( $s['posts'] ) {
			$people = array_slice( $s['people'], 0, 2 );
			$more   = count( $s['people'] ) - count( $people );
			$who    = 2 === count( $people ) ? sprintf( /* translators: 1, 2: names */ __( '%1$s and %2$s', 'stubli' ), $people[0], $people[1] ) : (string) ( $people[0] ?? '' );
			if ( $more > 0 ) {
				/* translators: 1: names, 2: number of other people */
				$who = sprintf( _n( '%1$s and %2$d other', '%1$s and %2$d others', $more, 'stubli' ), implode( ', ', $people ), $more );
			}
			/* translators: 1: number of posts, 2: names */
			$lines[] = sprintf( _n( '%1$d new post from %2$s', '%1$d new posts from %2$s', $s['posts'], 'stubli' ), $s['posts'], $who );
		}
		if ( ! $lines ) {
			return array();
		}
		return array(
			'title' => __( 'Your Stubli today', 'stubli' ),
			'body'  => implode( ' · ', $lines ),
			'url'   => home_url( $s['waiting'] || $s['comments'] || $s['guestbook'] ? '/app/#whatsnew' : '/app/#home' ),
		);
	}

	/**
	 * Called every 15 minutes: send today's summary once the chosen time has passed.
	 *
	 * @param bool $force Send now regardless of the time (tests).
	 * @return string What happened.
	 */
	public static function maybe_send( $force = false ) {
		$tz    = wp_timezone();
		$now   = new \DateTimeImmutable( 'now', $tz );
		$today = $now->format( 'Y-m-d' );
		$last  = (array) get_option( self::SENT, array() );
		if ( ! $force ) {
			$at = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $today . ' ' . Settings::get()['digest_time'], $tz );
			if ( ! $at || $now < $at || ( $last['day'] ?? '' ) === $today ) {
				return 'not yet';
			}
		}
		$since = isset( $last['at'] ) ? (int) $last['at'] : time() - DAY_IN_SECONDS;
		$since = max( $since, time() - 2 * DAY_IN_SECONDS );
		update_option(
			self::SENT,
			array(
				'day' => $today,
				'at'  => time(),
			),
			false
		);
		$msg = self::message( self::summary( $since ) );
		if ( ! $msg ) {
			return 'nothing new';
		}
		$pushed = self::push_all( $msg );
		$mailed = false;
		if ( Settings::get()['digest_email'] ) {
			$mailed = self::email( $msg );
		}
		return sprintf( 'pushed %d, email %s', $pushed, $mailed ? 'yes' : 'no' );
	}

	/**
	 * Email fallback.
	 *
	 * @param array $msg Message.
	 * @return bool
	 */
	public static function email( array $msg ) {
		$to = (string) get_option( 'admin_email' );
		if ( ! is_email( $to ) ) {
			return false;
		}
		$body = $msg['body'] . "\n\n" . __( 'Open Stubli:', 'stubli' ) . ' ' . $msg['url'] . "\n\n" .
			__( 'You get at most one of these a day, and only when something is new. Turn it off in the app: You → Daily summary.', 'stubli' );
		return wp_mail( $to, $msg['title'], str_replace( ' · ', "\n", $body ) );
	}

	/* ── Web Push ── */

	/**
	 * base64url without padding.
	 *
	 * @param string $s Bytes.
	 * @return string
	 */
	public static function b64u( $s ) {
		return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decode base64url.
	 *
	 * @param string $s Text.
	 * @return string Bytes.
	 */
	public static function unb64u( $s ) {
		$s = strtr( (string) $s, '-_', '+/' );
		return (string) base64_decode( $s . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * The site's VAPID key pair, made on first use.
	 *
	 * @return array|null { private (PEM), public (raw 65 bytes, base64url) }.
	 */
	public static function vapid() {
		$v = get_option( self::VAPID );
		if ( is_array( $v ) && ! empty( $v['private'] ) && ! empty( $v['public'] ) ) {
			return $v;
		}
		if ( ! function_exists( 'openssl_pkey_new' ) ) {
			return null;
		}
		$key = openssl_pkey_new(
			array(
				'curve_name'       => 'prime256v1',
				'private_key_type' => OPENSSL_KEYTYPE_EC,
			)
		);
		if ( ! $key || ! openssl_pkey_export( $key, $pem ) ) {
			return null;
		}
		$v = array(
			'private' => $pem,
			'public'  => self::b64u( self::raw_public( $key ) ),
		);
		update_option( self::VAPID, $v, false );
		return $v;
	}

	/**
	 * Uncompressed P-256 public point (0x04 || X || Y).
	 *
	 * @param \OpenSSLAsymmetricKey|resource $key Key.
	 * @return string 65 bytes.
	 */
	private static function raw_public( $key ) {
		$d = openssl_pkey_get_details( $key );
		return "\x04" . str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT );
	}

	/**
	 * A raw P-256 public point as a PEM key.
	 *
	 * @param string $raw 65 bytes.
	 * @return string PEM.
	 */
	private static function pem_public( $raw ) {
		$der = hex2bin( '3059301306072a8648ce3d020106082a8648ce3d030107034200' ) . $raw;
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * A DER ECDSA signature as the 64-byte r||s that JWT ES256 needs.
	 *
	 * @param string $der Signature.
	 * @return string
	 */
	private static function der_to_raw( $der ) {
		$p    = 2;
		$out  = '';
		for ( $i = 0; $i < 2; $i++ ) {
			$len  = ord( $der[ $p + 1 ] );
			$int  = substr( $der, $p + 2, $len );
			$out .= str_pad( ltrim( $int, "\0" ), 32, "\0", STR_PAD_LEFT );
			$p   += 2 + $len;
		}
		return $out;
	}

	/**
	 * VAPID Authorization header for a push endpoint.
	 *
	 * @param string $endpoint Push endpoint URL.
	 * @return string|null
	 */
	public static function vapid_header( $endpoint ) {
		$v = self::vapid();
		if ( ! $v ) {
			return null;
		}
		$parts = wp_parse_url( $endpoint );
		$aud   = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		$email = (string) get_option( 'admin_email' );
		$jwt   = self::b64u( '{"typ":"JWT","alg":"ES256"}' ) . '.' . self::b64u(
			wp_json_encode(
				array(
					'aud' => $aud,
					'exp' => time() + 12 * HOUR_IN_SECONDS,
					'sub' => is_email( $email ) ? 'mailto:' . $email : home_url( '/' ),
				),
				JSON_UNESCAPED_SLASHES
			)
		);
		if ( ! openssl_sign( $jwt, $sig, $v['private'], OPENSSL_ALGO_SHA256 ) ) {
			return null;
		}
		return 'vapid t=' . $jwt . '.' . self::b64u( self::der_to_raw( $sig ) ) . ', k=' . $v['public'];
	}

	/**
	 * Encrypt a message for one subscription (RFC 8291, aes128gcm).
	 *
	 * @param string $payload Plain text.
	 * @param string $p256dh  Subscriber public key, base64url.
	 * @param string $auth    Subscriber auth secret, base64url.
	 * @return string|null Body bytes.
	 */
	public static function encrypt( $payload, $p256dh, $auth ) {
		$ua_public = self::unb64u( $p256dh );
		$secret    = self::unb64u( $auth );
		if ( 65 !== strlen( $ua_public ) || "\x04" !== $ua_public[0] || 16 !== strlen( $secret ) ) {
			return null;
		}
		$eph = openssl_pkey_new(
			array(
				'curve_name'       => 'prime256v1',
				'private_key_type' => OPENSSL_KEYTYPE_EC,
			)
		);
		$peer = openssl_pkey_get_public( self::pem_public( $ua_public ) );
		if ( ! $eph || ! $peer ) {
			return null;
		}
		$shared = openssl_pkey_derive( $peer, $eph, 32 );
		if ( false === $shared ) {
			return null;
		}
		$as_public = self::raw_public( $eph );
		$ikm       = hash_hkdf( 'sha256', $shared, 32, "WebPush: info\0" . $ua_public . $as_public, $secret );
		$salt      = random_bytes( 16 );
		$cek       = hash_hkdf( 'sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt );
		$nonce     = hash_hkdf( 'sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt );
		$tag       = '';
		$cipher    = openssl_encrypt( $payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag );
		if ( false === $cipher ) {
			return null;
		}
		return $salt . pack( 'N', 4096 ) . chr( 65 ) . $as_public . $cipher . $tag;
	}

	/**
	 * Send one message to one subscription.
	 *
	 * @param array $sub Subscription { endpoint, p256dh, auth }.
	 * @param array $msg Message.
	 * @return int HTTP status (0 on failure to send).
	 */
	public static function push( array $sub, array $msg ) {
		$body = self::encrypt( wp_json_encode( $msg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), $sub['p256dh'], $sub['auth'] );
		$auth = self::vapid_header( $sub['endpoint'] );
		if ( null === $body || null === $auth ) {
			return 0;
		}
		$res = wp_safe_remote_post(
			$sub['endpoint'],
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization'    => $auth,
					'Content-Encoding' => 'aes128gcm',
					'Content-Type'     => 'application/octet-stream',
					'TTL'              => (string) ( 12 * HOUR_IN_SECONDS ),
					'Urgency'          => 'normal',
					'Topic'            => 'stubli-daily',
				),
				'body'    => $body,
			)
		);
		return is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	}

	/**
	 * Send to every subscribed device; forget the ones the push service says are gone.
	 *
	 * @param array $msg Message.
	 * @return int How many accepted it.
	 */
	public static function push_all( array $msg ) {
		$subs = (array) get_option( self::SUBS, array() );
		$ok   = 0;
		foreach ( $subs as $key => $sub ) {
			$code = self::push( $sub, $msg );
			if ( $code >= 200 && $code < 300 ) {
				++$ok;
			} elseif ( 404 === $code || 410 === $code ) {
				unset( $subs[ $key ] );
			}
		}
		update_option( self::SUBS, $subs, false );
		return $ok;
	}

	/**
	 * Is this a push service we should send to? https only, never this site's network.
	 *
	 * @param string $url Endpoint.
	 * @return bool
	 */
	private static function valid_endpoint( $url ) {
		return (bool) wp_http_validate_url( $url ) && 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) || (bool) apply_filters( 'stubli_push_allow_endpoint', false, $url );
	}

	/* ── REST ── */

	/**
	 * Routes (owner only).
	 */
	public static function routes() {
		$owner = array( Rest::class, 'is_owner' );
		Rest::register(
			'/push',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static function () {
						$v = self::vapid();
						return rest_ensure_response(
							array(
								'key'     => $v ? $v['public'] : '',
								'devices' => count( (array) get_option( self::SUBS, array() ) ),
								'time'    => Settings::get()['digest_time'],
								'email'   => Settings::get()['digest_email'],
								'address' => (string) get_option( 'admin_email' ),
							)
						);
					},
					'permission_callback' => $owner,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$endpoint = esc_url_raw( (string) $r['endpoint'] );
						$keys     = (array) $r['keys'];
						if ( ! self::valid_endpoint( $endpoint ) || empty( $keys['p256dh'] ) || empty( $keys['auth'] ) ) {
							return new \WP_Error( 'stubli_push', __( 'This phone didn\'t give a usable address for notifications.', 'stubli' ), array( 'status' => 400 ) );
						}
						$subs = (array) get_option( self::SUBS, array() );
						$subs[ md5( $endpoint ) ] = array(
							'endpoint' => $endpoint,
							'p256dh'   => sanitize_text_field( (string) $keys['p256dh'] ),
							'auth'     => sanitize_text_field( (string) $keys['auth'] ),
							'device'   => mb_substr( sanitize_text_field( (string) $r['device'] ), 0, 60 ),
							'since'    => time(),
						);
						update_option( self::SUBS, array_slice( $subs, -self::MAX_SUBS, null, true ), false );
						return rest_ensure_response( array( 'devices' => count( $subs ) ) );
					},
					'permission_callback' => $owner,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$subs = (array) get_option( self::SUBS, array() );
						unset( $subs[ md5( esc_url_raw( (string) $r['endpoint'] ) ) ] );
						update_option( self::SUBS, $subs, false );
						return rest_ensure_response( array( 'devices' => count( $subs ) ) );
					},
					'permission_callback' => $owner,
				),
			)
		);
		Rest::register(
			'/push/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function () {
					$msg = self::message( self::summary( time() - DAY_IN_SECONDS ) );
					if ( ! $msg ) {
						$msg = array(
							'title' => __( 'Your Stubli today', 'stubli' ),
							'body'  => __( 'This is how your daily summary will look. Nothing new right now.', 'stubli' ),
							'url'   => home_url( '/app/#whatsnew' ),
						);
					}
					return rest_ensure_response( array( 'sent' => self::push_all( $msg ) ) );
				},
				'permission_callback' => $owner,
			)
		);
	}

	/**
	 * The service worker: shows the summary and opens the app when tapped.
	 */
	public static function service_worker() {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . wp_parse_url( home_url( '/app/' ), PHP_URL_PATH ) );
		header( 'Cache-Control: no-cache' );
		$icon = STUBLI_PLUGIN_URL . 'assets/app/icon-192.png';
		$app  = home_url( '/app/' );
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded values in a script.
		echo '/* Stubli: the daily summary. No caching, no tracking. */' . "\n";
		echo 'var ICON = ' . wp_json_encode( $icon ) . ', APP = ' . wp_json_encode( $app ) . ";\n";
		// phpcs:enable
		echo <<<'JS'
self.addEventListener( 'install', function () { self.skipWaiting(); } );
self.addEventListener( 'activate', function ( e ) { e.waitUntil( self.clients.claim() ); } );
self.addEventListener( 'push', function ( e ) {
	var d = {};
	try { d = e.data ? e.data.json() : {}; } catch ( err ) { d = {}; }
	e.waitUntil( self.registration.showNotification( d.title || 'Stubli', { body: d.body || '', icon: ICON, badge: ICON, tag: 'stubli-daily', renotify: false, data: { url: d.url || APP } } ) );
} );
self.addEventListener( 'notificationclick', function ( e ) {
	e.notification.close();
	var url = ( e.notification.data && e.notification.data.url ) || APP;
	e.waitUntil( self.clients.matchAll( { type: 'window', includeUncontrolled: true } ).then( function ( list ) {
		for ( var i = 0; i < list.length; i++ ) { if ( list[ i ].url.indexOf( APP ) === 0 && 'focus' in list[ i ] ) { list[ i ].navigate( url ); return list[ i ].focus(); } }
		return self.clients.openWindow( url );
	} ) );
} );
JS;
		exit;
	}
}
