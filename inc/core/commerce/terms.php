<?php
/**
 * Terms of Business at checkout, the 14-day cancellation period, and the legal pages
 * (client updates, 29 October 2026).
 *
 *  - Terms of Business: shown in a scroll box at checkout, which must be ticked. The
 *    text is the "Terms of Business" page (/terms-of-business/), so it's edited in one
 *    place; that page has a "Download or print" button, and the footer links it.
 *  - Early start (Terms of Business section 8.2): nothing starts during the 14-day
 *    cancellation period unless the client ticks the optional, unticked early-start
 *    box. Without it, appointments can only be booked from the day after the period
 *    ends, and a written report is due a set time after the period ends.
 *  - Reminder: someone who starts a booking but doesn't pay may get one reminder
 *    email, unless they tick "don't send me a reminder" (see exf_send_unpaid_reminders()).
 *  - The Terms of Business page is created, and the privacy policy brought up to the
 *    29 October version, once each (see exf_legal_pages_install()).
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_LEGAL_PAGES_VERSION = '2026-10-29';
const EXF_COOLING_OFF_DAYS    = 14;

/* -------------------------------------------------------------------------
 * The cancellation period
 * ---------------------------------------------------------------------- */

/**
 * When the 14-day cancellation period ends for a contract made at $from: the start
 * of the 15th day after the day the contract is made (UK time).
 *
 * @param int $from When the contract is made (a timestamp; now if 0).
 * @return int Timestamp.
 */
function exf_cooling_off_end( $from = 0 ) {
	$day = ( new DateTimeImmutable( '@' . ( $from ? (int) $from : time() ) ) )->setTimezone( wp_timezone() )->setTime( 0, 0 );
	return $day->modify( '+' . ( EXF_COOLING_OFF_DAYS + 1 ) . ' days' )->getTimestamp();
}

/**
 * When an order's cancellation period ends, or 0 if it doesn't apply (free, or the
 * client asked for an early start).
 *
 * @param int $order_id Order.
 */
function exf_order_cooling_off_end( $order_id ) {
	if ( get_post_meta( $order_id, 'immediate_consent', true ) || ! (int) get_post_meta( $order_id, 'amount_pence', true ) ) {
		return 0;
	}
	$paid = (int) get_post_meta( $order_id, 'paid_at', true );
	return exf_cooling_off_end( $paid ? $paid : (int) get_post_timestamp( $order_id ) );
}

/**
 * Whether a service offers (and needs) the early-start choice: every paid service.
 *
 * @param array $service From exf_service().
 */
function exf_service_has_cooling_off( array $service ) {
	return empty( $service['free'] );
}

/* -------------------------------------------------------------------------
 * Checkout: early start, Terms of Business, reminder opt-out
 * ---------------------------------------------------------------------- */

/**
 * The Terms of Business page, if published.
 *
 * @return WP_Post|null
 */
function exf_terms_page() {
	$page = get_page_by_path( 'terms-of-business' );
	return $page && 'publish' === $page->post_status ? $page : null;
}

/**
 * The Terms of Business address ('' if there's no page).
 */
function exf_terms_url() {
	$page = exf_terms_page();
	return $page ? get_permalink( $page ) : '';
}

/**
 * Checkout values from a posted form (or a saved basket).
 *
 * @param array $src Posted values or a basket.
 * @return array { consent, terms, no_reminders }
 */
function exf_checkout_choices( array $src ) {
	return array(
		'consent'      => ! empty( $src['immediate_consent'] ) || ! empty( $src['consent'] ),
		'terms'        => ! empty( $src['accept_terms'] ) || ! empty( $src['terms'] ),
		'no_reminders' => ! empty( $src['no_reminders'] ),
	);
}

/**
 * Check the checkout choices for a service and time.
 *
 * @param array $service From exf_service().
 * @param array $choices From exf_checkout_choices().
 * @param int   $slot    Appointment start, or 0.
 * @return array Errors (field => message).
 */
