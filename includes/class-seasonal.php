<?php
/**
 * Little surprises on special days (easter eggs): Samichlaus, New Year,
 * Easter and the Stubli's own birthday. Dates follow the site's time zone.
 *
 * On by default; the owner can switch them off in the app (Colour and reading
 * mode). Visitors see each one at most once a day (the script remembers that
 * in their browser).
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
	 * The words the script needs, in the site's language.
	 *
	 * @return array
	 */
	public static function text() {
		return array(
			'samichlaus' => __( 'Samichlaus waves hello and leaves a mandarin and some peanuts.', 'stubli' ),
			'newyear'    => __( 'Little fireworks for the New Year.', 'stubli' ),
			'egg'        => __( 'A little Easter egg. Tap to open it.', 'stubli' ),
			'chick'      => __( 'A chick says hello. Happy Easter!', 'stubli' ),
			/* translators: %s: number of years */
			'birthday'   => __( 'Balloons: this Stubli is %s today.', 'stubli' ),
		);
	}

	/**
	 * The shared script's address.
	 *
	 * @return string
	 */
	public static function script_url() {
		return STUBLI_PLUGIN_URL . 'assets/season/season.js';
	}
}
