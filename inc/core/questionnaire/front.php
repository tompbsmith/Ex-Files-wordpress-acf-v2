<?php
/**
 * The questionnaire in the portal: /my-ex-file/questionnaire/{id}/?step={section|review}.
 *
 * Each step saves when the client presses Next, Back or "Save and finish later", so
 * they can leave and come back. Submitting locks it; the solicitor can reopen it.
 *
 * Statuses (meta q_status): to_do, in_progress, submitted.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		add_rewrite_endpoint( 'questionnaire', EP_PAGES );
	},
	19
);

// The questionnaire isn't a menu item, but it is a portal section.
add_filter(
	'exf_portal_extra_sections',
	function ( $s ) {
		$s[] = 'questionnaire';
		return $s;
	}
);

/**
 * Link to a questionnaire (or one of its steps).
 *
 * @param int    $q    Questionnaire.
 * @param string $step Optional step.
 */
function exf_q_url( $q, $step = '' ) {
	$url = exf_portal_url( 'questionnaire', (int) $q );
	return $step ? add_query_arg( 'step', $step, $url ) : $url;
}

add_filter(
	'exf_questionnaire_url',
	function ( $url, $q ) {
		return exf_q_url( $q );
	},
	10,
	2
);

/**
 * Whether the current user owns a questionnaire.
 *
 * @param int $q Questionnaire.
 */
function exf_q_owned( $q ) {
	return 'fs_questionnaire' === get_post_type( $q ) && (int) get_post_field( 'post_author', $q ) === get_current_user_id();
}

/**
 * Status of a questionnaire.
 *
 * @param int $q Questionnaire.
 */
function exf_q_status( $q ) {
	$s = (string) get_post_meta( $q, 'q_status', true );
	return $s ? $s : 'to_do';
}

/**
 * Add a line to the questionnaire's history and its Ex File's activity.
 *
 * @param int    $q       Questionnaire.
 * @param string $message Message.
 */
function exf_q_log( $q, $message ) {
	$log   = array_filter( (array) get_post_meta( $q, 'q_log', true ) );
	$log[] = array(
		'time' => time(),
		'msg'  => $message,
	);
	update_post_meta( $q, 'q_log', $log );
	$ex_file = (int) get_post_meta( $q, 'ex_file_id', true );
	if ( $ex_file ) {
		exf_ex_file_log( $ex_file, 'Questionnaire: ' . $message );
	}
}

/**
 * The service name for a questionnaire.
 *
 * @param int $q Questionnaire.
 */
function exf_q_service_name( $q ) {
	$names = array();
	foreach ( exf_q_orders( $q ) as $order ) {
		$name = (string) get_post_meta( $order, 'service_name', true );
		if ( '' !== $name ) {
			$names[ $name ] = $name;
		}
	}
	return implode( ' and ', $names );
}

/**
 * Orders a questionnaire is used for, oldest first. There's one questionnaire per
 * Ex File, so a later service is linked to the existing one (meta "order_ids").
 *
 * @param int $q Questionnaire.
 * @return int[]
 */
