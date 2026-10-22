<?php
/**
 * "Download everything": one zip with the owner's original photos and
 * videos, plain pages that open offline in any browser, and a data file.
 *
 * Built in small steps (the app calls /archive/step until it's done), so
 * big libraries work on hosts with short time limits. The zip is kept for
 * a day, then removed.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * The archive.
 */
class Archive {

	const OPTION   = 'stubli_archive';
	const DIR      = 'stubli-export';
	const PER_STEP = 25;
	const MAX_STEP = 64 * MB_IN_BYTES;
	const KEEP     = DAY_IN_SECONDS;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'admin_post_stubli_archive_download', array( self::class, 'download' ) );
		add_action( Install::CRON_HOOK, array( self::class, 'clean_up' ), 40 );
	}

	/**
	 * Can this host make zip files?
	 *
	 * @return bool
	 */
	public static function supported() {
		return class_exists( '\ZipArchive' );
	}

	/**
	 * Folder for archives (and nothing else), created on demand.
	 *
	 * @return string
	 */
	private static function dir() {
		$up  = wp_upload_dir( null, false );
		$dir = trailingslashit( $up['basedir'] ) . self::DIR;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $dir;
	}

	/**
	 * Current job (or null).
	 *
	 * @return array|null
	 */
	public static function job() {
		$j = get_option( self::OPTION );
		return is_array( $j ) ? $j : null;
	}

	/**
	 * What the app shows.
	 *
	 * @return array
	 */
	public static function status() {
		$j = self::job();
		if ( ! $j || ! file_exists( $j['file'] ) ) {
			return array(
				'state'     => 'none',
				'supported' => self::supported(),
			);
		}
		$ready = empty( $j['queue'] );
		return array(
			'state'     => $ready ? 'ready' : 'packing',
			'supported' => true,
			'done'      => (int) $j['total'] - count( $j['queue'] ),
			'total'     => (int) $j['total'],
			'size'      => $ready ? size_format( (int) filesize( $j['file'] ), 1 ) : '',
			'expires'   => $ready ? wp_date( 'j F, H:i', (int) $j['created'] + self::KEEP ) : '',
			'download'  => $ready ? add_query_arg(
				array(
					'action'   => 'stubli_archive_download',
					'_wpnonce' => wp_create_nonce( 'stubli_archive_download' ),
				),
				admin_url( 'admin-post.php' )
			) : '',
		);
	}

	/**
	 * Start a new archive: the pages and data file go in now, the media files in steps.
	 *
	 * @return array|\WP_Error
	 */
	public static function start() {
		if ( ! self::supported() ) {
			return new \WP_Error( 'stubli_archive_zip', __( 'This host can\'t make zip files, so the archive can\'t be built here. Ask your host to turn on PHP\'s Zip extension.', 'stubli' ), array( 'status' => 500 ) );
		}
		self::remove();
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$file = self::dir() . '/stubli-' . sanitize_file_name( $host ) . '-' . gmdate( 'Y-m-d' ) . '-' . wp_generate_password( 20, false ) . '.zip';

		$posts = array();
		foreach ( Bundle::post_ids() as $pid ) {
			$p = Bundle::post( $pid );
			if ( $p && 'other' !== $p['type'] ) {
				$posts[] = $p;
			}
		}
		$queue = array();
		$names = array();
		$place = static function ( &$m, $date ) use ( &$queue, &$names ) {
			if ( ! $m || '' === $m['path'] || ! file_exists( $m['path'] ) ) {
				if ( $m ) {
					$m['file'] = '';
				}
				return;
			}
			$base = 'media/' . substr( $date, 0, 7 ) . '/' . $m['name'];
			$name = $base;
			$i    = 2;
			while ( isset( $names[ $name ] ) ) {
				$name = preg_replace( '/(\.[^.]+)$/', '-' . $i . '$1', $base );
				++$i;
			}
			$names[ $name ] = true;
			$m['file']      = $name;
			$queue[]        = array( $m['path'], $name );
		};
		foreach ( $posts as &$p ) {
			foreach ( $p['photos'] as &$ph ) {
				$place( $ph, $p['date'] );
				if ( ! empty( $ph['video'] ) ) {
					$place( $ph['video'], $p['date'] );
				}
			}
			unset( $ph );
		}
		unset( $p );
		$profile = Bundle::profile();
		if ( $profile['avatar'] ) {
			$place( $profile['avatar'], gmdate( 'Y-m-d' ) );
			if ( $profile['avatar']['file'] ) {
				$profile['avatar']['file'] = 'media/profile-' . $profile['avatar']['name'];
				$queue[ count( $queue ) - 1 ][1] = $profile['avatar']['file'];
			}
		}
		$data = array(
			'format'    => Bundle::FORMAT,
			'generator' => 'stubli/' . VERSION,
			'exported'  => gmdate( 'c' ),
			'site'      => home_url( '/' ),
			'profile'   => $profile,
			'settings'  => Bundle::settings(),
			'following' => Bundle::follows(),
			'guestbook' => Bundle::guestbook(),
			'posts'     => $posts,
		);
		$strip = static function ( $v ) use ( &$strip ) {
			if ( is_array( $v ) ) {
				unset( $v['path'] );
				return array_map( $strip, $v );
			}
			return $v;
		};
		$data = $strip( $data );

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return new \WP_Error( 'stubli_archive_write', __( 'The archive couldn\'t be started. There may not be enough space on this host.', 'stubli' ), array( 'status' => 500 ) );
		}
		$zip->addFromString( 'data/stubli.json', (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		foreach ( self::pages( $data ) as $name => $html ) {
			$zip->addFromString( $name, $html );
		}
		$zip->addFromString( 'README.txt', self::readme( $data ) );
		$zip->close();

		$job = array(
			'file'    => $file,
			'queue'   => $queue,
			'total'   => count( $queue ),
			'created' => time(),
		);
		update_option( self::OPTION, $job, false );
		return self::status();
	}

	/**
	 * Add the next media files.
	 *
	 * @return array|\WP_Error
	 */
	public static function step() {
		$j = self::job();
		if ( ! $j || ! file_exists( $j['file'] ) ) {
			return new \WP_Error( 'stubli_archive_gone', __( 'The archive was removed. Start again.', 'stubli' ), array( 'status' => 404 ) );
		}
		if ( ! $j['queue'] ) {
			return self::status();
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $j['file'] ) ) {
			return new \WP_Error( 'stubli_archive_write', __( 'The archive couldn\'t be opened. Start again.', 'stubli' ), array( 'status' => 500 ) );
		}
		$n     = 0;
		$bytes = 0;
		while ( $j['queue'] && $n < self::PER_STEP && $bytes < self::MAX_STEP ) {
			list( $path, $name ) = array_shift( $j['queue'] );
			if ( file_exists( $path ) ) {
				$zip->addFile( $path, $name );
				// Photos and videos are already compressed: store them as they are.
				if ( method_exists( $zip, 'setCompressionName' ) ) {
					$zip->setCompressionName( $name, \ZipArchive::CM_STORE );
				}
				$bytes += (int) filesize( $path );
			}
			++$n;
		}
		if ( ! $zip->close() ) {
			return new \WP_Error( 'stubli_archive_write', __( 'The archive couldn\'t be written. There may not be enough space on this host.', 'stubli' ), array( 'status' => 500 ) );
		}
		update_option( self::OPTION, $j, false );
		return self::status();
	}

	/**
	 * Send the finished zip to the owner.
	 */
	public static function download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only the site owner can download this.', 'stubli' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'stubli_archive_download' );
		$j = self::job();
		if ( ! $j || $j['queue'] || ! file_exists( $j['file'] ) ) {
			wp_die( esc_html__( 'The archive isn\'t ready, or it was removed after a day. Make a new one in the app: You → Yours to keep.', 'stubli' ), '', array( 'response' => 404 ) );
		}
		$name = preg_replace( '/-[A-Za-z0-9]{20}\.zip$/', '.zip', wp_basename( $j['file'] ) );
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $j['file'] ) );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		$fh = fopen( $j['file'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		while ( $fh && ! feof( $fh ) ) {
			echo fread( $fh, 1048576 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.AlternativeFunctions.file_system_operations_fread
			flush();
		}
		if ( $fh ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		exit;
	}

	/**
	 * Delete the archive.
	 */
	public static function remove() {
		$j = self::job();
		if ( $j && ! empty( $j['file'] ) && file_exists( $j['file'] ) ) {
			wp_delete_file( $j['file'] );
		}
		delete_option( self::OPTION );
	}

	/**
	 * Remove archives older than a day.
	 */
	public static function clean_up() {
		$j = self::job();
		if ( $j && time() - (int) $j['created'] > self::KEEP ) {
			self::remove();
		}
	}

	/* ── Offline pages ── */

	/**
	 * The pages inside the zip.
	 *
	 * @param array $d Data.
	 * @return array name => html
	 */
	private static function pages( array $d ) {
		$e      = static fn( $s ) => esc_html( (string) $s );
		$photos = array_values( array_filter( $d['posts'], static fn( $p ) => 'photo' === $p['type'] ) );
		$jour   = array_values( array_filter( $d['posts'], static fn( $p ) => 'journal' === $p['type'] ) );
		$photos = array_reverse( $photos );
		$jour   = array_reverse( $jour );
		$day    = static fn( $iso ) => wp_date( 'j F Y', (int) strtotime( $iso ) );
		$nav    = static function ( $on ) use ( $e ) {
			$items = array(
				'index.html'     => __( 'Photos', 'stubli' ),
				'journal.html'   => __( 'Journal', 'stubli' ),
				'guestbook.html' => __( 'Guest book', 'stubli' ),
				'people.html'    => __( 'People', 'stubli' ),
			);
			$out = '<nav>';
			foreach ( $items as $f => $label ) {
				$out .= '<a href="' . $f . '"' . ( $f === $on ? ' aria-current="page"' : '' ) . '>' . $e( $label ) . '</a>';
			}
			return $out . '</nav>';
		};
		$comments = static function ( array $cs ) use ( $e, $day ) {
			if ( ! $cs ) {
				return '';
			}
			$out = '<ul class="c">';
			foreach ( $cs as $c ) {
				$out .= '<li><strong>' . ( $c['url'] ? '<a href="' . esc_url( $c['url'] ) . '">' . $e( $c['name'] ) . '</a>' : $e( $c['name'] ) ) . '</strong> ' . $e( $c['text'] ) . ' <span class="m">' . $e( $day( $c['date'] ) ) . '</span></li>';
			}
			return $out . '</ul>';
		};
		$media = static function ( $m ) use ( $e ) {
			if ( empty( $m['file'] ) ) {
				return '';
			}
			if ( ! empty( $m['video']['file'] ) ) {
				return '<video controls preload="none" poster="' . esc_attr( $m['file'] ) . '" src="' . esc_attr( $m['video']['file'] ) . '"></video>';
			}
			return '<img src="' . esc_attr( $m['file'] ) . '" alt="' . esc_attr( $m['alt'] ) . '" loading="lazy">';
		};
		$p    = $d['profile'];
		$head = '<header><div class="av">' . ( ! empty( $p['avatar']['file'] ) ? '<img src="' . esc_attr( $p['avatar']['file'] ) . '" alt="">' : '' ) . '</div><div><h1>' . $e( $p['name'] ) . '</h1>' . ( $p['pronouns'] ? '<p class="m">' . $e( $p['pronouns'] ) . '</p>' : '' ) . ( $p['bio'] ? '<p>' . $e( $p['bio'] ) . '</p>' : '' ) . '<p class="m">' . $e( sprintf( /* translators: 1: site address, 2: date */ __( 'Archive of %1$s, made on %2$s', 'stubli' ), $d['site'], $day( $d['exported'] ) ) ) . '</p></div></header>';

		// Photos: a grid, then every post with its photos, caption and comments.
		$grid = '<div class="grid">';
		$list = '';
		foreach ( $photos as $post ) {
			$first = $post['photos'][0] ?? null;
			if ( $first && $first['file'] ) {
				$grid .= '<a href="#p' . (int) $post['id'] . '"><img src="' . esc_attr( $first['file'] ) . '" alt="' . esc_attr( $first['alt'] ) . '" loading="lazy">' . ( count( $post['photos'] ) > 1 ? '<span>' . count( $post['photos'] ) . '</span>' : '' ) . ( ! empty( $first['video'] ) ? '<span>▶</span>' : '' ) . '</a>';
			}
			$list .= '<article id="p' . (int) $post['id'] . '"><p class="m">' . $e( $day( $post['date'] ) ) . ( $post['collection'] ? ' · ' . $e( $post['collection'] ) : '' ) . ( $post['note'] ? ' · ' . $e( sprintf( /* translators: %s: content note */ __( 'Content note: %s', 'stubli' ), $post['note'] ) ) : '' ) . ( 'friends' === ( $post['audience'] ?? '' ) ? ' · ' . $e( __( 'Friends only', 'stubli' ) ) : ( 'private' === $post['status'] ? ' · ' . $e( __( 'Only me', 'stubli' ) ) : '' ) ) . '</p>';
			foreach ( $post['photos'] as $ph ) {
				$list .= '<figure>' . $media( $ph ) . ( $ph['alt'] ? '<figcaption>' . $e( $ph['alt'] ) . '</figcaption>' : '' ) . '</figure>';
			}
			$list .= ( '' !== $post['caption'] ? '<p class="cap">' . nl2br( $e( $post['caption'] ) ) . '</p>' : '' ) . $comments( $post['comments'] ) . '<p class="m"><a href="' . esc_url( $post['url'] ) . '">' . $e( __( 'Online at the time', 'stubli' ) ) . '</a></p></article>';
		}
		$grid .= '</div>';

		// Journal: text with its photos pointing at the files in the zip.
		$jhtml = '';
		foreach ( $jour as $post ) {
			$html = wp_kses_post( $post['html'] );
			foreach ( $post['photos'] as $ph ) {
				if ( $ph['file'] ) {
					$html = Bundle::replace_img( $html, $ph, $media( $ph ) );
				}
			}
			$jhtml .= '<article><p class="m">' . $e( $day( $post['date'] ) ) . ( 'private' === $post['status'] ? ' · ' . $e( __( 'Only me', 'stubli' ) ) : '' ) . '</p><div class="cap">' . $html . '</div>' . $comments( $post['comments'] ) . '</article>';
		}

		$gb = $comments( $d['guestbook'] );
		$pp = '<ul class="c">';
		foreach ( $d['following'] as $f ) {
			$pp .= '<li><strong><a href="' . esc_url( $f['url'] ) . '">' . $e( $f['name'] ? $f['name'] : $f['url'] ) . '</a></strong> <span class="m">' . $e( $f['url'] ) . ( $f['interest'] ? ' · ' . $e( $f['interest'] ) : '' ) . '</span></li>';
		}
		$pp .= '</ul>';

		$wrap = static function ( $file, $title, $body ) use ( $nav, $head, $e, $p ) {
			return '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $e( $title . ' · ' . $p['name'] ) . '</title><style>' . self::css() . '</style></head><body><main>' . $head . $nav( $file ) . $body . '</main></body></html>';
		};
		return array(
			'index.html'     => $wrap( 'index.html', __( 'Photos', 'stubli' ), ( $photos ? $grid . $list : '<p class="m">' . $e( __( 'No photos yet.', 'stubli' ) ) . '</p>' ) ),
			'journal.html'   => $wrap( 'journal.html', __( 'Journal', 'stubli' ), $jhtml ? $jhtml : '<p class="m">' . $e( __( 'No journal entries.', 'stubli' ) ) . '</p>' ),
			'guestbook.html' => $wrap( 'guestbook.html', __( 'Guest book', 'stubli' ), $gb ? $gb : '<p class="m">' . $e( __( 'No guest book entries.', 'stubli' ) ) . '</p>' ),
			'people.html'    => $wrap( 'people.html', __( 'People', 'stubli' ), $d['following'] ? '<h2>' . $e( __( 'People you follow', 'stubli' ) ) . '</h2>' . $pp : '<p class="m">' . $e( __( 'You weren\'t following anyone.', 'stubli' ) ) . '</p>' ),
		);
	}

	/**
	 * Styles for the offline pages: linen, ink and one green.
	 *
	 * @return string
	 */
	private static function css() {
		return 'body{margin:0;background:#f7f3ec;color:#211d19;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}main{max-width:720px;margin:0 auto;padding:24px 16px 64px}header{display:flex;gap:16px;align-items:center}.av{width:72px;height:72px;border-radius:50%;overflow:hidden;background:#e8e1d5;flex:none}.av img{width:100%;height:100%;object-fit:cover}h1{margin:0;font-size:24px}h2{font-size:18px}p{margin:4px 0}.m{color:#6b6157;font-size:14px}nav{display:flex;gap:18px;margin:20px 0;border-bottom:1px solid #e2d9cc}nav a{padding:8px 0;color:#211d19;text-decoration:none}nav a[aria-current]{border-bottom:2px solid #4b6b47;font-weight:600}a{color:#3d5a3a}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:3px;margin-bottom:32px}.grid a{position:relative;aspect-ratio:4/5;background:#e8e1d5}.grid img{width:100%;height:100%;object-fit:cover;display:block}.grid span{position:absolute;top:6px;right:6px;background:rgba(0,0,0,.55);color:#fff;font-size:12px;padding:1px 6px;border-radius:9px}article{padding:20px 0;border-top:1px solid #e2d9cc}figure{margin:10px 0}figure img,figure video,.cap img,.cap video{max-width:100%;height:auto;border-radius:10px;display:block}figcaption{color:#6b6157;font-size:14px}.cap{white-space:normal}ul.c{list-style:none;padding:0}ul.c li{padding:6px 0}@media (prefers-color-scheme:dark){body{background:#1c1916;color:#eee6da}.m,figcaption{color:#b3a899}nav{border-color:#3a342d}nav a{color:#eee6da}article{border-color:#3a342d}a{color:#9fc29a}.av,.grid a{background:#2a2520}}';
	}

	/**
	 * README inside the zip.
	 *
	 * @param array $d Data.
	 * @return string
	 */
	private static function readme( array $d ) {
		$n = count( array_filter( $d['posts'], static fn( $p ) => 'photo' === $p['type'] ) );
		$j = count( $d['posts'] ) - $n;
		return sprintf(
			/* translators: 1: name, 2: site, 3: date, 4: photo posts, 5: journal entries */
			__( "Your Stubli, yours to keep\n\nAn archive of %1\$s (%2\$s), made on %3\$s.\n\nindex.html        Your photos, captions and comments. Open it in any browser; no internet needed.\njournal.html      Your journal entries, including the ones only you could see.\nguestbook.html    Your guest book.\npeople.html       The people you followed.\nmedia/            Your original photos and videos, by month, with location already removed.\ndata/stubli.json  Everything above as data, for other tools.\n\nInside: %4\$d photo posts and %5\$d journal entries.\n\nTo move to another WordPress site instead, use \"Move to another site\" in the Stubli app.\n", 'stubli' ),
			$d['profile']['name'],
			$d['site'],
			wp_date( 'j F Y', (int) strtotime( $d['exported'] ) ),
			$n,
			$j
		);
	}

	/**
	 * REST routes (owner only).
	 */
	public static function routes() {
		$owner = array( Rest::class, 'is_owner' );
		$wrap  = static fn( $res ) => is_wp_error( $res ) ? $res : rest_ensure_response( $res );
		Rest::register(
			'/archive',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn() => rest_ensure_response( self::status() ),
					'permission_callback' => $owner,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static fn() => $wrap( self::start() ),
					'permission_callback' => $owner,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => static function () {
						self::remove();
						return rest_ensure_response( self::status() );
					},
					'permission_callback' => $owner,
				),
			)
		);
		Rest::register(
			'/archive/step',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static fn() => $wrap( self::step() ),
				'permission_callback' => $owner,
			)
		);
	}
}
