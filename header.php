<?php
/**
 * Site header.
 *
 * @package exfiles
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="nav-inner">
		<a href="<?php echo esc_url( exf_url( '#top' ) ); ?>" class="logo-mark">
			<?php echo exf_logo_img( 'logo-img' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</a>
		<nav class="links" id="navLinks" aria-label="<?php esc_attr_e( 'Primary', 'exfiles' ); ?>">
			<?php exf_nav_links(); ?>
		</nav>
		<button class="burger" id="burger" aria-label="<?php esc_attr_e( 'Toggle menu', 'exfiles' ); ?>" aria-controls="navLinks" aria-expanded="false"><span></span><span></span><span></span></button>
	</div>
</header>

<main id="top">
