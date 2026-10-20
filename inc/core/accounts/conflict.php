<?php
/**
 * Conflict of interest check.
 *
 * Runs whenever an Ex File needs checking (after email verification, and later for
 * each new separation). It compares:
 *
 *   1. the ex's details      against every existing client, and conflict register
 *                             entries for former clients (and "other");
 *   2. the client's details  against the exes named in other clients' Ex Files,
 *                             and conflict register entries for opposing parties
 *                             (the "reverse" check: someone we act for may have named
 *                             this person as their ex).
 *
 * Each comparison scores the strongest matching signal (see exf_conflict_signals()).
 * The highest score decides the outcome: 100+ blocked, 30–99 review, under 30 clear.
 * The weights are a starting point to agree with the client and tune after testing.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_CONFLICT_BLOCK  = 100;
const EXF_CONFLICT_REVIEW = 30;

// Reverse matches and "other" register entries can raise a review, never a block on their own.
const EXF_CONFLICT_REVERSE_CAP = 60;

/**
 * Signal weights. A comparison scores its single strongest signal, not the sum.
 */
function exf_conflict_signals() {
	return array(
		'email'         => array( 100, __( 'Email address matches', 'exfiles' ) ),
		'name_dob'      => array( 100, __( 'Full name and date of birth match', 'exfiles' ) ),
		'phone'         => array( 80, __( 'Phone number matches', 'exfiles' ) ),
		'name_postcode' => array( 70, __( 'Full name and postcode match', 'exfiles' ) ),
		'surname_dob'   => array( 40, __( 'Surname and date of birth match', 'exfiles' ) ),
		'name'          => array( 30, __( 'Full name matches', 'exfiles' ) ),
	);
}

/* -------------------------------------------------------------------------
 * Normalising
 * ---------------------------------------------------------------------- */

/**
 * Lower case, no accents, titles, punctuation or extra spaces.
 *
 * @param string $name Name.
 */
function exf_norm_name( $name ) {
	$name = strtolower( remove_accents( (string) $name ) );
	$name = preg_replace( "/['’`]/u", '', $name ); // O'Brien → obrien.
	$name = preg_replace( '/[^a-z]+/', ' ', $name );
	$name = preg_replace( '/\b(mr|mrs|ms|miss|mx|dr|prof|sir|dame|lord|lady|rev)\b/', ' ', $name );
	return trim( preg_replace( '/\s+/', ' ', $name ) );
}

/**
 * Every full-name form a person might go by: [ [first, last], … ].
 *
 * Previous names are free text ("Jane Doe, Smith"). A single word is taken as a
 * previous surname with the current first name.
 *
 * @param string $first      First name.
 * @param string $last       Last name.
 * @param string $prev_names Previous names, comma separated.
 */
function exf_name_variants( $first, $last, $prev_names = '' ) {
	$first    = exf_norm_name( $first );
	$last     = exf_norm_name( $last );
	$firsts   = $first ? array( strtok( $first, ' ' ) ) : array();
	$variants = array();

	// "Mary Ann Smith-Jones" also matches "Smith Jones" and "Jones".
	$lasts = array();
	if ( $last ) {
		$lasts[] = $last;
		$parts   = explode( ' ', $last );
		if ( count( $parts ) > 1 ) {
			$lasts[] = end( $parts );
		}
	}

	foreach ( $firsts as $f ) {
		foreach ( $lasts as $l ) {
			$variants[] = array( $f, $l );
		}
	}

	foreach ( preg_split( '/[,;\n]+/', (string) $prev_names ) as $prev ) {
		$prev = exf_norm_name( $prev );
		if ( '' === $prev ) {
			continue;
		}
		$words = explode( ' ', $prev );
		if ( 1 === count( $words ) ) {
			foreach ( $firsts as $f ) {
				$variants[] = array( $f, $words[0] );
			}
		} else {
			$variants[] = array( $words[0], end( $words ) );
		}
	}

	return array_values( array_unique( $variants, SORT_REGULAR ) );
}

