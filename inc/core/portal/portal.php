<?php
/**
 * The client portal: sections of the My Ex File page.
 *
 *   /my-ex-file/                      Overview
 *   /my-ex-file/ex-files/             All Ex Files
 *   /my-ex-file/ex-files/123/         One Ex File
 *   /my-ex-file/documents/            Documents (and uploading)
 *   /my-ex-file/appointments/         Appointments
 *   /my-ex-file/payments/             Payments
 *   /my-ex-file/account-details/      Contact details, email, password
 *
 * Sections are rewrite endpoints on the My Ex File page, so it stays one WordPress page.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_PORTAL_ID = 'exf_portal';

/**
 * Portal sections: slug => label.
 */
function exf_portal_sections() {
	return array(
		'overview'        => __( 'Overview', 'exfiles' ),
		'ex-files'        => __( 'Ex Files', 'exfiles' ),
		'documents'       => __( 'Documents', 'exfiles' ),
		'appointments'    => __( 'Appointments', 'exfiles' ),
		'payments'        => __( 'Payments', 'exfiles' ),
		'account-details' => __( 'Account details', 'exfiles' ),
	);
}

add_action(
	'init',
	function () {
		foreach ( array_keys( exf_portal_sections() ) as $slug ) {
			if ( 'overview' !== $slug ) {
				add_rewrite_endpoint( $slug, EP_PAGES );
			}
		}
		// New endpoints need the rewrite rules rebuilt once.
		$sig = md5( implode( ',', array_merge( array_keys( exf_portal_sections() ), (array) apply_filters( 'exf_portal_extra_sections', array() ) ) ) . '|login' );
		if ( get_option( 'exf_rewrite_sig' ) !== $sig ) {
			flush_rewrite_rules( false );
			update_option( 'exf_rewrite_sig', $sig, false );
		}
	},
	20
);

/**
 * Current portal section and its value (e.g. an Ex File ID).
 *
 * @return array [ section, value ]
 */
function exf_portal_current() {
	global $wp_query;
	foreach ( array_merge( array_keys( exf_portal_sections() ), (array) apply_filters( 'exf_portal_extra_sections', array() ) ) as $slug ) {
		if ( 'overview' !== $slug && isset( $wp_query->query_vars[ $slug ] ) ) {
			return array( $slug, (string) $wp_query->query_vars[ $slug ] );
		}
	}
	return array( 'overview', '' );
}

/**
 * URL of a portal section.
 *
 * @param string     $section Section slug.
 * @param string|int $value   Optional value, e.g. an Ex File ID.
 */
function exf_portal_url( $section = 'overview', $value = '' ) {
	$base = trailingslashit( exf_page_url( 'account' ) );
	if ( 'overview' === $section ) {
		return $base;
	}
	return $base . $section . '/' . ( '' !== (string) $value ? rawurlencode( (string) $value ) . '/' : '' );
}

/**
 * Editable portal wording (Ex Files → Portal Copy), with defaults.
 *
 * @param string $key Key.
 */
function exf_portal_copy( $key ) {
	$defaults = array(
		'active_text'     => __( 'Choose a service whenever you’re ready. Everything you book, and anything we send you, will appear here.', 'exfiles' ),
		'review_text'     => __( "Before we can act for you, we complete a routine check, which we do by hand for some new clients. We'll email you as soon as it's finished.", 'exfiles' ),
		'documents_intro' => __( "Reports and documents we prepare for you appear here. You can also send us documents, such as bank statements or pension letters. Only you and The Ex Files can see them.", 'exfiles' ),
		'upload_help'     => __( 'PDF, Word, Excel or photos, up to 15 MB each.', 'exfiles' ),
		'change_policy'   => __( "Appointments can't be changed online. If you need to move or cancel one, send us a request below and we'll be in touch.", 'exfiles' ),
		'q_intro'         => __( "This tells us about your finances and your family, so we can give you advice that fits your situation. It helps to have recent bank, mortgage and pension statements to hand, but if you don't know something, just say so: please don't guess.", 'exfiles' ),
		'q_review'        => __( "Please check everything before you submit. Answers you've estimated are highlighted in yellow, and answers you don't know in red. Use Edit to change a section.", 'exfiles' ),
		'q_submitted'     => __( "Thank you. We've received your answers and will be in touch. Receiving them doesn't mean we have checked your financial disclosure yet.", 'exfiles' ),
		'locked_details'  => __( 'Your name and date of birth are part of the checks we carry out before acting for you, so please contact us if they need changing.', 'exfiles' ),
	);
	return (string) exf_get_option_field( $key, EXF_PORTAL_ID, $defaults[ $key ] ?? '' );
}

