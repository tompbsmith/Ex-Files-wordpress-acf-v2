<?php
/**
 * Ex Files options pages and settings helpers.
 *
 * Each options page stores its values under its own ID ("exf_settings",
 * "exf_guide"), separate from the theme's Site Settings, so field names can't clash.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_SETTINGS_ID = 'exf_settings';
const EXF_GUIDE_ID    = 'exf_guide';

add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
			return;
		}
		acf_add_options_sub_page(
			array(
				'page_title'  => __( 'Ex Files Settings', 'exfiles' ),
				'menu_title'  => __( 'Settings', 'exfiles' ),
				'menu_slug'   => 'exf-settings',
				'parent_slug' => 'edit.php?post_type=ex_file',
				'capability'  => EXF_CAP,
				'post_id'     => EXF_SETTINGS_ID,
				'autoload'    => true,
			)
		);
		acf_add_options_sub_page(
			array(
				'page_title'  => __( 'Divorce Guide Content', 'exfiles' ),
				'menu_title'  => __( 'Divorce Guide Content', 'exfiles' ),
				'menu_slug'   => 'exf-guide-content',
				'parent_slug' => 'edit.php?post_type=ex_file',
				'capability'  => EXF_CAP,
				'post_id'     => EXF_GUIDE_ID,
				'autoload'    => false,
			)
		);
	}
);

/**
 * Read an Ex Files setting.
 *
 * @param string $name    Field name.
 * @param mixed  $default Fallback when empty or ACF is missing.
 */
function exf_setting( $name, $default = '' ) {
	return exf_get_option_field( $name, EXF_SETTINGS_ID, $default );
}

/**
 * Whether the "email me about your services" tick-boxes are shown. Hidden by
 * default until marketing emails are actually sent.
 */
function exf_marketing_optin() {
	return 'on' === exf_setting( 'marketing_optin', 'off' );
}

/**
 * Read a field from one of the Ex Files options pages.
 *
 * @param string $name    Field name.
 * @param string $id      Options page post ID.
 * @param mixed  $default Fallback.
 */
function exf_get_option_field( $name, $id, $default = '' ) {
	if ( ! function_exists( 'get_field' ) ) {
		return $default;
	}
	$value = get_field( $name, $id );
	return ( null === $value || '' === $value || false === $value || array() === $value ) ? $default : $value;
}

/**
 * Email addresses that receive Ex Files notifications.
 *
 * @return string[]
 */
function exf_notify_emails() {
	$raw    = (string) exf_setting( 'notify_emails', get_option( 'admin_email' ) );
	$emails = array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', $raw ) ), 'is_email' );
	return $emails ? array_values( $emails ) : array( get_option( 'admin_email' ) );
}

/**
 * Site name for emails and PDFs.
 */
function exf_site_name() {
	$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	return $name ? $name : 'The Ex Files';
}

/*
 * UK time (client meeting, October 2026: the activity log was an hour out during
 * BST). Times are stored in UTC and shown in the site's time zone, so a site set to
 * a fixed offset such as "UTC+0" (WordPress's default) shows UTC all year, which is
 * an hour behind in summer, for the activity log, emails and booking times alike.
 * The firm is in England, so a site left on a fixed offset is switched to London
 * time once. A named time zone someone has chosen is left alone.
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'exf_timezone_checked' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_option( 'exf_timezone_checked', time(), false );
		if ( '' === (string) get_option( 'timezone_string' ) ) {
			$was = (float) get_option( 'gmt_offset' );
			update_option( 'timezone_string', 'Europe/London' );
			update_option( 'exf_timezone_changed_from', $was, false );
			if ( function_exists( 'exf_audit' ) ) {
				exf_audit( 'settings', sprintf( 'Set the site time zone to London (was a fixed offset of UTC%+g), so times show in UK time all year.', $was ) );
			}
		}
	}
);

add_action(
	'admin_notices',
	function () {
		$was = get_option( 'exf_timezone_changed_from', null );
		if ( null === $was || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_option( 'exf_timezone_changed_from' );
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'The site’s time zone was set to a fixed offset, so times were an hour out during British Summer Time. It’s now set to London (Settings → General → Timezone), so times show in UK time all year.', 'exfiles' ) . '</p></div>';
	}
);