/**
 * UK-style phone number reduced to digits: +44 7700 900123 → 07700900123.
 *
 * @param string $phone Phone.
 */
function exf_norm_phone( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	if ( 0 === strpos( $digits, '0044' ) ) {
		$digits = '0' . substr( $digits, 4 );
	} elseif ( 0 === strpos( $digits, '44' ) && strlen( $digits ) >= 12 ) {
		$digits = '0' . substr( $digits, 2 );
	}
	return strlen( $digits ) >= 10 ? $digits : '';
}

/**
 * Postcode without spaces, upper case.
 *
 * @param string $postcode Postcode.
 */
function exf_norm_postcode( $postcode ) {
	$pc = strtoupper( preg_replace( '/\s+/', '', (string) $postcode ) );
	return strlen( $pc ) >= 5 ? $pc : '';
}

/**
 * Date of birth as Y-m-d, or ''.
 *
 * @param string $dob Date in Y-m-d, Ymd or d/m/Y.
 */
function exf_norm_dob( $dob ) {
	$dob = trim( (string) $dob );
	if ( preg_match( '/^(\d{4})-?(\d{2})-?(\d{2})$/', $dob, $m ) ) {
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? "$m[1]-$m[2]-$m[3]" : '';
	}
	if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $dob, $m ) ) {
		return checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ? sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] ) : '';
	}
	return '';
}

/**
 * A person ready for comparison.
 *
 * @param array $p Raw: first_name, last_name, prev_names, dob, email, phone, postcode.
 */
function exf_conflict_person( array $p ) {
	return array(
		'names'    => exf_name_variants( $p['first_name'] ?? '', $p['last_name'] ?? '', $p['prev_names'] ?? '' ),
		'dob'      => exf_norm_dob( $p['dob'] ?? '' ),
		'email'    => strtolower( trim( (string) ( $p['email'] ?? '' ) ) ),
		'phone'    => exf_norm_phone( $p['phone'] ?? '' ),
		'postcode' => exf_norm_postcode( $p['postcode'] ?? '' ),
	);
}

/**
 * Compare two people.
 *
 * @param array $a Person from exf_conflict_person().
 * @param array $b Person from exf_conflict_person().
 * @return array { score, signals[] }
 */
function exf_conflict_compare( array $a, array $b ) {
	$found = array();

	if ( $a['email'] && $a['email'] === $b['email'] ) {
		$found[] = 'email';
	}
	if ( $a['phone'] && $a['phone'] === $b['phone'] ) {
		$found[] = 'phone';
	}

	$full_name = false;
	$surname   = false;
	foreach ( $a['names'] as $na ) {
		foreach ( $b['names'] as $nb ) {
			if ( $na[1] === $nb[1] ) {
				$surname = true;
				if ( $na[0] === $nb[0] ) {
					$full_name = true;
				}
			}
		}
	}
	$same_dob = $a['dob'] && $a['dob'] === $b['dob'];

	if ( $full_name && $same_dob ) {
		$found[] = 'name_dob';
	}
	if ( $full_name && $a['postcode'] && $a['postcode'] === $b['postcode'] ) {
		$found[] = 'name_postcode';
	}
	if ( $surname && $same_dob ) {
		$found[] = 'surname_dob';
	}
	if ( $full_name ) {
		$found[] = 'name';
	}

	$weights = exf_conflict_signals();
	$score   = 0;
	foreach ( $found as $s ) {
		$score = max( $score, $weights[ $s ][0] );
	}
	return array(
		'score'   => $score,
		'signals' => $found,
	);
}

/* -------------------------------------------------------------------------
 * Pools to compare against
 * ---------------------------------------------------------------------- */

/**
 * Ex details stored on an Ex File.
 *
 * @param int $ex_file_id Ex File ID.
 */
