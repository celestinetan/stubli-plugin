<?php
/**
 * News from Stubli (0.53.0): short notes from Celestine, shown in the app's
 * What's new, only for owners who said yes.
 *
 * - stubli.app (the hub) keeps the notes as private "Stubli news" items and
 *   lists the published ones at GET /stubli/v1/news (public, plain data).
 * - Every other Stubli fetches that list once a day, and only while the
 *   owner has news on (WordPress.org guideline 7: outside calls need
 *   consent). The request carries no site address, cookies or owner
 *   details: just "Stubli/<version>".
 * - The list is text and one stubli.app link, shown as plain text, never as
 *   code (guideline 8). One card at a time; "Got it" hides it for good.
 * - A "safety" note also goes to the owner's phone, once, if phone
 *   notifications are on.
 *
 * @package Stubli
 */

namespace Stubli;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * News from the Stubli team.
 */
class News {

	const TYPE     = 'stubli_news';
	const CRON     = 'stubli_news_fetch';
	const OPT      = 'stubli_news';         // Unset: not asked yet. 1: on. 0: not now.
	const ITEMS    = 'stubli_news_items';   // The last list fetched.
	const GONE     = 'stubli_news_gone';    // Ids the owner dismissed.
	const PUSHED   = 'stubli_news_pushed';  // Safety ids already sent to the phone.
	const MAX_TEXT = 280;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( self::CRON, array( self::class, 'fetch' ) );
		if ( Feedback::is_hub() ) {
			add_action( 'init', array( self::class, 'register_type' ) );
			add_action( 'add_meta_boxes', array( self::class, 'meta_box' ) );
			add_action( 'save_post_' . self::TYPE, array( self::class, 'save_meta' ) );
		}
		// Keep the daily check in step with the switch, also after updates and moves.
		add_action( 'init', array( self::class, 'schedule' ), 30 );
	}

	/**
	 * Has the owner turned news on?
	 *
	 * @return bool
	 */
	public static function is_on() {
		return 1 === (int) get_option( self::OPT, 0 );
	}

	/**
	 * Has the owner answered the question yet?
	 *
	 * @return bool
	 */
	public static function asked() {
		return false !== get_option( self::OPT, false );
	}

	/**
	 * Turn news on or off.
	 *
	 * @param bool $on New state.
	 */
	public static function set( $on ) {
		update_option( self::OPT, $on ? 1 : 0, false );
		if ( $on ) {
			// First look straight away, in the background.
			if ( ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_event( time() + 30, 'daily', self::CRON );
			}
			wp_schedule_single_event( time() + 5, self::CRON );
		} else {
			wp_clear_scheduled_hook( self::CRON );
			delete_option( self::ITEMS );
		}
	}

	/**
	 * Daily check only while news is on.
	 */
	public static function schedule() {
		if ( Feedback::is_hub() ) {
			return;
		}
		$next = wp_next_scheduled( self::CRON );
		if ( self::is_on() && ! $next ) {
			wp_schedule_event( time() + 60, 'daily', self::CRON );
		} elseif ( ! self::is_on() && $next ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/**
	 * Fetch the list from stubli.app (or, on the hub, read it).
	 *
	 * @return bool Fetched.
	 */
	public static function fetch() {
		if ( ! self::is_on() ) {
			return false;
		}
		if ( Feedback::is_hub() ) {
			$list = self::published();
		} else {
			$res = wp_safe_remote_get(
				Feedback::hub() . '/wp-json/' . REST_NS . '/news',
				array(
					'timeout'     => 10,
					'redirection' => 2,
					// Nothing about this site: WordPress would otherwise send its address here.
					'user-agent'  => 'Stubli/' . VERSION,
					'headers'     => array( 'Accept' => 'application/json' ),
				)
			);
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				return false;
			}
			$list = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		}
		$items = self::clean( is_array( $list ) ? $list : array() );
		update_option( self::ITEMS, $items, false );
		self::push_safety( $items );
		return true;
	}

	/**
	 * Keep only well-formed notes, as plain text, with stubli.app links.
	 *
	 * @param array $list Raw list.
	 * @return array
	 */
	public static function clean( array $list ) {
		$out = array();
		foreach ( array_slice( $list, 0, 10 ) as $n ) {
			if ( ! is_array( $n ) || empty( $n['id'] ) || empty( $n['title'] ) ) {
				continue;
			}
			// Links go to stubli.app only (or the hub that sent the list, for local tests).
			$link = isset( $n['link'] ) ? esc_url_raw( (string) $n['link'], array( 'https', 'http' ) ) : '';
			$hub  = wp_parse_url( Feedback::hub() );
			$at   = wp_parse_url( $link );
			$ok   = $link && ( ( 'https' === ( $at['scheme'] ?? '' ) && 'stubli.app' === ( $at['host'] ?? '' ) )
				|| ( ( $at['scheme'] ?? '' ) === ( $hub['scheme'] ?? 'https' ) && ( $at['host'] ?? '' ) === ( $hub['host'] ?? '' ) && ( $at['port'] ?? 0 ) === ( $hub['port'] ?? 0 ) ) );
			if ( ! $ok ) {
				$link = '';
			}
			$below = isset( $n['below'] ) ? preg_replace( '/[^0-9.]/', '', (string) $n['below'] ) : '';
			if ( '' !== $below && version_compare( VERSION, $below, '>=' ) ) {
				continue; // Meant for older versions only.
			}
			$out[] = array(
				'id'    => mb_substr( sanitize_key( (string) $n['id'] ), 0, 40 ),
				'date'  => isset( $n['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}/', (string) $n['date'] ) ? substr( (string) $n['date'], 0, 10 ) : '',
				'kind'  => isset( $n['kind'] ) && 'safety' === $n['kind'] ? 'safety' : 'news',
				'title' => mb_substr( sanitize_text_field( (string) $n['title'] ), 0, 80 ),
				'text'  => mb_substr( sanitize_textarea_field( (string) ( $n['text'] ?? '' ) ), 0, self::MAX_TEXT ),
				'link'  => $link,
			);
		}
		return $out;
	}

	/**
	 * Notes still to show: not dismissed, newest first, safety first.
	 *
	 * @return array
	 */
	public static function current() {
		if ( ! self::is_on() ) {
			return array();
		}
		$gone  = (array) get_option( self::GONE, array() );
		$items = array_values(
			array_filter(
				(array) get_option( self::ITEMS, array() ),
				static fn( $n ) => is_array( $n ) && ! in_array( $n['id'], $gone, true )
			)
		);
		usort( $items, static fn( $a, $b ) => ( 'safety' === $b['kind'] ) <=> ( 'safety' === $a['kind'] ) ?: strcmp( $b['date'], $a['date'] ) );
		return $items;
	}

	/**
	 * Send each new safety note to the phone once.
	 *
	 * @param array $items Notes.
	 */
	private static function push_safety( array $items ) {
		$done = (array) get_option( self::PUSHED, array() );
		$gone = (array) get_option( self::GONE, array() );
		foreach ( $items as $n ) {
			if ( 'safety' !== $n['kind'] || in_array( $n['id'], $done, true ) || in_array( $n['id'], $gone, true ) ) {
				continue;
			}
			$done[] = $n['id'];
			Digest::push_all(
				array(
					'title' => $n['title'],
					'body'  => $n['text'],
					'url'   => home_url( '/app/#whatsnew' ),
					'tag'   => 'stubli-news',
				)
			);
		}
		update_option( self::PUSHED, array_slice( $done, -50 ), false );
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		Rest::register(
			'/news/mine',
			array(
				'methods'             => 'GET',
				'callback'            => static fn() => rest_ensure_response( self::state() ),
				'permission_callback' => array( Rest::class, 'is_owner' ),
			)
		);
		Rest::register(
			'/news/mine',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'change' ),
				'permission_callback' => array( Rest::class, 'is_owner' ),
				'args'                => array(
					'on'      => array( 'type' => 'boolean' ),
					'dismiss' => array( 'type' => 'string' ),
				),
			)
		);
		if ( Feedback::is_hub() ) {
			Rest::register(
				'/news',
				array(
					'methods'             => 'GET',
					'callback'            => static fn() => rest_ensure_response( self::published() ),
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	/**
	 * What the app needs.
	 *
	 * @return array
	 */
	public static function state() {
		return array(
			'on'    => self::is_on(),
			'asked' => self::asked(),
			'items' => array_slice( self::current(), 0, 1 ),
		);
	}

	/**
	 * Turn news on or off, or dismiss a note.
	 *
	 * @param WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public static function change( WP_REST_Request $r ) {
		if ( null !== $r['on'] ) {
			self::set( (bool) $r['on'] );
		}
		if ( $r['dismiss'] ) {
			$gone   = (array) get_option( self::GONE, array() );
			$gone[] = sanitize_key( (string) $r['dismiss'] );
			update_option( self::GONE, array_slice( array_values( array_unique( $gone ) ), -100 ), false );
		}
		return rest_ensure_response( self::state() );
	}

	/* ── stubli.app only ── */

	/**
	 * The private "Stubli news" list in the hub's dashboard.
	 */
	public static function register_type() {
		register_post_type(
			self::TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Stubli news', 'stubli' ),
					'singular_name' => __( 'Stubli news', 'stubli' ),
					'menu_name'     => __( 'Stubli news', 'stubli' ),
					'add_new_item'  => __( 'New note for Stubli owners', 'stubli' ),
					'edit_item'     => __( 'Note for Stubli owners', 'stubli' ),
					'not_found'     => __( 'No notes yet.', 'stubli' ),
				),
				'description'         => __( 'Short notes shown in What\'s new to owners who turned on News from Stubli. Published notes go out within a day.', 'stubli' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-megaphone',
				'menu_position'       => 27,
				'supports'            => array( 'title', 'editor' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Kind, link and versions, below the note.
	 */
	public static function meta_box() {
		add_meta_box(
			'stubli-news',
			__( 'How it goes out', 'stubli' ),
			static function ( $post ) {
				wp_nonce_field( 'stubli_news_meta', 'stubli_news_nonce' );
				$kind  = (string) get_post_meta( $post->ID, '_stubli_news_kind', true );
				$link  = (string) get_post_meta( $post->ID, '_stubli_news_link', true );
				$below = (string) get_post_meta( $post->ID, '_stubli_news_below', true );
				echo '<p>' . esc_html__( 'Title: up to 80 characters. Text: up to 280, plain text. Owners see one note at a time in What\'s new.', 'stubli' ) . '</p>';
				echo '<p><label><input type="checkbox" name="stubli_news_safety" value="1"' . checked( 'safety', $kind, false ) . '> ' . esc_html__( 'Safety update: shown first, and sent to owners\' phones if they have notifications on', 'stubli' ) . '</label></p>';
				echo '<p><label>' . esc_html__( 'Link (stubli.app only, optional)', 'stubli' ) . '<br><input type="url" class="widefat" name="stubli_news_link" value="' . esc_attr( $link ) . '" placeholder="https://stubli.app/help/…"></label></p>';
				echo '<p><label>' . esc_html__( 'Only for versions older than (optional, e.g. 0.53.2)', 'stubli' ) . '<br><input type="text" name="stubli_news_below" value="' . esc_attr( $below ) . '" pattern="[0-9.]*"></label></p>';
			},
			self::TYPE,
			'normal'
		);
	}

	/**
	 * Save the box.
	 *
	 * @param int $post_id Post.
	 */
	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['stubli_news_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['stubli_news_nonce'] ) ), 'stubli_news_meta' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_stubli_news_kind', empty( $_POST['stubli_news_safety'] ) ? 'news' : 'safety' );
		update_post_meta( $post_id, '_stubli_news_link', isset( $_POST['stubli_news_link'] ) ? esc_url_raw( wp_unslash( $_POST['stubli_news_link'] ), array( 'https' ) ) : '' );
		update_post_meta( $post_id, '_stubli_news_below', isset( $_POST['stubli_news_below'] ) ? preg_replace( '/[^0-9.]/', '', sanitize_text_field( wp_unslash( $_POST['stubli_news_below'] ) ) ) : '' );
	}

	/**
	 * Published notes from the last 90 days, newest first.
	 *
	 * @return array
	 */
	public static function published() {
		$posts = get_posts(
			array(
				'post_type'   => self::TYPE,
				'post_status' => 'publish',
				'numberposts' => 5,
				'date_query'  => array( array( 'after' => '90 days ago' ) ),
			)
		);
		return array_map(
			static fn( $p ) => array(
				'id'    => 'n' . $p->ID,
				'date'  => get_the_date( 'Y-m-d', $p ),
				'kind'  => 'safety' === get_post_meta( $p->ID, '_stubli_news_kind', true ) ? 'safety' : 'news',
				'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $p ) ), ENT_QUOTES, 'UTF-8' ),
				'text'  => mb_substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $p->post_content ) ) ) ), 0, self::MAX_TEXT ),
				'link'  => (string) get_post_meta( $p->ID, '_stubli_news_link', true ),
				'below' => (string) get_post_meta( $p->ID, '_stubli_news_below', true ),
			),
			$posts
		);
	}
}
