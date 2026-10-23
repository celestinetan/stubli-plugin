<?php
/**
 * Little surprises on special days: the two dots of the ü in the Stubli
 * logo dress up for Samichlaus, New Year, Easter and the Stubli's own
 * birthday. Dates follow the site's time zone.
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
	 * The two dots of the ü in the logo, dressed for the day (SVG, viewBox
	 * 0 0 60 80, dots at 19,15 and 41,15). Plain dots on other days.
	 *
	 * @param string $key Surprise key.
	 * @return string
	 */
	public static function dots( $key ) {
		switch ( $key ) {
			case 'samichlaus':
				// A mandarin and a peanut.
				return '<g class="st-dots st-dots-samichlaus"><circle cx="19" cy="16" r="7" fill="#f08a24"/><path d="M19 9c.6-2.6 2.8-3.8 5-3.2-.8 2-2.6 3-5 3.2z" fill="#5d8a45"/>'
					. '<path d="M41 6.5c3.4 0 5 2.6 4.4 5.4-.3 1.5-1.4 2.3-1.4 3.6s1.1 2.1 1.4 3.6c.6 2.8-1 5.4-4.4 5.4s-5-2.6-4.4-5.4c.3-1.5 1.4-2.3 1.4-3.6s-1.1-2.1-1.4-3.6c-.6-2.8 1-5.4 4.4-5.4z" fill="#d9b483"/></g>';
			case 'newyear':
				// Two sparkles.
				$star = static function ( $x, $y, $r ) {
					$k = $r * 0.22;
					$f = static fn( $n ) => rtrim( rtrim( number_format( $n, 2, '.', '' ), '0' ), '.' );
					return '<path d="M' . $f( $x ) . ' ' . $f( $y - $r ) . 'Q' . $f( $x + $k ) . ' ' . $f( $y - $k ) . ' ' . $f( $x + $r ) . ' ' . $f( $y )
						. 'Q' . $f( $x + $k ) . ' ' . $f( $y + $k ) . ' ' . $f( $x ) . ' ' . $f( $y + $r )
						. 'Q' . $f( $x - $k ) . ' ' . $f( $y + $k ) . ' ' . $f( $x - $r ) . ' ' . $f( $y )
						. 'Q' . $f( $x - $k ) . ' ' . $f( $y - $k ) . ' ' . $f( $x ) . ' ' . $f( $y - $r ) . 'z"/>';
				};
				return '<g class="st-dots st-dots-newyear" fill="currentColor"><g>' . $star( 19, 15, 9 ) . '</g><g>' . $star( 41, 15, 9 ) . '</g></g>';
			case 'easter':
				// Two small eggs with a stripe.
				return '<g class="st-dots st-dots-easter"><g transform="rotate(-10 19 15)"><ellipse cx="19" cy="15" rx="5.6" ry="7.4" fill="currentColor"/><path d="M13.8 15.5h10.4" stroke="#f6cf45" stroke-width="2"/></g>'
					. '<g transform="rotate(10 41 15)"><ellipse cx="41" cy="15" rx="5.6" ry="7.4" fill="currentColor"/><path d="M35.8 13.5h10.4" stroke="#e8879a" stroke-width="2"/></g></g>';
			case 'birthday':
				// Two balloons on strings.
				return '<g class="st-dots st-dots-birthday"><g><path d="M19 17.5q-1.5 4 0 8" fill="none" stroke="currentColor" stroke-width="1.2"/><ellipse cx="19" cy="10" rx="6" ry="7.4" fill="currentColor"/><path d="M17.8 17.2h2.4L19 19z" fill="currentColor"/></g>'
					. '<g><path d="M41 17.5q1.5 4 0 8" fill="none" stroke="currentColor" stroke-width="1.2"/><ellipse cx="41" cy="10" rx="6" ry="7.4" fill="currentColor"/><path d="M39.8 17.2h2.4L41 19z" fill="currentColor"/></g></g>';
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
	 * @return array|null { key, dots, title }
	 */
	public static function logo() {
		$s = self::current();
		if ( ! $s ) {
			return null;
		}
		return array(
			'key'   => $s['key'],
			'dots'  => self::dots( $s['key'] ),
			'title' => self::title( $s ),
		);
	}
}