function exf_q_orders( $q ) {
	$ids = array_merge( array( (int) get_post_meta( $q, 'order_id', true ) ), array_map( 'intval', (array) get_post_meta( $q, 'order_ids', true ) ) );
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * The client's last submitted questionnaire on the same Ex File, if any.
 *
 * @param int $q Questionnaire.
 */
function exf_q_previous( $q ) {
	$ex_file = (int) get_post_meta( $q, 'ex_file_id', true );
	$latest  = 0;
	$when    = 0;
	foreach ( exf_client_questionnaires( (int) get_post_field( 'post_author', $q ), $ex_file ) as $other ) {
		$at = (int) get_post_meta( $other, 'q_submitted_at', true );
		if ( $other !== (int) $q && 'submitted' === exf_q_status( $other ) && $at > $when ) {
			$latest = $other;
			$when   = $at;
		}
	}
	return $latest;
}

/**
 * Handle the start screen: begin afresh or from the last answers.
 *
 * @param int   $q    Questionnaire.
 * @param array $post Posted data.
 */
function exf_q_handle_start( $q, array $post ) {
	if ( 'to_do' !== exf_q_status( $q ) ) {
		wp_safe_redirect( exf_q_url( $q ) );
		exit;
	}
	$prev = exf_q_previous( $q );
	if ( ! empty( $post['copy'] ) && $prev ) {
		update_post_meta( $q, 'q_data', exf_q_data( $prev ) );
		exf_q_log( $q, 'Started from the answers in an earlier questionnaire' );
	} else {
		exf_q_log( $q, 'Started' );
	}
	update_post_meta( $q, 'q_status', 'in_progress' );
	update_post_meta( $q, 'q_started_at', time() );
	$first = exf_q_visible_sections( exf_q_data( $q ) )[0];
	wp_safe_redirect( exf_q_url( $q, $first ) );
	exit;
}

/**
 * Save a step, then move on (or show what needs fixing).
 *
 * @param int   $q    Questionnaire.
 * @param array $post Posted data.
 * @return array Errors.
 */
function exf_q_handle_step( $q, array $post ) {
	if ( 'submitted' === exf_q_status( $q ) ) {
		wp_safe_redirect( exf_q_url( $q ) );
		exit;
	}
	$key  = sanitize_key( $post['section'] ?? '' );
	$data = exf_q_data( $q );
	if ( ! isset( exf_q_sections()[ $key ] ) ) {
		wp_safe_redirect( exf_q_url( $q ) );
		exit;
	}
	$data[ $key ] = exf_q_clean_section( $key, $post['q'] ?? array(), $data );

	// A change in "About you" can switch the agreements section on or off.
	$visible = exf_q_visible_sections( $data );
	foreach ( array_keys( $data ) as $k ) {
		if ( ! in_array( $k, $visible, true ) ) {
			unset( $data[ $k ] );
		}
	}
	update_post_meta( $q, 'q_data', $data );
	if ( 'to_do' === exf_q_status( $q ) ) {
		update_post_meta( $q, 'q_status', 'in_progress' );
		update_post_meta( $q, 'q_started_at', time() );
	}
	update_post_meta( $q, 'q_saved_at', time() );

	$go     = sanitize_key( $post['go'] ?? 'next' );
	$errors = exf_q_validate_section( $key, $data[ $key ], $data );
	$pos    = array_search( $key, $visible, true );

	if ( 'save' === $go ) {
		wp_safe_redirect( add_query_arg( 'saved', 1, exf_page_url( 'account' ) ) );
		exit;
	}
	if ( 'back' === $go ) {
		wp_safe_redirect( exf_q_url( $q, $pos > 0 ? $visible[ $pos - 1 ] : $key ) );
		exit;
	}
	// Missing or wrong answers don't stop them moving on: the section button turns
	// red, and the answers are marked when they come back to it (or at review).
	unset( $errors );
	$next = $visible[ $pos + 1 ] ?? 'review';
	if ( 'review' === sanitize_key( $post['return'] ?? '' ) ) {
		$next = 'review';
	}
	wp_safe_redirect( exf_q_url( $q, $next ) );
	exit;
}

/**
 * Submit the questionnaire.
 *
 * @param int   $q    Questionnaire.
 * @param array $post Posted data.
 * @return array Errors.
 */
function exf_q_handle_submit( $q, array $post ) {
	if ( 'submitted' === exf_q_status( $q ) ) {
		wp_safe_redirect( exf_q_url( $q ) );
		exit;
	}
	$data = exf_q_data( $q );
	if ( exf_q_validate_all( $data ) ) {
		return array( '_form' => __( 'Some sections still need answers. Please use the Edit links below.', 'exfiles' ) );
	}
	if ( empty( $post['declaration'] ) ) {
		return array( 'declaration' => __( 'Please confirm the declaration.', 'exfiles' ) );
	}

	// Clean every section once more, in order, so nothing hidden is kept.
	$clean = array();
	foreach ( exf_q_visible_sections( $data ) as $key ) {
		$clean[ $key ] = exf_q_clean_section( $key, $data[ $key ] ?? array(), $clean + $data );
	}
	update_post_meta( $q, 'q_data', $clean );
	update_post_meta( $q, 'q_status', 'submitted' );
	update_post_meta( $q, 'q_submitted_at', time() );
	update_post_meta( $q, 'q_declaration_at', time() );
	exf_q_log( $q, 'Submitted by the client' );

	exf_q_after_submit( $q );

	wp_safe_redirect( add_query_arg( 'submitted', 1, exf_q_url( $q ) ) );
	exit;
}

/**
 * Outputs and emails after a questionnaire is submitted.
 *
 * @param int $q Questionnaire.
 */
function exf_q_after_submit( $q ) {
	$data    = exf_q_data( $q );
	$ex_file = (int) get_post_meta( $q, 'ex_file_id', true );
	$client  = get_userdata( (int) get_post_field( 'post_author', $q ) );
	$flags   = array();

	// Not married: carry on, but flag the Ex File for the solicitor to review.
	if ( 'No' === ( $data['about']['married'] ?? '' ) ) {
		update_post_meta( $ex_file, 'flag_unmarried', time() );
		exf_ex_file_log( $ex_file, 'Flagged: the client says they are not married to their ex' );
		$flags[] = __( 'The client says they are NOT married to (or in a civil partnership with) their ex. Please check the matter is suitable.', 'exfiles' );
	} else {
		delete_post_meta( $ex_file, 'flag_unmarried' );
	}

	$pdf  = exf_q_generate_pdf( $q );
	$xlsx = exf_q_generate_xlsx( $q );
	if ( ! $pdf ) {
		$flags[] = __( 'The PDF copy could not be created. The answers are saved in the admin.', 'exfiles' );
	}
	if ( ! $xlsx ) {
		$flags[] = __( 'The asset schedule could not be created. The answers are saved in the admin.', 'exfiles' );
	}

	$counts = exf_q_flag_counts( $data );
	// When each written report is now due (e.g. Check Your Deal: 7 days from today).
	$due    = array();
	foreach ( exf_q_orders( $q ) as $o ) {
		$at = function_exists( 'exf_order_report_due' ) ? exf_order_report_due( $o ) : 0;
		if ( $at && ! exf_order_completed( $o ) ) {
			/* translators: 1: service, 2: date */
			$due[] = sprintf( __( 'We’ll send your written report for %1$s by %2$s.', 'exfiles' ), get_post_meta( $o, 'service_name', true ), wp_date( 'l j F Y', $at ) );
		}
	}
	$vars   = array_merge( ( $ex_file ? exf_ex_file_email_vars( $ex_file ) : array() ), array(
		'report_due' => implode( "\n", $due ),
		'service'    => exf_q_service_name( $q ),
		'link'       => exf_portal_url( 'documents' ),
		'admin_link' => admin_url( 'post.php?post=' . $q . '&action=edit' ),
		'dont_know'  => $counts['dont_know'],
		'estimates'  => $counts['estimate'],
		'flags'      => $flags ? implode( "\n", $flags ) . "\n" : '',
	) );
	if ( $client ) {
		exf_send_email( 'questionnaire_received', $client->user_email, $vars );
	}
	exf_send_email( 'admin_questionnaire', exf_notify_emails(), $vars );
}

/**
 * Reopen a submitted questionnaire so the client can change it.
 *
 * @param int $q Questionnaire.
 */
function exf_q_reopen( $q ) {
	update_post_meta( $q, 'q_status', 'in_progress' );
	delete_post_meta( $q, 'q_reminders' );
	exf_q_log( $q, 'Reopened for the client by ' . wp_get_current_user()->display_name );
	$client = get_userdata( (int) get_post_field( 'post_author', $q ) );
	$ex     = (int) get_post_meta( $q, 'ex_file_id', true );
	if ( $client ) {
		exf_send_email(
			'questionnaire_reopened',
			$client->user_email,
			array_merge( ( $ex ? exf_ex_file_email_vars( $ex ) : array() ), array(
				'service' => exf_q_service_name( $q ),
				'link'    => exf_q_url( $q, 'review' ),
			) )
		);
	}
}

// Questionnaire forms in the portal.
add_action(
	'template_redirect',
	function () {
		if ( ! is_page_template( 'page-account.php' ) || ! is_user_logged_in() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['exf_q'] ) ) {
			return;
		}
		$q      = absint( $_POST['questionnaire'] ?? 0 );
		$action = sanitize_key( wp_unslash( $_POST['exf_q'] ) );
		check_admin_referer( 'exf_q_' . $q );
		if ( ! exf_q_owned( $q ) ) {
			wp_die( esc_html__( "Sorry, you can't change that questionnaire.", 'exfiles' ), '', array( 'response' => 403 ) );
		}
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned against the schema.
		if ( 'start' === $action ) {
			exf_q_handle_start( $q, $post );
		} elseif ( 'step' === $action ) {
			exf_portal_errors( 'questionnaire', exf_q_handle_step( $q, $post ) );
		} elseif ( 'submit' === $action ) {
			exf_portal_errors( 'questionnaire', exf_q_handle_submit( $q, $post ) );
		}
	},
	7
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_page_template( 'page-questionnaire-preview.php' ) || ( is_page_template( 'page-account.php' ) && 'questionnaire' === exf_portal_current()[0] ) ) {
			wp_enqueue_script( 'exf-questionnaire', get_template_directory_uri() . '/assets/js/questionnaire.js', array(), EXF_VERSION, true );
		}
	}
);

