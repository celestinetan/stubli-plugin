<?php
/**
 * "From your own Stubli": a visitor on someone else's Stubli enters their
 * address and lands here, on their own dashboard, to follow, comment or sign.
 * WordPress asks them to log in first if they aren't.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * The stubli-act screen.
 */
class Act {

	const PAGE = 'stubli-act';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_stubli_act', array( self::class, 'handle' ) );
	}

	/**
	 * A hidden screen (no menu item).
	 */
	public static function menu() {
		add_submenu_page( 'options.php', __( 'From your Stubli', 'stubli' ), '', 'publish_posts', self::PAGE, array( self::class, 'render' ) );
	}

	/**
	 * The page it's about, if it's a real web address on another site.
	 *
	 * @param string $raw Raw URL.
	 * @return string
	 */
	private static function target( $raw ) {
		$url = esc_url_raw( trim( (string) $raw ) );
		if ( ! $url || ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Render the screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return;
		}
		self::body( false );
	}

	/**
	 * The screen's content, in the dashboard or inside the Stubli app.
	 *
	 * @param bool $app Drawn inside the app (/app/?act=1).
	 */
	public static function body( $app ) {
		$btn = $app ? 'a-btn' : 'button button-primary';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only; the form below carries a nonce.
		$do     = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : 'comment';
		$target = self::target( isset( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ) ) : '' );
		$done   = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
		$error  = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		$invite = isset( $_GET['invite'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_GET['invite'] ) ) ) : '';
		$pidx   = isset( $_GET['p'] ) && '' !== $_GET['p'] ? (int) $_GET['p'] : -1;
		// phpcs:enable
		$do    = in_array( $do, array( 'follow', 'comment', 'sign', 'accept', 'note' ), true ) ? $do : 'comment';
		$quote = ( 'note' === $do && $target && $pidx >= 0 && ! isset( $_GET['done'] ) ) ? Notes::their_prompt( $target, $pidx ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'accept' === $do && '' === $invite ) {
			$do = 'follow';
		}
		$host = $target ? (string) wp_parse_url( $target, PHP_URL_HOST ) : '';
		?>
		<div class="<?php echo $app ? 'a-compose a-act' : 'wrap'; ?>" style="max-width:640px">
			<?php if ( ! $target ) : ?>
				<h1><?php esc_html_e( 'Nothing to do here', 'stubli' ); ?></h1>
				<p><?php esc_html_e( 'This link is missing the page it\'s about. Go back and try the button again.', 'stubli' ); ?></p>
			<?php elseif ( $done ) : ?>
				<h1><?php echo 'accept' === $do ? ( 'both' === $done ? esc_html__( 'You\'re following each other', 'stubli' ) : esc_html__( 'You\'re following them', 'stubli' ) ) : ( 'follow' === $do ? esc_html__( 'You\'re following them', 'stubli' ) : ( 'note' === $do ? esc_html__( 'Note sent', 'stubli' ) : esc_html__( 'Sent', 'stubli' ) ) ); ?></h1>
				<p>
					<?php
					if ( 'accept' === $do && 'both' === $done ) {
						/* translators: %s: site */
						printf( esc_html__( 'New photos from %s will show in your feed, and yours in theirs.', 'stubli' ), esc_html( $host ) );
					} elseif ( 'accept' === $do ) {
						/* translators: %s: site */
						printf( esc_html__( 'New photos from %s will show in your feed. Their site couldn\'t follow you back just now; they can follow you from your page.', 'stubli' ), esc_html( $host ) );
					} elseif ( 'follow' === $do ) {
						/* translators: %s: site */
						printf( esc_html__( 'New photos from %s will show in your feed.', 'stubli' ), esc_html( $host ) );
					} elseif ( 'paused' === $done ) {
						esc_html_e( 'They\'re taking a break, so Stubli will keep sending it and it shows once they\'re back.', 'stubli' );
					} else {
						esc_html_e( 'If they follow you, it shows right away. Otherwise it waits for their OK.', 'stubli' );
					}
					?>
				</p>
				<p><a class="<?php echo esc_attr( $btn ); ?>" href="<?php echo esc_url( $target ); ?>"><?php /* translators: %s: site */ printf( esc_html__( 'Back to %s', 'stubli' ), esc_html( $host ) ); ?></a></p>
			<?php else : ?>
				<h1>
					<?php
					if ( 'accept' === $do ) {
						/* translators: %s: site */
						printf( esc_html__( 'Follow each other with %s?', 'stubli' ), esc_html( $host ) );
					} elseif ( 'follow' === $do ) {
						/* translators: %s: site */
						printf( esc_html__( 'Follow %s?', 'stubli' ), esc_html( $host ) );
					} elseif ( 'sign' === $do ) {
						/* translators: %s: site */
						printf( esc_html__( 'Sign %s\'s guest book', 'stubli' ), esc_html( $host ) );
					} elseif ( 'note' === $do ) {
						/* translators: %s: site */
						printf( esc_html__( 'Write to %s', 'stubli' ), esc_html( $host ) );
					} else {
						/* translators: %s: site */
						printf( esc_html__( 'Comment on %s', 'stubli' ), esc_html( $host ) );
					}
					?>
				</h1>
				<p><a href="<?php echo esc_url( $target ); ?>"><?php echo esc_html( $target ); ?></a></p>
				<?php if ( $quote ) : ?>
					<blockquote class="<?php echo $app ? 'a-quote' : ''; ?>"><strong><?php echo esc_html( $quote['q'] ); ?></strong><br><?php echo esc_html( $quote['a'] ); ?></blockquote>
				<?php endif; ?>
				<?php if ( 'note' === $do ) : ?>
					<p><?php esc_html_e( 'A private note: only they see it. If they follow you, it arrives straight away; otherwise it waits for their OK.', 'stubli' ); ?></p>
				<?php endif; ?>
				<?php if ( 'accept' === $do ) : ?>
					<p><?php /* translators: %s: site */ printf( esc_html__( 'You\'ll follow %s, and their site will follow you back. Either of you can stop following any time.', 'stubli' ), esc_html( $host ) ); ?></p>
				<?php endif; ?>
				<?php if ( $error ) : ?>
					<div class="<?php echo $app ? 'a-notice' : 'notice notice-error'; ?>" role="alert"><p><?php echo esc_html( $error ); ?></p></div>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="stubli_act">
					<input type="hidden" name="do" value="<?php echo esc_attr( $do ); ?>">
					<input type="hidden" name="url" value="<?php echo esc_url( $target ); ?>">
					<input type="hidden" name="invite" value="<?php echo esc_attr( $invite ); ?>">
					<input type="hidden" name="p" value="<?php echo (int) $pidx; ?>">
					<?php wp_nonce_field( 'stubli_act' ); ?>
					<?php if ( $app ) : ?>
						<input type="hidden" name="from" value="app">
					<?php endif; ?>
					<?php if ( 'follow' !== $do && 'accept' !== $do ) : ?>
						<p>
							<label for="og-act-text"><strong><?php echo 'sign' === $do ? esc_html__( 'Your message', 'stubli' ) : ( 'note' === $do ? esc_html__( 'Your note', 'stubli' ) : esc_html__( 'Your comment', 'stubli' ) ); ?></strong></label><br>
							<textarea id="og-act-text" name="text" rows="5" class="<?php echo $app ? 'a-textarea' : 'large-text'; ?>" maxlength="1000" required></textarea>
						</p>
						<?php if ( 'note' !== $do ) : ?>
						<p class="description"><?php esc_html_e( 'It\'s saved on your own site too, and you can delete it any time.', 'stubli' ); ?></p>
					<?php endif; ?>
					<?php endif; ?>
					<p><button type="submit" id="submit" class="<?php echo esc_attr( $btn ); ?>"><?php echo esc_html( 'accept' === $do ? __( 'Follow each other', 'stubli' ) : ( 'follow' === $do ? __( 'Follow', 'stubli' ) : ( 'sign' === $do ? __( 'Sign the guest book', 'stubli' ) : ( 'note' === $do ? __( 'Send note', 'stubli' ) : __( 'Send comment', 'stubli' ) ) ) ) ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Do it.
	 */
	public static function handle() {
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_die( esc_html__( 'Only the site owner can do this.', 'stubli' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'stubli_act' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$do     = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$target = self::target( isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '' );
		$text   = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
		$invite = isset( $_POST['invite'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_POST['invite'] ) ) ) : '';
		// phpcs:enable
		$back = array(
			'page' => self::PAGE,
			'do'   => $do,
			'url'  => rawurlencode( $target ),
		);
		$from_app = ! empty( $_POST['from'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		$base     = $from_app ? home_url( '/app/' ) : admin_url( 'admin.php' );
		if ( $from_app ) {
			unset( $back['page'] );
			$back = array( 'act' => '1' ) + $back;
		}
		if ( ! $target ) {
			wp_safe_redirect( add_query_arg( $back, $base ) );
			exit;
		}
		if ( 'accept' === $do && '' !== $invite ) {
			$back['invite'] = $invite;
			$result         = Invites::accept_here( $target, $invite );
		} elseif ( 'follow' === $do ) {
			$result = Follows::add( $target );
		} elseif ( 'note' === $do ) {
			$result = Notes::send( $target, $text, isset( $_POST['p'] ) ? (int) $_POST['p'] : -1 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		} elseif ( '' === trim( $text ) ) {
			$result = new \WP_Error( 'stubli_empty', __( 'Write something first.', 'stubli' ) );
		} else {
			$result = Replies::comment_on_url( $target, $text );
		}
		if ( is_wp_error( $result ) ) {
			$back['error'] = rawurlencode( $result->get_error_message() );
		} else {
			$back['done'] = ( is_array( $result ) && isset( $result['status'] ) && 'paused' === $result['status'] ) ? 'paused' : '1';
			if ( 'accept' === $do ) {
				$back['done'] = ! empty( $result['followed_back'] ) ? 'both' : 'one';
			}
		}
		wp_safe_redirect( add_query_arg( $back, $base ) );
		exit;
	}
}
