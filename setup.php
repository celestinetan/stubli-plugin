<?php
/**
 * Fills the Playground demo with Ana, a made-up person, and her made-up
 * friends: her page, photos and journal, the people she follows and their
 * posts, comments, guest book entries and notes, and a few people to
 * discover. Run once by blueprint.json. Nobody here is real, and the friends
 * have no sites of their own: Stubli's demo mode reads them from the option
 * stubli_demo_people instead of the network. All pictures are drawings.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

use Stubli\Avatar;
use Stubli\Follows;
use Stubli\Friends;
use Stubli\Guestbook;
use Stubli\Journal;
use Stubli\Posts;
use Stubli\Prompts;
use Stubli\Replies;
use Stubli\Sync;

wp_set_current_user( 1 );
// A new WordPress comes with a sample post, comment and page; Ana's Stubli doesn't need them.
foreach ( array( 'hello-world' => 'post', 'sample-page' => 'page' ) as $slug => $type ) {
	$sample = get_page_by_path( $slug, OBJECT, $type );
	if ( $sample ) {
		wp_delete_post( $sample->ID, true );
	}
}
$dir = __DIR__ . '/';
$now = time();
$h   = HOUR_IN_SECONDS;
$d   = DAY_IN_SECONDS;

update_option( 'stubli_demo', 1 );
update_option( 'stubli_setup_done', 1 );
update_option( 'stubli_setup_open', 0 );
update_option( 'stubli_firstday', 'off' );
update_option( 'timezone_string', 'Europe/Zurich' );
update_option( 'blogname', 'Ana Keller' );
update_option( 'stubli_bio', 'Walks around Zurich, mostly by the lake. Drawings, coffee and the odd recipe.' );
update_option( 'stubli_pronouns', 'she/her' );
Avatar::save( 2, 'beret' );
Prompts::save(
	array(
		array( 'q' => 'A perfect Sunday…', 'a' => 'Zopf with butter, a long walk by the lake, and nobody needing anything from me.' ),
		array( 'q' => 'Something I\'m learning…', 'a' => 'Drawing trams from memory. They\'re harder than they look.' ),
		array( 'q' => 'The best café in my city…', 'a' => 'The small one by the park with the wobbly tables. You know the one.' ),
	)
);

/** A drawing in the media library, for the friends' posts. */
$media = static function ( $file, $alt ) use ( $dir ) {
	$tmp = wp_tempnam( $file );
	copy( $dir . $file, $tmp );
	$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0 );
	if ( is_wp_error( $id ) ) {
		return array( 'url' => '', 'alt' => $alt );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	$src = wp_get_attachment_image_src( $id, 'large' );
	return array( 'url' => $src ? $src[0] : wp_get_attachment_url( $id ), 'alt' => $alt );
};
$p = static function ( $text ) {
	return '<p>' . esc_html( $text ) . '</p>';
};

// ——— Ana's own photos ———
$ana   = array();
$posts = array(
	'tram'   => array( array( array( 'tram-rain.jpg', 'A blue tram in the rain on a city street' ) ), 'The 4 in the rain. Everyone quiet, windows fogged up.', 'City', 26 * $h ),
	'coffee' => array( array( array( 'coffee.jpg', 'An espresso and a Gipfeli on a café table' ) ), 'First coffee and a Gipfeli, before anyone else is up.', 'Food', 2 * $d + 3 * $h ),
	'boat'   => array( array( array( 'steamer.jpg', 'A white paddle steamer on a calm lake at dawn' ) ), 'The first boat of the day, and not a ripple on the lake.', 'Lakes', 3 * $d + 5 * $h ),
	'old'    => array( array( array( 'oldtown.jpg', 'Tall painted houses around a fountain in the old town' ) ), 'Old town colours on a grey day.', 'City', 5 * $d + 2 * $h ),
	'peaks'  => array( array( array( 'alps.jpg', 'A pointed snowy peak above fir trees and a small house' ) ), 'Zermatt for the weekend. First snow on the peaks.', 'Mountains', 7 * $d + 4 * $h ),
	'zopf'   => array( array( array( 'zopf.jpg', 'A braided Sunday loaf on a board with butter and jam' ) ), 'Sunday Zopf, the slow kind of breakfast.', 'Food', 9 * $d + 6 * $h ),
	'fog'    => array( array( array( 'forest-fog.jpg', 'Rows of fir trees fading into morning fog' ) ), 'Fog in the forest above the town.', 'Mountains', 12 * $d + 3 * $h ),
	'night'  => array( array( array( 'river-night.jpg', 'City lights and two church towers reflected in the river at night' ) ), 'Walking home late along the river.', 'City', 15 * $d + 9 * $h ),
);
foreach ( array_reverse( $posts, true ) as $key => $post ) {
	$files = array();
	$alts  = array();
	foreach ( $post[0] as $img ) {
		$tmp     = wp_tempnam( $img[0] );
		copy( $dir . $img[0], $tmp );
		$files[] = array( 'name' => $img[0], 'type' => 'image/jpeg', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize( $tmp ) );
		$alts[]  = $img[1];
	}
	$r = Posts::create_photo_post( $files, $alts, $post[1], $post[2], '', array( 'date' => $now - $post[3] ) );
	if ( is_array( $r ) && ! empty( $r['id'] ) ) {
		$ana[ $key ] = (int) $r['id'];
	}
}

