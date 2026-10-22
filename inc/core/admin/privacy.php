<?php
/**
 * Data protection requests, through WordPress's own tools (Tools → Export Personal
 * Data / Erase Personal Data).
 *
 * Export: account details, Ex Files, orders, appointments, documents (names),
 * questionnaire answers and free guide requests.
 *
 * Erase: free guide requests and unpaid, never-cleared registrations are deleted.
 * A client's matters are legal records, so they're kept until the retention period
 * ends; the request says so, and they appear in Retention review after that.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'wp_privacy_personal_data_exporters',
	function ( $exporters ) {
		$exporters['exfiles'] = array(
			'exporter_friendly_name' => __( 'The Ex Files', 'exfiles' ),
			'callback'               => 'exf_privacy_export',
		);
		return $exporters;
	}
);

add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['exfiles'] = array(
			'eraser_friendly_name' => __( 'The Ex Files', 'exfiles' ),
			'callback'             => 'exf_privacy_erase',
		);
		return $erasers;
	}
);

/**
 * Guide requests made with an email address.
 *
 * @param string $email Email.
 * @return int[]
 */
function exf_guide_requests_for( $email ) {
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => 'guide_request',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_exf_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		)
	);
}

/**
 * Name/value pairs for the export.
 *
 * @param array $pairs label => value.
 */
function exf_privacy_pairs( array $pairs ) {
	$out = array();
	foreach ( $pairs as $name => $value ) {
		if ( '' !== (string) $value ) {
			$out[] = array(
				'name'  => $name,
				'value' => (string) $value,
			);
		}
	}
	return $out;
}

/**
 * Exporter.
 *
 * @param string $email Email.
 * @param int    $page  Page.
 */
/**
 * A stored date (Ymd or Y-m-d) as dd/mm/yyyy for people to read.
 *
 * @param string $value Stored date.
 */
function exf_privacy_date( $value ) {
	$norm = exf_norm_dob( (string) $value );
	return $norm ? gmdate( 'd/m/Y', strtotime( $norm ) ) : (string) $value;
}

function exf_privacy_export( $email, $page = 1 ) {
	$items = array();
	$user  = get_user_by( 'email', $email );

	if ( $user && 1 === (int) $page ) {
		$uid     = $user->ID;
		$d       = exf_client_details( $uid );
		$items[] = array(
			'group_id'    => 'exf-account',
			'group_label' => __( 'Ex Files account', 'exfiles' ),
			'item_id'     => 'account-' . $uid,
			'data'        => exf_privacy_pairs(
				array(
					'Name'           => trim( $d['first_name'] . ' ' . $d['last_name'] ),
					'Previous names' => $d['prev_names'],
					'Date of birth'  => exf_privacy_date( $d['dob'] ),
					'Email'          => $d['email'],
					'Phone'          => $d['phone'],
					'Address'        => trim( implode( ', ', array_filter( array( get_user_meta( $uid, 'billing_address_1', true ), get_user_meta( $uid, 'billing_address_2', true ), get_user_meta( $uid, 'billing_city', true ), $d['postcode'] ) ) ) ),
					'Marketing'      => get_user_meta( $uid, 'exf_marketing', true ) ? 'Yes' : 'No',
					'Account status' => exf_status_label( exf_user_status( $uid ) ),
				)
			),
		);
		foreach ( exf_client_ex_files( $uid ) as $f ) {
			$ex      = exf_ex_details( $f );
			$items[] = array(
				'group_id'    => 'exf-ex-files',
				'group_label' => __( 'Ex Files (separations)', 'exfiles' ),
				'item_id'     => 'ex-file-' . $f,
				'data'        => exf_privacy_pairs(
					array(
						"Ex's name"            => trim( $ex['first_name'] . ' ' . $ex['last_name'] ),
						"Ex's other names"     => $ex['prev_names'],
						"Ex's date of birth"   => exf_privacy_date( $ex['dob'] ),
						"Ex's email"           => $ex['email'],
						"Ex's phone"           => $ex['phone'],
						"Ex's postcode"        => $ex['postcode'],
						'Relationship'         => get_post_meta( $f, 'relationship', true ),
						'Date of marriage'     => exf_privacy_date( get_post_meta( $f, 'marriage_date', true ) ),
						'Date of separation'   => exf_privacy_date( get_post_meta( $f, 'separation_date', true ) ),
						'Opened'               => get_the_date( 'j M Y', $f ),
					)
				),
			);
		}
		foreach ( exf_client_orders( $uid ) as $o ) {
			$start   = (int) get_post_meta( $o, 'booking_start', true );
			$pence   = (int) get_post_meta( $o, 'amount_pence', true );
			$items[] = array(
				'group_id'    => 'exf-orders',
				'group_label' => __( 'Services and payments', 'exfiles' ),
				'item_id'     => 'order-' . $o,
				'data'        => exf_privacy_pairs(
					array(
						'Reference'   => exf_order_reference( $o ),
						'Service'     => get_post_meta( $o, 'service_name', true ),
						'Amount'      => $pence ? '£' . number_format( $pence / 100, 2 ) : 'Free',
						'Status'      => exf_order_status_label( exf_order_status( $o ) ),
						'Date'        => get_the_date( 'j M Y', $o ),
						'Appointment' => $start ? exf_booking_label( $start, (int) get_post_meta( $o, 'booking_end', true ) ) : '',
					)
				),
			);
		}
		foreach ( exf_client_documents( $uid ) as $doc ) {
			$items[] = array(
				'group_id'    => 'exf-documents',
				'group_label' => __( 'Documents', 'exfiles' ),
				'item_id'     => 'document-' . $doc,
				'data'        => exf_privacy_pairs(
					array(
						'Document' => get_post_meta( $doc, 'file_name', true ),
						'From'     => 'client' === get_post_meta( $doc, 'uploaded_by', true ) ? 'You' : 'The Ex Files',
						'Date'     => get_the_date( 'j M Y', $doc ),
						'Note'     => get_post_meta( $doc, 'doc_note', true ),
					)
				),
			);
		}
		foreach ( exf_client_questionnaires( $uid ) as $q ) {
			$data  = exf_q_data( $q );
			$pairs = array( 'Status' => exf_questionnaire_status_label( exf_q_status( $q ) ) );
			foreach ( exf_q_visible_sections( $data ) as $key ) {
				foreach ( exf_q_summary_section( $key, $data ) as $row ) {
					if ( 'row' === $row['type'] ) {
						$pairs[ exf_q_sections()[ $key ]['title'] . ': ' . $row['label'] . ' #' . count( $pairs ) ] = $row['value'];
					}
				}
			}
			$data_pairs = exf_privacy_pairs( $pairs );
			foreach ( $data_pairs as &$p ) {
				$p['name'] = preg_replace( '/ #\d+$/', '', $p['name'] );
			}
			$items[] = array(
				'group_id'    => 'exf-questionnaires',
				'group_label' => __( 'Financial questionnaires', 'exfiles' ),
				'item_id'     => 'questionnaire-' . $q,
				'data'        => $data_pairs,
			);
		}
	}

	if ( 1 === (int) $page ) {
		foreach ( exf_guide_requests_for( $email ) as $g ) {
			$items[] = array(
				'group_id'    => 'exf-guides',
				'group_label' => __( 'Free Divorce Guide requests', 'exfiles' ),
				'item_id'     => 'guide-' . $g,
				'data'        => exf_privacy_pairs(
					array(
						'Requested' => get_the_date( 'j M Y H:i', $g ),
						'Email'     => $email,
						'Answers'   => implode( '; ', array_map( 'strval', (array) get_post_meta( $g, '_exf_answers', true ) ) ),
					)
				),
			);
		}
	}

	return array(
		'data' => $items,
		'done' => true,
	);
}

