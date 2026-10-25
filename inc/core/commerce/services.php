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

	// Optional add-on, e.g. a written report or a follow-up consultation.
	$addon       = null;
	$addon_price = (float) $get( 'addon_price', 0 );
	$addon_name  = trim( (string) $get( 'addon_name', '' ) );
	if ( ! $free && '' !== $addon_name && $addon_price > 0 ) {
		$addon_type = 'consultation' === $get( 'addon_type', 'report' ) ? 'consultation' : 'report';
		$addon_mins = (int) $get( 'addon_minutes', 60 );
		// A consultation can also be bought on its own after the report, as a separate
		// product with its own price (October 2026); the same price unless set.
		$later      = (float) $get( 'addon_later_price', 0 );
		$addon      = array(
			'name'        => $addon_name,
			'price'       => $addon_price,
			'pence'       => (int) round( $addon_price * 100 ),
			'later_pence' => (int) round( ( $later > 0 ? $later : $addon_price ) * 100 ),
			'type'        => $addon_type,
			'minutes'     => $addon_mins > 0 ? $addon_mins : 60,
			'description' => (string) $get( 'addon_description', '' ),
		);
	}

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
		'immediate'     => ! $free && (bool) $get( 'starts_immediately', 0 ),
		'addon'         => $addon,
		// Deadlines (October 2026): the questionnaire is needed this many hours before
		// an appointment, and a written report goes out this many days after it's received.
		'q_due_hours'   => (bool) $get( 'requires_booking', 0 ) && (bool) $get( 'requires_questionnaire', 0 ) ? max( 0, (int) $get( 'questionnaire_due_hours', 24 ) ) : 0,
		'report_days'   => max( 0, (int) $get( 'report_days', 'check_your_deal' === $get( 'service_type', 'other' ) ? 7 : 0 ) ),
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
 * An amount in pence as "£125" or "£125.50".
 *
 * @param int $pence Pence.
 */
function exf_money( $pence ) {
	$pence = (int) $pence;
	return '£' . number_format( $pence / 100, 0 === $pence % 100 ? 0 : 2 );
}

/**
 * The VAT line shown with prices.
 */
function exf_vat_note() {
	return exf_portal_copy( 'vat_note' );
}

/**
 * Cancellation wording for a service, as shown at checkout and in the confirmation.
 *
 * @param array|null $service Service, or null for the general policy only.
 */
function exf_cancellation_text( $service = null ) {
	$text = exf_portal_copy( 'cancel_policy' );
	if ( $service && ! empty( $service['immediate'] ) ) {
		$text .= ' ' . str_replace( '{service}', $service['name'], exf_portal_copy( 'cancel_immediate' ) );
	}
	return trim( $text );
}

/**
 * Where a "Buy" or "Book" button goes for a service.
 *
 * The buy page: the basket for visitors who aren't logged in, choosing the Ex File
 * for clients who are.
 *
 * @param int $page_id Service page.
 */
function exf_service_buy_url( $page_id ) {
	// Logged in: choose the Ex File and pay. Not logged in: the basket, then
	// "Create your Ex File", then payment (see basket.php).
	return add_query_arg( 'service', (int) $page_id, exf_page_url( 'buy' ) );
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
 * The free 15-minute consultation was dropped at the client's request (people
 * unsure what they need use the enquiry form). Free bookable services still work:
 * set "Free" in a service page's Service settings to bring one back.
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
				'price'                  => 275,
				'addon_name'             => __( 'Written report of the advice', 'exfiles' ),
				'addon_price'            => 125,
				'addon_type'             => 'report',
				'intro'                  => __( 'Gain clarity on your financial claims with a 1-hour virtual consultation and the option to receive a written advisory report.', 'exfiles' ),
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
				'price'                  => 300,
				'starts_immediately'     => 1,
				'addon_name'             => __( 'Virtual consultation after your report', 'exfiles' ),
				'addon_price'            => 200,
				'addon_type'             => 'consultation',
				'addon_minutes'          => 60,
				'intro'                  => __( 'Review your existing agreement to ensure it is a fair and legally sound resolution for your specific circumstances.', 'exfiles' ),
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
				'price'                  => 900,
				'starts_immediately'     => 1,
				'intro'                  => exf_make_it_legal_intro(),
			),
		),
	);
}

/*
 * One-off tidy-up: the free consultation page that earlier versions created is
 * switched to draft (not deleted), so it can be republished if it's wanted again.
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'exf_free_consult_retired' ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		update_option( 'exf_free_consult_retired', time(), false );
		$id = (int) get_option( 'exf_page_svc_free' );
		if ( $id && 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'draft',
				)
			);
		}
		delete_option( 'exf_page_svc_free' );
	}
);

/*
 * One-off (1.7.32): the client's prices, add-ons and cancellation settings (October
 * 2026), applied once to the existing service pages. Prices are set to her figures;
 * other settings are only filled where empty.
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'exf_service_prices_2026_10c' ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		update_option( 'exf_service_prices_2026_10c', time(), false );
		$by_type = array();
		foreach ( exf_default_services() as $def ) {
			$by_type[ $def[2]['service_type'] ] = $def[2];
		}
		foreach ( exf_services() as $s ) {
			$def = $by_type[ $s['type'] ] ?? null;
			if ( ! $def || $s['free'] ) {
				continue;
			}
			$set = array();
			foreach ( array( 'price', 'starts_immediately', 'addon_name', 'addon_price', 'addon_type', 'addon_minutes' ) as $k ) {
				// The prices are the client's own figures, so they replace any placeholder
				// test prices; the rest only fills gaps.
				if ( ! isset( $def[ $k ] ) ) {
					continue;
				}
				$fill = 'price' === $k || 'addon_price' === $k ? (string) get_post_meta( $s['id'], $k, true ) !== (string) $def[ $k ] : in_array( get_post_meta( $s['id'], $k, true ), array( '', null, false, '0', 0 ), true );
				if ( $fill ) {
					update_post_meta( $s['id'], $k, $def[ $k ] );
					$set[] = $k;
				}
			}
			// A note such as "Including VAT" would contradict the not-VAT-registered line.
			if ( false !== stripos( (string) get_post_meta( $s['id'], 'price_note', true ), 'VAT' ) ) {
				update_post_meta( $s['id'], 'price_note', '' );
				$set[] = 'price_note (cleared)';
			}
			if ( $set ) {
				exf_audit( 'service_settings', sprintf( 'Filled in %s for %s (prices and add-ons agreed October 2026).', implode( ', ', $set ), $s['name'] ), 'post', $s['id'] );
			}
		}
	}
);

/**
 * Make It Legal's description. Kept general (October 2026): a consent order is the
 * usual document, but the service may cover other court documents in future.
 */
function exf_make_it_legal_intro() {
	return __( 'Make your agreement legally binding. We prepare the documents the court needs to approve it, such as a financial consent order.', 'exfiles' );
}

/*
 * One-off (October 2026): Make It Legal's description no longer promises only a
 * consent order. Changed only if it still has the old default wording.
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'exf_mil_wording_2026_10' ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		update_option( 'exf_mil_wording_2026_10', time(), false );
		foreach ( exf_services() as $s ) {
			if ( 'make_it_legal' === $s['type'] && 'Transform your agreement into a legally binding financial consent order.' === trim( $s['intro'] ) ) {
				update_post_meta( $s['id'], 'intro', exf_make_it_legal_intro() );
				exf_audit( 'service_settings', 'Updated the Make It Legal description so it isn’t limited to consent orders.', 'post', $s['id'] );
			}
		}
	}
);
