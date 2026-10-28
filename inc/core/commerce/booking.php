<?php
/**
 * Appointment booking.
 *
 * Free slots come from the weekly availability in Ex Files → Settings → Bookings,
 * minus: blocked dates, slots inside the minimum notice period, existing bookings,
 * slots being held while someone pays, and busy times in the solicitor's calendar
 * (read from its private iCal address, so no Google login is needed).
 *
 * Times are worked out in the site's time zone (Settings → General) and stored as
 * Unix timestamps.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_HOLD_MINUTES = 30; // Matches Stripe's shortest checkout expiry.

/**
 * Booking settings with defaults.
 */
function exf_booking_settings() {
	$availability = exf_setting( 'availability', array() );
	if ( ! $availability ) {
		// Default: weekdays 9:30–5pm.
		$availability = array();
		foreach ( array( 'mon', 'tue', 'wed', 'thu', 'fri' ) as $d ) {
			$availability[] = array(
				'day'   => $d,
				'start' => '09:30',
				'end'   => '17:00',
			);
		}
	}
	$last = trim( (string) exf_setting( 'booking_last_start', '16:00' ) );
	return array(
		'availability' => $availability,
		'notice_hours' => max( 0, (int) exf_setting( 'min_booking_notice_hours', 24 ) ),
		'days_ahead'   => max( 1, (int) exf_setting( 'booking_days_ahead', 30 ) ),
		'buffer'       => max( 0, (int) exf_setting( 'booking_buffer_minutes', 30 ) ),
		'interval'     => max( 5, (int) exf_setting( 'booking_interval_minutes', 30 ) ),
		'last_start'   => preg_match( '/^\d{1,2}:\d{2}$/', $last ) ? $last : '',
		'blocked'      => (string) exf_setting( 'blocked_dates', '' ),
		'ical_urls'    => exf_calendar_urls( (string) exf_setting( 'calendar_ical_url', '' ) ),
	);
}

/**
 * Calendar addresses from the setting: one per line. Apple Calendar's public
 * "webcal://" links are read over https.
 *
 * @param string $text Setting text.
 * @return string[]
 */
function exf_calendar_urls( $text ) {
	$out = array();
	foreach ( preg_split( '/[\s,]+/', trim( $text ) ) as $url ) {
		$url = preg_replace( '#^webcals?://#i', 'https://', trim( $url ) );
		if ( preg_match( '#^https?://#i', $url ) ) {
			$out[] = esc_url_raw( $url );
		}
	}
	return array_values( array_unique( array_filter( $out ) ) );
}

/**
 * How appointments work, for confirmations, reminders and calendar invites: the
 * "Appointment details" text, then the meeting link on its own line.
 *
 * @param int $order_id Order, for its own Teams meeting link (teams.php), or 0.
 */
function exf_meeting_details( $order_id = 0 ) {
	$text = trim( (string) exf_setting( 'meeting_details', '' ) );
	if ( '' === $text ) {
		$text = __( 'Your appointment will take place by video call on Microsoft Teams. Please note that meetings may be recorded.', 'exfiles' );
	}
	$link = exf_meeting_link( $order_id );
	if ( $link ) {
		$text .= "\n\n" . __( 'Join the meeting at your appointment time:', 'exfiles' ) . "\n" . $link;
	}
	return $text;
}

/**
 * The video meeting link for an appointment: its own Teams meeting if it has one
 * (teams.php), otherwise the fixed link in Settings → Bookings, or ''.
 *
 * @param int $order_id Order, or 0 for the fixed link.
 */
function exf_meeting_link( $order_id = 0 ) {
	if ( $order_id && function_exists( 'exf_teams_link' ) && exf_teams_link( $order_id ) ) {
		return esc_url_raw( exf_teams_link( $order_id ) );
	}
	$link = trim( (string) exf_setting( 'meeting_link', '' ) );
	return preg_match( '#^https://#i', $link ) ? esc_url_raw( $link ) : '';
}

/**
 * Blocked dates as a set of Y-m-d strings. One per line; "date - date" for a range.
 *
 * @param string $text Setting text.
 * @return array<string,bool>
 */