/**
 * Eraser.
 *
 * @param string $email Email.
 * @param int    $page  Page.
 */
function exf_privacy_erase( $email, $page = 1 ) {
	$removed  = false;
	$retained = false;
	$messages = array();

	foreach ( exf_guide_requests_for( $email ) as $g ) {
		wp_delete_post( $g, true );
		$removed = true;
	}

	$user = get_user_by( 'email', $email );
	// Someone who never bought a service: delete everything, ex included, and keep
	// nothing on the Conflict Register (the client's decision, October 2026).
	if ( $user && function_exists( 'exf_delete_prospect' ) && exf_delete_prospect( $user->ID, 'erasure request' ) ) {
		return array(
			'items_removed'  => true,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
	if ( $user && ! user_can( $user, EXF_CAP ) ) {
		$uid   = $user->ID;
		$files = exf_client_ex_files( $uid );
		$paid  = exf_client_payments( $uid );
		$keep  = array();
		foreach ( $files as $f ) {
			// A paying client's matters are all kept together; otherwise keep any with work or an open check.
			$has_work = $paid || (bool) exf_client_orders( $uid, $f ) || exf_client_documents( $uid, $f ) || in_array( exf_ex_file_status( $f ), array( 'blocked', 'review', 'info_requested' ), true );
			if ( $has_work ) {
				$keep[] = $f;
			} else {
				exf_delete_matter( $f );
				$removed = true;
			}
		}
		if ( $keep || $paid ) {
			$retained = true;
			$years    = exf_retention_years();
			$until    = 0;
			foreach ( $keep as $f ) {
				$until = max( $until, exf_matter_last_activity( $f ) );
			}
			$messages[] = $years
				/* translators: 1: years, 2: date */
				? sprintf( __( 'The Ex Files: this person’s matters are legal records and have been kept. They must be kept for %1$d years after the last activity (until about %2$s), and will then appear in Ex Files → Retention review for deletion. Marketing emails have been switched off.', 'exfiles' ), $years, wp_date( 'F Y', strtotime( '+' . $years . ' years', $until ? $until : time() ) ) )
				: __( 'The Ex Files: this person’s matters are legal records and have been kept. Marketing emails have been switched off.', 'exfiles' );
			update_user_meta( $uid, 'exf_marketing', 0 );
			exf_audit( 'privacy_erase', 'Erasure request for ' . $email . ': matters kept as legal records; marketing switched off.', 'user', $uid );
		} else {
			exf_audit( 'privacy_erase', 'Erasure request for ' . $email . ': registration with no matters deleted.', 'user', $uid );
		}
	} elseif ( $removed ) {
		exf_audit( 'privacy_erase', 'Erasure request for ' . $email . ': free guide requests deleted.' );
	}

	return array(
		'items_removed'  => $removed,
		'items_retained' => $retained,
		'messages'       => $messages,
		'done'           => true,
	);
}

add_action(
	'admin_init',
	function () {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				__( 'The Ex Files', 'exfiles' ),
				wp_kses_post(
					'<p>' . __( 'Suggested text: When you open an Ex File we keep the details you give us about you and your ex, the services you buy, your appointments, the documents you and we share, and your financial questionnaire. We use the details of you and your ex to check that we can act for you (a conflict check). We keep this as a legal record for six years after the last activity on your matter, then delete it, keeping only the names and contact details needed for future conflict checks. If you open an Ex File but don’t buy a service, we delete your details and your ex’s after 30 days without activity, or sooner if you ask. Free Divorce Guide requests are kept for 24 months. Payments are handled by Stripe; we do not see or store card details.', 'exfiles' ) . '</p>'
				)
			);
		}
	}
);
