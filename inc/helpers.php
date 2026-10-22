<?php
/**
 * Template helpers.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether ACF (or Secure Custom Fields) with Flexible Content is available.
 */
function exf_acf_ready() {
	return function_exists( 'have_rows' ) && function_exists( 'acf_get_field_type' ) && acf_get_field_type( 'flexible_content' );
}

/**
 * Read a Site Settings value, safely when ACF is missing.
 *
 * @param string $name    Field name.
 * @param mixed  $default Fallback.
 */
function exf_option( $name, $default = '' ) {
	if ( ! function_exists( 'get_field' ) ) {
		return $default;
	}
	$value = get_field( $name, 'option' );
	return ( null === $value || '' === $value || false === $value ) ? $default : $value;
}

/**
 * Whether a page is built from sections (front page or the Sections template).
 *
 * @param int $post_id Page ID.
 */
function exf_is_sections_page( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || 'page' !== get_post_type( $post_id ) ) {
		return false;
	}
	return ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post_id )
		|| 'page-sections.php' === get_page_template_slug( $post_id );
}

/**
 * Render the flexible content sections for a page.
 *
 * @param int|null $post_id Page ID, defaults to the current page.
 * @return bool Whether any section was rendered.
 */
function exf_render_sections( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	if ( ! exf_acf_ready() ) {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<section><div class="wrap"><p>' . esc_html__( 'This page is built with ACF Flexible Content. Please install and activate ACF Pro or Secure Custom Fields.', 'exfiles' ) . '</p></div></section>';
		}
		return false;
	}

	$rendered = false;
	if ( have_rows( 'sections', $post_id ) ) {
		while ( have_rows( 'sections', $post_id ) ) {
			the_row();
			if ( get_sub_field( 'hide_section' ) ) {
				continue;
			}
			$layout = sanitize_key( get_row_layout() );
			if ( locate_template( 'template-parts/sections/' . $layout . '.php' ) ) {
				get_template_part( 'template-parts/sections/' . $layout );
				$rendered = true;
			}
		}
	}

	if ( ! $rendered && current_user_can( 'edit_post', $post_id ) ) {
		$setup = admin_url( 'themes.php?page=exf-setup' );
		echo '<section><div class="wrap"><p>' . wp_kses_post(
			sprintf(
				/* translators: 1: edit page URL, 2: setup URL */
				__( 'No sections yet. <a href="%1$s">Add sections to this page</a> or <a href="%2$s">import the default Ex Files content</a>.', 'exfiles' ),
				esc_url( get_edit_post_link( $post_id ) ),
				esc_url( $setup )
			)
		) . '</p></div></section>';
	}

	return $rendered;
}

/**
 * Print class and id attributes for the current section row.
 *
 * @param string $class Base class.
 */
function exf_section_attrs( $class ) {
	$id = sanitize_title( (string) get_sub_field( 'section_id' ) );
	echo 'class="' . esc_attr( $class ) . '"';
	if ( $id ) {
		echo ' id="' . esc_attr( $id ) . '"';
	}
	// "With motion?" is on unless it's been switched off (rows saved before the
	// switch existed have no value, so they keep their motion).
	if ( false === get_sub_field( 'motion' ) ) {
		echo ' data-motion="off"';
	}
}

/**
 * Escape text and turn new lines into <br>.
 *
 * @param string $text Text.
 */
function exf_lines( $text ) {
	return nl2br( esc_html( trim( (string) $text ) ), false );
}

/**
 * Make "#anchor" links work from pages other than the homepage.
 *
 * @param string $url URL.
 */
function exf_url( $url ) {
	$url = (string) $url;
	if ( '' !== $url && '#' === $url[0] && ! is_front_page() ) {
		return home_url( '/' . ( '#top' === $url ? '' : $url ) );
	}
	return $url;
}

/**
 * Print an ACF link field as a button.
 *
 * @param array|null $link  ACF link array.
 * @param string     $class CSS classes.
 */