function exf_checkout_check( array $service, array $choices, $slot ) {
	$errors = array();
	if ( exf_terms_page() && empty( $choices['terms'] ) ) {
		$errors['accept_terms'] = __( 'Please read the Terms of Business and tick to accept them.', 'exfiles' );
	}
	if ( $slot && exf_service_has_cooling_off( $service ) && empty( $choices['consent'] ) && $slot < exf_cooling_off_end() ) {
		$errors['slot'] = sprintf(
			/* translators: %s: date */
			__( 'That time is within your 14-day cancellation period. Tick the box to ask us to start early, or choose a time from %s.', 'exfiles' ),
			wp_date( 'l j F', exf_cooling_off_end() )
		);
	}
	return $errors;
}

/**
 * The "When we start" choice, shown before the times so it decides which can be picked.
 *
 * @param array $service From exf_service().
 * @param bool  $checked Box ticked.
 * @param array $errors  Errors.
 */
function exf_checkout_start_html( array $service, $checked, array $errors = array() ) {
	if ( ! exf_service_has_cooling_off( $service ) ) {
		return;
	}
	$end = exf_cooling_off_end();
	?>
	<fieldset class="gq-services buy-step buy-terms" data-early-start>
		<legend class="gq-label"><?php esc_html_e( 'Your 14-day right to cancel', 'exfiles' ); ?></legend>
		<p class="field-note"><?php echo esc_html( exf_cancellation_text( $service ) ); ?></p>
		<p class="field-note">
			<?php
			if ( $service['booking'] ) {
				/* translators: %s: date */
				echo esc_html( sprintf( __( "If you don't ask us to start early, you can choose a time from %s onwards.", 'exfiles' ), wp_date( 'l j F', $end ) ) );
			} elseif ( ! empty( $service['report_days'] ) ) {
				/* translators: 1: days, 2: date */
				echo esc_html( sprintf( __( "If you don't ask us to start early, we'll start once the cancellation period ends on %2\$s, and send your report within %1\$d days of then (or of receiving your questionnaire, if later).", 'exfiles' ), (int) $service['report_days'], wp_date( 'l j F', $end ) ) );
			} else {
				/* translators: %s: date */
				echo esc_html( sprintf( __( "If you don't ask us to start early, we'll start once the cancellation period ends on %s.", 'exfiles' ), wp_date( 'l j F', $end ) ) );
			}
			?>
		</p>
		<label class="gq-option gq-option--check gq-consent"><input type="checkbox" name="immediate_consent" value="1" <?php checked( $checked ); ?>><span><?php echo esc_html( exf_portal_copy( 'immediate_consent' ) ); ?></span></label>
		<?php if ( exf_portal_copy( 'cancel_immediate' ) ) : ?>
			<p class="field-note"><?php echo esc_html( str_replace( '{service}', $service['name'], exf_portal_copy( 'cancel_immediate' ) ) ); ?></p>
		<?php endif; ?>
	</fieldset>
	<?php if ( $service['booking'] ) : ?>
		<script>
		( function () {
			// Times inside the cancellation period can only be chosen with an early start.
			var box = document.querySelector( '[data-early-start] [name="immediate_consent"]' );
			if ( ! box ) { return; }
			function update() {
				document.querySelectorAll( '[data-slot-picker] [data-early]' ).forEach( function ( input ) {
					input.disabled = ! box.checked;
					if ( ! box.checked && input.checked ) { input.checked = false; }
					input.closest( '.slot' ).classList.toggle( 'is-disabled', ! box.checked );
				} );
				document.querySelectorAll( '[data-early-note]' ).forEach( function ( n ) { n.hidden = box.checked; } );
			}
			box.addEventListener( 'change', update );
			document.addEventListener( 'DOMContentLoaded', update );
			update();
		}() );
		</script>
	<?php endif; ?>
	<?php
}

/**
 * The Terms of Business scroll box and tick, and (for new visitors) the reminder notice.
 *
 * @param array $values   accept_terms, no_reminders (bools).
 * @param array $errors   Errors.
 * @param bool  $reminder Show the reminder notice and opt-out (visitors who aren't clients yet).
 */
