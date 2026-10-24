<?php
/**
 * Little surprises on special days: the letters of the Stubli logo play
 * along on New Year, Valentine's Day, Halloween, Christmas, 1 August (on
 * Swiss Stublis) and the Stubli's own birthday. Dates follow the site's time
 * zone.
 *
 * On by default; the owner can switch them off in the app (Colour and
 * reading mode).
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Which surprise, if any, is on today.
 */
class Seasonal {

	const OPTION = 'stubli_seasonal';
	const KEYS   = array( 'newyear', 'valentine', 'halloween', 'christmas', 'swiss', 'birthday' );

	/**
	 * Are surprises on for this site? On unless the owner switched them off.
	 *
	 * @return bool
	 */
	public static function on() {
		return '0' !== (string) get_option( self::OPTION, '1' );
	}

	/**
	 * Switch surprises on or off.
	 *
	 * @param mixed $on New value.
	 */
	public static function set( $on ) {
		update_option( self::OPTION, rest_sanitize_boolean( $on ) ? '1' : '0' );
	}

	/**
	 * Today's surprise for visitors, or null.
	 *
	 * @return array|null { key, years? }
	 */
	public static function current() {
		$preview = self::preview();
		if ( $preview ) {
			return $preview;
		}
		if ( ! self::on() ) {
			return null;
		}
		return self::for_date( current_datetime() );
	}