function exf_button( $link, $class = 'btn' ) {
	if ( empty( $link['url'] ) ) {
		return;
	}
	$target = ! empty( $link['target'] ) ? $link['target'] : '';
	printf(
		'<a href="%1$s" class="%2$s"%3$s>%4$s</a>',
		esc_url( exf_url( $link['url'] ) ),
		esc_attr( $class ),
		$target ? ' target="' . esc_attr( $target ) . '" rel="noopener"' : '',
		esc_html( ! empty( $link['title'] ) ? $link['title'] : $link['url'] )
	);
}

/**
 * Logo <img>: Customizer logo, falling back to the bundled one.
 *
 * @param string $class   CSS class.
 * @param mixed  $override Optional ACF image array.
 */
function exf_logo_img( $class = 'logo-img', $override = null ) {
	$alt = get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'The Ex Files';
	$src = '';
	// Full-size file, no srcset: the logo is tiny on screen, so srcset would pick a blurry thumbnail.
	if ( ! empty( $override['ID'] ) ) {
		$src = wp_get_attachment_image_url( $override['ID'], 'full' );
	}
	if ( ! $src && get_theme_mod( 'custom_logo' ) ) {
		$src = wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' );
	}
	if ( ! $src ) {
		$src = EXF_URI . '/assets/img/logo.png';
	}
	return sprintf( '<img src="%s" alt="%s" class="%s">', esc_url( $src ), esc_attr( $alt ), esc_attr( $class ) );
}

/**
 * Print the primary nav links (falls back to the original three links).
 */
function exf_nav_links() {
	$locations = get_nav_menu_locations();
	$items     = ! empty( $locations['primary'] ) ? wp_get_nav_menu_items( $locations['primary'] ) : array();

	if ( empty( $items ) ) {
		$items = array(
			(object) array( 'title' => __( 'Home', 'exfiles' ), 'url' => '#top', 'target' => '', 'classes' => array(), 'menu_item_parent' => 0 ),
			(object) array( 'title' => __( 'Our Services', 'exfiles' ), 'url' => '#services', 'target' => '', 'classes' => array(), 'menu_item_parent' => 0 ),
			(object) array( 'title' => __( 'The Brains', 'exfiles' ), 'url' => '/the-brains/', 'target' => '', 'classes' => is_page( 'the-brains' ) ? array( 'current-menu-item' ) : array(), 'menu_item_parent' => 0 ),
			(object) array( 'title' => __( 'Open Your Ex File', 'exfiles' ), 'url' => '/open-your-ex-file/', 'target' => '', 'classes' => array(), 'menu_item_parent' => 0 ),
		);
	} else {
		_wp_menu_item_classes_by_context( $items );
	}

	foreach ( $items as $item ) {
		if ( ! empty( $item->menu_item_parent ) ) {
			continue;
		}
		$classes = (array) $item->classes;
		$active  = in_array( 'current-menu-item', $classes, true ) || ( '#top' === $item->url && is_front_page() );
		printf(
			'<a href="%1$s"%2$s%3$s>%4$s</a>',
			esc_url( exf_url( $item->url ) ),
			$active ? ' class="active" aria-current="page"' : '',
			! empty( $item->target ) ? ' target="' . esc_attr( $item->target ) . '" rel="noopener"' : '',
			esc_html( $item->title )
		);
	}
	do_action( 'exf_after_nav_links' );
}

/**
 * Built-in trust icons.
 *
 * @param string $name Icon name.
 */
function exf_icon( $name ) {
	$c     = '#f1e9de';
	$icons = array(
		'shield'   => '<path d="M12 2L4 5V11C4 16 7.5 20.5 12 22C16.5 20.5 20 16 20 11V5L12 2Z" stroke="' . $c . '" stroke-width="1.6" stroke-linejoin="round"/><path d="M8.5 12L11 14.5L15.5 9.5" stroke="' . $c . '" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
		'report'   => '<rect x="5" y="3" width="12" height="18" rx="1" stroke="' . $c . '" stroke-width="1.6"/><path d="M8 8H14M8 12H14M8 16H11" stroke="' . $c . '" stroke-width="1.6" stroke-linecap="round"/><circle cx="17" cy="17" r="3.2" stroke="' . $c . '" stroke-width="1.6"/><path d="M19.3 19.3L21.5 21.5" stroke="' . $c . '" stroke-width="1.6" stroke-linecap="round"/>',
		'document' => '<path d="M7 2H14L18 6V22H7V2Z" stroke="' . $c . '" stroke-width="1.6" stroke-linejoin="round"/><path d="M14 2V6H18" stroke="' . $c . '" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 12H15.5M9.5 15.5H15.5M9.5 18.5H13" stroke="' . $c . '" stroke-width="1.6" stroke-linecap="round"/>',
	);
	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}
	return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' . $icons[ $name ] . '</svg>';
}

