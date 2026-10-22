<?php
/**
 * Guide submission: build the PDF, store it, email it, log the request, and
 * serve the time-limited download link.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_GUIDE_LINK_HOURS = 48;

add_action( 'admin_post_exf_guide_submit', 'exf_guide_handle_submit' );
add_action( 'admin_post_nopriv_exf_guide_submit', 'exf_guide_handle_submit' );

/**
 * Handle the guide form.
 */
function exf_guide_handle_submit() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form on a cacheable page: honeypot + rate limit instead, as with the contact form.
	$form_url = exf_page_url( 'guide_form' );

	if ( ! empty( $_POST['company'] ) ) {
		wp_safe_redirect( exf_page_url( 'guide_sent' ) );
		exit; // Quietly drop bots.
	}

	$raw_answers = isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is checked against the allowed options.
	$raw_answers = array_map( 'sanitize_key', array_map( 'strval', $raw_answers ) );
	$walk        = exf_guide_walk( $raw_answers );

	$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$services  = isset( $_POST['services'] ) && is_array( $_POST['services'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['services'] ) ) : array();
	$services  = array_values( array_intersect( $services, array_keys( exf_guide_services() ) ) );
	$marketing = exf_marketing_optin() && ! empty( $_POST['marketing'] );
	// phpcs:enable

	if ( 'final' !== $walk['outcome'] ) {
		exf_guide_back( $form_url, 'exit' === $walk['outcome'] ? 'exit' : 'incomplete' );
	}
	if ( ! is_email( $email ) ) {
		exf_guide_back( $form_url, 'email' );
	}

	// 5 guides per 10 minutes per IP.
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$limit = 'exf_guide_' . md5( $ip );
	$count = (int) get_transient( $limit );
	if ( $count >= 5 ) {
		exf_guide_back( $form_url, 'rate' );
	}
	set_transient( $limit, $count + 1, 10 * MINUTE_IN_SECONDS );

	$result = exf_guide_create( $walk['answers'], $email, $services, $marketing );
	if ( is_wp_error( $result ) ) {
		error_log( 'Ex Files guide failed: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		exf_guide_back( $form_url, 'failed' );
	}

	wp_safe_redirect( add_query_arg( 'guide', rawurlencode( $result['token'] ), exf_page_url( 'guide_sent' ) ) );
	exit;
}

/**
 * Redirect back to the form with an error code.
 *
 * @param string $url  Form URL.
 * @param string $code Error code (see page-guide-form.php).
 */
function exf_guide_back( $url, $code ) {
	wp_safe_redirect( add_query_arg( 'guide_error', $code, $url ) );
	exit;
}

/**
 * Build, store, email and log a guide.
 *
 * @param array    $answers   Valid answers from exf_guide_walk().
 * @param string   $email     Recipient.
 * @param string[] $services  Services they'd like to hear about.
 * @param bool     $marketing Marketing consent.
 * @return array|WP_Error { post_id, token }
 */
function exf_guide_create( array $answers, $email, array $services, $marketing ) {
	$tags       = exf_guide_tags( $answers );
	$ids        = exf_guide_paragraph_ids( $tags );
	$paragraphs = exf_guide_paragraphs();
	$completed  = time();

	$pdf = exf_render_pdf(
		'guide',
		array(
			'title'      => exf_guide_copy( 'pdf_title' ),
			'point'      => exf_guide_copy( 'pdf_point' ),
			'important'  => exf_guide_copy( 'pdf_important' ),
			'footer'     => exf_guide_copy( 'pdf_footer' ),
			'logo'       => exf_guide_pdf_logo(),
			'date'       => wp_date( 'j F Y', $completed ),
			'completed'  => wp_date( 'j F Y \a\t H:i', $completed ),
			'paragraphs' => array_map(
				function ( $id ) use ( $paragraphs ) {
					return array( 'id' => $id ) + $paragraphs[ $id ];
				},
				$ids
			),
			'annex'      => exf_guide_annex_rows( $answers ),
		)
	);
	if ( is_wp_error( $pdf ) ) {
		return $pdf;
	}

	$file = exf_private_save( 'guides', 'the-ex-files-divorce-guide.pdf', $pdf );
	if ( ! $file ) {
		return new WP_Error( 'exf_save_failed', 'Could not save the guide PDF.' );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'guide_request',
			'post_status' => 'publish',
			/* translators: 1: date, 2: email */
			'post_title'  => sprintf( __( '%1$s – %2$s', 'exfiles' ), wp_date( 'j M Y H:i', $completed ), $email ),
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		exf_private_delete( $file );
		return $post_id;
	}

	$token = wp_generate_password( 32, false, false );
	$meta  = array(
		'_exf_email'         => $email,
		'_exf_answers'       => $answers,
		'_exf_tags'          => $tags,
		'_exf_paragraphs'    => $ids,
		'_exf_services'      => $services,
		'_exf_marketing'     => $marketing ? 1 : 0,
		'_exf_file'          => $file,
		'_exf_token_hash'    => hash( 'sha256', $token ),
		'_exf_token_expires' => $completed + EXF_GUIDE_LINK_HOURS * HOUR_IN_SECONDS,
		'_exf_route'         => exf_guide_route_label( $answers ),
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}

	$sent = exf_guide_send_email( $email, $services, exf_private_path( $file ) );
	update_post_meta( $post_id, '_exf_email_sent', $sent ? 1 : 0 );

	return array(
		'post_id' => $post_id,
		'token'   => $token,
	);
}