// ——— Ana's journal ———
$entries = array(
	array( 'First snow, warm Nussgipfel', '<p>Walked along the lake before work. First snow on the peaks, and the bakery had warm Nussgipfel again.</p><p>Small things, but they set the tone for the whole day.</p>', 2 * $d, 'everyone' ),
	array( 'Bread with Ben', '<p>Ben came over with his starter and a lot of opinions. Our first loaf was flat, the second one rose like it meant it.</p><p>The kitchen still smells of it.</p>', 5 * $d, 'everyone', 'Food' ),
	array( '', '<p>A slow Sunday: coffee, a long walk, nothing else. To do next week:</p><ul class="checklist"><li data-checked="true">Call Mum</li><li data-checked="false">Buy new boots</li></ul>', 8 * $d, 'everyone' ),
	array( 'Things to draw this winter', '<ul class="checklist"><li data-checked="true">The 4 in the rain</li><li data-checked="false">Sofia\'s market stall</li><li data-checked="false">Bruno, if he sits still</li><li data-checked="false">The lake frozen at the edges</li></ul>', 10 * $d, 'only_me' ),
);
foreach ( $entries as $e ) {
	$r = Journal::create( '', $e[3], array(), $e[1], false, $e[0] );
	if ( is_array( $r ) && ! empty( $r['id'] ) ) {
		$g = gmdate( 'Y-m-d H:i:s', $now - $e[2] );
		$upd = array( 'ID' => $r['id'], 'post_date' => get_date_from_gmt( $g ), 'post_date_gmt' => $g, 'edit_date' => true );
		if ( '' === $e[0] ) {
			$upd['post_title'] = wp_date( get_option( 'date_format' ), $now - $e[2] );
		}
		wp_update_post( $upd );
		if ( ! empty( $e[4] ) && method_exists( Journal::class, 'file' ) ) {
			Journal::file( (int) $r['id'], $e[4] ); // Also a tile in her Food collection.
		}
	}
}

