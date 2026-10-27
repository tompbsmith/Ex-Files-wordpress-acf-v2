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
			'Confirm your email to create your Ex File',
			"Hello {first_name},\n\nThank you for creating your Ex File with The Ex Files. Please confirm your email address using this link:\n\n{link}\n\nThe link works for 48 hours. If you didn't sign up, you can ignore this email.\n\nBest wishes,\nThe Ex Files",
		),
		'verify_paid'    => array(
			'Confirm your email to access your Ex File',
			"Hello {first_name},\n\nThank you for your order. To finish creating your Ex File, please confirm your email address using this link:\n\n{link}\n\nYou'll then be logged in to your Ex File, where you'll find your booking and anything we need from you next. We've sent your order confirmation separately.\n\nThe link works for 48 hours. If it runs out, you can ask for a new one on the log in page.\n\nBest wishes,\nThe Ex Files",
		),
		'review_checkout' => array(
			"We're just checking a few details",
			"Hello {first_name},\n\nThank you for choosing The Ex Files. Before we can take your booking, we need to check a few details. This is done within one working day, and we'll email you as soon as it's finished so you can complete your booking.\n\nYou haven't been charged.\n\nBest wishes,\nThe Ex Files",
		),
		'active'         => array(
			"You're all set",
			"Hello {first_name},\n\nYour Ex File is open and you can now choose your service:\n\n{link}\n\nBest wishes,\nThe Ex Files",
		),
		'review'         => array(
			"We're just checking a few details",
			"Hello {first_name},\n\nThank you for confirming your email. Before we can act for you, we need to complete a routine check, which we do by hand for some new clients. This is done within one working day, and we'll email you as soon as it's finished.\n\nYou can log in to your Ex File in the meantime:\n\n{link}\n\nBest wishes,\nThe Ex Files",
		),
		'info_requested' => array(
			'We need a little more information',
			"Hello {first_name},\n\nTo finish our routine check, we need a little more information:\n\n{question}\n\nPlease log in to your Ex File to reply:\n\n{link}\n\nBest wishes,\nThe Ex Files",
		),
		'unable'         => array(
			'About your Ex File',
			"Hello {first_name},\n\nThank you for your interest in The Ex Files. Unfortunately we're unable to take on your matter. We can't give details, as it can be for a number of reasons. We're sorry we can't help on this occasion.\n\nIf you have any questions, please reply to this email.\n\nBest wishes,\nThe Ex Files",
		),
		'admin_review'   => array(
			'Conflict check needs review: {client_name}',
			"A new Ex File needs a manual conflict check.\n\nClient: {client_name}\nEx: {ex_name}\nScore: {score} ({strength})\n\nThe client has been told only that you're checking a few details. If you decline, they'll get the same general message whatever the reason.\n\nReview it here:\n{admin_link}",
		),
		'admin_blocked'  => array(
			'Account blocked by conflict check: {client_name}',
			"The conflict check blocked a new Ex File automatically.\n\nClient: {client_name}\nEx: {ex_name}\nScore: {score}\n\nThe client was told only that you're unable to act. If this is a false match, you can clear it here:\n{admin_link}",
		),
		'order_confirmed'  => array(
			'Confirmed: {service}',
			"Hello {first_name},\n\nThank you. Here are the details:\n\nService: {order_items}\nAmount: {amount}\nAppointment: {appointment}\n\n{meeting_details}\n\n{next_steps}\n\nYou can see everything in your Ex File:\n{link}\n\nStripe will email you a separate receipt for any payment.\n\n{cancellation}\n\nBest wishes,\nThe Ex Files",
		),
		'booking_reminder' => array(
			'Reminder: your appointment tomorrow',
			"Hello {first_name},\n\nA reminder that your {service} appointment is on {appointment}.\n\n{meeting_details}\n\nIf you need to change it, please send us a request from your Ex File:\n{link}\n\nBest wishes,\nThe Ex Files",
		),
		'appointment_moved' => array(
			'Your appointment has moved: {appointment}',
			"Hello {first_name},\n\nYour {service} appointment has moved. The new time is:\n\n{appointment}\n\n(It was {old_appointment}.)\n\n{meeting_details}\n\nAn updated calendar invite is attached, so the appointment should move in your calendar.\n\nYou can see your appointments in your Ex File:\n{link}\n\nBest wishes,\nThe Ex Files",
		),
		'appointment_cancelled' => array(
			'Your appointment has been cancelled',
			"Hello {first_name},\n\nYour {service} appointment on {appointment} has been cancelled. {reason}\n\nIf you'd like to arrange a new time, please reply to this email.\n\nA calendar cancellation is attached, so the appointment should disappear from your calendar.\n\nBest wishes,\nThe Ex Files",
		),
		'admin_appointment_changed' => array(
			'Appointment {what}: {client_name}, {service}',
			"{client_name}'s {service} appointment has been {what}: {appointment}.\n\nThe calendar file attached updates the event in your calendar. Order details:\n{admin_link}",
		),
		'admin_q_overdue' => array(
			'Questionnaire not received: {client_name}',
			"{client_name} hasn't submitted their financial questionnaire for {service}. It was needed by {deadline}, for the appointment on {appointment}.\n\nYou can cancel or move the appointment here:\n{admin_link}",
		),
		'document_ready'   => array(
			'A new document is ready in your Ex File',
			"Hello {first_name},\n\nWe've added a new document to your Ex File: {document}.\n\n{attachment_note}\n{link}\n\nBest wishes,\nThe Ex Files",
		),
		'consultation_open' => array(
			'Book your consultation',
			"Hello {first_name},\n\nYour report for {service} is ready in your Ex File, so you can now book the consultation you added to your order. Please choose a time here:\n\n{link}\n\nBest wishes,\nThe Ex Files",
		),
		'consultation_offer' => array(
			'Would you like to talk your report through?',
			"Hello {first_name},\n\nYour report for {service} is in your Ex File. If you'd like to go through it with us, you can book a {addon} for {price}. Choose a time and pay here:\n\n{link}\n\nThere's no need if you don't want one.\n\nBest wishes,\nThe Ex Files",
		),
		'email_change'     => array(
			'Confirm your new email address',
			"Hello {first_name},\n\nPlease confirm that you'd like to use {new_email} for your Ex File by clicking this link while logged in:\n\n{link}\n\nThe link works for 24 hours. If you didn't ask for this, you can ignore this email.\n\nBest wishes,\nThe Ex Files",
		),
		'email_changed'    => array(
			'Your email address has been changed',
			"Hello {first_name},\n\nThe email address for your Ex File has been changed to {new_email}. We'll use the new address from now on.\n\nIf you didn't make this change, please contact us straight away.\n\nBest wishes,\nThe Ex Files",
		),
		'password_reset'   => array(
			'Reset your password',
			"Hello {first_name},\n\nSomeone asked to reset the password for your Ex File. If this was you, choose a new password here:\n\n{link}\n\nIf you didn't ask for this, you can ignore this email and your password won't change.\n\nBest wishes,\nThe Ex Files",
		),
		'admin_appointment_request' => array(
			'Appointment request from {client_name}: {request}',
			"{client_name} has asked you to: {request}.\n\nService: {service}\nAppointment: {appointment}\n\nTheir message:\n{message}\n\nThe client can't change the booking online, so please contact them. If you cancel it, use \"Cancel appointment\" on the order to free the slot (this doesn't refund):\n{admin_link}",
		),
		'questionnaire_reminder' => array(
			'Reminder: your financial questionnaire',
			"Hello {first_name},\n\n{before_appt}We're still waiting for your financial questionnaire for {service}. Your answers are saved as you go, so you can pick up where you left off:\n\n{link}\n\nIf you don't know an answer, just say so; please don't guess.\n\nBest wishes,\nThe Ex Files",
		),
		'questionnaire_received' => array(
			"We've received your questionnaire",
			"Hello {first_name},\n\nThank you for completing your financial questionnaire for {service}. We'll be in touch.\n\n{report_due}\n\nA copy of your answers is in your Ex File:\n{link}\n\nPlease note that receiving your answers doesn't mean we have checked your financial disclosure yet.\n\nBest wishes,\nThe Ex Files",
		),
		'questionnaire_reopened' => array(
			'Your questionnaire is open again',
			"Hello {first_name},\n\nWe've reopened your financial questionnaire for {service} so you can update your answers. When you're done, please submit it again:\n\n{link}\n\nBest wishes,\nThe Ex Files",
		),
		'admin_questionnaire' => array(
			'Questionnaire submitted: {client_name}',
			"{client_name} has submitted their questionnaire for {service}.\n\n{flags}Answers marked \"Don't know\": {dont_know}\nEstimated answers: {estimates}\n\nThe answers, PDF and asset schedule are here:\n{admin_link}",
		),
		'admin_client_upload' => array(
			'New document from {client_name}',
			"{client_name} has uploaded a document to their Ex File.\n\nFile: {document}\nType: {doc_type}\nTheir note: {note}\n\nOpen it here:\n{admin_link}",
		),
		'admin_order'      => array(
			'New order: {service} for {client_name}',
			"{client_name} has ordered {order_items} (total {total}).\n\nEx File: {client_name} / {ex_name}\nAppointment: {appointment}\n\nOrder details:\n{admin_link}",
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
	$email = exf_email_render( $key, $vars );
	if ( ! $email ) {
		return false;
	}
	return (bool) wp_mail( $to, $email[0], $email[1], array( 'Content-Type: text/plain; charset=UTF-8' ) );
}

