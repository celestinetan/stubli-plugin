<?php
/**
 * Fills the Playground demo with Ana, a made-up person: her page, photos,
 * journal and guest book. Run once by blueprint.json. Nothing here is real.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

wp_set_current_user( 1 );
update_option( 'stubli_demo', 1 );
update_option( 'stubli_setup_done', 1 );
update_option( 'stubli_setup_open', 0 );
update_option( 'blogname', 'Ana Keller' );
update_option( 'stubli_bio', 'Walks around Zurich, mostly by the lake. Drawings, coffee and the odd recipe.' );
update_option( 'stubli_pronouns', 'she/her' );

$dir   = __DIR__ . '/';
$posts = array(
	array( 'tram-rain.jpg', 'A blue tram in the rain', 'The 4 in the rain. Everyone quiet, windows fogged up.', 'City', 1 ),
	array( 'coffee.jpg', 'A cup of coffee on a dark table', 'First coffee, before anyone else is up.', 'Food', 2 ),
	array( 'lake-dawn.jpg', 'Mountains over a still lake at dawn', 'The lake before work. Not a ripple.', 'Lakes', 3 ),
	array( 'old-town.jpg', 'Tall coloured houses in the old town', 'Old town colours on a grey day.', 'City', 5 ),
	array( 'peaks.jpg', 'Snowy peaks under a blue sky', 'First snow on the peaks.', 'Mountains', 7 ),
	array( 'food.jpg', 'A plate with an omelette and tomato', 'Sunday breakfast, the slow kind.', 'Food', 9 ),
	array( 'forest-fog.jpg', 'Pine trees in fog', 'Fog in the forest above the town.', 'Mountains', 12 ),
	array( 'city-night.jpg', 'City lights at night', 'Walking home late.', 'City', 15 ),
);
foreach ( $posts as $p ) {
	$tmp = wp_tempnam( $p[0] );
	copy( $dir . $p[0], $tmp );
	\Stubli\Posts::create_photo_post(
		array( array( 'name' => $p[0], 'type' => 'image/jpeg', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize( $tmp ) ) ),
		array( $p[1] ),
		$p[2],
		$p[3],
		'',
		array( 'date' => time() - $p[4] * DAY_IN_SECONDS )
	);
}

$entries = array(
	array( 'First snow, warm Nussgipfel', '<p>Walked along the lake before work. First snow on the peaks, and the bakery had warm Nussgipfel again.</p><p>Small things, but they set the tone for the whole day.</p>', 2 ),
	array( '', '<p>A slow Sunday: coffee, a long walk, nothing else. To do next week:</p><ul class="checklist"><li data-checked="true">Call Mum</li><li data-checked="false">Buy new boots</li></ul>', 8 ),
);
foreach ( $entries as $e ) {
	$r = \Stubli\Journal::create( '', 'everyone', array(), $e[1], false, $e[0] );
	if ( is_array( $r ) && ! empty( $r['id'] ) ) {
		$d = gmdate( 'Y-m-d H:i:s', time() - $e[2] * DAY_IN_SECONDS );
		wp_update_post( array( 'ID' => $r['id'], 'post_date' => get_date_from_gmt( $d ), 'post_date_gmt' => $d, 'edit_date' => true ) );
	}
}

$gb = \Stubli\Guestbook::page_id();
if ( $gb ) {
	wp_insert_comment(
		array(
			'comment_post_ID'    => $gb,
			'comment_author'     => 'Ben Huber',
			'comment_author_url' => 'https://ben.example',
			'comment_content'    => 'Lovely to see your drawings here, Ana. See you at the lake on Saturday!',
			'comment_approved'   => 1,
			'comment_date_gmt'   => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
		)
	);
}
