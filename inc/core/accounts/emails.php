<?php
/**
 * Account emails. Wording is editable in Ex Files → Email Copy; empty fields use
 * the defaults below.
 *
 * Client emails never say why an account was blocked or that a conflict was found:
 * that would tell them their ex is (or was) a client.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_EMAILS_ID = 'exf_emails';

add_action(
	'acf/init',
	function () {
		if ( function_exists( 'acf_add_options_sub_page' ) ) {
			acf_add_options_sub_page(
				array(
					'page_title'  => __( 'Email Copy', 'exfiles' ),
					'menu_title'  => __( 'Email Copy', 'exfiles' ),
					'menu_slug'   => 'exf-email-copy',
					'parent_slug' => 'edit.php?post_type=ex_file',
					'capability'  => EXF_CAP,
					'post_id'     => EXF_EMAILS_ID,
					'autoload'    => false,
				)
			);
		}
	}
);

/**
 * Default subject and body for each email. Keys match the Email Copy fields
 * ({key}_subject, {key}_body).
 */
function exf_email_defaults() {
	return array(
		'verify'         => array(
			'Confirm your email to open your Ex File',
			"Hello {first_name},\n\nThank you for opening your Ex File with The Ex Files. Please confirm your email address using this link:\n\n{link}\n\nThe link works for 48 hours. If you didn't sign up, you can ignore this email.\n\nBest wishes,\nThe Ex Files",
		),
		'active'         => array(
			"You're all set",
			"Hello {first_name},\n\nYour Ex File is open and you can now choose your service:\n\n{link}\n\nBest wishes,\nThe Ex Files",
		),
		'review'         => array(
			"We're just checking a few details",
			"Hello {first_name},\n\nThank you for confirming your email. Before we can act for you, we need to complete a routine check, which we do by hand for some new clients. This is usually done within one working day, and we'll email you as soon as it's finished.\n\nYou can log in to your Ex File in the meantime:\n\n{link}\n\nBest wishes,\nThe Ex Files",
		),
		'info_requested' => array(
			'We need a little more information',
			"Hello {first_name},\n\nTo finish our routine check, we need a little more information:\n\n{question}\n\nPlease log in to your Ex File to reply:\n\n{link}\n\nBest wishes,\nThe Ex Files",
		),
		'unable'         => array(
			'About your Ex File',
			"Hello {first_name},\n\nThank you for your interest in The Ex Files. Unfortunately we're unable to act for you on this matter. We're sorry we can't help on this occasion.\n\nIf you have any questions, please reply to this email.\n\nBest wishes,\nThe Ex Files",
		),
		'admin_review'   => array(
			'Conflict check needs review: {client_name}',
			"A new Ex File needs a manual conflict check.\n\nClient: {client_name}\nEx: {ex_name}\nScore: {score}\n\nReview it here:\n{admin_link}",
		),
		'admin_blocked'  => array(
			'Account blocked by conflict check: {client_name}',
			"The conflict check blocked a new Ex File automatically.\n\nClient: {client_name}\nEx: {ex_name}\nScore: {score}\n\nThe client was told only that you're unable to act. If this is a false match, you can clear it here:\n{admin_link}",
		),
		'order_confirmed'  => array(
			'Confirmed: {service}',
			"Hello {first_name},\n\nThank you. Here are the details:\n\nService: {service}\nAmount: {amount}\nAppointment: {appointment}\n\n{meeting_details}\n\n{next_steps}\n\nYou can see everything in your Ex File:\n{link}\n\nStripe will email you a separate receipt for any payment.\n\nBest wishes,\nThe Ex Files",
		),
		'booking_reminder' => array(
			'Reminder: your appointment tomorrow',
			"Hello {first_name},\n\nA reminder that your {service} appointment is on {appointment}.\n\n{meeting_details}\n\nIf you need to change it, please reply to this email.\n\nBest wishes,\nThe Ex Files",
		),
		'admin_order'      => array(
			'New order: {service} for {client_name}',
			"{client_name} has ordered {service} ({amount}).\n\nEx File: {client_name} / {ex_name}\nAppointment: {appointment}\n\nOrder details:\n{admin_link}",
		),
		'admin_info'     => array(
			'More information received: {client_name}',
			"{client_name} has replied to your request for more information, and the conflict check has been run again (score {score}).\n\nReview it here:\n{admin_link}",
		),
	);
}