function exf_blocked_dates( $text ) {
	$out = array();
	foreach ( preg_split( '/\R/', $text ) as $line ) {
		$parts = array_map( 'trim', preg_split( '/\s+(?:-|–|to)\s+/u', trim( $line ) ) );
		$from  = exf_norm_dob( $parts[0] ?? '' );
		$to    = exf_norm_dob( $parts[1] ?? '' );
		if ( ! $from ) {
			continue;
		}
		$to = $to && $to >= $from ? $to : $from;
		for ( $d = $from; $d <= $to; $d = gmdate( 'Y-m-d', strtotime( $d . ' +1 day' ) ) ) {
			$out[ $d ] = true;
			if ( count( $out ) > 800 ) {
				break 2;
			}
		}
	}
	return $out;
}

/**
 * Free slots for an appointment length.
 *
 * @param int      $minutes      Appointment length.
 * @param int|null $now          Current time (for tests).
 * @param int      $ignore_order Order whose own hold shouldn't count.
 * @return array<string,int[]> 'Y-m-d' => [ start timestamps ].
 */
function exf_available_slots( $minutes, $now = null, $ignore_order = 0 ) {
	$minutes = max( 5, (int) $minutes );
	$now     = $now ?? time();
	$s       = exf_booking_settings();
	$tz      = wp_timezone();
	$blocked = exf_blocked_dates( $s['blocked'] );
	$earliest = $now + $s['notice_hours'] * HOUR_IN_SECONDS;
	$latest   = $now + $s['days_ahead'] * DAY_IN_SECONDS;
	$busy     = exf_busy_periods( $now, $latest + DAY_IN_SECONDS, $ignore_order );
	$buffer   = $s['buffer'] * MINUTE_IN_SECONDS;
	$step     = $s['interval'] * MINUTE_IN_SECONDS;

	$by_day = array();
	foreach ( (array) $s['availability'] as $row ) {
		$day = strtolower( substr( (string) ( $row['day'] ?? '' ), 0, 3 ) );
		if ( preg_match( '/^\d{1,2}:\d{2}/', (string) ( $row['start'] ?? '' ) ) && preg_match( '/^\d{1,2}:\d{2}/', (string) ( $row['end'] ?? '' ) ) ) {
			$by_day[ $day ][] = array( substr( $row['start'], 0, 5 ), substr( $row['end'], 0, 5 ) );
		}
	}

	$slots = array();
	$date  = new DateTimeImmutable( '@' . $now );
	$date  = $date->setTimezone( $tz )->setTime( 0, 0 );
	$end   = ( new DateTimeImmutable( '@' . $latest ) )->setTimezone( $tz );

	while ( $date <= $end ) {
		$ymd = $date->format( 'Y-m-d' );
		$dow = strtolower( $date->format( 'D' ) );
		if ( empty( $blocked[ $ymd ] ) && ! empty( $by_day[ $dow ] ) ) {
			foreach ( $by_day[ $dow ] as $window ) {
				list( $wh, $wm ) = array_map( 'intval', explode( ':', $window[0] ) );
				list( $eh, $em ) = array_map( 'intval', explode( ':', $window[1] ) );
				$start = $date->setTime( $wh, $wm )->getTimestamp();
				$stop  = $date->setTime( $eh, $em )->getTimestamp();
				if ( $s['last_start'] ) {
					list( $lh, $lm ) = array_map( 'intval', explode( ':', $s['last_start'] ) );
					$last_start      = $date->setTime( $lh, $lm )->getTimestamp();
				} else {
					$last_start = $stop;
				}
				// Start times on a regular grid (every 30 minutes by default). The gap
				// between appointments is kept by treating each booking as busy for the
				// gap either side, so times next to a booking drop out.
				for ( $t = $start; $t + $minutes * MINUTE_IN_SECONDS <= $stop && $t <= $last_start; $t += $step ) {
					if ( $t < $earliest || $t > $latest ) {
						continue;
					}
					if ( ! exf_overlaps( $t - $buffer, $t + $minutes * MINUTE_IN_SECONDS + $buffer, $busy ) ) {
						$slots[ $ymd ][] = $t;
					}
				}
			}
		}
		$date = $date->modify( '+1 day' );
	}
	return $slots;
}

/**
 * Whether a period overlaps any busy period.
 *
 * @param int     $start Start.
 * @param int     $end   End.
 * @param int[][] $busy  [ [start, end], … ].
 */
function exf_overlaps( $start, $end, array $busy ) {
	foreach ( $busy as $b ) {
		if ( $start < $b[1] && $end > $b[0] ) {
			return true;
		}
	}
	return false;
}