/* -------------------------------------------------------------------------
 * Reminders: 3 and 7 days after payment, and a day before the questionnaire is
 * needed for an appointment (see deadlines.php).
 * ---------------------------------------------------------------------- */

add_action( 'exf_hourly', 'exf_q_send_reminders' );

/**
 * Send any reminders that are due.
 */
function exf_q_send_reminders() {
	$ids = get_posts(
		array(
			'post_type'      => 'fs_questionnaire',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'q_status',
					'value'   => array( 'to_do', 'in_progress' ),
					'compare' => 'IN',
				),
			),
		)
	);
	$now = time();
	foreach ( $ids as $q ) {
		$live = array_values( array_filter( exf_q_orders( $q ), 'exf_order_is_live' ) );
		if ( ! $live ) {
			continue;
		}
		$order = $live[0];
		$start = 0;
		foreach ( $live as $o ) {
			$s = (int) get_post_meta( $o, 'booking_start', true );
			if ( $s > $now && ( ! $start || $s < $start ) ) {
				$start = $s;
				$order = $o;
			}
		}
		$sent = array_filter( (array) get_post_meta( $q, 'q_reminders', true ) );
		$paid = (int) get_post_meta( $live[0], 'paid_at', true );
		$paid = $paid ? $paid : get_post_timestamp( $q );
		// The appointment reminder goes a day before the questionnaire's deadline
		// (Service settings → "Questionnaire needed by"), or 2 days before the appointment.
		$deadline = $start && function_exists( 'exf_order_q_deadline' ) ? exf_order_q_deadline( $order ) : 0;
		$remind   = $deadline ? $deadline - DAY_IN_SECONDS : $start - 2 * DAY_IN_SECONDS;
		$due      = '';
		if ( $start && $now >= $remind && ( ! $deadline || $now < $deadline ) && empty( $sent['appointment'] ) ) {
			$due = 'appointment';
		} elseif ( $now - $paid >= 7 * DAY_IN_SECONDS && empty( $sent['day7'] ) ) {
			$due = 'day7';
		} elseif ( $now - $paid >= 3 * DAY_IN_SECONDS && $now - $paid < 7 * DAY_IN_SECONDS && empty( $sent['day3'] ) ) {
			$due = 'day3';
		}
		if ( ! $due ) {
			continue;
		}
		// Don't send two reminders within a day of each other.
		if ( $sent && max( $sent ) > $now - DAY_IN_SECONDS ) {
			continue;
		}
		$client = get_userdata( (int) get_post_field( 'post_author', $q ) );
		if ( $client ) {
			$ex = (int) get_post_meta( $q, 'ex_file_id', true );
			exf_send_email(
				'questionnaire_reminder',
				$client->user_email,
				array_merge( ( $ex ? exf_ex_file_email_vars( $ex ) : array() ), array(
					'service'     => exf_q_service_name( $q ),
					'link'        => exf_q_url( $q ),
					'appointment' => $start ? exf_booking_label( $start, (int) get_post_meta( $order, 'booking_end', true ) ) : '',
					'before_appt' => 'appointment' === $due && $start
						? ( $deadline
							/* translators: 1: appointment date, 2: deadline */
							? sprintf( __( 'Your appointment is on %1$s. To prepare, we need your answers by %2$s. If we don’t have them by then, we may need to cancel or move your appointment.', 'exfiles' ), exf_booking_label( $start ), exf_booking_label( $deadline ) )
							/* translators: %s: appointment date */
							: sprintf( __( 'Your appointment is on %s, and we need your answers before then to prepare.', 'exfiles' ), exf_booking_label( $start ) ) ) . "\n\n"
						: '',
				) )
			);
		}
		$sent[ $due ] = $now;
		update_post_meta( $q, 'q_reminders', $sent );
		exf_q_log( $q, 'Reminder sent (' . $due . ')' );
	}
}
