<?php
/**
 * Little surprises on special days: the smiling ü in the Stubli logo dresses
 * up for Samichlaus, New Year, Easter and the Stubli's own birthday. Dates follow the site's time zone.
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
	const KEYS   = array( 'samichlaus', 'newyear', 'easter', 'birthday' );

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
	 * The owner can see one now with ?stubli_season=samichlaus (or newyear,
	 * easter, birthday) on their own page, even when surprises are off.
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
	 * Which surprise belongs to a date. The Stubli's birthday wins on a shared day.
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
		$m   = (int) $d->format( 'n' );
		$day = (int) $d->format( 'j' );
		if ( 12 === $m && $day <= 6 ) {
			return array( 'key' => 'samichlaus' );
		}
		if ( ( 12 === $m && 31 === $day ) || ( 1 === $m && 1 === $day ) ) {
			return array( 'key' => 'newyear' );
		}
		$easter = self::easter( (int) $d->format( 'Y' ), $d->getTimezone() );
		$diff   = (int) round( ( $d->setTime( 0, 0 )->getTimestamp() - $easter->getTimestamp() ) / DAY_IN_SECONDS );
		if ( $diff >= -2 && $diff <= 1 ) {
			// Good Friday to Easter Monday.
			return array( 'key' => 'easter' );
		}
		return null;
	}

	/**
	 * Easter Sunday (Gregorian calendar, anonymous algorithm), at midnight.
	 *
	 * @param int           $y  Year.
	 * @param \DateTimeZone $tz Time zone.
	 * @return \DateTimeImmutable
	 */
	public static function easter( $y, \DateTimeZone $tz ) {
		$a = $y % 19;
		$b = intdiv( $y, 100 );
		$c = $y % 100;
		$d = intdiv( $b, 4 );
		$e = $b % 4;
		$f = intdiv( $b + 8, 25 );
		$g = intdiv( $b - $f + 1, 3 );
		$h = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i = intdiv( $c, 4 );
		$k = $c % 4;
		$l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$month = intdiv( $h + $l - 7 * $m + 114, 31 );
		$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
		return new \DateTimeImmutable( sprintf( '%04d-%02d-%02d 00:00:00', $y, $month, $day ), $tz );
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
	 * What the ü face wears on the day: SVG drawn on top of the logo's ü
	 * (viewBox 0 0 60 80, eyes at 19,15 and 41,15; drawings reach above 0).
	 *
	 * @param string $key Surprise key.
	 * @return string
	 */
	public static function wear( $key ) {
		$cream = '#fffdf8';
		switch ( $key ) {
			case 'samichlaus':
				// A red hood with a white trim and a bobble.
				return '<g class="st-wear st-wear-samichlaus"><path d="M7 9C9-4 19-15 30-17c9 2 17 8 20 18" fill="#c9362c"/><path d="M50 3c4 1 6 4 6 8" fill="none" stroke="#c9362c" stroke-width="5" stroke-linecap="round"/>'
					. '<circle cx="56" cy="13" r="3.6" fill="' . $cream . '" stroke="currentColor" stroke-width="1.5"/><path d="M6 7.5h46" stroke="' . $cream . '" stroke-width="5" stroke-linecap="round"/></g>';
			case 'newyear':
				// A party hat, worn at an angle.
				return '<g class="st-wear st-wear-newyear"><g transform="rotate(16 34 6)"><path d="M22 6L34-24 46 6z" fill="#d9a520"/><path d="M27.3-6.5l11.4 4M30.6-14.8l7.5 2.8" stroke="' . $cream . '" stroke-width="2.2" stroke-linecap="round"/>'
					. '<path d="M21 6.5h26" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><circle cx="34" cy="-25" r="3.4" fill="#e0785a"/></g></g>';
			case 'easter':
				// Bunny ears.
				return '<g class="st-wear st-wear-easter"><g class="st-ear"><g transform="rotate(-14 21 6)"><ellipse cx="21" cy="-9" rx="5.6" ry="14" fill="currentColor"/><ellipse cx="21" cy="-8" rx="2.4" ry="9.5" fill="#f2a7b5"/></g></g>'
					. '<g transform="rotate(14 39 6)"><ellipse cx="39" cy="-9" rx="5.6" ry="14" fill="currentColor"/><ellipse cx="39" cy="-8" rx="2.4" ry="9.5" fill="#f2a7b5"/></g></g>';
			case 'birthday':
				// One candle on its head.
				return '<g class="st-wear st-wear-birthday"><rect x="26" y="-11" width="8" height="16" rx="1.5" fill="#7fb2d6"/><path d="M26-6l8-2.8M26 .5l8-2.8" stroke="' . $cream . '" stroke-width="1.6"/>'
					. '<path d="M30-12v-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><g class="st-flame"><path d="M30-27c3.4 4.4 4.6 7 4.6 9.2a4.6 4.6 0 0 1-9.2 0c0-2.2 1.2-4.8 4.6-9.2z" fill="#f4b740"/>'
					. '<path d="M30-21c1.4 1.8 1.9 3 1.9 3.9a1.9 1.9 0 0 1-3.8 0c0-.9.5-2.1 1.9-3.9z" fill="#e8652a"/></g></g>';
		}
		return '';
	}

	/**
	 * A short line for the logo's tooltip.
	 *
	 * @param array $season Today's surprise.
	 * @return string
	 */
	public static function title( array $season ) {
		switch ( $season['key'] ?? '' ) {
			case 'samichlaus':
				return __( 'Samichlaus was here', 'stubli' );
			case 'newyear':
				return __( 'Happy New Year', 'stubli' );
			case 'easter':
				return __( 'Happy Easter', 'stubli' );
			case 'birthday':
				/* translators: %s: number of years */
				return sprintf( _n( 'This Stubli is %s year old today', 'This Stubli is %s years old today', (int) ( $season['years'] ?? 1 ), 'stubli' ), number_format_i18n( (int) ( $season['years'] ?? 1 ) ) );
		}
		return '';
	}

	/**
	 * Today's logo, ready for the page and the app: null on ordinary days.
	 *
	 * @return array|null { key, wear, title }
	 */
	public static function logo() {
		$s = self::current();
		if ( ! $s ) {
			return null;
		}
		return array(
			'key'   => $s['key'],
			'wear'  => self::wear( $s['key'] ),
			'title' => self::title( $s ),
		);
	}
}
