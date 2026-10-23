<?php
/**
 * Small helpers shared by the views.
 *
 * @package Stubli
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is the Stubli plugin active?
 *
 * @return bool
 */
function stubli_has_plugin() {
	return class_exists( '\Stubli\Posts' );
}

/**
 * @font-face rules for the self-hosted fonts that are present in
 * assets/fonts. Missing files fall back to system fonts, never to a CDN.
 *
 * @return string CSS.
 */
function stubli_font_faces() {
	$dir   = stubli_look_dir() . '/assets/fonts/';
	$uri   = stubli_look_uri() . '/assets/fonts/';
	$faces = array(
		array( 'Figtree', 'Figtree-Variable.woff2', '300 900', 'normal' ),
		array( 'Figtree', 'Figtree-Italic-Variable.woff2', '300 900', 'italic' ),
		array( 'Literata', 'Literata-Variable.woff2', '200 900', 'normal' ),
		array( 'Literata', 'Literata-Italic-Variable.woff2', '200 900', 'italic' ),
	);
	$css   = '';
	foreach ( $faces as $f ) {
		if ( file_exists( $dir . $f[1] ) ) {
			$css .= sprintf( "@font-face{font-family:'%s';src:url('%s') format('woff2');font-weight:%s;font-style:%s;font-display:swap}\n", $f[0], esc_url( $uri . $f[1] ), $f[2], $f[3] );
		}
	}
	return $css;
}

/**
 * The owner's profile, from the plugin when present.
 *
 * @return array
 */
function stubli_profile() {
	if ( stubli_has_plugin() ) {
		$p = \Stubli\Posts::profile();
	} else {
		$p = array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'pronouns'    => '',
			'url'         => home_url( '/' ),
			'avatar'      => get_site_icon_url( 256 ),
			'guestbook'   => '',
			'on_break'    => false,
		);
	}
	$p['host'] = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$p['bio']  = (string) get_option( 'stubli_bio', '' );
	if ( '' === $p['bio'] ) {
		$p['bio'] = (string) $p['description'];
	}
	return $p;
}

/**
 * Is the visitor the site owner?
 *
 * @return bool
 */
function stubli_is_owner() {
	return current_user_can( 'manage_options' );
}

/**
 * Tax query that keeps journal entries (status format) out.
 *
 * @return array
 */
function stubli_has_photos_query() {
	return array(
		'relation' => 'OR',
		array(
			'key'     => '_stubli_photos',
			'compare' => 'EXISTS',
		),
		array(
			'key'     => '_thumbnail_id',
			'compare' => 'EXISTS',
		),
	);
}

/**
 * Tax query that keeps journal entries (status format) out.
 *
 * @return array
 */
function stubli_photo_tax_query() {
	return array(
		array(
			'taxonomy' => 'post_format',
			'field'    => 'slug',
			'terms'    => array( 'post-format-status' ),
			'operator' => 'NOT IN',
		),
	);
}

/**
 * The collections: categories that hold at least one photo post, biggest
 * first. The default category is called "Photos".
 *
 * @return WP_Term[]
 */
function stubli_collections() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$out = array();
	foreach ( get_categories( array( 'hide_empty' => true ) ) as $term ) {
		$q = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'cat'            => $term->term_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'tax_query'      => stubli_photo_tax_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
		if ( $q->found_posts > 0 ) {
			$term->stubli_count = (int) $q->found_posts;
			$out[]              = $term;
		}
	}
	// The owner's order (Arrange in the app) first, the rest biggest first.
	$order = array_map( 'intval', (array) get_option( 'stubli_coll_order', array() ) );
	usort(
		$out,
		static function ( $a, $b ) use ( $order ) {
			$ia = array_search( (int) $a->term_id, $order, true );
			$ib = array_search( (int) $b->term_id, $order, true );
			if ( false !== $ia && false !== $ib ) {
				return $ia - $ib;
			}
			if ( false !== $ia || false !== $ib ) {
				return false !== $ia ? -1 : 1;
			}
			return $b->stubli_count - $a->stubli_count;
		}
	);
	$cache = $out;
	return $out;
}

/**
 * A collection's display name.
 *
 * @param WP_Term $term Category.
 * @return string
 */
function stubli_collection_name( $term ) {
	if ( (int) $term->term_id === (int) get_option( 'default_category' ) && in_array( strtolower( $term->slug ), array( 'uncategorized', 'uncategorised' ), true ) ) {
		return __( 'Photos', 'stubli' );
	}
	return $term->name;
}

