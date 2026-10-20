<?php
/**
 * Small security measures that don't change how the site works for anyone.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

// Don't advertise the WordPress version.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// XML-RPC isn't used, and is a common target for password guessing.
add_filter( 'xmlrpc_enabled', '__return_false' );
// xmlrpc_enabled only stops the login methods; refuse the whole endpoint (it still listed its methods).
add_action(
	'init',
	function () {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && apply_filters( 'exf_block_xmlrpc', true ) ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'XML-RPC is not available on this site.';
			exit;
		}
	},
	0
);
add_filter(
	'wp_headers',
	function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);

// Don't let visitors list usernames (?author=1, or the REST API's users list).
add_action(
	'template_redirect',
	function () {
		if ( is_author() || ( isset( $_GET['author'] ) && ! is_admin() ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	1
);
add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( ! current_user_can( 'list_users' ) ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}
);

// The same message for a wrong username or a wrong password on wp-login.php.
add_filter(
	'login_errors',
	function ( $error ) {
		global $errors;
		$codes = is_wp_error( $errors ) ? $errors->get_error_codes() : array();
		if ( array_intersect( $codes, array( 'invalid_username', 'invalid_email', 'incorrect_password' ) ) ) {
			return __( '<strong>Error:</strong> That username or password is not right.', 'exfiles' );
		}
		return $error;
	}
);

// Security headers on every page.
add_action(
	'send_headers',
	function () {
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
	}
);

// Theme and plugin file editors in wp-admin are off, unless wp-config.php says otherwise.
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}