	/**
	 * The owner can see one now with ?stubli_season=halloween (or newyear,
	 * valentine, christmas, swiss, birthday) on their own page or in the app,
	 * even when surprises are off.
	 *
	 * @return array|null
	 */
	private static function preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview for the owner.
		$k = isset( $_GET['stubli_season'] ) ? sanitize_key( wp_unslash( $_GET['stubli_season'] ) ) : '';
		if ( '' === $k || ! in_array( $k, self::KEYS, true ) || ! current_user_can( 'manage_options' ) ) {
			return null;
		}
		$out = array(
			'key'     => $k,
			'preview' => true,
		);
		if ( 'birthday' === $k ) {
			$out['years'] = max( 1, self::years_on( current_datetime() ) );
		}
		return $out;
	}

	/**
	 * Which surprise belongs to a date: New Year (31 Dec and 1 Jan), Valentine's
	 * Day, Halloween, Christmas (24 to 26 Dec), 1 August on Swiss Stublis, and
	 * the Stubli's birthday, which wins on a shared day.
	 *
	 * @param \DateTimeImmutable $d Date in the site's time zone.
	 * @return array|null
	 */
	public static function for_date( \DateTimeImmutable $d ) {
		$years = self::birthday_years( $d );
		if ( $years > 0 ) {
			return array(
				'key'   => 'birthday',
				'years' => $years,
			);
		}
		$md = $d->format( 'm-d' );
		if ( '12-31' === $md || '01-01' === $md ) {
			return array( 'key' => 'newyear' );
		}
		if ( '02-14' === $md ) {
			return array( 'key' => 'valentine' );
		}
		if ( '10-31' === $md ) {
			return array( 'key' => 'halloween' );
		}
		if ( in_array( $md, array( '12-24', '12-25', '12-26' ), true ) ) {
			return array( 'key' => 'christmas' );
		}
		if ( '08-01' === $md && self::is_swiss() ) {
			return array( 'key' => 'swiss' );
		}
		return null;
	}

	/**
	 * A Swiss Stubli: the site's language is a Swiss one (de_CH, fr_CH, it_CH
	 * and so on) or its clock is set to Zurich.
	 *
	 * @return bool
	 */
	public static function is_swiss() {
		return '_CH' === substr( (string) get_locale(), -3 ) || 0 === strpos( get_locale(), 'rm' ) || 'Europe/Zurich' === wp_timezone_string();
	}

	/**
	 * The date of the first published post, in the site's time zone, or null.
	 *
	 * @return \DateTimeImmutable|null
	 */
	public static function first_post() {
		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( ! $ids ) {
			return null;
		}
		$t = get_post_datetime( (int) $ids[0] );
		return $t ? $t->setTimezone( wp_timezone() ) : null;
	}

	/**
	 * How many years old the Stubli is today, if today is its birthday; else 0.
	 * A first post on 29 February is celebrated on 1 March in other years.
	 *
	 * @param \DateTimeImmutable $d Today.
	 * @return int
	 */
	public static function birthday_years( \DateTimeImmutable $d ) {
		$first = self::first_post();
		if ( ! $first ) {
			return 0;
		}
		$years = (int) $d->format( 'Y' ) - (int) $first->format( 'Y' );
		if ( $years < 1 ) {
			return 0;
		}
		$md = $first->format( 'm-d' );
		if ( '02-29' === $md && ! $d->format( 'L' ) ) {
			$md = '03-01';
		}
		return $d->format( 'm-d' ) === $md ? $years : 0;
	}

	/**
	 * Full years since the first post (for previews), at least 0.
	 *
	 * @param \DateTimeImmutable $d Today.
	 * @return int
	 */
	private static function years_on( \DateTimeImmutable $d ) {
		$first = self::first_post();
		return $first ? max( 0, (int) $first->diff( $d )->y ) : 0;
	}

	/**
	 * The wordmark "stübli" for the day, letter by letter: the word dances at
	 * New Year, the smiley gets heart eyes on Valentine's Day, a pumpkin sits
	 * behind it at Halloween, fairy lights drape over it at Christmas, a Swiss
	 * flag replaces the i's dot on 1 August, and the i is a candle in a cupcake
	 * on the Stubli's birthday.
	 * Sizes are in em, so it fits any logo size.
	 *
	 * @param string $key Surprise key.
	 * @return string Inner HTML for the wordmark, or '' on ordinary days.
	 */
	public static function letters( $key ) {
		$u     = '<path d="M10.5 35.5V52a19.5 19.5 0 0 0 39 0V35.5" fill="none" stroke="currentColor" stroke-width="11" stroke-linecap="round"/><circle cx="19" cy="15" r="6.2" fill="currentColor"/><circle cx="41" cy="15" r="6.2" fill="currentColor"/>';
		$flame = '<g class="stw-flame"><path d="M50 4c22 28 30 44 30 58a30 30 0 0 1-60 0c0-14 8-30 30-58z" fill="#f4b740"/><path d="M50 44c8 10 11 16 11 21a11 11 0 0 1-22 0c0-5 3-11 11-21z" fill="#e8652a"/></g>';
		$ov    = static function ( $l, $t, $w, $h, $svg, $cls = '', $vb = '0 0 100 100' ) {
			return '<svg class="stw-ov' . ( $cls ? ' ' . $cls : '' ) . '" viewBox="' . $vb . '" preserveAspectRatio="none" aria-hidden="true" focusable="false" style="left:' . $l . 'em;top:' . $t . 'em;width:' . $w . 'em;height:' . $h . 'em">' . $svg . '</svg>';
		};
		$add   = array_fill( 0, 6, '' );
		$i     = 'i';
		$tilt  = array();
		$hide  = -1;
		switch ( $key ) {
			case 'newyear':
				// The word dances out of line while confetti keeps falling.
				$tilt   = array( array( -6, .02 ), array( 5, -.04 ), array( -3, 0 ), array( 7, -.03 ), array( -5, .03 ), array( 4, -.02 ) );
				$add[2] = $ov( -.25, -.4, 1.7, 1.25, '<circle class="stw-bit" cx="10" cy="6" r="3.6" fill="#e0785a" style="animation-delay:0.00s"/><circle class="stw-bit" cx="26" cy="6" r="3.2" fill="#d9a520" style="animation-delay:0.23s"/><circle class="stw-bit" cx="42" cy="6" r="4" fill="#7fb2d6" style="animation-delay:0.46s"/><circle class="stw-bit" cx="58" cy="6" r="3.2" fill="#e8879a" style="animation-delay:0.69s"/><circle class="stw-bit" cx="74" cy="6" r="3.6" fill="#6fae73" style="animation-delay:0.92s"/><circle class="stw-bit" cx="90" cy="6" r="3.6" fill="#e0785a" style="animation-delay:1.15s"/><circle class="stw-bit" cx="106" cy="6" r="3.2" fill="#d9a520" style="animation-delay:1.38s"/><circle class="stw-bit" cx="122" cy="6" r="4" fill="#7fb2d6" style="animation-delay:0.01s"/><circle class="stw-bit" cx="138" cy="6" r="3.2" fill="#e8879a" style="animation-delay:0.24s"/><circle class="stw-bit" cx="154" cy="6" r="3.6" fill="#6fae73" style="animation-delay:0.47s"/><circle class="stw-bit" cx="18" cy="6" r="3.6" fill="#e0785a" style="animation-delay:0.70s"/><circle class="stw-bit" cx="50" cy="6" r="3.2" fill="#d9a520" style="animation-delay:0.93s"/><circle class="stw-bit" cx="82" cy="6" r="4" fill="#7fb2d6" style="animation-delay:1.16s"/><circle class="stw-bit" cx="114" cy="6" r="3.2" fill="#e8879a" style="animation-delay:1.39s"/><circle class="stw-bit" cx="146" cy="6" r="3.6" fill="#6fae73" style="animation-delay:0.02s"/>', 'stw-confetti', '0 0 170 125' );
				break;
			case 'valentine':
				// The smiley falls in love: its eyes turn into hearts, then back.
				$u = '<path d="M10.5 35.5V52a19.5 19.5 0 0 0 39 0V35.5" fill="none" stroke="currentColor" stroke-width="11" stroke-linecap="round"/><g class="stw-eyes"><circle cx="19" cy="15" r="6.2" fill="currentColor"/><circle cx="41" cy="15" r="6.2" fill="currentColor"/></g><g class="stw-hearts"><g transform="translate(9.5 5.9) scale(.19)"><path d="M50 90C20 68 4 52 4 32 4 16 16 6 29 6c9 0 16 5 21 12 5-7 12-12 21-12 13 0 25 10 25 26 0 20-16 36-46 58z" fill="#d94f5c"/></g><g transform="translate(31.5 5.9) scale(.19)"><path d="M50 90C20 68 4 52 4 32 4 16 16 6 29 6c9 0 16 5 21 12 5-7 12-12 21-12 13 0 25 10 25 26 0 20-16 36-46 58z" fill="#d94f5c"/></g></g>';
				break;
			case 'halloween':
				// The smiley dresses up as a pumpkin: a big round pumpkin behind the usual ü face.
				$add[2] = $ov( -.24, -.06, 1.05, .95, '<g class="stw-pumpkin"><path d="M52 30c0-9 3-16 9-21l6 4c-5 4-7 10-7 17z" fill="#6f7d3c"/><path d="M63 12c6-3 12-1 14 4s-2 9-6 7" fill="none" stroke="#6f7d3c" stroke-width="2.4" stroke-linecap="round"/><ellipse cx="17" cy="64" rx="17" ry="30" fill="#d4691b"/><ellipse cx="83" cy="64" rx="17" ry="30" fill="#d4691b"/><ellipse cx="33" cy="62" rx="20" ry="35" fill="#e8822a"/><ellipse cx="67" cy="62" rx="20" ry="35" fill="#e8822a"/><ellipse cx="50" cy="61" rx="18" ry="36" fill="#f29a3f"/><path d="M34 30q-6 32 0 64M66 30q6 32 0 64" fill="none" stroke="#d4691b" stroke-width="2" opacity=".7"/></g>', 'stw-behind' );
				break;
			case 'christmas':
				// A string of Christmas lights rests on the tops of the letters and sags between them,
				// and both ends run down to the baseline and trail along it; the bulbs glow and twinkle.
				$add[0] = $ov( -0.36, 0, 3.12, .96, '<path d="M2.0 84.5 C12.0 85.5 24.0 84.5 26.0 66.0 S34.0 34.0 60.0 33.5 Q78.0 42.3 96.0 19.5 Q117.0 39.5 138.0 22.5 Q148.0 31.3 158.0 22.5 Q172.0 30.8 186.0 14.5 Q215.0 40.0 244.0 14.5 Q255.0 22.7 266.0 11.5 C278.0 12.0 283.0 40.0 285.0 66.0 S288.0 84.5 298.0 84.5 L310.0 84.0" fill="none" stroke="#2f3d2c" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><g class="stw-bulb" style="animation-delay:0.00s"><g><circle cx="79.0" cy="42.0" r="11" fill="#e5483b" opacity=".3"/><rect x="76.9" y="33.5" width="4.2" height="3.8" rx="1" fill="#3b4a38"/><ellipse cx="79.0" cy="42.0" rx="4.2" ry="6" fill="#e5483b"/><ellipse cx="77.6" cy="39.8" rx="1.2" ry="2" fill="#fff" opacity=".65"/></g></g><g class="stw-bulb" style="animation-delay:0.30s"><g><circle cx="117.0" cy="38.2" r="11" fill="#f2b632" opacity=".3"/><rect x="114.9" y="29.7" width="4.2" height="3.8" rx="1" fill="#3b4a38"/><ellipse cx="117.0" cy="38.2" rx="4.2" ry="6" fill="#f2b632"/><ellipse cx="115.6" cy="36.0" rx="1.2" ry="2" fill="#fff" opacity=".65"/></g></g><g class="stw-bulb" style="animation-delay:0.60s"><g><circle cx="148.0" cy="34.9" r="11" fill="#4caf6e" opacity=".3"/><rect x="145.9" y="26.4" width="4.2" height="3.8" rx="1" fill="#3b4a38"/><ellipse cx="148.0" cy="34.9" rx="4.2" ry="6" fill="#4caf6e"/><ellipse cx="146.6" cy="32.7" rx="1.2" ry="2" fill="#fff" opacity=".65"/></g></g><g class="stw-bulb" style="animation-delay:0.90s"><g><circle cx="173.0" cy="32.3" r="11" fill="#5aa7e0" opacity=".3"/><rect x="170.9" y="23.8" width="4.2" height="3.8" rx="1" fill="#3b4a38"/><ellipse cx="173.0" cy="32.3" rx="4.2" ry="6" fill="#5aa7e0"/><ellipse cx="171.6" cy="30.1" rx="1.2" ry="2" fill="#fff" opacity=".65"/></g></g><g class="stw-bulb" style="animation-delay:1.20s"><g><circle cx="233.0" cy="30.4" r="11" fill="#ef7fb0" opacity=".3"/><rect x="230.9" y="21.9" width="4.2" height="3.8" rx="1" fill="#3b4a38"/><ellipse cx="233.0" cy="30.4" rx="4.2" ry="6" fill="#ef7fb0"/><ellipse cx="231.6" cy="28.2" rx="1.2" ry="2" fill="#fff" opacity=".65"/></g></g><g class="stw-bulb" style="animation-delay:0.10s"><g><circle cx="256.0" cy="25.7" r="11" fill="#f2b632" opacity=".3"/><rect x="253.9" y="17.2" width="4.2" height="3.8" rx="1" fill="#3b4a38"/><ellipse cx="256.0" cy="25.7" rx="4.2" ry="6" fill="#f2b632"/><ellipse cx="254.6" cy="23.5" rx="1.2" ry="2" fill="#fff" opacity=".65"/></g></g><g class="stw-bulb" style="animation-delay:1.10s"><g><circle cx="24.5" cy="68.0" r="11" fill="#e5483b" opacity=".3"/><rect x="22.4" y="59.5" width="4.2" height="3.8" rx="1" fill="#3b4a38"/><ellipse cx="24.5" cy="68.0" rx="4.2" ry="6" fill="#e5483b"/><ellipse cx="23.1" cy="65.8" rx="1.2" ry="2" fill="#fff" opacity=".65"/></g></g><g class="stw-bulb" style="animation-delay:0.50s"><g><circle cx="283.5" cy="58.0" r="11" fill="#4caf6e" opacity=".3"/><rect x="281.4" y="49.5" width="4.2" height="3.8" rx="1" fill="#3b4a38"/><ellipse cx="283.5" cy="58.0" rx="4.2" ry="6" fill="#4caf6e"/><ellipse cx="282.1" cy="55.8" rx="1.2" ry="2" fill="#fff" opacity=".65"/></g></g><g class="stw-bulb" style="animation-delay:0.80s"><g transform="rotate(90 9.0 84.0)"><circle cx="9.0" cy="92.0" r="11" fill="#5aa7e0" opacity=".3"/><rect x="6.9" y="83.5" width="4.2" height="3.8" rx="1" fill="#3b4a38"/><ellipse cx="9.0" cy="92.0" rx="4.2" ry="6" fill="#5aa7e0"/><ellipse cx="7.6" cy="89.8" rx="1.2" ry="2" fill="#fff" opacity=".65"/></g></g><g class="stw-bulb" style="animation-delay:0.20s"><g transform="rotate(-90 304.0 84.0)"><circle cx="304.0" cy="92.0" r="11" fill="#ef7fb0" opacity=".3"/><rect x="301.9" y="83.5" width="4.2" height="3.8" rx="1" fill="#3b4a38"/><ellipse cx="304.0" cy="92.0" rx="4.2" ry="6" fill="#ef7fb0"/><ellipse cx="302.6" cy="89.8" rx="1.2" ry="2" fill="#fff" opacity=".65"/></g></g>', 'stw-lights stw-front', '0 0 312 96' );
				break;
			case 'swiss':
				// A Swiss flag takes the place of the dot on the i and waves in the wind: square, cross arms a sixth
				// longer than wide (the official proportions), red #DA291C.
				$i      = 'ı';
				$add[5] = $ov( -.035, -.04, .33, .33, '<rect x="0" y="4" width="92" height="92" fill="#da291c"/><path d="M37.4 21.2h17.2v20.1h20.1v17.2h-20.1v20.1h-17.2v-20.1h-20.1v-17.2h20.1z" fill="#fff"/>', 'stw-flag' );
				break;
			case 'birthday':
				// The i becomes a green candle with a flickering flame, standing in a proper cupcake.
				$hide   = 5;
				$add[5] = $ov( .045, .02, .16, .5, '<rect x="11" y="34" width="10" height="66" rx="2" fill="#5f9a5c"/><path d="M11 44l10-6M11 58l10-6M11 72l10-6M11 86l10-6" stroke="#fffdf8" stroke-width="2.6"/><path d="M16 34v-5" stroke="#3a3a36" stroke-width="1.6" stroke-linecap="round"/><g class="stw-flame"><path d="M16 4c6 7 8 11 8 15a8 8 0 0 1-16 0c0-4 2-8 8-15z" fill="#f4b740"/><path d="M16 15c2 2.5 3 4 3 5.5a3 3 0 0 1-6 0c0-1.5 1-3 3-5.5z" fill="#e8652a"/></g>', 'stw-candle', '0 0 32 100' ) . $ov( -.11, .39, .47, .423, '<path d="M14 50h72l-10 38H24z" fill="#8ec5e8"/><path d="M25 50l5 38M36 50l2.5 38M47 50v38M58 50l-1.5 38M69 50l-4 38" stroke="#6aa9d3" stroke-width="2.6"/><path d="M8 54c-4-9 3-16 10-15 0-9 9-14 17-11 4-7 16-9 22-3 7-4 17 0 18 8 8-1 15 6 11 14-2 5-6 7-10 7H18c-5 0-8-0-10-0z" fill="#f6b3c4"/><path d="M20 45c8 4 18 4 28 1s20-3 30 1" fill="none" stroke="#fbd3de" stroke-width="3" stroke-linecap="round"/><path d="M28 34c6 3 14 3 22 0s14-2 19 1" fill="none" stroke="#fbd3de" stroke-width="2.6" stroke-linecap="round"/><rect x="22" y="38" width="5" height="2.2" rx="1" fill="#7fb2d6" transform="rotate(-30 24 39)"/><rect x="40" y="30" width="5" height="2.2" rx="1" fill="#f6cf45" transform="rotate(20 42 31)"/><rect x="60" y="40" width="5" height="2.2" rx="1" fill="#6fae73" transform="rotate(-15 62 41)"/><rect x="72" y="47" width="5" height="2.2" rx="1" fill="#fff" transform="rotate(35 74 48)"/><rect x="33" y="47" width="5" height="2.2" rx="1" fill="#e0785a" transform="rotate(10 35 48)"/>', 'stw-cupcake', '0 0 100 90' );
				break;
			default:
				return '';
		}
		$chars = array( 's', 't', '', 'b', 'l', $i );
		$out   = '';
		foreach ( $chars as $n => $c ) {
			$style = isset( $tilt[ $n ] ) ? ' style="--stw-r:' . $tilt[ $n ][0] . 'deg;--stw-y:' . $tilt[ $n ][1] . 'em"' : '';
			$inner = 2 === $n ? '<svg class="st-wordmark-u stw-u" viewBox="0 0 60 80" aria-hidden="true" focusable="false">' . $u . '</svg>' : esc_html( $c );
			$cls   = $hide === $n ? 'stw-l stw-hide' : 'stw-l';
			$out  .= '<span class="' . $cls . '"' . $style . ' aria-hidden="true">' . $inner . $add[ $n ] . '</span>';
		}
		return $out;
	}

	/**
	 * A short line for the logo's tooltip.
	 *
	 * @param array $season Today's surprise.
	 * @return string
	 */
	public static function title( array $season ) {
		switch ( $season['key'] ?? '' ) {
			case 'newyear':
				return __( 'Happy New Year', 'stubli' );
			case 'valentine':
				return __( 'Happy Valentine\'s Day', 'stubli' );
			case 'halloween':
				return __( 'Boo! Happy Halloween', 'stubli' );
			case 'christmas':
				return __( 'Merry Christmas', 'stubli' );
			case 'swiss':
				return __( 'Happy Swiss National Day', 'stubli' );
			case 'birthday':
				/* translators: %s: number of years */
				return sprintf( _n( 'This Stubli is %s year old today', 'This Stubli is %s years old today', (int) ( $season['years'] ?? 1 ), 'stubli' ), number_format_i18n( (int) ( $season['years'] ?? 1 ) ) );
		}
		return '';
	}

	/**
	 * Today's logo, ready for the page and the app: null on ordinary days.
	 *
	 * @return array|null { key, html, title }
	 */
	public static function logo() {
		$s = self::current();
		if ( ! $s ) {
			return null;
		}
		return array(
			'key'   => $s['key'],
			'html'  => self::letters( $s['key'] ),
			'title' => self::title( $s ),
		);
	}
}