// ——— The made-up friends, and people they follow ———
$site = static function ( $slug ) {
	return 'https://' . $slug . '.example/';
};
$o = static function ( $slug ) {
	return 'https://' . $slug . '.example';
};
$me = Follows::origin( home_url() );
$people_src = array(
	'ben-huber'        => array( 'Ben Huber', 0, 'alpine', 'Climbs on weekends, bakes on Sundays. Zurich.', array( 'sofia-meier', 'luca-bernasconi', 'noah-frei', 'elias-graf' ), array(),
		array(
			array( 'b1', 3 * $h, 'Last ride up before the season closes. Worth the queue.', array( array( 'cable-car.jpg', 'A red cable car high above a valley with snowy peaks' ) ) ),
			array( 'b2', 27 * $h, 'Night at the hut. No signal, all the stars.', array( array( 'hut-stars.jpg', 'A mountain hut with lit windows under a starry sky' ) ) ),
			array( 'b3', 4 * $d, 'Loaf number 40. Finally the crumb I wanted. Bringing one to Ana\'s on Saturday.', array( array( 'sourdough.jpg', 'A round sourdough loaf on a wooden board' ) ), '', 'friends' ),
			array( 'b4', 8 * $d, 'Summit done. Seven hours, one very good sandwich.', array( array( 'summit.jpg', 'A summit cross with a red flag above the peaks' ), array( 'signpost.jpg', 'Yellow hiking signposts on a mountain path' ) ) ),
		),
	),
	'sofia-meier'      => array( 'Sofia Meier', 1, 'glasses', 'Cooks for too many people. Bern.', array( 'ben-huber', 'cleo-martin', 'yasmin-haddad' ), array(),
		array(
			array( 's1', 5 * $h, 'The Marroni man is back on the square. Autumn is official.', array( array( 'marroni.jpg', 'A roasted chestnut stand with a paper bag of chestnuts' ) ) ),
			array( 's2', 2 * $d + 4 * $h, 'Saturday market haul, Sunday soup.', array( array( 'market.jpg', 'A market stall full of pumpkins under a striped awning' ), array( 'soup.jpg', 'A bowl of pumpkin soup with bread' ) ) ),
			array( 's3', 6 * $d, 'Fondue night. Seven people, one pot, nothing left.', array( array( 'fondue.jpg', 'A fondue pot with forks on a checked tablecloth' ) ) ),
		),
	),
	'luca-bernasconi'  => array( 'Luca Bernasconi', 2, 'cowboy', 'Train window person. From Lugano, usually somewhere else.', array( 'ben-huber', 'mila-rossi', 'noah-frei' ), array(),
		array(
			array( 'l1', 1 * $h, 'Home for the weekend. 22 degrees in October and I\'m not complaining.', array( array( 'lugano.jpg', 'Palm trees on a lakeside promenade with a sailboat' ) ) ),
			array( 'l2', 2 * $d + 8 * $h, 'Crossed it twice, because the first time I didn\'t look down.', array( array( 'gorge-bridge.jpg', 'A long footbridge high over a deep gorge' ) ), 'Heights' ),
			array( 'l3', 5 * $d + 6 * $h, 'Lucerne in the morning, Zurich by lunch. Two cities, one day pass.', array( array( 'bridge.jpg', 'A covered wooden bridge and a stone tower over a river' ), array( 'city.jpg', 'Two church towers and a blue tram by the river' ) ) ),
		),
	),
	'cleo-martin'      => array( 'Cleo Martin', 5, 'flowers', 'Paints small things. Lausanne.', array( 'sofia-meier', 'yasmin-haddad' ), array( array( 'q' => 'Currently obsessed with…', 'a' => 'Cheap watercolour paper and the colour of wet slate roofs.' ) ),
		array(
			array( 'c1', 7 * $h, 'Tried to paint the vineyards from the train window. Got off and did it properly.', array( array( 'lavaux.jpg', 'Vineyard terraces stepping down to a lake' ) ) ),
			array( 'c2', 3 * $d + 2 * $h, 'Same view, new paper. Third try.', array( array( 'art.jpg', 'An easel with a small mountain painting and a paint palette' ) ) ),
			array( 'c3', 9 * $d, 'Rainy Sunday corner.', array( array( 'books.jpg', 'A green armchair with an open book under full bookshelves' ) ) ),
			array( 'c4', 13 * $d, 'Painted from a pressed one my grandmother kept.', array( array( 'edelweiss.jpg', 'An edelweiss flower in front of mountains' ) ) ),
		),
	),
	'mila-rossi'       => array( 'Mila Rossi', 4, 'bow', 'Two kids, one kite, many snacks. Winterthur.', array( 'luca-bernasconi', 'jonas-weber' ), array(),
		array(
			array( 'm1', 20 * $h, 'Nina turned four. She asked for "a cake with all the colours".', array( array( 'cake.jpg', 'A birthday cake with four candles and bunting' ) ), '', 'friends' ),
			array( 'm2', 4 * $d + 3 * $h, 'Wind finally on our side.', array( array( 'family.jpg', 'A parent and child flying a red kite on a hill' ) ) ),
			array( 'm3', 11 * $d, 'Lake bench, long talk, cold hands. Thanks, A.', array( array( 'friends.jpg', 'Two friends on a bench by the lake at sunset' ) ) ),
		),
	),
	'jonas-weber'      => array( 'Jonas Weber', 3, 'beanie', 'Plays the trumpet, walks Bruno. Basel.', array( 'mila-rossi' ), array(),
		array(
			array( 'j1', 2 * $h, 'Bruno has opinions about the rain.', array( array( 'pets.jpg', 'A black and white dog with a red collar' ) ) ),
			array( 'j2', 3 * $d + 5 * $h, 'Took the river ferry across just because. Best two francs of the week.', array( array( 'rhine-ferry.jpg', 'A small ferry crossing a river below red towers' ) ) ),
			array( 'j3', 10 * $d, 'Practising for the autumn fair. Sorry, neighbours.', array( array( 'music.jpg', 'A musician walking down a green hill with notes in the air' ) ) ),
		),
	),
	// Not followed by Ana: they appear in Discover through her friends.
	'noah-frei'        => array( 'Noah Frei', 1, 'glasses', 'Photographs trains in the mountains. Chur.', array(), array( array( 'q' => 'My favourite walk…', 'a' => 'Along the old railway line, where you hear the train long before you see it.' ) ),
		array(
			array( 'n1', 6 * $h, 'The red train on the viaduct, right on time.', array( array( 'viaduct.jpg', 'A red train crossing a stone viaduct' ) ) ),
			array( 'n2', 3 * $d, 'Waiting for the next one.', array( array( 'viaduct-sq.jpg', 'A stone viaduct between fir trees' ) ) ),
			array( 'n3', 6 * $d, 'The station master\'s house.', array( array( 'chalet.jpg', 'A wooden chalet with red shutters' ) ) ),
		),
	),
	'elias-graf'       => array( 'Elias Graf', 4, 'moustache', 'Cheesemaker\'s son, now a cheesemaker. Appenzell.', array(), array( array( 'q' => 'Ask me about…', 'a' => 'Why our cheese is washed in herbs. I\'ll tell you everything except the recipe.' ) ),
		array(
			array( 'e1', 9 * $h, 'Back from the summer pasture. She knows the way better than I do.', array( array( 'cow.jpg', 'A brown and white cow with a bell' ) ) ),
			array( 'e2', 4 * $d, 'Last edelweiss of the year.', array( array( 'edelweiss-sq.jpg', 'An edelweiss flower close up' ) ) ),
			array( 'e3', 8 * $d, 'View from the high pasture.', array( array( 'alps-sq.jpg', 'A snowy peak above fir trees' ) ) ),
		),
	),
	'yasmin-haddad'    => array( 'Yasmin Haddad', 5, 'glasses', 'Bookshop on weekdays, lake on weekends. Geneva.', array( 'cleo-martin' ), array( array( 'q' => 'The best café in my city…', 'a' => 'The one with the green shutters. Order the cardamom bun.' ) ),
		array(
			array( 'y1', 4 * $h, 'The fountain is on again. So am I.', array( array( 'jet-deau.jpg', 'A tall lake fountain with flags along the quay' ) ) ),
			array( 'y2', 2 * $d, 'New shelf at the shop.', array( array( 'books-sq.jpg', 'Shelves full of colourful books' ) ) ),
			array( 'y3', 5 * $d, 'Break time.', array( array( 'coffee-sq.jpg', 'An espresso and a croissant on a table' ) ) ),
		),
	),
	// Someone nobody Ana knows follows: their comment waits for her OK.
	'arno-keel'        => array( 'Arno Keel', 1, '', 'Runs before work. Zurich.', array(), array(), array() ),
);
// The friends' pictures only show in the app's feed, so one size is enough (and quicker).
$one_size = static function ( $sizes ) {
	return array_intersect_key( $sizes, array( 'large' => 1 ) );
};
add_filter( 'intermediate_image_sizes_advanced', $one_size );
$people = array();
foreach ( $people_src as $slug => $x ) {
	$posts_out = array();
	foreach ( $x[6] as $post ) {
		$images = array();
		foreach ( $post[3] as $img ) {
			$images[] = $media( $img[0], $img[1] );
		}
		$posts_out[] = array(
			'id'       => $post[0],
			'at'       => $now - $post[1],
			'caption'  => $p( $post[2] ),
			'images'   => $images,
			'note'     => $post[4] ?? '',
			'audience' => $post[5] ?? '',
		);
	}
	$following = array( $me );
	foreach ( $x[4] as $f ) {
		$following[] = $o( $f );
	}
	$people[ $o( $slug ) ] = array(
		'url'       => $site( $slug ),
		'name'      => $x[0],
		'avatar'    => Avatar::url( array( 'c' => $x[1], 'x' => $x[2] ) ),
		'bio'       => $x[3],
		'prompts'   => $x[5],
		'following' => in_array( $slug, array( 'noah-frei', 'elias-graf', 'yasmin-haddad', 'arno-keel' ), true ) ? array_slice( $following, 1 ) : $following,
		'posts'     => $posts_out,
	);
}
remove_filter( 'intermediate_image_sizes_advanced', $one_size );
update_option( 'stubli_demo_people', $people, false );

