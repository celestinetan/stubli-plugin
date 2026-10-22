<?php
/**
 * The page views, drawn by the stubli/view block.
 *
 * @package Stubli
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render callback for stubli/view.
 *
 * @param array $attrs Block attributes.
 * @return string
 */
function stubli_render_view( $attrs ) {
	$view = isset( $attrs['view'] ) ? (string) $attrs['view'] : 'profile';
	if ( get_query_var( 'stubli_journal' ) ) {
		$view = 'journal';
	} elseif ( get_query_var( 'stubli_links' ) ) {
		$view = 'links';
	} elseif ( get_query_var( 'stubli_invite' ) ) {
		$view = 'invite';
	} elseif ( get_query_var( 'stubli_remote' ) ) {
		$view = 'remote';
	} elseif ( 'page' === $view && stubli_has_plugin() && is_page( \Stubli\Guestbook::page_id() ) ) {
		$view = 'guestbook';
	}

	ob_start();
	echo '<div class="st-app">';
	switch ( $view ) {
		case 'single':
			stubli_view_single();
			break;
		case 'journal':
			stubli_view_journal();
			break;
		case 'guestbook':
			stubli_view_guestbook();
			break;
		case 'links':
			stubli_view_links();
			break;
		case 'remote':
			stubli_view_remote();
			break;
		case 'invite':
			stubli_view_invite();
			break;
		case 'page':
			stubli_view_page();
			break;
		case 'notfound':
			stubli_view_notfound();
			break;
		default:
			stubli_view_profile();
	}
	stubli_footer();
	echo '</div>';
	return (string) ob_get_clean();
}

/* ───────────────────────── Shared pieces ───────────────────────── */

/**
 * Top bar: site address (or a back link), project pages, reading mode.
 *
 * @param string $back_url  Optional back link.
 * @param string $back_text Its label.
 */
