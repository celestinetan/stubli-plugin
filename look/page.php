<?php
/**
 * The page the built-in look draws, whatever theme is installed.
 *
 * @package Stubli
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'stubli-look' ); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'stubli' ); ?></a>
<?php stubli_print_view( stubli_view_name( \Stubli\Look::view() ) ); ?>
<?php wp_footer(); ?>
</body>
</html>