/**
 * Send one of the account emails.
 *
 * @param string          $key  Email key from exf_email_defaults().
 * @param string|string[] $to   Recipient(s).
 * @param array           $vars Merge tags without braces.
 * @return bool
 */
function exf_send_email( $key, $to, array $vars = array() ) {
	$defaults = exf_email_defaults();
	if ( ! isset( $defaults[ $key ] ) ) {
		return false;
	}
	$subject = (string) exf_get_option_field( $key . '_subject', EXF_EMAILS_ID, $defaults[ $key ][0] );
	$body    = (string) exf_get_option_field( $key . '_body', EXF_EMAILS_ID, $defaults[ $key ][1] );

	$vars += array( 'site_name' => exf_site_name() );
	$tags  = array();
	foreach ( $vars as $k => $v ) {
		$tags[ '{' . $k . '}' ] = (string) $v;
	}

	return (bool) wp_mail(
		$to,
		wp_strip_all_tags( strtr( $subject, $tags ) ),
		strtr( $body, $tags ),
		array( 'Content-Type: text/plain; charset=UTF-8' )
	);
}

/**
 * Emails that go with a conflict check outcome.
 *
 * @param int    $ex_file_id Ex File ID.
 * @param string $outcome    clear|review|blocked.
 */
function exf_conflict_notify( $ex_file_id, $outcome ) {
	$client_id = (int) get_post_field( 'post_author', $ex_file_id );
	$user      = get_userdata( $client_id );
	if ( ! $user ) {
		return;
	}
	$vars = exf_ex_file_email_vars( $ex_file_id );

	if ( 'clear' === $outcome ) {
		exf_send_email( 'active', $user->user_email, $vars + array( 'link' => exf_after_clear_url( $client_id ) ) );
	} elseif ( 'review' === $outcome ) {
		exf_send_email( 'review', $user->user_email, $vars + array( 'link' => exf_page_url( 'account' ) ) );
		exf_send_email( 'admin_review', exf_notify_emails(), $vars );
	} elseif ( 'blocked' === $outcome ) {
		exf_send_email( 'unable', $user->user_email, $vars );
		exf_send_email( 'admin_blocked', exf_notify_emails(), $vars );
	}
}

/**
 * Merge tags for an Ex File.
 *
 * @param int $ex_file_id Ex File ID.
 */
function exf_ex_file_email_vars( $ex_file_id ) {
	$client_id = (int) get_post_field( 'post_author', $ex_file_id );
	$client    = exf_client_details( $client_id );
	$ex        = exf_ex_details( $ex_file_id );
	return array(
		'first_name'  => $client['first_name'],
		'client_name' => trim( $client['first_name'] . ' ' . $client['last_name'] ),
		'ex_name'     => trim( $ex['first_name'] . ' ' . $ex['last_name'] ),
		'score'       => (int) get_post_meta( $ex_file_id, 'conflict_score', true ),
		'admin_link'  => admin_url( 'post.php?post=' . $ex_file_id . '&action=edit' ),
	);
}

/**
 * Where a cleared client goes next: the service they picked before registering, or their account.
 *
 * @param int $user_id User ID.
 */
function exf_after_clear_url( $user_id ) {
	$service = (string) get_user_meta( $user_id, 'exf_intended_service', true );
	$url     = $service ? apply_filters( 'exf_service_purchase_url', '', $service, $user_id ) : '';
	return $url ? $url : exf_page_url( 'account' );
}
