<?php
/**
 * Services: each service is a page using the "Service" template (page-service.php),
 * with its price and behaviour in the "Service settings" fields. Selling a service
 * is a Stripe Checkout payment (see stripe.php); free services skip payment.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Service types. Used to link the Free Divorce Guide's interests to pages.
 */
function exf_service_types() {
	return array(
		'know_your_position' => __( 'Know Your Position', 'exfiles' ),
		'check_your_deal'    => __( 'Check Your Deal', 'exfiles' ),
		'make_it_legal'      => __( 'Make It Legal', 'exfiles' ),
		'free_consultation'  => __( 'Free consultation', 'exfiles' ),
		'other'              => __( 'Other', 'exfiles' ),
	);
}

/**
 * A service's settings.
 *
 * @param int $page_id Service page.
 * @return array|null Null if it isn't a published service page.
 */
function exf_service( $page_id ) {
	$page_id = (int) $page_id;
	if ( ! $page_id || 'page-service.php' !== get_page_template_slug( $page_id ) || 'publish' !== get_post_status( $page_id ) ) {
		return null;
	}
	$get     = function ( $k, $d = '' ) use ( $page_id ) {
		$v = get_post_meta( $page_id, $k, true );
		return '' === $v || null === $v ? $d : $v;
	};
	$free    = (bool) $get( 'is_free', 0 );
	$price   = $free ? 0.0 : (float) $get( 'price', 0 );
	$minutes = (int) $get( 'appointment_minutes', 60 );

	return array(
		'id'            => $page_id,
		'name'          => get_the_title( $page_id ),
		'type'          => (string) $get( 'service_type', 'other' ),
		'free'          => $free,
		'price'         => $price,
		'pence'         => (int) round( $price * 100 ),
		'price_note'    => (string) $get( 'price_note', '' ),
		'booking'       => (bool) $get( 'requires_booking', 0 ),
		'minutes'       => $minutes > 0 ? $minutes : 60,
		'questionnaire' => (bool) $get( 'requires_questionnaire', 0 ),
		'deliverable'   => (string) $get( 'deliverable', 'report' ),
		'turnaround'    => (string) $get( 'turnaround', '' ),
		'intro'         => (string) $get( 'intro', '' ),
		'buy_label'     => (string) $get( 'buy_label', '' ),
		'sellable'      => $free || $price > 0,
	);
}

/**
 * All published service pages.
 *
 * @return array[]
 */
function exf_services() {
	$ids = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'meta_key'       => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => 'page-service.php', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	return array_values( array_filter( array_map( 'exf_service', $ids ) ) );
}

/**
 * Price as "£195" or "£195.50", or "Free".
 *
 * @param array $service Service.
 */
function exf_service_price_label( array $service ) {
	if ( $service['free'] ) {
		return __( 'Free', 'exfiles' );
	}
	if ( $service['price'] <= 0 ) {
		return '';
	}
	$decimals = floor( $service['price'] ) === $service['price'] ? 0 : 2;
	return '£' . number_format( $service['price'], $decimals );
}

/**
 * Where a "Buy" or "Book" button goes for a service.
 *
 * Logged-out visitors go to registration first (the service is remembered),
 * logged-in clients straight to the buy page.
 *
 * @param int $page_id Service page.
 */
function exf_service_buy_url( $page_id ) {
	if ( is_user_logged_in() ) {
		return add_query_arg( 'service', (int) $page_id, exf_page_url( 'buy' ) );
	}
	return add_query_arg( 'service', (int) $page_id, exf_page_url( 'register' ) );
}

// After registration and a clear check, send the client on to the service they picked.
add_filter(
	'exf_service_purchase_url',
	function ( $url, $service ) {
		$id = absint( $service );
		return $id && exf_service( $id ) ? add_query_arg( 'service', $id, exf_page_url( 'buy' ) ) : $url;
	},
	10,
	2
);

// Service pages use the Page Builder sections too.
add_filter(
	'use_block_editor_for_post',
	function ( $use, $post ) {
		return ( $post && 'page-service.php' === get_page_template_slug( $post->ID ) ) ? false : $use;
	},
	20,
	2
);

// Warn while any service has no price (its Buy button stays hidden).
add_action(
	'admin_notices',
	function () {
		if ( ! exf_user_can_manage() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'edit-page', 'edit-exf_order' ), true ) && false === strpos( (string) $screen->id, 'exf-' ) ) {
			return;
		}
		$unpriced = array_filter(
			exf_services(),
			function ( $s ) {
				return ! $s['sellable'];
			}
		);
		if ( ! $unpriced ) {
			return;
		}
		$links = array_map(
			function ( $s ) {
				return '<a href="' . esc_url( get_edit_post_link( $s['id'] ) ) . '">' . esc_html( $s['name'] ) . '</a>';
			},
			$unpriced
		);
		echo '<div class="notice notice-warning"><p>' . wp_kses_post(
			/* translators: %s: service page links */
			sprintf( __( "These services don't have a price yet, so they can't be bought: %s. Set the price in each page's Service settings.", 'exfiles' ), implode( ', ', $links ) )
		) . '</p></div>';
	}
);

/**
 * Default settings for the service pages created automatically.
 *
 * @return array key => [ title, slug, settings ]
 */
function exf_default_services() {
	return array(
		'svc_kyp'  => array(
			__( 'Know Your Position', 'exfiles' ),
			'know-your-position',
			array(
				'service_type'           => 'know_your_position',
				'requires_booking'       => 1,
				'appointment_minutes'    => 60,
				'requires_questionnaire' => 1,
				'deliverable'            => 'appointment',
				'intro'                  => __( 'A fixed-fee appointment to understand your financial position before you negotiate.', 'exfiles' ),
			),
		),
		'svc_cyd'  => array(
			__( 'Check Your Deal', 'exfiles' ),
			'check-your-deal',
			array(
				'service_type'           => 'check_your_deal',
				'requires_booking'       => 0,
				'requires_questionnaire' => 1,
				'deliverable'            => 'report',
				'intro'                  => __( 'A written review of an agreement you have already reached, so you know whether it is sensible before you sign.', 'exfiles' ),
			),
		),
		'svc_mil'  => array(
			__( 'Make It Legal', 'exfiles' ),
			'make-it-legal',
			array(
				'service_type'           => 'make_it_legal',
				'requires_booking'       => 0,
				'requires_questionnaire' => 1,
				'deliverable'            => 'document',
				'intro'                  => __( 'We prepare the financial consent order that makes your agreement legally binding once the court approves it.', 'exfiles' ),
			),
		),
		'svc_free' => array(
			__( 'Free 15-minute consultation', 'exfiles' ),
			'free-consultation',
			array(
				'service_type'           => 'free_consultation',
				'is_free'                => 1,
				'requires_booking'       => 1,
				'appointment_minutes'    => 15,
				'requires_questionnaire' => 0,
				'deliverable'            => 'appointment',
				'buy_label'              => __( 'Book a free call', 'exfiles' ),
				'intro'                  => __( 'A short call to talk through your situation and which service would help.', 'exfiles' ),
			),
		),
	);
}