/**
 * Social network icons for the footer.
 *
 * @param string $network instagram|linkedin|facebook.
 */
function exf_social_icon( $network ) {
	$paths = array(
		'instagram' => 'M12 2.2c3.2 0 3.6 0 4.85.07 1.17.05 1.97.24 2.43.4a4.9 4.9 0 0 1 1.77 1.15 4.9 4.9 0 0 1 1.15 1.77c.16.46.35 1.26.4 2.43.06 1.25.07 1.65.07 4.85s0 3.6-.07 4.85c-.05 1.17-.24 1.97-.4 2.43a4.9 4.9 0 0 1-1.15 1.77 4.9 4.9 0 0 1-1.77 1.15c-.46.16-1.26.35-2.43.4-1.25.06-1.65.07-4.85.07s-3.6 0-4.85-.07c-1.17-.05-1.97-.24-2.43-.4a4.9 4.9 0 0 1-1.77-1.15 4.9 4.9 0 0 1-1.15-1.77c-.16-.46-.35-1.26-.4-2.43C2.2 15.6 2.2 15.2 2.2 12s0-3.6.07-4.85c.05-1.17.24-1.97.4-2.43a4.9 4.9 0 0 1 1.15-1.77A4.9 4.9 0 0 1 5.6 1.8c.46-.16 1.26-.35 2.43-.4C9.28 1.35 9.68 1.34 12 1.34zm0 1.98c-3.15 0-3.52 0-4.76.07-1.03.04-1.6.22-1.97.36-.5.19-.86.42-1.23.8-.38.37-.61.73-.8 1.23-.14.37-.32.94-.36 1.97-.06 1.24-.07 1.6-.07 4.76s0 3.52.07 4.76c.04 1.03.22 1.6.36 1.97.19.5.42.86.8 1.23.37.38.73.61 1.23.8.37.14.94.32 1.97.36 1.24.06 1.6.07 4.76.07s3.52 0 4.76-.07c1.03-.04 1.6-.22 1.97-.36.5-.19.86-.42 1.23-.8.38-.37.61-.73.8-1.23.14-.37.32-.94.36-1.97.06-1.24.07-1.6.07-4.76s0-3.52-.07-4.76c-.04-1.03-.22-1.6-.36-1.97a3.3 3.3 0 0 0-.8-1.23 3.3 3.3 0 0 0-1.23-.8c-.37-.14-.94-.32-1.97-.36-1.24-.07-1.61-.07-4.76-.07zm0 3.4a5.42 5.42 0 1 1 0 10.84A5.42 5.42 0 0 1 12 7.58zm0 1.98a3.44 3.44 0 1 0 0 6.88 3.44 3.44 0 0 0 0-6.88zm5.65-2.17a1.27 1.27 0 1 1-2.53 0 1.27 1.27 0 0 1 2.53 0z',
		'linkedin'  => 'M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9h4v12H3V9zm7 0h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.78 2.65 4.78 6.1V21h-4v-5.6c0-1.34-.02-3.06-1.86-3.06-1.87 0-2.16 1.46-2.16 2.96V21h-4V9z',
		'facebook'  => 'M13.35 21v-8.2h2.7l.4-3.3h-3.1V7.4c0-.95.5-1.4 1.5-1.4h1.7V3.1c-.3 0-1.3-.1-2.2-.1-2.7 0-4.3 1.6-4.3 4.5v2h-2.6v3.3h2.6V21h3.3z',
	);
	return isset( $paths[ $network ] ) ? '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . $paths[ $network ] . '"/></svg>' : '';
}