add_action(
	'acf/init',
	function () {
		if ( function_exists( 'acf_add_options_sub_page' ) ) {
			acf_add_options_sub_page(
				array(
					'page_title'  => __( 'Portal Copy', 'exfiles' ),
					'menu_title'  => __( 'Portal Copy', 'exfiles' ),
					'menu_slug'   => 'exf-portal-copy',
					'parent_slug' => 'edit.php?post_type=ex_file',
					'capability'  => EXF_CAP,
					'post_id'     => EXF_PORTAL_ID,
					'autoload'    => false,
				)
			);
		}
	}
);

/**
 * Whether the current user may see an Ex File in the portal.
 *
 * @param int $ex_file_id Ex File.
 */
function exf_portal_owns_ex_file( $ex_file_id ) {
	return 'ex_file' === get_post_type( $ex_file_id ) && (int) get_post_field( 'post_author', $ex_file_id ) === get_current_user_id();
}

/**
 * Things the client should do or know about now, for the overview.
 *
 * @param int $user_id Client.
 * @return array[] [ [ 'text' => …, 'url' => …, 'label' => … ], … ]
 */
function exf_portal_next_steps( $user_id ) {
	$steps = array();

	foreach ( exf_client_ex_files( $user_id ) as $id ) {
		if ( 'info_requested' === exf_ex_file_status( $id ) ) {
			$steps[] = array(
				'text'  => __( 'We need a little more information from you to finish our checks.', 'exfiles' ),
				'url'   => exf_portal_url( 'ex-files', $id ),
				'label' => __( 'Reply now', 'exfiles' ),
			);
		}
	}

	foreach ( exf_client_questionnaires( $user_id ) as $q ) {
		$status = (string) get_post_meta( $q, 'q_status', true );
		if ( in_array( $status, array( 'to_do', 'in_progress' ), true ) ) {
			$url     = (string) apply_filters( 'exf_questionnaire_url', '', $q );
			$service = (string) get_post_meta( (int) get_post_meta( $q, 'order_id', true ), 'service_name', true );
			$steps[] = array(
				'text'  => $url
					/* translators: %s: service name */
					? sprintf( __( 'Please complete your financial questionnaire for %s so we can start work.', 'exfiles' ), $service )
					/* translators: %s: service name */
					: sprintf( __( "Your financial questionnaire for %s will be ready here soon. We'll email you when it is.", 'exfiles' ), $service ),
				'url'   => $url,
				'label' => 'in_progress' === $status ? __( 'Continue', 'exfiles' ) : __( 'Start', 'exfiles' ),
			);
		}
	}

	$next = exf_client_appointments( $user_id, 'upcoming' );
	if ( $next ) {
		$o       = $next[0];
		$steps[] = array(
			/* translators: 1: service, 2: date and time */
			'text'  => sprintf( __( 'Your %1$s appointment is on %2$s.', 'exfiles' ), get_post_meta( $o, 'service_name', true ), exf_booking_label( (int) get_post_meta( $o, 'booking_start', true ), (int) get_post_meta( $o, 'booking_end', true ) ) ),
			'url'   => exf_portal_url( 'appointments' ),
			'label' => __( 'Details', 'exfiles' ),
		);
	}

	$new_docs = exf_client_new_documents( $user_id );
	if ( $new_docs ) {
		$steps[] = array(
			/* translators: %d: number of documents */
			'text'  => sprintf( _n( 'You have %d new document from us.', 'You have %d new documents from us.', count( $new_docs ), 'exfiles' ), count( $new_docs ) ),
			'url'   => exf_portal_url( 'documents' ),
			'label' => __( 'View', 'exfiles' ),
		);
	}

	return $steps;
}