function stubli_topbar( $back_url = '', $back_text = '' ) {
	$pages = get_pages(
		array(
			'sort_column' => 'menu_order,post_title',
			'exclude'     => stubli_has_plugin() ? array( \Stubli\Guestbook::page_id() ) : array(),
		)
	);
	// Pages ordered 90 or later stay out of the top bar (stubli.app keeps Privacy in its footer).
	// WordPress's starter "Sample Page" never shows (audit #12).
	$pages = array_values( array_filter( $pages, static fn( $p ) => (int) $p->menu_order < 90 && 'sample-page' !== $p->post_name ) );
	/**
	 * Filters the pages listed in the top bar.
	 *
	 * @param WP_Post[] $pages Pages, in order.
	 */
	$pages = (array) apply_filters( 'stubli_top_pages', $pages );
	$hub   = class_exists( '\\Stubli\\Feedback' ) && \Stubli\Feedback::is_hub();
	?>
	<header class="st-top<?php echo $hub ? ' st-top-brand' : ''; ?>">
		<?php if ( $back_url ) : ?>
			<a class="st-top-back" href="<?php echo esc_url( $back_url ); ?>"><?php echo stubli_icon( 'back', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $back_text ); ?></span></a>
		<?php else : ?>
			<?php if ( $hub ) : ?>
				<a class="st-top-host st-top-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Stubli (beta), home', 'stubli' ); ?>"><?php echo stubli_wordmark( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="st-beta" aria-hidden="true"><?php esc_html_e( 'Beta', 'stubli' ); ?></span></a>
			<?php else : ?>
				<a class="st-top-host" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></a>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( $pages ) : ?>
			<nav class="st-top-pages" aria-label="<?php esc_attr_e( 'Pages', 'stubli' ); ?>">
				<?php foreach ( $pages as $page ) : ?>
					<a href="<?php echo esc_url( get_permalink( $page ) ); ?>"<?php echo $hub && 'get-started' === $page->post_name ? ' class="st-top-join"' : ''; ?><?php echo is_page( $page->ID ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( get_the_title( $page ) ); ?></a>
				<?php endforeach; ?>
				<?php if ( $hub && function_exists( 'stubli_story_on' ) && stubli_story_on() ) : ?>
					<a class="st-top-join" href="<?php echo esc_url( stubli_story_url( 'join' ) ); ?>"><?php esc_html_e( 'Join Stubli', 'stubli' ); ?></a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
		<button type="button" class="st-mode" data-st-mode aria-label="<?php esc_attr_e( 'Reading mode', 'stubli' ); ?>"><span class="st-mode-aa" aria-hidden="true">Aa</span> <span class="st-mode-label"><?php esc_html_e( 'Auto', 'stubli' ); ?></span></button>
	</header>
	<?php
}

/**
 * A small pill-shaped action inside a 44px hit area.
 *
 * @param string $url     Link.
 * @param string $label   Text.
 * @param string $variant primary | quiet.
 * @return string
 */
function stubli_pill( $url, $label, $variant = 'primary' ) {
	return sprintf( '<a class="st-pill st-pill-%3$s" href="%1$s"><span>%2$s</span></a>', esc_url( $url ), esc_html( $label ), esc_attr( $variant ) );
}

/**
 * The profile card and the Latest card.
 */
function stubli_profile_cards() {
	$p      = stubli_profile();
	$latest = stubli_latest_photo();
	?>
	<?php $prompts = ! empty( $p['prompts'] ) && is_array( $p['prompts'] ) ? $p['prompts'] : array(); ?>
	<div class="st-cards">
		<div class="st-stack-wrap">
		<div class="st-stack<?php echo $prompts ? ' has-cards' : ''; ?>" data-st-stack aria-roledescription="<?php esc_attr_e( 'cards', 'stubli' ); ?>">
		<section class="st-card st-profile is-front" data-st-card aria-label="<?php esc_attr_e( 'About', 'stubli' ); ?>">
			<div class="st-profile-head">
				<?php if ( ! empty( $p['avatar'] ) ) : ?>
					<img class="st-avatar" src="<?php echo esc_url( stubli_img_src( $p['avatar'] ), array( 'http', 'https', 'data' ) ); ?>" alt="" width="44" height="44">
				<?php endif; ?>
				<div class="st-profile-name">
					<h1><?php echo esc_html( $p['name'] ); ?><?php if ( ! empty( $p['pronouns'] ) ) : ?> <span class="st-pronouns"><?php echo esc_html( $p['pronouns'] ); ?></span><?php endif; ?></h1>
					<span class="st-muted"><?php echo esc_html( $p['host'] ); ?></span>
				</div>
				<?php
				if ( stubli_is_owner() ) {
					echo stubli_pill( home_url( '/app/' ), __( 'Open the app', 'stubli' ), 'quiet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo stubli_pill( stubli_remote_url( 'follow', home_url( '/' ) ), __( 'Follow', 'stubli' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
			<?php if ( '' !== trim( $p['bio'] ) ) : ?>
				<p class="st-bio"><?php echo esc_html( $p['bio'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $p['on_break'] ) ) : ?>
				<p class="st-break"><?php /* translators: %s: name */ printf( esc_html__( '%s is taking a break from comments.', 'stubli' ), esc_html( $p['name'] ) ); ?></p>
			<?php endif; ?>
		</section>
		<?php foreach ( $prompts as $i => $card ) : ?>
			<section class="st-card st-prompt" data-st-card aria-label="<?php echo esc_attr( $card['q'] ); ?>">
				<p class="st-prompt-q"><?php echo esc_html( $card['q'] ); ?></p>
				<p class="st-prompt-a"><?php echo esc_html( $card['a'] ); ?></p>
				<?php if ( ! stubli_is_owner() && empty( $p['on_break'] ) ) : ?>
					<a class="st-pill st-pill-quiet st-prompt-reply" href="<?php echo esc_url( add_query_arg( 'p', (int) $i, stubli_remote_url( 'note', home_url( '/' ) ) ) ); ?>"><span><?php esc_html_e( 'Reply to this', 'stubli' ); ?></span></a>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
		</div>
		<?php if ( $prompts ) : ?>
			<div class="st-stack-nav" data-st-stack-nav hidden>
				<button type="button" class="st-stack-btn" data-st-prev aria-label="<?php esc_attr_e( 'Previous card', 'stubli' ); ?>"><?php echo stubli_icon( 'back', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<span class="st-stack-dots" aria-hidden="true"><?php echo str_repeat( '<span></span>', count( $prompts ) + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<button type="button" class="st-stack-btn" data-st-next aria-label="<?php /* translators: %s: name */ echo esc_attr( sprintf( __( 'Next card: more about %s', 'stubli' ), $p['name'] ) ); ?>"><?php echo stubli_icon( 'chev', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<span class="st-sr" aria-live="polite" data-st-stack-status></span>
			</div>
		<?php endif; ?>
		</div>
		<?php if ( $latest ) : ?>
			<?php
			$ids  = stubli_photo_ids( $latest->ID );
			$coll = stubli_post_collection( $latest->ID );
			?>
			<a class="st-card st-latest" href="<?php echo esc_url( get_permalink( $latest ) ); ?>">
				<?php echo $ids ? wp_get_attachment_image( $ids[0], 'thumbnail', false, array( 'class' => 'st-latest-img', 'alt' => '' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="st-latest-text">
					<span class="st-muted"><?php /* translators: %s: when */ printf( esc_html__( 'Latest · %s', 'stubli' ), esc_html( stubli_gentle_date( $latest ) ) ); ?></span>
					<span><?php echo esc_html( stubli_post_label( $latest ) ); ?><?php if ( $coll ) : ?> <span class="st-muted"><?php /* translators: %s: collection */ printf( esc_html__( 'in %s', 'stubli' ), esc_html( stubli_collection_name( $coll ) ) ); ?></span><?php endif; ?></span>
				</span>
				<?php echo stubli_icon( 'chev', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * The folder tabs: collections and Journal on the left, Guest book and
 * Links (Stubli green) on the right.
 *
 * @param int|string $active Term ID, or journal | guest | links.
 */
function stubli_tabs( $active ) {
	$collections = stubli_collections();
	$tabs        = array(
		'journal' => array( home_url( '/journal/' ), __( 'Journal', 'stubli' ), 'book' ),
		'guest'   => array( stubli_has_plugin() ? \Stubli\Guestbook::url() : '', __( 'Guest book', 'stubli' ), 'pen' ),
		'links'   => array( home_url( '/links/' ), __( 'Links', 'stubli' ), 'mark' ),
	);
	$icon_tab    = static function ( $key, $green ) use ( $tabs, $active ) {
		list( $url, $label, $icon ) = $tabs[ $key ];
		if ( ! $url ) {
			return;
		}
		$is = ( $active === $key );
		printf(
			'<a class="st-tab %1$s%2$s" href="%3$s"%4$s%5$s><span class="st-tab-face">%6$s<span class="st-tab-label">%7$s</span></span></a>',
			$green ? 'st-tab-green' : 'st-tab-plain',
			$is ? ' is-active' : ' st-tab-icon',
			esc_url( $url ),
			$is ? ' aria-current="page"' : '',
			$is ? '' : ' aria-label="' . esc_attr( $label ) . '" data-tip="' . esc_attr( $label ) . '"',
			stubli_icon( $icon, 18 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $label )
		);
	};
	?>
	<nav class="st-tabs" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name */ __( '%s\'s page', 'stubli' ), get_bloginfo( 'name' ) ) ); ?>">
		<div class="st-tabs-left">
			<div class="st-tabs-scroll" data-st-scroll>
				<?php foreach ( $collections as $term ) : ?>
					<?php $is = ( (int) $active === (int) $term->term_id ); ?>
					<a class="st-tab st-tab-plain<?php echo $is ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_category_link( $term ) ); ?>"<?php echo $is ? ' aria-current="page"' : ''; ?>><span class="st-tab-face"><span class="st-tab-label"><?php echo esc_html( stubli_collection_name( $term ) ); ?></span></span></a>
				<?php endforeach; ?>
			</div>
			<?php $icon_tab( 'journal', false ); ?>
		</div>
		<div class="st-tabs-right" role="group" aria-label="<?php esc_attr_e( 'Visit and connect', 'stubli' ); ?>">
			<?php $icon_tab( 'guest', true ); ?>
			<?php $icon_tab( 'links', true ); ?>
		</div>
	</nav>
	<?php
}

/**
 * The photo grid.
 *
 * @param WP_Query $q Photo posts.
 */
function stubli_grid( WP_Query $q ) {
	if ( ! $q->have_posts() ) {
		echo stubli_empty( 'photos' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		return;
	}
	echo '<ul class="st-grid" role="list">';
	foreach ( $q->posts as $post ) {
		$ids  = stubli_photo_ids( $post->ID );
		$note = (string) get_post_meta( $post->ID, '_stubli_content_note', true );
		$alt  = $ids ? (string) get_post_meta( $ids[0], '_wp_attachment_image_alt', true ) : '';
		echo '<li><a class="st-tile" href="' . esc_url( get_permalink( $post ) ) . '">';
		echo '<span class="st-tile-photo' . ( $note ? ' has-note' : '' ) . '">';
		if ( $ids ) {
			echo wp_get_attachment_image( $ids[0], 'stubli-tile', false, array( 'alt' => $note ? '' : $alt, 'loading' => 'lazy', 'sizes' => '(min-width: 1280px) 240px, (min-width: 768px) 30vw, 46vw' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( count( $ids ) > 1 ) {
			echo '<span class="st-badge">' . stubli_icon( 'stack', 14 ) . '<span class="screen-reader-text">' . esc_html__( 'Photos:', 'stubli' ) . ' </span>' . (int) count( $ids ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( $ids && stubli_video_for( $ids[0] ) ) {
			echo '<span class="st-badge">' . stubli_icon( 'play', 14 ) . '<span class="screen-reader-text">' . esc_html__( 'Video', 'stubli' ) . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $note ) {
			echo '<span class="st-tile-note">' . esc_html( $note ) . '</span>';
		}
		echo '</span><span class="st-tile-caption"><span class="st-muted">' . esc_html( stubli_short_date( $post ) ) . '</span> · ' . esc_html( stubli_post_label( $post ) ) . '</span>';
		echo '</a></li>';
	}
	echo '</ul>';
	$next = (int) $q->get( 'paged' ) < (int) $q->max_num_pages ? get_pagenum_link( max( 1, (int) $q->get( 'paged' ) ) + 1 ) : '';
	if ( $next ) {
		echo '<p class="st-more"><a href="' . esc_url( $next ) . '">' . esc_html__( 'Older photos', 'stubli' ) . '</a></p>';
	}
}

/**
 * Comments on a post or page.
 *
 * @param int $post_id Post ID.
 */
function stubli_comments( $post_id ) {
	$comments = stubli_has_plugin() ? \Stubli\Moderation::for_post( $post_id ) : array();
	?>
	<section class="st-comments" aria-labelledby="st-comments-h">
		<h2 id="st-comments-h"><?php esc_html_e( 'Comments', 'stubli' ); ?></h2>
		<?php if ( ! $comments ) : ?>
			<p class="st-muted"><?php esc_html_e( 'No comments yet.', 'stubli' ); ?></p>
		<?php endif; ?>
		<?php foreach ( $comments as $c ) : ?>
			<div class="st-comment<?php echo $c['parent'] ? ' is-reply' : ''; ?>">
				<img class="st-comment-avatar" src="<?php echo esc_url( stubli_img_src( $c['author']['avatar'] ), array( 'http', 'https', 'data' ) ); ?>" alt="" width="32" height="32" loading="lazy">
				<div>
					<p class="st-comment-meta"><a href="<?php echo esc_url( $c['author']['url'] ); ?>"><strong><?php echo esc_html( $c['author']['name'] ); ?></strong></a> <span class="st-muted"><?php echo esc_html( (string) wp_parse_url( $c['author']['url'], PHP_URL_HOST ) ); ?> · <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $c['date'] ) ) ); ?></span></p>
					<p class="st-comment-text"><?php echo esc_html( $c['text'] ); ?></p>
				</div>
			</div>
		<?php endforeach; ?>
		<?php if ( stubli_profile()['on_break'] ) : ?>
			<p class="st-break"><?php esc_html_e( 'Comments are paused for now.', 'stubli' ); ?></p>
		<?php elseif ( ! stubli_is_owner() ) : ?>
			<?php echo stubli_pill( stubli_remote_url( 'comment', get_permalink( $post_id ) ), __( 'Comment from your own Stubli', 'stubli' ), 'quiet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
		<?php if ( stubli_is_owner() && in_array( get_post_format( $post_id ), array( 'image', 'gallery' ), true ) ) : ?>
			<?php echo stubli_pill( home_url( '/app/#edit/' . (int) $post_id ), __( 'Edit in the app', 'stubli' ), 'quiet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Footer: © years and name; "Made with stübli" only if the owner turned it on.
 */
function stubli_footer() {
	$now = (int) wp_date( 'Y' );
	if ( 'stubli.app' === (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		// The project site: who made Stubli, and that the code is free to use.
		?>
	<footer class="st-footer">
		<span>&copy; <?php echo (int) $now; ?> Celestine Tan</span>
		<span aria-hidden="true">·</span>
		<a href="https://buymeacoffee.com/celestinetan"><?php esc_html_e( 'Support Stubli', 'stubli' ); ?></a>
		<span aria-hidden="true">·</span>
		<a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'stubli' ); ?></a>
	</footer>
		<?php
		return;
	}
	// Everyone else's Stubli: their photos and words are theirs.
	$first = get_posts(
		array(
			'numberposts' => 1,
			'orderby'     => 'date',
			'order'       => 'ASC',
			'post_status' => 'publish',
			'fields'      => 'ids',
		)
	);
	$from  = $first ? (int) get_the_date( 'Y', $first[0] ) : $now;
	$years = $from && $from < $now ? $from . '–' . $now : (string) $now;
	?>
	<footer class="st-footer">
		<span>&copy; <?php echo esc_html( $years . ' ' . get_bloginfo( 'name' ) ); ?></span>
		<?php if ( get_option( 'stubli_credit', 0 ) ) : // Opt-in only: the owner ticks it in the setup or in Settings → Stubli. ?>
			<span aria-hidden="true">·</span>
			<a href="https://stubli.app/"><?php esc_html_e( 'Made with', 'stubli' ); ?> <?php echo stubli_wordmark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		<?php endif; ?>
	</footer>
	<?php
}

/* ───────────────────────── Views ───────────────────────── */

/**
 * Profile: cards, tabs, the active collection's panel.
 */
function stubli_view_profile() {
	$collections = stubli_collections();
	$active      = 0;
	if ( is_category() ) {
		$active = (int) get_queried_object_id();
	} elseif ( $collections ) {
		$active = (int) $collections[0]->term_id;
	}
	$term = $active ? get_term( $active, 'category' ) : null;
	stubli_topbar();
	echo '<main class="st-main" id="main">';
	$moved = (string) get_option( 'stubli_moved_to', '' );
	if ( $moved ) {
		/* translators: %s: new address */
		echo '<p class="st-card-tone st-moved" role="status">' . sprintf( esc_html__( 'This Stubli has moved to %s.', 'stubli' ), '<a href="' . esc_url( $moved ) . '">' . esc_html( untrailingslashit( preg_replace( '#^https?://#', '', $moved ) ) ) . '</a>' ) . '</p>';
	}
	if ( function_exists( 'stubli_hub_welcome' ) && ! is_category() ) {
		stubli_hub_welcome();
	}
	stubli_profile_cards();
	stubli_tabs( $active );
	echo '<section class="st-panel' . ( $collections && (int) $collections[0]->term_id === $active ? ' is-first' : '' ) . '">';
	if ( $term && ! is_wp_error( $term ) && '' !== trim( $term->description ) ) {
		echo '<p class="st-wall-label">' . esc_html( wp_strip_all_tags( $term->description ) ) . '</p>';
	}
	stubli_grid( stubli_photos( $active, max( 1, (int) get_query_var( 'paged' ) ) ) );
	echo '</section></main>';
}

/**
 * One photo post (or a journal entry on its own).
 */
function stubli_view_single() {
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		stubli_view_notfound();
		return;
	}
	if ( has_post_format( 'status', $post ) ) {
		stubli_topbar( home_url( '/journal/' ), __( 'Journal', 'stubli' ) );
		echo '<main class="st-main st-reading" id="main"><article class="st-entry is-full">';
		echo '<h1 class="st-entry-date">' . esc_html( get_the_date( 'l, j F Y', $post ) ) . '</h1>';
		echo '<div class="st-entry-text">' . stubli_entry_html( $post ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</article>';
		stubli_comments( $post->ID );
		echo '</main>';
		return;
	}

	$ids  = stubli_photo_ids( $post->ID );
	$coll = stubli_post_collection( $post->ID );
	$note = (string) get_post_meta( $post->ID, '_stubli_content_note', true );
	$name = get_bloginfo( 'name' );
	stubli_topbar( $coll ? get_category_link( $coll ) : home_url( '/' ), $name );
	echo '<main class="st-main st-post" id="main"><article class="st-post-grid">';
	echo '<h1 class="screen-reader-text">' . esc_html( stubli_post_label( $post ) ) . '</h1>';

	echo '<div class="st-carousel' . ( $note ? ' has-note' : '' ) . '" data-st-carousel>';
	echo '<div class="st-carousel-track" tabindex="0" aria-label="' . esc_attr__( 'Photos', 'stubli' ) . '">';
	foreach ( $ids as $i => $id ) {
		$img = wp_get_attachment_image( $id, 'stubli-large', false, array( 'loading' => 0 === $i ? 'eager' : 'lazy', 'sizes' => '(min-width: 1024px) 600px, 100vw' ) );
		$vid = stubli_video_for( $id );
		if ( $vid ) {
			// Tap to play, with sound (nothing loads until then).
			$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			echo '<figure class="st-slide st-slide-video"><button type="button" class="st-play" data-st-video="' . esc_url( wp_get_attachment_url( $vid ) ) . '" data-type="' . esc_attr( get_post_mime_type( $vid ) ) . '" data-label="' . esc_attr( $alt ) . '" aria-label="' . esc_attr( $alt ? sprintf( /* translators: %s: video description */ __( 'Play video: %s', 'stubli' ), $alt ) : __( 'Play video', 'stubli' ) ) . '">' . $img . '<span class="st-play-icon">' . stubli_icon( 'play', 30 ) . '</span></button></figure>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<figure class="st-slide">' . $img . '</figure>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
	echo '</div>';
	if ( count( $ids ) > 1 ) {
		/* translators: 1: current photo, 2: number of photos */
		echo '<span class="st-carousel-count" aria-live="polite">' . esc_html( sprintf( __( '%1$d of %2$d', 'stubli' ), 1, count( $ids ) ) ) . '</span>';
		echo '<div class="st-dots" aria-hidden="true">' . str_repeat( '<span></span>', count( $ids ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	if ( $note ) {
		echo '<button type="button" class="st-note-reveal" data-st-reveal>' . stubli_icon( 'eye', 20 ) . '<span><strong>' . esc_html( $note ) . '</strong><span>' . esc_html__( 'Show photo', 'stubli' ) . '</span></span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</div>';

	echo '<div class="st-post-side">';
	echo '<p class="st-post-meta st-muted">' . esc_html( get_the_date( 'F Y', $post ) );
	if ( $coll ) {
		echo ' · <a href="' . esc_url( get_category_link( $coll ) ) . '">' . esc_html( stubli_collection_name( $coll ) ) . '</a>';
	}
	echo '</p>';
	echo '<div class="st-caption">' . wp_kses_post( wpautop( $post->post_content ) ) . '</div>';
	stubli_comments( $post->ID );

	$next = $coll ? get_adjacent_post( true, '', true, 'category' ) : get_adjacent_post( false, '', true );
	if ( $next && ! has_post_format( 'status', $next ) ) {
		$nids = stubli_photo_ids( $next->ID );
		echo '<a class="st-card-tone st-next" href="' . esc_url( get_permalink( $next ) ) . '">';
		echo $nids ? wp_get_attachment_image( $nids[0], 'thumbnail', false, array( 'alt' => '' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		/* translators: %s: collection */
		echo '<span><span class="st-muted">' . esc_html( $coll ? sprintf( __( 'Next in %s', 'stubli' ), stubli_collection_name( $coll ) ) : __( 'Next', 'stubli' ) ) . '</span><span>' . esc_html( stubli_post_label( $next ) ) . '</span></span>' . stubli_icon( 'chev', 20 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</div></article></main>';
}

/**
 * A journal entry's HTML, with video links turned into click-to-play
 * (nothing loads from YouTube or Vimeo until pressed).
 *
 * @param WP_Post $post Entry.
 * @return string
 */
function stubli_entry_html( $post ) {
	$html = wpautop( $post->post_content );
	$html = preg_replace_callback(
		'#<a [^>]*href="(https?://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/|vimeo\.com/)([A-Za-z0-9_-]+)[^"]*)"[^>]*>[^<]*</a>#i',
		static function ( $m ) {
			$is_vimeo = false !== stripos( $m[1], 'vimeo.com' );
			$src      = $is_vimeo ? 'https://player.vimeo.com/video/' . rawurlencode( $m[2] ) . '?dnt=1&autoplay=1' : 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $m[2] ) . '?autoplay=1';
			$from     = $is_vimeo ? 'Vimeo' : 'YouTube';
			return '<span class="st-video" data-st-video="' . esc_url( $src ) . '"><button type="button" class="st-video-play" aria-label="' . esc_attr__( 'Play video', 'stubli' ) . '">' . stubli_icon( 'play', 22 ) . '</button></span><span class="st-video-note">' . esc_html( sprintf( /* translators: %s: YouTube or Vimeo */ __( 'Video from %s · loads only when you press play', 'stubli' ), $from ) ) . '</span>';
		},
		$html
	);
	return wp_kses_post( $html ) === $html ? $html : stubli_kses_entry( $html );
}

/**
 * Allow the click-to-play markup through kses.
 *
 * @param string $html HTML.
 * @return string
 */
function stubli_kses_entry( $html ) {
	$allowed                          = wp_kses_allowed_html( 'post' );
	$allowed['span']['data-st-video'] = true;
	$allowed['button']                = array(
		'type'       => true,
		'class'      => true,
		'aria-label' => true,
	);
	$allowed['svg']                   = array( 'class' => true, 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'aria-hidden' => true, 'focusable' => true );
	$allowed['path']                  = array( 'd' => true, 'fill' => true );
	return wp_kses( $html, $allowed );
}

/**
 * Journal tab.
 */
function stubli_view_journal() {
	stubli_topbar();
	echo '<main class="st-main" id="main">';
	stubli_profile_cards();
	stubli_tabs( 'journal' );
	echo '<section class="st-panel st-journal">';
	$entries = new WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'paged'          => max( 1, (int) get_query_var( 'paged' ) ),
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'post_format',
					'field'    => 'slug',
					'terms'    => array( 'post-format-status' ),
				),
			),
		)
	);
	if ( ! $entries->have_posts() ) {
		echo stubli_empty( 'journal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
	foreach ( $entries->posts as $entry ) {
		$long = mb_strlen( wp_strip_all_tags( $entry->post_content ) ) > 600;
		echo '<article class="st-entry' . ( $long ? ' is-long' : '' ) . '">';
		echo '<h2 class="st-entry-date"><time datetime="' . esc_attr( get_post_time( 'c', true, $entry ) ) . '">' . esc_html( get_the_date( 'l, j F', $entry ) ) . '</time></h2>';
		echo '<div class="st-entry-text">' . stubli_entry_html( $entry ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $long ) {
			echo '<a class="st-read-more" href="' . esc_url( get_permalink( $entry ) ) . '">' . esc_html__( 'Read more', 'stubli' ) . '</a>';
		}
		echo '</article>';
	}
	echo '</section></main>';
}

/**
 * Guest book tab.
 */
function stubli_view_guestbook() {
	$page = get_queried_object();
	stubli_topbar();
	echo '<main class="st-main" id="main">';
	stubli_profile_cards();
	stubli_tabs( 'guest' );
	echo '<section class="st-panel st-panel-green">';
	if ( $page instanceof WP_Post && '' !== trim( $page->post_content ) ) {
		echo '<div class="st-guest-intro">' . wp_kses_post( wpautop( $page->post_content ) ) . '</div>';
	}
	if ( stubli_profile()['on_break'] ) {
		echo '<p class="st-break">' . esc_html__( 'The guest book is paused for now.', 'stubli' ) . '</p>';
	} elseif ( ! stubli_is_owner() && $page instanceof WP_Post ) {
		echo stubli_pill( stubli_remote_url( 'sign', get_permalink( $page ) ), __( 'Sign the guest book', 'stubli' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	$entries = stubli_has_plugin() ? \Stubli\Guestbook::entries() : array();
	if ( ! $entries ) {
		echo '<p class="st-empty">' . esc_html__( 'Be the first to say hello.', 'stubli' ) . '</p>';
	}
	echo '<div class="st-guest-list">';
	foreach ( $entries as $e ) {
		echo '<article class="st-guest">';
		echo '<div class="st-guest-head"><img src="' . esc_url( stubli_img_src( $e['author']['avatar'] ), array( 'http', 'https', 'data' ) ) . '" alt="" width="32" height="32" loading="lazy"><div><a href="' . esc_url( $e['author']['url'] ) . '"><strong>' . esc_html( $e['author']['name'] ) . '</strong></a><span class="st-muted">' . esc_html( (string) wp_parse_url( $e['author']['url'], PHP_URL_HOST ) ) . ' · ' . esc_html( wp_date( 'j M', strtotime( $e['date'] ) ) ) . '</span></div>';
		if ( $e['pinned'] ) {
			echo '<span class="st-pinned st-muted">' . stubli_icon( 'pin', 14 ) . esc_html__( 'Pinned', 'stubli' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div><p>' . esc_html( $e['text'] ) . '</p></article>';
	}
	echo '</div></section></main>';
}

/**
 * Links tab: my links, then sites I follow.
 */
function stubli_view_links() {
	stubli_topbar();
	echo '<main class="st-main" id="main">';
	stubli_profile_cards();
	stubli_tabs( 'links' );
	echo '<section class="st-panel st-panel-green is-last">';
	$links = array();
	foreach ( preg_split( '/\r?\n/', (string) get_option( 'stubli_links', '' ) ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( 2 === count( $parts ) && '' !== $parts[0] && in_array( wp_parse_url( $parts[1], PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			$links[] = $parts;
		}
	}
	if ( $links ) {
		echo '<h2 class="st-h3">' . esc_html__( 'My links', 'stubli' ) . '</h2><div class="st-link-list">';
		foreach ( $links as $l ) {
			echo '<a class="st-link-row" href="' . esc_url( $l[1] ) . '" rel="me noopener"><span><strong>' . esc_html( $l[0] ) . '</strong><span class="st-muted">' . esc_html( preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $l[1] ) ) ) . '</span></span>' . stubli_icon( 'chev', 18 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}
	$sites = stubli_has_plugin() ? \Stubli\Follows::public_list() : array();
	echo '<h2 class="st-h3">' . esc_html__( 'Sites I follow', 'stubli' ) . ' <span class="st-muted st-normal">' . esc_html__( '· people I like to visit', 'stubli' ) . '</span></h2>';
	if ( ! $sites ) {
		echo '<p class="st-empty">' . esc_html__( 'Not following anyone yet.', 'stubli' ) . '</p>';
	}
	foreach ( $sites as $s ) {
		echo '<a class="st-site-row" href="' . esc_url( $s['url'] ) . '">';
		echo '<img src="' . esc_url( stubli_img_src( $s['avatar'] ), array( 'http', 'https', 'data' ) ) . '" alt="" width="40" height="40" loading="lazy">';
		echo '<span><span><strong>' . esc_html( $s['name'] ) . '</strong> <span class="st-muted">' . esc_html( (string) wp_parse_url( $s['url'], PHP_URL_HOST ) ) . '</span></span>';
		if ( '' !== $s['note'] ) {
			echo '<span>' . esc_html( $s['note'] ) . '</span>';
		}
		echo '</span></a>';
	}
	echo '</section></main>';
}

/**
 * "From your own Stubli": ask for the visitor's address, then send them to
 * their own site to follow, comment or sign.
 */
function stubli_view_remote() {
	$do     = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : 'comment'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$target = isset( $_GET['url'] ) ? esc_url_raw( rawurldecode( wp_unslash( $_GET['url'] ) ) ) : home_url( '/' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded, then esc_url_raw().
	if ( wp_parse_url( $target, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		$target = home_url( '/' );
	}
	$name   = get_bloginfo( 'name' );
	$titles = array(
		/* translators: %s: name */
		'follow'  => sprintf( __( 'Follow %s from your own Stubli', 'stubli' ), $name ),
		'comment' => __( 'Comment from your own Stubli', 'stubli' ),
		/* translators: %s: name */
		'sign'    => sprintf( __( 'Sign %s\'s guest book', 'stubli' ), $name ),
		/* translators: %s: name */
		'note'    => sprintf( __( 'Reply to %s', 'stubli' ), $name ),
	);
	$do = isset( $titles[ $do ] ) ? $do : 'comment';
	if ( 'note' === $do ) {
		stubli_view_note( $name );
		return;
	}
	stubli_topbar( $target, $name );
	?>
	<main class="st-main st-narrow" id="main">
		<h1 class="st-h1"><?php echo esc_html( $titles[ $do ] ); ?></h1>
		<p class="st-lead">
			<?php
			if ( 'follow' === $do ) {
				esc_html_e( 'Following happens on your own site. Enter your address and we\'ll take you there to confirm.', 'stubli' );
			} else {
				/* translators: %s: name */
				printf( esc_html__( 'You write it on your own site, and it shows up here too. If %1$s follows you, it appears right away; otherwise it waits for %1$s\'s OK.', 'stubli' ), esc_html( $name ) );
			}
			?>
		</p>
		<form class="st-remote" data-st-remote data-do="<?php echo esc_attr( $do ); ?>" data-target="<?php echo esc_url( $target ); ?>">
			<label for="st-addr"><?php esc_html_e( 'Your Stubli address', 'stubli' ); ?></label>
			<input id="st-addr" name="addr" type="url" inputmode="url" autocomplete="url" placeholder="yourname.com" required>
			<span class="st-muted st-small"><?php esc_html_e( 'This device will remember it for next time.', 'stubli' ); ?></span>
			<p class="st-error" role="alert" hidden></p>
			<button type="submit" class="st-button"><?php esc_html_e( 'Continue', 'stubli' ); ?></button>
		</form>
		<section class="st-card-tone st-no-stubli">
			<h2 class="st-h3"><?php esc_html_e( 'No Stubli yet?', 'stubli' ); ?></h2>
			<p><?php esc_html_e( 'It\'s your own little website with photos, a journal and the people you follow. Ask someone who has one for an invite. Setting up takes about 15 minutes.', 'stubli' ); ?></p>
			<a href="https://stubli.app/"><?php esc_html_e( 'How Stubli works', 'stubli' ); ?></a>
		</section>
	</main>
	<?php
}

/**
 * Reply to a prompt card: from your own Stubli, or as a guest with a note
 * only the owner sees (design Q48).
 *
 * @param string $name Owner's name.
 */
function stubli_view_note( $name ) {
	$i      = isset( $_GET['p'] ) ? (int) $_GET['p'] : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$p      = stubli_profile();
	$card   = ( $i >= 0 && isset( $p['prompts'][ $i ] ) ) ? $p['prompts'][ $i ] : null;
	$first  = (string) strtok( $name, ' ' );
	$paused = ! empty( $p['on_break'] );
	stubli_topbar( home_url( '/' ), $name );
	?>
	<main class="st-main st-narrow" id="main">
		<h1 class="st-h1"><?php /* translators: %s: name */ printf( esc_html__( 'Reply to %s', 'stubli' ), esc_html( $first ) ); ?></h1>
		<?php if ( $card ) : ?>
			<div class="st-card st-prompt st-note-quote"><p class="st-prompt-q"><?php echo esc_html( $card['q'] ); ?></p><p class="st-prompt-a"><?php echo esc_html( $card['a'] ); ?></p></div>
		<?php endif; ?>
		<?php if ( $paused ) : ?>
			<p class="st-break"><?php /* translators: %s: name */ printf( esc_html__( '%s is taking a break, so notes are paused for now.', 'stubli' ), esc_html( $first ) ); ?></p>
		<?php else : ?>
		<div class="st-note-tabs" role="tablist" aria-label="<?php esc_attr_e( 'How to reply', 'stubli' ); ?>" data-st-note-tabs>
			<button type="button" role="tab" id="st-tab-guest" aria-controls="st-pane-guest" aria-selected="true"><?php esc_html_e( 'Leave a note', 'stubli' ); ?></button>
			<button type="button" role="tab" id="st-tab-own" aria-controls="st-pane-own" aria-selected="false"><?php esc_html_e( 'I have a Stubli', 'stubli' ); ?></button>
		</div>
		<section id="st-pane-guest" role="tabpanel" aria-labelledby="st-tab-guest">
			<form class="st-note-form" data-st-note data-url="<?php echo esc_url( rest_url( 'stubli/v1/notes/guest' ) ); ?>" data-prompt="<?php echo (int) $i; ?>" novalidate>
				<label for="st-note-text"><?php esc_html_e( 'Your note', 'stubli' ); ?></label>
				<textarea id="st-note-text" name="text" rows="4" maxlength="1000" required></textarea>
				<label for="st-note-name"><?php esc_html_e( 'Your name', 'stubli' ); ?></label>
				<input id="st-note-name" name="name" type="text" maxlength="60" autocomplete="name" required>
				<label for="st-note-contact"><?php /* translators: %s: first name */ printf( esc_html__( 'How %s can reach you', 'stubli' ), esc_html( $first ) ); ?></label>
				<input id="st-note-contact" name="contact" type="text" maxlength="120" autocomplete="email" placeholder="<?php esc_attr_e( 'Email, phone or Instagram name', 'stubli' ); ?>" required>
				<div class="st-trap" aria-hidden="true"><label for="st-note-website">Website</label><input id="st-note-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
				<p class="st-muted st-small"><?php /* translators: %s: first name */ printf( esc_html__( 'Only %s sees this note and your contact details. Nothing appears on this page, and it\'s deleted after 90 days.', 'stubli' ), esc_html( $first ) ); ?></p>
				<p class="st-error" role="alert" hidden></p>
				<button type="submit" class="st-button"><?php esc_html_e( 'Send note', 'stubli' ); ?></button>
			</form>
			<div class="st-card-tone st-note-done" data-st-note-done hidden tabindex="-1">
				<p><strong><?php esc_html_e( 'Sent. Thank you!', 'stubli' ); ?></strong></p>
				<p class="st-muted"><?php /* translators: %s: first name */ printf( esc_html__( '%s will see it soon and can write back to you directly.', 'stubli' ), esc_html( $first ) ); ?></p>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php /* translators: %s: first name */ printf( esc_html__( 'Back to %s\'s page', 'stubli' ), esc_html( $first ) ); ?></a>
			</div>
		</section>
		<section id="st-pane-own" role="tabpanel" aria-labelledby="st-tab-own" hidden>
			<p><?php /* translators: %s: first name */ printf( esc_html__( 'Write it in your own Stubli app. %s gets it as a private note: straight away if they follow you, otherwise after their OK.', 'stubli' ), esc_html( $first ) ); ?></p>
			<form class="st-remote" data-st-remote data-do="note" data-target="<?php echo esc_url( home_url( '/' ) ); ?>" data-p="<?php echo (int) $i; ?>">
				<label for="st-addr"><?php esc_html_e( 'Your Stubli address', 'stubli' ); ?></label>
				<input id="st-addr" name="addr" type="url" inputmode="url" autocomplete="url" placeholder="yourname.com" required>
				<span class="st-muted st-small"><?php esc_html_e( 'This device will remember it for next time.', 'stubli' ); ?></span>
				<p class="st-error" role="alert" hidden></p>
				<button type="submit" class="st-button"><?php esc_html_e( 'Continue in my app', 'stubli' ); ?></button>
			</form>
		</section>
		<?php endif; ?>
	</main>
	<?php
}

/**
 * An invite from the owner: the friend confirms on their own Stubli and
 * both sites follow each other.
 */
function stubli_view_invite() {
	$token = isset( $_GET['t'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_GET['t'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$state = stubli_has_plugin() && class_exists( '\Stubli\Invites' ) ? \Stubli\Invites::check( $token ) : 'unknown';
	$p     = stubli_profile();
	$name  = $p['name'];
	stubli_topbar( home_url( '/' ), $name );
	?>
	<main class="st-main st-narrow" id="main">
		<?php if ( 'ok' === $state ) : ?>
			<div class="st-invite-head">
				<?php if ( ! empty( $p['avatar'] ) ) : ?>
					<img class="st-avatar" src="<?php echo esc_url( stubli_img_src( $p['avatar'] ), array( 'http', 'https', 'data' ) ); ?>" alt="" width="56" height="56">
				<?php endif; ?>
				<h1 class="st-h1"><?php /* translators: %s: name */ printf( esc_html__( '%s invited you to Stubli', 'stubli' ), esc_html( $name ) ); ?></h1>
			</div>
			<p class="st-lead">
				<?php /* translators: %s: name */ printf( esc_html__( 'Stubli is your own little website for photos, a journal and the people you like. Once you\'re set up, you and %s follow each other automatically.', 'stubli' ), esc_html( $name ) ); ?>
			</p>
			<h2 class="st-h3"><?php esc_html_e( 'I already have a Stubli', 'stubli' ); ?></h2>
			<form class="st-remote" data-st-remote data-do="accept" data-target="<?php echo esc_url( home_url( '/' ) ); ?>" data-invite="<?php echo esc_attr( $token ); ?>">
				<label for="st-addr"><?php esc_html_e( 'Your Stubli address', 'stubli' ); ?></label>
				<input id="st-addr" name="addr" type="url" inputmode="url" autocomplete="url" placeholder="yourname.com" required>
				<span class="st-muted st-small"><?php esc_html_e( 'You\'ll confirm on your own site. This device will remember the address.', 'stubli' ); ?></span>
				<p class="st-error" role="alert" hidden></p>
				<button type="submit" class="st-button"><?php esc_html_e( 'Continue', 'stubli' ); ?></button>
			</form>
			<section class="st-card-tone st-no-stubli">
				<h2 class="st-h3"><?php esc_html_e( 'Not yet?', 'stubli' ); ?></h2>
				<p>
					<?php
					/* translators: %s: date */
					printf( esc_html__( 'Setting one up takes about 15 minutes. Keep this link: during setup, paste it in "Your people" and you\'ll follow each other. It works until %s.', 'stubli' ), esc_html( wp_date( 'j F', \Stubli\Invites::expires( $token ) ) ) );
					?>
				</p>
				<a href="https://stubli.app/get-started/"><?php esc_html_e( 'How to get started', 'stubli' ); ?></a>
			</section>
		<?php else : ?>
			<section class="st-card-tone st-notfound">
				<?php echo stubli_app_icon( 44 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h1 class="st-h2">
					<?php
					if ( 'used' === $state ) {
						esc_html_e( 'This invite has already been used', 'stubli' );
					} elseif ( 'expired' === $state ) {
						esc_html_e( 'This invite has expired', 'stubli' );
					} else {
						esc_html_e( 'This invite link doesn\'t work', 'stubli' );
					}
					?>
				</h1>
				<p><?php /* translators: %s: name */ printf( esc_html__( 'Invites work once, for 30 days. Ask %s for a new link.', 'stubli' ), esc_html( $name ) ); ?></p>
				<?php echo stubli_pill( home_url( '/' ), sprintf( /* translators: %s: name */ __( 'Go to %s\'s page', 'stubli' ), $name ), 'quiet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>
		<?php endif; ?>
	</main>
	<?php
}

/**
 * Ordinary pages (the project site's About, Get started, …).
 */
function stubli_view_page() {
	$page = get_queried_object();
	stubli_topbar();
	echo '<main class="st-main st-narrow st-prose" id="main">';
	if ( $page instanceof WP_Post ) {
		echo '<h1 class="st-h1">' . esc_html( get_the_title( $page ) ) . '</h1>';
		// stubli.app's About page is one long page: what, how, join, support, questions.
		if ( 'about' === $page->post_name && function_exists( 'stubli_story_on' ) && stubli_story_on() && class_exists( '\\Stubli\\Feedback' ) && \Stubli\Feedback::is_hub() ) {
			stubli_story( $page );
			echo '</main>';
			return;
		}
		// stubli.app's Get started page is a guided flow: host first, then Stubli.
		if ( 'get-started' === $page->post_name && function_exists( 'stubli_get_started' ) && class_exists( '\\Stubli\\Feedback' ) && \Stubli\Feedback::is_hub() ) {
			stubli_get_started();
			echo '</main>';
			return;
		}
		echo apply_filters( 'the_content', $page->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( 'feedback' === $page->post_name && class_exists( '\\Stubli\\Feedback' ) && \Stubli\Feedback::is_hub() ) {
			stubli_feedback_form();
		}
	}
	echo '</main>';
}

/**
 * The public feedback form (on stubli.app's Send feedback page).
 */
function stubli_feedback_form() {
	$kinds = array(
		'idea'   => array( __( 'An idea', 'stubli' ), __( 'What would make Stubli better?', 'stubli' ), __( 'I wish I could…', 'stubli' ) ),
		'broken' => array( __( 'Something\'s not working', 'stubli' ), __( 'What happened?', 'stubli' ), __( 'I tried to … and then …', 'stubli' ) ),
		'love'   => array( __( 'Kind words', 'stubli' ), __( 'What do you like?', 'stubli' ), __( 'I love that…', 'stubli' ) ),
	);
	?>
	<form class="st-feedback" data-st-feedback data-url="<?php echo esc_url( rest_url( 'stubli/v1/feedback' ) ); ?>" data-version="<?php echo esc_attr( defined( '\\Stubli\\VERSION' ) ? \Stubli\VERSION : '' ); ?>" novalidate>
		<h2 class="st-h3" id="st-fb-kind"><?php esc_html_e( 'What is it about?', 'stubli' ); ?></h2>
		<div class="st-fb-kinds" role="radiogroup" aria-labelledby="st-fb-kind">
			<?php foreach ( $kinds as $key => $k ) : ?>
				<label class="st-fb-kind"><input type="radio" name="kind" value="<?php echo esc_attr( $key ); ?>" data-q="<?php echo esc_attr( $k[1] ); ?>" data-ph="<?php echo esc_attr( $k[2] ); ?>"<?php checked( 'idea', $key ); ?>><span><?php echo esc_html( $k[0] ); ?></span></label>
			<?php endforeach; ?>
		</div>
		<label for="st-fb-text" class="st-fb-label" data-st-q><?php echo esc_html( $kinds['idea'][1] ); ?></label>
		<textarea id="st-fb-text" name="message" rows="6" maxlength="4000" required placeholder="<?php echo esc_attr( $kinds['idea'][2] ); ?>"></textarea>
		<label for="st-fb-reply" class="st-fb-label"><?php esc_html_e( 'Your email or Stubli address', 'stubli' ); ?> <span class="st-muted"><?php esc_html_e( '(optional, so we can reply)', 'stubli' ); ?></span></label>
		<input id="st-fb-reply" name="reply_to" type="text" maxlength="200" autocomplete="email">
		<label for="st-fb-shot" class="st-fb-label"><?php esc_html_e( 'A screenshot', 'stubli' ); ?> <span class="st-muted"><?php esc_html_e( '(optional)', 'stubli' ); ?></span></label>
		<input id="st-fb-shot" name="screenshot" type="file" accept="image/png,image/jpeg,image/webp">
		<div class="st-fb-hp" aria-hidden="true"><label for="st-fb-website">Website</label><input id="st-fb-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
		<p class="st-muted st-small"><?php esc_html_e( 'We also keep the type of phone or computer you use, to help fix problems. Nothing else.', 'stubli' ); ?></p>
		<p class="st-error" role="alert" hidden></p>
		<button type="submit" class="st-button"><?php esc_html_e( 'Send feedback', 'stubli' ); ?></button>
	</form>
	<div class="st-card-tone st-fb-done" data-st-feedback-done hidden tabindex="-1">
		<h2 class="st-h3"><?php esc_html_e( 'Thank you', 'stubli' ); ?></h2>
		<p><?php esc_html_e( 'Your note is with the Stubli team. If you left an address and asked a question, Celestine will reply.', 'stubli' ); ?></p>
	</div>
	<?php
}

/**
 * Page not found.
 */
function stubli_view_notfound() {
	stubli_topbar();
	?>
	<main class="st-main st-narrow" id="main">
		<section class="st-card-tone st-notfound">
			<?php echo stubli_app_icon( 44, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h1 class="st-h2"><?php esc_html_e( 'This page has moved or is gone', 'stubli' ); ?></h1>
			<p><?php /* translators: %s: name */ printf( esc_html__( 'Maybe it was tidied away. %s\'s photos are still here.', 'stubli' ), esc_html( get_bloginfo( 'name' ) ) ); ?></p>
			<?php echo stubli_pill( home_url( '/' ), sprintf( /* translators: %s: name */ __( 'Go to %s\'s page', 'stubli' ), get_bloginfo( 'name' ) ), 'quiet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
	</main>
	<?php
}