/**
 * Whether a slot is still free (re-checked just before booking).
 *
 * @param int $start        Start timestamp.
 * @param int $minutes      Length.
 * @param int $ignore_order Order whose own hold shouldn't count.
 */
function exf_slot_is_free( $start, $minutes, $ignore_order = 0 ) {
	$slots = exf_available_slots( $minutes, null, $ignore_order );
	return in_array( (int) $start, $slots[ wp_date( 'Y-m-d', $start ) ] ?? array(), true );
}

/**
 * Busy periods: confirmed bookings, live holds, and the solicitor's calendar.
 *
 * @param int  $from     From timestamp.
 * @param int  $to       To timestamp.
 * @param int  $ignore   Order to leave out.
 * @param bool $calendar Include the solicitor's calendar (false: only bookings made here).
 * @return int[][]
 */
function exf_busy_periods( $from, $to, $ignore = 0, $calendar = true ) {
	$busy = $calendar ? exf_calendar_busy( $from, $to ) : array();

	$orders = get_posts(
		array(
			'post_type'      => 'exf_order',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'booking_start',
					'value'   => array( $from - DAY_IN_SECONDS, $to ),
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	foreach ( $orders as $oid ) {
		if ( (int) $oid === (int) $ignore ) {
			continue;
		}
		$status = (string) get_post_meta( $oid, 'order_status', true );
		$hold   = (int) get_post_meta( $oid, 'hold_expires', true );
		if ( in_array( $status, array( 'paid', 'confirmed' ), true ) || ( 'pending' === $status && $hold > time() ) ) {
			$busy[] = array( (int) get_post_meta( $oid, 'booking_start', true ), (int) get_post_meta( $oid, 'booking_end', true ) );
		}
	}
	return $busy;
}

/**
 * Busy periods from the solicitor's calendars (private or public iCal addresses).
 *
 * Transparent ("free") events and cancelled events are ignored; recurring events
 * are expanded. If the calendar can't be read, the last good copy is used, and
 * nothing is blocked if there has never been one (an admin notice says so).
 *
 * @param int $from From.
 * @param int $to   To.
 * @return int[][]
 */
function exf_calendar_busy( $from, $to ) {
	$busy = array();
	foreach ( exf_booking_settings()['ical_urls'] as $url ) {
		$busy = array_merge( $busy, exf_parse_ical_busy( exf_calendar_fetch( $url ), $from, $to ) );
	}
	return $busy;
}

/**
 * One calendar's iCal text, cached for 10 minutes. Falls back to the last good copy.
 *
 * @param string $url Calendar address.
 */
function exf_calendar_fetch( $url ) {
	$cache_key = 'exf_ical_' . md5( $url );
	$ics       = get_transient( $cache_key );
	if ( false !== $ics ) {
		return (string) $ics;
	}
	$good = (array) get_option( 'exf_ical_last_good_by_url', array() );
	$res  = wp_remote_get(
		$url,
		array(
			'timeout'     => 10,
			'redirection' => 3,
		)
	);
	if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) && false !== strpos( wp_remote_retrieve_body( $res ), 'BEGIN:VCALENDAR' ) ) {
		$ics                       = wp_remote_retrieve_body( $res );
		$good[ md5( $url ) ]       = $ics;
		update_option( 'exf_ical_last_good_by_url', $good, false );
		delete_option( 'exf_ical_error' );
	} else {
		$ics = (string) ( $good[ md5( $url ) ] ?? ( 1 === count( exf_booking_settings()['ical_urls'] ) ? get_option( 'exf_ical_last_good', '' ) : '' ) );
		update_option( 'exf_ical_error', time(), false );
	}
	set_transient( $cache_key, $ics, 10 * MINUTE_IN_SECONDS );
	return (string) $ics;
}

/**
 * Busy periods from iCal text.
 *
 * @param string $ics  Calendar.
 * @param int    $from From.
 * @param int    $to   To.
 * @return int[][]
 */
