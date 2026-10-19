<?php
/**
 * Daily housekeeping: delete personal data that's past its retention period.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'exf_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'exf_daily_cleanup' );
		}
	}
);

add_action( 'exf_daily_cleanup', 'exf_cleanup_guide_requests' );

/**
 * Delete guide requests (and their PDFs) older than the retention period.
 *
 * @return int Number deleted.
 */
function exf_cleanup_guide_requests() {
	$months = (int) exf_setting( 'guide_retention_months', 24 );
	if ( $months < 1 ) {
		return 0; // 0 = keep forever.
	}

	$deleted = 0;
	do {
		$ids = get_posts(
			array(
				'post_type'      => 'guide_request',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'before' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . $months . ' months' ) ),
						'column' => 'post_date_gmt',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true ); // The PDF goes too: see before_delete_post in guide/admin.php.
			++$deleted;
		}
	} while ( count( $ids ) === 50 );

	return $deleted;
}
