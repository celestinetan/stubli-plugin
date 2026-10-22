<?php
/**
 * A simple Settings → Stubli screen in wp-admin, until the app's own
 * Settings screen exists.
 *
 * @package Stubli
 */

namespace Stubli;

defined( 'ABSPATH' ) || exit;

/**
 * Lets the owner change Stubli's privacy and comment settings from the
 * dashboard. Uses the same Settings class as the REST API.
 */
class Admin {

	const PAGE = 'stubli';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_stubli_save_settings', array( self::class, 'save' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( STUBLI_PLUGIN_FILE ), array( self::class, 'action_link' ) );
	}

	/**
	 * Add the menu item.
	 */
	public static function menu() {
		add_options_page( __( 'Stubli', 'stubli' ), __( 'Stubli', 'stubli' ), 'manage_options', self::PAGE, array( self::class, 'render' ) );
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'stubli' ) . '</a>' );
		return $links;
	}

	/**
	 * Render the screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = Settings::get();
		$saved = isset( $_GET['saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$row   = static function ( $name, $label, $help, $checked ) {
			printf(
				'<tr><th scope="row">%2$s</th><td><label><input type="checkbox" name="%1$s" value="1" %4$s> %3$s</label></td></tr>',
				esc_attr( $name ),
				esc_html( $label ),
				esc_html( $help ),
				checked( $checked, true, false )
			);
		};
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Stubli', 'stubli' ); ?></h1>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'stubli' ); ?></p></div>
			<?php endif; ?>
			<?php
			$helper = isset( $_GET['helper'] ) ? sanitize_key( wp_unslash( $_GET['helper'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'ok' === $helper && Helper::active() ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Webmention is on. Comments can now travel between sites.', 'stubli' ); ?></p></div>
			<?php elseif ( ! Helper::active() ) : ?>
				<div class="notice notice-warning">
					<p><strong><?php esc_html_e( 'One piece is missing', 'stubli' ); ?></strong> · <?php esc_html_e( 'Comments and guest book entries travel between sites with the free Webmention plugin from WordPress.org.', 'stubli' ); ?></p>
					<?php if ( $helper ) : ?>
						<p><?php /* translators: %s: link to the Plugins screen */ printf( esc_html__( 'That didn\'t work here. You can add it yourself: %s.', 'stubli' ), '<a href="' . esc_url( Helper::manual_url() ) . '">' . esc_html__( 'open Webmention in Plugins', 'stubli' ) . '</a>' ); ?></p>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 10px">
						<input type="hidden" name="action" value="stubli_add_webmention">
						<?php wp_nonce_field( 'stubli_add_webmention' ); ?>
						<button type="submit" class="button button-primary"><?php echo esc_html( 'installed' === Helper::state() ? __( 'Switch on Webmention', 'stubli' ) : __( 'Add Webmention', 'stubli' ) ); ?></button>
					</form>
				</div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="stubli_save_settings">
				<?php wp_nonce_field( 'stubli_save_settings' ); ?>
				<h2><?php esc_html_e( 'Look', 'stubli' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					if ( 'stubli' === get_option( 'stylesheet' ) ) {
						echo '<tr><th scope="row">' . esc_html__( 'Stubli look', 'stubli' ) . '</th><td>' . esc_html__( 'Your site uses the Stubli theme.', 'stubli' ) . '</td></tr>';
					} else {
						$row( 'look', __( 'Stubli look', 'stubli' ), __( 'Show your site in the Stubli design (recommended). Turn off to keep your current theme.', 'stubli' ), $s['look'] );
					}
					$row( 'credit', __( 'Made with Stubli', 'stubli' ), __( 'Show a small "Made with Stubli · Make your own" line below your photos and in your footer, so visitors can find out how to get their own. Off unless you turn it on.', 'stubli' ), $s['credit'] );
					?>
				</table>
				<h2><?php esc_html_e( 'Privacy', 'stubli' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$row( 'search_engines', __( 'Search engines', 'stubli' ), __( 'Let Google and other search engines find this site', 'stubli' ), $s['search_engines'] );
					$row( 'block_ai', __( 'AI crawlers', 'stubli' ), __( 'Ask AI crawlers to stay away (a request respectable bots honour)', 'stubli' ), $s['block_ai'] );
					?>
				</table>
				<h2><?php esc_html_e( 'Comments and guest book', 'stubli' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php $row( 'on_break', __( 'Take a break', 'stubli' ), __( 'Pause comments and the guest book. Nothing is deleted.', 'stubli' ), $s['on_break'] ); ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Who can comment and sign', 'stubli' ); ?></th>
						<td>
							<label><input type="radio" name="who_can_comment" value="friends_of_friends" <?php checked( $s['who_can_comment'], 'friends_of_friends' ); ?>> <?php esc_html_e( 'People you follow and people they follow show straight away; everyone else waits for your OK', 'stubli' ); ?></label><br>
							<label><input type="radio" name="who_can_comment" value="following" <?php checked( $s['who_can_comment'], 'following' ); ?>> <?php esc_html_e( 'Only people you follow show straight away', 'stubli' ); ?></label>
						</td>
					</tr>
				</table>
				<h2><?php esc_html_e( 'Your profile', 'stubli' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="og-pronouns"><?php esc_html_e( 'Pronouns', 'stubli' ); ?></label></th>
						<td><input id="og-pronouns" type="text" name="pronouns" value="<?php echo esc_attr( $s['pronouns'] ); ?>" maxlength="30" class="regular-text"><p class="description"><?php esc_html_e( 'Optional, shown next to your name. Leave empty to show nothing.', 'stubli' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="og-bio"><?php esc_html_e( 'Bio', 'stubli' ); ?></label></th>
						<td><textarea id="og-bio" name="bio" rows="3" maxlength="300" class="large-text"><?php echo esc_textarea( (string) get_option( 'stubli_bio', '' ) ); ?></textarea><p class="description"><?php esc_html_e( 'A few lines about you, shown on your profile card. Links go in My links below.', 'stubli' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="og-links"><?php esc_html_e( 'My links', 'stubli' ); ?></label></th>
						<td><textarea id="og-links" name="links" rows="4" class="large-text code" placeholder="Shop | https://example.com"><?php echo esc_textarea( (string) get_option( 'stubli_links', '' ) ); ?></textarea><p class="description"><?php esc_html_e( 'One per line: a name, a | and the address. Shown in your Links tab.', 'stubli' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'stubli' ) ); ?>
			</form>
			<hr>
			<?php HostCheck::render(); ?>
		</div>
		<?php
	}

	/**
	 * Save the form.
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only the site owner can change these settings.', 'stubli' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'stubli_save_settings' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$changes = array(
				'search_engines'  => ! empty( $_POST['search_engines'] ),
				'credit'          => ! empty( $_POST['credit'] ),
				'block_ai'        => ! empty( $_POST['block_ai'] ),
				'on_break'        => ! empty( $_POST['on_break'] ),
				'who_can_comment' => isset( $_POST['who_can_comment'] ) ? sanitize_key( wp_unslash( $_POST['who_can_comment'] ) ) : 'friends_of_friends',
				'pronouns'        => isset( $_POST['pronouns'] ) ? sanitize_text_field( wp_unslash( $_POST['pronouns'] ) ) : '',
		);
		if ( 'stubli' !== get_option( 'stylesheet' ) ) {
			$changes['look'] = ! empty( $_POST['look'] );
		}
		Settings::update( $changes );
		update_option( 'stubli_bio', isset( $_POST['bio'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ), 0, 300 ) : '' );
		$links = array();
		foreach ( preg_split( '/\r?\n/', isset( $_POST['links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['links'] ) ) : '' ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 === count( $parts ) && '' !== $parts[0] ) {
				$url = esc_url_raw( $parts[1], array( 'http', 'https' ) );
				if ( $url ) {
					$links[] = sanitize_text_field( $parts[0] ) . ' | ' . $url;
				}
			}
		}
		update_option( 'stubli_links', implode( "\n", array_slice( $links, 0, 20 ) ) );
		// phpcs:enable
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE . '&saved=1' ) );
		exit;
	}
}