function exf_parse_ical_busy( $ics, $from, $to ) {
	if ( '' === $ics || ! class_exists( '\Sabre\VObject\Reader' ) ) {
		return array();
	}
	try {
		$cal = \Sabre\VObject\Reader::read( $ics, \Sabre\VObject\Reader::OPTION_FORGIVING );
		$cal = $cal->expand( new DateTime( '@' . $from ), new DateTime( '@' . $to ), wp_timezone() );
	} catch ( \Throwable $e ) {
		return array();
	}

	$busy = array();
	foreach ( $cal->select( 'VEVENT' ) as $event ) {
		if ( isset( $event->TRANSP ) && 'TRANSPARENT' === strtoupper( (string) $event->TRANSP ) ) {
			continue;
		}
		if ( isset( $event->STATUS ) && 'CANCELLED' === strtoupper( (string) $event->STATUS ) ) {
			continue;
		}
		if ( ! isset( $event->DTSTART ) ) {
			continue;
		}
		$start = $event->DTSTART->getDateTime( wp_timezone() )->getTimestamp();
		if ( isset( $event->DTEND ) ) {
			$end = $event->DTEND->getDateTime( wp_timezone() )->getTimestamp();
		} elseif ( ! $event->DTSTART->hasTime() ) {
			$end = $start + DAY_IN_SECONDS;
		} else {
			$end = $start;
		}
		if ( $end > $start ) {
			$busy[] = array( $start, $end );
		}
	}
	return $busy;
}

/* -------------------------------------------------------------------------
 * A simple lock, so two people can't book the same slot at the same moment
 * ---------------------------------------------------------------------- */

/**
 * Take a lock. add_option() is atomic (the option name is unique), so only one
 * request can create it.
 *
 * @param string $name Lock name.
 * @param int    $ttl  Seconds before a stuck lock is ignored.
 */
function exf_lock( $name, $ttl = 30 ) {
	$key = 'exf_lock_' . $name;
	for ( $i = 0; $i < 25; $i++ ) {
		if ( add_option( $key, time() + $ttl, '', 'no' ) ) {
			return true;
		}
		$expires = (int) get_option( $key );
		if ( $expires && $expires < time() ) {
			delete_option( $key );
			continue;
		}
		usleep( 200000 );
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
	return false;
}

/**
 * Release a lock.
 *
 * @param string $name Lock name.
 */
function exf_unlock( $name ) {
	delete_option( 'exf_lock_' . $name );
}

/* -------------------------------------------------------------------------
 * Calendar invites
 * ---------------------------------------------------------------------- */

/**
 * An .ics invite for a booking. Every version of an order's invite has the same UID,
 * so calendars update the existing event: a moved appointment is sent again with a
 * higher SEQUENCE, and a cancelled one with METHOD:CANCEL (see appointments-admin.php).
 *
 * @param int    $order_id Order.
 * @param string $method   REQUEST (new or moved) or CANCEL.
 * @param int    $start    Start, if not the order's current booking (e.g. when cancelled).
 * @param int    $end      End, likewise.
 * @return string
 */
function exf_booking_ics( $order_id, $method = 'REQUEST', $start = 0, $end = 0 ) {
	$cancel  = 'CANCEL' === $method;
	$start   = $start ? (int) $start : (int) get_post_meta( $order_id, 'booking_start', true );
	$end     = $end ? (int) $end : (int) get_post_meta( $order_id, 'booking_end', true );
	$client  = get_userdata( (int) get_post_meta( $order_id, 'client_id', true ) );
	$service = (string) get_post_meta( $order_id, 'service_name', true );
	$org     = exf_notify_emails()[0];
	$details = exf_meeting_details( $order_id );
	$link    = exf_meeting_link( $order_id );
	$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$fmt     = function ( $ts ) {
		return gmdate( 'Ymd\THis\Z', $ts );
	};
	$esc     = function ( $text ) {
		return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\;', '\,', '\n', '\n' ), (string) $text );
	};
	$who     = $client ? $client->display_name : '';
	$lines   = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//The Ex Files//Bookings//EN',
		'METHOD:' . ( $cancel ? 'CANCEL' : 'REQUEST' ),
		'BEGIN:VEVENT',
		'UID:exf-order-' . $order_id . '@' . $host,
		'SEQUENCE:' . (int) get_post_meta( $order_id, 'ics_sequence', true ),
		'DTSTAMP:' . $fmt( time() ),
		'DTSTART:' . $fmt( $start ),
		'DTEND:' . $fmt( $end ),
		'SUMMARY:' . $esc( $service . ( $who ? ' – ' . $who : '' ) ),
		'DESCRIPTION:' . $esc( trim( $service . "\n\n" . $details ) ),
		'ORGANIZER;CN=' . $esc( exf_site_name() ) . ':mailto:' . $org,
	);
	if ( $link ) {
		$lines[] = 'LOCATION:' . $esc( $link );
		$lines[] = 'URL:' . $link;
	}
	if ( $client ) {
		$lines[] = 'ATTENDEE;CN=' . $esc( $who ) . ';ROLE=REQ-PARTICIPANT;RSVP=TRUE:mailto:' . $client->user_email;
	}
	$lines[] = $cancel ? 'STATUS:CANCELLED' : 'STATUS:CONFIRMED';
	$lines[] = 'END:VEVENT';
	$lines[] = 'END:VCALENDAR';

	// Fold long lines at 74 octets, as the standard asks.
	$out = array();
	foreach ( $lines as $line ) {
		while ( strlen( $line ) > 74 ) {
			$cut   = mb_strcut( $line, 0, 74, 'UTF-8' );
			$out[] = $cut;
			$line  = ' ' . substr( $line, strlen( $cut ) );
		}
		$out[] = $line;
	}
	return implode( "\r\n", $out ) . "\r\n";
}