// ——— Ana follows six of them, in her interests, with a note each ———
$follows = array(
	'ben-huber'       => array( 'Mountains', 'Bread and summits', true ),
	'sofia-meier'     => array( 'Food', 'The best fondue in Bern', true ),
	'luca-bernasconi' => array( 'Travel', 'Always on a train', false ),
	'cleo-martin'     => array( 'Art', 'Watercolours', false ),
	'mila-rossi'      => array( 'Friends', 'Kite queen', true ),
	'jonas-weber'     => array( 'Friends', 'Bruno\'s person', false ),
);
$items = array();
foreach ( $follows as $slug => $f ) {
	$row = Follows::add( $site( $slug ), $f[0] );
	if ( is_array( $row ) && ! empty( $row['id'] ) ) {
		Follows::update( (int) $row['id'], array( 'note' => $f[1] ) );
		if ( $f[2] ) {
			Friends::set( $site( $slug ), true );
		}
	}
}
global $wpdb;
foreach ( $wpdb->get_results( 'SELECT id, guid FROM ' . \Stubli\Install::items_table(), ARRAY_A ) as $row ) { // phpcs:ignore
	$items[ substr( $row['guid'], strrpos( $row['guid'], '=' ) + 1 ) ] = (int) $row['id'];
}

// ——— Ana's comments on her friends' posts ———
foreach ( array( 'm1' => 'Happy birthday, Nina! All the colours, as requested.', 'b3' => 'Saturday it is. I\'ll bring the jam.', 'c1' => 'This is gorgeous. Did you sit on the wall by the station?' ) as $k => $text ) {
	if ( isset( $items[ $k ] ) ) {
		Replies::comment_on_item( $items[ $k ], $text );
	}
}