function exf_ex_details( $ex_file_id ) {
	$get = function ( $k ) use ( $ex_file_id ) {
		return (string) get_post_meta( $ex_file_id, $k, true );
	};
	return array(
		'first_name' => $get( 'ex_first_name' ),
		'last_name'  => $get( 'ex_last_name' ),
		'prev_names' => $get( 'ex_prev_names' ),
		'dob'        => $get( 'ex_dob_unknown' ) ? '' : $get( 'ex_dob' ),
		'email'      => $get( 'ex_email' ),
		'phone'      => $get( 'ex_phone' ),
		'postcode'   => $get( 'ex_postcode' ),
	);
}

/**
 * Everyone the solicitor acts or has acted for, except one user.
 *
 * @param int $exclude_user Client being checked.
 * @return array[] [ [ 'type' => 'client'|'register', 'id' => int, 'label' => string, 'role' => string, 'person' => [...] ], … ]
 */
function exf_conflict_pool_clients( $exclude_user ) {
	$pool  = array();
	$users = get_users(
		array(
			'meta_key'     => 'exf_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'   => EXF_STATUS_PENDING, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_compare' => '!=',
			'exclude'      => array( (int) $exclude_user ),
			'fields'       => 'ID',
			'number'       => -1,
		)
	);
	foreach ( $users as $uid ) {
		$d      = exf_client_details( $uid );
		$pool[] = array(
			'type'   => 'client',
			'id'     => (int) $uid,
			'label'  => trim( $d['first_name'] . ' ' . $d['last_name'] ),
			'role'   => 'client',
			'person' => exf_conflict_person( $d ),
			'raw'    => $d,
		);
	}
	foreach ( exf_conflict_pool_register() as $row ) {
		if ( 'opposing_party' !== $row['role'] ) {
			$pool[] = $row;
		}
	}
	return $pool;
}

/**
 * Exes named in other clients' Ex Files, plus opposing parties from the register.
 *
 * @param int $exclude_user Client being checked (their own Ex Files are skipped).
 */
function exf_conflict_pool_exes( $exclude_user ) {
	$pool = array();
	$ids  = get_posts(
		array(
			'post_type'      => 'ex_file',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'author__not_in' => array( (int) $exclude_user ),
		)
	);
	foreach ( $ids as $id ) {
		$owner = (int) get_post_field( 'post_author', $id );
		if ( EXF_STATUS_PENDING === exf_user_status( $owner ) ) {
			continue; // Unverified registrations don't count yet.
		}
		$d      = exf_ex_details( $id );
		$pool[] = array(
			'type'   => 'ex_file',
			'id'     => (int) $id,
			'label'  => trim( $d['first_name'] . ' ' . $d['last_name'] ),
			'role'   => 'ex',
			'person' => exf_conflict_person( $d ),
			'raw'    => $d,
		);
	}
	foreach ( exf_conflict_pool_register() as $row ) {
		if ( 'opposing_party' === $row['role'] ) {
			$pool[] = $row;
		}
	}
	return $pool;
}

/**
 * Conflict register entries.
 */
