<?php
/**
 * "Is this host Stubli-ready?" — a check in Settings → Stubli.
 *
 * Looks at what Stubli needs from a host: a recent PHP and WordPress,
 * Webmention, HTTPS, photo processing, upload size, background jobs,
 * connections out to other sites, and (with stubli.app's help) whether
 * other sites can reach this one. Free hosts often block that last one
 * with a browser check, which silently breaks follows and comments.
 *
 * The owner can send the result to the Stubli team; stubli.app keeps it
 * in the Feedback inbox as a host report, so the host list is built from
 * real results.
 *
 * @package Stubli
 */

namespace Stubli;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Host readiness check.
 */
class HostCheck {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( Install::CRON_HOOK, array( self::class, 'mark_cron' ), 1 );
		add_action( 'admin_post_stubli_host_check', array( self::class, 'handle' ) );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Remember when background jobs last ran.
	 */
	public static function mark_cron() {
		update_option( 'stubli_last_cron', time(), false );
	}

	/**
	 * The hub's "can I reach you?" endpoint.
	 */
	public static function routes() {
		if ( ! Feedback::is_hub() ) {
			return;
		}
		Rest::register(
			'/reach',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'reach' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'url' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * On the hub: try to open another site's Stubli profile, the way other
	 * Stubli sites will.
	 *
	 * @param WP_REST_Request $r Request.
	 * @return array|\WP_Error
	 */
	public static function reach( WP_REST_Request $r ) {
		$n = (int) get_transient( 'stubli_reach_count' );
		if ( $n >= 60 ) {
			return new \WP_Error( 'stubli_rate_limited', __( 'Too many checks right now. Try again in an hour.', 'stubli' ), array( 'status' => 429 ) );
		}
		set_transient( 'stubli_reach_count', $n + 1, HOUR_IN_SECONDS );
		$url = esc_url_raw( (string) $r['url'] );
		if ( ! $url || ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			return new \WP_Error( 'stubli_bad_url', 'Bad address.', array( 'status' => 400 ) );
		}
		return self::probe( add_query_arg( 'rest_route', '/' . LEGACY_NS . '/profile', trailingslashit( $url ) ) );
	}

	/**
	 * Fetch a Stubli profile and describe what came back.
	 *
	 * @param string $endpoint Profile endpoint.
	 * @return array { ok, status, detail }
	 */
	public static function probe( $endpoint ) {
		$res = wp_safe_remote_get(
			$endpoint,
			array(
				'timeout'    => 15,
				'user-agent' => 'Stubli/' . VERSION . ' (host check)',
			)
		);
		if ( is_wp_error( $res ) ) {
			return array(
				'ok'     => false,
				'status' => 0,
				'detail' => 'unreachable',
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		$json = json_decode( $body, true );
		if ( 200 === $code && is_array( $json ) && isset( $json['generator'] ) ) {
			return array(
				'ok'     => true,
				'status' => 200,
				'detail' => 'stubli',
			);
		}
		$detail = 'other';
		if ( preg_match( '/aes\.js|slowAES|requires Javascript|checking your browser|cf-chl|captcha/i', $body ) ) {
			$detail = 'browser-check';
		} elseif ( 200 === $code && is_array( $json ) ) {
			$detail = 'no-stubli';
		} elseif ( in_array( $code, array( 401, 403, 429 ), true ) ) {
			$detail = 'blocked';
		}
		return array(
			'ok'     => false,
			'status' => $code,
			'detail' => $detail,
		);
	}

	/**
	 * Run every check.
	 *
	 * @return array List of { key, ok (true|false|null for a warning), label, help }.
	 */
	public static function run() {
		global $wp_version;
		$out = array();
		$add = static function ( $key, $ok, $label, $help ) use ( &$out ) {
			$out[] = array(
				'key'   => $key,
				'ok'    => $ok,
				'label' => $label,
				'help'  => $help,
			);
		};

		$add( 'php', version_compare( PHP_VERSION, '7.4', '>=' ), 'PHP ' . PHP_VERSION, __( 'Stubli needs PHP 7.4 or newer; 8.1 or newer is best.', 'stubli' ) );
		$add( 'wp', version_compare( $wp_version, '6.5', '>=' ), 'WordPress ' . $wp_version, __( 'Stubli needs WordPress 6.5 or newer.', 'stubli' ) );
		$add( 'webmention', function_exists( 'send_webmention' ), __( 'Webmention plugin', 'stubli' ), __( 'Comments and guest book entries travel between sites with the Webmention plugin. Install and activate it under Plugins.', 'stubli' ) );
		$add( 'https', 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ), __( 'Secure address (https)', 'stubli' ), __( 'Your address should start with https://. Most hosts offer a free certificate; look for "SSL" or "Let\'s Encrypt" in your host\'s settings.', 'stubli' ) );

		$jpeg = wp_image_editor_supports( array( 'mime_type' => 'image/jpeg' ) );
		$webp = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
		$add( 'images', $jpeg ? ( $webp ? true : null ) : false, __( 'Photo processing', 'stubli' ), $jpeg ? ( $webp ? __( 'JPEG and WebP photos both work.', 'stubli' ) : __( 'JPEG works. WebP photos would need a newer image library, which is optional.', 'stubli' ) ) : __( 'This host can\'t resize photos (no GD or Imagick). Ask your host to enable one of them.', 'stubli' ) );

		$max = (int) wp_max_upload_size();
		$add( 'upload', $max >= 8 * MB_IN_BYTES ? true : ( $max >= 2 * MB_IN_BYTES ? null : false ), sprintf( /* translators: %s: size */ __( 'Largest upload: %s', 'stubli' ), size_format( $max ) ), __( 'The app makes photos smaller before sending, so 8 MB is plenty. Below 2 MB, some photos won\'t upload.', 'stubli' ) );

		$mem = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		$add( 'memory', $mem < 0 || $mem >= 128 * MB_IN_BYTES ? true : null, sprintf( /* translators: %s: size */ __( 'Memory: %s', 'stubli' ), $mem < 0 ? __( 'no limit', 'stubli' ) : size_format( $mem ) ), __( 'Photo processing likes 128 MB or more. Lower can work for smaller photos.', 'stubli' ) );

		$last      = (int) get_option( 'stubli_last_cron', 0 );
		$cron_off  = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$scheduled = (bool) wp_next_scheduled( Install::CRON_HOOK );
		$cron_ok   = $scheduled && $last && ( time() - $last ) < 2 * HOUR_IN_SECONDS;
		$add(
			'cron',
			$cron_ok ? true : ( $scheduled ? null : false ),
			__( 'Background jobs', 'stubli' ),
			$cron_ok ? __( 'New posts from people you follow are fetched regularly.', 'stubli' ) : ( $cron_off ? __( 'WordPress\'s own background jobs are switched off here. Ask your host to run wp-cron.php every 15 minutes, or your feed won\'t update.', 'stubli' ) : __( 'Background jobs haven\'t run in the last two hours. On quiet sites they only run when someone visits; a real cron job from your host every 15 minutes fixes that.', 'stubli' ) )
		);

		$out_probe = self::probe( add_query_arg( 'rest_route', '/' . LEGACY_NS . '/profile', Feedback::hub() . '/' ) );
		$add( 'outgoing', $out_probe['ok'], __( 'Reaching other Stubli sites', 'stubli' ), $out_probe['ok'] ? __( 'This site can open other Stubli sites, so following works.', 'stubli' ) : __( 'This site couldn\'t reach stubli.app. Some hosts block outgoing connections; ask yours to allow them.', 'stubli' ) );

		if ( Feedback::is_hub() ) {
			$in = array(
				'ok'     => true,
				'detail' => 'hub',
			);
		} else {
			$res = wp_safe_remote_post(
				add_query_arg( 'rest_route', '/' . LEGACY_NS . '/reach', Feedback::hub() . '/' ),
				array(
					'timeout' => 30,
					'body'    => array( 'url' => home_url( '/' ) ),
				)
			);
			$in  = is_wp_error( $res ) ? array(
				'ok'     => null,
				'detail' => 'hub-offline',
			) : (array) json_decode( (string) wp_remote_retrieve_body( $res ), true );
		}
		$in_help = array(
			'stubli'        => __( 'stubli.app opened your profile from outside, the way your friends\' sites will.', 'stubli' ),
			'hub'           => __( 'This is stubli.app itself.', 'stubli' ),
			'browser-check' => __( 'Your host shows other sites a "checking your browser" page instead of your site. Friends\' sites can\'t get past it, so follows and comments won\'t work. This is common on free hosting and usually can\'t be switched off.', 'stubli' ),
			'blocked'       => __( 'Your host or a security plugin turned stubli.app away. Look for a firewall or "bot protection" setting and allow other sites to reach your site\'s API.', 'stubli' ),
			'no-stubli'     => __( 'Your site answered, but not with Stubli. Is the Stubli plugin active?', 'stubli' ),
			'unreachable'   => __( 'stubli.app couldn\'t reach your site at all. Is it published and public?', 'stubli' ),
			'hub-offline'   => __( 'Couldn\'t ask stubli.app to check right now. Try again later.', 'stubli' ),
			'other'         => __( 'stubli.app reached your site but got an unexpected answer.', 'stubli' ),
		);
		$detail = (string) ( $in['detail'] ?? 'other' );
		$add( 'incoming', array_key_exists( 'ok', $in ) ? $in['ok'] : false, __( 'Other sites reaching yours', 'stubli' ), $in_help[ $detail ] ?? $in_help['other'] );

		return $out;
	}

	/**
	 * Run (and optionally send) the check from the settings screen.
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only the site owner can do this.', 'stubli' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'stubli_host_check' );
		$results = self::run();
		set_transient( 'stubli_host_check_' . get_current_user_id(), $results, HOUR_IN_SECONDS );
		$sent = '';
		if ( ! empty( $_POST['send'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
			$host  = isset( $_POST['host_name'] ) ? sanitize_text_field( wp_unslash( $_POST['host_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$lines = array( '' !== $host ? $host : __( 'Host not named', 'stubli' ) );
			foreach ( $results as $r ) {
				$lines[] = ( true === $r['ok'] ? '✓ ' : ( null === $r['ok'] ? '! ' : '✗ ' ) ) . $r['label'];
			}
			$note = array(
				'kind'     => 'host',
				'message'  => implode( "\n", $lines ),
				'reply_to' => home_url( '/' ),
				'device'   => mb_substr( 'PHP ' . PHP_VERSION . ' · ' . ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '' ), 0, 60 ),
				'version'  => VERSION,
				'source'   => 'app',
			);
			$sent = Feedback::is_hub() ? ( is_wp_error( Feedback::store( $note ) ) ? 'no' : 'yes' ) : ( is_wp_error( Feedback::forward_note( $note ) ) ? 'no' : 'yes' );
		}
		wp_safe_redirect( add_query_arg( array_filter( array( 'page' => Admin::PAGE, 'checked' => '1', 'sent' => $sent ) ), admin_url( 'options-general.php' ) ) . '#host-check' );
		exit;
	}

	/**
	 * The section on the settings screen.
	 */
	public static function render() {
		$results = get_transient( 'stubli_host_check_' . get_current_user_id() );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$sent = isset( $_GET['sent'] ) ? sanitize_key( wp_unslash( $_GET['sent'] ) ) : '';
		// phpcs:enable
		?>
		<h2 id="host-check"><?php esc_html_e( 'Is this host Stubli-ready?', 'stubli' ); ?></h2>
		<p><?php esc_html_e( 'Checks what Stubli needs from your web host, including whether other Stubli sites can reach yours. It takes a few seconds.', 'stubli' ); ?></p>
		<?php if ( 'yes' === $sent ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Thank you. The result is with the Stubli team and helps build the list of hosts that work.', 'stubli' ); ?></p></div>
		<?php elseif ( 'no' === $sent ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'The result couldn\'t be sent just now. Try again later.', 'stubli' ); ?></p></div>
		<?php endif; ?>
		<?php if ( is_array( $results ) ) : ?>
			<table class="widefat striped" style="max-width:760px">
				<tbody>
				<?php foreach ( $results as $r ) : ?>
					<tr>
						<td style="width:28px;font-size:18px" aria-hidden="true"><?php echo true === $r['ok'] ? '✓' : ( null === $r['ok'] ? '!' : '✗' ); ?></td>
						<td><strong><?php echo esc_html( $r['label'] ); ?></strong> <span class="screen-reader-text"><?php echo true === $r['ok'] ? esc_html__( 'OK', 'stubli' ) : ( null === $r['ok'] ? esc_html__( 'Worth a look', 'stubli' ) : esc_html__( 'Needs fixing', 'stubli' ) ); ?></span><br><span class="description"><?php echo esc_html( $r['help'] ); ?></span></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			$bad = count(
				array_filter(
					$results,
					static function ( $r ) {
						return false === $r['ok'];
					}
				)
			);
			?>
			<p><strong><?php echo 0 === $bad ? esc_html__( 'This host is Stubli-ready.', 'stubli' ) : esc_html( sprintf( /* translators: %d: number of problems */ _n( '%d thing needs fixing before Stubli works fully here.', '%d things need fixing before Stubli works fully here.', $bad, 'stubli' ), $bad ) ); ?></strong></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="stubli_host_check">
			<?php wp_nonce_field( 'stubli_host_check' ); ?>
			<p>
				<label for="og-host-name"><?php esc_html_e( 'Which host is this? (optional, e.g. Infomaniak or WordPress.com Business)', 'stubli' ); ?></label><br>
				<input id="og-host-name" name="host_name" type="text" class="regular-text" maxlength="80">
			</p>
			<p>
				<button type="submit" class="button"><?php echo is_array( $results ) ? esc_html__( 'Check again', 'stubli' ) : esc_html__( 'Run the check', 'stubli' ); ?></button>
				<button type="submit" class="button button-primary" name="send" value="1"><?php esc_html_e( 'Check and send the result to the Stubli team', 'stubli' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Sending shares the results above, your site\'s address, your Stubli and PHP versions and the host name you typed. Nothing else.', 'stubli' ); ?></p>
		</form>
		<?php
	}
}