// ——— Comments on Ana's posts, and her replies ———
$comment = static function ( $post_id, $slug, $text, $ago, $approved = 1, $parent = 0 ) use ( $people, $o, $site, $now ) {
	if ( ! $post_id ) {
		return 0;
	}
	$who  = $people[ $o( $slug ) ]['name'] ?? '';
	$args = array(
		'comment_post_ID'  => $post_id,
		'comment_content'  => $text,
		'comment_approved' => $approved,
		'comment_parent'   => $parent,
		'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - $ago ),
		'comment_date'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $now - $ago ) ),
	);
	if ( 'ana' === $slug ) {
		$args += array( 'user_id' => 1, 'comment_author' => 'Ana Keller', 'comment_author_url' => home_url( '/' ) );
	} else {
		$args += array( 'comment_author' => $who, 'comment_author_url' => $site( $slug ) );
	}
	$id = wp_insert_comment( $args );
	if ( $id && 'ana' !== $slug ) {
		update_comment_meta( $id, 'protocol', 'webmention' );
		update_comment_meta( $id, 'webmention_source_url', $site( $slug ) . 'c/' . $id . '/' );
	}
	return (int) $id;
};
$c = $comment( $ana['tram'] ?? 0, 'mila-rossi', 'The 4 is the best tram. Don\'t let anyone tell you otherwise.', 3 * $h );
$comment( $ana['tram'] ?? 0, 'ana', 'Agreed. The 11 is a close second.', 2 * $h, 1, $c );
$comment( $ana['coffee'] ?? 0, 'ben-huber', 'Gipfeli from the bakery on the corner? Save me one.', 26 * $h );
$comment( $ana['boat'] ?? 0, 'luca-bernasconi', 'Which boat is this? I want that exact morning.', 2 * $d );
$comment( $ana['old'] ?? 0, 'cleo-martin', 'These colours. I\'d paint this in a heartbeat.', 4 * $d );
$comment( $ana['peaks'] ?? 0, 'ben-huber', 'Was up there on Saturday. The snow came early this year.', 6 * $d );
$c = $comment( $ana['zopf'] ?? 0, 'sofia-meier', 'That braid! Teach me your ways.', 8 * $d );
$comment( $ana['zopf'] ?? 0, 'ana', 'Saturday? Bring the soup.', 8 * $d - 3 * $h, 1, $c );
$comment( $ana['night'] ?? 0, 'jonas-weber', 'Basel has nothing on this. Okay, maybe a little.', 14 * $d );
// Waiting for Ana's OK: people she doesn't follow.
$comment( $ana['peaks'] ?? 0, 'elias-graf', 'Lovely light. Which side did you walk up from?', 5 * $h, 0 );
$comment( $ana['fog'] ?? 0, 'arno-keel', 'Where is this forest? It looks like the hill behind my flat.', 30 * $h, 0 );

