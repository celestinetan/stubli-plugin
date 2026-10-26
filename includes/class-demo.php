<?php
/**
 * Demo mode, for the "See a demo Stubli" in WordPress Playground: a small
 * bar on every page and app screen says it's a demo that lives only in this
 * browser, and how to make a real Stubli. Real Stublis never show it; only
 * the demo's setup switches it on (option stubli_demo).
 *
 * The demo's friends are made up and have no sites of their own, so in demo
 * mode their profiles and posts come from the option stubli_demo_people
 * (written by the demo's setup) instead of the network, and anything Ana
 * sends them is treated as delivered.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * The demo bar.
 */
class Demo {

	const GET_STARTED = 'https://stubli.app/get-started/';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_body_open', array( self::class, 'bar' ) );
		add_filter( 'pre_get_avatar_data', array( self::class, 'comment_avatar' ), 10, 2 );
	}

	/**
	 * The made-up people, by origin.
	 *
	 * @return array origin => person
	 */
	public static function people() {
		if ( ! self::on() ) {
			return array();
		}
		$p = get_option( 'stubli_demo_people', array() );
		return is_array( $p ) ? $p : array();
	}

	/**
	 * A made-up person, by any address on their (imaginary) site.
	 *
	 * @param string $url Any URL.
	 * @return array|null
	 */
	public static function person( $url ) {
		$people = self::people();
		$origin = Follows::origin( (string) $url );
		return $origin && isset( $people[ $origin ] ) ? $people[ $origin ] : null;
	}

	/**
	 * What following would read from a made-up person's site.
	 *
	 * @param string $url Any URL on their site.
	 * @return array|null Same shape as Follows::discover().
	 */
	public static function profile( $url ) {
		$p = self::person( $url );
		if ( ! $p ) {
			return null;
		}
		return array(
			'url'       => $p['url'],
			'feed'      => $p['url'] . 'feed/',
			'following' => $p['url'] . 'following/',
			'name'      => $p['name'],
			'avatar'    => $p['avatar'],
		);
	}

	/**
	 * "Fetch" a made-up person's posts into the reader.
	 *
	 * @param array $follow Follow row.
	 * @return int New items.
	 */
	public static function poll( array $follow ) {
		global $wpdb;
		$p     = self::person( $follow['site_url'] );
		$table = Install::items_table();
		$new   = 0;
		foreach ( $p ? (array) $p['posts'] : array() as $post ) {
			if ( (int) $post['at'] > time() ) {
				continue; // Not "posted" yet.
			}
			$guid = $p['url'] . '?p=' . $post['id'];
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE follow_id = %d AND guid = %s", $follow['id'], $guid ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
				continue;
			}
			$images = (array) $post['images'];
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'follow_id'    => (int) $follow['id'],
					'guid'         => $guid,
					'permalink'    => $p['url'] . $post['id'] . '/',
					'caption'      => Sync::safe_caption( (string) $post['caption'] ),
					'image_url'    => $images ? $images[0]['url'] : '',
					'images'       => wp_json_encode( $images ),
					'content_note' => (string) ( $post['note'] ?? '' ),
					'audience'     => (string) ( $post['audience'] ?? '' ),
					'published'    => gmdate( 'Y-m-d H:i:s', (int) $post['at'] ),
				)
			);
			++$new;
		}
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Install::follows_table(),
			array(
				'last_fetched'  => current_time( 'mysql', true ),
				'last_error'    => null,
				'their_follows' => wp_json_encode( $p ? array_values( (array) $p['following'] ) : array() ),
			),
			array( 'id' => (int) $follow['id'] )
		);
		return $new;
	}

	/**
	 * Discover's view of made-up people.
	 *
	 * @param string[] $origins Origins.
	 * @return array origin => profile|null
	 */
	public static function discover( array $origins ) {
		$people = self::people();
		$out    = array();
		foreach ( $origins as $o ) {
			$p = $people[ $o ] ?? null;
			if ( ! $p ) {
				$out[ $o ] = null;
				continue;
			}
			$recent = array();
			foreach ( array_slice( (array) $p['posts'], 0, 3 ) as $post ) {
				$recent[] = array(
					'url'  => $post['images'][0]['url'] ?? '',
					'alt'  => $post['images'][0]['alt'] ?? '',
					'link' => $p['url'] . $post['id'] . '/',
					'note' => (string) ( $post['note'] ?? '' ),
					'date' => gmdate( 'c', (int) $post['at'] ),
				);
			}
			$out[ $o ] = array(
				'url'     => $p['url'],
				'name'    => $p['name'],
				'avatar'  => $p['avatar'],
				'bio'     => $p['bio'],
				'recent'  => $recent,
				'prompts' => (array) ( $p['prompts'] ?? array() ),
			);
		}
		return $out;
	}

	/**
	 * Comments from made-up people show their smiley.
	 *
	 * @param array $args Avatar data.
	 * @param mixed $id   User, email, comment…
	 * @return array
	 */
	public static function comment_avatar( $args, $id ) {
		if ( $id instanceof \WP_Comment && self::on() ) {
			$p = self::person( $id->comment_author_url );
			if ( $p ) {
				$args['url'] = $p['avatar'];
			} elseif ( (int) $id->user_id > 0 ) {
				$args['url'] = Avatar::current(); // Ana's own replies show her smiley.
			}
		}
		return $args;
	}

	/**
	 * Is this site the Playground demo?
	 *
	 * @return bool
	 */
	public static function on() {
		return (bool) get_option( 'stubli_demo' );
	}

	/**
	 * The words on the bar, for the page and the app.
	 *
	 * @return array { text, link, url }
	 */
	public static function words() {
		return array(
			'text' => __( 'Demo · only in your browser', 'stubli' ),
			'link' => __( 'Make your own Stubli', 'stubli' ),
			'url'  => self::GET_STARTED,
			'friends' => __( 'Ana\'s friends are made up for this demo, so their Stublis don\'t open.', 'stubli' ),
		);
	}

	/**
	 * The bar at the top of every page of the demo.
	 */
	public static function bar() {
		if ( ! self::on() ) {
			return;
		}
		$w = self::words();
		echo '<div class="stubli-demo-bar" role="note" style="position:sticky;top:0;z-index:1000;display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:4px 12px;padding:8px 16px;background:#22201d;color:#fbfaf8;font:500 14px/1.4 Figtree,system-ui,sans-serif;text-align:center">'
			. '<span>' . esc_html( $w['text'] ) . '</span>'
			. '<a href="' . esc_url( $w['url'] ) . '" target="_blank" rel="noopener" style="color:#fbfaf8;font-weight:650;text-decoration:underline;text-underline-offset:3px">' . esc_html( $w['link'] ) . ' →</a>'
			. '</div>';
		wp_print_inline_script_tag( self::link_script() );
	}

	/**
	 * The made-up friends have no real sites: a tap on a link to one says so
	 * instead of opening an error page.
	 *
	 * @return string JavaScript.
	 */
	public static function link_script() {
		$msg = wp_json_encode( self::words()['friends'] );
		return '(function(){document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a[href]");if(!a||!/\\.example$/.test(a.hostname)){return;}e.preventDefault();var t=document.getElementById("stubli-demo-note");if(!t){t=document.createElement("div");t.id="stubli-demo-note";t.setAttribute("role","status");t.style.cssText="position:fixed;left:50%;bottom:24px;transform:translateX(-50%);max-width:340px;z-index:1001;padding:10px 16px;border-radius:12px;background:#22201d;color:#fbfaf8;font:500 14px/1.4 Figtree,system-ui,sans-serif;text-align:center";document.body.appendChild(t);}t.textContent=' . $msg . ';clearTimeout(t._h);t.hidden=false;t._h=setTimeout(function(){t.hidden=true;},4000);},true);})();';
	}
}
