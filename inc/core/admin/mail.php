<?php
/**
 * Email delivery: who emails come from, and sending through an SMTP server
 * (an email provider such as Microsoft 365, Google Workspace, Brevo or Postmark)
 * so they don't land in spam. Settings are in Ex Files → Settings → Email; on the
 * live site the password is better kept in wp-config.php:
 *
 *   define( 'EXF_SMTP_HOST', 'smtp.example.com' );
 *   define( 'EXF_SMTP_PORT', 587 );
 *   define( 'EXF_SMTP_SECURE', 'tls' );   // tls, ssl or none
 *   define( 'EXF_SMTP_USER', 'hello@theexfiles.co.uk' );
 *   define( 'EXF_SMTP_PASS', '…' );
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * SMTP settings (constants win over the settings page).
 */
function exf_smtp_settings() {
	$get = function ( $const, $field, $default = '' ) {
		return defined( $const ) ? constant( $const ) : exf_setting( $field, $default );
	};
	return array(
		'host'   => trim( (string) $get( 'EXF_SMTP_HOST', 'smtp_host' ) ),
		'port'   => (int) $get( 'EXF_SMTP_PORT', 'smtp_port', 587 ),
		'secure' => (string) $get( 'EXF_SMTP_SECURE', 'smtp_secure', 'tls' ),
		'user'   => (string) $get( 'EXF_SMTP_USER', 'smtp_user' ),
		'pass'   => (string) $get( 'EXF_SMTP_PASS', 'smtp_pass' ),
	);
}

add_action(
	'phpmailer_init',
	function ( $mailer ) {
		$s = exf_smtp_settings();
		if ( '' === $s['host'] ) {
			return;
		}
		$mailer->isSMTP();
		$mailer->Host       = $s['host']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->Port       = $s['port'] ? $s['port'] : 587; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->SMTPSecure = in_array( $s['secure'], array( 'tls', 'ssl' ), true ) ? $s['secure'] : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->SMTPAutoTLS = 'none' !== $s['secure']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( '' !== $s['user'] ) {
			$mailer->SMTPAuth = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$mailer->Username = $s['user']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$mailer->Password = $s['pass']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
	}
);

add_filter(
	'wp_mail_from',
	function ( $from ) {
		$email = sanitize_email( (string) exf_setting( 'mail_from_email', '' ) );
		return is_email( $email ) ? $email : $from;
	}
);

add_filter(
	'wp_mail_from_name',
	function ( $name ) {
		$set = trim( (string) exf_setting( 'mail_from_name', '' ) );
		return '' !== $set ? $set : exf_site_name();
	}
);

// Replies go to the first notification address unless an email sets its own.
add_filter(
	'wp_mail',
	function ( $args ) {
		$headers = is_array( $args['headers'] ) ? $args['headers'] : array_filter( explode( "\n", (string) $args['headers'] ) );
		foreach ( $headers as $h ) {
			if ( 0 === stripos( trim( $h ), 'reply-to:' ) ) {
				return $args;
			}
		}
		$reply = sanitize_email( (string) exf_setting( 'mail_reply_to', '' ) );
		if ( is_email( $reply ) ) {
			$headers[]       = 'Reply-To: ' . $reply;
			$args['headers'] = $headers;
		}
		return $args;
	}
);

// Show the test button inside the settings tab.
add_filter(
	'acf/load_field/key=field_exf_set_mail_help',
	function ( $field ) {
		$s    = exf_smtp_settings();
		$test = wp_nonce_url( admin_url( 'admin-post.php?action=exf_mail_test' ), 'exf_mail_test' );
		$field['message'] = ( '' === $s['host']
			? __( 'Emails are currently sent by the web server, which some inboxes treat as spam. For the live site, enter your email provider’s SMTP details below (or put them in wp-config.php: see the README).', 'exfiles' )
			/* translators: %s: server */
			: sprintf( __( 'Emails are sent through %s.', 'exfiles' ), $s['host'] ) . ( defined( 'EXF_SMTP_HOST' ) ? ' ' . __( '(Set in wp-config.php.)', 'exfiles' ) : '' ) )
			. "\n\n" . '<a class="button" href="' . esc_url( $test ) . '">' . esc_html__( 'Send a test email to me', 'exfiles' ) . '</a> ' . esc_html__( '(save any changes first)', 'exfiles' );
		return $field;
	}
);

add_action(
	'admin_post_exf_mail_test',
	function () {
		check_admin_referer( 'exf_mail_test' );
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$error = '';
		$catch = function ( $e ) use ( &$error ) {
			$error = $e->get_error_message();
		};
		add_action( 'wp_mail_failed', $catch );
		$to   = wp_get_current_user()->user_email;
		$sent = wp_mail( $to, __( 'Test email from The Ex Files website', 'exfiles' ), __( 'If you can read this, the website can send emails.', 'exfiles' ) );
		remove_action( 'wp_mail_failed', $catch );
		set_transient( 'exf_mail_test_' . get_current_user_id(), $sent ? 'ok:' . $to : 'fail:' . $error, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'edit.php?post_type=ex_file&page=exf-settings' ) );
		exit;
	}
);

add_action(
	'admin_notices',
	function () {
		$r = get_transient( 'exf_mail_test_' . get_current_user_id() );
		if ( ! $r ) {
			return;
		}
		delete_transient( 'exf_mail_test_' . get_current_user_id() );
		list( $state, $detail ) = explode( ':', $r, 2 );
		if ( 'ok' === $state ) {
			/* translators: %s: email */
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Test email sent to %s. Check it arrived (and not in junk).', 'exfiles' ), $detail ) ) . '</p></div>';
		} else {
			/* translators: %s: error */
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'The test email could not be sent: %s', 'exfiles' ), $detail ? $detail : __( 'unknown error', 'exfiles' ) ) ) . '</p></div>';
		}
	}
);