/**
 * Subject and body of an email with its merge tags filled in.
 *
 * @param string $key  Email key from exf_email_defaults().
 * @param array  $vars Merge tags without braces.
 * @return array|null [ subject, body ]
 */
function exf_email_render( $key, array $vars = array() ) {
	$defaults = exf_email_defaults();
	if ( ! isset( $defaults[ $key ] ) ) {
		return null;
	}
	$subject = (string) exf_get_option_field( $key . '_subject', EXF_EMAILS_ID, $defaults[ $key ][0] );
	$body    = (string) exf_get_option_field( $key . '_body', EXF_EMAILS_ID, $defaults[ $key ][1] );

	$vars += array( 'site_name' => exf_site_name() );
	$tags  = array();
	foreach ( $vars as $k => $v ) {
		$tags[ '{' . $k . '}' ] = (string) $v;
	}
	// Empty merge tags (e.g. no appointment details) would leave gaps: keep at most one blank line.
	$body = preg_replace( "/(\r?\n[ \t]*){3,}/", "\n\n", strtr( $body, $tags ) );
	return array( wp_strip_all_tags( strtr( $subject, $tags ) ), trim( $body ) . "\n" );
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
		// The link confirms their email if they haven't yet (sign-ups held for review at
		// checkout aren't sent a confirmation link until now), then goes on to the service
		// they chose. Otherwise it goes through the log in page, which sends anyone already
		// logged in straight on.
		exf_send_email( 'active', $user->user_email, $vars + array( 'link' => exf_client_link( $client_id, exf_after_clear_url( $client_id ) ) ) );
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
		'strength'    => (int) get_post_meta( $ex_file_id, 'conflict_score', true ) >= EXF_CONFLICT_BLOCK ? 'strong match: we recommend declining' : 'possible match',
		'admin_link'  => admin_url( 'post.php?post=' . $ex_file_id . '&action=edit' ),
	);
}

/**
 * A link for an email to a client: their confirmation link if they haven't confirmed
 * their email address yet (it logs them in), otherwise the log in page, which goes on
 * to $url (straight away if they're already logged in).
 *
 * @param int    $user_id Client.
 * @param string $url     Where they should end up.
 */
function exf_client_link( $user_id, $url ) {
	if ( EXF_STATUS_PENDING === exf_user_status( $user_id ) && ! get_user_meta( $user_id, 'exf_email_verified', true ) ) {
		update_user_meta( $user_id, 'exf_verify_then', esc_url_raw( $url ) );
		return exf_new_verification_link( $user_id );
	}
	return wp_login_url( $url );
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