/**
 * A client's questionnaires, newest first.
 *
 * @param int $user_id Client.
 * @param int $ex_file Optional Ex File.
 * @return int[]
 */
function exf_client_questionnaires( $user_id, $ex_file = 0 ) {
	$args = array(
		'post_type'      => 'fs_questionnaire',
		'post_status'    => 'any',
		'author'         => (int) $user_id,
		'posts_per_page' => -1,
		'fields'         => 'ids',
	);
	if ( $ex_file ) {
		$args['meta_key']   = 'ex_file_id'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$args['meta_value'] = (int) $ex_file; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	}
	return array_map( 'intval', get_posts( $args ) );
}

/**
 * Label for a questionnaire status.
 *
 * @param string $status to_do|in_progress|submitted.
 */
function exf_questionnaire_status_label( $status ) {
	$labels = array(
		'to_do'       => __( 'To do', 'exfiles' ),
		'in_progress' => __( 'In progress', 'exfiles' ),
		'submitted'   => __( 'Submitted', 'exfiles' ),
	);
	return $labels[ $status ] ?? $status;
}

// Header: "My Ex File" or "Log in" after the menu links.
add_action(
	'exf_after_nav_links',
	function () {
		if ( is_user_logged_in() ) {
			$current = is_page_template( 'page-account.php' );
			printf( '<a href="%s" class="nav-account%s">%s</a>', esc_url( exf_page_url( 'account' ) ), $current ? ' active' : '', esc_html__( 'My Ex File', 'exfiles' ) );
		} elseif ( exf_page_id( 'login' ) ) {
			printf( '<a href="%s" class="nav-account">%s</a>', esc_url( exf_page_url( 'login' ) ), esc_html__( 'Log in', 'exfiles' ) );
		}
	}
);

/**
 * Errors from a portal form, for the template.
 *
 * @param string     $form   Form name.
 * @param array|null $errors Set errors.
 * @return array
 */
function exf_portal_errors( $form, $errors = null ) {
	static $all = array();
	if ( is_array( $errors ) ) {
		$all[ $form ] = $errors;
	}
	return $all[ $form ] ?? array();
}

// Portal forms. Each handler redirects on success or returns errors for the template.
add_action(
	'template_redirect',
	function () {
		if ( ! is_page_template( 'page-account.php' ) || ! is_user_logged_in() ) {
			return;
		}
		$uid = get_current_user_id();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the check.
		if ( isset( $_GET['confirm_email'] ) ) {
			exf_handle_email_confirm( $uid, sanitize_text_field( wp_unslash( $_GET['confirm_email'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['exf_portal'] ) || ! exf_is_client( $uid ) ) {
			return;
		}
		$form = sanitize_key( wp_unslash( $_POST['exf_portal'] ) );
		check_admin_referer( 'exf_portal_' . $form );
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised field by field.

		switch ( $form ) {
			case 'upload':
				exf_portal_errors( $form, exf_handle_client_upload( $uid ) );
				break;
			case 'delete_doc':
				exf_handle_client_delete_document( absint( $post['doc'] ?? 0 ) );
				break;
			case 'appointment':
				exf_portal_errors( $form, exf_handle_appointment_request( $uid, $post ) );
				break;
			case 'details':
				exf_portal_errors( $form, exf_handle_details_update( $uid, $post ) );
				break;
			case 'email':
				exf_portal_errors( $form, exf_handle_email_change( $uid, $post ) );
				break;
			case 'password':
				exf_portal_errors( $form, exf_handle_password_change( $uid, $post ) );
				break;
		}
	},
	6
);

/**
 * Hidden fields that start a portal form.
 *
 * @param string $form Form name.
 */
function exf_portal_form_fields( $form ) {
	wp_nonce_field( 'exf_portal_' . $form );
	echo '<input type="hidden" name="exf_portal" value="' . esc_attr( $form ) . '">';
}

/**
 * Render a portal section from template-parts/portal/.
 *
 * @param string $section Section.
 * @param array  $args    Arguments.
 */
function exf_portal_part( $section, array $args = array() ) {
	get_template_part( 'template-parts/portal/' . $section, null, $args );
}
