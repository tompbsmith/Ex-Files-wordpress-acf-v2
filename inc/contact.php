<?php
/**
 * Contact form handler (replaces the Vercel /api/contact + Resend function).
 *
 * Sends with wp_mail(). For reliable delivery install an SMTP plugin
 * (e.g. WP Mail SMTP), which can also use Resend's SMTP service.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_exf_contact', 'exf_handle_contact' );
add_action( 'wp_ajax_nopriv_exf_contact', 'exf_handle_contact' );

/**
 * Handle an AJAX contact form submission.
 */
function exf_handle_contact() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form; a nonce would break under page caching. Honeypot + rate limit instead.
	$honeypot = isset( $_POST['company'] ) ? trim( wp_unslash( $_POST['company'] ) ) : '';
	if ( '' !== $honeypot ) {
		wp_send_json_success(); // Quietly drop bots.
	}

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	// phpcs:enable

	if ( '' === $name || '' === $message || ! isset( $_POST['email'] ) || '' === trim( wp_unslash( $_POST['email'] ) ) ) { // phpcs:ignore
		wp_send_json_error( array( 'error' => __( 'Please fill in your name, email and message.', 'exfiles' ) ), 400 );
	}
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'error' => __( 'Please enter a valid email address.', 'exfiles' ) ), 400 );
	}

	// Simple rate limit: 5 messages per 10 minutes per IP.
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key   = 'exf_contact_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= 5 ) {
		wp_send_json_error( array( 'error' => __( 'Too many messages in a short time. Please try again later or email us directly.', 'exfiles' ) ), 429 );
	}
	set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );

	$to = exf_option( 'form_recipient', get_option( 'admin_email' ) );
	if ( ! is_email( $to ) ) {
		$to = get_option( 'admin_email' );
	}

	$site_name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$site_name  = $site_name ? $site_name : 'The Ex Files';
	$reply_name = trim( str_replace( array( '"', '<', '>' ), '', $name ) );

	/* translators: 1: sender name, 2: site name */
	$subject = sprintf( __( 'New enquiry from %1$s — %2$s website', 'exfiles' ), $name, $site_name );
	$lines   = array( 'Name: ' . $name, 'Email: ' . $email );
	if ( '' !== $phone ) {
		$lines[] = 'Phone: ' . $phone;
	}
	$lines[] = '';
	$lines[] = 'Message:';
	$lines[] = $message;

	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		sprintf( 'Reply-To: "%s" <%s>', $reply_name, $email ),
	);

	if ( wp_mail( $to, $subject, implode( "\n", $lines ), $headers ) ) {
		wp_send_json_success();
	}

	wp_send_json_error( array( 'error' => __( 'Something went wrong sending your message. Please try again or email us directly.', 'exfiles' ) ), 500 );
}
