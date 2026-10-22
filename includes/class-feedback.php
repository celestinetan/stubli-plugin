<?php
/**
 * Feedback: every Stubli can send a note to the Stubli team; stubli.app
 * (the hub) keeps them privately in one inbox.
 *
 * - Any site: POST /og/v1/feedback/send (owner only) passes the note on to
 *   the hub from the server, so the browser never talks to another site.
 * - The hub: POST /og/v1/feedback (public, rate-limited, honeypot) stores
 *   it as a private "Feedback" item, visible in the hub's dashboard and
 *   readable through the REST API by its administrators.
 *
 * Only what the sender sees on the form is stored: the kind, the message,
 * an optional reply address, an optional screenshot, the Stubli version
 * and a short device description. No IP address is kept.
 *
 * @package Stubli
 */

namespace Stubli;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Collect feedback for the Stubli team.
 */
class Feedback {

	const HUB      = 'https://stubli.app';
	const TYPE     = 'stubli_feedback';
	const KINDS    = array( 'idea', 'broken', 'love', 'host' );
	const MAX_TEXT = 4000;

	/**
	 * Is this site the place where feedback is kept?
	 *
	 * @return bool
	 */
	public static function is_hub() {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return (bool) apply_filters( 'stubli_feedback_is_hub', 'stubli.app' === $host );
	}

	/**
	 * Hub address (filterable, so tests can point at a local hub).
	 *
	 * @return string
	 */
	public static function hub() {
		return untrailingslashit( (string) apply_filters( 'stubli_feedback_hub', self::HUB ) );
	}

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		if ( self::is_hub() ) {
			add_action( 'init', array( self::class, 'register_type' ) );
			add_filter( 'manage_' . self::TYPE . '_posts_columns', array( self::class, 'columns' ) );
			add_action( 'manage_' . self::TYPE . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
		}
	}