// ——— Guest book ———
$gb = Guestbook::page_id();
if ( $gb ) {
	Guestbook::set_intro( 'Hello and welcome. Leave a note, I read every one.' );
	$pin = $comment( $gb, 'cleo-martin', 'Your page feels like a sketchbook. Come and visit Lausanne in spring!', 10 * $d );
	if ( $pin ) {
		update_comment_meta( $pin, 'stubli_pinned', 1 );
	}
	$comment( $gb, 'ben-huber', 'Lovely to see your drawings here, Ana. See you at the lake on Saturday!', $d );
	$comment( $gb, 'luca-bernasconi', 'Signed from a train somewhere near Bellinzona. Ciao Ana!', 6 * $d );
	$comment( $gb, 'mila-rossi', 'The kids want to know when you\'ll draw them a cow.', 12 * $d );
	$comment( $gb, 'jonas-weber', 'Bruno says hi. Well, he barked.', 20 * $d );
	$comment( $gb, 'yasmin-haddad', 'Found you through Cleo\'s page. Your tram drawing made my morning.', 22 * $h, 0 );
}

// ——— Letters: a few conversations, one still waiting for Ana's OK ———
$letter = static function ( $slug, $dir, $text, $ago, $args = array() ) use ( $people, $o, $site, $now ) {
	$p    = $people[ $o( $slug ) ];
	$meta = array(
		'_stubli_dir'  => $dir,
		'_stubli_site' => $site( $slug ),
	);
	if ( 'in' === $dir ) {
		$meta += array(
			'_stubli_kind'   => 'stubli',
			'_stubli_name'   => $p['name'],
			'_stubli_avatar' => $p['avatar'],
			'_stubli_state'  => $args['state'] ?? 'inbox',
		);
	} else {
		$meta['_stubli_state'] = 'sent';
	}
	if ( ! empty( $args['prompt'] ) ) {
		$meta['_stubli_prompt'] = wp_json_encode( $args['prompt'] );
	}
	if ( ! empty( $args['read'] ) || 'out' === $dir ) {
		$meta['_stubli_read'] = 1;
	}
	$g = gmdate( 'Y-m-d H:i:s', $now - $ago );
	return wp_insert_post(
		wp_slash(
			array(
				'post_type'     => 'stubli_note',
				'post_status'   => 'private',
				'post_title'    => wp_trim_words( $text, 8, '…' ),
				'post_content'  => $text,
				'post_date_gmt' => $g,
				'post_date'     => get_date_from_gmt( $g ),
				'meta_input'    => $meta,
			)
		)
	);
};
update_option( 'stubli_letters_read_marked', 1, false );
// Sofia: answers Ana's Sunday card, not read yet.
$letter( 'sofia-meier', 'out', 'The soup photo! Is the recipe in your journal yet?', 3 * $d + 2 * $h );
$letter( 'sofia-meier', 'in', 'Tomorrow, promise. It\'s mostly pumpkin, cream and patience.', 3 * $d, array( 'read' => 1 ) );
$letter( 'sofia-meier', 'in', 'Zopf and a lake walk? Count me in next Sunday. I\'ll bring the jam this time.', 9 * $h, array( 'prompt' => Prompts::at( 0 ) ) );
// Ben: bread plans, his last letter unread.
$letter( 'ben-huber', 'in', 'Loaf 40 worked. Want to try my starter? It\'s called Kurt.', 2 * $d, array( 'read' => 1 ) );
$letter( 'ben-huber', 'out', 'Yes please. Is Kurt friendly?', 2 * $d - 2 * $h );
$letter( 'ben-huber', 'in', 'Very. Bring a clean jar on Saturday and he\'s yours.', 20 * $h );
// Mila: Ana wrote last.
$letter( 'mila-rossi', 'in', 'Nina wants to know if you can draw her a cow. A purple one.', 4 * $d, array( 'read' => 1 ) );
$letter( 'mila-rossi', 'out', 'A purple cow is the only kind I draw. Coming up this week.', 4 * $d - 5 * $h );
// Luca: answered her café card, read.
$letter( 'luca-bernasconi', 'in', 'The one with the wobbly tables? I think I\'ve sat there. Next time I\'m in Zurich, coffee is on me.', 12 * $d, array( 'read' => 1, 'prompt' => Prompts::at( 2 ) ) );
// Yasmin: Ana doesn't follow her, so it waits for her OK.
$letter( 'yasmin-haddad', 'in', 'Found you through Cleo. Your tram drawing made my morning. Do you ever sell prints?', 6 * $h, array( 'state' => 'request' ) );