function exf_checkout_terms_html( array $values, array $errors = array(), $reminder = false ) {
	$page = exf_terms_page();
	?>
	<?php if ( $page ) : ?>
		<fieldset class="gq-services buy-step buy-terms buy-tob">
			<legend class="gq-label"><?php esc_html_e( 'Terms of Business', 'exfiles' ); ?></legend>
			<div class="tob-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Terms of Business', 'exfiles' ); ?>">
				<?php echo wp_kses_post( apply_filters( 'exf_terms_scroll_content', do_blocks( $page->post_content ) ) ); ?>
			</div>
			<p class="field-note">
				<a href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open, download or print the Terms of Business', 'exfiles' ); ?></a>
			</p>
			<label class="gq-option gq-option--check gq-consent">
				<input type="checkbox" name="accept_terms" value="1" required <?php checked( ! empty( $values['accept_terms'] ) ); ?>>
				<span><?php echo esc_html( str_replace( '{email}', exf_contact_email(), exf_portal_copy( 'terms_accept' ) ) ); ?></span>
			</label>
			<?php if ( isset( $errors['accept_terms'] ) ) : ?>
				<p class="field-error"><?php echo esc_html( $errors['accept_terms'] ); ?></p>
			<?php endif; ?>
		</fieldset>
	<?php endif; ?>
	<?php if ( $reminder ) : ?>
		<div class="buy-step buy-reminder">
			<p class="field-note"><?php echo esc_html( exf_portal_copy( 'reminder_notice' ) ); ?></p>
			<label class="gq-option gq-option--check"><input type="checkbox" name="no_reminders" value="1" <?php checked( ! empty( $values['no_reminders'] ) ); ?>><span><?php echo esc_html( exf_portal_copy( 'reminder_optout' ) ); ?></span></label>
		</div>
	<?php endif; ?>
	<?php
}

/**
 * The business's contact email for client-facing text.
 */
function exf_contact_email() {
	$email = function_exists( 'exf_option' ) ? (string) exf_option( 'contact_email', '' ) : '';
	return is_email( $email ) ? $email : 'info@theexfiles.co.uk';
}

/**
 * Cancellation information for an order's confirmation email: the cancellation
 * period, whether they asked for an early start, and where the Terms of Business
 * and cancellation form are (Terms of Business section 3.5).
 *
 * @param int $order_id Order.
 */
function exf_order_cancellation_text( $order_id ) {
	$lines = array();
	$end   = exf_order_cooling_off_end( $order_id );
	if ( get_post_meta( $order_id, 'immediate_consent', true ) ) {
		$lines[] = __( 'You asked us to start within your 14-day cancellation period. If you cancel after we’ve started, you pay only for the work done up to then. Once the service has been fully provided, you can no longer cancel.', 'exfiles' );
	} elseif ( $end ) {
		/* translators: %s: date */
		$lines[] = sprintf( __( 'You can cancel without giving a reason until the end of %s. We won’t start work before then.', 'exfiles' ), wp_date( 'l j F Y', $end - DAY_IN_SECONDS ) );
	}
	$url = exf_terms_url();
	if ( $url ) {
		/* translators: 1: email, 2: link */
		$lines[] = sprintf( __( 'To cancel, email %1$s. Our Terms of Business, which you accepted when you booked and which include a cancellation form you can use, are here: %2$s', 'exfiles' ), exf_contact_email(), $url );
	}
	return implode( "\n\n", $lines );
}

/* -------------------------------------------------------------------------
 * The Terms of Business page: a download / print button
 * ---------------------------------------------------------------------- */