	/**
	 * The private inbox (hub only).
	 */
	public static function register_type() {
		register_post_type(
			self::TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Feedback', 'stubli' ),
					'singular_name' => __( 'Feedback', 'stubli' ),
					'menu_name'     => __( 'Feedback', 'stubli' ),
					'all_items'     => __( 'All feedback', 'stubli' ),
					'edit_item'     => __( 'Feedback', 'stubli' ),
					'not_found'     => __( 'No feedback yet.', 'stubli' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => true,
				'rest_base'           => 'stubli-feedback',
				'menu_icon'           => 'dashicons-format-chat',
				'menu_position'       => 26,
				'supports'            => array( 'title', 'editor', 'custom-fields' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
			)
		);
		foreach ( array( '_stubli_kind', '_stubli_reply_to', '_stubli_version', '_stubli_device', '_stubli_source', '_stubli_screenshot' ) as $key ) {
			register_post_meta(
				self::TYPE,
				$key,
				array(
					'type'          => '_stubli_screenshot' === $key ? 'integer' : 'string',
					'single'        => true,
					'show_in_rest'  => array( 'name' => ltrim( substr( $key, 3 ), '_' ) ),
					'auth_callback' => static fn() => current_user_can( 'edit_others_posts' ),
				)
			);
		}
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		$fields = array(
			'kind'     => array(
				'type'     => 'string',
				'enum'     => self::KINDS,
				'required' => true,
			),
			'message'  => array(
				'type'     => 'string',
				'required' => true,
			),
			'reply_to' => array(
				'type'    => 'string',
				'default' => '',
			),
			'device'   => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		Rest::register(
			'/feedback/send',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'send' ),
				'permission_callback' => static fn() => current_user_can( 'publish_posts' ),
				'args'                => $fields + array(
					'include_address' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		if ( self::is_hub() ) {
			Rest::register(
			'/feedback',
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'receive' ),
					'permission_callback' => '__return_true',
					'args'                => $fields + array(
						'version' => array(
							'type'    => 'string',
							'default' => '',
						),
						'source'  => array(
							'type'    => 'string',
							'enum'    => array( 'app', 'web' ),
							'default' => 'web',
						),
						'website' => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				)
			);
		}
	}

	/**
	 * From a site's own app: pass the note on to the hub.
	 *
	 * @param WP_REST_Request $r Request.
	 * @return array|WP_Error
	 */
	public static function send( WP_REST_Request $r ) {
		$note = array(
			'kind'     => (string) $r['kind'],
			'message'  => (string) $r['message'],
			'reply_to' => $r['include_address'] ? home_url( '/' ) : '',
			'device'   => (string) $r['device'],
			'version'  => VERSION,
			'source'   => 'app',
		);
		$file = $r->get_file_params()['screenshot'] ?? null;
		if ( self::is_hub() ) {
			$result = self::store( $note, $file );
			return is_wp_error( $result ) ? $result : array( 'sent' => true );
		}
		return self::forward( $note, $file );
	}

	/**
	 * Pass a note on to the hub without a screenshot (host reports).
	 *
	 * @param array $note Fields.
	 * @return array|WP_Error
	 */
	public static function forward_note( array $note ) {
		return self::forward( $note, null );
	}

	/**
	 * Server-to-server post to the hub (multipart when there's a screenshot).
	 *
	 * @param array      $note Fields.
	 * @param array|null $file Uploaded screenshot.
	 * @return array|WP_Error
	 */
	private static function forward( array $note, $file ) {
		$boundary = 'stubli' . wp_generate_password( 20, false );
		$body     = '';
		foreach ( $note as $k => $v ) {
			$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
		}
		if ( $file && empty( $file['error'] ) && is_uploaded_file( $file['tmp_name'] ) && $file['size'] <= 8 * MB_IN_BYTES ) {
			$type  = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
			$mime  = in_array( $type['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ? $type['type'] : '';
			if ( $mime ) {
				$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"screenshot\"; filename=\"screenshot.{$type['ext']}\"\r\nContent-Type: {$mime}\r\n\r\n" . file_get_contents( $file['tmp_name'] ) . "\r\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}
		$body .= "--{$boundary}--\r\n";
		$res   = wp_safe_remote_post(
			add_query_arg( 'rest_route', '/' . LEGACY_NS . '/feedback', self::hub() . '/' ),
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'stubli_feedback_offline', __( 'Stubli couldn\'t reach the Stubli team just now. Your note is still here; try again in a moment.', 'stubli' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code >= 200 && $code < 300 ) {
			return array( 'sent' => true );
		}
		return new WP_Error( 'stubli_feedback_refused', is_array( $data ) && ! empty( $data['message'] ) ? (string) $data['message'] : __( 'The note didn\'t go through. Try again in a moment.', 'stubli' ), array( 'status' => $code ? $code : 502 ) );
	}

	/**
	 * On the hub: accept a note from anyone, gently rate-limited.
	 *
	 * @param WP_REST_Request $r Request.
	 * @return array|WP_Error
	 */
	public static function receive( WP_REST_Request $r ) {
		if ( '' !== trim( (string) $r['website'] ) ) {
			return array( 'sent' => true ); // A bot filled the hidden field; pretend all is well.
		}
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$who   = 'stubli_fb_' . substr( hash_hmac( 'sha256', $ip, wp_salt() ), 0, 20 );
		$n     = (int) get_transient( $who );
		$all   = (int) get_transient( 'stubli_fb_all' );
		if ( $n >= 10 || $all >= 300 ) {
			return new WP_Error( 'stubli_rate_limited', __( 'Thank you, that\'s plenty for now. Please send more in an hour.', 'stubli' ), array( 'status' => 429 ) );
		}
		set_transient( $who, $n + 1, HOUR_IN_SECONDS );
		set_transient( 'stubli_fb_all', $all + 1, DAY_IN_SECONDS );
		$result = self::store(
			array(
				'kind'     => (string) $r['kind'],
				'message'  => (string) $r['message'],
				'reply_to' => (string) $r['reply_to'],
				'device'   => (string) $r['device'],
				'version'  => (string) $r['version'],
				'source'   => (string) $r['source'],
			),
			$r->get_file_params()['screenshot'] ?? null
		);
		return is_wp_error( $result ) ? $result : array( 'sent' => true );
	}

	/**
	 * Keep one note (hub only).
	 *
	 * @param array      $note Fields.
	 * @param array|null $file Screenshot upload.
	 * @return int|WP_Error Post ID.
	 */
	public static function store( array $note, $file = null ) {
		$kind    = in_array( $note['kind'], self::KINDS, true ) ? $note['kind'] : 'idea';
		$message = trim( mb_substr( sanitize_textarea_field( (string) $note['message'] ), 0, self::MAX_TEXT ) );
		if ( '' === $message ) {
			return new WP_Error( 'stubli_feedback_empty', __( 'Write a few words first.', 'stubli' ), array( 'status' => 400 ) );
		}
		$labels  = array(
			'idea'   => __( 'Idea', 'stubli' ),
			'broken' => __( 'Not working', 'stubli' ),
			'love'   => __( 'Kind words', 'stubli' ),
			'host'   => __( 'Host report', 'stubli' ),
		);
		$reply   = trim( mb_substr( sanitize_text_field( (string) $note['reply_to'] ), 0, 200 ) );
		$post_id = wp_insert_post(
			array(
				'post_type'    => self::TYPE,
				'post_status'  => 'private',
				'post_title'   => '[' . $labels[ $kind ] . '] ' . wp_trim_words( $message, 10, '…' ),
				'post_content' => $message,
				'post_author'  => 0,
				'meta_input'   => array(
					'_stubli_kind'     => $kind,
					'_stubli_reply_to' => $reply,
					'_stubli_version'  => mb_substr( sanitize_text_field( (string) $note['version'] ), 0, 20 ),
					'_stubli_device'   => mb_substr( sanitize_text_field( (string) $note['device'] ), 0, 60 ),
					'_stubli_source'   => 'app' === $note['source'] ? 'app' : 'web',
				),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		if ( $file && empty( $file['error'] ) && ! empty( $file['tmp_name'] ) && (int) $file['size'] <= 8 * MB_IN_BYTES ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
			if ( in_array( $type['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
				$file['name'] = 'feedback-' . strtolower( wp_generate_password( 16, false ) ) . '.' . $type['ext'];
				$att          = media_handle_sideload( $file, $post_id );
				if ( ! is_wp_error( $att ) ) {
					update_post_meta( $post_id, '_stubli_screenshot', (int) $att );
				}
			}
		}
		return (int) $post_id;
	}

	/**
	 * Inbox columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function columns( $cols ) {
		return array(
			'cb'           => $cols['cb'] ?? '',
			'title'        => __( 'Note', 'stubli' ),
			'stubli_from'      => __( 'Reply to', 'stubli' ),
			'stubli_where'     => __( 'Version · device', 'stubli' ),
			'stubli_shot'      => __( 'Screenshot', 'stubli' ),
			'date'         => __( 'Received', 'stubli' ),
		);
	}

	/**
	 * Inbox column content.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post.
	 */
	public static function column( $col, $post_id ) {
		if ( 'stubli_from' === $col ) {
			$r = (string) get_post_meta( $post_id, '_stubli_reply_to', true );
			echo '' !== $r ? esc_html( $r ) : '—';
		} elseif ( 'stubli_where' === $col ) {
			echo esc_html( trim( get_post_meta( $post_id, '_stubli_version', true ) . ' · ' . get_post_meta( $post_id, '_stubli_device', true ) . ' · ' . get_post_meta( $post_id, '_stubli_source', true ), ' ·' ) );
		} elseif ( 'stubli_shot' === $col ) {
			$a = (int) get_post_meta( $post_id, '_stubli_screenshot', true );
			echo $a ? '<a href="' . esc_url( wp_get_attachment_url( $a ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open', 'stubli' ) . '</a>' : '—';
		}
	}
}