/**
 * Photo posts in a collection (or all), newest first.
 *
 * @param int $cat   Category ID, 0 for all.
 * @param int $paged Page.
 * @return WP_Query
 */
function stubli_photos( $cat = 0, $paged = 1 ) {
	$args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 24,
		'paged'          => max( 1, (int) $paged ),
		'tax_query'      => stubli_photo_tax_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		'meta_query'     => stubli_has_photos_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	);
	if ( $cat ) {
		$args['cat'] = (int) $cat;
	}
	$pins = $cat ? stubli_pins( (int) $cat ) : array();
	if ( ! $pins ) {
		return new WP_Query( $args );
	}
	// Pinned posts lead the collection's first page; the rest follow newest first.
	$n                    = count( $pins );
	$args['post__not_in'] = $pins;
	$page                 = max( 1, (int) $paged );
	$args['offset']       = 1 === $page ? 0 : ( $page - 1 ) * 24 - $n;
	if ( 1 === $page ) {
		$args['posts_per_page'] = 24 - $n;
	}
	$q = new WP_Query( $args );
	if ( 1 === $page ) {
		$top = get_posts(
			array(
				'post__in'       => $pins,
				'orderby'        => 'post__in',
				'post_status'    => 'publish',
				'posts_per_page' => $n,
			)
		);
		foreach ( $top as $t ) {
			$t->stubli_pinned = true;
		}
		$q->posts      = array_merge( $top, $q->posts );
		$q->post_count = count( $q->posts );
	}
	$q->found_posts  += $n;
	$q->max_num_pages = (int) ceil( $q->found_posts / 24 );
	$q->set( 'paged', $page );
	return $q;
}

/**
 * Posts pinned to the top of a collection (Arrange in the app), still there.
 *
 * @param int $cat Category ID.
 * @return int[]
 */
function stubli_pins( $cat ) {
	$all  = (array) get_option( 'stubli_pins', array() );
	$keep = array();
	foreach ( (array) ( $all[ $cat ] ?? array() ) as $id ) {
		$p = get_post( (int) $id );
		if ( $p && 'publish' === $p->post_status && has_category( (int) $cat, $p ) ) {
			$keep[] = (int) $id;
		}
	}
	return array_slice( $keep, 0, 3 );
}

/**
 * The newest photo post.
 *
 * @return WP_Post|null
 */
function stubli_latest_photo() {
	$l = stubli_latest_photos( 1 );
	return $l ? $l[0] : null;
}

/**
 * The newest few photo posts, for the Latest card.
 *
 * @param int $n How many.
 * @return WP_Post[]
 */
function stubli_latest_photos( $n = 3 ) {
	$q = stubli_photos( 0, 1 );
	return array_slice( $q->posts, 0, (int) $n );
}

/**
 * Photo attachment IDs of a post, in order.
 *
 * @param int $post_id Post ID.
 * @return int[]
 */
function stubli_photo_ids( $post_id ) {
	if ( stubli_has_plugin() ) {
		return \Stubli\Posts::photo_ids( $post_id );
	}
	$id = get_post_thumbnail_id( $post_id );
	return $id ? array( (int) $id ) : array();
}

/**
 * The post's collection.
 *
 * @param int $post_id Post ID.
 * @return WP_Term|null
 */
function stubli_post_collection( $post_id ) {
	$cats = get_the_category( $post_id );
	return $cats ? $cats[0] : null;
}

/**
 * "Dec 2024" on tiles.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function stubli_short_date( $post ) {
	return wp_date( 'M Y', get_post_timestamp( $post ) );
}

/**
 * "today", "3 days ago" for two weeks, then the month (design §7.1: a quiet
 * spell never reads as "inactive for 94 days").
 *
 * @param WP_Post $post Post.
 * @return string
 */
function stubli_gentle_date( $post ) {
	$ts   = get_post_timestamp( $post );
	$days = (int) floor( ( time() - $ts ) / DAY_IN_SECONDS );
	if ( $days < 1 ) {
		return __( 'today', 'stubli' );
	}
	if ( 1 === $days ) {
		return __( 'yesterday', 'stubli' );
	}
	if ( $days < 14 ) {
		/* translators: %d: number of days */
		return sprintf( __( '%d days ago', 'stubli' ), $days );
	}
	return wp_date( 'M Y', $ts );
}