add_filter(
	'the_content',
	function ( $content ) {
		$page = exf_terms_page();
		if ( ! $page || ! is_page( $page->ID ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$button = '<p class="legal-print no-print"><button type="button" class="btn outline" onclick="window.print()">' . esc_html__( 'Download or print (save as PDF)', 'exfiles' ) . '</button></p>';
		return $button . $content;
	},
	20
);

/* -------------------------------------------------------------------------
 * Installing the legal pages
 * ---------------------------------------------------------------------- */

/**
 * Block content of a bundled legal page.
 *
 * @param string $name privacy-policy or terms-of-business.
 */
function exf_legal_page_content( $name ) {
	$file = EXF_CORE_DIR . '/content/' . sanitize_file_name( $name ) . '.html';
	return is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
}

/**
 * Create the Terms of Business page, and bring the privacy policy up to date, once
 * per version. A page someone has edited since the theme last wrote it is left alone.
 */
function exf_legal_pages_install() {
	if ( EXF_LEGAL_PAGES_VERSION === get_option( 'exf_legal_pages_version' ) || ! exf_lock( 'legal_pages', 60 ) ) {
		return;
	}
	$written = (array) get_option( 'exf_legal_pages_written', array() );

	// Terms of Business.
	$terms   = get_page_by_path( 'terms-of-business' );
	$content = exf_legal_page_content( 'terms-of-business' );
	if ( $content ) {
		if ( ! $terms ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Terms of Business', 'exfiles' ),
					'post_name'    => 'terms-of-business',
					'post_content' => $content,
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$written['terms-of-business'] = md5( (string) get_post_field( 'post_content', $id ) );
			}
		} elseif ( isset( $written['terms-of-business'] ) && md5( $terms->post_content ) === $written['terms-of-business'] ) {
			wp_update_post( array( 'ID' => $terms->ID, 'post_content' => $content ) );
			$written['terms-of-business'] = md5( (string) get_post_field( 'post_content', $terms->ID ) );
		}
	}

	// Privacy policy: the 29 October version replaces WordPress's placeholder or the
	// first draft (as published on 28 October), but not anything else.
	$content = exf_legal_page_content( 'privacy-policy' );
	$pid     = (int) get_option( 'wp_page_for_privacy_policy' );
	$current = $pid ? (string) get_post_field( 'post_content', $pid ) : '';
	$replace = ! $pid
		|| false !== strpos( $current, 'privacy-policy-tutorial' )
		|| false !== strpos( $current, 'Accounts are created only when purchasing a service; we do not require email-address verification' )
		|| ( isset( $written['privacy-policy'] ) && md5( $current ) === $written['privacy-policy'] );
	if ( $content && $replace ) {
		if ( $pid && 'page' === get_post_type( $pid ) ) {
			wp_update_post(
				array(
					'ID'           => $pid,
					'post_content' => $content,
					'post_status'  => 'publish',
				)
			);
		} else {
			$pid = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Privacy Policy', 'exfiles' ),
					'post_name'    => 'privacy-policy',
					'post_content' => $content,
				)
			);
			if ( $pid && ! is_wp_error( $pid ) ) {
				update_option( 'wp_page_for_privacy_policy', $pid );
			}
		}
		if ( $pid && ! is_wp_error( $pid ) ) {
			$written['privacy-policy'] = md5( (string) get_post_field( 'post_content', $pid ) );
		}
	}

	// Checkout wording saved with the old defaults (work starting "straight away" was
	// required) moves to the new ones; wording she has changed is kept.
	$old = array(
		'cancel_policy'     => "You have the right to cancel within 14 days, unless you ask us to begin work before that period ends. If you don't attend your appointment, you won't be eligible for a refund.",
		'cancel_immediate'  => '{service} is an instruction for us to begin work straight away, before the 14-day cancellation period ends. If you wish to cancel, please let us know as soon as possible: we may charge a reasonable amount for any work already undertaken.',
		'immediate_consent' => 'I ask The Ex Files to begin work straight away, and I understand how this affects my right to cancel.',
	);
	if ( function_exists( 'get_field' ) && function_exists( 'update_field' ) && defined( 'EXF_PORTAL_ID' ) ) {
		foreach ( $old as $key => $text ) {
			if ( trim( (string) get_field( $key, EXF_PORTAL_ID ) ) === $text ) {
				update_field( $key, '', EXF_PORTAL_ID );
			}
		}
	}

	update_option( 'exf_legal_pages_written', $written, false );
	update_option( 'exf_legal_pages_version', EXF_LEGAL_PAGES_VERSION, false );
	exf_unlock( 'legal_pages' );
}
add_action( 'init', 'exf_legal_pages_install', 60 );

/* -------------------------------------------------------------------------
 * Reminder for people who start a booking but don't pay
 * ---------------------------------------------------------------------- */

add_action( 'exf_hourly', 'exf_send_unpaid_reminders' );

/**
 * One reminder, a day after someone gave their details at checkout without paying,
 * unless they ticked "don't send me a reminder". Their details are deleted after the
 * unpaid sign-up period (7 days by default), so the reminder says when.
 *
 * @return int Reminders sent.
 */