/**
 * Email the guide PDF.
 *
 * @param string   $email    Recipient.
 * @param string[] $services Services they asked about.
 * @param string   $path     Absolute PDF path.
 * @return bool
 */
function exf_guide_send_email( $email, array $services, $path ) {
	$list = '';
	if ( $services ) {
		$names = array_intersect_key( exf_guide_services(), array_flip( $services ) );
		$list  = str_replace( '{list}', '- ' . implode( "\n- ", $names ), (string) exf_guide_copy( 'email_services' ) );
	}

	$body    = str_replace( '{services}', $list, (string) exf_guide_copy( 'email_body' ) );
	$subject = (string) exf_guide_copy( 'email_subject' );
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	// Name the attachment nicely: copy it to a temp file with a clean name.
	$attachment = array();
	if ( $path ) {
		$tmp_dir = trailingslashit( get_temp_dir() ) . 'exf-' . wp_generate_password( 12, false, false );
		if ( wp_mkdir_p( $tmp_dir ) && copy( $path, $tmp_dir . '/The Ex Files - Your Divorce Guide.pdf' ) ) {
			$attachment[] = $tmp_dir . '/The Ex Files - Your Divorce Guide.pdf';
		} else {
			$attachment[] = $path;
		}
	}

	$sent = wp_mail( $email, $subject, $body, $headers, $attachment );

	if ( isset( $tmp_dir ) && is_dir( $tmp_dir ) ) {
		array_map( 'wp_delete_file', glob( $tmp_dir . '/*' ) );
		rmdir( $tmp_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
	return (bool) $sent;
}

/**
 * Logo for the PDF: the guide content's logo, then the site logo, then the bundled one.
 */
function exf_guide_pdf_logo() {
	$logo = exf_get_option_field( 'pdf_logo', EXF_GUIDE_ID, null );
	$id   = is_array( $logo ) ? (int) ( $logo['ID'] ?? 0 ) : (int) $logo;
	if ( ! $id ) {
		$id = (int) get_theme_mod( 'custom_logo' );
	}
	$path = $id ? get_attached_file( $id ) : '';
	$uri  = exf_pdf_image_data_uri( $path );
	return $uri ? $uri : exf_pdf_image_data_uri( EXF_DIR . '/assets/img/logo.png' );
}

/**
 * Guide request for a download token, if it's still valid.
 *
 * @param string $token Token from the URL.
 * @return int Post ID, or 0.
 */
function exf_guide_request_by_token( $token ) {
	$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
	if ( strlen( $token ) !== 32 ) {
		return 0;
	}
	$ids = get_posts(
		array(
			'post_type'      => 'guide_request',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_exf_token_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => hash( 'sha256', $token ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( ! $ids ) {
		return 0;
	}
	$expires = (int) get_post_meta( $ids[0], '_exf_token_expires', true );
	return $expires > time() ? (int) $ids[0] : 0;
}

// Download link from the "guide sent" page: ?exf_guide_download=<token>.
add_action(
	'template_redirect',
	function () {
		if ( ! isset( $_GET['exf_guide_download'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$id = exf_guide_request_by_token( sanitize_text_field( wp_unslash( $_GET['exf_guide_download'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $id ) {
			wp_die(
				esc_html__( 'This download link has expired. Your guide was also emailed to you, or you can complete the questions again.', 'exfiles' ),
				esc_html__( 'Link expired', 'exfiles' ),
				array(
					'response'  => 410,
					'link_url'  => esc_url( exf_page_url( 'guide_form' ) ),
					'link_text' => esc_html__( 'Start again', 'exfiles' ),
				)
			);
		}
		exf_private_stream( get_post_meta( $id, '_exf_file', true ), 'The Ex Files - Your Divorce Guide.pdf' );
	},
	1
);