function exf_conflict_pool_register() {
	// Cached for one request, until any post changes (e.g. a register entry is added).
	static $rows = null, $stamp = null;
	$changed = wp_cache_get_last_changed( 'posts' );
	if ( null !== $rows && $stamp === $changed ) {
		return $rows;
	}
	$stamp = $changed;
	$rows = array();
	$ids  = get_posts(
		array(
			'post_type'      => 'conflict_record',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $ids as $id ) {
		$d      = array(
			'first_name' => (string) get_post_meta( $id, 'first_name', true ),
			'last_name'  => (string) get_post_meta( $id, 'last_name', true ),
			'prev_names' => (string) get_post_meta( $id, 'prev_names', true ),
			'dob'        => (string) get_post_meta( $id, 'dob', true ),
			'email'      => (string) get_post_meta( $id, 'email', true ),
			'phone'      => (string) get_post_meta( $id, 'phone', true ),
			'postcode'   => (string) get_post_meta( $id, 'postcode', true ),
		);
		$role   = (string) get_post_meta( $id, 'record_role', true );
		$rows[] = array(
			'type'   => 'register',
			'id'     => (int) $id,
			'label'  => trim( $d['first_name'] . ' ' . $d['last_name'] ),
			'role'   => $role ? $role : 'former_client',
			'person' => exf_conflict_person( $d ),
			'raw'    => $d,
		);
	}
	return $rows;
}

/* -------------------------------------------------------------------------
 * Running the check
 * ---------------------------------------------------------------------- */

/**
 * Score an Ex File without changing anything.
 *
 * @param int $ex_file_id Ex File ID.
 * @return array { score, outcome, matches[] } Matches are sorted strongest first.
 */
function exf_conflict_evaluate( $ex_file_id ) {
	$client_id = (int) get_post_field( 'post_author', $ex_file_id );
	$ex        = exf_conflict_person( exf_ex_details( $ex_file_id ) );
	$client    = exf_conflict_person( exf_client_details( $client_id ) );
	$matches   = array();

	// 1. Is the ex someone we act or acted for?
	foreach ( exf_conflict_pool_clients( $client_id ) as $row ) {
		$r = exf_conflict_compare( $ex, $row['person'] );
		if ( $r['score'] > 0 ) {
			$score     = 'other' === $row['role'] ? min( $r['score'], EXF_CONFLICT_REVERSE_CAP ) : $r['score'];
			$matches[] = array(
				'direction' => 'ex',
				'score'     => $score,
				'signals'   => $r['signals'],
			) + array_diff_key( $row, array( 'person' => 1 ) );
		}
	}

	// 2. Has someone we act for named this client as their ex, or are they a known opposing party?
	foreach ( exf_conflict_pool_exes( $client_id ) as $row ) {
		$r = exf_conflict_compare( $client, $row['person'] );
		if ( $r['score'] > 0 ) {
			$matches[] = array(
				'direction' => 'client',
				'score'     => min( $r['score'], EXF_CONFLICT_REVERSE_CAP ),
				'signals'   => $r['signals'],
			) + array_diff_key( $row, array( 'person' => 1 ) );
		}
	}

	usort(
		$matches,
		function ( $x, $y ) {
			return $y['score'] <=> $x['score'];
		}
	);
	$score = $matches ? $matches[0]['score'] : 0;

	if ( $score >= EXF_CONFLICT_BLOCK ) {
		$outcome = 'blocked';
	} elseif ( $score >= EXF_CONFLICT_REVIEW ) {
		$outcome = 'review';
	} else {
		$outcome = 'clear';
	}

	return array(
		'score'   => $score,
		'outcome' => $outcome,
		'matches' => $matches,
	);
}

/**
 * Run the check on an Ex File, record the result and apply it to the client's account.
 *
 * @param int  $ex_file_id Ex File ID.
 * @param bool $notify     Send the emails that go with the outcome.
 * @return string Outcome: clear|review|blocked.
 */
function exf_conflict_run( $ex_file_id, $notify = true ) {
	$result    = exf_conflict_evaluate( $ex_file_id );
	$client_id = (int) get_post_field( 'post_author', $ex_file_id );

	update_post_meta( $ex_file_id, 'conflict_score', $result['score'] );
	update_post_meta( $ex_file_id, 'conflict_matches', $result['matches'] );
	update_post_meta( $ex_file_id, 'conflict_checked_at', time() );
	exf_ex_file_set_status( $ex_file_id, $result['outcome'] );
	exf_ex_file_log( $ex_file_id, sprintf( 'Conflict check: %s (score %d, %d match%s)', $result['outcome'], $result['score'], count( $result['matches'] ), 1 === count( $result['matches'] ) ? '' : 'es' ) );

	exf_sync_user_status( $client_id );

	if ( $notify ) {
		exf_conflict_notify( $ex_file_id, $result['outcome'] );
	}
	return $result['outcome'];
}

/**
 * Conflict status of an Ex File: pending|clear|review|info_requested|blocked.
 *
 * @param int $ex_file_id Ex File ID.
 */
function exf_ex_file_status( $ex_file_id ) {
	$s = (string) get_post_meta( $ex_file_id, 'conflict_status', true );
	return $s ? $s : 'pending';
}

/**
 * Set an Ex File's conflict status.
 *
 * @param int    $ex_file_id Ex File ID.
 * @param string $status     Status.
 */
function exf_ex_file_set_status( $ex_file_id, $status ) {
	update_post_meta( $ex_file_id, 'conflict_status', $status );
}

/**
 * Label for an Ex File status.
 *
 * @param string $status Status.
 */
function exf_ex_file_status_label( $status ) {
	$labels = array(
		'pending'        => __( 'Not checked yet', 'exfiles' ),
		'clear'          => __( 'Clear', 'exfiles' ),
		'review'         => __( 'Needs review', 'exfiles' ),
		'info_requested' => __( 'More information requested', 'exfiles' ),
		'blocked'        => __( 'Blocked', 'exfiles' ),
	);
	return $labels[ $status ] ?? $status;
}

/**
 * Append a line to an Ex File's audit log.
 *
 * @param int    $ex_file_id Ex File ID.
 * @param string $message    What happened.
 */
function exf_ex_file_log( $ex_file_id, $message ) {
	$log   = (array) get_post_meta( $ex_file_id, 'exf_log', true );
	$user  = wp_get_current_user();
	$log[] = array(
		'time' => time(),
		'user' => $user && $user->exists() ? $user->display_name : __( 'System', 'exfiles' ),
		'msg'  => $message,
	);
	update_post_meta( $ex_file_id, 'exf_log', array_slice( array_filter( $log ), -200 ) );
	do_action( 'exf_ex_file_logged', $ex_file_id, $message );
}

/**
 * The account follows the client's first Ex File: a block or review there blocks or
 * holds the account. Later Ex Files (a new separation) only affect themselves.
 *
 * @param int $user_id User ID.
 */
function exf_sync_user_status( $user_id ) {
	if ( EXF_STATUS_PENDING === exf_user_status( $user_id ) && ! get_user_meta( $user_id, 'exf_email_verified', true ) ) {
		return;
	}
	$first = exf_first_ex_file( $user_id );
	if ( ! $first ) {
		return;
	}
	$map = array(
		'clear'          => EXF_STATUS_ACTIVE,
		'review'         => EXF_STATUS_REVIEW,
		'info_requested' => EXF_STATUS_REVIEW,
		'blocked'        => EXF_STATUS_BLOCKED,
	);
	$s   = exf_ex_file_status( $first );
	if ( isset( $map[ $s ] ) ) {
		exf_set_user_status( $user_id, $map[ $s ] );
	}
	if ( EXF_STATUS_BLOCKED === exf_user_status( $user_id ) ) {
		WP_Session_Tokens::get_instance( $user_id )->destroy_all();
	}
}

/**
 * A client's first Ex File.
 *
 * @param int $user_id User ID.
 * @return int 0 if none.
 */
function exf_first_ex_file( $user_id ) {
	$ids = exf_client_ex_files( $user_id );
	return $ids ? (int) end( $ids ) : 0;
}

/**
 * A client's Ex Files, newest first.
 *
 * @param int $user_id User ID.
 * @return int[]
 */
function exf_client_ex_files( $user_id ) {
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => 'ex_file',
				'post_status'    => 'any',
				'author'         => (int) $user_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'date ID',
				'order'          => 'DESC',
			)
		)
	);
}