function exf_send_unpaid_reminders() {
	$after = (int) apply_filters( 'exf_unpaid_reminder_hours', 24 );
	$users = get_users(
		array(
			'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'exf_status',
					'value' => EXF_STATUS_AWAITING,
				),
				array(
					'key'     => 'exf_unpaid_reminded',
					'compare' => 'NOT EXISTS',
				),
			),
			'date_query'   => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - $after * HOUR_IN_SECONDS ) ) ),
			'fields'       => 'ID',
			'number'       => 50,
			'role__not_in' => array( 'administrator', 'ex_files_manager' ),
		)
	);
	$sent = 0;
	foreach ( $users as $uid ) {
		$uid = (int) $uid;
		update_user_meta( $uid, 'exf_unpaid_reminded', time() ); // Once only, whatever happens next.
		$pending = get_user_meta( $uid, 'exf_pending_basket', true );
		if ( ! is_array( $pending ) || ! empty( $pending['no_reminders'] ) ) {
			continue;
		}
		$service = exf_service( (int) ( $pending['service'] ?? 0 ) );
		$ex_file = exf_first_ex_file( $uid );
		if ( ! $service || ! $service['sellable'] || ! $ex_file || 'clear' !== exf_ex_file_status( $ex_file ) ) {
			continue;
		}
		$user  = get_userdata( $uid );
		$token = wp_generate_password( 32, false, false );
		update_user_meta( $uid, 'exf_resume_token', wp_hash( $token ) );
		$days  = max( 1, (int) exf_setting( 'unverified_retention_days', 7 ) );
		$until = (int) strtotime( $user->user_registered . ' UTC' ) + $days * DAY_IN_SECONDS;
		exf_send_email(
			'unpaid_reminder',
			$user->user_email,
			array(
				'first_name'   => $user->first_name,
				'service'      => $service['name'],
				'link'         => add_query_arg(
					array(
						'resume' => $uid . '-' . $token,
					),
					exf_page_url( 'buy' )
				),
				'delete_date'  => wp_date( 'l j F', $until ),
			)
		);
		++$sent;
	}
	return $sent;
}

/**
 * The reminder email's link: reopen the basket in this browser and go to payment.
 *
 * @param string $value "{user}-{token}".
 * @return bool Whether it worked (the caller redirects either way).
 */
function exf_basket_resume( $value ) {
	if ( ! preg_match( '/^(\d+)-([A-Za-z0-9]{32})$/', (string) $value, $m ) ) {
		return false;
	}
	$uid  = (int) $m[1];
	$hash = (string) get_user_meta( $uid, 'exf_resume_token', true );
	if ( ! $hash || ! hash_equals( $hash, wp_hash( $m[2] ) ) || EXF_STATUS_AWAITING !== exf_user_status( $uid ) ) {
		return false;
	}
	$pending = get_user_meta( $uid, 'exf_pending_basket', true );
	$service = is_array( $pending ) ? exf_service( (int) ( $pending['service'] ?? 0 ) ) : null;
	$ex_file = exf_first_ex_file( $uid );
	if ( ! $service || ! $ex_file || 'clear' !== exf_ex_file_status( $ex_file ) ) {
		return false;
	}
	$slot = (int) ( $pending['slot'] ?? 0 );
	if ( $service['booking'] && ( ! $slot || ! exf_slot_is_free( $slot, $service['minutes'] ) || ( empty( $pending['consent'] ) && $slot < exf_cooling_off_end() ) ) ) {
		$slot = 0; // Gone meanwhile: they choose another.
	}
	exf_basket_save(
		array(
			'service'      => (int) $service['id'],
			'slot'         => $slot,
			'addon'        => ! empty( $pending['addon'] ),
			'consent'      => ! empty( $pending['consent'] ),
			'terms'        => false, // Shown and ticked again before paying.
			'no_reminders' => ! empty( $pending['no_reminders'] ),
			'user_id'      => $uid,
			'ex_file'      => $ex_file,
			'error'        => $service['booking'] && ! $slot ? __( 'The time you chose is no longer available. Please choose another.', 'exfiles' ) : '',
		)
	);
	return true;
}