/**
 * The appointment time picker used on the buy page and in the basket.
 *
 * @param array  $slots    From exf_available_slots().
 * @param int    $minutes  Length.
 * @param int    $selected Chosen start, or 0.
 * @param string $error    Error message, or ''.
 */
function exf_render_slot_picker( $slots, $minutes, $selected = 0, $error = '' ) {
	?>
				<fieldset class="buy-step slot-picker" data-slot-picker>
					<legend class="gq-label">
						<?php
						/* translators: %d: minutes */
						echo esc_html( sprintf( __( 'Choose a time (%d minutes)', 'exfiles' ), $minutes ) );
						?>
					</legend>
					<?php if ( ! $slots ) : ?>
						<p><?php esc_html_e( 'Sorry, there are no times available online at the moment. Please contact us and we’ll find a time.', 'exfiles' ); ?></p>
					<?php else : ?>
						<div class="slot-days">
							<?php $exf_n = 0; ?>
							<?php foreach ( $slots as $exf_times ) : ?>
								<details class="slot-day" <?php echo ( 0 === $exf_n || in_array( (int) $selected, $exf_times, true ) ) ? 'open' : ''; ?>>
									<summary><?php echo esc_html( wp_date( 'l j F', $exf_times[0] ) ); ?> <span class="slot-count">
										<?php
										/* translators: %d: number of times */
										echo esc_html( sprintf( _n( '%d time', '%d times', count( $exf_times ), 'exfiles' ), count( $exf_times ) ) );
										?>
									</span></summary>
									<div class="slot-times">
										<?php foreach ( $exf_times as $exf_t ) : ?>
											<label class="slot"><input type="radio" name="slot" value="<?php echo (int) $exf_t; ?>" <?php checked( (int) $selected, $exf_t ); ?>><span><?php echo esc_html( wp_date( 'g:ia', $exf_t ) ); ?></span></label>
										<?php endforeach; ?>
									</div>
								</details>
								<?php ++$exf_n; ?>
							<?php endforeach; ?>
						</div>
						<p class="field-note">
							<?php
							/* translators: %d: hours */
							echo esc_html( sprintf( __( 'Times are UK time. Appointments need at least %d hours’ notice.', 'exfiles' ), exf_booking_settings()['notice_hours'] ) );
							?>
						</p>
					<?php endif; ?>
					<?php if ( $error ) : ?>
						<p class="field-error"><?php echo esc_html( $error ); ?></p>
					<?php endif; ?>
				</fieldset>
	<?php
}

/**
 * Human date and time for a booking, in the site's time zone.
 *
 * @param int $start Start.
 * @param int $end   End.
 */
function exf_booking_label( $start, $end = 0 ) {
	$label = wp_date( 'l j F Y, g:ia', $start );
	return $end ? $label . '–' . wp_date( 'g:ia', $end ) : $label;
}

// Admin warning when the calendar couldn't be read recently.
add_action(
	'admin_notices',
	function () {
		$err = (int) get_option( 'exf_ical_error' );
		if ( ! $err || $err < time() - DAY_IN_SECONDS || ! exf_user_can_manage() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'exf-' ) && 'dashboard' !== $screen->id ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( "The booking calendar couldn't read your calendar recently, so busy times may be out of date. Check the calendar addresses in Ex Files → Settings → Bookings.", 'exfiles' ) . '</p></div>';
	}
);