/**
 * A short label for a photo post.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function stubli_post_label( $post ) {
	$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );
	if ( '' !== $title ) {
		return $title;
	}
	$ids = stubli_photo_ids( $post->ID );
	$alt = $ids ? (string) get_post_meta( $ids[0], '_wp_attachment_image_alt', true ) : '';
	return '' !== $alt ? $alt : __( 'A photo', 'stubli' );
}

/**
 * Link to the "from your own Stubli" step.
 *
 * @param string $do  follow | comment | sign.
 * @param string $url The page it's about.
 * @return string
 */
function stubli_remote_url( $do, $url ) {
	return add_query_arg(
		array(
			'do'  => $do,
			'url' => rawurlencode( $url ),
		),
		home_url( '/from-your-stubli/' )
	);
}

/**
 * Inline SVG icons on a 24px grid, 1.7–1.8 stroke, round caps (design §18).
 *
 * @param string $name Icon name.
 * @param int    $size Pixel size.
 * @return string
 */
function stubli_icon( $name, $size = 18 ) {
	$paths = array(
		'book'    => '<path d="M12 6.5C10.3 5.2 7.9 4.5 4 4.5v14c3.9 0 6.3.7 8 2 1.7-1.3 4.1-2 8-2v-14c-3.9 0-6.3.7-8 2z"/><path d="M12 6.5v14"/>',
		'pen'     => '<path d="M15.5 4.5l4 4L9 19l-5 1 1-5z"/><path d="M13 7l4 4"/>',
		'mark'    => '<path d="M6.5 3.5h11v17L12 16.5l-5.5 4z"/>',
		'back'    => '<path d="M15 5l-7 7 7 7"/>',
		'chev'    => '<path d="M9 6l6 6-6 6"/>',
		'stack'   => '<rect x="7" y="7" width="13" height="13" rx="3"/><path d="M4 16V6a2 2 0 0 1 2-2h10"/>',
		'link'    => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
		'search'  => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/>',
		'pin'     => '<path d="M12 17v5"/><path d="M8 3h8l-1 6 3 4H6l3-4z"/>',
		'play'    => '<path d="M8 5v14l11-7z" fill="currentColor"/>',
		'eye'     => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf( '<svg class="st-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>', (int) $size, $paths[ $name ] );
}

/**
 * The video behind a photo (a video's poster), or 0.
 *
 * @param int $photo_id Attachment ID.
 * @return int
 */
function stubli_video_for( $photo_id ) {
	return ( stubli_has_plugin() && method_exists( '\Stubli\Posts', 'video_for' ) ) ? \Stubli\Posts::video_for( $photo_id ) : 0;
}

/**
 * The smiling ü glyph (u = smile, umlaut = eyes).
 *
 * @param bool $wink Wink the right eye once.
 * @return string SVG inner markup.
 */
function stubli_glyph( $wink = false ) {
	return '<path d="M10.5 35.5V52a19.5 19.5 0 0 0 39 0V35.5" fill="none" stroke="currentColor" stroke-width="11" stroke-linecap="round"/><circle cx="19" cy="15" r="6.2" fill="currentColor"/><circle' . ( $wink ? ' class="st-wink"' : '' ) . ' cx="41" cy="15" r="6.2" fill="currentColor"/>';
}

/**
 * The wordmark "stübli", one colour.
 *
 * @param bool $wink Wink once.
 * @return string
 */
function stubli_wordmark( $wink = false ) {
	return '<span class="st-wordmark" role="img" aria-label="Stubli"><span aria-hidden="true">st</span><svg class="st-wordmark-u" viewBox="0 0 60 80" aria-hidden="true" focusable="false">' . stubli_glyph( $wink ) . '</svg><span aria-hidden="true">bli</span></span>';
}

/**
 * The app icon: the smiling ü cut out of a rounded square.
 *
 * @param int  $size Pixels.
 * @param bool $wink Wink once.
 * @return string
 */
function stubli_app_icon( $size = 40, $wink = false ) {
	static $n = 0;
	++$n;
	$id = 'st-icon-mask-' . $n;
	return sprintf(
		'<svg class="st-app-icon" width="%1$d" height="%1$d" viewBox="0 0 100 100" role="img" aria-label="Stubli"><defs><mask id="%2$s"><rect width="100" height="100" fill="#fff"/><g transform="translate(24.2 12.25) scale(0.86)" style="color:#000">%3$s</g></mask></defs><rect width="100" height="100" rx="24" fill="currentColor" mask="url(#%2$s)"/></svg>',
		(int) $size,
		esc_attr( $id ),
		stubli_glyph( $wink )
	);
}

/**
 * An escaped image address that also allows the local stand-in avatar
 * (an inline SVG data URI), which esc_url would drop.
 *
 * @param string $src Image URL.
 * @return string
 */
function stubli_img_src( $src ) {
	$src = (string) $src;
	if ( '' === $src || false !== strpos( $src, 'gravatar.com' ) ) {
		$src = 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" fill="#e6e1d8"/><circle cx="20" cy="16" r="7" fill="#c9c1b4"/><path d="M6 38c2-8 8-12 14-12s12 4 14 12z" fill="#c9c1b4"/></svg>' );
	}
	return $src;
}


/**
 * A friendly empty state in Stubli's voice: the smiling ü (with a wink), one
 * line that changes each day, and for the owner a way to fill it.
 *
 * @param string $where journal | photos.
 * @return string
 */
function stubli_empty( $where ) {
	$name  = (string) strtok( (string) get_bloginfo( 'name' ), ' ' );
	$name  = '' !== $name ? $name : __( 'They', 'stubli' );
	$owner = function_exists( 'stubli_is_owner' ) && stubli_is_owner();
	if ( 'journal' === $where ) {
		$lines = $owner ? array(
			__( 'Your journal is a blank page. One line about today is a fine start.', 'stubli' ),
			__( 'Nothing written yet. The best entries begin with an ordinary day.', 'stubli' ),
			__( 'Your first entry can be short. Future you will be glad you wrote it.', 'stubli' ),
		) : array(
			/* translators: %s: first name */
			sprintf( __( '%s\'s journal is still a blank page. Good stories take their time.', 'stubli' ), $name ),
			/* translators: %s: first name */
			sprintf( __( 'Nothing written yet. %s is probably out living the first entry.', 'stubli' ), $name ),
			/* translators: %s: first name */
			sprintf( __( 'The ink on %s\'s first entry is still drying. Come back soon.', 'stubli' ), $name ),
		);
		$cta = $owner ? array( home_url( '/app/#journal' ), __( 'Write your first entry', 'stubli' ) ) : null;
	} else {
		$lines = $owner ? array(
			__( 'Nothing in here yet. A photo from today would look lovely.', 'stubli' ),
			__( 'An empty shelf, waiting for its first photo.', 'stubli' ),
		) : array(
			/* translators: %s: first name */
			sprintf( __( 'No photos here yet. %s is still choosing the good ones.', 'stubli' ), $name ),
			/* translators: %s: first name */
			sprintf( __( 'This spot is saved for %s\'s next favourite photo.', 'stubli' ), $name ),
		);
		$cta = $owner ? array( home_url( '/app/#new' ), __( 'Post a photo', 'stubli' ) ) : null;
	}
	$line = $lines[ (int) wp_date( 'z' ) % count( $lines ) ];
	$out  = '<div class="st-empty"><svg class="st-empty-mark" viewBox="0 0 60 80" width="30" height="40" aria-hidden="true" focusable="false">' . stubli_glyph( true ) . '</svg><p>' . esc_html( $line ) . '</p>';
	if ( $cta ) {
		$out .= stubli_pill( $cta[0], $cta[1], 'quiet' );
	}
	return $out . '</div>';
}

/**
 * Whether this Stubli shows "Made with Stubli". Off unless the owner said
 * yes in setup or in Settings → Stubli (WordPress.org guideline 10).
 *
 * @return bool
 */
function stubli_credit_on() {
	return (bool) get_option( 'stubli_credit', 0 );
}

/**
 * A quiet invitation below the photos, for visitors: "Made with Stubli ·
 * Make your own". Never for the owner, never on stubli.app itself, and
 * only when the owner shows the credit.
 */
function stubli_make_your_own() {
	if ( ! stubli_credit_on() || current_user_can( 'manage_options' ) || ( class_exists( '\\Stubli\\Feedback' ) && \Stubli\Feedback::is_hub() ) ) {
		return;
	}
	$GLOBALS['stubli_own_shown'] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- own prefix.
	echo '<p class="st-own"><span>' . esc_html__( 'Made with', 'stubli' ) . ' ' . stubli_wordmark() . '</span> <span aria-hidden="true">·</span> <a href="https://stubli.app/welcome/?from=profile">' . esc_html__( 'Make your own', 'stubli' ) . '</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wordmark is static SVG.
}
